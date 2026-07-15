using MiniWebsite.Domain.Common;
using MiniWebsite.Domain.Enums;

namespace MiniWebsite.Domain.Entities;

public class User : BaseEntity
{
    public required string Email { get; set; }
    public string? Phone { get; set; }
    public required string Name { get; set; }
    public required string PasswordHash { get; set; }
    public UserRole Role { get; set; } = UserRole.Customer;
    public string Status { get; set; } = "ACTIVE";
    public string? State { get; set; }
    public string? ReferralCode { get; set; }
    public string? ReferredBy { get; set; }

    public ICollection<RefreshToken> RefreshTokens { get; set; } = new List<RefreshToken>();
    public ICollection<PasswordResetToken> PasswordResetTokens { get; set; } = new List<PasswordResetToken>();
}
