using System.Globalization;
using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Admin.ManageDeals.Dtos;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;

namespace MiniWebsite.Application.Admin.ManageDeals;

public class AdminManageDealsService : IAdminManageDealsService
{
    private static readonly string[] IndiaStates =
    [
        "Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh",
        "Goa", "Gujarat", "Haryana", "Himachal Pradesh", "Jharkhand", "Karnataka",
        "Kerala", "Madhya Pradesh", "Maharashtra", "Manipur", "Meghalaya", "Mizoram",
        "Nagaland", "Odisha", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu",
        "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal",
        "Andaman and Nicobar Islands", "Chandigarh",
        "Dadra and Nagar Haveli and Daman and Diu", "Delhi", "Jammu and Kashmir",
        "Ladakh", "Lakshadweep", "Puducherry",
    ];

    private readonly IApplicationDbContext _db;

    public AdminManageDealsService(IApplicationDbContext db)
    {
        _db = db;
    }

    public ManageDealsMetaDto GetMeta() => new(IndiaStates);

    public async Task<ApiResult<ManageDealsPageDto>> ListAsync(ManageDealsQuery query, CancellationToken ct = default)
    {
        query.Page = query.Page < 1 ? 1 : query.Page;
        query.PageSize = query.PageSize is < 1 or > 100 ? 10 : query.PageSize;
        var offset = (query.Page - 1) * query.PageSize;
        var ef = RequireEf();

        try
        {
            var search = string.IsNullOrWhiteSpace(query.Search) ? null : "%" + query.Search.Trim() + "%";
            var planType = string.IsNullOrWhiteSpace(query.PlanType) || query.PlanType.Equals("all", StringComparison.OrdinalIgnoreCase)
                ? null
                : query.PlanType.Trim();
            var status = string.IsNullOrWhiteSpace(query.Status) || query.Status.Equals("all", StringComparison.OrdinalIgnoreCase)
                ? null
                : query.Status.Trim();

            // Filters: pass NULL to skip. CAST numeric cols so mixed VARCHAR/INT schemas work.
            var total = (await ef.Database
                .SqlQueryRaw<CountRow>(
                    @"SELECT COUNT(*) AS Value FROM deals d
                      WHERE ({0} IS NULL OR d.deal_name LIKE {0} OR d.coupon_code LIKE {0}
                            OR d.plan_name LIKE {0} OR IFNULL(d.deal_state,'') LIKE {0})
                        AND ({1} IS NULL OR d.plan_type = {1})
                        AND ({2} IS NULL OR d.deal_status = {2})",
                    search, planType, status)
                .FirstAsync(ct)).Value;

            var rows = await ef.Database
                .SqlQueryRaw<DealSqlRow>(
                    @"SELECT d.id AS Id,
                             d.plan_name AS PlanName,
                             d.plan_type AS PlanType,
                             d.deal_state AS DealState,
                             d.deal_name AS DealName,
                             d.coupon_code AS CouponCode,
                             CAST(d.bonus_amount AS CHAR) AS BonusAmount,
                             CAST(d.discount_amount AS CHAR) AS DiscountAmount,
                             CAST(d.discount_percentage AS CHAR) AS DiscountPercentage,
                             CAST(d.validity_date AS CHAR) AS ValidityDate,
                             CAST(d.max_usage AS CHAR) AS MaxUsage,
                             CAST(d.current_usage AS CHAR) AS CurrentUsage,
                             d.deal_status AS DealStatus,
                             CAST(d.uploaded_date AS CHAR) AS UploadedDate
                      FROM deals d
                      WHERE ({0} IS NULL OR d.deal_name LIKE {0} OR d.coupon_code LIKE {0}
                            OR d.plan_name LIKE {0} OR IFNULL(d.deal_state,'') LIKE {0})
                        AND ({1} IS NULL OR d.plan_type = {1})
                        AND ({2} IS NULL OR d.deal_status = {2})
                      ORDER BY d.id DESC
                      LIMIT {3} OFFSET {4}",
                    search, planType, status, query.PageSize, offset)
                .ToListAsync(ct);

            return ApiResult<ManageDealsPageDto>.Ok(new ManageDealsPageDto(
                rows.Select(MapRow).ToList(),
                total,
                query.Page,
                query.PageSize));
        }
        catch (Exception ex)
        {
            return ApiResult<ManageDealsPageDto>.Fail("Unable to load deals: " + ex.Message);
        }
    }

    public async Task<ApiResult<ManageDealRowDto>> GetAsync(int id, CancellationToken ct = default)
    {
        var ef = RequireEf();
        try
        {
            var row = await ef.Database
                .SqlQueryRaw<DealSqlRow>(
                    @"SELECT d.id AS Id,
                             d.plan_name AS PlanName,
                             d.plan_type AS PlanType,
                             d.deal_state AS DealState,
                             d.deal_name AS DealName,
                             d.coupon_code AS CouponCode,
                             CAST(d.bonus_amount AS CHAR) AS BonusAmount,
                             CAST(d.discount_amount AS CHAR) AS DiscountAmount,
                             CAST(d.discount_percentage AS CHAR) AS DiscountPercentage,
                             CAST(d.validity_date AS CHAR) AS ValidityDate,
                             CAST(d.max_usage AS CHAR) AS MaxUsage,
                             CAST(d.current_usage AS CHAR) AS CurrentUsage,
                             d.deal_status AS DealStatus,
                             CAST(d.uploaded_date AS CHAR) AS UploadedDate
                      FROM deals d WHERE d.id = {0} LIMIT 1",
                    id)
                .FirstOrDefaultAsync(ct);

            if (row == null) return ApiResult<ManageDealRowDto>.Fail("Deal not found");
            return ApiResult<ManageDealRowDto>.Ok(MapRow(row));
        }
        catch (Exception ex)
        {
            return ApiResult<ManageDealRowDto>.Fail("Unable to load deal: " + ex.Message);
        }
    }

    public async Task<ApiResult<ManageDealRowDto>> CreateAsync(UpsertDealRequest request, CancellationToken ct = default)
    {
        var validation = Validate(request);
        if (validation != null) return ApiResult<ManageDealRowDto>.Fail(validation);

        var coupon = NormalizeCoupon(request.CouponCode);
        var ef = RequireEf();

        try
        {
            var exists = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM deals WHERE coupon_code = {0}", coupon)
                .FirstAsync(ct)).Value;
            if (exists > 0) return ApiResult<ManageDealRowDto>.Fail("Coupon code already exists!");

            var createdBy = string.IsNullOrWhiteSpace(request.CreatedBy) ? "admin" : request.CreatedBy.Trim();
            var dealState = string.IsNullOrWhiteSpace(request.DealState) ? "" : request.DealState.Trim();
            var bonus = Math.Max(0, request.BonusAmount);
            var discountAmt = Math.Max(0, request.DiscountAmount);
            var discountPct = ClampPercent(request.DiscountPercentage);
            var maxUsage = Math.Max(0, request.MaxUsage);
            var validity = request.ValidityDate.Date.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture);

            await ef.Database.ExecuteSqlRawAsync(
                @"INSERT INTO deals
                    (plan_name, plan_type, deal_name, coupon_code, bonus_amount, discount_amount,
                     discount_percentage, validity_date, max_usage, deal_state, created_by, deal_status, current_usage, uploaded_date)
                  VALUES
                    ({0}, {1}, {2}, {3}, {4}, {5}, {6}, {7}, {8}, {9}, {10}, 'Active', 0, NOW())",
                [
                    request.PlanName.Trim(),
                    request.PlanType.Trim(),
                    request.DealName.Trim(),
                    coupon,
                    FormatNum(bonus),
                    FormatNum(discountAmt),
                    FormatNum(discountPct),
                    validity,
                    maxUsage.ToString(CultureInfo.InvariantCulture),
                    dealState,
                    createdBy
                ],
                ct);

            var id = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT LAST_INSERT_ID() AS Value")
                .FirstAsync(ct)).Value;

            return await GetAsync(id, ct) switch
            {
                { Success: true, Data: { } data } => ApiResult<ManageDealRowDto>.Ok(data, "Deal Added Successfully!"),
                var r => r
            };
        }
        catch (Exception ex)
        {
            return ApiResult<ManageDealRowDto>.Fail("Error adding deal: " + ex.Message);
        }
    }

    public async Task<ApiResult<ManageDealRowDto>> UpdateAsync(int id, UpsertDealRequest request, CancellationToken ct = default)
    {
        var validation = Validate(request);
        if (validation != null) return ApiResult<ManageDealRowDto>.Fail(validation);

        var coupon = NormalizeCoupon(request.CouponCode);
        var ef = RequireEf();

        try
        {
            var exists = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM deals WHERE id = {0}", id)
                .FirstAsync(ct)).Value;
            if (exists == 0) return ApiResult<ManageDealRowDto>.Fail("Deal not found");

            var conflict = (await ef.Database
                .SqlQueryRaw<CountRow>(
                    "SELECT COUNT(*) AS Value FROM deals WHERE coupon_code = {0} AND id <> {1}",
                    coupon, id)
                .FirstAsync(ct)).Value;
            if (conflict > 0) return ApiResult<ManageDealRowDto>.Fail("Coupon code already exists!");

            var dealState = string.IsNullOrWhiteSpace(request.DealState) ? "" : request.DealState.Trim();
            var bonus = Math.Max(0, request.BonusAmount);
            var discountAmt = Math.Max(0, request.DiscountAmount);
            var discountPct = ClampPercent(request.DiscountPercentage);
            var maxUsage = Math.Max(0, request.MaxUsage);
            var validity = request.ValidityDate.Date.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture);

            await ef.Database.ExecuteSqlRawAsync(
                @"UPDATE deals SET
                    plan_name = {0},
                    plan_type = {1},
                    deal_name = {2},
                    coupon_code = {3},
                    bonus_amount = {4},
                    discount_amount = {5},
                    discount_percentage = {6},
                    validity_date = {7},
                    max_usage = {8},
                    deal_state = {9}
                  WHERE id = {10}",
                [
                    request.PlanName.Trim(),
                    request.PlanType.Trim(),
                    request.DealName.Trim(),
                    coupon,
                    FormatNum(bonus),
                    FormatNum(discountAmt),
                    FormatNum(discountPct),
                    validity,
                    maxUsage.ToString(CultureInfo.InvariantCulture),
                    dealState,
                    id
                ],
                ct);

            return await GetAsync(id, ct) switch
            {
                { Success: true, Data: { } data } => ApiResult<ManageDealRowDto>.Ok(data, "Deal Updated Successfully!"),
                var r => r
            };
        }
        catch (Exception ex)
        {
            return ApiResult<ManageDealRowDto>.Fail("Error updating deal: " + ex.Message);
        }
    }

    public async Task<ApiResult> ToggleStatusAsync(int id, CancellationToken ct = default)
    {
        var ef = RequireEf();
        try
        {
            var row = await ef.Database
                .SqlQueryRaw<StatusRow>("SELECT deal_status AS DealStatus FROM deals WHERE id = {0} LIMIT 1", id)
                .FirstOrDefaultAsync(ct);
            if (row == null) return ApiResult.Fail("Deal not found");

            var next = string.Equals(row.DealStatus, "Active", StringComparison.OrdinalIgnoreCase)
                ? "Inactive"
                : "Active";

            await ef.Database.ExecuteSqlRawAsync(
                "UPDATE deals SET deal_status = {0} WHERE id = {1}",
                [next, id],
                ct);

            return ApiResult.Ok("Status updated!");
        }
        catch (Exception ex)
        {
            return ApiResult.Fail("Unable to update status: " + ex.Message);
        }
    }

    public async Task<ApiResult> DeleteAsync(int id, CancellationToken ct = default)
    {
        var ef = RequireEf();
        try
        {
            var affected = await ef.Database.ExecuteSqlRawAsync(
                "DELETE FROM deals WHERE id = {0}",
                [id],
                ct);
            if (affected == 0) return ApiResult.Fail("Deal not found");
            return ApiResult.Ok("Deal deleted!");
        }
        catch (Exception ex)
        {
            return ApiResult.Fail("Unable to delete deal: " + ex.Message);
        }
    }

    private static string? Validate(UpsertDealRequest request)
    {
        if (string.IsNullOrWhiteSpace(request.PlanName)) return "Plan name is required";
        if (string.IsNullOrWhiteSpace(request.PlanType)) return "Plan type is required";
        if (string.IsNullOrWhiteSpace(request.DealName)) return "Deal name is required";
        if (string.IsNullOrWhiteSpace(request.CouponCode)) return "Coupon code is required";
        if (request.ValidityDate == default) return "Validity date is required";

        var plan = request.PlanName.Trim();
        if (plan is not ("Basic" or "Premium" or "Enterprise"))
            return "Invalid plan name";

        var type = request.PlanType.Trim();
        if (type is not ("MiniWebsite" or "Franchise"))
            return "Invalid plan type";

        return null;
    }

    private static string NormalizeCoupon(string code) => code.Trim().ToUpperInvariant();

    private static decimal ClampPercent(decimal value) =>
        value < 0 ? 0 : value > 100 ? 100 : value;

    private static string FormatNum(decimal v) => v.ToString(CultureInfo.InvariantCulture);

    private static ManageDealRowDto MapRow(DealSqlRow d)
    {
        var culture = CultureInfo.InvariantCulture;
        var bonus = ParseDecimal(d.BonusAmount);
        var discountAmt = ParseDecimal(d.DiscountAmount);
        var discountPct = ParseDecimal(d.DiscountPercentage);
        var maxUsage = ParseInt(d.MaxUsage);
        var currentUsage = ParseInt(d.CurrentUsage);
        var validity = ParseDate(d.ValidityDate) ?? DateTime.Today;
        var uploaded = ParseDateTime(d.UploadedDate);
        var expired = validity.Date < DateTime.Today;
        var hasState = !string.IsNullOrWhiteSpace(d.DealState);

        string discountDisplay;
        if (discountAmt > 0)
            discountDisplay = "₹" + discountAmt.ToString("N0", culture);
        else if (discountPct > 0)
            discountDisplay = discountPct.ToString("0.##", culture) + "%";
        else
            discountDisplay = "-";

        var usage = maxUsage == 0
            ? currentUsage + "/∞"
            : currentUsage + "/" + maxUsage;

        var statusTone = string.Equals(d.DealStatus, "Active", StringComparison.OrdinalIgnoreCase)
            ? "ok"
            : "neutral";

        return new ManageDealRowDto(
            d.Id,
            d.PlanName ?? "",
            d.PlanType ?? "",
            d.DealState,
            hasState ? d.DealState! : "All",
            d.DealName ?? "",
            d.CouponCode ?? "",
            uploaded,
            uploaded?.ToString("dd-MM-yyyy", culture) ?? "-",
            bonus,
            "₹" + bonus.ToString("N0", culture),
            discountAmt,
            discountPct,
            discountDisplay,
            validity,
            validity.ToString("dd-MM-yyyy", culture),
            expired,
            maxUsage,
            currentUsage,
            usage,
            d.DealStatus ?? "",
            statusTone);
    }

    private static decimal ParseDecimal(string? v) =>
        decimal.TryParse(v, NumberStyles.Any, CultureInfo.InvariantCulture, out var d) ? d : 0m;

    private static int ParseInt(string? v) =>
        int.TryParse(v, NumberStyles.Any, CultureInfo.InvariantCulture, out var n) ? n : 0;

    private static DateTime? ParseDate(string? v)
    {
        if (string.IsNullOrWhiteSpace(v)) return null;
        if (DateTime.TryParse(v, CultureInfo.InvariantCulture, DateTimeStyles.AssumeLocal, out var d))
            return d.Date;
        return null;
    }

    private static DateTime? ParseDateTime(string? v)
    {
        if (string.IsNullOrWhiteSpace(v) || v.StartsWith("0000", StringComparison.Ordinal)) return null;
        if (DateTime.TryParse(v, CultureInfo.InvariantCulture, DateTimeStyles.AssumeLocal, out var d))
            return d;
        return null;
    }

    private DbContext RequireEf() =>
        _db as DbContext
        ?? throw new InvalidOperationException("IApplicationDbContext must be an EF DbContext.");

    private sealed class CountRow
    {
        public int Value { get; set; }
    }

    private sealed class StatusRow
    {
        public string? DealStatus { get; set; }
    }

    private sealed class DealSqlRow
    {
        public int Id { get; set; }
        public string? PlanName { get; set; }
        public string? PlanType { get; set; }
        public string? DealState { get; set; }
        public string? DealName { get; set; }
        public string? CouponCode { get; set; }
        public string? BonusAmount { get; set; }
        public string? DiscountAmount { get; set; }
        public string? DiscountPercentage { get; set; }
        public string? ValidityDate { get; set; }
        public string? MaxUsage { get; set; }
        public string? CurrentUsage { get; set; }
        public string? DealStatus { get; set; }
        public string? UploadedDate { get; set; }
    }
}
