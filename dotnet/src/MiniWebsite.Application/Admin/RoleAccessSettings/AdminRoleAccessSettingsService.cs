using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Admin.RoleAccessSettings.Dtos;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;

namespace MiniWebsite.Application.Admin.RoleAccessSettings;

public class AdminRoleAccessSettingsService : IAdminRoleAccessSettingsService
{
    private readonly IApplicationDbContext _db;

    public AdminRoleAccessSettingsService(IApplicationDbContext db)
    {
        _db = db;
    }

    public async Task<RoleAccessMatrixDto> GetMatrixAsync(CancellationToken ct = default)
    {
        var ef = RequireEf();
        if (!await TablesExistAsync(ef, ct))
        {
            return new RoleAccessMatrixDto(false, [], [], []);
        }

        var profiles = await ef.Database
            .SqlQueryRaw<ProfileRow>(
                @"SELECT id AS Id,
                         profile_key AS ProfileKey,
                         profile_label AS ProfileLabel,
                         base_role AS BaseRole,
                         requires_collaboration AS RequiresCollaboration,
                         requires_influencer AS RequiresInfluencer,
                         CAST(sort_order AS SIGNED) AS SortOrder
                  FROM role_access_profiles
                  WHERE is_active = 1
                  ORDER BY sort_order ASC, id ASC")
            .ToListAsync(ct);

        var features = await ef.Database
            .SqlQueryRaw<FeatureRow>(
                @"SELECT id AS Id,
                         feature_key AS FeatureKey,
                         feature_label AS FeatureLabel,
                         feature_group AS FeatureGroup,
                         field_type AS FieldType,
                         CAST(sort_order AS SIGNED) AS SortOrder
                  FROM role_access_features
                  WHERE is_active = 1
                  ORDER BY sort_order ASC, id ASC")
            .ToListAsync(ct);

        var cells = await ef.Database
            .SqlQueryRaw<CellRow>(
                @"SELECT s.id AS SettingId,
                         p.profile_key AS ProfileKey,
                         f.feature_key AS FeatureKey,
                         CAST(s.is_not_applicable AS SIGNED) AS IsNotApplicableInt,
                         IFNULL(s.setting_value,'') AS SettingValue
                  FROM role_access_settings s
                  INNER JOIN role_access_profiles p ON p.id = s.profile_id
                  INNER JOIN role_access_features f ON f.id = s.feature_id
                  ORDER BY p.sort_order ASC, f.sort_order ASC")
            .ToListAsync(ct);

        var profileDtos = profiles
            .Select(p => new RoleAccessProfileDto(
                p.Id,
                p.ProfileKey ?? "",
                p.ProfileLabel ?? "",
                p.BaseRole ?? "",
                p.RequiresCollaboration ?? "ANY",
                p.RequiresInfluencer ?? "ANY",
                p.SortOrder))
            .ToList();

        var featureDtos = features
            .Select(f => new RoleAccessFeatureDto(
                f.Id,
                f.FeatureKey ?? "",
                f.FeatureLabel ?? "",
                f.FeatureGroup ?? "General",
                f.FieldType ?? "text",
                f.SortOrder))
            .ToList();

        var groups = featureDtos
            .GroupBy(f => f.FeatureGroup, StringComparer.OrdinalIgnoreCase)
            .Select(g => new RoleAccessFeatureGroupDto(
                g.Key,
                g.OrderBy(x => x.SortOrder).ThenBy(x => x.FeatureLabel, StringComparer.OrdinalIgnoreCase).ToList()))
            .ToList();

        var cellDtos = cells
            .Select(c => new RoleAccessCellDto(
                c.SettingId,
                c.ProfileKey ?? "",
                c.FeatureKey ?? "",
                c.IsNotApplicableInt != 0,
                c.SettingValue ?? ""))
            .ToList();

        return new RoleAccessMatrixDto(true, profileDtos, groups, cellDtos);
    }

    public async Task<ApiResult> UpdateSettingAsync(int settingId, UpdateRoleAccessSettingRequest request, CancellationToken ct = default)
    {
        if (settingId <= 0)
            return ApiResult.Fail("Invalid setting.");

        var ef = RequireEf();
        if (!await TablesExistAsync(ef, ct))
            return ApiResult.Fail("Role access tables are not set up yet.");

        var exists = (await ef.Database
            .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM role_access_settings WHERE id = {0}", settingId)
            .FirstAsync(ct)).Value;
        if (exists == 0)
            return ApiResult.Fail("Setting not found.");

        var isNa = request.IsNotApplicable ? 1 : 0;
        var value = (request.SettingValue ?? "").Trim();
        var updatedBy = string.IsNullOrWhiteSpace(request.UpdatedBy) ? "admin" : request.UpdatedBy.Trim();

        try
        {
            await ef.Database.ExecuteSqlRawAsync(
                @"UPDATE role_access_settings
                  SET is_not_applicable = {0}, setting_value = {1}, updated_by = {2}
                  WHERE id = {3}",
                [isNa, value, updatedBy, settingId],
                ct);
            return ApiResult.Ok("Setting updated successfully.");
        }
        catch (Exception ex)
        {
            return ApiResult.Fail("Failed to update setting: " + ex.Message);
        }
    }

    private static async Task<bool> TablesExistAsync(DbContext ef, CancellationToken ct)
    {
        var count = (await ef.Database
            .SqlQueryRaw<CountRow>(
                @"SELECT COUNT(*) AS Value
                  FROM information_schema.tables
                  WHERE table_schema = DATABASE()
                    AND table_name = 'role_access_profiles'")
            .FirstAsync(ct)).Value;
        return count > 0;
    }

    private DbContext RequireEf() =>
        _db as DbContext ?? throw new InvalidOperationException("Database context unavailable.");

    private sealed class CountRow { public int Value { get; set; } }

    private sealed class ProfileRow
    {
        public int Id { get; set; }
        public string? ProfileKey { get; set; }
        public string? ProfileLabel { get; set; }
        public string? BaseRole { get; set; }
        public string? RequiresCollaboration { get; set; }
        public string? RequiresInfluencer { get; set; }
        public int SortOrder { get; set; }
    }

    private sealed class FeatureRow
    {
        public int Id { get; set; }
        public string? FeatureKey { get; set; }
        public string? FeatureLabel { get; set; }
        public string? FeatureGroup { get; set; }
        public string? FieldType { get; set; }
        public int SortOrder { get; set; }
    }

    private sealed class CellRow
    {
        public int SettingId { get; set; }
        public string? ProfileKey { get; set; }
        public string? FeatureKey { get; set; }
        public int IsNotApplicableInt { get; set; }
        public string? SettingValue { get; set; }
    }
}
