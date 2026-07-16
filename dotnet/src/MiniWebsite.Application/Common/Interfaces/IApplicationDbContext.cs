using Microsoft.EntityFrameworkCore;
using MiniWebsite.Domain.Entities;

namespace MiniWebsite.Application.Common.Interfaces;

public interface IApplicationDbContext
{
    DbSet<User> Users { get; }
    DbSet<RefreshToken> RefreshTokens { get; }
    DbSet<PasswordResetToken> PasswordResetTokens { get; }
    DbSet<RegistrationPending> RegistrationPendings { get; }
    DbSet<ReferralEarning> ReferralEarnings { get; }
    DbSet<Deal> Deals { get; }
    DbSet<DigiCard> DigiCards { get; }
    DbSet<DigiCardPreviousSlug> DigiCardPreviousSlugs { get; }
    DbSet<CardProductPricing> CardProducts { get; }
    DbSet<CardProductService> CardServices { get; }
    DbSet<CardImageGallery> CardGallery { get; }
    DbSet<CardSpecialOffer> CardOffers { get; }
    Task<int> SaveChangesAsync(CancellationToken cancellationToken = default);
}
