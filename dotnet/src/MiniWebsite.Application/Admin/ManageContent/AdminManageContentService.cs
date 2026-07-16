using System.Globalization;
using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Admin.ManageContent.Dtos;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;

namespace MiniWebsite.Application.Admin.ManageContent;

public class AdminManageContentService : IAdminManageContentService
{
    private static readonly ContentTypeOptionDto[] ContentTypes =
    [
        new("terms_conditions", "Terms & Conditions", "Legal"),
        new("privacy_policy", "Privacy Policy", "Privacy"),
        new("franchisee_agreement", "Franchisee Agreement", "Partnership"),
        new("franchisee_distributer", "Franchisee Distributer", "Program"),
        new("mw_full_franchise_agreement", "MW Full Franchise Agreement", "Franchise"),
        new("mw_franchisee_operation_policy", "MW Franchisee Operation Policy", "Policy"),
    ];

    private readonly IApplicationDbContext _db;

    public AdminManageContentService(IApplicationDbContext db)
    {
        _db = db;
    }

    public ManageContentMetaDto GetMeta() => new(ContentTypes);

    public async Task<ApiResult<ManageContentListDto>> ListAsync(CancellationToken ct = default)
    {
        try
        {
            var items = new List<ManageContentItemDto>();
            foreach (var type in ContentTypes)
                items.Add(await LoadOrEmptyAsync(type.Value, ct));

            return ApiResult<ManageContentListDto>.Ok(new ManageContentListDto(items));
        }
        catch (Exception ex)
        {
            return ApiResult<ManageContentListDto>.Fail("Unable to load content: " + ex.Message);
        }
    }

    public async Task<ApiResult<ManageContentItemDto>> GetByTypeAsync(string contentType, CancellationToken ct = default)
    {
        if (!IsValidType(contentType))
            return ApiResult<ManageContentItemDto>.Fail("Invalid content type");

        try
        {
            return ApiResult<ManageContentItemDto>.Ok(await LoadOrEmptyAsync(contentType.Trim(), ct));
        }
        catch (Exception ex)
        {
            return ApiResult<ManageContentItemDto>.Fail("Unable to load content: " + ex.Message);
        }
    }

    public async Task<ApiResult<ManageContentItemDto>> UpsertAsync(UpsertContentRequest request, CancellationToken ct = default)
    {
        var contentType = (request.ContentType ?? "").Trim();
        if (!IsValidType(contentType))
            return ApiResult<ManageContentItemDto>.Fail("Invalid content type");
        if (string.IsNullOrWhiteSpace(request.Title))
            return ApiResult<ManageContentItemDto>.Fail("Title is required");

        var title = request.Title.Trim();
        var content = request.Content ?? "";
        var metaDesc = request.MetaDescription ?? "";
        var metaKeys = request.MetaKeywords ?? "";
        var updatedBy = string.IsNullOrWhiteSpace(request.UpdatedBy) ? "admin" : request.UpdatedBy.Trim();
        var ef = RequireEf();

        try
        {
            var exists = (await ef.Database
                .SqlQueryRaw<CountRow>(
                    "SELECT COUNT(*) AS Value FROM content_management WHERE content_type = {0}",
                    contentType)
                .FirstAsync(ct)).Value;

            if (exists > 0)
            {
                await ef.Database.ExecuteSqlRawAsync(
                    @"UPDATE content_management SET
                        title = {0},
                        content = {1},
                        meta_description = {2},
                        meta_keywords = {3},
                        updated_by = {4},
                        last_updated = NOW()
                      WHERE content_type = {5}",
                    [title, content, metaDesc, metaKeys, updatedBy, contentType],
                    ct);

                var updated = await LoadOrEmptyAsync(contentType, ct);
                return ApiResult<ManageContentItemDto>.Ok(updated, "Content updated successfully!");
            }

            await ef.Database.ExecuteSqlRawAsync(
                @"INSERT INTO content_management
                    (content_type, title, content, meta_description, meta_keywords, updated_by, last_updated, is_active)
                  VALUES
                    ({0}, {1}, {2}, {3}, {4}, {5}, NOW(), 1)",
                [contentType, title, content, metaDesc, metaKeys, updatedBy],
                ct);

            var created = await LoadOrEmptyAsync(contentType, ct);
            return ApiResult<ManageContentItemDto>.Ok(created, "Content created successfully!");
        }
        catch (Exception ex)
        {
            // Retry insert without is_active if column missing
            if (ex.Message.Contains("is_active", StringComparison.OrdinalIgnoreCase))
            {
                try
                {
                    await ef.Database.ExecuteSqlRawAsync(
                        @"INSERT INTO content_management
                            (content_type, title, content, meta_description, meta_keywords, updated_by, last_updated)
                          VALUES
                            ({0}, {1}, {2}, {3}, {4}, {5}, NOW())",
                        [contentType, title, content, metaDesc, metaKeys, updatedBy],
                        ct);
                    var created = await LoadOrEmptyAsync(contentType, ct);
                    return ApiResult<ManageContentItemDto>.Ok(created, "Content created successfully!");
                }
                catch (Exception ex2)
                {
                    return ApiResult<ManageContentItemDto>.Fail("Error saving content: " + ex2.Message);
                }
            }

            return ApiResult<ManageContentItemDto>.Fail("Error saving content: " + ex.Message);
        }
    }

    private async Task<ManageContentItemDto> LoadOrEmptyAsync(string contentType, CancellationToken ct)
    {
        var ef = RequireEf();
        var row = await ef.Database
            .SqlQueryRaw<ContentSqlRow>(
                @"SELECT content_type AS ContentType,
                         title AS Title,
                         content AS Content,
                         meta_description AS MetaDescription,
                         meta_keywords AS MetaKeywords,
                         CAST(last_updated AS CHAR) AS LastUpdated,
                         updated_by AS UpdatedBy
                  FROM content_management
                  WHERE content_type = {0}
                  LIMIT 1",
                contentType)
            .FirstOrDefaultAsync(ct);

        if (row == null)
        {
            return new ManageContentItemDto(
                contentType, "", "", "", "", null, null, null);
        }

        DateTime? lastUpdated = null;
        string? display = null;
        if (!string.IsNullOrWhiteSpace(row.LastUpdated)
            && !row.LastUpdated.StartsWith("0000", StringComparison.Ordinal)
            && DateTime.TryParse(row.LastUpdated, CultureInfo.InvariantCulture, DateTimeStyles.AssumeLocal, out var dt))
        {
            lastUpdated = dt;
            display = dt.ToString("dd MMM yyyy HH:mm", CultureInfo.InvariantCulture);
        }

        return new ManageContentItemDto(
            row.ContentType ?? contentType,
            row.Title ?? "",
            row.Content ?? "",
            row.MetaDescription ?? "",
            row.MetaKeywords ?? "",
            lastUpdated?.ToString("o"),
            display,
            row.UpdatedBy);
    }

    private static bool IsValidType(string? contentType) =>
        !string.IsNullOrWhiteSpace(contentType)
        && ContentTypes.Any(t => t.Value.Equals(contentType.Trim(), StringComparison.Ordinal));

    private DbContext RequireEf() =>
        _db as DbContext
        ?? throw new InvalidOperationException("IApplicationDbContext must be an EF DbContext.");

    private sealed class CountRow
    {
        public int Value { get; set; }
    }

    private sealed class ContentSqlRow
    {
        public string? ContentType { get; set; }
        public string? Title { get; set; }
        public string? Content { get; set; }
        public string? MetaDescription { get; set; }
        public string? MetaKeywords { get; set; }
        public string? LastUpdated { get; set; }
        public string? UpdatedBy { get; set; }
    }
}
