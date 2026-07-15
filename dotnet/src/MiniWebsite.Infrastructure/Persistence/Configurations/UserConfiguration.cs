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
        // Live PHP schema uses user_details (not the scaffolded "users" table).
        builder.ToTable("user_details");
        builder.HasKey(x => x.Id);

        builder.Property(x => x.Email).HasColumnName("email").HasMaxLength(200).IsRequired();
        builder.Property(x => x.Phone).HasColumnName("phone").HasMaxLength(20);
        builder.Property(x => x.Name).HasColumnName("name").HasMaxLength(200).IsRequired();
        builder.Property(x => x.PasswordHash).HasColumnName("password_hash").HasMaxLength(500).IsRequired();
        builder.Property(x => x.Status).HasColumnName("status").HasMaxLength(50).IsRequired();
        builder.Property(x => x.State).HasColumnName("state").HasMaxLength(100);
        builder.Property(x => x.ReferralCode).HasColumnName("referral_code").HasMaxLength(50);
        builder.Property(x => x.ReferredBy).HasColumnName("referred_by").HasMaxLength(200);
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");

        builder.Property(x => x.Role)
            .HasColumnName("role")
            .HasMaxLength(50)
            .HasConversion(new UserRoleConverter())
            .IsRequired();

        // Not present on live user_details
        builder.Ignore(x => x.UpdatedAt);
        builder.Ignore(x => x.IsDeleted);

        builder.HasIndex(x => x.Email).IsUnique();

        // Soft-deleted PHP users use status, not IsDeleted
        builder.HasQueryFilter(x => x.Status != "DELETED");
    }
}

internal sealed class UserRoleConverter : ValueConverter<UserRole, string>
{
    public UserRoleConverter()
        : base(
            v => ToDb(v),
            v => FromDb(v))
    {
    }

    private static string ToDb(UserRole role) => role switch
    {
        UserRole.Customer => "CUSTOMER",
        UserRole.Franchisee => "FRANCHISEE",
        UserRole.Team => "TEAM",
        UserRole.Admin => "ADMIN",
        _ => role.ToString().ToUpperInvariant()
    };

    private static UserRole FromDb(string value)
    {
        if (string.IsNullOrWhiteSpace(value))
            return UserRole.Customer;

        return value.Trim().ToUpperInvariant() switch
        {
            "CUSTOMER" => UserRole.Customer,
            "FRANCHISEE" => UserRole.Franchisee,
            "TEAM" => UserRole.Team,
            "ADMIN" => UserRole.Admin,
            _ => Enum.TryParse<UserRole>(value, true, out var parsed) ? parsed : UserRole.Customer
        };
    }
}
