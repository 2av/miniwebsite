using System.Globalization;
using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Admin.WalletRecharge.Dtos;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;
using MiniWebsite.Domain.Enums;

namespace MiniWebsite.Application.Admin.WalletRecharge;

public class AdminWalletRechargeService : IAdminWalletRechargeService
{
    private const decimal MinAmount = 10m;
    private const decimal MaxAmount = 1000m;

    private readonly IApplicationDbContext _db;

    public AdminWalletRechargeService(IApplicationDbContext db)
    {
        _db = db;
    }

    public async Task<ApiResult<FranchiseeWalletLookupDto>> LookupAsync(
        string? email,
        int? userId,
        CancellationToken ct = default)
    {
        var user = await FindFranchiseeAsync(email, userId, ct);
        if (user == null)
            return ApiResult<FranchiseeWalletLookupDto>.Fail("Sorry! User Not available");

        var balance = await GetLatestBalanceAsync(user.Email, ct);
        return ApiResult<FranchiseeWalletLookupDto>.Ok(new FranchiseeWalletLookupDto(
            user.Id,
            user.Email,
            user.Name,
            user.Phone,
            balance,
            FormatMoney(balance)));
    }

    public async Task<ApiResult<WalletRechargeResultDto>> RechargeAsync(
        WalletRechargeRequest request,
        CancellationToken ct = default)
    {
        var email = Normalize(request.UserEmail);
        if (string.IsNullOrWhiteSpace(email))
            return ApiResult<WalletRechargeResultDto>.Fail("Franchisee email is required");

        if (request.Amount < MinAmount || request.Amount > MaxAmount)
            return ApiResult<WalletRechargeResultDto>.Fail($"Amount must be between ₹{MinAmount:0} and ₹{MaxAmount:0}");

        var user = await FindFranchiseeAsync(email, null, ct);
        if (user == null)
            return ApiResult<WalletRechargeResultDto>.Fail("Sorry! User Not available");

        var current = await GetLatestBalanceAsync(user.Email, ct);
        var amount = Math.Round(request.Amount, 2);
        var newBalance = current + amount;
        var txnMsg = string.IsNullOrWhiteSpace(request.TxnMsg) ? "Promotional Amount" : request.TxnMsg.Trim();

        var ef = RequireEf();
        try
        {
            await ef.Database.ExecuteSqlRawAsync(
                @"INSERT INTO wallet
                    (f_user_email, w_deposit, w_order_id, w_balance, uploaded_date, w_txn_msg)
                  VALUES
                    ({0}, {1}, 'Promotional', {2}, NOW(), {3})",
                [
                    user.Email,
                    FormatNum(amount),
                    FormatNum(newBalance),
                    txnMsg
                ],
                ct);

            return ApiResult<WalletRechargeResultDto>.Ok(
                new WalletRechargeResultDto(
                    user.Email,
                    user.Name,
                    amount,
                    newBalance,
                    FormatMoney(amount),
                    FormatMoney(newBalance),
                    $"Amount of {FormatMoney(amount)} credited in user's account."),
                "Wallet recharged successfully");
        }
        catch (Exception ex)
        {
            return ApiResult<WalletRechargeResultDto>.Fail(
                $"Failed! Amount of {FormatMoney(amount)} could not be added. " + ex.Message);
        }
    }

    private async Task<FranchiseeUser?> FindFranchiseeAsync(string? email, int? userId, CancellationToken ct)
    {
        var q = _db.Users.AsNoTracking().Where(u => u.Role == UserRole.Franchisee);

        if (userId is > 0)
            q = q.Where(u => u.Id == userId.Value);
        else if (!string.IsNullOrWhiteSpace(email))
        {
            var e = Normalize(email);
            q = q.Where(u => u.Email.ToLower() == e);
        }
        else
            return null;

        var row = await q.Select(u => new { u.Id, u.Email, u.Name, u.Phone }).FirstOrDefaultAsync(ct);
        return row == null ? null : new FranchiseeUser(row.Id, row.Email, row.Name, row.Phone);
    }

    private async Task<decimal> GetLatestBalanceAsync(string franchiseeEmail, CancellationToken ct)
    {
        var ef = RequireEf();
        try
        {
            var row = await ef.Database
                .SqlQueryRaw<BalanceRow>(
                    @"SELECT CAST(w_balance AS CHAR) AS Balance
                      FROM wallet
                      WHERE f_user_email = {0}
                      ORDER BY id DESC
                      LIMIT 1",
                    franchiseeEmail)
                .FirstOrDefaultAsync(ct);

            return ParseDecimal(row?.Balance);
        }
        catch
        {
            return 0m;
        }
    }

    private static string Normalize(string? value) => (value ?? "").Trim().ToLowerInvariant();

    private static string FormatNum(decimal v) => v.ToString(CultureInfo.InvariantCulture);

    private static string FormatMoney(decimal v) =>
        "₹" + v.ToString("N2", CultureInfo.InvariantCulture);

    private static decimal ParseDecimal(string? v) =>
        decimal.TryParse(v, NumberStyles.Any, CultureInfo.InvariantCulture, out var d) ? d : 0m;

    private DbContext RequireEf() =>
        _db as DbContext
        ?? throw new InvalidOperationException("IApplicationDbContext must be an EF DbContext.");

    private sealed record FranchiseeUser(int Id, string Email, string Name, string? Phone);

    private sealed class BalanceRow
    {
        public string? Balance { get; set; }
    }
}
