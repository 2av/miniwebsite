using System.Globalization;
using System.Net;
using System.Text;
using System.Text.RegularExpressions;
using Microsoft.EntityFrameworkCore;
using MiniWebsite.Application.Admin.ManageTeams.Dtos;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;
using MiniWebsite.Domain.Entities;
using MiniWebsite.Domain.Enums;

namespace MiniWebsite.Application.Admin.ManageTeams;

public class AdminManageTeamsService : IAdminManageTeamsService
{
    private readonly IApplicationDbContext _db;
    private readonly IPasswordHasher _passwordHasher;
    private readonly IEmailSender _email;

    public AdminManageTeamsService(
        IApplicationDbContext db,
        IPasswordHasher passwordHasher,
        IEmailSender email)
    {
        _db = db;
        _passwordHasher = passwordHasher;
        _email = email;
    }

    public async Task<ApiResult<ManageTeamsPageDto>> ListAsync(ManageTeamsQuery query, CancellationToken ct = default)
    {
        query.Page = query.Page < 1 ? 1 : query.Page;
        query.PageSize = query.PageSize is < 1 or > 100 ? 10 : query.PageSize;

        try
        {
            var q = _db.Users.AsNoTracking().Where(u => u.Role == UserRole.Team);
            if (!string.IsNullOrWhiteSpace(query.Search))
            {
                var s = query.Search.Trim().ToLower();
                q = q.Where(u =>
                    u.Email.ToLower().Contains(s)
                    || u.Name.ToLower().Contains(s)
                    || (u.Phone != null && u.Phone.Contains(s))
                    || (u.District != null && u.District.ToLower().Contains(s))
                    || (u.State != null && u.State.ToLower().Contains(s)));
            }

            var status = NormalizeStatusFilter(query.Status);
            if (status != null)
                q = q.Where(u => u.Status == status);

            var total = await q.CountAsync(ct);
            var users = await q
                .OrderByDescending(u => u.CreatedAt)
                .ThenByDescending(u => u.Id)
                .Skip((query.Page - 1) * query.PageSize)
                .Take(query.PageSize)
                .ToListAsync(ct);

            var rows = new List<ManageTeamRowDto>(users.Count);
            foreach (var u in users)
                rows.Add(await MapRowAsync(u, ct));

            return ApiResult<ManageTeamsPageDto>.Ok(new ManageTeamsPageDto(rows, total, query.Page, query.PageSize));
        }
        catch (Exception ex)
        {
            return ApiResult<ManageTeamsPageDto>.Fail("Unable to load team members: " + ex.Message);
        }
    }

    public async Task<ApiResult<ManageTeamRowDto>> GetAsync(int id, CancellationToken ct = default)
    {
        var user = await FindTeamAsync(id, tracked: false, ct);
        if (user == null) return ApiResult<ManageTeamRowDto>.Fail("Team member not found");
        return ApiResult<ManageTeamRowDto>.Ok(await MapRowAsync(user, ct));
    }

    public async Task<ApiResult<ManageTeamRowDto>> CreateAsync(CreateTeamMemberRequest request, CancellationToken ct = default)
    {
        var name = (request.Name ?? "").Trim();
        var email = NormalizeEmail(request.Email);
        var phone = (request.Phone ?? "").Trim();
        var district = (request.District ?? "").Trim();
        var state = (request.State ?? "").Trim();
        var password = request.Password ?? "";

        if (name.Length == 0) return ApiResult<ManageTeamRowDto>.Fail("Member name is required.");
        if (email.Length == 0 || !email.Contains('@')) return ApiResult<ManageTeamRowDto>.Fail("A valid member email address is required.");
        if (password.Length < 6) return ApiResult<ManageTeamRowDto>.Fail("Password must be at least 6 characters long.");

        var emailConflict = await _db.Users.AsNoTracking()
            .Where(u => u.Email.ToLower() == email)
            .Select(u => u.Role)
            .FirstOrDefaultAsync(ct);
        if (emailConflict != default)
            return ApiResult<ManageTeamRowDto>.Fail(
                $"This email address is already registered as a {RoleLabel(emailConflict)}. Please use a different email.");

        if (phone.Length > 0)
        {
            var phoneConflict = await _db.Users.AsNoTracking()
                .Where(u => u.Phone == phone)
                .Select(u => u.Role)
                .FirstOrDefaultAsync(ct);
            if (phoneConflict != default)
                return ApiResult<ManageTeamRowDto>.Fail(
                    $"This mobile number is already registered as a {RoleLabel(phoneConflict)}. Please use a different mobile number.");
        }

        var hash = _passwordHasher.Hash(password);
        var user = new User
        {
            Role = UserRole.Team,
            Email = email,
            Phone = phone,
            Name = name,
            District = district,
            State = state,
            Password = hash,
            PasswordHash = hash,
            Status = "ACTIVE",
            CreatedAt = DateTime.UtcNow,
            UpdatedAt = DateTime.UtcNow,
        };
        _db.Users.Add(user);
        await _db.SaveChangesAsync(ct);

        var message = "Team member added successfully.";
        try
        {
            var html = $"""
                <p>Hello {WebUtility.HtmlEncode(name)},</p>
                <p>Your team member account has been created on MiniWebsite.</p>
                <p><strong>Login Email:</strong> {WebUtility.HtmlEncode(email)}<br/>
                <strong>Password:</strong> {WebUtility.HtmlEncode(password)}</p>
                <p>Please keep these credentials secure.</p>
                """;
            await _email.SendAsync(email, "Welcome to MiniWebsite Team", html, ct);
        }
        catch
        {
            message = "Team member created, but welcome email could not be sent.";
        }

        return ApiResult<ManageTeamRowDto>.Ok(await MapRowAsync(user, ct), message);
    }

    public async Task<ApiResult<ManageTeamRowDto>> UpdateAsync(int id, UpdateTeamMemberRequest request, CancellationToken ct = default)
    {
        var user = await FindTeamAsync(id, tracked: true, ct);
        if (user == null) return ApiResult<ManageTeamRowDto>.Fail("Team member not found");

        var name = (request.Name ?? "").Trim();
        var email = NormalizeEmail(request.Email);
        var phone = (request.Phone ?? "").Trim();
        var district = (request.District ?? "").Trim();
        var state = (request.State ?? "").Trim();

        if (name.Length == 0) return ApiResult<ManageTeamRowDto>.Fail("Member name is required.");
        if (email.Length == 0 || !email.Contains('@')) return ApiResult<ManageTeamRowDto>.Fail("A valid member email address is required.");

        var emailConflict = await _db.Users.AsNoTracking()
            .Where(u => u.Id != user.Id && u.Email.ToLower() == email)
            .Select(u => u.Role)
            .FirstOrDefaultAsync(ct);
        if (emailConflict != default)
            return ApiResult<ManageTeamRowDto>.Fail(
                $"This email address is already registered as a {RoleLabel(emailConflict)}. Please use a different email.");

        if (phone.Length > 0)
        {
            var phoneConflict = await _db.Users.AsNoTracking()
                .Where(u => u.Id != user.Id && u.Phone == phone)
                .Select(u => u.Role)
                .FirstOrDefaultAsync(ct);
            if (phoneConflict != default)
                return ApiResult<ManageTeamRowDto>.Fail(
                    $"This mobile number is already registered as a {RoleLabel(phoneConflict)}. Please use a different mobile number.");
        }

        user.Name = name;
        user.Email = email;
        user.Phone = phone;
        user.District = district;
        user.State = state;
        user.UpdatedAt = DateTime.UtcNow;
        await _db.SaveChangesAsync(ct);

        return ApiResult<ManageTeamRowDto>.Ok(await MapRowAsync(user, ct), "Team member updated successfully.");
    }

    public async Task<ApiResult<ManageTeamRowDto>> ToggleStatusAsync(int id, ToggleTeamStatusRequest request, CancellationToken ct = default)
    {
        var user = await FindTeamAsync(id, tracked: true, ct);
        if (user == null) return ApiResult<ManageTeamRowDto>.Fail("Team member not found");

        var requested = (request.NewStatus ?? "").Trim().ToUpperInvariant();
        string newStatus;
        if (requested is "ACTIVE" or "INACTIVE")
            newStatus = requested;
        else
            newStatus = string.Equals(user.Status, "ACTIVE", StringComparison.OrdinalIgnoreCase) ? "INACTIVE" : "ACTIVE";

        user.Status = newStatus;
        user.UpdatedAt = DateTime.UtcNow;
        await _db.SaveChangesAsync(ct);
        return ApiResult<ManageTeamRowDto>.Ok(await MapRowAsync(user, ct), "Member status updated.");
    }

    public async Task<ApiResult> ResetPasswordAsync(int id, ResetTeamPasswordRequest request, CancellationToken ct = default)
    {
        var password = (request.NewPassword ?? "").Trim();
        if (password.Length < 6)
            return ApiResult.Fail("Please provide a valid password (minimum 6 characters).");

        var user = await FindTeamAsync(id, tracked: true, ct);
        if (user == null) return ApiResult.Fail("Team member not found");

        var hash = _passwordHasher.Hash(password);
        user.Password = hash;
        user.PasswordHash = hash;
        user.UpdatedAt = DateTime.UtcNow;
        await _db.SaveChangesAsync(ct);
        return ApiResult.Ok("Password reset successfully.");
    }

    public async Task<ApiResult<TeamReferralsDto>> GetReferralsAsync(int id, CancellationToken ct = default)
    {
        var user = await FindTeamAsync(id, tracked: false, ct);
        if (user == null) return ApiResult<TeamReferralsDto>.Fail("Team member not found");

        var email = NormalizeEmail(user.Email);
        var ef = RequireEf();

        try
        {
            var rows = await ef.Database
                .SqlQueryRaw<ReferralSqlRow>(
                    @"SELECT DISTINCT
                        ud_referred.id AS UserId,
                        IFNULL(ud_referred.name, '') AS ReferredName,
                        COALESCE(re.referred_email, ud_referred.email) AS ReferredEmail,
                        IFNULL(ud_referred.phone, '') AS Phone,
                        COALESCE(re.is_collaboration, 'NO') AS IsCollaboration,
                        COALESCE(re.referral_date, ud_referred.created_at) AS ReferralDate,
                        IFNULL(dc.d_payment_status, '') AS PaymentStatus,
                        dc.d_payment_date AS PaymentDate
                      FROM user_details ud_referred
                      LEFT JOIN referral_earnings re
                        ON CONVERT(re.referred_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
                       AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT({0} USING utf8mb4)
                      LEFT JOIN digi_card dc
                        ON CONVERT(dc.user_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
                      WHERE (CONVERT(ud_referred.referred_by USING utf8mb4) = CONVERT({0} USING utf8mb4)
                             AND ud_referred.referred_by != ''
                             AND ud_referred.referred_by IS NOT NULL)
                         OR (re.id IS NOT NULL AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT({0} USING utf8mb4))
                      ORDER BY COALESCE(re.id, 0) DESC, ud_referred.created_at DESC
                      LIMIT 300",
                    email)
                .ToListAsync(ct);

            var totalSales = (await ef.Database
                .SqlQueryRaw<CountRow>(
                    @"SELECT COUNT(DISTINCT ud_referred.email) AS Value
                      FROM user_details ud_referred
                      INNER JOIN digi_card dc
                        ON CONVERT(dc.user_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
                       AND dc.d_payment_status = 'Success'
                      WHERE (CONVERT(ud_referred.referred_by USING utf8mb4) = CONVERT({0} USING utf8mb4)
                             AND ud_referred.referred_by != ''
                             AND ud_referred.referred_by IS NOT NULL)
                         OR EXISTS (
                              SELECT 1 FROM referral_earnings re
                               WHERE CONVERT(re.referred_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
                                 AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT({0} USING utf8mb4)
                         )",
                    email)
                .FirstAsync(ct)).Value;

            var totalMw = (await ef.Database
                .SqlQueryRaw<CountRow>(
                    @"SELECT COUNT(dc.id) AS Value
                      FROM user_details ud_referred
                      LEFT JOIN digi_card dc
                        ON CONVERT(dc.user_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
                      WHERE (CONVERT(ud_referred.referred_by USING utf8mb4) = CONVERT({0} USING utf8mb4)
                             AND ud_referred.referred_by != ''
                             AND ud_referred.referred_by IS NOT NULL)
                         OR EXISTS (
                              SELECT 1 FROM referral_earnings re
                               WHERE CONVERT(re.referred_email USING utf8mb4) = CONVERT(ud_referred.email USING utf8mb4)
                                 AND CONVERT(re.referrer_email USING utf8mb4) = CONVERT({0} USING utf8mb4)
                         )",
                    email)
                .FirstAsync(ct)).Value;

            var mapped = rows.Select(r =>
            {
                var (label, tone) = MapPaymentStatus(r.PaymentStatus, r.PaymentDate);
                var type = string.Equals(r.IsCollaboration, "YES", StringComparison.OrdinalIgnoreCase)
                    ? "Franchise"
                    : "Mini Website";
                var name = string.IsNullOrWhiteSpace(r.ReferredName) ? (r.ReferredEmail ?? "") : r.ReferredName;
                return new TeamReferralRowDto(
                    r.UserId,
                    name,
                    r.ReferredEmail ?? "",
                    string.IsNullOrWhiteSpace(r.Phone) ? "—" : r.Phone,
                    type,
                    label,
                    tone,
                    FormatPaidOn(r.PaymentStatus, r.PaymentDate),
                    FormatDate(r.ReferralDate));
            }).ToList();

            return ApiResult<TeamReferralsDto>.Ok(new TeamReferralsDto(
                user.Name,
                user.Email,
                totalSales,
                totalMw,
                mapped));
        }
        catch (Exception ex)
        {
            return ApiResult<TeamReferralsDto>.Fail("Unable to load referrals: " + ex.Message);
        }
    }

    public async Task<ApiResult<TeamTrackerDto>> GetTrackerAsync(int id, CancellationToken ct = default)
    {
        var user = await FindTeamAsync(id, tracked: false, ct);
        if (user == null) return ApiResult<TeamTrackerDto>.Fail("Team member not found");

        try
        {
            var ef = RequireEf();
            var rows = await ef.Database
                .SqlQueryRaw<TrackerSqlRow>(
                    @"SELECT ct.id AS Id,
                             IFNULL(ct.shop_name,'') AS ShopName,
                             IFNULL(ct.contact_number,'') AS ContactNumber,
                             IFNULL(ct.approached_for,'') AS ApproachedFor,
                             IFNULL(ct.address,'') AS Address,
                             ct.date_visited AS DateVisited,
                             IFNULL(ct.final_status,'') AS FinalStatus,
                             COALESCE(
                                 (SELECT MAX(f.followup_datetime)
                                    FROM customer_tracker_followups f
                                   WHERE f.tracker_id = ct.id),
                                 ct.created_at
                             ) AS LastUpdated
                      FROM customer_tracker ct
                      WHERE ct.team_member_id = {0}
                      ORDER BY ct.date_visited DESC, LastUpdated DESC",
                    id)
                .ToListAsync(ct);

            var mapped = rows.Select(r =>
            {
                var (tone, _) = MapTrackerStatus(r.FinalStatus);
                return new TeamTrackerRowDto(
                    r.Id,
                    r.ShopName ?? "",
                    string.IsNullOrWhiteSpace(r.ContactNumber) ? "-" : r.ContactNumber,
                    string.IsNullOrWhiteSpace(r.ApproachedFor) ? "-" : r.ApproachedFor,
                    string.IsNullOrWhiteSpace(r.Address) ? "-" : r.Address,
                    FormatDate(r.DateVisited),
                    r.FinalStatus ?? "",
                    tone,
                    FormatDateTime(r.LastUpdated));
            }).ToList();

            return ApiResult<TeamTrackerDto>.Ok(new TeamTrackerDto(user.Name, user.Email, mapped));
        }
        catch (Exception ex)
        {
            return ApiResult<TeamTrackerDto>.Fail("Unable to load tracker: " + ex.Message);
        }
    }

    public async Task<(byte[] Content, string FileName)?> ExportTrackerCsvAsync(int id, CancellationToken ct = default)
    {
        var result = await GetTrackerAsync(id, ct);
        if (!result.Success || result.Data == null) return null;

        var sb = new StringBuilder();
        sb.Append('\uFEFF');
        sb.AppendLine("Shop/Person Name,Contact Number,Approached For,Address,Date Visited,Final Status,Last Updated");
        foreach (var r in result.Data.Rows)
        {
            sb.AppendLine(string.Join(',', new[]
            {
                EscapeCsv(r.ShopName),
                EscapeCsv(r.ContactNumber),
                EscapeCsv(r.ApproachedFor),
                EscapeCsv(r.Address),
                EscapeCsv(r.DateVisitedDisplay),
                EscapeCsv(r.FinalStatus),
                EscapeCsv(r.LastUpdatedDisplay),
            }));
        }

        var bytes = Encoding.UTF8.GetBytes(sb.ToString());
        var safeName = Regex.Replace(result.Data.MemberName, @"[^a-zA-Z0-9_-]+", "_");
        return (bytes, $"tracker_{safeName}_{DateTime.Now:yyyyMMdd_HHmmss}.csv");
    }

    private async Task<ManageTeamRowDto> MapRowAsync(User u, CancellationToken ct)
    {
        var email = NormalizeEmail(u.Email);
        var ef = RequireEf();

        var sales = (await ef.Database
            .SqlQueryRaw<CountRow>(
                @"SELECT COUNT(*) AS Value
                  FROM referral_earnings re
                  LEFT JOIN digi_card dc ON CONVERT(dc.user_email USING utf8mb4) = CONVERT(re.referred_email USING utf8mb4)
                  WHERE CONVERT(re.referrer_email USING utf8mb4) = CONVERT({0} USING utf8mb4)
                    AND dc.d_payment_status = 'Success'
                    AND dc.d_payment_date IS NOT NULL
                    AND dc.d_payment_date != '0000-00-00 00:00:00'",
                email)
            .FirstAsync(ct)).Value;

        var trackerCount = (await ef.Database
            .SqlQueryRaw<CountRow>(
                "SELECT COUNT(*) AS Value FROM customer_tracker WHERE team_member_id = {0}",
                u.Id)
            .FirstAsync(ct)).Value;

        var referralEarningsCount = (await ef.Database
            .SqlQueryRaw<CountRow>(
                @"SELECT COUNT(*) AS Value FROM referral_earnings re
                  WHERE CONVERT(re.referrer_email USING utf8mb4) = CONVERT({0} USING utf8mb4)",
                email)
            .FirstAsync(ct)).Value;

        var referredByCount = (await ef.Database
            .SqlQueryRaw<CountRow>(
                @"SELECT COUNT(*) AS Value FROM user_details ud
                  WHERE CONVERT(ud.referred_by USING utf8mb4) = CONVERT({0} USING utf8mb4)
                    AND ud.referred_by != ''
                    AND ud.referred_by IS NOT NULL",
                email)
            .FirstAsync(ct)).Value;

        var referralCount = Math.Max(referralEarningsCount, referredByCount);

        var ownMwIds = await _db.DigiCards.AsNoTracking()
            .Where(c => c.UserEmail != null && c.UserEmail.ToLower() == email)
            .OrderByDescending(c => c.Id)
            .Select(c => c.Id)
            .ToListAsync(ct);

        var status = (u.Status ?? "ACTIVE").ToUpperInvariant();
        var tone = status == "ACTIVE" ? "ok" : "danger";

        return new ManageTeamRowDto(
            u.Id,
            u.LegacyTeamId,
            u.Name,
            u.Email,
            u.Phone ?? "",
            u.District ?? "",
            u.State ?? "",
            status,
            tone,
            FormatDate(u.CreatedAt),
            sales,
            ownMwIds.Count,
            referralCount,
            trackerCount,
            ownMwIds);
    }

    private async Task<User?> FindTeamAsync(int id, bool tracked, CancellationToken ct)
    {
        var q = tracked ? _db.Users : _db.Users.AsNoTracking();
        var byId = await q.FirstOrDefaultAsync(u => u.Id == id && u.Role == UserRole.Team, ct);
        if (byId != null) return byId;

        q = tracked ? _db.Users : _db.Users.AsNoTracking();
        return await q.FirstOrDefaultAsync(u => u.LegacyTeamId == id && u.Role == UserRole.Team, ct);
    }

    private static string? NormalizeStatusFilter(string? value)
    {
        if (string.IsNullOrWhiteSpace(value) || value.Equals("all", StringComparison.OrdinalIgnoreCase))
            return null;
        var v = value.Trim().ToUpperInvariant();
        return v is "ACTIVE" or "INACTIVE" ? v : null;
    }

    private static string NormalizeEmail(string? email) => (email ?? "").Trim().ToLowerInvariant();

    private static string RoleLabel(UserRole role) => role switch
    {
        UserRole.Franchisee => "Franchisee",
        UserRole.Team => "Team",
        UserRole.Admin => "Admin",
        _ => "Customer"
    };

    private static (string Label, string Tone) MapPaymentStatus(string? status, DateTime? paidOn)
    {
        if (string.IsNullOrWhiteSpace(status)) return ("N/A", "neutral");
        if (string.Equals(status, "Success", StringComparison.OrdinalIgnoreCase)
            && paidOn is { } d && d.Year > 1)
            return ("Paid", "ok");
        if (string.Equals(status, "Failed", StringComparison.OrdinalIgnoreCase))
            return ("Failed", "danger");
        if (status is "Created" or "Pending" or "")
            return ("Pending", "warn");
        return (status, "neutral");
    }

    private static string FormatPaidOn(string? status, DateTime? paidOn)
    {
        if (!string.Equals(status, "Success", StringComparison.OrdinalIgnoreCase)) return "-";
        return FormatDate(paidOn);
    }

    private static (string Tone, string _) MapTrackerStatus(string? status)
    {
        if (string.Equals(status, "Joined", StringComparison.OrdinalIgnoreCase)) return ("ok", status ?? "");
        if (string.Equals(status, "Not Interested", StringComparison.OrdinalIgnoreCase)) return ("danger", status ?? "");
        return ("warn", status ?? "");
    }

    private static string FormatDate(DateTime? value)
    {
        if (value is not { } d || d.Year < 2) return "—";
        return d.ToString("dd MMM yyyy", CultureInfo.InvariantCulture);
    }

    private static string FormatDateTime(DateTime? value)
    {
        if (value is not { } d || d.Year < 2) return "-";
        return d.ToString("dd-MM-yyyy HH:mm", CultureInfo.InvariantCulture);
    }

    private static string EscapeCsv(string? value)
    {
        var v = value ?? "";
        if (v.Contains('"') || v.Contains(',') || v.Contains('\n') || v.Contains('\r'))
            return "\"" + v.Replace("\"", "\"\"") + "\"";
        return v;
    }

    private DbContext RequireEf() =>
        _db as DbContext
        ?? throw new InvalidOperationException("IApplicationDbContext must be an EF DbContext.");

    private sealed class CountRow
    {
        public int Value { get; set; }
    }

    private sealed class ReferralSqlRow
    {
        public int? UserId { get; set; }
        public string? ReferredName { get; set; }
        public string? ReferredEmail { get; set; }
        public string? Phone { get; set; }
        public string? IsCollaboration { get; set; }
        public DateTime? ReferralDate { get; set; }
        public string? PaymentStatus { get; set; }
        public DateTime? PaymentDate { get; set; }
    }

    private sealed class TrackerSqlRow
    {
        public int Id { get; set; }
        public string? ShopName { get; set; }
        public string? ContactNumber { get; set; }
        public string? ApproachedFor { get; set; }
        public string? Address { get; set; }
        public DateTime? DateVisited { get; set; }
        public string? FinalStatus { get; set; }
        public DateTime? LastUpdated { get; set; }
    }
}
