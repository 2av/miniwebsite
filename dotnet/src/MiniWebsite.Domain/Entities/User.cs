using MiniWebsite.Domain.Common;
using MiniWebsite.Domain.Enums;

namespace MiniWebsite.Domain.Entities;

/// <summary>Maps to live PHP table <c>user_details</c>.</summary>
public class User : BaseEntity
{
    public UserRole Role { get; set; } = UserRole.Customer;
    public required string Email { get; set; }
    public string? Phone { get; set; }
    public required string Name { get; set; }

    /// <summary>Required column on live DB (PHP stores bcrypt here too).</summary>
    public required string Password { get; set; }

    public string? PasswordHash { get; set; }
    public string? Ip { get; set; }
    public string Status { get; set; } = "ACTIVE";
    public string? UserToken { get; set; }
    public string? RefundStatus { get; set; }
    public DateTime? RefundStatusDate { get; set; }
    public uint? MwReferralId { get; set; }
    public string CollaborationEnabled { get; set; } = "NO";
    public string SaleskitEnabled { get; set; } = "NO";
    public string Influencer { get; set; } = "NO";
    public string? ReferredBy { get; set; }
    public uint? SenderUserId { get; set; }
    public string? SelectService { get; set; }
    public string? WalletBalance { get; set; }
    public string? GooglePay { get; set; }
    public string? Paytm { get; set; }
    public string? RzPay { get; set; }
    public string? RzPay2 { get; set; }
    public int? LegacyCustomerId { get; set; }
    public int? LegacyFranchiseeId { get; set; }
    public int? LegacyTeamId { get; set; }
    public int? LegacyAdminId { get; set; }
    public string? Department { get; set; }
    public string? ProfileImage { get; set; }
    public string? ReferralCode { get; set; }
    public string? District { get; set; }
    public string? State { get; set; }

    public ICollection<RefreshToken> RefreshTokens { get; set; } = new List<RefreshToken>();
    public ICollection<PasswordResetToken> PasswordResetTokens { get; set; } = new List<PasswordResetToken>();
}
