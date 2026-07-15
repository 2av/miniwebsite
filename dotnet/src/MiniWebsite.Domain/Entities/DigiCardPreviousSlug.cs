namespace MiniWebsite.Domain.Entities;

/// <summary>
/// Maps to <c>digi_card_previous_slug</c>.
/// Holds previous URL slug + company-details business meta (kept off digi_card for row-size limits).
/// </summary>
public class DigiCardPreviousSlug
{
    public uint DigiCardId { get; set; }
    public required string PreviousSlug { get; set; }
    public string DBusinessType { get; set; } = "";
    public string DBusinessOperationArea { get; set; } = "";
    public string? DBusinessOperationLocations { get; set; }
    public string DBusinessProfileType { get; set; } = "";
}
