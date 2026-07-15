namespace MiniWebsite.Domain.Entities;

/// <summary>Maps to live <c>digi_card</c> (Mini Website / card). Blobs excluded.</summary>
public class DigiCard
{
    public int Id { get; set; }
    public string? Ip { get; set; }
    public string? FUserEmail { get; set; }
    public string? UserEmail { get; set; }
    public string? CardId { get; set; }
    public string? Password { get; set; }
    public string? DCss { get; set; }
    public string? DMobileCss { get; set; }
    public string? DCompName { get; set; }
    public string? DDisplayName { get; set; }
    public string? DTitle { get; set; }
    public string? DFName { get; set; }
    public string? DLName { get; set; }
    public string? DPosition { get; set; }
    public string? DContact { get; set; }
    public string? DContact2 { get; set; }
    public string? DWhatsapp { get; set; }
    public string? DAddress { get; set; }
    public string? DEmail { get; set; }
    public string? DWebsite { get; set; }
    public string? DLocation { get; set; }
    public string? DFb { get; set; }
    public string? DTwitter { get; set; }
    public string? DInstagram { get; set; }
    public string? DLinkedin { get; set; }
    public string? DYoutube { get; set; }
    public string? DPinterest { get; set; }
    public string? DWebsite2 { get; set; }
    public string? DCompEstDate { get; set; }
    public string? DNatureOfBusiness { get; set; }
    public string? DSpeciality { get; set; }
    public string? DAboutUs { get; set; }
    public string? DPaytm { get; set; }
    public string? DGooglePay { get; set; }
    public string? DPhonePay { get; set; }
    public string? DAccountNo { get; set; }
    public string? DIfsc { get; set; }
    public string? DAcName { get; set; }
    public string? DBankName { get; set; }
    public string? DAcType { get; set; }
    public string? DPaymentStatus { get; set; }
    public string? DCardStatus { get; set; }
    public string? DPaymentAmount { get; set; }
    public string? DOrderId { get; set; }
    public string? DLogoLocation { get; set; }
    public DateTime? UploadedDate { get; set; }
    public DateTime? DPaymentDate { get; set; }
    public string? DGst { get; set; }
    public string? DGstName { get; set; }
    public string? DGstAddress { get; set; }
    public string? DGstState { get; set; }
    public string? DGstCity { get; set; }
    public string? DGstPincode { get; set; }
    public string? CollaborationEnabled { get; set; }
    public string? ComplimentaryEnabled { get; set; }
    public string? DGstEmail { get; set; }
    public string? DGstContact { get; set; }
    public DateTime? ValidityDate { get; set; }
    public int NameChangeCount { get; set; }
    public int DNameChangeCount { get; set; }
    public string? DHeroImageLocation { get; set; }
    public string DPositionPrimary { get; set; } = "";
    public string DPositionSecondary { get; set; } = "";
    public string DGstNumber { get; set; } = "";
    public string DAddress2 { get; set; } = "";
    public string DCity { get; set; } = "";
    public string DState { get; set; } = "";
    public string DPincode { get; set; } = "";
    public string DCountry { get; set; } = "";
    public string? DBusinessHours { get; set; }

    // YouTube slots 1–20 (text URLs only)
    public string? DYoutube1 { get; set; }
    public string? DYoutube2 { get; set; }
    public string? DYoutube3 { get; set; }
    public string? DYoutube4 { get; set; }
    public string? DYoutube5 { get; set; }
    public string? DYoutube6 { get; set; }
    public string? DYoutube7 { get; set; }
    public string? DYoutube8 { get; set; }
    public string? DYoutube9 { get; set; }
    public string? DYoutube10 { get; set; }
    public string? DYoutube11 { get; set; }
    public string? DYoutube12 { get; set; }
    public string? DYoutube13 { get; set; }
    public string? DYoutube14 { get; set; }
    public string? DYoutube15 { get; set; }
    public string? DYoutube16 { get; set; }
    public string? DYoutube17 { get; set; }
    public string? DYoutube18 { get; set; }
    public string? DYoutube19 { get; set; }
    public string? DYoutube20 { get; set; }

    // Product names 1–10
    public string? DProName1 { get; set; }
    public string? DProName2 { get; set; }
    public string? DProName3 { get; set; }
    public string? DProName4 { get; set; }
    public string? DProName5 { get; set; }
    public string? DProName6 { get; set; }
    public string? DProName7 { get; set; }
    public string? DProName8 { get; set; }
    public string? DProName9 { get; set; }
    public string? DProName10 { get; set; }
}
