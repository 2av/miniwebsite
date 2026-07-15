using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Domain.Entities;

namespace MiniWebsite.Infrastructure.Persistence;

public class ApplicationDbContext : DbContext, IApplicationDbContext
{
    public ApplicationDbContext(DbContextOptions<ApplicationDbContext> options) : base(options)
    {
    }

    public DbSet<User> Users => Set<User>();
    public DbSet<RefreshToken> RefreshTokens => Set<RefreshToken>();
    public DbSet<PasswordResetToken> PasswordResetTokens => Set<PasswordResetToken>();
    public DbSet<RegistrationPending> RegistrationPendings => Set<RegistrationPending>();
    public DbSet<ReferralEarning> ReferralEarnings => Set<ReferralEarning>();
    public DbSet<DigiCard> DigiCards => Set<DigiCard>();
    public DbSet<DigiCardPreviousSlug> DigiCardPreviousSlugs => Set<DigiCardPreviousSlug>();
    public DbSet<CardProductPricing> CardProducts => Set<CardProductPricing>();
    public DbSet<CardProductService> CardServices => Set<CardProductService>();
    public DbSet<CardImageGallery> CardGallery => Set<CardImageGallery>();
    public DbSet<CardSpecialOffer> CardOffers => Set<CardSpecialOffer>();

    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        modelBuilder.ApplyConfigurationsFromAssembly(typeof(ApplicationDbContext).Assembly);
        base.OnModelCreating(modelBuilder);
    }
}
