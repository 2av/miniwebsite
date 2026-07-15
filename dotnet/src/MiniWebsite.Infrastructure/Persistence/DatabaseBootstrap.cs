using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Logging;

namespace MiniWebsite.Infrastructure.Persistence;

/// <summary>
/// Live DB already has PHP tables. Only ensure API-only auth tables exist.
/// Never run EnsureCreated against miniwebsite_live (it would no-op or fight the schema).
/// </summary>
public static class DatabaseBootstrap
{
    public static async Task EnsureAuthTablesAsync(ApplicationDbContext db, ILogger logger, CancellationToken ct = default)
    {
        // Create auxiliary tables needed by JWT refresh / password reset.
        // Safe on both local and live (IF NOT EXISTS).
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
            """;

        await db.Database.ExecuteSqlRawAsync(sql, ct);
        logger.LogInformation("Auth auxiliary tables verified (refresh_tokens, password_reset_tokens).");
    }
}
