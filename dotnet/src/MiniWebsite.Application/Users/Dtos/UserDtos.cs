namespace MiniWebsite.Application.Users.Dtos;

public record UserDto(
    int Id,
    string Email,
    string? Phone,
    string Name,
    string Role,
    string Status,
    string? State,
    string? ReferralCode,
    string? ReferredBy,
    DateTime CreatedAt);

public record UpdateUserRequest(
    string? Name,
    string? Phone,
    string? State,
    string? Status);

public record CreateUserRequest(
    string Name,
    string Email,
    string Phone,
    string Password,
    string Role,
    string? State = null);
