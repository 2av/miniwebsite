using System.Globalization;
using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Admin.AllOrders.Dtos;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;
using MiniWebsite.Domain.Enums;

namespace MiniWebsite.Application.Admin.AllOrders;

public class AdminAllOrdersService : IAdminAllOrdersService
{
    private readonly IApplicationDbContext _db;

    public AdminAllOrdersService(IApplicationDbContext db)
    {
        _db = db;
    }

    public async Task<ApiResult<AllOrdersPageDto>> ListAsync(AllOrdersQuery query, CancellationToken ct = default)
    {
        query.Page = query.Page < 1 ? 1 : query.Page;
        query.PageSize = query.PageSize is < 1 or > 100 ? 10 : query.PageSize;
        var offset = (query.Page - 1) * query.PageSize;
        var search = string.IsNullOrWhiteSpace(query.Search) ? null : query.Search.Trim();

        var ef = RequireEf();
        int totalCount;
        List<InvoiceSqlRow> invoices;

        try
        {
            if (search == null)
            {
                totalCount = (await ef.Database
                    .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM invoice_details")
                    .FirstAsync(ct)).Value;

                invoices = await ef.Database
                    .SqlQueryRaw<InvoiceSqlRow>(
                        @"SELECT id AS Id,
                                 user_email AS UserEmail,
                                 user_name AS UserName,
                                 invoice_number AS InvoiceNumber,
                                 CAST(card_id AS CHAR) AS CardId,
                                 service_name AS ServiceName,
                                 payment_type AS PaymentType,
                                 payment_status AS PaymentStatus,
                                 invoice_date AS InvoiceDate,
                                 CAST(total_amount AS CHAR) AS TotalAmount
                          FROM invoice_details
                          ORDER BY id DESC
                          LIMIT {0} OFFSET {1}",
                        query.PageSize,
                        offset)
                    .ToListAsync(ct);
            }
            else
            {
                var term = "%" + search + "%";
                totalCount = (await ef.Database
                    .SqlQueryRaw<CountRow>(
                        @"SELECT COUNT(*) AS Value FROM invoice_details id
                          WHERE id.user_email LIKE {0}
                             OR id.user_name LIKE {0}
                             OR id.invoice_number LIKE {0}",
                        term)
                    .FirstAsync(ct)).Value;

                invoices = await ef.Database
                    .SqlQueryRaw<InvoiceSqlRow>(
                        @"SELECT id AS Id,
                                 user_email AS UserEmail,
                                 user_name AS UserName,
                                 invoice_number AS InvoiceNumber,
                                 CAST(card_id AS CHAR) AS CardId,
                                 service_name AS ServiceName,
                                 payment_type AS PaymentType,
                                 payment_status AS PaymentStatus,
                                 invoice_date AS InvoiceDate,
                                 CAST(total_amount AS CHAR) AS TotalAmount
                          FROM invoice_details id
                          WHERE id.user_email LIKE {0}
                             OR id.user_name LIKE {0}
                             OR id.invoice_number LIKE {0}
                          ORDER BY id DESC
                          LIMIT {1} OFFSET {2}",
                        term,
                        query.PageSize,
                        offset)
                    .ToListAsync(ct);
            }
        }
        catch (Exception ex)
        {
            return ApiResult<AllOrdersPageDto>.Fail("Unable to load orders: " + ex.Message);
        }

        var emails = invoices
            .Select(i => Normalize(i.UserEmail))
            .Where(e => e.Length > 0)
            .Distinct()
            .ToList();

        var users = emails.Count == 0
            ? []
            : await _db.Users.AsNoTracking()
                .Where(u => emails.Contains(u.Email.ToLower()))
                .Select(u => new { u.Id, Email = u.Email.ToLower(), u.Role })
                .ToListAsync(ct);

        var customerByEmail = users
            .Where(u => u.Role == UserRole.Customer)
            .GroupBy(u => u.Email)
            .ToDictionary(g => g.Key, g => g.First().Id, StringComparer.OrdinalIgnoreCase);

        var franchiseeByEmail = users
            .Where(u => u.Role == UserRole.Franchisee)
            .GroupBy(u => u.Email)
            .ToDictionary(g => g.Key, g => g.First().Id, StringComparer.OrdinalIgnoreCase);

        var rows = invoices.Select(inv =>
        {
            var email = Normalize(inv.UserEmail);
            var isFranchisee = IsFranchiseeInvoice(inv);
            string userIdDisplay = "-";

            if (isFranchisee && franchiseeByEmail.TryGetValue(email, out var frId))
                userIdDisplay = "FR - " + frId.ToString(CultureInfo.InvariantCulture);
            else if (customerByEmail.TryGetValue(email, out var customerId))
                userIdDisplay = customerId.ToString("D5", CultureInfo.InvariantCulture);

            var mwId = ParseCardId(inv.CardId);
            var amount = ParseAmount(inv.TotalAmount);
            var (label, tone, paidOn) = ResolvePaymentStatus(inv.PaymentStatus, inv.InvoiceDate);

            return new AllOrderRowDto(
                inv.Id,
                userIdDisplay,
                mwId?.ToString(CultureInfo.InvariantCulture),
                label,
                tone,
                paidOn,
                amount,
                "₹" + amount.ToString("N2", CultureInfo.CreateSpecificCulture("en-IN")));
        }).ToList();

        return ApiResult<AllOrdersPageDto>.Ok(new AllOrdersPageDto(
            rows,
            totalCount,
            query.Page,
            query.PageSize));
    }

    private static bool IsFranchiseeInvoice(InvoiceSqlRow inv)
    {
        if (string.Equals(inv.PaymentType, "Franchisee", StringComparison.OrdinalIgnoreCase))
            return true;
        return !string.IsNullOrWhiteSpace(inv.ServiceName)
               && inv.ServiceName.Contains("Franchisee", StringComparison.OrdinalIgnoreCase);
    }

    private static int? ParseCardId(string? cardId)
    {
        if (string.IsNullOrWhiteSpace(cardId)) return null;
        return int.TryParse(cardId.Trim(), NumberStyles.Integer, CultureInfo.InvariantCulture, out var id) && id > 0
            ? id
            : null;
    }

    private static decimal ParseAmount(string? totalAmount)
    {
        if (string.IsNullOrWhiteSpace(totalAmount)) return 0;
        return decimal.TryParse(totalAmount.Trim(), NumberStyles.Any, CultureInfo.InvariantCulture, out var amt)
            ? amt
            : 0;
    }

    private static (string Label, string Tone, string? PaidOn) ResolvePaymentStatus(
        string? paymentStatus, DateTime? invoiceDate)
    {
        var paidDateNorm = FormatInvoiceDate(invoiceDate);
        var ps = (paymentStatus ?? string.Empty).Trim();
        if (ps.Length == 0)
            return ("-", "neutral", null);

        if (ps.StartsWith("Paid on", StringComparison.OrdinalIgnoreCase))
        {
            var paidDate = ps["Paid on".Length..].Trim();
            if (paidDate.Length == 0) paidDate = paidDateNorm ?? "";
            return ("Paid", "ok", string.IsNullOrWhiteSpace(paidDate) ? null : paidDate);
        }

        if (ps.Equals("Success", StringComparison.OrdinalIgnoreCase)
            || ps.Equals("Paid", StringComparison.OrdinalIgnoreCase))
        {
            return ("Paid", "ok", paidDateNorm);
        }

        if (ps.Equals("Failed", StringComparison.OrdinalIgnoreCase)
            || ps.Equals("Failure", StringComparison.OrdinalIgnoreCase))
            return ("Failed", "danger", null);

        if (ps.Equals("Pending", StringComparison.OrdinalIgnoreCase)
            || ps.Equals("Initiated", StringComparison.OrdinalIgnoreCase))
            return ("Pending", "warn", null);

        if (ps.Equals("Refunded", StringComparison.OrdinalIgnoreCase))
            return ("Refunded", "neutral", null);

        return (ps, "neutral", null);
    }

    private static string? FormatInvoiceDate(DateTime? invoiceDate)
    {
        if (!invoiceDate.HasValue) return null;
        if (invoiceDate.Value.Year <= 1) return null;
        return invoiceDate.Value.ToString("dd-MM-yyyy", CultureInfo.InvariantCulture);
    }

    private DbContext RequireEf() =>
        _db as DbContext
        ?? throw new InvalidOperationException("IApplicationDbContext must be an EF DbContext.");

    private static string Normalize(string? value) => (value ?? string.Empty).Trim().ToLowerInvariant();

    private sealed class CountRow
    {
        public int Value { get; set; }
    }

    private sealed class InvoiceSqlRow
    {
        public int Id { get; set; }
        public string? UserEmail { get; set; }
        public string? UserName { get; set; }
        public string? InvoiceNumber { get; set; }
        public string? CardId { get; set; }
        public string? ServiceName { get; set; }
        public string? PaymentType { get; set; }
        public string? PaymentStatus { get; set; }
        public DateTime? InvoiceDate { get; set; }
        public string? TotalAmount { get; set; }
    }
}
