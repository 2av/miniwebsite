using FluentValidation;
using Microsoft.Extensions.DependencyInjection;
using MiniWebsite.Application.Admin.FranchiseDistributors;
using MiniWebsite.Application.Admin.ManageCards;
using MiniWebsite.Application.Admin.ManageUsers;
using MiniWebsite.Application.Auth;
using MiniWebsite.Application.DigiCards;
using MiniWebsite.Application.Registration;
using MiniWebsite.Application.Users;
using MiniWebsite.Application.Website;
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
        services.AddScoped<IDigiCardService, DigiCardService>();
        services.AddScoped<IWebsiteService, WebsiteService>();
        services.AddScoped<IAdminManageUsersService, AdminManageUsersService>();
        services.AddScoped<IAdminManageCardsService, AdminManageCardsService>();
        services.AddScoped<IAdminFranchiseDistributorsService, AdminFranchiseDistributorsService>();
        return services;
    }
}
