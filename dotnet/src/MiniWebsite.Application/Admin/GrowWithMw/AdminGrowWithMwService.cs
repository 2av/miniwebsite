using System.Globalization;
using System.Text.RegularExpressions;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Options;
using MiniWebsite.Application.Admin.GrowWithMw.Dtos;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;
using MiniWebsite.Application.Common.Options;

namespace MiniWebsite.Application.Admin.GrowWithMw;

public class AdminGrowWithMwService : IAdminGrowWithMwService
{
    private readonly IApplicationDbContext _db;
    private readonly AppOptions _app;
    private bool _schemaReady;

    public AdminGrowWithMwService(IApplicationDbContext db, IOptions<AppOptions> app)
    {
        _db = db;
        _app = app.Value;
    }

    public async Task<GrowWithMwMetaDto> GetMetaAsync(CancellationToken ct = default)
    {
        await EnsureSchemaAsync(ct);
        var sections = await ListSectionOptionsAsync(ct);
        return new GrowWithMwMetaDto(sections, PublicDocsPrefix(), GrowWithMwPrefix());
    }

    public async Task<ApiResult<DocPagesPageDto>> ListPagesAsync(DocPagesQuery query, CancellationToken ct = default)
    {
        query.Page = query.Page < 1 ? 1 : query.Page;
        query.PageSize = query.PageSize is < 1 or > 100 ? 10 : query.PageSize;
        var offset = (query.Page - 1) * query.PageSize;
        var ef = RequireEf();

        try
        {
            await EnsureSchemaAsync(ct);
            var search = string.IsNullOrWhiteSpace(query.Search) ? null : "%" + query.Search.Trim() + "%";
            var status = NormalizePageStatus(query.Status);
            var sectionId = query.SectionId is > 0 ? query.SectionId : null;

            var total = (await ef.Database
                .SqlQueryRaw<CountRow>(
                    @"SELECT COUNT(*) AS Value
                      FROM doc_pages p
                      INNER JOIN doc_sections s ON s.id = p.section_id
                      WHERE ({0} IS NULL OR p.title LIKE {0} OR p.slug LIKE {0} OR IFNULL(p.meta_title,'') LIKE {0})
                        AND ({1} IS NULL OR p.section_id = {1})
                        AND ({2} IS NULL OR p.status = {2})",
                    search ?? (object)DBNull.Value,
                    sectionId.HasValue ? sectionId.Value : DBNull.Value,
                    status ?? (object)DBNull.Value)
                .FirstAsync(ct)).Value;

            var rows = await ef.Database
                .SqlQueryRaw<PageListSqlRow>(
                    @"SELECT p.id AS Id,
                             p.title AS Title,
                             p.slug AS Slug,
                             p.status AS Status,
                             CAST(p.sort_order AS SIGNED) AS SortOrder,
                             p.updated_at AS UpdatedAt,
                             p.section_id AS SectionId,
                             s.title AS SectionTitle
                      FROM doc_pages p
                      INNER JOIN doc_sections s ON s.id = p.section_id
                      WHERE ({0} IS NULL OR p.title LIKE {0} OR p.slug LIKE {0} OR IFNULL(p.meta_title,'') LIKE {0})
                        AND ({1} IS NULL OR p.section_id = {1})
                        AND ({2} IS NULL OR p.status = {2})
                      ORDER BY s.sort_order ASC, p.sort_order ASC, p.id ASC
                      LIMIT {3} OFFSET {4}",
                    search ?? (object)DBNull.Value,
                    sectionId.HasValue ? sectionId.Value : DBNull.Value,
                    status ?? (object)DBNull.Value,
                    query.PageSize,
                    offset)
                .ToListAsync(ct);

            var items = rows.Select(r => new DocPageListItemDto(
                r.Id,
                r.Title ?? "",
                r.Slug ?? "",
                r.Status ?? "draft",
                (r.Status ?? "") == "published" ? "ok" : "neutral",
                r.SortOrder,
                FormatDateTime(r.UpdatedAt),
                r.SectionId,
                r.SectionTitle ?? "")).ToList();

            return ApiResult<DocPagesPageDto>.Ok(new DocPagesPageDto(items, total, query.Page, query.PageSize));
        }
        catch (Exception ex)
        {
            return ApiResult<DocPagesPageDto>.Fail("Unable to load pages: " + ex.Message);
        }
    }

    public async Task<ApiResult<DocPageDetailDto>> GetPageAsync(int id, CancellationToken ct = default)
    {
        await EnsureSchemaAsync(ct);
        var row = await LoadPageAsync(id, ct);
        if (row == null) return ApiResult<DocPageDetailDto>.Fail("Page not found.");
        return ApiResult<DocPageDetailDto>.Ok(row);
    }

    public async Task<ApiResult<DocPageDetailDto>> UpsertPageAsync(int? id, UpsertDocPageRequest request, CancellationToken ct = default)
    {
        await EnsureSchemaAsync(ct);
        var ef = RequireEf();

        var title = (request.Title ?? "").Trim();
        var sectionId = request.SectionId;
        var action = (request.Action ?? "draft").Trim().ToLowerInvariant();
        var publish = action == "publish";
        var status = publish ? "published" : "draft";
        var content = request.ContentHtml ?? "";
        var metaTitle = (request.MetaTitle ?? "").Trim();
        var metaDesc = (request.MetaDescription ?? "").Trim();
        var metaKw = (request.MetaKeywords ?? "").Trim();

        if (title.Length == 0 || sectionId <= 0)
            return ApiResult<DocPageDetailDto>.Fail("Title and section are required.");

        var sectionExists = (await ef.Database
            .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM doc_sections WHERE id = {0}", sectionId)
            .FirstAsync(ct)).Value;
        if (sectionExists == 0)
            return ApiResult<DocPageDetailDto>.Fail("Invalid section.");

        try
        {
            var slugBase = string.IsNullOrWhiteSpace(request.Slug) ? title : request.Slug!;
            var slug = await UniquePageSlugAsync(Slugify(slugBase), id, ct);

            if (id is null or <= 0)
            {
                var nextOrd = (await ef.Database
                    .SqlQueryRaw<CountRow>(
                        "SELECT COALESCE(MAX(sort_order),0)+1 AS Value FROM doc_pages WHERE section_id = {0}",
                        sectionId)
                    .FirstAsync(ct)).Value;

                if (publish)
                {
                    await ef.Database.ExecuteSqlRawAsync(
                        @"INSERT INTO doc_pages
                            (section_id, title, slug, status, content_html, meta_title, meta_description, meta_keywords, sort_order, published_at)
                          VALUES ({0}, {1}, {2}, {3}, {4}, {5}, {6}, {7}, {8}, NOW())",
                        [sectionId, title, slug, status, content, metaTitle, metaDesc, metaKw, nextOrd],
                        ct);
                }
                else
                {
                    await ef.Database.ExecuteSqlRawAsync(
                        @"INSERT INTO doc_pages
                            (section_id, title, slug, status, content_html, meta_title, meta_description, meta_keywords, sort_order, published_at)
                          VALUES ({0}, {1}, {2}, {3}, {4}, {5}, {6}, {7}, {8}, NULL)",
                        [sectionId, title, slug, status, content, metaTitle, metaDesc, metaKw, nextOrd],
                        ct);
                }

                var newId = (await ef.Database
                    .SqlQueryRaw<CountRow>("SELECT LAST_INSERT_ID() AS Value")
                    .FirstAsync(ct)).Value;

                return await GetPageAsync(newId, ct) switch
                {
                    { Success: true, Data: { } data } => ApiResult<DocPageDetailDto>.Ok(data, publish ? "Page published." : "Draft saved."),
                    var r => r
                };
            }

            var exists = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM doc_pages WHERE id = {0}", id.Value)
                .FirstAsync(ct)).Value;
            if (exists == 0) return ApiResult<DocPageDetailDto>.Fail("Page not found.");

            if (publish)
            {
                await ef.Database.ExecuteSqlRawAsync(
                    @"UPDATE doc_pages SET
                        section_id = {0}, title = {1}, slug = {2}, status = {3}, content_html = {4},
                        meta_title = {5}, meta_description = {6}, meta_keywords = {7},
                        published_at = COALESCE(published_at, NOW()), updated_at = NOW()
                      WHERE id = {8}",
                    [sectionId, title, slug, status, content, metaTitle, metaDesc, metaKw, id.Value],
                    ct);
            }
            else
            {
                await ef.Database.ExecuteSqlRawAsync(
                    @"UPDATE doc_pages SET
                        section_id = {0}, title = {1}, slug = {2}, status = {3}, content_html = {4},
                        meta_title = {5}, meta_description = {6}, meta_keywords = {7}, updated_at = NOW()
                      WHERE id = {8}",
                    [sectionId, title, slug, status, content, metaTitle, metaDesc, metaKw, id.Value],
                    ct);
            }

            return await GetPageAsync(id.Value, ct) switch
            {
                { Success: true, Data: { } data } => ApiResult<DocPageDetailDto>.Ok(data, publish ? "Page published." : "Draft saved."),
                var r => r
            };
        }
        catch (Exception ex)
        {
            return ApiResult<DocPageDetailDto>.Fail("Could not save page: " + ex.Message);
        }
    }

    public async Task<ApiResult> DeletePageAsync(int id, CancellationToken ct = default)
    {
        await EnsureSchemaAsync(ct);
        var ef = RequireEf();
        try
        {
            var affected = await ef.Database.ExecuteSqlRawAsync("DELETE FROM doc_pages WHERE id = {0}", [id], ct);
            if (affected == 0) return ApiResult.Fail("Page not found.");
            return ApiResult.Ok("Page deleted.");
        }
        catch (Exception ex)
        {
            return ApiResult.Fail("Could not delete page: " + ex.Message);
        }
    }

    public async Task<ApiResult> ReorderPagesAsync(ReorderPagesRequest request, CancellationToken ct = default)
    {
        await EnsureSchemaAsync(ct);
        var ef = RequireEf();
        if (request.SectionId <= 0 || request.Order == null || request.Order.Count == 0)
            return ApiResult.Fail("Invalid payload");

        try
        {
            var pos = 0;
            foreach (var pageId in request.Order.Where(x => x > 0))
            {
                await ef.Database.ExecuteSqlRawAsync(
                    "UPDATE doc_pages SET sort_order = {0} WHERE id = {1} AND section_id = {2}",
                    [pos, pageId, request.SectionId],
                    ct);
                pos++;
            }
            return ApiResult.Ok("Order saved.");
        }
        catch (Exception ex)
        {
            return ApiResult.Fail("Reorder failed: " + ex.Message);
        }
    }

    public async Task<ApiResult<IReadOnlyList<DocSectionDto>>> ListSectionsAsync(CancellationToken ct = default)
    {
        await EnsureSchemaAsync(ct);
        var ef = RequireEf();
        try
        {
            var rows = await ef.Database
                .SqlQueryRaw<SectionSqlRow>(
                    @"SELECT s.id AS Id,
                             s.title AS Title,
                             s.slug AS Slug,
                             IFNULL(s.description,'') AS Description,
                             CAST(s.sort_order AS SIGNED) AS SortOrder,
                             CAST(IFNULL(s.collapsed_default,0) AS SIGNED) AS CollapsedInt,
                             CAST((SELECT COUNT(*) FROM doc_pages p WHERE p.section_id = s.id) AS SIGNED) AS PageCount
                      FROM doc_sections s
                      ORDER BY s.sort_order ASC, s.id ASC")
                .ToListAsync(ct);

            var list = rows.Select(MapSection).ToList();
            return ApiResult<IReadOnlyList<DocSectionDto>>.Ok(list);
        }
        catch (Exception ex)
        {
            return ApiResult<IReadOnlyList<DocSectionDto>>.Fail("Unable to load sections: " + ex.Message);
        }
    }

    public async Task<ApiResult<DocSectionDto>> CreateSectionAsync(UpsertDocSectionRequest request, CancellationToken ct = default)
    {
        await EnsureSchemaAsync(ct);
        var title = (request.Title ?? "").Trim();
        if (title.Length == 0) return ApiResult<DocSectionDto>.Fail("Title is required.");

        var ef = RequireEf();
        try
        {
            var slug = await UniqueSectionSlugAsync(
                Slugify(string.IsNullOrWhiteSpace(request.Slug) ? title : request.Slug!),
                null,
                ct);
            var nextOrd = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT COALESCE(MAX(sort_order),0)+1 AS Value FROM doc_sections")
                .FirstAsync(ct)).Value;
            var desc = (request.Description ?? "").Trim();
            var collapsed = request.CollapsedDefault ? 1 : 0;

            await ef.Database.ExecuteSqlRawAsync(
                @"INSERT INTO doc_sections (title, slug, description, sort_order, collapsed_default)
                  VALUES ({0}, {1}, {2}, {3}, {4})",
                [title, slug, desc, nextOrd, collapsed],
                ct);

            var id = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT LAST_INSERT_ID() AS Value")
                .FirstAsync(ct)).Value;

            var list = await ListSectionsAsync(ct);
            var row = list.Data?.FirstOrDefault(s => s.Id == id);
            return row == null
                ? ApiResult<DocSectionDto>.Fail("Section created but could not reload.")
                : ApiResult<DocSectionDto>.Ok(row, "Section created.");
        }
        catch (Exception ex)
        {
            return ApiResult<DocSectionDto>.Fail("Could not create section: " + ex.Message);
        }
    }

    public async Task<ApiResult<DocSectionDto>> UpdateSectionAsync(int id, UpsertDocSectionRequest request, CancellationToken ct = default)
    {
        await EnsureSchemaAsync(ct);
        var title = (request.Title ?? "").Trim();
        if (id <= 0 || title.Length == 0) return ApiResult<DocSectionDto>.Fail("Invalid section.");

        var ef = RequireEf();
        try
        {
            var exists = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM doc_sections WHERE id = {0}", id)
                .FirstAsync(ct)).Value;
            if (exists == 0) return ApiResult<DocSectionDto>.Fail("Section not found.");

            var slug = await UniqueSectionSlugAsync(
                Slugify(string.IsNullOrWhiteSpace(request.Slug) ? title : request.Slug!),
                id,
                ct);
            var desc = (request.Description ?? "").Trim();
            var collapsed = request.CollapsedDefault ? 1 : 0;

            await ef.Database.ExecuteSqlRawAsync(
                @"UPDATE doc_sections SET title = {0}, slug = {1}, description = {2}, collapsed_default = {3}, updated_at = NOW()
                  WHERE id = {4}",
                [title, slug, desc, collapsed, id],
                ct);

            var list = await ListSectionsAsync(ct);
            var row = list.Data?.FirstOrDefault(s => s.Id == id);
            return row == null
                ? ApiResult<DocSectionDto>.Fail("Section updated but could not reload.")
                : ApiResult<DocSectionDto>.Ok(row, "Section updated.");
        }
        catch (Exception ex)
        {
            return ApiResult<DocSectionDto>.Fail("Could not update section: " + ex.Message);
        }
    }

    public async Task<ApiResult> DeleteSectionAsync(int id, CancellationToken ct = default)
    {
        await EnsureSchemaAsync(ct);
        var ef = RequireEf();
        try
        {
            var pages = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM doc_pages WHERE section_id = {0}", id)
                .FirstAsync(ct)).Value;
            if (pages > 0) return ApiResult.Fail("Move or delete pages in this section first.");

            var affected = await ef.Database.ExecuteSqlRawAsync("DELETE FROM doc_sections WHERE id = {0}", [id], ct);
            if (affected == 0) return ApiResult.Fail("Section not found.");
            return ApiResult.Ok("Section deleted.");
        }
        catch (Exception ex)
        {
            return ApiResult.Fail("Could not delete section: " + ex.Message);
        }
    }

    public async Task<ApiResult> ReorderSectionsAsync(ReorderRequest request, CancellationToken ct = default)
    {
        await EnsureSchemaAsync(ct);
        var ef = RequireEf();
        if (request.Order == null || request.Order.Count == 0)
            return ApiResult.Fail("Invalid payload");

        try
        {
            var pos = 0;
            foreach (var sectionId in request.Order.Where(x => x > 0))
            {
                await ef.Database.ExecuteSqlRawAsync(
                    "UPDATE doc_sections SET sort_order = {0} WHERE id = {1}",
                    [pos, sectionId],
                    ct);
                pos++;
            }
            return ApiResult.Ok("Order saved.");
        }
        catch (Exception ex)
        {
            return ApiResult.Fail("Reorder failed: " + ex.Message);
        }
    }

    public async Task<ApiResult<DocMediaListDto>> ListMediaAsync(CancellationToken ct = default)
    {
        await EnsureSchemaAsync(ct);
        var ef = RequireEf();
        try
        {
            var rows = await ef.Database
                .SqlQueryRaw<MediaSqlRow>(
                    @"SELECT id AS Id,
                             filename AS Filename,
                             rel_path AS RelPath,
                             IFNULL(mime,'') AS Mime,
                             CAST(IFNULL(size_bytes,0) AS SIGNED) AS SizeBytes,
                             uploaded_by AS UploadedBy,
                             created_at AS CreatedAt
                      FROM doc_media
                      ORDER BY id DESC
                      LIMIT 100")
                .ToListAsync(ct);

            var items = rows.Select(MapMedia).ToList();
            return ApiResult<DocMediaListDto>.Ok(new DocMediaListDto(items));
        }
        catch (Exception ex)
        {
            return ApiResult<DocMediaListDto>.Fail("Unable to load media: " + ex.Message);
        }
    }

    public async Task<ApiResult<DocUploadResultDto>> UploadMediaAsync(
        Stream file,
        string fileName,
        string contentType,
        string? uploadedBy,
        CancellationToken ct = default)
    {
        await EnsureSchemaAsync(ct);

        var map = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase)
        {
            ["image/jpeg"] = "jpg",
            ["image/png"] = "png",
            ["image/gif"] = "gif",
            ["image/webp"] = "webp",
        };

        if (!map.TryGetValue(contentType ?? "", out var ext))
            return ApiResult<DocUploadResultDto>.Fail("Only JPG, PNG, GIF, WebP allowed");

        await using var ms = new MemoryStream();
        await file.CopyToAsync(ms, ct);
        if (ms.Length > 5 * 1024 * 1024)
            return ApiResult<DocUploadResultDto>.Fail("Max 5MB");
        if (ms.Length == 0)
            return ApiResult<DocUploadResultDto>.Fail("Empty file");

        var dir = ResolveUploadDir();
        if (string.IsNullOrWhiteSpace(dir))
            return ApiResult<DocUploadResultDto>.Fail("Documentation upload path is not configured (App:DocumentationUploadsFsPath).");

        try
        {
            Directory.CreateDirectory(dir);
            var baseName = $"doc-{DateTime.Now:yyyyMMdd}-{Guid.NewGuid().ToString("N")[..8]}.{ext}";
            var dest = Path.Combine(dir, baseName);
            await File.WriteAllBytesAsync(dest, ms.ToArray(), ct);

            var rel = "uploads/documentation/" + baseName;
            var mime = contentType;
            var size = (int)ms.Length;
            var by = string.IsNullOrWhiteSpace(uploadedBy) ? "admin" : uploadedBy.Trim();
            var ef = RequireEf();

            await ef.Database.ExecuteSqlRawAsync(
                @"INSERT INTO doc_media (filename, rel_path, mime, size_bytes, uploaded_by)
                  VALUES ({0}, {1}, {2}, {3}, {4})",
                [baseName, rel, mime, size, by],
                ct);

            var id = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT LAST_INSERT_ID() AS Value")
                .FirstAsync(ct)).Value;

            var media = new DocMediaItemDto(
                id,
                baseName,
                rel,
                BuildPublicUrl(rel),
                mime,
                size,
                by,
                FormatDateTime(DateTime.Now),
                true);

            return ApiResult<DocUploadResultDto>.Ok(new DocUploadResultDto(media.Url, media), "Uploaded");
        }
        catch (Exception ex)
        {
            return ApiResult<DocUploadResultDto>.Fail("Upload failed: " + ex.Message);
        }
    }

    private async Task<DocPageDetailDto?> LoadPageAsync(int id, CancellationToken ct)
    {
        var ef = RequireEf();
        var row = await ef.Database
            .SqlQueryRaw<PageDetailSqlRow>(
                @"SELECT p.id AS Id,
                         p.section_id AS SectionId,
                         s.title AS SectionTitle,
                         p.title AS Title,
                         p.slug AS Slug,
                         p.status AS Status,
                         IFNULL(p.content_html,'') AS ContentHtml,
                         IFNULL(p.meta_title,'') AS MetaTitle,
                         IFNULL(p.meta_description,'') AS MetaDescription,
                         IFNULL(p.meta_keywords,'') AS MetaKeywords,
                         CAST(p.sort_order AS SIGNED) AS SortOrder,
                         p.published_at AS PublishedAt,
                         p.updated_at AS UpdatedAt
                  FROM doc_pages p
                  INNER JOIN doc_sections s ON s.id = p.section_id
                  WHERE p.id = {0}
                  LIMIT 1",
                id)
            .FirstOrDefaultAsync(ct);

        if (row == null) return null;

        var sectionPages = await ef.Database
            .SqlQueryRaw<PageOrderSqlRow>(
                @"SELECT id AS Id, title AS Title, slug AS Slug, status AS Status,
                         CAST(sort_order AS SIGNED) AS SortOrder
                  FROM doc_pages WHERE section_id = {0}
                  ORDER BY sort_order ASC, id ASC",
                row.SectionId)
            .ToListAsync(ct);

        return new DocPageDetailDto(
            row.Id,
            row.SectionId,
            row.SectionTitle ?? "",
            row.Title ?? "",
            row.Slug ?? "",
            row.Status ?? "draft",
            row.ContentHtml ?? "",
            row.MetaTitle ?? "",
            row.MetaDescription ?? "",
            row.MetaKeywords ?? "",
            row.SortOrder,
            row.PublishedAt is { Year: > 1 } ? FormatDateTime(row.PublishedAt) : null,
            FormatDateTime(row.UpdatedAt),
            GrowWithMwPrefix().TrimEnd('/') + "/" + Uri.EscapeDataString(row.Slug ?? ""),
            sectionPages.Select(p => new DocPageOrderItemDto(p.Id, p.Title ?? "", p.Slug ?? "", p.Status ?? "", p.SortOrder)).ToList());
    }

    private async Task<IReadOnlyList<DocSectionOptionDto>> ListSectionOptionsAsync(CancellationToken ct)
    {
        var ef = RequireEf();
        var rows = await ef.Database
            .SqlQueryRaw<SectionOptionSqlRow>(
                "SELECT id AS Id, title AS Title FROM doc_sections ORDER BY sort_order ASC, id ASC")
            .ToListAsync(ct);
        return rows.Select(r => new DocSectionOptionDto(r.Id, r.Title ?? "")).ToList();
    }

    private async Task EnsureSchemaAsync(CancellationToken ct)
    {
        if (_schemaReady) return;
        var ef = RequireEf();

        await ef.Database.ExecuteSqlRawAsync(
            @"CREATE TABLE IF NOT EXISTS `doc_sections` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(191) NOT NULL,
                `description` VARCHAR(500) DEFAULT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `collapsed_default` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_doc_sections_slug` (`slug`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ct);

        await ef.Database.ExecuteSqlRawAsync(
            @"CREATE TABLE IF NOT EXISTS `doc_pages` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `section_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(191) NOT NULL,
                `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
                `content_html` MEDIUMTEXT,
                `meta_title` VARCHAR(255) DEFAULT NULL,
                `meta_description` VARCHAR(500) DEFAULT NULL,
                `meta_keywords` VARCHAR(255) DEFAULT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `published_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_doc_pages_slug` (`slug`),
                KEY `idx_doc_pages_section_sort` (`section_id`, `sort_order`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ct);

        await ef.Database.ExecuteSqlRawAsync(
            @"CREATE TABLE IF NOT EXISTS `doc_media` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `filename` VARCHAR(255) NOT NULL,
                `rel_path` VARCHAR(512) NOT NULL,
                `mime` VARCHAR(128) DEFAULT NULL,
                `size_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
                `uploaded_by` VARCHAR(255) DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_doc_media_created` (`created_at`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ct);

        var count = (await ef.Database
            .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM doc_sections")
            .FirstAsync(ct)).Value;

        if (count == 0)
        {
            await ef.Database.ExecuteSqlRawAsync(
                @"INSERT INTO doc_sections (title, slug, description, sort_order, collapsed_default)
                  VALUES ('Getting Started', 'getting-started', 'Introduction and setup', 0, 0)",
                ct);
            var sid = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT LAST_INSERT_ID() AS Value")
                .FirstAsync(ct)).Value;
            var welcome = "<h1>Welcome to the documentation</h1><p>This is your first published page.</p>";
            await ef.Database.ExecuteSqlRawAsync(
                @"INSERT INTO doc_pages (section_id, title, slug, status, content_html, sort_order, published_at)
                  VALUES ({0}, 'Welcome', 'welcome', 'published', {1}, 0, NOW())",
                [sid, welcome],
                ct);
        }

        _schemaReady = true;
    }

    private async Task<string> UniquePageSlugAsync(string baseSlug, int? excludeId, CancellationToken ct)
    {
        var ef = RequireEf();
        var candidate = string.IsNullOrWhiteSpace(baseSlug) ? "page" : baseSlug;
        var n = 2;
        while (true)
        {
            CountRow row;
            if (excludeId is > 0)
            {
                row = await ef.Database
                    .SqlQueryRaw<CountRow>(
                        "SELECT COUNT(*) AS Value FROM doc_pages WHERE slug = {0} AND id != {1}",
                        candidate, excludeId.Value)
                    .FirstAsync(ct);
            }
            else
            {
                row = await ef.Database
                    .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM doc_pages WHERE slug = {0}", candidate)
                    .FirstAsync(ct);
            }

            if (row.Value == 0) return candidate;
            candidate = baseSlug + "-" + n;
            n++;
        }
    }

    private async Task<string> UniqueSectionSlugAsync(string baseSlug, int? excludeId, CancellationToken ct)
    {
        var ef = RequireEf();
        var candidate = string.IsNullOrWhiteSpace(baseSlug) ? "section" : baseSlug;
        var n = 2;
        while (true)
        {
            CountRow row;
            if (excludeId is > 0)
            {
                row = await ef.Database
                    .SqlQueryRaw<CountRow>(
                        "SELECT COUNT(*) AS Value FROM doc_sections WHERE slug = {0} AND id != {1}",
                        candidate, excludeId.Value)
                    .FirstAsync(ct);
            }
            else
            {
                row = await ef.Database
                    .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM doc_sections WHERE slug = {0}", candidate)
                    .FirstAsync(ct);
            }

            if (row.Value == 0) return candidate;
            candidate = baseSlug + "-" + n;
            n++;
        }
    }

    private string ResolveUploadDir()
    {
        if (!string.IsNullOrWhiteSpace(_app.DocumentationUploadsFsPath))
            return _app.DocumentationUploadsFsPath.Trim();

        // Common local XAMPP layout relative to solution: ../../../../uploads/documentation from API bin
        var candidates = new[]
        {
            Path.GetFullPath(Path.Combine(AppContext.BaseDirectory, "..", "..", "..", "..", "..", "..", "uploads", "documentation")),
            Path.GetFullPath(Path.Combine(Directory.GetCurrentDirectory(), "..", "..", "uploads", "documentation")),
            @"C:\xampp\htdocs\miniwebsite\uploads\documentation",
        };
        foreach (var c in candidates)
        {
            if (Directory.Exists(c) || Directory.Exists(Path.GetDirectoryName(c)!))
                return c;
        }
        return candidates[0];
    }

    private string PublicDocsPrefix()
    {
        var prefix = _app.DocumentationPublicPrefix;
        if (prefix.StartsWith("http", StringComparison.OrdinalIgnoreCase)) return prefix;
        return CombineUrl(_app.PhpSiteBaseUrl, prefix);
    }

    private string GrowWithMwPrefix()
    {
        var prefix = _app.GrowWithMwPublicPrefix;
        if (prefix.StartsWith("http", StringComparison.OrdinalIgnoreCase)) return prefix;
        return CombineUrl(_app.PhpSiteBaseUrl, prefix);
    }

    private string BuildPublicUrl(string relPath) =>
        CombineUrl(_app.PhpSiteBaseUrl, relPath);

    private static string CombineUrl(string baseUrl, string path)
    {
        return baseUrl.TrimEnd('/') + "/" + path.TrimStart('/');
    }

    private DocMediaItemDto MapMedia(MediaSqlRow r)
    {
        var mime = r.Mime ?? "";
        return new DocMediaItemDto(
            r.Id,
            r.Filename ?? "",
            r.RelPath ?? "",
            BuildPublicUrl(r.RelPath ?? ""),
            mime,
            r.SizeBytes,
            r.UploadedBy,
            FormatDateTime(r.CreatedAt),
            mime.StartsWith("image/", StringComparison.OrdinalIgnoreCase));
    }

    private static DocSectionDto MapSection(SectionSqlRow r) =>
        new(r.Id, r.Title ?? "", r.Slug ?? "", r.Description ?? "", r.SortOrder, r.CollapsedInt != 0, r.PageCount);

    private static string? NormalizePageStatus(string? value)
    {
        if (string.IsNullOrWhiteSpace(value) || value.Equals("all", StringComparison.OrdinalIgnoreCase))
            return null;
        var v = value.Trim().ToLowerInvariant();
        return v is "draft" or "published" ? v : null;
    }

    private static string Slugify(string text)
    {
        var s = text.Trim().ToLowerInvariant();
        s = Regex.Replace(s, @"[^a-z0-9]+", "-");
        s = s.Trim('-');
        return string.IsNullOrEmpty(s) ? "section" : s;
    }

    private static string FormatDateTime(DateTime? value)
    {
        if (value is not { } d || d.Year < 2) return "—";
        return d.ToString("yyyy-MM-dd HH:mm:ss", CultureInfo.InvariantCulture);
    }

    private DbContext RequireEf() =>
        _db as DbContext
        ?? throw new InvalidOperationException("IApplicationDbContext must be an EF DbContext.");

    private sealed class CountRow { public int Value { get; set; } }
    private sealed class SectionOptionSqlRow { public int Id { get; set; } public string? Title { get; set; } }
    private sealed class PageListSqlRow
    {
        public int Id { get; set; }
        public string? Title { get; set; }
        public string? Slug { get; set; }
        public string? Status { get; set; }
        public int SortOrder { get; set; }
        public DateTime? UpdatedAt { get; set; }
        public int SectionId { get; set; }
        public string? SectionTitle { get; set; }
    }
    private sealed class PageDetailSqlRow
    {
        public int Id { get; set; }
        public int SectionId { get; set; }
        public string? SectionTitle { get; set; }
        public string? Title { get; set; }
        public string? Slug { get; set; }
        public string? Status { get; set; }
        public string? ContentHtml { get; set; }
        public string? MetaTitle { get; set; }
        public string? MetaDescription { get; set; }
        public string? MetaKeywords { get; set; }
        public int SortOrder { get; set; }
        public DateTime? PublishedAt { get; set; }
        public DateTime? UpdatedAt { get; set; }
    }
    private sealed class PageOrderSqlRow
    {
        public int Id { get; set; }
        public string? Title { get; set; }
        public string? Slug { get; set; }
        public string? Status { get; set; }
        public int SortOrder { get; set; }
    }
    private sealed class SectionSqlRow
    {
        public int Id { get; set; }
        public string? Title { get; set; }
        public string? Slug { get; set; }
        public string? Description { get; set; }
        public int SortOrder { get; set; }
        public int CollapsedInt { get; set; }
        public int PageCount { get; set; }
    }
    private sealed class MediaSqlRow
    {
        public int Id { get; set; }
        public string? Filename { get; set; }
        public string? RelPath { get; set; }
        public string? Mime { get; set; }
        public int SizeBytes { get; set; }
        public string? UploadedBy { get; set; }
        public DateTime? CreatedAt { get; set; }
    }
}
