namespace MiniWebsite.Application.Auth.Dtos;

public record RegisterRequest(
    string Name,
    string Email,
    string Phone,
    string Password,
    string? State = null,
    string? ReferralCode = null);

public record LoginRequest(string UserId, string Password);

public record RefreshTokenRequest(string AccessToken, string RefreshToken);

public record ForgotPasswordRequest(string Email);

public record ResetPasswordRequest(string Email, string Token, string NewPassword);

public record AuthResponse(
    int UserId,
    string Email,
    string Name,
    string Role,
    string AccessToken,
    string RefreshToken,
    DateTime AccessTokenExpiresAt);
