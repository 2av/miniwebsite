namespace MiniWebsite.Domain.Entities;

/// <summary>Maps to live <c>referral_earnings</c> table.</summary>
public class ReferralEarning
{
    public int Id { get; set; }
    public required string ReferrerEmail { get; set; }
    public required string ReferredEmail { get; set; }
    public DateTime ReferralDate { get; set; }
    public string Status { get; set; } = "Pending";
    public decimal Amount { get; set; }
    public string? IsCollaboration { get; set; }
}
