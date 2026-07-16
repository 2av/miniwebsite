using System.Globalization;
using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Admin.ManageReferrals.Dtos;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;
using MiniWebsite.Domain.Enums;

namespace MiniWebsite.Application.Admin.ManageReferrals;

public class AdminManageReferralsService : IAdminManageReferralsService
{
    private readonly IApplicationDbContext _db;

    public AdminManageReferralsService(IApplicationDbContext db)
    {
        _db = db;
    }

    public async Task<ApiResult<ManageReferralsPageDto>> ListAsync(ManageReferralsQuery query, CancellationToken ct = default)
    {
        query.Page = query.Page < 1 ? 1 : query.Page;
        query.PageSize = query.PageSize is < 1 or > 100 ? 10 : query.PageSize;

        var q = _db.ReferralEarnings.AsNoTracking().AsQueryable();
        if (!string.IsNullOrWhiteSpace(query.Search))
        {
            var s = query.Search.Trim().ToLower();
            q = q.Where(r =>
                r.ReferrerEmail.ToLower().Contains(s)
                || r.ReferredEmail.ToLower().Contains(s));
        }

        var totalCount = await q.CountAsync(ct);
        var earnings = await q
            .OrderByDescending(r => r.Id)
            .Skip((query.Page - 1) * query.PageSize)
            .Take(query.PageSize)
            .ToListAsync(ct);

        if (earnings.Count == 0)
        {
            return ApiResult<ManageReferralsPageDto>.Ok(new ManageReferralsPageDto(
                [], totalCount, query.Page, query.PageSize));
        }

        var referrerEmails = earnings.Select(e => Normalize(e.ReferrerEmail)).Distinct().ToList();
        var referredEmails = earnings.Select(e => Normalize(e.ReferredEmail)).Distinct().ToList();
        var allEmails = referrerEmails.Concat(referredEmails).Distinct().ToList();

        var users = await _db.Users.AsNoTracking()
            .Where(u => allEmails.Contains(u.Email.ToLower()))
            .ToListAsync(ct);

        var customers = users
            .Where(u => u.Role == UserRole.Customer)
            .GroupBy(u => Normalize(u.Email))
            .ToDictionary(g => g.Key, g => g.First(), StringComparer.OrdinalIgnoreCase);

        var franchisees = users
            .Where(u => u.Role == UserRole.Franchisee)
            .GroupBy(u => Normalize(u.Email))
            .ToDictionary(g => g.Key, g => g.First(), StringComparer.OrdinalIgnoreCase);

        var cards = await _db.DigiCards.AsNoTracking()
            .Where(c => c.UserEmail != null && referredEmails.Contains(c.UserEmail.ToLower()))
            .OrderByDescending(c => c.Id)
            .ToListAsync(ct);

        var latestCardByEmail = new Dictionary<string, Domain.Entities.DigiCard>(StringComparer.OrdinalIgnoreCase);
        foreach (var c in cards)
        {
            var key = Normalize(c.UserEmail);
            if (!latestCardByEmail.ContainsKey(key))
                latestCardByEmail[key] = c;
        }

        var cardIds = latestCardByEmail.Values.Select(c => c.Id).Distinct().ToList();
        var invoiceCardIds = await GetCardsWithInvoiceAsync(cardIds, ct);

        var rows = earnings.Select(e =>
        {
            var refEmail = Normalize(e.ReferrerEmail);
            var referred = Normalize(e.ReferredEmail);
            customers.TryGetValue(refEmail, out var referrer);
            latestCardByEmail.TryGetValue(referred, out var card);
            franchisees.TryGetValue(referred, out var fran);

            var isCollab = string.Equals(e.IsCollaboration, "YES", StringComparison.OrdinalIgnoreCase);
            string referredTo;
            if (isCollab)
                referredTo = "FR - " + (fran?.Id.ToString(CultureInfo.InvariantCulture) ?? e.ReferredEmail);
            else
                referredTo = "MW - " + (card != null
                    ? card.Id.ToString(CultureInfo.InvariantCulture)
                    : e.ReferredEmail);

            var (label, tone) = ResolveMwPaymentStatus(e.Status, e.PaymentDate, card?.DPaymentStatus);

            return new ManageReferralRowDto(
                e.Id,
                e.ReferrerEmail,
                referrer != null ? referrer.Id.ToString("D5", CultureInfo.InvariantCulture) : "-",
                referrer?.Name ?? "-",
                referrer?.Phone ?? "-",
                referredTo,
                "₹" + e.Amount.ToString("N0", CultureInfo.InvariantCulture),
                e.Amount,
                string.IsNullOrWhiteSpace(referrer?.RefundStatus) ? "None" : referrer.RefundStatus!,
                label,
                tone,
                card?.Id,
                card != null && invoiceCardIds.Contains(card.Id));
        }).ToList();

        return ApiResult<ManageReferralsPageDto>.Ok(new ManageReferralsPageDto(
            rows, totalCount, query.Page, query.PageSize));
    }

    public async Task<ApiResult<ReferrerPaymentDetailsDto>> GetReferrerDetailsAsync(
        string referrerEmail,
        CancellationToken ct = default)
    {
        var email = Normalize(referrerEmail);
        if (string.IsNullOrWhiteSpace(email))
            return ApiResult<ReferrerPaymentDetailsDto>.Fail("Missing referrer email");

        var referrer = await _db.Users.AsNoTracking()
            .FirstOrDefaultAsync(u => u.Email.ToLower() == email && u.Role == UserRole.Customer, ct);

        var earnings = await _db.ReferralEarnings.AsNoTracking()
            .Where(r => r.ReferrerEmail.ToLower() == email)
            .OrderByDescending(r => r.ReferralDate)
            .ToListAsync(ct);

        var paidMap = await GetPaidAmountsByReferralIdsAsync(earnings.Select(e => e.Id).ToList(), ct);
        var referredEmails = earnings.Select(e => Normalize(e.ReferredEmail)).Distinct().ToList();

        var nameMap = await _db.Users.AsNoTracking()
            .Where(u => referredEmails.Contains(u.Email.ToLower()))
            .GroupBy(u => u.Email.ToLower())
            .Select(g => new { Email = g.Key, Name = g.First().Name })
            .ToDictionaryAsync(x => x.Email, x => x.Name, ct);

        var cards = await _db.DigiCards.AsNoTracking()
            .Where(c => c.UserEmail != null && referredEmails.Contains(c.UserEmail.ToLower()))
            .OrderByDescending(c => c.Id)
            .ToListAsync(ct);
        var cardMap = new Dictionary<string, Domain.Entities.DigiCard>(StringComparer.OrdinalIgnoreCase);
        foreach (var c in cards)
        {
            var key = Normalize(c.UserEmail);
            if (!cardMap.ContainsKey(key))
                cardMap[key] = c;
        }

        var lines = earnings.Select(e =>
        {
            var re = Normalize(e.ReferredEmail);
            paidMap.TryGetValue(e.Id, out var paid);
            var pending = Math.Max(0, e.Amount - paid);
            cardMap.TryGetValue(re, out var card);
            nameMap.TryGetValue(re, out var name);

            string userPayLabel;
            string userPayTone;
            if (string.Equals(card?.DPaymentStatus, "Success", StringComparison.OrdinalIgnoreCase))
            {
                userPayTone = "ok";
                userPayLabel = card?.DPaymentDate is { } d
                    ? "Paid on " + d.ToString("dd-MM-yyyy", CultureInfo.InvariantCulture)
                    : "Paid";
            }
            else
            {
                userPayTone = "warn";
                userPayLabel = "Not Paid";
            }

            string statusLabel;
            string statusTone;
            if (pending <= 0)
            {
                statusLabel = "Fully Paid";
                statusTone = "ok";
            }
            else if (paid > 0)
            {
                statusLabel = "Partial";
                statusTone = "warn";
            }
            else
            {
                statusLabel = "Pending";
                statusTone = "warn";
            }

            return new ReferrerPaymentLineDto(
                e.Id,
                e.ReferredEmail,
                name ?? "Unknown",
                e.ReferralDate,
                userPayLabel,
                userPayTone,
                e.Amount,
                paid,
                pending,
                statusLabel,
                statusTone,
                pending > 0,
                paid > 0);
        }).ToList();

        return ApiResult<ReferrerPaymentDetailsDto>.Ok(new ReferrerPaymentDetailsDto(
            email,
            referrer?.Name ?? email,
            lines));
    }

    public async Task<ApiResult<ReferralPaymentHistoryDto>> GetPaymentHistoryAsync(
        int referralId,
        CancellationToken ct = default)
    {
        var earning = await _db.ReferralEarnings.AsNoTracking()
            .FirstOrDefaultAsync(r => r.Id == referralId, ct);
        if (earning == null)
            return ApiResult<ReferralPaymentHistoryDto>.Fail("Referral not found");

        var referrer = await _db.Users.AsNoTracking()
            .FirstOrDefaultAsync(u =>
                u.Email.ToLower() == Normalize(earning.ReferrerEmail) && u.Role == UserRole.Customer, ct);
        var referred = await _db.Users.AsNoTracking()
            .FirstOrDefaultAsync(u =>
                u.Email.ToLower() == Normalize(earning.ReferredEmail), ct);

        var items = await LoadPaymentHistoryItemsAsync(referralId, ct);

        return ApiResult<ReferralPaymentHistoryDto>.Ok(new ReferralPaymentHistoryDto(
            referralId,
            referrer?.Name ?? earning.ReferrerEmail,
            referred?.Name ?? earning.ReferredEmail,
            earning.Amount,
            items));
    }

    public async Task<ApiResult> ProcessPaymentAsync(
        ProcessReferralPaymentRequest request,
        CancellationToken ct = default)
    {
        if (request.ReferralId <= 0)
            return ApiResult.Fail("Invalid referral id");
        if (request.Amount <= 0)
            return ApiResult.Fail("Amount must be greater than zero");
        if (string.IsNullOrWhiteSpace(request.TransactionNumber))
            return ApiResult.Fail("Transaction number is required");
        if (string.IsNullOrWhiteSpace(request.PaymentMethod))
            return ApiResult.Fail("Payment method is required");

        var earning = await _db.ReferralEarnings
            .FirstOrDefaultAsync(r => r.Id == request.ReferralId, ct);
        if (earning == null)
            return ApiResult.Fail("Referral not found");

        var ef = RequireEf();
        var processedBy = string.IsNullOrWhiteSpace(request.ProcessedBy) ? "admin" : request.ProcessedBy.Trim();

        await ef.Database.ExecuteSqlRawAsync(
            @"INSERT INTO referral_payment_history
                (referral_id, amount, transaction_number, payment_method, payment_notes, payment_date, processed_by)
              VALUES ({0}, {1}, {2}, {3}, {4}, NOW(), {5})",
            [
                request.ReferralId,
                request.Amount,
                request.TransactionNumber.Trim(),
                request.PaymentMethod.Trim(),
                request.PaymentNotes ?? "",
                processedBy
            ],
            ct);

        var paidMap = await GetPaidAmountsByReferralIdsAsync([request.ReferralId], ct);
        paidMap.TryGetValue(request.ReferralId, out var totalPaid);

        if (totalPaid >= earning.Amount)
        {
            earning.Status = "Paid";
            earning.PaymentDate = DateTime.Now;
        }
        else
        {
            earning.Status = "Partial";
            earning.PaymentDate = null;
        }

        await _db.SaveChangesAsync(ct);
        return ApiResult.Ok($"Payment of ₹{request.Amount:N0} processed successfully");
    }

    public async Task<ApiResult<ManageReferralBankDetailsDto>> GetBankDetailsAsync(
        string userEmail,
        CancellationToken ct = default)
    {
        var email = Normalize(userEmail);
        if (string.IsNullOrWhiteSpace(email))
            return ApiResult<ManageReferralBankDetailsDto>.Fail("Missing user email");

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

            return ApiResult<ManageReferralBankDetailsDto>.Ok(new ManageReferralBankDetailsDto(
                email,
                row?.AccountHolderName,
                row?.AccountNumber,
                row?.IfscCode,
                row?.BankName,
                row?.UpiId,
                row?.UpiName));
        }
        catch (Exception ex)
        {
            return ApiResult<ManageReferralBankDetailsDto>.Fail("Unable to load bank details: " + ex.Message);
        }
    }

    private async Task<HashSet<int>> GetCardsWithInvoiceAsync(List<int> cardIds, CancellationToken ct)
    {
        if (cardIds.Count == 0) return [];
        var ef = RequireEf();
        try
        {
            var ids = string.Join(",", cardIds.Select(i => i.ToString(CultureInfo.InvariantCulture)));
            var rows = await ef.Database
                .SqlQueryRaw<IdRow>(
                    $"SELECT DISTINCT card_id AS Value FROM invoice_details WHERE card_id IN ({ids})")
                .ToListAsync(ct);
            return rows.Select(r => r.Value).ToHashSet();
        }
        catch
        {
            return [];
        }
    }

    private async Task<Dictionary<int, decimal>> GetPaidAmountsByReferralIdsAsync(
        List<int> referralIds,
        CancellationToken ct)
    {
        var map = referralIds.ToDictionary(id => id, _ => 0m);
        if (referralIds.Count == 0) return map;

        var ef = RequireEf();
        try
        {
            var ids = string.Join(",", referralIds.Select(i => i.ToString(CultureInfo.InvariantCulture)));
            var rows = await ef.Database
                .SqlQueryRaw<PaidRow>(
                    $"SELECT referral_id AS ReferralId, COALESCE(SUM(amount), 0) AS Paid FROM referral_payment_history WHERE referral_id IN ({ids}) GROUP BY referral_id")
                .ToListAsync(ct);
            foreach (var r in rows)
                map[r.ReferralId] = r.Paid;
        }
        catch
        {
            // table may be missing in some envs
        }

        return map;
    }

    private async Task<IReadOnlyList<ReferralPaymentHistoryItemDto>> LoadPaymentHistoryItemsAsync(
        int referralId,
        CancellationToken ct)
    {
        var ef = RequireEf();
        try
        {
            var rows = await ef.Database
                .SqlQueryRaw<HistorySqlRow>(
                    @"SELECT payment_date AS PaymentDate,
                             amount AS Amount,
                             transaction_number AS TransactionNumber,
                             payment_method AS PaymentMethod,
                             payment_notes AS PaymentNotes,
                             processed_by AS ProcessedBy
                      FROM referral_payment_history
                      WHERE referral_id = {0}
                      ORDER BY payment_date DESC",
                    referralId)
                .ToListAsync(ct);

            return rows.Select(r => new ReferralPaymentHistoryItemDto(
                r.PaymentDate,
                r.Amount,
                r.TransactionNumber ?? "",
                r.PaymentMethod ?? "",
                r.PaymentNotes,
                r.ProcessedBy ?? "")).ToList();
        }
        catch
        {
            return [];
        }
    }

    private static (string Label, string Tone) ResolveMwPaymentStatus(
        string? referralStatus,
        DateTime? paymentDate,
        string? cardPaymentStatus)
    {
        if (string.Equals(referralStatus, "Paid", StringComparison.OrdinalIgnoreCase))
        {
            var d = paymentDate?.ToString("dd-MM-yyyy", CultureInfo.InvariantCulture) ?? "N/A";
            return ("Paid on " + d, "ok");
        }

        var cardOk = string.Equals(cardPaymentStatus, "Success", StringComparison.OrdinalIgnoreCase);
        if (string.Equals(referralStatus, "Partial", StringComparison.OrdinalIgnoreCase) && cardOk)
            return ("Partial Payment", "warn");
        if (string.Equals(referralStatus, "Pending", StringComparison.OrdinalIgnoreCase) && cardOk)
            return ("Pending", "warn");
        return ("Not Eligible", "danger");
    }

    private static string Normalize(string? value) => (value ?? "").Trim().ToLowerInvariant();

    private DbContext RequireEf() =>
        _db as DbContext
        ?? throw new InvalidOperationException("IApplicationDbContext must be an EF DbContext.");

    private sealed class BankSqlRow
    {
        public string? AccountHolderName { get; set; }
        public string? AccountNumber { get; set; }
        public string? IfscCode { get; set; }
        public string? BankName { get; set; }
        public string? UpiId { get; set; }
        public string? UpiName { get; set; }
    }

    private sealed class IdRow
    {
        public int Value { get; set; }
    }

    private sealed class PaidRow
    {
        public int ReferralId { get; set; }
        public decimal Paid { get; set; }
    }

    private sealed class HistorySqlRow
    {
        public DateTime PaymentDate { get; set; }
        public decimal Amount { get; set; }
        public string? TransactionNumber { get; set; }
        public string? PaymentMethod { get; set; }
        public string? PaymentNotes { get; set; }
        public string? ProcessedBy { get; set; }
    }
}
