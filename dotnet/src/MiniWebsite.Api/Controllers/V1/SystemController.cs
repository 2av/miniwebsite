using Microsoft.AspNetCore.Mvc;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;
using MiniWebsite.Shared.Constants;

namespace MiniWebsite.Api.Controllers.V1;

/// <summary>
/// Scaffold endpoints proving Email / Payment abstractions are wired.
/// Real Auth, CRUD, and production payment flows come in later phases.
/// </summary>
[ApiController]
[Route(ApiConstants.ApiRoutePrefix + "/system")]
public class SystemController : ControllerBase
{
    private readonly IEmailSender _emailSender;
    private readonly IPaymentGateway _paymentGateway;

    public SystemController(IEmailSender emailSender, IPaymentGateway paymentGateway)
    {
        _emailSender = emailSender;
        _paymentGateway = paymentGateway;
    }

    [HttpPost("payments/create-order-demo")]
    public async Task<ActionResult<ApiResult<object>>> CreateOrderDemo([FromBody] DemoOrderRequest request, CancellationToken ct)
    {
        var order = await _paymentGateway.CreateOrderAsync(request.AmountInRupees, request.Receipt, ct);
        return Ok(ApiResult<object>.Ok(order, "Scaffold order created (replace with live Razorpay in Phase 3)."));
    }

    [HttpPost("email/send-demo")]
    public async Task<ActionResult<ApiResult>> SendEmailDemo([FromBody] DemoEmailRequest request, CancellationToken ct)
    {
        await _emailSender.SendAsync(request.ToEmail, request.Subject, request.HtmlBody, ct);
        return Ok(ApiResult.Ok("Email send invoked (skipped if SMTP not configured)."));
    }
}

public record DemoOrderRequest(decimal AmountInRupees, string Receipt);
public record DemoEmailRequest(string ToEmail, string Subject, string HtmlBody);
