using System.Security.Cryptography;
using System.Text;
using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Auth.Dtos;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;
using MiniWebsite.Domain.Entities;
using MiniWebsite.Domain.Enums;
using MiniWebsite.Application.Common.Options;
using Microsoft.Extensions.Options;

namespace MiniWebsite.Application.Auth;

public class AuthService : IAuthService
{
    private readonly IApplicationDbContext _db;
    private readonly IPasswordHasher _passwordHasher;
    private readonly IJwtTokenService _jwt;
    private readonly IEmailSender _email;
    private readonly JwtOptions _jwtOptions;

    public AuthService(
        IApplicationDbContext db,
        IPasswordHasher passwordHasher,
        IJwtTokenService jwt,
        IEmailSender email,
        IOptions<JwtOptions> jwtOptions)
    {
        _db = db;
        _passwordHasher = passwordHasher;
        _jwt = jwt;
        _email = email;
        _jwtOptions = jwtOptions.Value;
    }

    public async Task<ApiResult<AuthResponse>> RegisterAsync(RegisterRequest request, CancellationToken ct = default)
    {
        var email = request.Email.Trim().ToLowerInvariant();
        var phone = request.Phone.Trim();

        if (await _db.Users.AnyAsync(u => u.Email == email, ct))
            return ApiResult<AuthResponse>.Fail("Email is already registered.");

        if (await _db.Users.AnyAsync(u => u.Phone == phone, ct))
            return ApiResult<AuthResponse>.Fail("Phone number is already registered.");

        string? referredBy = null;
        if (!string.IsNullOrWhiteSpace(request.ReferralCode))
        {
            var code = request.ReferralCode.Trim().ToUpperInvariant();
            var referrer = await _db.Users.FirstOrDefaultAsync(u => u.ReferralCode == code, ct);
            if (referrer != null)
                referredBy = referrer.Email;
        }

        var hash = _passwordHasher.Hash(request.Password);
        var user = new User
        {
            Name = request.Name.Trim(),
            Email = email,
            Phone = phone,
            Password = hash,
            PasswordHash = hash,
            Role = UserRole.Customer,
            Status = "ACTIVE",
            CollaborationEnabled = "NO",
            SaleskitEnabled = "NO",
            Influencer = "NO",
            State = string.IsNullOrWhiteSpace(request.State) ? null : request.State.Trim(),
            ReferralCode = GenerateReferralCode(email),
            ReferredBy = referredBy,
            CreatedAt = DateTime.Now,
            UpdatedAt = DateTime.Now,
            IsDeleted = false
        };

        _db.Users.Add(user);
        await _db.SaveChangesAsync(ct);

        try
        {
            await _email.SendAsync(user.Email, "Welcome to MiniWebsite",
                $"Hi {user.Name},<br/><br/>Your account has been created successfully.<br/>Email: {user.Email}", ct);
        }
        catch
        {
            // Email failure should not block registration
        }

        return ApiResult<AuthResponse>.Ok(await IssueTokensAsync(user, ct), "Registered successfully.");
    }

    public async Task<ApiResult<AuthResponse>> LoginAsync(LoginRequest request, CancellationToken ct = default)
    {
        var userId = request.UserId.Trim();
        var emailKey = userId.ToLowerInvariant();
        var user = await _db.Users.FirstOrDefaultAsync(
            u => u.Email.ToLower() == emailKey || u.Phone == userId, ct);

        if (user == null || !VerifyPassword(request.Password, user))
            return ApiResult<AuthResponse>.Fail("Invalid user id or password.");

        if (!string.Equals(user.Status, "ACTIVE", StringComparison.OrdinalIgnoreCase))
            return ApiResult<AuthResponse>.Fail("Account is not active.");

        return ApiResult<AuthResponse>.Ok(await IssueTokensAsync(user, ct), "Login successful.");
    }

    public async Task<ApiResult<AuthResponse>> RefreshAsync(RefreshTokenRequest request, CancellationToken ct = default)
    {
        var principal = _jwt.GetPrincipalFromExpiredToken(request.AccessToken);
        if (principal == null)
            return ApiResult<AuthResponse>.Fail("Invalid access token.");

        var userIdClaim = principal.FindFirst(System.Security.Claims.ClaimTypes.NameIdentifier)?.Value
            ?? principal.FindFirst("sub")?.Value;
        if (!int.TryParse(userIdClaim, out var userId))
            return ApiResult<AuthResponse>.Fail("Invalid access token claims.");

        var stored = await _db.RefreshTokens
            .Include(x => x.User)
            .FirstOrDefaultAsync(x => x.Token == request.RefreshToken && x.UserId == userId, ct);

        if (stored == null || !stored.IsActive)
            return ApiResult<AuthResponse>.Fail("Invalid or expired refresh token.");

        stored.RevokedAt = DateTime.UtcNow;
        await _db.SaveChangesAsync(ct);

        return ApiResult<AuthResponse>.Ok(await IssueTokensAsync(stored.User, ct));
    }

    public async Task<ApiResult> ForgotPasswordAsync(ForgotPasswordRequest request, CancellationToken ct = default)
    {
        var email = request.Email.Trim().ToLowerInvariant();
        var user = await _db.Users.FirstOrDefaultAsync(u => u.Email == email, ct);

        // Always return success to avoid account enumeration
        if (user == null)
            return ApiResult.Ok("If the email exists, a reset code has been sent.");

        var rawToken = Convert.ToHexString(RandomNumberGenerator.GetBytes(16));
        var token = new PasswordResetToken
        {
            UserId = user.Id,
            TokenHash = HashToken(rawToken),
            ExpiresAt = DateTime.UtcNow.AddMinutes(30)
        };
        _db.PasswordResetTokens.Add(token);
        await _db.SaveChangesAsync(ct);

        await _email.SendAsync(user.Email, "Password reset code",
            $"Hi {user.Name},<br/><br/>Your password reset code is: <b>{rawToken}</b><br/>Valid for 30 minutes.", ct);

        return ApiResult.Ok("If the email exists, a reset code has been sent.");
    }

    public async Task<ApiResult> ResetPasswordAsync(ResetPasswordRequest request, CancellationToken ct = default)
    {
        var email = request.Email.Trim().ToLowerInvariant();
        var user = await _db.Users.FirstOrDefaultAsync(u => u.Email == email, ct);
        if (user == null)
            return ApiResult.Fail("Invalid reset request.");

        var hash = HashToken(request.Token.Trim());
        var token = await _db.PasswordResetTokens
            .Where(t => t.UserId == user.Id && t.TokenHash == hash)
            .OrderByDescending(t => t.Id)
            .FirstOrDefaultAsync(ct);

        if (token == null || !token.IsValid)
            return ApiResult.Fail("Invalid or expired reset token.");

        var passwordHash = _passwordHasher.Hash(request.NewPassword);
        user.Password = passwordHash;
        user.PasswordHash = passwordHash;
        user.UpdatedAt = DateTime.Now;
        token.UsedAt = DateTime.UtcNow;

        var activeRefresh = await _db.RefreshTokens
            .Where(r => r.UserId == user.Id && r.RevokedAt == null)
            .ToListAsync(ct);
        foreach (var r in activeRefresh)
            r.RevokedAt = DateTime.UtcNow;

        await _db.SaveChangesAsync(ct);
        return ApiResult.Ok("Password reset successfully.");
    }

    public async Task<ApiResult> LogoutAsync(int userId, string refreshToken, CancellationToken ct = default)
    {
        var stored = await _db.RefreshTokens
            .FirstOrDefaultAsync(x => x.UserId == userId && x.Token == refreshToken, ct);
        if (stored != null && stored.RevokedAt == null)
        {
            stored.RevokedAt = DateTime.UtcNow;
            await _db.SaveChangesAsync(ct);
        }
        return ApiResult.Ok("Logged out.");
    }

    private async Task<AuthResponse> IssueTokensAsync(User user, CancellationToken ct)
    {
        var accessToken = _jwt.CreateAccessToken(user);
        var refreshToken = _jwt.CreateRefreshToken();
        var expiresAt = DateTime.UtcNow.AddMinutes(_jwtOptions.AccessTokenMinutes);

        _db.RefreshTokens.Add(new RefreshToken
        {
            UserId = user.Id,
            Token = refreshToken,
            ExpiresAt = DateTime.UtcNow.AddDays(_jwtOptions.RefreshTokenDays)
        });
        await _db.SaveChangesAsync(ct);

        return new AuthResponse(
            user.Id,
            user.Email,
            user.Name,
            user.Role.ToString(),
            accessToken,
            refreshToken,
            expiresAt);
    }

    private bool VerifyPassword(string password, User user)
    {
        if (!string.IsNullOrWhiteSpace(user.PasswordHash) && _passwordHasher.Verify(password, user.PasswordHash))
            return true;
        if (!string.IsNullOrWhiteSpace(user.Password) && _passwordHasher.Verify(password, user.Password))
            return true;
        return false;
    }

    private static string GenerateReferralCode(string email) =>
        Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(email + DateTime.UtcNow.Ticks)))[..8];

    private static string HashToken(string raw) =>
        Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(raw)));
}
