using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Infrastructure.Persistence;

namespace MiniWebsite.Infrastructure.Persistence;

public class WalletBalanceLookup : IWalletBalanceLookup
{
    private readonly ApplicationDbContext _db;

    public WalletBalanceLookup(ApplicationDbContext db)
    {
        _db = db;
    }

    public async Task<decimal> GetLatestBalanceAsync(string franchiseeEmail, CancellationToken ct = default)
    {
        try
        {
            return await _db.Database
                .SqlQueryRaw<decimal>(
                    @"SELECT w_balance AS Value
                      FROM wallet
                      WHERE f_user_email = {0}
                      ORDER BY ID DESC
                      LIMIT 1",
                    franchiseeEmail)
                .FirstOrDefaultAsync(ct);
        }
        catch
        {
            return 0m;
        }
    }
}
