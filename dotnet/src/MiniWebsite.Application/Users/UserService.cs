using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;
using MiniWebsite.Application.Users.Dtos;
using MiniWebsite.Domain.Entities;
using MiniWebsite.Domain.Enums;

namespace MiniWebsite.Application.Users;

public class UserService : IUserService
{
    private readonly IApplicationDbContext _db;
    private readonly IPasswordHasher _passwordHasher;

    public UserService(IApplicationDbContext db, IPasswordHasher passwordHasher)
    {
        _db = db;
        _passwordHasher = passwordHasher;
    }

    public async Task<ApiResult<UserDto>> GetByIdAsync(int id, CancellationToken ct = default)
    {
        var user = await _db.Users.FirstOrDefaultAsync(u => u.Id == id, ct);
        return user == null
            ? ApiResult<UserDto>.Fail("User not found.")
            : ApiResult<UserDto>.Ok(Map(user));
    }

    public Task<ApiResult<UserDto>> GetMeAsync(int userId, CancellationToken ct = default) =>
        GetByIdAsync(userId, ct);

    public async Task<ApiResult<PagedResult<UserDto>>> ListAsync(int page, int pageSize, string? role, string? search, CancellationToken ct = default)
    {
        page = page < 1 ? 1 : page;
        pageSize = pageSize is < 1 or > 100 ? 20 : pageSize;

        var query = _db.Users.AsQueryable();

        if (!string.IsNullOrWhiteSpace(role) && Enum.TryParse<UserRole>(role, true, out var roleEnum))
            query = query.Where(u => u.Role == roleEnum);

        if (!string.IsNullOrWhiteSpace(search))
        {
            var s = search.Trim().ToLowerInvariant();
            query = query.Where(u =>
                u.Email.Contains(s) ||
                u.Name.ToLower().Contains(s) ||
                (u.Phone != null && u.Phone.Contains(s)));
        }

        var total = await query.CountAsync(ct);
        var items = await query
            .OrderByDescending(u => u.Id)
            .Skip((page - 1) * pageSize)
            .Take(pageSize)
            .ToListAsync(ct);

        return ApiResult<PagedResult<UserDto>>.Ok(new PagedResult<UserDto>
        {
            Items = items.Select(Map).ToList(),
            Page = page,
            PageSize = pageSize,
            TotalCount = total
        });
    }

    public async Task<ApiResult<UserDto>> CreateAsync(CreateUserRequest request, CancellationToken ct = default)
    {
        var email = request.Email.Trim().ToLowerInvariant();
        var phone = request.Phone.Trim();

        if (!Enum.TryParse<UserRole>(request.Role, true, out var role))
            return ApiResult<UserDto>.Fail("Invalid role. Use Customer, Franchisee, Team, or Admin.");

        if (await _db.Users.AnyAsync(u => u.Email == email, ct))
            return ApiResult<UserDto>.Fail("Email is already registered.");

        if (await _db.Users.AnyAsync(u => u.Phone == phone, ct))
            return ApiResult<UserDto>.Fail("Phone number is already registered.");

        var user = new User
        {
            Name = request.Name.Trim(),
            Email = email,
            Phone = phone,
            PasswordHash = _passwordHasher.Hash(request.Password),
            Role = role,
            Status = "ACTIVE",
            State = string.IsNullOrWhiteSpace(request.State) ? null : request.State.Trim(),
            ReferralCode = Guid.NewGuid().ToString("N")[..8].ToUpperInvariant()
        };

        _db.Users.Add(user);
        await _db.SaveChangesAsync(ct);
        return ApiResult<UserDto>.Ok(Map(user), "User created.");
    }

    public async Task<ApiResult<UserDto>> UpdateAsync(int id, UpdateUserRequest request, CancellationToken ct = default)
    {
        var user = await _db.Users.FirstOrDefaultAsync(u => u.Id == id, ct);
        if (user == null)
            return ApiResult<UserDto>.Fail("User not found.");

        if (!string.IsNullOrWhiteSpace(request.Name))
            user.Name = request.Name.Trim();
        if (!string.IsNullOrWhiteSpace(request.Phone))
        {
            var phone = request.Phone.Trim();
            if (await _db.Users.AnyAsync(u => u.Phone == phone && u.Id != id, ct))
                return ApiResult<UserDto>.Fail("Phone number is already in use.");
            user.Phone = phone;
        }
        if (request.State != null)
            user.State = string.IsNullOrWhiteSpace(request.State) ? null : request.State.Trim();
        if (!string.IsNullOrWhiteSpace(request.Status))
            user.Status = request.Status.Trim().ToUpperInvariant();

        await _db.SaveChangesAsync(ct);
        return ApiResult<UserDto>.Ok(Map(user), "User updated.");
    }

    public async Task<ApiResult> SoftDeleteAsync(int id, CancellationToken ct = default)
    {
        var user = await _db.Users.FirstOrDefaultAsync(u => u.Id == id, ct);
        if (user == null)
            return ApiResult.Fail("User not found.");

        user.Status = "DELETED";
        await _db.SaveChangesAsync(ct);
        return ApiResult.Ok("User deleted.");
    }

    private static UserDto Map(User u) => new(
        u.Id, u.Email, u.Phone, u.Name, u.Role.ToString(), u.Status,
        u.State, u.ReferralCode, u.ReferredBy, u.CreatedAt);
}
