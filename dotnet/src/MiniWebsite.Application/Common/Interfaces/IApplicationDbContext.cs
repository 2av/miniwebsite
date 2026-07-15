using Microsoft.EntityFrameworkCore;
using MiniWebsite.Domain.Entities;

namespace MiniWebsite.Application.Common.Interfaces;

public interface IApplicationDbContext
{
    DbSet<User> Users { get; }
    DbSet<RefreshToken> RefreshTokens { get; }
    DbSet<PasswordResetToken> PasswordResetTokens { get; }
    Task<int> SaveChangesAsync(CancellationToken cancellationToken = default);
}
