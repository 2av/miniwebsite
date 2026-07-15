using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Infrastructure.Persistence;

namespace MiniWebsite.Infrastructure.Persistence;

public class DealBonusLookup : IDealBonusLookup
{
    private readonly ApplicationDbContext _db;

    public DealBonusLookup(ApplicationDbContext db)
    {
        _db = db;
    }

    public async Task<decimal> GetBonusAmountAsync(string referrerEmail, string planType, string defaultCouponCode, CancellationToken ct = default)
    {
        const decimal fallback = 250m;

        try
        {
            var mapped = await _db.Database
                .SqlQueryRaw<decimal>(
                    @"SELECT d.bonus_amount AS Value
                      FROM deals d
                      INNER JOIN deal_customer_mapping dcm ON d.id = dcm.deal_id
                      WHERE dcm.customer_email = {0}
                        AND d.deal_status = 'Active'
                        AND d.plan_type = {1}
                      ORDER BY dcm.created_date DESC
                      LIMIT 1",
                    referrerEmail, planType)
                .FirstOrDefaultAsync(ct);

            if (mapped > 0) return mapped;

            var coupon = await _db.Database
                .SqlQueryRaw<decimal>(
                    @"SELECT bonus_amount AS Value
                      FROM deals
                      WHERE coupon_code = {0} AND deal_status = 'Active'
                      LIMIT 1",
                    defaultCouponCode)
                .FirstOrDefaultAsync(ct);

            return coupon > 0 ? coupon : fallback;
        }
        catch
        {
            return fallback;
        }
    }
}
