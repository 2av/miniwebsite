using Microsoft.AspNetCore.Mvc;
using MiniWebsite.Application.Common.Models;
using MiniWebsite.Shared.Constants;

namespace MiniWebsite.Api.Controllers.V1;

[ApiController]
[Route(ApiConstants.ApiRoutePrefix + "/health")]
public class HealthController : ControllerBase
{
    [HttpGet]
    public ActionResult<ApiResult<object>> Get()
    {
        return Ok(ApiResult<object>.Ok(new
        {
            status = "Healthy",
            service = "MiniWebsite.Api",
            version = ApiConstants.ApiVersion,
            utc = DateTime.UtcNow
        }));
    }
}
