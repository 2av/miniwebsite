using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;
using MiniWebsite.Domain.Entities;

namespace MiniWebsite.Infrastructure.Persistence.Configurations;

public class DigiCardPreviousSlugConfiguration : IEntityTypeConfiguration<DigiCardPreviousSlug>
{
    public void Configure(EntityTypeBuilder<DigiCardPreviousSlug> builder)
    {
        builder.ToTable("digi_card_previous_slug");
        builder.HasKey(x => x.DigiCardId);
        builder.Property(x => x.DigiCardId).HasColumnName("digi_card_id");
        builder.Property(x => x.PreviousSlug).HasColumnName("previous_slug").HasMaxLength(255).IsRequired();
        builder.Property(x => x.DBusinessType).HasColumnName("d_business_type").HasMaxLength(32).IsRequired();
        builder.Property(x => x.DBusinessOperationArea).HasColumnName("d_business_operation_area").HasMaxLength(32).IsRequired();
        builder.Property(x => x.DBusinessOperationLocations).HasColumnName("d_business_operation_locations");
        builder.Property(x => x.DBusinessProfileType).HasColumnName("d_business_profile_type").HasMaxLength(100).IsRequired();
        builder.HasIndex(x => x.PreviousSlug).IsUnique();
    }
}
