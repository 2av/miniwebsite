using System.Globalization;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.Options;
using MiniWebsite.Application.Admin.KitManagement.Dtos;
using MiniWebsite.Application.Common.Interfaces;
using MiniWebsite.Application.Common.Models;
using MiniWebsite.Application.Common.Options;

namespace MiniWebsite.Application.Admin.KitManagement;

public class AdminKitManagementService : IAdminKitManagementService
{
    private static readonly string[] AllowedCategories = ["sales", "marketing", "franchise_sales"];

    private static readonly HashSet<string> ImageExtensions = new(StringComparer.OrdinalIgnoreCase)
    {
        "jpg", "jpeg", "png", "gif"
    };

    private static readonly HashSet<string> VideoExtensions = new(StringComparer.OrdinalIgnoreCase)
    {
        "mp4", "webm", "mov", "avi"
    };

    private static readonly HashSet<string> FileExtensions = new(StringComparer.OrdinalIgnoreCase)
    {
        "pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "txt", "zip", "rar",
        "mp4", "avi", "mov", "mp3", "wav"
    };

    private readonly IApplicationDbContext _db;
    private readonly AppOptions _app;

    public AdminKitManagementService(IApplicationDbContext db, IOptions<AppOptions> app)
    {
        _db = db;
        _app = app.Value;
    }

    public async Task<KitManagementMetaDto> GetMetaAsync(CancellationToken ct = default)
    {
        var ef = RequireEf();
        var counts = await ef.Database
            .SqlQueryRaw<CategoryCountRow>(
                "SELECT category AS Category, COUNT(*) AS ItemCount FROM franchisee_kit GROUP BY category")
            .ToListAsync(ct);

        var countMap = counts.ToDictionary(
            c => NormalizeCategory(c.Category),
            c => c.ItemCount,
            StringComparer.OrdinalIgnoreCase);

        var categories = AllowedCategories
            .Select(k => new KitCategoryMetaDto(k, CategoryLabel(k), countMap.GetValueOrDefault(k)))
            .ToList();

        return new KitManagementMetaDto(categories, BuildPublicPrefix());
    }

    public async Task<ApiResult<KitExplorerDto>> GetExplorerAsync(string category, int folderId, CancellationToken ct = default)
    {
        category = NormalizeCategory(category);
        if (!IsAllowedCategory(category))
            return ApiResult<KitExplorerDto>.Fail("Invalid kit category.");

        var ef = RequireEf();
        try
        {
            var folders = await LoadFoldersAsync(ef, category, ct);
            var foldersById = folders.ToDictionary(f => f.Id);

            if (folderId > 0 && !foldersById.ContainsKey(folderId))
                folderId = 0;

            var items = await LoadItemsAsync(ef, category, ct);
            var childrenMap = BuildChildrenMap(folders);
            var itemsByFolder = GroupItemsByFolder(items);

            var breadcrumb = BuildBreadcrumb(folderId, foldersById);
            var subfolders = (childrenMap.GetValueOrDefault(folderId) ?? [])
                .OrderBy(f => f.DisplayOrder)
                .ThenBy(f => f.Title, StringComparer.OrdinalIgnoreCase)
                .Select(f =>
                {
                    var fid = f.Id;
                    var directItems = itemsByFolder.GetValueOrDefault(fid)?.Count ?? 0;
                    var subCount = childrenMap.GetValueOrDefault(fid)?.Count ?? 0;
                    return new KitFolderTileDto(fid, f.Title, f.DisplayOrder, f.Status, directItems, subCount);
                })
                .ToList();

            var currentItems = folderId == 0
                ? items.Where(i => i.FolderId is null or 0).ToList()
                : itemsByFolder.GetValueOrDefault(folderId) ?? [];

            var folderOptions = BuildFolderOptions(childrenMap, folders);

            var stats = new KitStatsDto(
                folders.Count,
                items.Count(i => i.Type == "image"),
                items.Count(i => i.Type == "video"),
                items.Count(i => i.Type == "file"),
                items.Count(i => i.Status == "active"),
                items.Count);

            var dto = new KitExplorerDto(
                category,
                CategoryLabel(category),
                folderId,
                stats,
                breadcrumb,
                subfolders,
                currentItems
                    .OrderBy(i => i.DisplayOrder)
                    .ThenByDescending(i => i.CreatedAt ?? "")
                    .ToList(),
                folderOptions);

            return ApiResult<KitExplorerDto>.Ok(dto);
        }
        catch (Exception ex)
        {
            return ApiResult<KitExplorerDto>.Fail("Unable to load kit explorer: " + ex.Message);
        }
    }

    public async Task<ApiResult<KitFolderTileDto>> CreateFolderAsync(CreateKitFolderRequest request, CancellationToken ct = default)
    {
        var category = NormalizeCategory(request.Category);
        if (!IsAllowedCategory(category))
            return ApiResult<KitFolderTileDto>.Fail("Invalid kit category.");

        var title = (request.Title ?? "").Trim();
        if (title.Length == 0)
            return ApiResult<KitFolderTileDto>.Fail("Please enter a folder name.");

        var ef = RequireEf();
        var parentId = await ValidateParentFolderAsync(ef, request.ParentId, category, null, ct);
        var displayOrder = request.DisplayOrder < 0 ? 0 : request.DisplayOrder;

        try
        {
            await ef.Database.ExecuteSqlRawAsync(
                @"INSERT INTO franchisee_kit_folders (title, category, parent_id, display_order, status)
                  VALUES ({0}, {1}, {2}, {3}, 'active')",
                [title, category, parentId > 0 ? parentId : DBNull.Value, displayOrder],
                ct);

            var id = (await ef.Database.SqlQueryRaw<CountRow>("SELECT LAST_INSERT_ID() AS Value").FirstAsync(ct)).Value;
            return ApiResult<KitFolderTileDto>.Ok(new KitFolderTileDto(id, title, displayOrder, "active", 0, 0), "Folder created successfully.");
        }
        catch (Exception ex)
        {
            return ApiResult<KitFolderTileDto>.Fail("Error creating folder: " + ex.Message);
        }
    }

    public async Task<ApiResult<KitFolderTileDto>> UpdateFolderAsync(int id, UpdateKitFolderRequest request, CancellationToken ct = default)
    {
        if (id <= 0)
            return ApiResult<KitFolderTileDto>.Fail("Invalid folder.");

        var category = NormalizeCategory(request.Category);
        if (!IsAllowedCategory(category))
            return ApiResult<KitFolderTileDto>.Fail("Invalid kit category.");

        var title = (request.Title ?? "").Trim();
        if (title.Length == 0)
            return ApiResult<KitFolderTileDto>.Fail("Please enter a folder name.");

        var status = NormalizeStatus(request.Status);
        var displayOrder = request.DisplayOrder < 0 ? 0 : request.DisplayOrder;

        var ef = RequireEf();
        if (!await FolderExistsAsync(ef, id, category, ct))
            return ApiResult<KitFolderTileDto>.Fail("Folder not found.");

        var parentId = await ValidateParentFolderAsync(ef, request.ParentId, category, id, ct);

        try
        {
            await ef.Database.ExecuteSqlRawAsync(
                @"UPDATE franchisee_kit_folders
                  SET title = {0}, parent_id = {1}, display_order = {2}, status = {3}
                  WHERE id = {4} AND category = {5}",
                [title, parentId > 0 ? parentId : DBNull.Value, displayOrder, status, id, category],
                ct);

            var itemCount = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM franchisee_kit WHERE folder_id = {0}", id)
                .FirstAsync(ct)).Value;

            var subCount = (await ef.Database
                .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM franchisee_kit_folders WHERE parent_id = {0}", id)
                .FirstAsync(ct)).Value;

            return ApiResult<KitFolderTileDto>.Ok(
                new KitFolderTileDto(id, title, displayOrder, status, itemCount, subCount),
                "Folder updated successfully.");
        }
        catch (Exception ex)
        {
            return ApiResult<KitFolderTileDto>.Fail("Error updating folder: " + ex.Message);
        }
    }

    public async Task<ApiResult> DeleteFolderAsync(int id, string category, CancellationToken ct = default)
    {
        if (id <= 0)
            return ApiResult.Fail("Invalid folder.");

        category = NormalizeCategory(category);
        if (!IsAllowedCategory(category))
            return ApiResult.Fail("Invalid kit category.");

        var ef = RequireEf();
        if (!await FolderExistsAsync(ef, id, category, ct))
            return ApiResult.Fail("Folder not found.");

        try
        {
            await DeleteFolderRecursiveAsync(ef, id, ct);
            return ApiResult.Ok("Folder deleted successfully.");
        }
        catch (Exception ex)
        {
            return ApiResult.Fail("Error deleting folder: " + ex.Message);
        }
    }

    public Task<ApiResult<KitItemDto>> AddImageAsync(
        string category,
        string title,
        int? folderId,
        int displayOrder,
        Stream file,
        string fileName,
        string contentType,
        CancellationToken ct = default)
        => AddUploadedItemAsync(
            category,
            "image",
            title,
            folderId,
            displayOrder,
            file,
            fileName,
            contentType,
            ImageExtensions,
            10 * 1024 * 1024,
            "kit_image_",
            ct);

    public async Task<ApiResult<KitItemDto>> AddVideoUrlAsync(AddKitVideoUrlRequest request, CancellationToken ct = default)
    {
        var category = NormalizeCategory(request.Category);
        if (!IsAllowedCategory(category))
            return ApiResult<KitItemDto>.Fail("Invalid kit category.");

        var videoUrl = (request.VideoUrl ?? "").Trim();
        if (videoUrl.Length == 0)
            return ApiResult<KitItemDto>.Fail("Please enter a video URL.");

        var ef = RequireEf();
        var folderId = await ValidateFolderForCategoryAsync(ef, request.FolderId, category, ct);
        var title = (request.Title ?? "").Trim();
        var displayOrder = request.DisplayOrder < 0 ? 0 : request.DisplayOrder;

        try
        {
            await ef.Database.ExecuteSqlRawAsync(
                @"INSERT INTO franchisee_kit (type, title, video_url, display_order, status, category, folder_id)
                  VALUES ('video', {0}, {1}, {2}, 'active', {3}, {4})",
                [title, videoUrl, displayOrder, category, folderId > 0 ? folderId : DBNull.Value],
                ct);

            var id = (await ef.Database.SqlQueryRaw<CountRow>("SELECT LAST_INSERT_ID() AS Value").FirstAsync(ct)).Value;
            var item = await LoadItemAsync(ef, id, ct);
            return item == null
                ? ApiResult<KitItemDto>.Fail("Video saved but could not be loaded.")
                : ApiResult<KitItemDto>.Ok(item, "Video link added successfully.");
        }
        catch (Exception ex)
        {
            return ApiResult<KitItemDto>.Fail("Error saving video: " + ex.Message);
        }
    }

    public Task<ApiResult<KitItemDto>> AddVideoFileAsync(
        string category,
        string title,
        int? folderId,
        int displayOrder,
        Stream file,
        string fileName,
        string contentType,
        CancellationToken ct = default)
        => AddUploadedItemAsync(
            category,
            "video",
            title,
            folderId,
            displayOrder,
            file,
            fileName,
            contentType,
            VideoExtensions,
            50 * 1024 * 1024,
            "kit_video_",
            ct);

    public Task<ApiResult<KitItemDto>> AddFileAsync(
        string category,
        string title,
        int? folderId,
        int displayOrder,
        Stream file,
        string fileName,
        string contentType,
        CancellationToken ct = default)
        => AddUploadedItemAsync(
            category,
            "file",
            title,
            folderId,
            displayOrder,
            file,
            fileName,
            contentType,
            FileExtensions,
            10 * 1024 * 1024,
            "kit_file_",
            ct);

    public async Task<ApiResult<KitItemDto>> UpdateImageAsync(
        int id,
        UpdateKitItemMetaRequest meta,
        Stream? file,
        string? fileName,
        string? contentType,
        CancellationToken ct = default)
    {
        var existing = await RequireItemAsync(id, "image", meta.Category, ct);
        if (existing.Error != null) return existing.Error;

        var ef = RequireEf();
        var folderId = await ValidateFolderForCategoryAsync(ef, meta.FolderId, existing.Category!, ct);
        var title = (meta.Title ?? "").Trim();
        var displayOrder = meta.DisplayOrder < 0 ? 0 : meta.DisplayOrder;
        var status = NormalizeStatus(meta.Status);

        string? newStoredName = null;
        if (file != null && !string.IsNullOrWhiteSpace(fileName))
        {
            var upload = await SaveUploadAsync(file, fileName!, contentType ?? "", ImageExtensions, 10 * 1024 * 1024, "kit_image_", ct);
            if (!upload.Success) return ApiResult<KitItemDto>.Fail(upload.Message!);
            newStoredName = upload.StoredName;
            DeletePhysicalFile(existing.FilePath);
        }

        try
        {
            if (newStoredName != null)
            {
                await ef.Database.ExecuteSqlRawAsync(
                    @"UPDATE franchisee_kit
                      SET title = {0}, display_order = {1}, status = {2}, folder_id = {3}, file_path = {4}
                      WHERE id = {5}",
                    [title, displayOrder, status, folderId > 0 ? folderId : DBNull.Value, newStoredName, id],
                    ct);
            }
            else
            {
                await ef.Database.ExecuteSqlRawAsync(
                    @"UPDATE franchisee_kit
                      SET title = {0}, display_order = {1}, status = {2}, folder_id = {3}
                      WHERE id = {4}",
                    [title, displayOrder, status, folderId > 0 ? folderId : DBNull.Value, id],
                    ct);
            }

            var item = await LoadItemAsync(ef, id, ct);
            return item == null
                ? ApiResult<KitItemDto>.Fail("Image updated but could not be loaded.")
                : ApiResult<KitItemDto>.Ok(item, "Image updated successfully.");
        }
        catch (Exception ex)
        {
            if (newStoredName != null) DeletePhysicalFile(newStoredName);
            return ApiResult<KitItemDto>.Fail("Error updating image: " + ex.Message);
        }
    }

    public async Task<ApiResult<KitItemDto>> UpdateVideoAsync(int id, UpdateKitVideoRequest request, CancellationToken ct = default)
    {
        var category = NormalizeCategory(request.Category);
        var existing = await RequireItemAsync(id, "video", category, ct);
        if (existing.Error != null) return existing.Error;

        var ef = RequireEf();
        var folderId = await ValidateFolderForCategoryAsync(ef, request.FolderId, category, ct);
        var title = (request.Title ?? "").Trim();
        var displayOrder = request.DisplayOrder < 0 ? 0 : request.DisplayOrder;
        var status = NormalizeStatus(request.Status);
        var videoUrl = (request.VideoUrl ?? "").Trim();
        var hasFile = !string.IsNullOrWhiteSpace(existing.FilePath);

        if (videoUrl.Length == 0 && !hasFile)
            return ApiResult<KitItemDto>.Fail("Please enter a video URL or keep the uploaded video file.");

        try
        {
            if (videoUrl.Length > 0)
            {
                await ef.Database.ExecuteSqlRawAsync(
                    @"UPDATE franchisee_kit
                      SET title = {0}, display_order = {1}, status = {2}, folder_id = {3}, video_url = {4}
                      WHERE id = {5}",
                    [title, displayOrder, status, folderId > 0 ? folderId : DBNull.Value, videoUrl, id],
                    ct);
            }
            else
            {
                await ef.Database.ExecuteSqlRawAsync(
                    @"UPDATE franchisee_kit
                      SET title = {0}, display_order = {1}, status = {2}, folder_id = {3}
                      WHERE id = {4}",
                    [title, displayOrder, status, folderId > 0 ? folderId : DBNull.Value, id],
                    ct);
            }

            var item = await LoadItemAsync(ef, id, ct);
            return item == null
                ? ApiResult<KitItemDto>.Fail("Video updated but could not be loaded.")
                : ApiResult<KitItemDto>.Ok(item, "Video updated successfully.");
        }
        catch (Exception ex)
        {
            return ApiResult<KitItemDto>.Fail("Error updating video: " + ex.Message);
        }
    }

    public async Task<ApiResult<KitItemDto>> UpdateFileAsync(
        int id,
        UpdateKitItemMetaRequest meta,
        Stream? file,
        string? fileName,
        string? contentType,
        CancellationToken ct = default)
    {
        var existing = await RequireItemAsync(id, "file", meta.Category, ct);
        if (existing.Error != null) return existing.Error;

        var ef = RequireEf();
        var folderId = await ValidateFolderForCategoryAsync(ef, meta.FolderId, existing.Category!, ct);
        var title = (meta.Title ?? "").Trim();
        var displayOrder = meta.DisplayOrder < 0 ? 0 : meta.DisplayOrder;
        var status = NormalizeStatus(meta.Status);

        string? newStoredName = null;
        if (file != null && !string.IsNullOrWhiteSpace(fileName))
        {
            var upload = await SaveUploadAsync(file, fileName!, contentType ?? "", FileExtensions, 10 * 1024 * 1024, "kit_file_", ct);
            if (!upload.Success) return ApiResult<KitItemDto>.Fail(upload.Message!);
            newStoredName = upload.StoredName;
            DeletePhysicalFile(existing.FilePath);
        }

        try
        {
            if (newStoredName != null)
            {
                await ef.Database.ExecuteSqlRawAsync(
                    @"UPDATE franchisee_kit
                      SET title = {0}, display_order = {1}, status = {2}, folder_id = {3}, file_path = {4}
                      WHERE id = {5}",
                    [title, displayOrder, status, folderId > 0 ? folderId : DBNull.Value, newStoredName, id],
                    ct);
            }
            else
            {
                await ef.Database.ExecuteSqlRawAsync(
                    @"UPDATE franchisee_kit
                      SET title = {0}, display_order = {1}, status = {2}, folder_id = {3}
                      WHERE id = {4}",
                    [title, displayOrder, status, folderId > 0 ? folderId : DBNull.Value, id],
                    ct);
            }

            var item = await LoadItemAsync(ef, id, ct);
            return item == null
                ? ApiResult<KitItemDto>.Fail("File updated but could not be loaded.")
                : ApiResult<KitItemDto>.Ok(item, "File updated successfully.");
        }
        catch (Exception ex)
        {
            if (newStoredName != null) DeletePhysicalFile(newStoredName);
            return ApiResult<KitItemDto>.Fail("Error updating file: " + ex.Message);
        }
    }

    public async Task<ApiResult> UpdateItemStatusAsync(int id, UpdateKitItemStatusRequest request, CancellationToken ct = default)
    {
        if (id <= 0) return ApiResult.Fail("Invalid item.");
        var status = NormalizeStatus(request.Status);
        var ef = RequireEf();

        var exists = (await ef.Database
            .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM franchisee_kit WHERE id = {0}", id)
            .FirstAsync(ct)).Value;
        if (exists == 0) return ApiResult.Fail("Item not found.");

        await ef.Database.ExecuteSqlRawAsync(
            "UPDATE franchisee_kit SET status = {0} WHERE id = {1}",
            [status, id],
            ct);
        return ApiResult.Ok("Status updated successfully.");
    }

    public async Task<ApiResult> MoveItemAsync(int id, MoveKitItemRequest request, CancellationToken ct = default)
    {
        if (id <= 0) return ApiResult.Fail("Invalid item.");
        var category = NormalizeCategory(request.Category);
        if (!IsAllowedCategory(category))
            return ApiResult.Fail("Invalid kit category.");

        var ef = RequireEf();
        var folderId = await ValidateFolderForCategoryAsync(ef, request.FolderId, category, ct);

        var exists = (await ef.Database
            .SqlQueryRaw<CountRow>("SELECT COUNT(*) AS Value FROM franchisee_kit WHERE id = {0} AND category = {1}", id, category)
            .FirstAsync(ct)).Value;
        if (exists == 0) return ApiResult.Fail("Item not found.");

        await ef.Database.ExecuteSqlRawAsync(
            "UPDATE franchisee_kit SET folder_id = {0} WHERE id = {1}",
            [folderId > 0 ? folderId : DBNull.Value, id],
            ct);

        var msg = folderId > 0 ? "Item moved to folder successfully." : "Item removed from folder (uncategorized).";
        return ApiResult.Ok(msg);
    }

    public async Task<ApiResult> DeleteItemAsync(int id, CancellationToken ct = default)
    {
        if (id <= 0) return ApiResult.Fail("Invalid item.");
        var ef = RequireEf();

        var row = await ef.Database
            .SqlQueryRaw<ItemFileRow>(
                "SELECT id AS Id, type AS Type, IFNULL(file_path,'') AS FilePath FROM franchisee_kit WHERE id = {0}",
                id)
            .FirstOrDefaultAsync(ct);
        if (row == null) return ApiResult.Fail("Item not found.");

        if ((row.Type == "image" || row.Type == "file" || row.Type == "video") && !string.IsNullOrWhiteSpace(row.FilePath))
            DeletePhysicalFile(row.FilePath);

        await ef.Database.ExecuteSqlRawAsync("DELETE FROM franchisee_kit WHERE id = {0}", [id], ct);
        return ApiResult.Ok("Item deleted successfully.");
    }

    private async Task<ApiResult<KitItemDto>> AddUploadedItemAsync(
        string category,
        string type,
        string title,
        int? folderId,
        int displayOrder,
        Stream file,
        string fileName,
        string contentType,
        HashSet<string> allowedExtensions,
        long maxBytes,
        string namePrefix,
        CancellationToken ct)
    {
        category = NormalizeCategory(category);
        if (!IsAllowedCategory(category))
            return ApiResult<KitItemDto>.Fail("Invalid kit category.");

        var ef = RequireEf();
        var validFolderId = await ValidateFolderForCategoryAsync(ef, folderId, category, ct);
        var upload = await SaveUploadAsync(file, fileName, contentType, allowedExtensions, maxBytes, namePrefix, ct);
        if (!upload.Success)
            return ApiResult<KitItemDto>.Fail(upload.Message!);

        title = (title ?? "").Trim();
        displayOrder = displayOrder < 0 ? 0 : displayOrder;

        try
        {
            await ef.Database.ExecuteSqlRawAsync(
                @"INSERT INTO franchisee_kit (type, title, file_path, display_order, status, category, folder_id)
                  VALUES ({0}, {1}, {2}, {3}, 'active', {4}, {5})",
                [type, title, upload.StoredName, displayOrder, category, validFolderId > 0 ? validFolderId : DBNull.Value],
                ct);

            var id = (await ef.Database.SqlQueryRaw<CountRow>("SELECT LAST_INSERT_ID() AS Value").FirstAsync(ct)).Value;
            var item = await LoadItemAsync(ef, id, ct);
            var msg = type switch
            {
                "image" => "Image uploaded successfully.",
                "video" => "Video uploaded successfully.",
                _ => "File uploaded successfully."
            };
            return item == null
                ? ApiResult<KitItemDto>.Fail("Saved but could not be loaded.")
                : ApiResult<KitItemDto>.Ok(item, msg);
        }
        catch (Exception ex)
        {
            DeletePhysicalFile(upload.StoredName);
            return ApiResult<KitItemDto>.Fail("Error saving item: " + ex.Message);
        }
    }

    private async Task<(string? Category, string? FilePath, ApiResult<KitItemDto>? Error)> RequireItemAsync(
        int id,
        string expectedType,
        string category,
        CancellationToken ct)
    {
        category = NormalizeCategory(category);
        var ef = RequireEf();
        var row = await ef.Database
            .SqlQueryRaw<ItemFileRow>(
                @"SELECT id AS Id, type AS Type, IFNULL(file_path,'') AS FilePath
                  FROM franchisee_kit WHERE id = {0} AND category = {1}",
                id, category)
            .FirstOrDefaultAsync(ct);

        if (row == null)
            return (null, null, ApiResult<KitItemDto>.Fail("Item not found."));
        if (!string.Equals(row.Type, expectedType, StringComparison.OrdinalIgnoreCase))
            return (null, null, ApiResult<KitItemDto>.Fail("Item type mismatch."));

        return (category, string.IsNullOrWhiteSpace(row.FilePath) ? null : row.FilePath, null);
    }

    private async Task DeleteFolderRecursiveAsync(DbContext ef, int folderId, CancellationToken ct)
    {
        var children = await ef.Database
            .SqlQueryRaw<CountRow>("SELECT id AS Value FROM franchisee_kit_folders WHERE parent_id = {0}", folderId)
            .ToListAsync(ct);

        foreach (var child in children)
            await DeleteFolderRecursiveAsync(ef, child.Value, ct);

        var items = await ef.Database
            .SqlQueryRaw<ItemFileRow>(
                "SELECT id AS Id, type AS Type, IFNULL(file_path,'') AS FilePath FROM franchisee_kit WHERE folder_id = {0}",
                folderId)
            .ToListAsync(ct);

        foreach (var item in items)
        {
            if (!string.IsNullOrWhiteSpace(item.FilePath))
                DeletePhysicalFile(item.FilePath);
            await ef.Database.ExecuteSqlRawAsync("DELETE FROM franchisee_kit WHERE id = {0}", [item.Id], ct);
        }

        await ef.Database.ExecuteSqlRawAsync("DELETE FROM franchisee_kit_folders WHERE id = {0}", [folderId], ct);
    }

    private async Task<(bool Success, string? StoredName, string? Message)> SaveUploadAsync(
        Stream file,
        string fileName,
        string contentType,
        HashSet<string> allowedExtensions,
        long maxBytes,
        string namePrefix,
        CancellationToken ct)
    {
        await using var ms = new MemoryStream();
        await file.CopyToAsync(ms, ct);
        if (ms.Length == 0)
            return (false, null, "Empty file.");
        if (ms.Length > maxBytes)
            return (false, null, $"File size too large. Maximum allowed size is {FormatBytes(maxBytes)}.");

        var ext = Path.GetExtension(fileName).TrimStart('.').ToLowerInvariant();
        if (string.IsNullOrWhiteSpace(ext) && !string.IsNullOrWhiteSpace(contentType))
        {
            ext = contentType.ToLowerInvariant() switch
            {
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/gif" => "gif",
                "video/mp4" => "mp4",
                "video/webm" => "webm",
                "video/quicktime" => "mov",
                "video/x-msvideo" => "avi",
                "application/pdf" => "pdf",
                _ => ext
            };
        }

        if (!allowedExtensions.Contains(ext))
            return (false, null, "Invalid file type.");

        var dir = ResolveUploadDir();
        if (string.IsNullOrWhiteSpace(dir))
            return (false, null, "Kit upload path is not configured (App:KitsUploadsFsPath).");

        try
        {
            Directory.CreateDirectory(dir);
            var stored = $"{namePrefix}{DateTimeOffset.UtcNow.ToUnixTimeSeconds()}_{Random.Shared.Next(1000, 9999)}.{ext}";
            var dest = Path.Combine(dir, stored);
            await File.WriteAllBytesAsync(dest, ms.ToArray(), ct);
            return (true, stored, null);
        }
        catch (Exception ex)
        {
            return (false, null, "Upload failed: " + ex.Message);
        }
    }

    private void DeletePhysicalFile(string? fileName)
    {
        if (string.IsNullOrWhiteSpace(fileName)) return;
        var dir = ResolveUploadDir();
        if (string.IsNullOrWhiteSpace(dir)) return;
        var path = Path.Combine(dir, Path.GetFileName(fileName.Replace('\\', '/')));
        if (File.Exists(path))
        {
            try { File.Delete(path); } catch { /* best effort */ }
        }
    }

    private async Task<List<FolderSqlRow>> LoadFoldersAsync(DbContext ef, string category, CancellationToken ct)
        => await ef.Database
            .SqlQueryRaw<FolderSqlRow>(
                @"SELECT f.id AS Id,
                         IFNULL(f.title,'') AS Title,
                         f.category AS Category,
                         f.parent_id AS ParentId,
                         CAST(IFNULL(f.display_order,0) AS SIGNED) AS DisplayOrder,
                         IFNULL(f.status,'active') AS Status
                  FROM franchisee_kit_folders f
                  WHERE f.category = {0}
                  ORDER BY f.display_order ASC, f.title ASC",
                category)
            .ToListAsync(ct);

    private async Task<List<KitItemDto>> LoadItemsAsync(DbContext ef, string category, CancellationToken ct)
    {
        var rows = await ef.Database
            .SqlQueryRaw<ItemSqlRow>(
                @"SELECT k.id AS Id,
                         IFNULL(k.type,'') AS Type,
                         IFNULL(k.title,'') AS Title,
                         k.file_path AS FilePath,
                         k.video_url AS VideoUrl,
                         CAST(IFNULL(k.display_order,0) AS SIGNED) AS DisplayOrder,
                         IFNULL(k.status,'active') AS Status,
                         k.folder_id AS FolderId,
                         k.created_at AS CreatedAt
                  FROM franchisee_kit k
                  WHERE k.category = {0}
                  ORDER BY k.display_order ASC, k.created_at DESC",
                category)
            .ToListAsync(ct);

        return rows.Select(MapItem).ToList();
    }

    private async Task<KitItemDto?> LoadItemAsync(DbContext ef, int id, CancellationToken ct)
    {
        var row = await ef.Database
            .SqlQueryRaw<ItemSqlRow>(
                @"SELECT k.id AS Id,
                         IFNULL(k.type,'') AS Type,
                         IFNULL(k.title,'') AS Title,
                         k.file_path AS FilePath,
                         k.video_url AS VideoUrl,
                         CAST(IFNULL(k.display_order,0) AS SIGNED) AS DisplayOrder,
                         IFNULL(k.status,'active') AS Status,
                         k.folder_id AS FolderId,
                         k.created_at AS CreatedAt
                  FROM franchisee_kit k
                  WHERE k.id = {0}",
                id)
            .FirstOrDefaultAsync(ct);
        return row == null ? null : MapItem(row);
    }

    private KitItemDto MapItem(ItemSqlRow row)
    {
        var filePath = string.IsNullOrWhiteSpace(row.FilePath) ? null : row.FilePath.Trim();
        var fileUrl = filePath == null ? null : BuildFileUrl(filePath);
        return new KitItemDto(
            row.Id,
            row.Type ?? "",
            row.Title ?? "",
            filePath,
            fileUrl,
            string.IsNullOrWhiteSpace(row.VideoUrl) ? null : row.VideoUrl.Trim(),
            row.DisplayOrder,
            row.Status ?? "active",
            row.FolderId,
            FormatDateTime(row.CreatedAt));
    }

    private static Dictionary<int, List<FolderSqlRow>> BuildChildrenMap(IReadOnlyList<FolderSqlRow> folders)
    {
        var map = new Dictionary<int, List<FolderSqlRow>>();
        foreach (var folder in folders)
        {
            var parentId = folder.ParentId is > 0 ? folder.ParentId.Value : 0;
            if (!map.TryGetValue(parentId, out var list))
            {
                list = [];
                map[parentId] = list;
            }
            list.Add(folder);
        }
        return map;
    }

    private static Dictionary<int, List<KitItemDto>> GroupItemsByFolder(IReadOnlyList<KitItemDto> items)
    {
        var map = new Dictionary<int, List<KitItemDto>>();
        foreach (var item in items)
        {
            if (item.FolderId is not > 0) continue;
            if (!map.TryGetValue(item.FolderId.Value, out var list))
            {
                list = [];
                map[item.FolderId.Value] = list;
            }
            list.Add(item);
        }
        return map;
    }

    private static List<KitBreadcrumbDto> BuildBreadcrumb(int folderId, IReadOnlyDictionary<int, FolderSqlRow> foldersById)
    {
        var crumbs = new List<KitBreadcrumbDto>();
        var current = folderId;
        var guard = 0;
        while (current > 0 && foldersById.TryGetValue(current, out var folder) && guard < 50)
        {
            crumbs.Add(new KitBreadcrumbDto(folder.Id, folder.Title));
            current = folder.ParentId is > 0 ? folder.ParentId.Value : 0;
            guard++;
        }
        crumbs.Reverse();
        return crumbs;
    }

    private static List<KitFolderOptionDto> BuildFolderOptions(
        IReadOnlyDictionary<int, List<FolderSqlRow>> childrenMap,
        IReadOnlyList<FolderSqlRow> folders)
    {
        var options = new List<KitFolderOptionDto>();
        void Walk(int parentId, int depth, int? excludeId)
        {
            if (!childrenMap.TryGetValue(parentId, out var children)) return;
            foreach (var folder in children.OrderBy(f => f.DisplayOrder).ThenBy(f => f.Title, StringComparer.OrdinalIgnoreCase))
            {
                if (excludeId.HasValue && folder.Id == excludeId.Value) continue;
                options.Add(new KitFolderOptionDto(folder.Id, folder.Title, depth, folder.ParentId));
                Walk(folder.Id, depth + 1, excludeId);
            }
        }

        Walk(0, 0, null);
        return options;
    }

    private async Task<int> ValidateFolderForCategoryAsync(DbContext ef, int? folderId, string category, CancellationToken ct)
    {
        if (folderId is not > 0) return 0;
        return await FolderExistsAsync(ef, folderId.Value, category, ct) ? folderId.Value : 0;
    }

    private async Task<int> ValidateParentFolderAsync(
        DbContext ef,
        int? parentId,
        string category,
        int? excludeFolderId,
        CancellationToken ct)
    {
        if (parentId is not > 0) return 0;
        if (excludeFolderId.HasValue && parentId.Value == excludeFolderId.Value) return 0;
        if (!await FolderExistsAsync(ef, parentId.Value, category, ct)) return 0;

        if (excludeFolderId is > 0)
        {
            var descendants = await GetFolderDescendantIdsAsync(ef, excludeFolderId.Value, ct);
            if (descendants.Contains(parentId.Value)) return 0;
        }

        return parentId.Value;
    }

    private async Task<HashSet<int>> GetFolderDescendantIdsAsync(DbContext ef, int folderId, CancellationToken ct)
    {
        var ids = new HashSet<int>();
        var queue = new Queue<int>();
        queue.Enqueue(folderId);
        var guard = 0;
        while (queue.Count > 0 && guard < 500)
        {
            guard++;
            var current = queue.Dequeue();
            var children = await ef.Database
                .SqlQueryRaw<CountRow>("SELECT id AS Value FROM franchisee_kit_folders WHERE parent_id = {0}", current)
                .ToListAsync(ct);
            foreach (var child in children)
            {
                if (ids.Add(child.Value))
                    queue.Enqueue(child.Value);
            }
        }
        return ids;
    }

    private static async Task<bool> FolderExistsAsync(DbContext ef, int folderId, string category, CancellationToken ct)
    {
        var count = (await ef.Database
            .SqlQueryRaw<CountRow>(
                "SELECT COUNT(*) AS Value FROM franchisee_kit_folders WHERE id = {0} AND category = {1}",
                folderId, category)
            .FirstAsync(ct)).Value;
        return count > 0;
    }

    private string BuildFileUrl(string fileName)
    {
        var prefix = BuildPublicPrefix().TrimEnd('/');
        return $"{prefix}/{fileName.TrimStart('/')}";
    }

    private string BuildPublicPrefix()
    {
        var path = string.IsNullOrWhiteSpace(_app.KitsUploadsPublicPath)
            ? "/assets/upload/kits"
            : _app.KitsUploadsPublicPath.Trim();
        if (path.StartsWith("http://", StringComparison.OrdinalIgnoreCase)
            || path.StartsWith("https://", StringComparison.OrdinalIgnoreCase))
            return path.TrimEnd('/');

        var baseUrl = (_app.PhpSiteBaseUrl ?? "").TrimEnd('/');
        return $"{baseUrl}/{path.TrimStart('/')}".TrimEnd('/');
    }

    private string? ResolveUploadDir()
    {
        if (!string.IsNullOrWhiteSpace(_app.KitsUploadsFsPath))
            return _app.KitsUploadsFsPath.Trim();
        return null;
    }

    private static string NormalizeCategory(string? category)
        => (category ?? "").Trim().ToLowerInvariant();

    private static bool IsAllowedCategory(string category)
        => AllowedCategories.Contains(category, StringComparer.OrdinalIgnoreCase);

    private static string CategoryLabel(string key) => key switch
    {
        "sales" => "MW Sales Kit",
        "marketing" => "Creator Kit",
        "franchise_sales" => "Franchise Sales Kit",
        _ => key
    };

    private static string NormalizeStatus(string? status)
        => string.Equals(status, "inactive", StringComparison.OrdinalIgnoreCase) ? "inactive" : "active";

    private static string? FormatDateTime(DateTime? dt)
        => dt.HasValue
            ? dt.Value.ToString("dd MMM yyyy, hh:mm tt", CultureInfo.InvariantCulture)
            : null;

    private static string FormatBytes(long bytes)
    {
        if (bytes >= 1_073_741_824) return $"{bytes / 1_073_741_824d:0.##} GB";
        if (bytes >= 1_048_576) return $"{bytes / 1_048_576d:0.##} MB";
        if (bytes >= 1024) return $"{bytes / 1024d:0.##} KB";
        return $"{bytes} bytes";
    }

    private DbContext RequireEf() =>
        _db as DbContext ?? throw new InvalidOperationException("Database context unavailable.");

    private sealed class CountRow { public int Value { get; set; } }

    private sealed class CategoryCountRow
    {
        public string? Category { get; set; }
        public int ItemCount { get; set; }
    }

    private sealed class FolderSqlRow
    {
        public int Id { get; set; }
        public string Title { get; set; } = "";
        public string? Category { get; set; }
        public int? ParentId { get; set; }
        public int DisplayOrder { get; set; }
        public string Status { get; set; } = "active";
    }

    private sealed class ItemSqlRow
    {
        public int Id { get; set; }
        public string? Type { get; set; }
        public string? Title { get; set; }
        public string? FilePath { get; set; }
        public string? VideoUrl { get; set; }
        public int DisplayOrder { get; set; }
        public string? Status { get; set; }
        public int? FolderId { get; set; }
        public DateTime? CreatedAt { get; set; }
    }

    private sealed class ItemFileRow
    {
        public int Id { get; set; }
        public string? Type { get; set; }
        public string? FilePath { get; set; }
    }
}
