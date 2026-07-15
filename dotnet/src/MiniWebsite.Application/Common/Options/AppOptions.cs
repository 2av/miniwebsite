namespace MiniWebsite.Application.Common.Options;

public class AppOptions
{
    public const string SectionName = "App";
    public string PublicHost { get; set; } = "miniwebsite.in";
    public string CustomerDashboardUrl { get; set; } = "https://miniwebsite.in/user/dashboard";
    public string FranchiseeLoginUrl { get; set; } = "https://miniwebsite.in/login/franchisee.php";
    public string FranchiseAgreementUrl { get; set; } = "https://miniwebsite.in/franchise_agreement.php";
}
