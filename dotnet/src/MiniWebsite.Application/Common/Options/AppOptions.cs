namespace MiniWebsite.Application.Common.Options;

public class AppOptions
{
    public const string SectionName = "App";
    public string PublicHost { get; set; } = "miniwebsite.in";
    public string CustomerDashboardUrl { get; set; } = "https://miniwebsite.in/user/dashboard";
    public string FranchiseeLoginUrl { get; set; } = "https://miniwebsite.in/login/franchisee.php";
    public string FranchiseAgreementUrl { get; set; } = "https://miniwebsite.in/franchise_agreement.php";

    /// <summary>When true, Swagger UI is available even outside Development (useful for first server test).</summary>
    public bool EnableSwagger { get; set; }

    /// <summary>When true, 500 responses include exception type, message, and stack (server debugging only).</summary>
    public bool ExposeExceptionDetails { get; set; }

    /// <summary>
    /// PHP site origin that serves uploaded website assets (no trailing slash).
    /// Example: https://miniwebsite.in
    /// </summary>
    public string PhpSiteBaseUrl { get; set; } = "https://miniwebsite.in";

    /// <summary>Root folder under PHP site for website uploads.</summary>
    public string WebsiteUploadsPath { get; set; } = "/assets/upload/websites";
}
