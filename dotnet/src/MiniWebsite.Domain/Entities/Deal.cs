namespace MiniWebsite.Domain.Entities;

/// <summary>Maps to live <c>deals</c> table (legacy columns often stored as strings).</summary>
public class Deal
{
    public int Id { get; set; }
    public string PlanName { get; set; } = "";
    public string PlanType { get; set; } = "";
    public string? DealState { get; set; }
    public string DealName { get; set; } = "";
    public string CouponCode { get; set; } = "";
    public decimal BonusAmount { get; set; }
    public decimal DiscountAmount { get; set; }
    public decimal DiscountPercentage { get; set; }
    public DateTime ValidityDate { get; set; }
    public int MaxUsage { get; set; }
    public int CurrentUsage { get; set; }
    public string DealStatus { get; set; } = "Active";
    public string? CreatedBy { get; set; }
    public DateTime? UploadedDate { get; set; }
}
