using System.Globalization;
using System.Net;
using System.Text.RegularExpressions;
using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Admin.ManageFaqs.Dtos;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;

namespace MiniWebsite.Application.Admin.ManageFaqs;

public class AdminManageFaqsService : IAdminManageFaqsService
{
    private static readonly FaqPageTypeOptionDto[] PageTypes =
    [
        new("home", "Home Page"),
        new("refer_earn", "Refer & Earn"),
        new("franchise", "Franchise"),
    ];

    private readonly IApplicationDbContext _db;

    public AdminManageFaqsService(IApplicationDbContext db)
    {
        _db = db;
    }

    public ManageFaqsMetaDto GetMeta() => new(PageTypes);

    public async Task<ApiResult<ManageFaqsPageDto>> ListAsync(ManageFaqsQuery query, CancellationToken ct = default)
    {
        query.Page = query.Page < 1 ? 1 : query.Page;
        query.PageSize = query.PageSize is < 1 or > 100 ? 10 : query.PageSize;
        var offset = (query.Page - 1) * query.PageSize;
        var ef = RequireEf();

        try
        {
            var search = string.IsNullOrWhiteSpace(query.Search) ? null : "%" + query.Search.Trim() + "%";
            var pageType = NormalizeFilter(query.PageType);
            var status = NormalizeFilter(query.Status);

            var total = (await ef.Database
                .SqlQueryRaw<CountRow>(
                    @"SELECT COUNT(*) AS Value FROM faq_management f
                      WHERE ({0} IS NULL OR f.question LIKE {0} OR f.answer LIKE {0})
                        AND ({1} IS NULL OR f.page_type = {1})
                        AND ({2} IS NULL OR f.status = {2})",
                    search ?? (object)DBNull.Value,
                    pageType ?? (object)DBNull.Value,
                    status ?? (object)DBNull.Value)
                .FirstAsync(ct)).Value;

            var rows = await ef.Database
                .SqlQueryRaw<FaqSqlRow>(
                    @"SELECT f.id AS Id,
                             f.page_type AS PageType,
                             f.question AS Question,
                             f.answer AS Answer,
                             CAST(f.sort_order AS SIGNED) AS SortOrder,
                             IFNULL(f.status, 'active') AS Status
                      FROM faq_management f
                      WHERE ({0} IS NULL OR f.question LIKE {0} OR f.answer LIKE {0})
                        AND ({1} IS NULL OR f.page_type = {1})
                        AND ({2} IS NULL OR f.status = {2})
                      ORDER BY f.page_type ASC, f.sort_order ASC, f.id ASC
                      LIMIT {3} OFFSET {4}",
                    search ?? (object)DBNull.Value,
                    pageType ?? (object)DBNull.Value,
                    status ?? (object)DBNull.Value,
                    query.PageSize,
                    offset)
                .ToListAsync(ct);

            return ApiResult<ManageFaqsPageDto>.Ok(new ManageFaqsPageDto(
                rows.Select(MapRow).ToList(),
                total,
                query.Page,
                query.PageSize));
        }
        catch (Exception ex)
        {
            return ApiResult<ManageFaqsPageDto>.Fail("Unable to load FAQs: " + ex.Message);
        }
    }

    public async Task<ApiResult<ManageFaqRowDto>> GetAsync(int id, CancellationToken ct = default)
    {
        var ef = RequireEf();
        try
        {
            var row = await ef.Database
                .SqlQueryRaw<FaqSqlRow>(
                    @"SELECT f.id AS Id,
                             f.page_type AS PageType,
                             f.question AS Question,
                             f.answer AS Answer,
                             CAST(f.sort_order AS SIGNED) AS SortOrder,
                             IFNULL(f.status, 'active') AS Status
                      FROM faq_management f WHERE f.id = {0} LIMIT 1",
                    id)
                .FirstOrDefaultAsync(ct);

            if (row == null) return ApiResult<ManageFaqRowDto>.Fail("FAQ not found");
            return ApiResult<ManageFaqRowDto>.Ok(MapRow(row));
        }
        catch (Exception ex)
        {
            return ApiResult<ManageFaqRowDto>.Fail("Unable to load FAQ: " + ex.Message);
        }
    }

    public async Task<ApiResult<ManageFaqRowDto>> CreateAsync(UpsertFaqRequest request, CancellationToken ct = default)
    {
        var validation = Validate(request, requireStatus: false);
        if (validation != null) return ApiResult<ManageFaqRowDto>.Fail(validation);

        var ef = RequireEf();
        var pageType = request.PageType.Trim();
        var sortOrder = request.SortOrder < 1 ? 1 : request.SortOrder;

        try
        {
            await ef.Database.ExecuteSqlRawAsync(
                @"INSERT INTO faq_management (page_type, question, answer, sort_order, status)
                  VALUES ({0}, {1}, {2}, {3}, 'active')",
                [pageType, request.Question.Trim(), request.Answer.Trim(), sortOrder],
                ct);

            var id = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT LAST_INSERT_ID() AS Value")
                .FirstAsync(ct)).Value;

            return await GetAsync(id, ct) switch
            {
                { Success: true, Data: { } data } => ApiResult<ManageFaqRowDto>.Ok(data, "FAQ added successfully!"),
                var r => r
            };
        }
        catch (Exception ex)
        {
            return ApiResult<ManageFaqRowDto>.Fail("Error adding FAQ: " + ex.Message);
        }
    }

    public async Task<ApiResult<ManageFaqRowDto>> UpdateAsync(int id, UpsertFaqRequest request, CancellationToken ct = default)
    {
        var validation = Validate(request, requireStatus: true);
        if (validation != null) return ApiResult<ManageFaqRowDto>.Fail(validation);

        var ef = RequireEf();
        var pageType = request.PageType.Trim();
        var status = (request.Status ?? "active").Trim().ToLowerInvariant();
        var sortOrder = request.SortOrder < 1 ? 1 : request.SortOrder;

        try
        {
            var exists = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM faq_management WHERE id = {0}", id)
                .FirstAsync(ct)).Value;
            if (exists == 0) return ApiResult<ManageFaqRowDto>.Fail("FAQ not found");

            await ef.Database.ExecuteSqlRawAsync(
                @"UPDATE faq_management SET
                    page_type = {0},
                    question = {1},
                    answer = {2},
                    sort_order = {3},
                    status = {4}
                  WHERE id = {5}",
                [
                    pageType,
                    request.Question.Trim(),
                    request.Answer.Trim(),
                    sortOrder,
                    status,
                    id
                ],
                ct);

            return await GetAsync(id, ct) switch
            {
                { Success: true, Data: { } data } => ApiResult<ManageFaqRowDto>.Ok(data, "FAQ updated successfully!"),
                var r => r
            };
        }
        catch (Exception ex)
        {
            return ApiResult<ManageFaqRowDto>.Fail("Error updating FAQ: " + ex.Message);
        }
    }

    public async Task<ApiResult> DeleteAsync(int id, CancellationToken ct = default)
    {
        var ef = RequireEf();
        try
        {
            var affected = await ef.Database.ExecuteSqlRawAsync(
                "DELETE FROM faq_management WHERE id = {0}",
                [id],
                ct);
            if (affected == 0) return ApiResult.Fail("FAQ not found");
            return ApiResult.Ok("FAQ deleted successfully!");
        }
        catch (Exception ex)
        {
            return ApiResult.Fail("Error deleting FAQ: " + ex.Message);
        }
    }

    private static string? Validate(UpsertFaqRequest request, bool requireStatus)
    {
        if (string.IsNullOrWhiteSpace(request.PageType)) return "Page type is required";
        if (string.IsNullOrWhiteSpace(request.Question)) return "Question is required";
        if (string.IsNullOrWhiteSpace(request.Answer)) return "Answer is required";

        var pageType = request.PageType.Trim();
        if (PageTypes.All(p => p.Value != pageType))
            return "Invalid page type";

        if (requireStatus)
        {
            var status = (request.Status ?? "").Trim().ToLowerInvariant();
            if (status is not ("active" or "inactive"))
                return "Invalid status";
        }

        return null;
    }

    private static string? NormalizeFilter(string? value) =>
        string.IsNullOrWhiteSpace(value) || value.Equals("all", StringComparison.OrdinalIgnoreCase)
            ? null
            : value.Trim();

    private static ManageFaqRowDto MapRow(FaqSqlRow r)
    {
        var pageType = r.PageType ?? "";
        var label = PageTypes.FirstOrDefault(p => p.Value == pageType)?.Label
                    ?? CultureInfo.InvariantCulture.TextInfo.ToTitleCase(pageType.Replace('_', ' '));
        var status = (r.Status ?? "active").ToLowerInvariant();
        var tone = status == "active" ? "ok" : "danger";

        return new ManageFaqRowDto(
            r.Id,
            pageType,
            label,
            r.Question ?? "",
            r.Answer ?? "",
            StripHtmlPreview(r.Answer, 120),
            r.SortOrder,
            status,
            tone);
    }

    private static string StripHtmlPreview(string? html, int maxLen)
    {
        if (string.IsNullOrWhiteSpace(html)) return "";
        var text = WebUtility.HtmlDecode(Regex.Replace(html, "<[^>]+>", " "));
        text = Regex.Replace(text, @"\s+", " ").Trim();
        if (text.Length <= maxLen) return text;
        return text[..maxLen].TrimEnd() + "…";
    }

    private DbContext RequireEf() =>
        _db as DbContext
        ?? throw new InvalidOperationException("IApplicationDbContext must be an EF DbContext.");

    private sealed class CountRow
    {
        public int Value { get; set; }
    }

    private sealed class FaqSqlRow
    {
        public int Id { get; set; }
        public string? PageType { get; set; }
        public string? Question { get; set; }
        public string? Answer { get; set; }
        public int SortOrder { get; set; }
        public string? Status { get; set; }
    }
}
