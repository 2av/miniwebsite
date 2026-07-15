using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging;

namespace MiniWebsite.Infrastructure.Persistence;

/// <summary>
/// Live DB already has PHP tables. Only ensure API-only tables exist.
/// </summary>
public static class DatabaseBootstrap
{
    public static async Task EnsureAuthTablesAsync(ApplicationDbContext db, ILogger logger, CancellationToken ct = default)
    {
        const string sql = """
            CREATE TABLE IF NOT EXISTS refresh_tokens (
              Id INT NOT NULL AUTO_INCREMENT,
              UserId INT NOT NULL,
              Token VARCHAR(500) NOT NULL,
              ExpiresAt DATETIME(6) NOT NULL,
              RevokedAt DATETIME(6) NULL,
              CreatedAt DATETIME(6) NOT NULL,
              UpdatedAt DATETIME(6) NULL,
              IsDeleted TINYINT(1) NOT NULL DEFAULT 0,
              PRIMARY KEY (Id),
              KEY IX_refresh_tokens_Token (Token),
              KEY IX_refresh_tokens_UserId (UserId)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS password_reset_tokens (
              Id INT NOT NULL AUTO_INCREMENT,
              UserId INT NOT NULL,
              TokenHash VARCHAR(128) NOT NULL,
              ExpiresAt DATETIME(6) NOT NULL,
              UsedAt DATETIME(6) NULL,
              CreatedAt DATETIME(6) NOT NULL,
              UpdatedAt DATETIME(6) NULL,
              IsDeleted TINYINT(1) NOT NULL DEFAULT 0,
              PRIMARY KEY (Id),
              KEY IX_password_reset_tokens_UserId (UserId)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS registration_pending (
              Id INT NOT NULL AUTO_INCREMENT,
              Role VARCHAR(20) NOT NULL,
              Email VARCHAR(255) NOT NULL,
              Phone VARCHAR(25) NOT NULL,
              Name VARCHAR(150) NOT NULL,
              State VARCHAR(100) NULL,
              PasswordHash VARCHAR(255) NOT NULL,
              PlainPassword VARCHAR(255) NOT NULL,
              ReferrerEmail VARCHAR(255) NULL,
              Otp VARCHAR(10) NOT NULL,
              ExpiresAt DATETIME(6) NOT NULL,
              IsConsumed TINYINT(1) NOT NULL DEFAULT 0,
              CreatedAt DATETIME(6) NOT NULL,
              UpdatedAt DATETIME(6) NULL,
              IsDeleted TINYINT(1) NOT NULL DEFAULT 0,
              PRIMARY KEY (Id),
              KEY IX_registration_pending_Email_Role (Email, Role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            """;

        await db.Database.ExecuteSqlRawAsync(sql, ct);
        logger.LogInformation("Auxiliary tables verified (auth + registration_pending).");
    }
}
