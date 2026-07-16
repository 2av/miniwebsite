using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;
using MiniWebsite.Domain.Entities;

namespace MiniWebsite.Infrastructure.Persistence.Configurations;

/// <summary>
/// Key-only mapping. Manage Deals uses raw SQL because live column types are mixed.
/// </summary>
public class DealConfiguration : IEntityTypeConfiguration<Deal>
{
    public void Configure(EntityTypeBuilder<Deal> builder)
    {
        builder.ToTable("deals");
        builder.HasKey(x => x.Id);
        builder.Property(x => x.Id).HasColumnName("id");
        builder.Property(x => x.PlanName).HasColumnName("plan_name").HasMaxLength(100);
        builder.Property(x => x.PlanType).HasColumnName("plan_type").HasMaxLength(50);
        builder.Property(x => x.DealState).HasColumnName("deal_state").HasMaxLength(100);
        builder.Property(x => x.DealName).HasColumnName("deal_name").HasMaxLength(255);
        builder.Property(x => x.CouponCode).HasColumnName("coupon_code").HasMaxLength(100);
        builder.Property(x => x.DealStatus).HasColumnName("deal_status").HasMaxLength(50);
        builder.Property(x => x.CreatedBy).HasColumnName("created_by").HasMaxLength(255);

        builder.Ignore(x => x.BonusAmount);
        builder.Ignore(x => x.DiscountAmount);
        builder.Ignore(x => x.DiscountPercentage);
        builder.Ignore(x => x.ValidityDate);
        builder.Ignore(x => x.MaxUsage);
        builder.Ignore(x => x.CurrentUsage);
        builder.Ignore(x => x.UploadedDate);
    }
}
