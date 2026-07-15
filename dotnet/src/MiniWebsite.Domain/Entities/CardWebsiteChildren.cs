namespace MiniWebsite.Domain.Entities;

public class CardProductPricing
{
    public int Id { get; set; }
    public required string CardId { get; set; }
    public int UserId { get; set; }
    public string? ProductName { get; set; }
    public int? ProductCategory { get; set; }
    public string? CategorySource { get; set; }
    public string? ProductDescription { get; set; }
    /// <summary>Often a filename stored in longblob (PHP pattern).</summary>
    public string? ProductImage { get; set; }
    public decimal Mrp { get; set; }
    public decimal SellingPrice { get; set; }
    public string? PriceType { get; set; }
    public string? PricingUnit { get; set; }
    public string? CtaText { get; set; }
    public decimal TaxRate { get; set; }
    public int DisplayOrder { get; set; }
    public DateTime? CreatedAt { get; set; }
    public DateTime? UpdatedAt { get; set; }
}

public class CardProductService
{
    public int Id { get; set; }
    public required string CardId { get; set; }
    public int UserId { get; set; }
    public string? ProductName { get; set; }
    public int? ProductCategory { get; set; }
    public string? CategorySource { get; set; }
    public string? ProductDescription { get; set; }
    public string? ProductImage { get; set; }
    public int DisplayOrder { get; set; }
    public DateTime? CreatedAt { get; set; }
    public DateTime? UpdatedAt { get; set; }
}

public class CardImageGallery
{
    public int Id { get; set; }
    public required string CardId { get; set; }
    public int UserId { get; set; }
    public string? GalleryImage { get; set; }
    public int DisplayOrder { get; set; }
    public DateTime? CreatedAt { get; set; }
    public DateTime? UpdatedAt { get; set; }
}

public class CardSpecialOffer
{
    public int Id { get; set; }
    public required string CardId { get; set; }
    public int UserId { get; set; }
    public required string OfferTitle { get; set; }
    public string? OfferDescription { get; set; }
    public string? OfferImage { get; set; }
    public string? Badge { get; set; }
    public int DiscountPercentage { get; set; }
    public DateOnly? StartDate { get; set; }
    public TimeOnly? StartTime { get; set; }
    public DateOnly? EndDate { get; set; }
    public TimeOnly? EndTime { get; set; }
    public string? Status { get; set; }
    public int DisplayOrder { get; set; }
    public DateTime? CreatedAt { get; set; }
    public DateTime? UpdatedAt { get; set; }
}
