using System.Globalization;
using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Admin.FranchiseDistributors.Dtos;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;
using MiniWebsite.Domain.Entities;
using MiniWebsite.Domain.Enums;

namespace MiniWebsite.Application.Admin.FranchiseDistributors;

public class AdminFranchiseDistributorsService : IAdminFranchiseDistributorsService
{
    private readonly IApplicationDbContext _db;

    public AdminFranchiseDistributorsService(IApplicationDbContext db)
    {
        _db = db;
    }

    public async Task<ApiResult<FranchiseDistributorPageDto>> ListAsync(
        FranchiseDistributorQuery query, CancellationToken ct = default)
    {
        query.Page = query.Page < 1 ? 1 : query.Page;
        query.PageSize = query.PageSize is < 1 or > 100 ? 15 : query.PageSize;

        var q = _db.Users.AsNoTracking()
            .Where(u => u.Role == UserRole.Customer
                        && u.CollaborationEnabled != null
                        && u.CollaborationEnabled.ToUpper() == "YES");

        if (!string.IsNullOrWhiteSpace(query.Search))
        {
            var s = query.Search.Trim().ToLowerInvariant();
            q = q.Where(u =>
                u.Name.ToLower().Contains(s)
                || u.Email.ToLower().Contains(s)
                || (u.Phone != null && u.Phone.ToLower().Contains(s)));
        }

        var total = await q.CountAsync(ct);
        var users = await q.OrderByDescending(u => u.Id)
            .Skip((query.Page - 1) * query.PageSize)
            .Take(query.PageSize)
            .ToListAsync(ct);

        var emails = users.Select(u => Normalize(u.Email)).Distinct().ToList();
        var websiteCounts = await _db.DigiCards.AsNoTracking()
            .Where(c => c.UserEmail != null && emails.Contains(c.UserEmail.ToLower()))
            .GroupBy(c => c.UserEmail!.ToLower())
            .Select(g => new { Email = g.Key, Count = g.Count() })
            .ToListAsync(ct);
        var countMap = websiteCounts.ToDictionary(x => x.Email, x => x.Count, StringComparer.OrdinalIgnoreCase);

        var latestCards = await _db.DigiCards.AsNoTracking()
            .Where(c => c.UserEmail != null && emails.Contains(c.UserEmail.ToLower()))
            .OrderByDescending(c => c.Id)
            .ToListAsync(ct);
        var cardByEmail = new Dictionary<string, DigiCard>(StringComparer.OrdinalIgnoreCase);
        foreach (var c in latestCards)
        {
            var key = Normalize(c.UserEmail);
            if (!cardByEmail.ContainsKey(key))
                cardByEmail[key] = c;
        }

        var referrerEmails = users
            .Select(u => Normalize(u.ReferredBy))
            .Where(e => e.Length > 0)
            .Distinct()
            .ToList();
        var referrers = referrerEmails.Count == 0
            ? new Dictionary<string, User>(StringComparer.OrdinalIgnoreCase)
            : (await _db.Users.AsNoTracking()
                .Where(u => referrerEmails.Contains(u.Email.ToLower()))
                .ToListAsync(ct))
            .GroupBy(u => Normalize(u.Email))
            .ToDictionary(g => g.Key, g => g.First(), StringComparer.OrdinalIgnoreCase);

        var frdPairs = await _db.ReferralEarnings.AsNoTracking()
            .Where(r => r.IsCollaboration == "YES" && emails.Contains(r.ReferredEmail.ToLower()))
            .Select(r => new { Referrer = r.ReferrerEmail.ToLower(), Referred = r.ReferredEmail.ToLower() })
            .ToListAsync(ct);
        var frdSet = frdPairs.Select(p => p.Referrer + "|" + p.Referred)
            .ToHashSet(StringComparer.OrdinalIgnoreCase);

        var mwDeals = await GetMappedDealsAsync(emails, "MiniWebsite", ct);
        var frDeals = await GetMappedDealsAsync(emails, "Franchise", ct);
        var invoices = await GetFranchiseInvoicesAsync(emails, ct);
        var pendingMap = await GetPendingAmountsAsync(emails, ct);
        var mwDealOptions = await ListActiveDealsAsync("MiniWebsite", ct);
        var frDealOptions = await ListActiveDealsAsync("Franchise", ct);

        var rows = users.Select(u =>
        {
            var email = Normalize(u.Email);
            countMap.TryGetValue(email, out var wc);
            cardByEmail.TryGetValue(email, out var card);
            mwDeals.TryGetValue(email, out var mwDeal);
            frDeals.TryGetValue(email, out var frDeal);
            invoices.TryGetValue(email, out var inv);
            inv ??= new InvoiceInfo(null, null, null, null);
            pendingMap.TryGetValue(email, out var pending);

            var feeDisplay = inv.FranchiseAmount is null
                ? "₹0.00"
                : "₹" + inv.FranchiseAmount.Value.ToString("0.00", CultureInfo.InvariantCulture);

            return new FranchiseDistributorRowDto(
                u.Id,
                u.Email,
                u.Name,
                u.Phone,
                u.CreatedAt,
                BuildReferralSource(u, referrers, frdSet),
                string.IsNullOrWhiteSpace(card?.DCompName) ? "-" : card!.DCompName!,
                NormalizeYesNo(u.CollaborationEnabled),
                NormalizeYesNo(u.Influencer),
                NormalizeYesNo(u.CollaborationEnabled) == "YES" ? "Active" : "Inactive",
                string.IsNullOrWhiteSpace(card?.DPaymentStatus) ? "-" : card!.DPaymentStatus!,
                feeDisplay,
                inv.FranchiseInvoiceId,
                inv.JoiningDealInvoiceId,
                wc,
                pending,
                mwDeal,
                frDeal);
        }).ToList();

        return ApiResult<FranchiseDistributorPageDto>.Ok(new FranchiseDistributorPageDto(
            rows, mwDealOptions, frDealOptions, total, query.Page, query.PageSize));
    }

    public async Task<ApiResult> SetInfluencerAsync(SetInfluencerRequest request, CancellationToken ct = default)
    {
        var email = Normalize(request.Email);
        var status = NormalizeYesNo(request.Status);
        if (status is not ("YES" or "NO"))
            return ApiResult.Fail("Status must be YES or NO.");

        var user = await _db.Users.FirstOrDefaultAsync(
            u => u.Email.ToLower() == email && u.Role == UserRole.Customer, ct);
        if (user == null)
            return ApiResult.Fail("User not found.");

        user.Influencer = status;
        user.UpdatedAt = DateTime.UtcNow;
        await _db.SaveChangesAsync(ct);
        return ApiResult.Ok("Influencer status updated successfully");
    }

    private async Task<Dictionary<string, MappedDealLiteDto>> GetMappedDealsAsync(
        IReadOnlyList<string> emails, string planType, CancellationToken ct)
    {
        var result = new Dictionary<string, MappedDealLiteDto>(StringComparer.OrdinalIgnoreCase);
        if (emails.Count == 0) return result;

        var ef = RequireEf();
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

        var set = emails.ToHashSet(StringComparer.OrdinalIgnoreCase);
        foreach (var row in rows)
        {
            if (row.CustomerEmail == null) continue;
            var key = Normalize(row.CustomerEmail);
            if (!set.Contains(key) || result.ContainsKey(key)) continue;
            result[key] = new MappedDealLiteDto(row.MappingId, row.DealId, row.DealName ?? "", row.CouponCode);
        }
        return result;
    }

    private async Task<List<DealOptionLiteDto>> ListActiveDealsAsync(string planType, CancellationToken ct)
    {
        var ef = RequireEf();
        var rows = await ef.Database
            .SqlQueryRaw<DealOptionSqlRow>(
                @"SELECT id AS Id, deal_name AS DealName, coupon_code AS CouponCode, plan_type AS PlanType
                  FROM deals WHERE deal_status = 'Active' AND plan_type = {0} ORDER BY deal_name",
                planType)
            .ToListAsync(ct);
        return rows.Select(r => new DealOptionLiteDto(r.Id, r.DealName ?? "", r.CouponCode, r.PlanType ?? planType)).ToList();
    }

    private async Task<Dictionary<string, InvoiceInfo>> GetFranchiseInvoicesAsync(
        IReadOnlyList<string> emails, CancellationToken ct)
    {
        var map = new Dictionary<string, InvoiceInfo>(StringComparer.OrdinalIgnoreCase);
        if (emails.Count == 0) return map;

        var ef = RequireEf();
        try
        {
            var rows = await ef.Database
                .SqlQueryRaw<InvoiceSqlRow>(
                    @"SELECT id AS Id, user_email AS UserEmail, service_name AS ServiceName,
                             total_amount AS TotalAmount, payment_status AS PaymentStatus, invoice_date AS InvoiceDate
                      FROM invoice_details
                      WHERE service_name IN ('Franchisee Registration', 'Joining Deal Payment')
                      ORDER BY id DESC")
                .ToListAsync(ct);

            var set = emails.ToHashSet(StringComparer.OrdinalIgnoreCase);
            foreach (var row in rows)
            {
                if (row.UserEmail == null) continue;
                var key = Normalize(row.UserEmail);
                if (!set.Contains(key)) continue;

                map.TryGetValue(key, out var existing);
                existing ??= new InvoiceInfo(null, null, null, null);

                if (row.ServiceName == "Franchisee Registration" && existing.FranchiseInvoiceId == null)
                {
                    decimal? amt = null;
                    if (decimal.TryParse(row.TotalAmount, NumberStyles.Any, CultureInfo.InvariantCulture, out var parsed))
                        amt = parsed;
                    existing = existing with
                    {
                        FranchiseInvoiceId = row.Id,
                        FranchiseAmount = amt,
                        FranchisePaid = string.Equals(row.PaymentStatus, "Success", StringComparison.OrdinalIgnoreCase)
                    };
                }
                else if (row.ServiceName == "Joining Deal Payment" && existing.JoiningDealInvoiceId == null)
                {
                    existing = existing with { JoiningDealInvoiceId = row.Id };
                }

                map[key] = existing;
            }
        }
        catch
        {
            // ignore if table unavailable
        }

        return map;
    }

    private async Task<Dictionary<string, decimal>> GetPendingAmountsAsync(
        IReadOnlyList<string> emails, CancellationToken ct)
    {
        var map = new Dictionary<string, decimal>(StringComparer.OrdinalIgnoreCase);
        if (emails.Count == 0) return map;

        var totals = await _db.ReferralEarnings.AsNoTracking()
            .Where(r => emails.Contains(r.ReferrerEmail.ToLower()))
            .GroupBy(r => r.ReferrerEmail.ToLower())
            .Select(g => new { Email = g.Key, Total = g.Sum(x => x.Amount) })
            .ToListAsync(ct);

        var ef = RequireEf();
        foreach (var t in totals)
        {
            decimal paid = 0;
            try
            {
                paid = await ef.Database
                    .SqlQueryRaw<decimal>(
                        @"SELECT COALESCE(SUM(rph.amount), 0) AS Value
                          FROM referral_payment_history rph
                          INNER JOIN referral_earnings re ON rph.referral_id = re.id
                          WHERE re.referrer_email = {0}",
                        t.Email)
                    .FirstOrDefaultAsync(ct);
            }
            catch { /* ignore */ }

            map[t.Email] = Math.Max(0, t.Total - paid);
        }

        return map;
    }

    private static string BuildReferralSource(
        User user, Dictionary<string, User> referrers, HashSet<string> frdSet)
    {
        if (string.IsNullOrWhiteSpace(user.ReferredBy))
            return "Direct";

        var refEmail = Normalize(user.ReferredBy);
        if (!referrers.TryGetValue(refEmail, out var referrer))
            return "Ref: " + user.ReferredBy.Trim();

        var display = referrer.Role switch
        {
            UserRole.Franchisee => "FR - " + referrer.Id.ToString("D3", CultureInfo.InvariantCulture),
            UserRole.Team => "Team - " + referrer.Id,
            UserRole.Admin => "Admin - " + referrer.Id,
            _ => "User - " + referrer.Id
        };

        if (frdSet.Contains(refEmail + "|" + Normalize(user.Email)))
            display += " (FRD)";

        return display;
    }

    private DbContext RequireEf() =>
        _db as DbContext
        ?? throw new InvalidOperationException("IApplicationDbContext must be an EF DbContext.");

    private static string Normalize(string? value) => (value ?? string.Empty).Trim().ToLowerInvariant();

    private static string NormalizeYesNo(string? value)
    {
        var v = (value ?? "NO").Trim().ToUpperInvariant();
        return v == "YES" ? "YES" : "NO";
    }

    private sealed record InvoiceInfo(
        int? FranchiseInvoiceId,
        int? JoiningDealInvoiceId,
        decimal? FranchiseAmount,
        bool? FranchisePaid);

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

    private sealed class InvoiceSqlRow
    {
        public int Id { get; set; }
        public string? UserEmail { get; set; }
        public string? ServiceName { get; set; }
        public string? TotalAmount { get; set; }
        public string? PaymentStatus { get; set; }
        public DateTime? InvoiceDate { get; set; }
    }
}
