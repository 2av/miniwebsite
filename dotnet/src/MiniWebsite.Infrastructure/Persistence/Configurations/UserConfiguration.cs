using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;
using Microsoft.EntityFrameworkCore.Storage.ValueConversion;
using MiniWebsite.Domain.Entities;
using MiniWebsite.Domain.Enums;

namespace MiniWebsite.Infrastructure.Persistence.Configurations;

public class UserConfiguration : IEntityTypeConfiguration<User>
{
    public void Configure(EntityTypeBuilder<User> builder)
    {
        builder.ToTable("user_details");
        builder.HasKey(x => x.Id);

        builder.Property(x => x.Id).HasColumnName("id");
        builder.Property(x => x.Role)
            .HasColumnName("role")
            .HasMaxLength(20)
            .HasConversion(new UserRoleConverter())
            .IsRequired();
        builder.Property(x => x.Email).HasColumnName("email").HasMaxLength(255).IsRequired();
        builder.Property(x => x.Phone).HasColumnName("phone").HasMaxLength(25);
        builder.Property(x => x.Name).HasColumnName("name").HasMaxLength(150).IsRequired();
        builder.Property(x => x.Password).HasColumnName("password").HasMaxLength(255).IsRequired();
        builder.Property(x => x.PasswordHash).HasColumnName("password_hash").HasMaxLength(255);
        builder.Property(x => x.Ip).HasColumnName("ip").HasMaxLength(100);
        builder.Property(x => x.Status).HasColumnName("status").HasMaxLength(20).IsRequired();
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");
        builder.Property(x => x.UpdatedAt).HasColumnName("updated_at");
        builder.Property(x => x.IsDeleted).HasColumnName("isDeleted");
        builder.Property(x => x.UserToken).HasColumnName("user_token").HasMaxLength(200);
        builder.Property(x => x.RefundStatus).HasColumnName("refund_status").HasMaxLength(20);
        builder.Property(x => x.RefundStatusDate).HasColumnName("refund_status_date");
        builder.Property(x => x.MwReferralId).HasColumnName("mw_referral_id");
        builder.Property(x => x.CollaborationEnabled).HasColumnName("collaboration_enabled").HasMaxLength(3).IsRequired();
        builder.Property(x => x.SaleskitEnabled).HasColumnName("saleskit_enabled").HasMaxLength(3).IsRequired();
        builder.Property(x => x.Influencer).HasColumnName("influencer").HasMaxLength(3).IsRequired();
        builder.Property(x => x.ReferredBy).HasColumnName("referred_by").HasMaxLength(255);
        builder.Property(x => x.SenderUserId).HasColumnName("sender_user_id");
        builder.Property(x => x.SelectService).HasColumnName("select_service").HasMaxLength(200);
        builder.Property(x => x.WalletBalance).HasColumnName("wallet_balance").HasMaxLength(200);
        builder.Property(x => x.GooglePay).HasColumnName("google_pay").HasMaxLength(200);
        builder.Property(x => x.Paytm).HasColumnName("paytm").HasMaxLength(200);
        builder.Property(x => x.RzPay).HasColumnName("rz_pay").HasMaxLength(300);
        builder.Property(x => x.RzPay2).HasColumnName("rz_pay2").HasMaxLength(300);
        builder.Property(x => x.LegacyCustomerId).HasColumnName("legacy_customer_id");
        builder.Property(x => x.LegacyFranchiseeId).HasColumnName("legacy_franchisee_id");
        builder.Property(x => x.LegacyTeamId).HasColumnName("legacy_team_id");
        builder.Property(x => x.LegacyAdminId).HasColumnName("legacy_admin_id");
        builder.Property(x => x.Department).HasColumnName("department").HasMaxLength(100);
        builder.Property(x => x.ProfileImage).HasColumnName("profile_image").HasMaxLength(255);
        builder.Property(x => x.ReferralCode).HasColumnName("referral_code").HasMaxLength(50);
        builder.Property(x => x.District).HasColumnName("district").HasMaxLength(100);
        builder.Property(x => x.State).HasColumnName("state").HasMaxLength(100);

        builder.HasIndex(x => x.Email);
        builder.HasQueryFilter(x => !x.IsDeleted);
    }
}

internal sealed class UserRoleConverter : ValueConverter<UserRole, string>
{
    public UserRoleConverter()
        : base(v => ToDb(v), v => FromDb(v))
    {
    }

    private static string ToDb(UserRole role) => role switch
    {
        UserRole.Customer => "CUSTOMER",
        UserRole.Franchisee => "FRANCHISEE",
        UserRole.Team => "TEAM",
        UserRole.Admin => "ADMIN",
        _ => "CUSTOMER"
    };

    private static UserRole FromDb(string value) =>
        (value ?? "").Trim().ToUpperInvariant() switch
        {
            "CUSTOMER" => UserRole.Customer,
            "FRANCHISEE" => UserRole.Franchisee,
            "TEAM" => UserRole.Team,
            "ADMIN" => UserRole.Admin,
            _ => UserRole.Customer
        };
}
