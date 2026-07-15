using FluentValidation;
using Microsoft.Extensions.DependencyInjection;
using MiniWebsite.Application.Auth;
using MiniWebsite.Application.Registration;
using MiniWebsite.Application.Users;
using System.Reflection;

namespace MiniWebsite.Application;

public static class DependencyInjection
{
    public static IServiceCollection AddApplication(this IServiceCollection services)
    {
        services.AddValidatorsFromAssembly(Assembly.GetExecutingAssembly());
        services.AddScoped<IAuthService, AuthService>();
        services.AddScoped<IUserService, UserService>();
        services.AddScoped<IRegistrationService, RegistrationService>();
        return services;
    }
}
