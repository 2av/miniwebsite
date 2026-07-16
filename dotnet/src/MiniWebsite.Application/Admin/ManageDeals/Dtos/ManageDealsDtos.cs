namespace MiniWebsite.Application.Admin.ManageDeals.Dtos;

public class ManageDealsQuery
{
    public int Page { get; set; } = 1;
    public int PageSize { get; set; } = 10;
    public string? Search { get; set; }
    public string? PlanType { get; set; }
    public string? Status { get; set; }
}

public record ManageDealsPageDto(
    IReadOnlyList<ManageDealRowDto> Deals,
    int TotalCount,
    int Page,
    int PageSize);

public record ManageDealRowDto(
    int Id,
    string PlanName,
    string PlanType,
    string? DealState,
    string DealStateDisplay,
    string DealName,
    string CouponCode,
    DateTime? CreatedAt,
    string CreatedAtDisplay,
    decimal BonusAmount,
    string BonusAmountDisplay,
    decimal DiscountAmount,
    decimal DiscountPercentage,
    string DiscountDisplay,
    DateTime ValidityDate,
    string ValidityDateDisplay,
    bool IsExpired,
    int MaxUsage,
    int CurrentUsage,
    string UsageDisplay,
    string DealStatus,
    string StatusTone);

public record UpsertDealRequest(
    string PlanName,
    string PlanType,
    string DealName,
    string CouponCode,
    decimal BonusAmount,
    decimal DiscountAmount,
    decimal DiscountPercentage,
    DateTime ValidityDate,
    int MaxUsage,
    string? DealState,
    string? CreatedBy);

public record ManageDealsMetaDto(IReadOnlyList<string> States);
