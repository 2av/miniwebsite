using System.Security.Claims;
using FluentValidation;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MiniWebsite.Application.Auth;
using MiniWebsite.Application.Auth.Dtos;
using MiniWebsite.Application.Common.Models;
using MiniWebsite.Shared.Constants;

namespace MiniWebsite.Api.Controllers.V1;

[ApiController]
[Route(ApiConstants.ApiRoutePrefix + "/auth")]
public class AuthController : ControllerBase
{
    private readonly IAuthService _auth;
    private readonly IValidator<RegisterRequest> _registerValidator;
    private readonly IValidator<LoginRequest> _loginValidator;
    private readonly IValidator<ResetPasswordRequest> _resetValidator;

    public AuthController(
        IAuthService auth,
        IValidator<RegisterRequest> registerValidator,
        IValidator<LoginRequest> loginValidator,
        IValidator<ResetPasswordRequest> resetValidator)
    {
        _auth = auth;
        _registerValidator = registerValidator;
        _loginValidator = loginValidator;
        _resetValidator = resetValidator;
    }

    [HttpPost("register")]
    [AllowAnonymous]
    public async Task<ActionResult<ApiResult<AuthResponse>>> Register([FromBody] RegisterRequest request, CancellationToken ct)
    {
        var validation = await _registerValidator.ValidateAsync(request, ct);
        if (!validation.IsValid)
            return BadRequest(ApiResult<AuthResponse>.Fail("Validation failed.", ToErrors(validation)));

        var result = await _auth.RegisterAsync(request, ct);
        return result.Success ? Ok(result) : BadRequest(result);
    }

    [HttpPost("login")]
    [AllowAnonymous]
    public async Task<ActionResult<ApiResult<AuthResponse>>> Login([FromBody] LoginRequest request, CancellationToken ct)
    {
        var validation = await _loginValidator.ValidateAsync(request, ct);
        if (!validation.IsValid)
            return BadRequest(ApiResult<AuthResponse>.Fail("Validation failed.", ToErrors(validation)));

        var result = await _auth.LoginAsync(request, ct);
        return result.Success ? Ok(result) : Unauthorized(result);
    }

    [HttpPost("refresh")]
    [AllowAnonymous]
    public async Task<ActionResult<ApiResult<AuthResponse>>> Refresh([FromBody] RefreshTokenRequest request, CancellationToken ct)
    {
        var result = await _auth.RefreshAsync(request, ct);
        return result.Success ? Ok(result) : Unauthorized(result);
    }

    [HttpPost("forgot-password")]
    [AllowAnonymous]
    public async Task<ActionResult<ApiResult>> ForgotPassword([FromBody] ForgotPasswordRequest request, CancellationToken ct)
    {
        var result = await _auth.ForgotPasswordAsync(request, ct);
        return Ok(result);
    }

    [HttpPost("reset-password")]
    [AllowAnonymous]
    public async Task<ActionResult<ApiResult>> ResetPassword([FromBody] ResetPasswordRequest request, CancellationToken ct)
    {
        var validation = await _resetValidator.ValidateAsync(request, ct);
        if (!validation.IsValid)
            return BadRequest(ApiResult.Fail("Validation failed.", ToErrors(validation)));

        var result = await _auth.ResetPasswordAsync(request, ct);
        return result.Success ? Ok(result) : BadRequest(result);
    }

    [HttpPost("logout")]
    [Authorize]
    public async Task<ActionResult<ApiResult>> Logout([FromBody] LogoutRequest request, CancellationToken ct)
    {
        var userId = GetUserId();
        if (userId == null) return Unauthorized(ApiResult.Fail("Unauthorized."));
        var result = await _auth.LogoutAsync(userId.Value, request.RefreshToken, ct);
        return Ok(result);
    }

    private int? GetUserId()
    {
        var id = User.FindFirstValue(ClaimTypes.NameIdentifier) ?? User.FindFirstValue("sub");
        return int.TryParse(id, out var userId) ? userId : null;
    }

    private static Dictionary<string, string[]> ToErrors(FluentValidation.Results.ValidationResult validation) =>
        validation.Errors
            .GroupBy(e => e.PropertyName)
            .ToDictionary(g => g.Key, g => g.Select(e => e.ErrorMessage).ToArray());
}

public record LogoutRequest(string RefreshToken);
