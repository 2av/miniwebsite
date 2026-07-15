using System.Text;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;
using Microsoft.EntityFrameworkCore.Storage.ValueConversion;
using MiniWebsite.Domain.Entities;

namespace MiniWebsite.Infrastructure.Persistence.Configurations;

/// <summary>PHP sometimes stores image filenames as text inside longblob columns.</summary>
internal static class BlobFilenameConverters
{
    public static readonly ValueConverter<string?, byte[]?> Utf8 =
        new(
            v => v == null ? null : Encoding.UTF8.GetBytes(v),
            v => v == null || v.Length == 0 ? null : Encoding.UTF8.GetString(v));
}

public class CardProductPricingConfiguration : IEntityTypeConfiguration<CardProductPricing>
{
    public void Configure(EntityTypeBuilder<CardProductPricing> builder)
    {
        builder.ToTable("card_product_pricing");
        builder.HasKey(x => x.Id);
        builder.Property(x => x.CardId).HasColumnName("card_id").HasMaxLength(50).IsRequired();
        builder.Property(x => x.UserId).HasColumnName("user_id");
        builder.Property(x => x.ProductName).HasColumnName("product_name").HasMaxLength(200);
        builder.Property(x => x.ProductCategory).HasColumnName("product_category");
        builder.Property(x => x.CategorySource).HasColumnName("category_source").HasMaxLength(10);
        builder.Property(x => x.ProductDescription).HasColumnName("product_description");
        builder.Property(x => x.ProductImage).HasColumnName("product_image").HasConversion(BlobFilenameConverters.Utf8);
        builder.Property(x => x.Mrp).HasColumnName("mrp").HasPrecision(10, 2);
        builder.Property(x => x.SellingPrice).HasColumnName("selling_price").HasPrecision(10, 2);
        builder.Property(x => x.PriceType).HasColumnName("price_type").HasMaxLength(20);
        builder.Property(x => x.PricingUnit).HasColumnName("pricing_unit").HasMaxLength(30);
        builder.Property(x => x.CtaText).HasColumnName("cta_text").HasMaxLength(50);
        builder.Property(x => x.TaxRate).HasColumnName("tax_rate").HasPrecision(10, 2);
        builder.Property(x => x.DisplayOrder).HasColumnName("display_order");
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");
        builder.Property(x => x.UpdatedAt).HasColumnName("updated_at");
    }
}

public class CardProductServiceConfiguration : IEntityTypeConfiguration<CardProductService>
{
    public void Configure(EntityTypeBuilder<CardProductService> builder)
    {
        builder.ToTable("card_products_services");
        builder.HasKey(x => x.Id);
        builder.Property(x => x.CardId).HasColumnName("card_id").HasMaxLength(50).IsRequired();
        builder.Property(x => x.UserId).HasColumnName("user_id");
        builder.Property(x => x.ProductName).HasColumnName("product_name").HasMaxLength(200);
        builder.Property(x => x.ProductCategory).HasColumnName("product_category");
        builder.Property(x => x.CategorySource).HasColumnName("category_source").HasMaxLength(10);
        builder.Property(x => x.ProductDescription).HasColumnName("product_description");
        builder.Property(x => x.ProductImage).HasColumnName("product_image").HasConversion(BlobFilenameConverters.Utf8);
        builder.Property(x => x.DisplayOrder).HasColumnName("display_order");
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");
        builder.Property(x => x.UpdatedAt).HasColumnName("updated_at");
    }
}

public class CardImageGalleryConfiguration : IEntityTypeConfiguration<CardImageGallery>
{
    public void Configure(EntityTypeBuilder<CardImageGallery> builder)
    {
        builder.ToTable("card_image_gallery");
        builder.HasKey(x => x.Id);
        builder.Property(x => x.CardId).HasColumnName("card_id").HasMaxLength(50).IsRequired();
        builder.Property(x => x.UserId).HasColumnName("user_id");
        builder.Property(x => x.GalleryImage).HasColumnName("gallery_image").HasConversion(BlobFilenameConverters.Utf8);
        builder.Property(x => x.DisplayOrder).HasColumnName("display_order");
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");
        builder.Property(x => x.UpdatedAt).HasColumnName("updated_at");
    }
}

public class CardSpecialOfferConfiguration : IEntityTypeConfiguration<CardSpecialOffer>
{
    public void Configure(EntityTypeBuilder<CardSpecialOffer> builder)
    {
        builder.ToTable("card_special_offers");
        builder.HasKey(x => x.Id);
        builder.Property(x => x.CardId).HasColumnName("card_id").HasMaxLength(50).IsRequired();
        builder.Property(x => x.UserId).HasColumnName("user_id");
        builder.Property(x => x.OfferTitle).HasColumnName("offer_title").HasMaxLength(255).IsRequired();
        builder.Property(x => x.OfferDescription).HasColumnName("offer_description");
        builder.Property(x => x.OfferImage).HasColumnName("offer_image").HasMaxLength(255);
        builder.Property(x => x.Badge).HasColumnName("badge").HasMaxLength(100);
        builder.Property(x => x.DiscountPercentage).HasColumnName("discount_percentage");
        builder.Property(x => x.StartDate).HasColumnName("start_date");
        builder.Property(x => x.StartTime).HasColumnName("start_time");
        builder.Property(x => x.EndDate).HasColumnName("end_date");
        builder.Property(x => x.EndTime).HasColumnName("end_time");
        builder.Property(x => x.Status).HasColumnName("status").HasMaxLength(50);
        builder.Property(x => x.DisplayOrder).HasColumnName("display_order");
        builder.Property(x => x.CreatedAt).HasColumnName("created_at");
        builder.Property(x => x.UpdatedAt).HasColumnName("updated_at");
    }
}
