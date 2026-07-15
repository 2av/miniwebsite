using System.Globalization;
using System.Text;
using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Admin.ManageUsers.Dtos;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;
using MiniWebsite.Domain.Entities;
using MiniWebsite.Domain.Enums;

namespace MiniWebsite.Application.Admin.ManageUsers;

public class AdminManageUsersService : IAdminManageUsersService
{
    private static readonly string[] AllowedRefund =
        ["None", "Refund Claimed", "Refund Settled"];

    private readonly IApplicationDbContext _db;
    private readonly IPasswordHasher _passwordHasher;
    private readonly IEmailSender _email;

    public AdminManageUsersService(
        IApplicationDbContext db,
        IPasswordHasher passwordHasher,
        IEmailSender email)
    {
        _db = db;
        _passwordHasher = passwordHasher;
        _email = email;
    }

    public async Task<ApiResult<ManageUsersPageDto>> ListAsync(ManageUsersQuery query, CancellationToken ct = default)
    {
        query.Page = query.Page < 1 ? 1 : query.Page;
        query.PageSize = query.PageSize is < 1 or > 100 ? 10 : query.PageSize;

        var customers = await ApplyCustomerFilters(_db.Users.AsNoTracking(), query)
            .OrderByDescending(u => u.Id)
            .ToListAsync(ct);

        var totalWebsites = await _db.DigiCards.AsNoTracking().CountAsync(ct);
        var dealEmails = await GetMappedCustomerEmailsAsync(ct);
        var websiteCounts = await GetWebsiteCountsAsync(ct);

        customers = ApplyPostFilters(customers, query, dealEmails, websiteCounts);

        var totalUsers = customers.Count;
        var activeUsers = customers.Count(u =>
            string.Equals(u.Status, "ACTIVE", StringComparison.OrdinalIgnoreCase));
        var usersWithDeals = customers.Count(u => dealEmails.Contains(Normalize(u.Email)));

        var pageUsers = customers
            .Skip((query.Page - 1) * query.PageSize)
            .Take(query.PageSize)
            .ToList();

        var emails = pageUsers.Select(u => Normalize(u.Email)).ToList();
        var mwDealsByEmail = await GetMappedDealsAsync(emails, "MiniWebsite", ct);
        var franchiseDealsByEmail = await GetMappedDealsAsync(emails, "Franchise", ct);
        var refSummaries = await GetReferralSummariesAsync(emails, ct);

        var rows = new List<ManageUserRowDto>(pageUsers.Count);
        foreach (var u in pageUsers)
        {
            var email = Normalize(u.Email);
            websiteCounts.TryGetValue(email, out var wc);
            refSummaries.TryGetValue(email, out var summary);
            summary ??= new ReferralSummary(0, 0, null);

            var pending = Math.Max(0, summary.TotalAmount - summary.PaidAmount);
            mwDealsByEmail.TryGetValue(email, out var mwDeal);
            franchiseDealsByEmail.TryGetValue(email, out var frDeal);

            rows.Add(new ManageUserRowDto(
                u.Id,
                u.Email,
                u.Name,
                u.Phone,
                u.State,
                u.Status,
                u.CreatedAt,
                NormalizeYesNo(u.CollaborationEnabled),
                NormalizeYesNo(u.SaleskitEnabled),
                string.IsNullOrWhiteSpace(u.RefundStatus) ? "None" : u.RefundStatus,
                u.RefundStatusDate,
                u.ReferredBy,
                await BuildReferralSourceAsync(u.ReferredBy, u.Email, ct),
                wc,
                pending,
                summary.TotalAmount,
                summary.PaidAmount,
                summary.LastPaymentDate,
                BuildMwPaymentLabel(summary),
                mwDeal,
                frDeal));
        }

        var mwDeals = await ListActiveDealsAsync("MiniWebsite", ct);
        var frDeals = await ListActiveDealsAsync("Franchise", ct);

        var page = new ManageUsersPageDto(
            new ManageUsersStatsDto(totalUsers, activeUsers, usersWithDeals, totalWebsites),
            mwDeals.Count,
            frDeals.Count,
            mwDeals,
            frDeals,
            rows,
            totalUsers,
            query.Page,
            query.PageSize);

        return ApiResult<ManageUsersPageDto>.Ok(page);
    }

    public async Task<ApiResult> MapDealAsync(MapDealRequest request, CancellationToken ct = default)
    {
        var email = Normalize(request.UserEmail);
        if (string.IsNullOrWhiteSpace(email) || request.DealId <= 0)
            return ApiResult.Fail("userEmail and dealId are required.");

        var ef = RequireEf();
        var exists = await ef.Database
            .SqlQueryRaw<int>(
                @"SELECT COUNT(*) AS Value FROM deal_customer_mapping
                  WHERE deal_id = {0} AND customer_email = {1}",
                request.DealId, email)
            .FirstOrDefaultAsync(ct);

        if (exists > 0)
            return ApiResult.Fail("Deal already mapped to this user!");

        var createdBy = string.IsNullOrWhiteSpace(request.CreatedBy) ? "admin" : request.CreatedBy!.Trim();
        await ef.Database.ExecuteSqlRawAsync(
            @"INSERT INTO deal_customer_mapping (deal_id, customer_email, created_by, created_date)
              VALUES ({0}, {1}, {2}, NOW())",
            [request.DealId, email, createdBy],
            ct);

        return ApiResult.Ok("Deal mapped successfully!");
    }

    public async Task<ApiResult> RemoveDealAsync(int mappingId, CancellationToken ct = default)
    {
        if (mappingId <= 0)
            return ApiResult.Fail("Invalid mapping id.");

        var ef = RequireEf();
        var rows = await ef.Database.ExecuteSqlRawAsync(
            @"DELETE FROM deal_customer_mapping WHERE id = {0}",
            [mappingId],
            ct);

        return rows > 0
            ? ApiResult.Ok("Deal mapping removed successfully!")
            : ApiResult.Fail("Deal mapping not found.");
    }

    public async Task<ApiResult> SetCollaborationAsync(ToggleStatusRequest request, CancellationToken ct = default)
    {
        var email = Normalize(request.Email);
        var status = NormalizeYesNo(request.Status);
        if (status is not ("YES" or "NO"))
            return ApiResult.Fail("Status must be YES or NO.");

        var user = await _db.Users.FirstOrDefaultAsync(
            u => u.Email.ToLower() == email && u.Role == UserRole.Customer, ct);
        if (user == null)
            return ApiResult.Fail("User not found.");

        user.CollaborationEnabled = status;
        user.UpdatedAt = DateTime.UtcNow;
        await _db.SaveChangesAsync(ct);

        if (status == "YES")
        {
            try
            {
                await _email.SendAsync(email, "Congratulations! Your Account Has Been Upgraded with Collaborator Feature",
                    BuildCollaborationEmailHtml(), ct);
                return ApiResult.Ok("User collaboration status updated successfully and congratulatory email sent!");
            }
            catch
            {
                return ApiResult.Ok("User collaboration status updated successfully, but email could not be sent.");
            }
        }

        return ApiResult.Ok("User collaboration status updated successfully");
    }

    public async Task<ApiResult> SetSaleskitAsync(ToggleStatusRequest request, CancellationToken ct = default)
    {
        var email = Normalize(request.Email);
        var status = NormalizeYesNo(request.Status);
        if (status is not ("YES" or "NO"))
            return ApiResult.Fail("Status must be YES or NO.");

        var user = await _db.Users.FirstOrDefaultAsync(
            u => u.Email.ToLower() == email && u.Role == UserRole.Customer, ct);
        if (user == null)
            return ApiResult.Fail("User not found.");

        user.SaleskitEnabled = status;
        user.UpdatedAt = DateTime.UtcNow;
        await _db.SaveChangesAsync(ct);
        return ApiResult.Ok("User sales kit status updated successfully");
    }

    public async Task<ApiResult> SetRefundAsync(SetRefundRequest request, CancellationToken ct = default)
    {
        var email = Normalize(request.Email);
        var refundStatus = (request.RefundStatus ?? "None").Trim();
        if (!AllowedRefund.Contains(refundStatus, StringComparer.Ordinal))
            return ApiResult.Fail("Invalid refund status.");

        var user = await _db.Users.FirstOrDefaultAsync(
            u => u.Email.ToLower() == email && u.Role == UserRole.Customer, ct);
        if (user == null)
            return ApiResult.Fail("User not found.");

        user.RefundStatus = refundStatus;
        if (refundStatus == "None")
            user.RefundStatusDate = null;
        else if (user.RefundStatusDate == null)
            user.RefundStatusDate = DateTime.UtcNow;

        user.UpdatedAt = DateTime.UtcNow;
        await _db.SaveChangesAsync(ct);
        return ApiResult.Ok("success");
    }

    public async Task<ApiResult> ResetPasswordAsync(AdminResetPasswordRequest request, CancellationToken ct = default)
    {
        var email = Normalize(request.Email);
        var role = ParseRole(request.Role);
        if (role == null)
            return ApiResult.Fail("Invalid role.");
        if (string.IsNullOrWhiteSpace(request.NewPassword) || request.NewPassword.Length < 6)
            return ApiResult.Fail("Password must be at least 6 characters.");

        var user = await _db.Users.FirstOrDefaultAsync(
            u => u.Email.ToLower() == email && u.Role == role.Value, ct);
        if (user == null)
            return ApiResult.Fail("User not found for this role.");

        var hash = _passwordHasher.Hash(request.NewPassword);
        user.Password = hash;
        user.PasswordHash = hash;
        user.UpdatedAt = DateTime.UtcNow;
        await _db.SaveChangesAsync(ct);
        return ApiResult.Ok("Password updated successfully for " + email);
    }

    public async Task<ApiResult<DashboardDetailsDto>> GetDashboardDetailsAsync(string userEmail, CancellationToken ct = default)
    {
        var email = Normalize(userEmail);
        if (string.IsNullOrWhiteSpace(email))
            return ApiResult<DashboardDetailsDto>.Fail("Invalid user email provided.");

        var cards = await _db.DigiCards.AsNoTracking()
            .Where(c => c.UserEmail != null && c.UserEmail.ToLower() == email)
            .OrderByDescending(c => c.UploadedDate)
            .ToListAsync(ct);

        var sites = cards.Select(c =>
        {
            var status = ResolveDisplayStatus(c);
            return new DashboardWebsiteDto(
                c.Id,
                c.DCompName,
                c.CardId,
                c.DCardStatus,
                c.DPaymentStatus,
                c.DPaymentDate,
                c.UploadedDate,
                c.ValidityDate,
                c.ComplimentaryEnabled,
                c.FUserEmail,
                status.Class,
                status.Text,
                FormatValidityDisplay(c),
                FormatPaymentLabel(c));
        }).ToList();

        return ApiResult<DashboardDetailsDto>.Ok(new DashboardDetailsDto(email, sites));
    }

    public async Task<ApiResult<ReferralDetailsDto>> GetReferralDetailsAsync(string referrerEmail, CancellationToken ct = default)
    {
        var email = Normalize(referrerEmail);
        if (string.IsNullOrWhiteSpace(email))
            return ApiResult<ReferralDetailsDto>.Fail("Missing referrer email");

        var user = await _db.Users.AsNoTracking()
            .FirstOrDefaultAsync(u => u.Email.ToLower() == email && u.Role == UserRole.Customer, ct);

        var earnings = await _db.ReferralEarnings.AsNoTracking()
            .Where(r => r.ReferrerEmail.ToLower() == email)
            .OrderByDescending(r => r.ReferralDate)
            .ToListAsync(ct);

        var referredEmails = earnings.Select(e => Normalize(e.ReferredEmail)).Distinct().ToList();
        var paidCardEmails = await _db.DigiCards.AsNoTracking()
            .Where(c => c.UserEmail != null
                        && referredEmails.Contains(c.UserEmail.ToLower())
                        && c.DPaymentStatus == "Success")
            .Select(c => c.UserEmail!.ToLower())
            .Distinct()
            .ToListAsync(ct);
        var paidSet = paidCardEmails.ToHashSet(StringComparer.OrdinalIgnoreCase);

        var totalReferral = earnings
            .Where(e => paidSet.Contains(Normalize(e.ReferredEmail)))
            .Sum(e => e.Amount);

        var paidAmount = await GetTotalPaidForReferrerAsync(email, ct);
        var pending = Math.Max(0, totalReferral - paidAmount);

        var regular = earnings.Count(e =>
            !string.Equals(e.IsCollaboration, "YES", StringComparison.OrdinalIgnoreCase));
        var collab = earnings.Count(e =>
            string.Equals(e.IsCollaboration, "YES", StringComparison.OrdinalIgnoreCase));

        var bank = await GetBankDetailsAsync(email, ct);

        var customerMap = await _db.Users.AsNoTracking()
            .Where(u => u.Role == UserRole.Customer && referredEmails.Contains(u.Email.ToLower()))
            .ToDictionaryAsync(u => Normalize(u.Email), u => u, ct);

        var franchiseeMap = await _db.Users.AsNoTracking()
            .Where(u => u.Role == UserRole.Franchisee && referredEmails.Contains(u.Email.ToLower()))
            .ToDictionaryAsync(u => Normalize(u.Email), u => u, ct);

        var allCards = await _db.DigiCards.AsNoTracking()
            .Where(c => c.UserEmail != null && referredEmails.Contains(c.UserEmail.ToLower()))
            .OrderByDescending(c => c.UploadedDate)
            .ToListAsync(ct);
        var cardMap = new Dictionary<string, DigiCard>(StringComparer.OrdinalIgnoreCase);
        foreach (var c in allCards)
        {
            var key = Normalize(c.UserEmail);
            if (!cardMap.ContainsKey(key))
                cardMap[key] = c;
        }

        var referred = new List<ReferredUserDto>();
        foreach (var e in earnings)
        {
            var re = Normalize(e.ReferredEmail);
            var isCollab = string.Equals(e.IsCollaboration, "YES", StringComparison.OrdinalIgnoreCase);
            customerMap.TryGetValue(re, out var cust);
            franchiseeMap.TryGetValue(re, out var fran);
            cardMap.TryGetValue(re, out var card);

            var statusText = card == null ? "-" : ResolveShortStatus(card);
            referred.Add(new ReferredUserDto(
                e.Id,
                e.ReferredEmail,
                e.ReferralDate,
                e.Amount,
                e.IsCollaboration,
                cust?.Id,
                fran?.Id,
                isCollab ? fran?.Name ?? cust?.Name : cust?.Name ?? fran?.Name,
                isCollab ? fran?.Phone ?? cust?.Phone : cust?.Phone ?? fran?.Phone,
                card?.Id,
                card?.UploadedDate,
                card?.ValidityDate,
                card?.ComplimentaryEnabled,
                card?.DPaymentStatus,
                card?.DPaymentDate,
                card?.FUserEmail,
                statusText,
                card == null ? "-" : FormatValidityDisplay(card)));
        }

        return ApiResult<ReferralDetailsDto>.Ok(new ReferralDetailsDto(
            email,
            user?.Name ?? email,
            NormalizeYesNo(user?.CollaborationEnabled),
            NormalizeYesNo(user?.SaleskitEnabled),
            totalReferral,
            paidAmount,
            pending,
            regular,
            collab,
            bank,
            referred));
    }

    public async Task<ApiResult> UpsertBankDetailsAsync(UpsertBankDetailsRequest request, CancellationToken ct = default)
    {
        var email = Normalize(request.UserEmail);
        if (string.IsNullOrWhiteSpace(email))
            return ApiResult.Fail("user_email is required.");

        var ef = RequireEf();
        var exists = await ef.Database
            .SqlQueryRaw<int>(
                @"SELECT COUNT(*) AS Value FROM user_bank_details WHERE user_email = {0}",
                email)
            .FirstOrDefaultAsync(ct);

        if (exists > 0)
        {
            await ef.Database.ExecuteSqlRawAsync(
                @"UPDATE user_bank_details SET
                    account_holder_name = {0},
                    account_number = {1},
                    ifsc_code = {2},
                    bank_name = {3},
                    upi_id = {4},
                    upi_name = {5}
                  WHERE user_email = {6}",
                [
                    request.AccountHolderName ?? "",
                    request.AccountNumber ?? "",
                    request.IfscCode ?? "",
                    request.BankName ?? "",
                    request.UpiId ?? "",
                    request.UpiName ?? "",
                    email
                ],
                ct);
        }
        else
        {
            await ef.Database.ExecuteSqlRawAsync(
                @"INSERT INTO user_bank_details
                    (user_email, account_holder_name, account_number, ifsc_code, bank_name, upi_id, upi_name)
                  VALUES ({0}, {1}, {2}, {3}, {4}, {5}, {6})",
                [
                    email,
                    request.AccountHolderName ?? "",
                    request.AccountNumber ?? "",
                    request.IfscCode ?? "",
                    request.BankName ?? "",
                    request.UpiId ?? "",
                    request.UpiName ?? ""
                ],
                ct);
        }

        return ApiResult.Ok("success");
    }

    public async Task<(byte[] Content, string FileName)> ExportCsvAsync(ManageUsersQuery query, CancellationToken ct = default)
    {
        query.Page = 1;
        query.PageSize = 10000;
        var result = await ListAsync(query, ct);
        var rows = result.Data?.Users ?? [];

        var sb = new StringBuilder();
        sb.AppendLine("Id,Email,Name,Phone,State,Status,JoinedOn,ReferralSource,WebsiteCount,PendingReferral,MwPaymentStatus,SalesKit,Collaboration,RefundStatus");
        foreach (var u in rows)
        {
            sb.AppendLine(string.Join(",",
                u.Id,
                Csv(u.Email),
                Csv(u.Name),
                Csv(u.Phone),
                Csv(u.State),
                Csv(u.Status),
                Csv(u.CreatedAt.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture)),
                Csv(u.ReferralSourceDisplay),
                u.WebsiteCount,
                u.PendingReferralAmount.ToString("0", CultureInfo.InvariantCulture),
                Csv(u.MwPaymentStatusLabel),
                Csv(u.SaleskitEnabled),
                Csv(u.CollaborationEnabled),
                Csv(u.RefundStatus)));
        }

        var bytes = Encoding.UTF8.GetBytes(sb.ToString());
        return (bytes, $"manage_users_{DateTime.UtcNow:yyyyMMdd_HHmmss}.csv");
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private IQueryable<User> ApplyCustomerFilters(IQueryable<User> query, ManageUsersQuery q)
    {
        query = query.Where(u => u.Role == UserRole.Customer);
        query = query.Where(u =>
            u.CollaborationEnabled == null
            || u.CollaborationEnabled.ToUpper() != "YES");

        if (!string.IsNullOrWhiteSpace(q.Search))
        {
            var s = q.Search.Trim().ToLowerInvariant();
            query = query.Where(u =>
                u.Name.ToLower().Contains(s)
                || u.Email.ToLower().Contains(s)
                || (u.Phone != null && u.Phone.ToLower().Contains(s)));
        }

        if (!string.IsNullOrWhiteSpace(q.StatusFilter))
        {
            var status = q.StatusFilter.Equals("YES", StringComparison.OrdinalIgnoreCase)
                ? "ACTIVE"
                : "INACTIVE";
            query = query.Where(u => u.Status == status);
        }

        if (!string.IsNullOrWhiteSpace(q.DateFilter))
        {
            var now = DateTime.UtcNow;
            switch (q.DateFilter.ToLowerInvariant())
            {
                case "today":
                    var today = now.Date;
                    query = query.Where(u => u.CreatedAt >= today);
                    break;
                case "week":
                    query = query.Where(u => u.CreatedAt >= now.AddDays(-7));
                    break;
                case "month":
                    query = query.Where(u => u.CreatedAt >= now.AddMonths(-1));
                    break;
                case "year":
                    query = query.Where(u => u.CreatedAt >= now.AddYears(-1));
                    break;
            }
        }

        return query;
    }

    private static List<User> ApplyPostFilters(
        List<User> customers,
        ManageUsersQuery q,
        HashSet<string> dealEmails,
        Dictionary<string, int> websiteCounts)
    {
        IEnumerable<User> result = customers;

        if (string.Equals(q.DealFilter, "mapped", StringComparison.OrdinalIgnoreCase))
            result = result.Where(u => dealEmails.Contains(Normalize(u.Email)));
        else if (string.Equals(q.DealFilter, "unmapped", StringComparison.OrdinalIgnoreCase))
            result = result.Where(u => !dealEmails.Contains(Normalize(u.Email)));

        if (!string.IsNullOrWhiteSpace(q.WebsiteFilter))
        {
            result = q.WebsiteFilter switch
            {
                "0" => result.Where(u => !websiteCounts.ContainsKey(Normalize(u.Email)) || websiteCounts[Normalize(u.Email)] == 0),
                "1-5" => result.Where(u =>
                {
                    websiteCounts.TryGetValue(Normalize(u.Email), out var c);
                    return c is >= 1 and <= 5;
                }),
                "6-10" => result.Where(u =>
                {
                    websiteCounts.TryGetValue(Normalize(u.Email), out var c);
                    return c is >= 6 and <= 10;
                }),
                "10+" => result.Where(u =>
                {
                    websiteCounts.TryGetValue(Normalize(u.Email), out var c);
                    return c > 10;
                }),
                _ => result
            };
        }

        return result.ToList();
    }

    private async Task<HashSet<string>> GetMappedCustomerEmailsAsync(CancellationToken ct)
    {
        var ef = RequireEf();
        var rows = await ef.Database
            .SqlQueryRaw<StringValueRow>(
                @"SELECT DISTINCT customer_email AS Value FROM deal_customer_mapping WHERE customer_email IS NOT NULL")
            .ToListAsync(ct);
        return rows
            .Where(r => !string.IsNullOrWhiteSpace(r.Value))
            .Select(r => Normalize(r.Value!))
            .ToHashSet(StringComparer.OrdinalIgnoreCase);
    }

    private async Task<Dictionary<string, int>> GetWebsiteCountsAsync(CancellationToken ct)
    {
        var groups = await _db.DigiCards.AsNoTracking()
            .Where(c => c.UserEmail != null)
            .GroupBy(c => c.UserEmail!.ToLower())
            .Select(g => new { Email = g.Key, Count = g.Count() })
            .ToListAsync(ct);

        return groups.ToDictionary(g => g.Email, g => g.Count, StringComparer.OrdinalIgnoreCase);
    }

    private async Task<Dictionary<string, MappedDealDto>> GetMappedDealsAsync(
        IReadOnlyList<string> emails, string planType, CancellationToken ct)
    {
        var result = new Dictionary<string, MappedDealDto>(StringComparer.OrdinalIgnoreCase);
        if (emails.Count == 0) return result;

        var ef = RequireEf();
        // Load all mappings for plan type then filter in memory (param list varies)
        var rows = await ef.Database
            .SqlQueryRaw<MappedDealSqlRow>(
                @"SELECT dcm.id AS MappingId, dcm.deal_id AS DealId, dcm.customer_email AS CustomerEmail,
                         d.deal_name AS DealName, d.coupon_code AS CouponCode
                  FROM deal_customer_mapping dcm
                  INNER JOIN deals d ON dcm.deal_id = d.id
                  WHERE d.plan_type = {0}
                  ORDER BY dcm.id DESC",
                planType)
            .ToListAsync(ct);

        var emailSet = emails.ToHashSet(StringComparer.OrdinalIgnoreCase);
        foreach (var row in rows)
        {
            if (row.CustomerEmail == null) continue;
            var key = Normalize(row.CustomerEmail);
            if (!emailSet.Contains(key) || result.ContainsKey(key)) continue;
            result[key] = new MappedDealDto(row.MappingId, row.DealId, row.DealName ?? "", row.CouponCode);
        }

        return result;
    }

    private async Task<List<DealOptionDto>> ListActiveDealsAsync(string planType, CancellationToken ct)
    {
        var ef = RequireEf();
        var rows = await ef.Database
            .SqlQueryRaw<DealOptionSqlRow>(
                @"SELECT id AS Id, deal_name AS DealName, coupon_code AS CouponCode, plan_type AS PlanType
                  FROM deals
                  WHERE deal_status = 'Active' AND plan_type = {0}
                  ORDER BY deal_name",
                planType)
            .ToListAsync(ct);

        return rows.Select(r => new DealOptionDto(r.Id, r.DealName ?? "", r.CouponCode, r.PlanType ?? planType)).ToList();
    }

    private async Task<Dictionary<string, ReferralSummary>> GetReferralSummariesAsync(
        IReadOnlyList<string> emails, CancellationToken ct)
    {
        var map = new Dictionary<string, ReferralSummary>(StringComparer.OrdinalIgnoreCase);
        if (emails.Count == 0) return map;

        var totals = await _db.ReferralEarnings.AsNoTracking()
            .Where(r => emails.Contains(r.ReferrerEmail.ToLower()))
            .GroupBy(r => r.ReferrerEmail.ToLower())
            .Select(g => new { Email = g.Key, Total = g.Sum(x => x.Amount) })
            .ToListAsync(ct);

        foreach (var t in totals)
        {
            var paid = await GetTotalPaidForReferrerAsync(t.Email, ct);
            var last = await GetLastPaymentDateAsync(t.Email, ct);
            map[t.Email] = new ReferralSummary(t.Total, paid, last);
        }

        return map;
    }

    private async Task<decimal> GetTotalPaidForReferrerAsync(string referrerEmail, CancellationToken ct)
    {
        var ef = RequireEf();
        try
        {
            return await ef.Database
                .SqlQueryRaw<decimal>(
                    @"SELECT COALESCE(SUM(rph.amount), 0) AS Value
                      FROM referral_payment_history rph
                      INNER JOIN referral_earnings re ON rph.referral_id = re.id
                      WHERE re.referrer_email = {0}",
                    referrerEmail)
                .FirstOrDefaultAsync(ct);
        }
        catch
        {
            return 0;
        }
    }

    private async Task<DateTime?> GetLastPaymentDateAsync(string referrerEmail, CancellationToken ct)
    {
        var ef = RequireEf();
        try
        {
            var rows = await ef.Database
                .SqlQueryRaw<DateValueRow>(
                    @"SELECT MAX(rph.payment_date) AS Value
                      FROM referral_payment_history rph
                      INNER JOIN referral_earnings re ON rph.referral_id = re.id
                      WHERE re.referrer_email = {0}",
                    referrerEmail)
                .ToListAsync(ct);
            return rows.FirstOrDefault()?.Value;
        }
        catch
        {
            return null;
        }
    }

    private async Task<BankDetailsDto> GetBankDetailsAsync(string email, CancellationToken ct)
    {
        var ef = RequireEf();
        try
        {
            var row = await ef.Database
                .SqlQueryRaw<BankSqlRow>(
                    @"SELECT account_holder_name AS AccountHolderName, account_number AS AccountNumber,
                             ifsc_code AS IfscCode, bank_name AS BankName, upi_id AS UpiId, upi_name AS UpiName
                      FROM user_bank_details WHERE user_email = {0} LIMIT 1",
                    email)
                .FirstOrDefaultAsync(ct);

            return row == null
                ? new BankDetailsDto(null, null, null, null, null, null)
                : new BankDetailsDto(row.AccountHolderName, row.AccountNumber, row.IfscCode, row.BankName, row.UpiId, row.UpiName);
        }
        catch
        {
            return new BankDetailsDto(null, null, null, null, null, null);
        }
    }

    private async Task<string> BuildReferralSourceAsync(string? referredBy, string userEmail, CancellationToken ct)
    {
        if (string.IsNullOrWhiteSpace(referredBy))
            return "Direct";

        var refEmail = Normalize(referredBy);
        var referrer = await _db.Users.AsNoTracking()
            .FirstOrDefaultAsync(u => u.Email.ToLower() == refEmail, ct);

        string display;
        if (referrer == null)
        {
            display = "Ref: " + referredBy.Trim();
        }
        else
        {
            display = referrer.Role switch
            {
                UserRole.Franchisee => "FR - " + referrer.Id.ToString("D3", CultureInfo.InvariantCulture),
                UserRole.Team => "Team - " + referrer.Id,
                UserRole.Admin => "Admin - " + referrer.Id,
                _ => "User - " + referrer.Id
            };
        }

        var hasFrd = await _db.ReferralEarnings.AsNoTracking().AnyAsync(r =>
            r.ReferrerEmail.ToLower() == refEmail
            && r.ReferredEmail.ToLower() == Normalize(userEmail)
            && r.IsCollaboration == "YES", ct);

        if (hasFrd)
            display += " (FRD)";

        return display;
    }

    private static string BuildMwPaymentLabel(ReferralSummary s)
    {
        if (s.TotalAmount <= 0) return "Not Eligible";
        if (s.PaidAmount >= s.TotalAmount)
        {
            var d = s.LastPaymentDate?.ToString("dd-MM-yyyy", CultureInfo.InvariantCulture)
                    ?? DateTime.UtcNow.ToString("dd-MM-yyyy", CultureInfo.InvariantCulture);
            return "Paid on " + d;
        }
        if (s.PaidAmount > 0) return "Partial Payment";
        return "Pending";
    }

    private static (string Class, string Text) ResolveDisplayStatus(DigiCard c)
    {
        if (string.Equals(c.ComplimentaryEnabled, "Yes", StringComparison.OrdinalIgnoreCase))
            return ("bg-success", "Active");

        if (string.Equals(c.DPaymentStatus, "Success", StringComparison.OrdinalIgnoreCase))
        {
            if (c.ValidityDate.HasValue && c.ValidityDate.Value < DateTime.UtcNow)
                return ("bg-secondary", "Expired on " + c.ValidityDate.Value.ToString("dd-MM-yyyy", CultureInfo.InvariantCulture));
            return ("bg-success", "Active");
        }

        var uploaded = c.UploadedDate;
        var trialEnd = uploaded?.AddDays(7);
        var trialExpired = trialEnd.HasValue && trialEnd.Value < DateTime.UtcNow;
        var franchiseLinked = !string.IsNullOrWhiteSpace(c.FUserEmail) && c.FUserEmail.Trim().Length >= 3;

        if (franchiseLinked)
            return trialExpired ? ("bg-secondary", "Inactive") : ("bg-pending", "Pending Payment");

        if (trialExpired)
            return ("bg-secondary", "Inactive");

        return ("bg-pending", "7 Day Trial");
    }

    private static string ResolveShortStatus(DigiCard c) => ResolveDisplayStatus(c).Text;

    private static string FormatValidityDisplay(DigiCard c)
    {
        if (c.ValidityDate.HasValue)
            return c.ValidityDate.Value.ToString("dd-MM-yyyy", CultureInfo.InvariantCulture);

        if (string.Equals(c.DPaymentStatus, "Success", StringComparison.OrdinalIgnoreCase) && c.DPaymentDate.HasValue)
            return c.DPaymentDate.Value.AddYears(1).ToString("dd-MM-yyyy", CultureInfo.InvariantCulture);

        if (c.UploadedDate.HasValue)
            return c.UploadedDate.Value.AddDays(7).ToString("dd-MM-yyyy", CultureInfo.InvariantCulture);

        return "-";
    }

    private static string FormatPaymentLabel(DigiCard c)
    {
        if (string.Equals(c.ComplimentaryEnabled, "Yes", StringComparison.OrdinalIgnoreCase))
            return "Complimentary";
        if (string.Equals(c.DPaymentStatus, "Success", StringComparison.OrdinalIgnoreCase))
        {
            if (c.DPaymentDate.HasValue)
                return "Paid on " + c.DPaymentDate.Value.ToString("dd-MM-yyyy", CultureInfo.InvariantCulture);
            return "Paid";
        }
        return "Pending";
    }

    private DbContext RequireEf() =>
        _db as DbContext
        ?? throw new InvalidOperationException("IApplicationDbContext must be an EF DbContext for raw SQL.");

    private static string Normalize(string? value) => (value ?? string.Empty).Trim().ToLowerInvariant();

    private static string NormalizeYesNo(string? value)
    {
        var v = (value ?? "NO").Trim().ToUpperInvariant();
        return v == "YES" ? "YES" : "NO";
    }

    private static UserRole? ParseRole(string? role) =>
        (role ?? "").Trim().ToUpperInvariant() switch
        {
            "CUSTOMER" => UserRole.Customer,
            "FRANCHISEE" => UserRole.Franchisee,
            "TEAM" => UserRole.Team,
            "ADMIN" => UserRole.Admin,
            _ => null
        };

    private static string Csv(string? v)
    {
        var s = v ?? "";
        if (s.Contains(',') || s.Contains('"') || s.Contains('\n'))
            return "\"" + s.Replace("\"", "\"\"") + "\"";
        return s;
    }

    private static string BuildCollaborationEmailHtml() =>
        """
        <!DOCTYPE html><html><body style="font-family:Arial,sans-serif;">
        <h2>Congratulations!</h2>
        <p>Your MiniWebsite account has been upgraded with the <strong>Collaborator Feature</strong>.</p>
        <p><a href="https://miniwebsite.in/login/customer.php">Login to Your Account</a></p>
        <p>Best regards,<br>MiniWebsite Team</p>
        </body></html>
        """;

    private sealed record ReferralSummary(decimal TotalAmount, decimal PaidAmount, DateTime? LastPaymentDate);

    private sealed class StringValueRow { public string? Value { get; set; } }
    private sealed class DateValueRow { public DateTime? Value { get; set; } }
    private sealed class MappedDealSqlRow
    {
        public int MappingId { get; set; }
        public int DealId { get; set; }
        public string? CustomerEmail { get; set; }
        public string? DealName { get; set; }
        public string? CouponCode { get; set; }
    }
    private sealed class DealOptionSqlRow
    {
        public int Id { get; set; }
        public string? DealName { get; set; }
        public string? CouponCode { get; set; }
        public string? PlanType { get; set; }
    }
    private sealed class BankSqlRow
    {
        public string? AccountHolderName { get; set; }
        public string? AccountNumber { get; set; }
        public string? IfscCode { get; set; }
        public string? BankName { get; set; }
        public string? UpiId { get; set; }
        public string? UpiName { get; set; }
    }
}
