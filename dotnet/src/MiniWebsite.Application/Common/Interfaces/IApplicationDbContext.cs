using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Domain.Entities;

namespace MiniWebsite.Application.Common.Interfaces;

public interface IApplicationDbContext
{
    DbSet<User> Users { get; }
    DbSet<RefreshToken> RefreshTokens { get; }
    DbSet<PasswordResetToken> PasswordResetTokens { get; }
    DbSet<RegistrationPending> RegistrationPendings { get; }
    DbSet<ReferralEarning> ReferralEarnings { get; }
    Task<int> SaveChangesAsync(CancellationToken cancellationToken = default);
}
