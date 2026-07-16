using System.Globalization;
using System.Text;
using System.Text.RegularExpressions;
using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Admin.ManageCategories.Dtos;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;

namespace MiniWebsite.Application.Admin.ManageCategories;

public class AdminManageCategoriesService : IAdminManageCategoriesService
{
    private static readonly string[] CsvHeaders =
    [
        "Business Profile Type",
        "Business Heading",
        "Business Category",
        "Business Category Slug",
        "Product Category",
        "Product Category Slug",
        "Directory Priority",
        "Is Active",
        "Keywords",
        "Tags",
    ];

    private static readonly string[] RequiredHeaderKeys =
    [
        "business profile type",
        "business heading",
        "business category",
        "business category slug",
        "product category",
        "product category slug",
        "directory priority",
        "is active",
        "keywords",
        "tags",
    ];

    private readonly IApplicationDbContext _db;

    public AdminManageCategoriesService(IApplicationDbContext db)
    {
        _db = db;
    }

    public ManageCategoriesMetaDto GetMeta() => new(CsvHeaders);

    public async Task<ApiResult<ManageCategoriesPageDto>> ListAsync(ManageCategoriesQuery query, CancellationToken ct = default)
    {
        query.Page = query.Page < 1 ? 1 : query.Page;
        query.PageSize = query.PageSize is < 1 or > 100 ? 10 : query.PageSize;
        var offset = (query.Page - 1) * query.PageSize;
        var ef = RequireEf();

        try
        {
            await EnsureTableAsync(ef, ct);

            var search = string.IsNullOrWhiteSpace(query.Search) ? null : "%" + query.Search.Trim() + "%";
            int? active = NormalizeActive(query.Active);

            var total = (await ef.Database
                .SqlQueryRaw<CountRow>(
                    @"SELECT COUNT(*) AS Value FROM product_categories c
                      WHERE ({0} IS NULL OR
                             c.business_profile_type LIKE {0} OR
                             c.business_heading LIKE {0} OR
                             c.business_category LIKE {0} OR
                             c.business_category_slug LIKE {0} OR
                             c.product_category LIKE {0} OR
                             c.product_category_slug LIKE {0} OR
                             IFNULL(c.keywords,'') LIKE {0} OR
                             IFNULL(c.tags,'') LIKE {0})
                        AND ({1} IS NULL OR c.is_active = {1})",
                    search ?? (object)DBNull.Value,
                    active.HasValue ? active.Value : DBNull.Value)
                .FirstAsync(ct)).Value;

            var rows = await ef.Database
                .SqlQueryRaw<CategorySqlRow>(
                    @"SELECT c.id AS Id,
                             IFNULL(c.business_profile_type,'') AS BusinessProfileType,
                             IFNULL(c.business_heading,'') AS BusinessHeading,
                             IFNULL(c.business_category,'') AS BusinessCategory,
                             IFNULL(c.business_category_slug,'') AS BusinessCategorySlug,
                             IFNULL(c.product_category,'') AS ProductCategory,
                             IFNULL(c.product_category_slug,'') AS ProductCategorySlug,
                             CAST(IFNULL(c.directory_priority,0) AS SIGNED) AS DirectoryPriority,
                             CAST(IFNULL(c.is_active,0) AS SIGNED) AS IsActiveInt,
                             IFNULL(c.keywords,'') AS Keywords,
                             IFNULL(c.tags,'') AS Tags
                      FROM product_categories c
                      WHERE ({0} IS NULL OR
                             c.business_profile_type LIKE {0} OR
                             c.business_heading LIKE {0} OR
                             c.business_category LIKE {0} OR
                             c.business_category_slug LIKE {0} OR
                             c.product_category LIKE {0} OR
                             c.product_category_slug LIKE {0} OR
                             IFNULL(c.keywords,'') LIKE {0} OR
                             IFNULL(c.tags,'') LIKE {0})
                        AND ({1} IS NULL OR c.is_active = {1})
                      ORDER BY c.directory_priority ASC, c.business_heading ASC, c.business_category ASC, c.product_category ASC
                      LIMIT {2} OFFSET {3}",
                    search ?? (object)DBNull.Value,
                    active.HasValue ? active.Value : DBNull.Value,
                    query.PageSize,
                    offset)
                .ToListAsync(ct);

            return ApiResult<ManageCategoriesPageDto>.Ok(new ManageCategoriesPageDto(
                rows.Select(MapRow).ToList(),
                total,
                query.Page,
                query.PageSize));
        }
        catch (Exception ex)
        {
            return ApiResult<ManageCategoriesPageDto>.Fail("Unable to load categories: " + ex.Message);
        }
    }

    public async Task<ApiResult<ManageCategoryRowDto>> GetAsync(int id, CancellationToken ct = default)
    {
        var ef = RequireEf();
        try
        {
            await EnsureTableAsync(ef, ct);
            var row = await LoadByIdAsync(ef, id, ct);
            if (row == null) return ApiResult<ManageCategoryRowDto>.Fail("Category not found");
            return ApiResult<ManageCategoryRowDto>.Ok(MapRow(row));
        }
        catch (Exception ex)
        {
            return ApiResult<ManageCategoryRowDto>.Fail("Unable to load category: " + ex.Message);
        }
    }

    public async Task<ApiResult<ManageCategoryRowDto>> CreateAsync(UpsertCategoryRequest request, CancellationToken ct = default)
    {
        var validation = Validate(request);
        if (validation != null) return ApiResult<ManageCategoryRowDto>.Fail(validation);

        var ef = RequireEf();
        var data = Normalize(request);

        try
        {
            await EnsureTableAsync(ef, ct);

            var dup = (await ef.Database
                .SqlQueryRaw<CountRow>(
                    @"SELECT COUNT(*) AS Value FROM product_categories
                      WHERE business_category_slug = {0} AND product_category_slug = {1}",
                    data.BusinessCategorySlug,
                    data.ProductCategorySlug)
                .FirstAsync(ct)).Value;
            if (dup > 0) return ApiResult<ManageCategoryRowDto>.Fail("Category with this business/product slug already exists");

            await ef.Database.ExecuteSqlRawAsync(
                @"INSERT INTO product_categories
                    (business_profile_type, business_heading, business_category, business_category_slug,
                     product_category, product_category_slug, directory_priority, is_active, keywords, tags, created_by)
                  VALUES ({0}, {1}, {2}, {3}, {4}, {5}, {6}, {7}, {8}, {9}, {10})",
                [
                    data.BusinessProfileType,
                    data.BusinessHeading,
                    data.BusinessCategory,
                    data.BusinessCategorySlug,
                    data.ProductCategory,
                    data.ProductCategorySlug,
                    data.DirectoryPriority,
                    data.IsActive ? 1 : 0,
                    data.Keywords,
                    data.Tags,
                    data.CreatedBy ?? "admin"
                ],
                ct);

            var id = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT LAST_INSERT_ID() AS Value")
                .FirstAsync(ct)).Value;

            return await GetAsync(id, ct) switch
            {
                { Success: true, Data: { } row } => ApiResult<ManageCategoryRowDto>.Ok(row, "Category added successfully!"),
                var r => r
            };
        }
        catch (Exception ex)
        {
            return ApiResult<ManageCategoryRowDto>.Fail("Error adding category: " + ex.Message);
        }
    }

    public async Task<ApiResult<ManageCategoryRowDto>> UpdateAsync(int id, UpsertCategoryRequest request, CancellationToken ct = default)
    {
        var validation = Validate(request);
        if (validation != null) return ApiResult<ManageCategoryRowDto>.Fail(validation);

        var ef = RequireEf();
        var data = Normalize(request);

        try
        {
            await EnsureTableAsync(ef, ct);

            var exists = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM product_categories WHERE id = {0}", id)
                .FirstAsync(ct)).Value;
            if (exists == 0) return ApiResult<ManageCategoryRowDto>.Fail("Category not found");

            var dup = (await ef.Database
                .SqlQueryRaw<CountRow>(
                    @"SELECT COUNT(*) AS Value FROM product_categories
                      WHERE business_category_slug = {0} AND product_category_slug = {1} AND id <> {2}",
                    data.BusinessCategorySlug,
                    data.ProductCategorySlug,
                    id)
                .FirstAsync(ct)).Value;
            if (dup > 0) return ApiResult<ManageCategoryRowDto>.Fail("Category with this business/product slug already exists");

            await ef.Database.ExecuteSqlRawAsync(
                @"UPDATE product_categories SET
                    business_profile_type = {0},
                    business_heading = {1},
                    business_category = {2},
                    business_category_slug = {3},
                    product_category = {4},
                    product_category_slug = {5},
                    directory_priority = {6},
                    is_active = {7},
                    keywords = {8},
                    tags = {9},
                    updated_at = NOW()
                  WHERE id = {10}",
                [
                    data.BusinessProfileType,
                    data.BusinessHeading,
                    data.BusinessCategory,
                    data.BusinessCategorySlug,
                    data.ProductCategory,
                    data.ProductCategorySlug,
                    data.DirectoryPriority,
                    data.IsActive ? 1 : 0,
                    data.Keywords,
                    data.Tags,
                    id
                ],
                ct);

            return await GetAsync(id, ct) switch
            {
                { Success: true, Data: { } row } => ApiResult<ManageCategoryRowDto>.Ok(row, "Category updated successfully!"),
                var r => r
            };
        }
        catch (Exception ex)
        {
            return ApiResult<ManageCategoryRowDto>.Fail("Error updating category: " + ex.Message);
        }
    }

    public async Task<ApiResult<ManageCategoryRowDto>> ToggleActiveAsync(int id, CancellationToken ct = default)
    {
        var ef = RequireEf();
        try
        {
            await EnsureTableAsync(ef, ct);
            var affected = await ef.Database.ExecuteSqlRawAsync(
                "UPDATE product_categories SET is_active = NOT is_active, updated_at = NOW() WHERE id = {0}",
                [id],
                ct);
            if (affected == 0) return ApiResult<ManageCategoryRowDto>.Fail("Category not found");

            return await GetAsync(id, ct) switch
            {
                { Success: true, Data: { } row } => ApiResult<ManageCategoryRowDto>.Ok(row, "Status updated successfully!"),
                var r => r
            };
        }
        catch (Exception ex)
        {
            return ApiResult<ManageCategoryRowDto>.Fail("Error updating status: " + ex.Message);
        }
    }

    public async Task<ApiResult> DeleteAsync(int id, CancellationToken ct = default)
    {
        var ef = RequireEf();
        try
        {
            await EnsureTableAsync(ef, ct);
            var affected = await ef.Database.ExecuteSqlRawAsync(
                "DELETE FROM product_categories WHERE id = {0}",
                [id],
                ct);
            if (affected == 0) return ApiResult.Fail("Category not found");
            return ApiResult.Ok("Row deleted successfully!");
        }
        catch (Exception ex)
        {
            return ApiResult.Fail("Error deleting row: " + ex.Message);
        }
    }

    public async Task<(byte[] Content, string FileName)> ExportCsvAsync(CancellationToken ct = default)
    {
        var ef = RequireEf();
        await EnsureTableAsync(ef, ct);

        var rows = await ef.Database
            .SqlQueryRaw<CategorySqlRow>(
                @"SELECT c.id AS Id,
                         IFNULL(c.business_profile_type,'') AS BusinessProfileType,
                         IFNULL(c.business_heading,'') AS BusinessHeading,
                         IFNULL(c.business_category,'') AS BusinessCategory,
                         IFNULL(c.business_category_slug,'') AS BusinessCategorySlug,
                         IFNULL(c.product_category,'') AS ProductCategory,
                         IFNULL(c.product_category_slug,'') AS ProductCategorySlug,
                         CAST(IFNULL(c.directory_priority,0) AS SIGNED) AS DirectoryPriority,
                         CAST(IFNULL(c.is_active,0) AS SIGNED) AS IsActiveInt,
                         IFNULL(c.keywords,'') AS Keywords,
                         IFNULL(c.tags,'') AS Tags
                  FROM product_categories c
                  ORDER BY c.directory_priority ASC, c.business_heading ASC, c.business_category ASC, c.product_category ASC")
            .ToListAsync(ct);

        var sb = new StringBuilder();
        sb.Append('\uFEFF');
        sb.AppendLine(string.Join(',', CsvHeaders.Select(EscapeCsv)));

        foreach (var r in rows)
        {
            sb.AppendLine(string.Join(',', new[]
            {
                EscapeCsv(r.BusinessProfileType),
                EscapeCsv(r.BusinessHeading),
                EscapeCsv(r.BusinessCategory),
                EscapeCsv(r.BusinessCategorySlug),
                EscapeCsv(r.ProductCategory),
                EscapeCsv(r.ProductCategorySlug),
                EscapeCsv(r.DirectoryPriority.ToString(CultureInfo.InvariantCulture)),
                EscapeCsv(r.IsActiveInt != 0 ? "1" : "0"),
                EscapeCsv(r.Keywords),
                EscapeCsv(r.Tags),
            }));
        }

        var bytes = Encoding.UTF8.GetBytes(sb.ToString());
        var fileName = $"categories_{DateTime.Now:yyyy-MM-dd_HH-mm-ss}.csv";
        return (bytes, fileName);
    }

    public async Task<ApiResult<CategoryImportResultDto>> ImportCsvAsync(
        Stream csvStream,
        bool replaceAll,
        bool skipDuplicates,
        string? createdBy,
        CancellationToken ct = default)
    {
        var ef = RequireEf();
        await EnsureTableAsync(ef, ct);

        try
        {
            using var reader = new StreamReader(csvStream, Encoding.UTF8, detectEncodingFromByteOrderMarks: true);
            var headerLine = await reader.ReadLineAsync(ct);
            if (string.IsNullOrWhiteSpace(headerLine))
                return ApiResult<CategoryImportResultDto>.Fail("CSV file is empty");

            var headers = ParseCsvLine(headerLine).Select(h => h.Trim().ToLowerInvariant()).ToList();
            var missing = RequiredHeaderKeys.Where(k => !headers.Contains(k)).ToList();
            if (missing.Count > 0)
                return ApiResult<CategoryImportResultDto>.Fail(
                    "Invalid CSV format. Required columns: " + string.Join(", ", CsvHeaders));

            if (replaceAll)
            {
                await ef.Database.ExecuteSqlRawAsync("TRUNCATE TABLE product_categories", ct);
            }

            var byEmail = string.IsNullOrWhiteSpace(createdBy) ? "admin" : createdBy.Trim();
            var errors = new List<string>();
            var imported = 0;
            var skipped = 0;
            var lineNo = 1;

            while (!reader.EndOfStream)
            {
                var line = await reader.ReadLineAsync(ct);
                lineNo++;
                if (string.IsNullOrWhiteSpace(line)) continue;

                var cols = ParseCsvLine(line);
                if (cols.Count < headers.Count)
                {
                    errors.Add($"Line {lineNo}: incomplete row");
                    skipped++;
                    continue;
                }

                string Get(string key)
                {
                    var idx = headers.IndexOf(key);
                    return idx >= 0 && idx < cols.Count ? cols[idx].Trim() : "";
                }

                var businessCategory = Get("business category");
                var productCategory = Get("product category");
                if (string.IsNullOrWhiteSpace(businessCategory) || string.IsNullOrWhiteSpace(productCategory))
                {
                    errors.Add($"Line {lineNo}: business/product category required");
                    skipped++;
                    continue;
                }

                var businessSlug = Get("business category slug");
                if (string.IsNullOrWhiteSpace(businessSlug)) businessSlug = Slugify(businessCategory);
                var productSlug = Get("product category slug");
                if (string.IsNullOrWhiteSpace(productSlug)) productSlug = Slugify(productCategory);

                int.TryParse(Get("directory priority"), NumberStyles.Integer, CultureInfo.InvariantCulture, out var priority);
                var isActive = ParseIsActive(Get("is active"));

                try
                {
                    if (skipDuplicates)
                    {
                        var exists = (await ef.Database
                            .SqlQueryRaw<CountRow>(
                                @"SELECT COUNT(*) AS Value FROM product_categories
                                  WHERE business_category_slug = {0} AND product_category_slug = {1}",
                                businessSlug,
                                productSlug)
                            .FirstAsync(ct)).Value;
                        if (exists > 0)
                        {
                            skipped++;
                            continue;
                        }

                        await ef.Database.ExecuteSqlRawAsync(
                            @"INSERT INTO product_categories
                                (business_profile_type, business_heading, business_category, business_category_slug,
                                 product_category, product_category_slug, directory_priority, is_active, keywords, tags, created_by)
                              VALUES ({0}, {1}, {2}, {3}, {4}, {5}, {6}, {7}, {8}, {9}, {10})",
                            [
                                Get("business profile type"),
                                Get("business heading"),
                                businessCategory,
                                businessSlug,
                                productCategory,
                                productSlug,
                                priority,
                                isActive,
                                Get("keywords"),
                                Get("tags"),
                                byEmail
                            ],
                            ct);
                    }
                    else
                    {
                        await ef.Database.ExecuteSqlRawAsync(
                            @"INSERT INTO product_categories
                                (business_profile_type, business_heading, business_category, business_category_slug,
                                 product_category, product_category_slug, directory_priority, is_active, keywords, tags, created_by)
                              VALUES ({0}, {1}, {2}, {3}, {4}, {5}, {6}, {7}, {8}, {9}, {10})
                              ON DUPLICATE KEY UPDATE
                                business_profile_type = VALUES(business_profile_type),
                                business_heading = VALUES(business_heading),
                                business_category = VALUES(business_category),
                                product_category = VALUES(product_category),
                                directory_priority = VALUES(directory_priority),
                                is_active = VALUES(is_active),
                                keywords = VALUES(keywords),
                                tags = VALUES(tags),
                                updated_at = NOW()",
                            [
                                Get("business profile type"),
                                Get("business heading"),
                                businessCategory,
                                businessSlug,
                                productCategory,
                                productSlug,
                                priority,
                                isActive,
                                Get("keywords"),
                                Get("tags"),
                                byEmail
                            ],
                            ct);
                    }

                    imported++;
                }
                catch (Exception ex)
                {
                    errors.Add($"Line {lineNo}: {ex.Message}");
                    skipped++;
                }
            }

            return ApiResult<CategoryImportResultDto>.Ok(
                new CategoryImportResultDto(imported, skipped, errors.Take(20).ToList()),
                $"Import finished. Imported: {imported}, Skipped: {skipped}");
        }
        catch (Exception ex)
        {
            return ApiResult<CategoryImportResultDto>.Fail("Import failed: " + ex.Message);
        }
    }

    private static string? Validate(UpsertCategoryRequest request)
    {
        if (string.IsNullOrWhiteSpace(request.BusinessCategory)) return "Business category is required";
        if (string.IsNullOrWhiteSpace(request.ProductCategory)) return "Product category is required";
        return null;
    }

    private static NormalizedCategory Normalize(UpsertCategoryRequest request)
    {
        var businessCategory = request.BusinessCategory.Trim();
        var productCategory = request.ProductCategory.Trim();
        var businessSlug = string.IsNullOrWhiteSpace(request.BusinessCategorySlug)
            ? Slugify(businessCategory)
            : Slugify(request.BusinessCategorySlug);
        var productSlug = string.IsNullOrWhiteSpace(request.ProductCategorySlug)
            ? Slugify(productCategory)
            : Slugify(request.ProductCategorySlug);

        return new NormalizedCategory(
            (request.BusinessProfileType ?? "").Trim(),
            (request.BusinessHeading ?? "").Trim(),
            businessCategory,
            businessSlug,
            productCategory,
            productSlug,
            request.DirectoryPriority,
            request.IsActive,
            (request.Keywords ?? "").Trim(),
            (request.Tags ?? "").Trim(),
            string.IsNullOrWhiteSpace(request.CreatedBy) ? "admin" : request.CreatedBy.Trim());
    }

    private static int? NormalizeActive(string? value)
    {
        if (string.IsNullOrWhiteSpace(value) || value.Equals("all", StringComparison.OrdinalIgnoreCase))
            return null;
        var v = value.Trim().ToLowerInvariant();
        if (v is "1" or "yes" or "true" or "active") return 1;
        if (v is "0" or "no" or "false" or "inactive") return 0;
        return null;
    }

    private static int ParseIsActive(string value)
    {
        var v = value.Trim().ToLowerInvariant();
        if (v is "0" or "no" or "n" or "false" or "inactive") return 0;
        return 1;
    }

    private static string Slugify(string text)
    {
        var s = text.Trim().ToLowerInvariant();
        s = Regex.Replace(s, @"[^a-z0-9]+", "-");
        s = s.Trim('-');
        return string.IsNullOrEmpty(s) ? "category" : s;
    }

    private static string EscapeCsv(string? value)
    {
        var v = value ?? "";
        if (v.Contains('"') || v.Contains(',') || v.Contains('\n') || v.Contains('\r'))
            return "\"" + v.Replace("\"", "\"\"") + "\"";
        return v;
    }

    private static List<string> ParseCsvLine(string line)
    {
        var result = new List<string>();
        var sb = new StringBuilder();
        var inQuotes = false;

        for (var i = 0; i < line.Length; i++)
        {
            var ch = line[i];
            if (inQuotes)
            {
                if (ch == '"')
                {
                    if (i + 1 < line.Length && line[i + 1] == '"')
                    {
                        sb.Append('"');
                        i++;
                    }
                    else
                    {
                        inQuotes = false;
                    }
                }
                else
                {
                    sb.Append(ch);
                }
            }
            else if (ch == '"')
            {
                inQuotes = true;
            }
            else if (ch == ',')
            {
                result.Add(sb.ToString());
                sb.Clear();
            }
            else
            {
                sb.Append(ch);
            }
        }

        result.Add(sb.ToString());
        return result;
    }

    private static ManageCategoryRowDto MapRow(CategorySqlRow r)
    {
        var active = r.IsActiveInt != 0;
        return new ManageCategoryRowDto(
            r.Id,
            r.BusinessProfileType ?? "",
            r.BusinessHeading ?? "",
            r.BusinessCategory ?? "",
            r.BusinessCategorySlug ?? "",
            r.ProductCategory ?? "",
            r.ProductCategorySlug ?? "",
            r.DirectoryPriority,
            active,
            active ? "Yes" : "No",
            active ? "ok" : "danger",
            r.Keywords ?? "",
            r.Tags ?? "");
    }

    private static async Task<CategorySqlRow?> LoadByIdAsync(DbContext ef, int id, CancellationToken ct) =>
        await ef.Database
            .SqlQueryRaw<CategorySqlRow>(
                @"SELECT c.id AS Id,
                         IFNULL(c.business_profile_type,'') AS BusinessProfileType,
                         IFNULL(c.business_heading,'') AS BusinessHeading,
                         IFNULL(c.business_category,'') AS BusinessCategory,
                         IFNULL(c.business_category_slug,'') AS BusinessCategorySlug,
                         IFNULL(c.product_category,'') AS ProductCategory,
                         IFNULL(c.product_category_slug,'') AS ProductCategorySlug,
                         CAST(IFNULL(c.directory_priority,0) AS SIGNED) AS DirectoryPriority,
                         CAST(IFNULL(c.is_active,0) AS SIGNED) AS IsActiveInt,
                         IFNULL(c.keywords,'') AS Keywords,
                         IFNULL(c.tags,'') AS Tags
                  FROM product_categories c WHERE c.id = {0} LIMIT 1",
                id)
            .FirstOrDefaultAsync(ct);

    private static async Task EnsureTableAsync(DbContext ef, CancellationToken ct)
    {
        await ef.Database.ExecuteSqlRawAsync(
            @"CREATE TABLE IF NOT EXISTS product_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                business_profile_type VARCHAR(100) NOT NULL DEFAULT '',
                business_heading VARCHAR(255) NOT NULL DEFAULT '',
                business_category VARCHAR(255) NOT NULL,
                business_category_slug VARCHAR(255) NOT NULL,
                product_category VARCHAR(255) NOT NULL,
                product_category_slug VARCHAR(255) NOT NULL,
                directory_priority INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                keywords TEXT,
                tags TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by VARCHAR(255) DEFAULT NULL,
                UNIQUE KEY uk_business_product_slug (business_category_slug, product_category_slug),
                INDEX idx_active (is_active),
                INDEX idx_business_slug (business_category_slug),
                INDEX idx_product_slug (product_category_slug),
                INDEX idx_directory_priority (directory_priority),
                INDEX idx_business_heading (business_heading)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            ct);
    }

    private DbContext RequireEf() =>
        _db as DbContext
        ?? throw new InvalidOperationException("IApplicationDbContext must be an EF DbContext.");

    private sealed record NormalizedCategory(
        string BusinessProfileType,
        string BusinessHeading,
        string BusinessCategory,
        string BusinessCategorySlug,
        string ProductCategory,
        string ProductCategorySlug,
        int DirectoryPriority,
        bool IsActive,
        string Keywords,
        string Tags,
        string CreatedBy);

    private sealed class CountRow
    {
        public int Value { get; set; }
    }

    private sealed class CategorySqlRow
    {
        public int Id { get; set; }
        public string? BusinessProfileType { get; set; }
        public string? BusinessHeading { get; set; }
        public string? BusinessCategory { get; set; }
        public string? BusinessCategorySlug { get; set; }
        public string? ProductCategory { get; set; }
        public string? ProductCategorySlug { get; set; }
        public int DirectoryPriority { get; set; }
        public int IsActiveInt { get; set; }
        public string? Keywords { get; set; }
        public string? Tags { get; set; }
    }
}
