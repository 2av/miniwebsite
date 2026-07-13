<?php
// Start session and include database connection first
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/access_control.php');
require_once(__DIR__ . '/../../app/helpers/role_helper.php');
require_once(__DIR__ . '/../../app/helpers/verification_helper.php');

// Franchisee must complete the franchise registration payment before accessing the kit.
$franchisee_email = $_SESSION['f_user_email'] ?? '';
if (function_exists('get_current_user_role') && get_current_user_role() === 'FRANCHISEE' && !isFranchiseeRegistrationAgreementPaid($franchisee_email)) {
    redirectFranchiseeToAgreementUntilPaid($franchisee_email);
}

// Check page access - redirects to dashboard if unauthorized
require_page_access('/kit');

// Now include the header
include '../includes/header.php';

// Determine kit category based on role / collaboration
// - FRANCHISEE  → Marketing Kit
// - TEAM (sales team) → Sales Kit
// - CUSTOMER   → based on collaboration flag (YES = marketing, NO = sales)
$current_role = get_current_user_role();

if ($current_role === 'FRANCHISEE') {
    $kit_category = 'marketing';
} elseif ($current_role === 'TEAM') {
    // TEAM can view both Sales Kit and Franchisee Sales Kit via menu; kit param: franchise_sales = marketing
    $get_kit = isset($_GET['kit']) ? trim($_GET['kit']) : '';
    $kit_category = ($get_kit === 'franchise_sales') ? 'marketing' : 'sales';
} else {
    // `$collaboration_enabled` is defined in header.php (for customers)
    // Collaboration customers (influencers) can view both Creator Kit (marketing)
    // and MW Sales Kit (sales) via menu; kit param: mw_sales = sales.
    $get_kit = isset($_GET['kit']) ? trim($_GET['kit']) : '';
    if ($collaboration_enabled ?? false) {
        $kit_category = ($get_kit === 'mw_sales') ? 'sales' : 'marketing';
    } else {
        $kit_category = 'sales';
    }
}

// Get all active kit items for the selected category
$kit_items_query = "SELECT * FROM franchisee_kit WHERE status = 'active' AND category='" . mysqli_real_escape_string($connect, $kit_category) . "' ORDER BY display_order ASC, created_at DESC";
$kit_items_result = mysqli_query($connect, $kit_items_query);
$kit_items = [];
if ($kit_items_result) {
    while ($row = mysqli_fetch_assoc($kit_items_result)) {
        $kit_items[] = $row;
    }
}

// Get active folders for this category
$kit_folders = [];
$folders_query = "SELECT * FROM franchisee_kit_folders WHERE status = 'active' AND category='" . mysqli_real_escape_string($connect, $kit_category) . "' ORDER BY display_order ASC, title ASC";
$folders_result = mysqli_query($connect, $folders_query);
if ($folders_result) {
    while ($row = mysqli_fetch_assoc($folders_result)) {
        $kit_folders[] = $row;
    }
}

$items_by_folder = [];
$uncategorized_items = [];
foreach ($kit_items as $item) {
    if (!empty($item['folder_id'])) {
        $fid = (int)$item['folder_id'];
        if (!isset($items_by_folder[$fid])) {
            $items_by_folder[$fid] = [];
        }
        $items_by_folder[$fid][] = $item;
    } else {
        $uncategorized_items[] = $item;
    }
}

// Separate uncategorized by type
$images = array_filter($uncategorized_items, function($item) {
    return $item['type'] == 'image';
});

$videos = array_filter($uncategorized_items, function($item) {
    return $item['type'] == 'video';
});

$files = array_filter($uncategorized_items, function($item) {
    return $item['type'] == 'file';
});

// Helper for label
function kitLabelCustomer($category) {
    if ($category === 'marketing') return 'Marketing Kit';
    if ($category === 'sales') return 'Sales Kit';
    return 'Kit';
}

function kitUserBuildFolderChildrenMap($folders) {
    $children = [];
    foreach ($folders as $folder) {
        $parent_id = !empty($folder['parent_id']) ? (int)$folder['parent_id'] : 0;
        if (!isset($children[$parent_id])) {
            $children[$parent_id] = [];
        }
        $children[$parent_id][] = $folder;
    }
    return $children;
}

function kitUserFolderHasContent($folder_id, $children_map, $items_by_folder) {
    $folder_id = (int)$folder_id;
    if (!empty($items_by_folder[$folder_id])) {
        return true;
    }
    if (!isset($children_map[$folder_id])) {
        return false;
    }
    foreach ($children_map[$folder_id] as $child) {
        if (kitUserFolderHasContent((int)$child['id'], $children_map, $items_by_folder)) {
            return true;
        }
    }
    return false;
}

function kitUserExplorerUrl($folder_id, $kit_category, $current_role) {
    $params = [];
    if ($current_role === 'TEAM' && $kit_category === 'marketing') {
        $params['kit'] = 'franchise_sales';
    } elseif ($current_role !== 'TEAM' && $current_role !== 'FRANCHISEE' && $kit_category === 'sales') {
        // Collaboration customer (influencer) viewing MW Sales Kit
        $params['kit'] = 'mw_sales';
    }
    if ((int) $folder_id > 0) {
        $params['folder'] = (int) $folder_id;
    }
    return 'index.php' . ($params ? ('?' . http_build_query($params)) : '');
}

function kitUserFoldersById($folders) {
    $map = [];
    foreach ($folders as $folder) {
        $map[(int) $folder['id']] = $folder;
    }
    return $map;
}

function kitUserCountFolderItems($folder_id, $items_by_folder, $children_map) {
    $folder_id = (int) $folder_id;
    $count = isset($items_by_folder[$folder_id]) ? count($items_by_folder[$folder_id]) : 0;
    if (!empty($children_map[$folder_id])) {
        foreach ($children_map[$folder_id] as $child) {
            $count += kitUserCountFolderItems((int) $child['id'], $items_by_folder, $children_map);
        }
    }
    return $count;
}

function kitUserFolderBreadcrumb($folder_id, $folders_by_id) {
    $crumbs = [];
    $current = (int) $folder_id;
    $guard = 0;
    while ($current > 0 && isset($folders_by_id[$current]) && $guard < 50) {
        $crumbs[] = $folders_by_id[$current];
        $current = !empty($folders_by_id[$current]['parent_id']) ? (int) $folders_by_id[$current]['parent_id'] : 0;
        $guard++;
    }
    return array_reverse($crumbs);
}

function kitUserSplitItemsByType(array $items) {
    return [
        'images' => array_values(array_filter($items, function ($item) { return ($item['type'] ?? '') === 'image'; })),
        'videos' => array_values(array_filter($items, function ($item) { return ($item['type'] ?? '') === 'video'; })),
        'files'  => array_values(array_filter($items, function ($item) { return ($item['type'] ?? '') === 'file'; })),
    ];
}

function kitUserFileIconClass($file_path) {
    $ext = strtolower(pathinfo((string) $file_path, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf': return 'fa-file-pdf';
        case 'doc':
        case 'docx': return 'fa-file-word';
        case 'xls':
        case 'xlsx': return 'fa-file-excel';
        case 'ppt':
        case 'pptx': return 'fa-file-powerpoint';
        case 'zip':
        case 'rar': return 'fa-file-archive';
        case 'txt': return 'fa-file-alt';
        case 'mp4':
        case 'avi':
        case 'mov': return 'fa-file-video';
        case 'mp3':
        case 'wav': return 'fa-file-audio';
        default: return 'fa-file';
    }
}

function kitUserItemForJson(array $item) {
    return [
        'type' => (string) ($item['type'] ?? ''),
        'title' => (string) ($item['title'] ?? ''),
        'file_path' => (string) ($item['file_path'] ?? ''),
        'video_url' => (string) ($item['video_url'] ?? ''),
    ];
}

function renderKitUserFolderGrid(array $folders, $items_by_folder, $children_map, $kit_category, $current_role) {
    if (empty($folders)) {
        return;
    }
    ?>
    <div class="kit-explorer-folders row g-3 mb-4">
        <?php foreach ($folders as $folder):
            $fid = (int) $folder['id'];
            $item_count = kitUserCountFolderItems($fid, $items_by_folder, $children_map);
            $url = kitUserExplorerUrl($fid, $kit_category, $current_role);
        ?>
        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
            <div class="kit-folder-tile"
                 role="button"
                 tabindex="0"
                 data-kit-folder-url="<?php echo htmlspecialchars($url); ?>"
                 title="<?php echo htmlspecialchars($folder['title']); ?>">
                <span class="kit-folder-icon" aria-hidden="true"><i class="fa fa-folder"></i></span>
                <span class="kit-folder-name"><?php echo htmlspecialchars($folder['title']); ?></span>
                <span class="kit-folder-count"><?php echo (int) $item_count; ?> <?php echo $item_count === 1 ? 'item' : 'items'; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
}

function renderKitUserImagesSection(array $images) {
    if (empty($images)) return;
    ?>
    <div class="row mb-5">
        <div class="col-12">
            <h4 class="mb-3 font22">Promotional Images</h4>
            <div class="row">
                <?php foreach ($images as $image): ?>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div>
                        <img src="../../assets/upload/kits/<?php echo htmlspecialchars($image['file_path']); ?>"
                             class="card-img-top" style="height: 200px; object-fit: cover;"
                             alt="<?php echo htmlspecialchars($image['title'] ?: 'Promotional Image'); ?>">
                        <div class="mt-auto d-flex" style="padding: 10px; justify-content: space-between;">
                            <h6 class="card-title mb-0 bottom_title"><?php echo htmlspecialchars($image['title'] ?: 'Promotional Image'); ?></h6>
                            <a href="../../assets/upload/kits/<?php echo htmlspecialchars($image['file_path']); ?>"
                               download="<?php echo htmlspecialchars($image['title'] ?: 'promotional_image'); ?>"
                               title="Download"><i class="fa fa-download"></i></a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

function renderKitUserVideosSection(array $videos) {
    if (empty($videos)) return;
    ?>
    <div class="row mb-5">
        <div class="col-12">
            <h4 class="heading">Videos</h4>
            <div class="row">
                <?php foreach ($videos as $video): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body p-2 video_section">
                            <?php if (!empty($video['file_path'])): ?>
                                <video controls style="width:100%;border-radius:8px;">
                                    <source src="../../assets/upload/kits/<?php echo htmlspecialchars($video['file_path']); ?>">
                                </video>
                            <?php elseif (strpos($video['video_url'], 'youtube') !== false || strpos($video['video_url'], 'youtu.be') !== false): ?>
                                <?php preg_match('/(youtu\.be\/|v=)([^&]+)/', $video['video_url'], $matches); $videoId = $matches[2] ?? ''; ?>
                                <div class="ratio ratio-16x9">
                                    <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($videoId); ?>" allowfullscreen></iframe>
                                </div>
                            <?php elseif (strpos($video['video_url'], 'instagram.com') !== false): ?>
                                <blockquote class="instagram-media"
                                    data-instgrm-permalink="<?php echo htmlspecialchars($video['video_url']); ?>"
                                    data-instgrm-version="14"
                                    style="margin:0 auto; min-width:100% !important"></blockquote>
                            <?php else: ?>
                                <p class="text-danger text-center">Unsupported video</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

function renderKitUserFilesSection(array $files) {
    if (empty($files)) return;
    ?>
    <div class="row mb-5">
        <div class="col-12">
            <h4 class="heading">Downloadable Files</h4>
            <div class="row">
                <?php foreach ($files as $file):
                    $file_extension = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
                    $icon_class = kitUserFileIconClass($file['file_path']);
                ?>
                <div class="col-md-4 col-sm-6 mb-4 small_device_center downloadfileSection">
                    <div class="card height-100">
                        <div class="card-body text-center downloded_files">
                            <div class="mb-3">
                                <i class="fa <?php echo $icon_class; ?> fa-3x text-primary mb-2"></i>
                            </div>
                            <h6 class="card-title"><?php echo htmlspecialchars($file['title'] ?: 'Downloadable File'); ?></h6>
                            <p class="card-text text-muted small"><?php echo strtoupper($file_extension); ?> File</p>
                            <a href="../../assets/upload/kits/<?php echo htmlspecialchars($file['file_path']); ?>"
                               download="<?php echo htmlspecialchars($file['title'] ?: 'file'); ?>"
                               class="btn last_download btn-sm">
                                <i class="fa fa-download me-1"></i><span>Download</span>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

$folder_children_map = kitUserBuildFolderChildrenMap($kit_folders);
$folders_by_id = kitUserFoldersById($kit_folders);
$root_folders = isset($folder_children_map[0]) ? $folder_children_map[0] : [];
$kit_explorer_mode = !empty($kit_folders);
$current_folder_id = $kit_explorer_mode ? (int) ($_GET['folder'] ?? 0) : 0;
if ($kit_explorer_mode && $current_folder_id > 0 && !isset($folders_by_id[$current_folder_id])) {
    $current_folder_id = 0;
}

$kit_explorer_payload = null;
if ($kit_explorer_mode) {
    $items_json = [];
    foreach ($items_by_folder as $fid => $items) {
        $items_json[(string) $fid] = array_map('kitUserItemForJson', $items);
    }
    $children_json = [];
    foreach ($folder_children_map as $pid => $list) {
        $children_json[(string) $pid] = array_map(function ($f) {
            return (int) $f['id'];
        }, $list);
    }
    $kit_query = [];
    if ($current_role === 'TEAM' && $kit_category === 'marketing') {
        $kit_query['kit'] = 'franchise_sales';
    } elseif ($current_role !== 'TEAM' && $current_role !== 'FRANCHISEE' && $kit_category === 'sales') {
        $kit_query['kit'] = 'mw_sales';
    }
    $kit_explorer_payload = [
        'kitLabel' => kitLabelCustomer($kit_category),
        'kitQuery' => $kit_query,
        'folders' => array_map(function ($f) {
            return [
                'id' => (int) $f['id'],
                'title' => (string) $f['title'],
                'parent_id' => !empty($f['parent_id']) ? (int) $f['parent_id'] : 0,
            ];
        }, $kit_folders),
        'childrenMap' => $children_json,
        'itemsByFolder' => $items_json,
        'initialFolderId' => $current_folder_id,
    ];
}
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
    <div class="main-top">
        <span class="heading"><?php echo kitLabelCustomer($kit_category); ?></span>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a href="../dashboard/">Mini Website </a></li>
                  <li class="breadcrumb-item active" aria-current="page"><?php echo kitLabelCustomer($kit_category); ?></li>
                </ol>
            </nav>                              
        </div>
       
        <div class="card mb-4">
            <div class="card-body">
                <div class="FranchiseeDashboard-head">
                    <?php if (!$kit_explorer_mode): ?>
                    <h2 class="heading"><?php echo kitLabelCustomer($kit_category); ?></h2>
                    <?php endif; ?>
                    <p class="text-muted mb-4 sub_title">You can download these resources & share with your customers which will help you grow.</p>

                    <?php if ($kit_explorer_mode): ?>
                    <div class="kit-explorer mb-4" id="kitExplorerRoot"></div>
                    <script type="application/json" id="kitExplorerPayload"><?php echo json_encode($kit_explorer_payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?></script>
                    <script src="kit-explorer.js" defer></script>

                    <?php else: ?>
                    <!-- Flat layout when admin has not arranged folders -->
                    <?php renderKitUserImagesSection(array_values($images)); ?>
                    <?php renderKitUserVideosSection(array_values($videos)); ?>
                    <?php renderKitUserFilesSection(array_values($files)); ?>

                    <?php if (empty($images) && empty($videos) && empty($files)): ?>
                    <div class="text-center py-5">
                        <i class="fa fa-toolbox fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No Kit Items Available</h4>
                        <p class="text-muted">Please check back later.</p>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    element.select();
    element.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        
        // Show success feedback
        const button = element.nextElementSibling;
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fa fa-check"></i> Copied!';
        button.classList.remove('btn-outline-secondary');
        button.classList.add('btn-success');
        
        setTimeout(function() {
            button.innerHTML = originalHTML;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-secondary');
        }, 2000);
        
    } catch (err) {
        console.error('Failed to copy: ', err);
        alert('Failed to copy to clipboard');
    }
}

// Add click-to-copy functionality for video URLs
document.addEventListener('DOMContentLoaded', function() {
    const videoInputs = document.querySelectorAll('input[readonly]');
    videoInputs.forEach(function(input) {
        input.addEventListener('click', function() {
            this.select();
        });
    });
});
</script>

<style>
/* Kit explorer — Windows-style folders */
.kit-explorer-bar {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    margin-bottom: 1.25rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.kit-explorer-up {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 6px;
    background: #002169;
    color: #fff !important;
    text-decoration: none !important;
    flex-shrink: 0;
    border: 0;
    cursor: pointer;
    padding: 0;
}

.kit-crumb-btn {
    background: none;
    border: 0;
    padding: 0;
    color: #278de6;
    cursor: pointer;
    font: inherit;
}

.kit-crumb-btn:hover {
    text-decoration: underline;
}

.kit-explorer-up:hover {
    background: #00154d;
    color: #fff !important;
}

.kit-explorer-breadcrumb {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 0.92rem;
}

.kit-explorer-breadcrumb li + li::before {
    content: '›';
    margin-right: 0.35rem;
    color: #94a3b8;
}

.kit-explorer-breadcrumb a {
    color: #278de6;
    text-decoration: none;
}

.kit-explorer-breadcrumb a:hover {
    text-decoration: underline;
}

.kit-folder-tile {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 1.25rem 0.75rem 1rem;
    border: 1px solid transparent;
    border-radius: 12px;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, transform 0.12s ease;
    min-height: 180px;
}

.kit-folder-tile:hover,
.kit-folder-tile:focus {
    background: #fff8eb;
    border-color: #fcd34d;
    box-shadow: 0 6px 16px rgba(234, 88, 12, 0.15);
    outline: none;
    transform: translateY(-2px);
}

.kit-folder-icon {
    font-size: 5.5rem;
    line-height: 1;
    margin-bottom: 0.6rem;
    filter: drop-shadow(0 3px 4px rgba(0,0,0,0.12));
}

.kit-folder-icon .win-folder-svg {
    width: 1em;
    height: auto;
    display: block;
}

.kit-folder-name {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.3;
    word-break: break-word;
    max-width: 100%;
}

.kit-folder-count {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 0.3rem;
}

@media (max-width: 575.98px) {
    .kit-folder-icon { font-size: 4rem; }
    .kit-folder-tile { min-height: 140px; }
}

/* Enhanced styling for the new layout */
.card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}
.customer_content_area{
    padding: 0px 40px;
    margin-top: 33px;
    
}
.card-img-top {
    border-bottom: 1px solid #f8f9fa;
}
.last_download{
    display:flex;
    justify-content:center;
}
.downloded_files{
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
/* Card body styling */
.card-body {
    padding: 0.75rem;
    background: #f8f9fa;
    border-top: none;
}

iframe
{
    width: 100% !important;
}
.heading {
    font-size: 24px !important;
}
.heading2{
    font-size:24px !important;
}
/* Title styling - bottom left */
.card-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #495057;
    margin: 0;
    line-height: 1.2;
    max-width: 70%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sub_title{
    font-size:22px;
}

/* Download button styling - bottom right */
.btn-primary.btn-sm {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    
    border: none;
    
    transition: all 0.2s ease;
}

.btn-primary.btn-sm:hover {
    background: #0056b3;
    transform: scale(1.1);
    box-shadow: 0 3px 6px rgba(0,123,255,0.4);
}

.btn-primary.btn-sm:active {
    transform: scale(0.95);
}

/* Layout container - clean base for manual positioning */
.d-flex.justify-content-between {
    width: 100%;
    min-height: 32px;
    align-items: center;
    padding: 0.5rem 0;
}
.bottom_title{
    font-size:16px !important;
}

/* Title styling - ready for manual positioning */
.card-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #495057;
    margin: 0;
    line-height: 1.2;
    max-width: 70%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Download button styling - ready for manual positioning */
.btn-primary.btn-sm {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
   
    border: none;
    
    transition: all 0.2s ease;
}

/* External link icon styling */
.btn-primary.btn-sm i {
    font-size: 14px;
    line-height: 1;
}
.Dashboard .card-body .btn {
    display: block;
    margin: 0 auto;
    color: #002169;
    font-size: 32px;
    line-height: 31px;
    font-weight: 600;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-title {
        font-size: 0.8rem;
        max-width: 65%;
    }
    
    .btn-primary.btn-sm {
        width: 26px;
        height: 26px;
        font-size: 0.7rem;
    }
    .small_device_center{
        display:flex;
        justify-content:center;
    }
    .ReferralDetails-head .card, .FranchiseeDashboard-head .card {
        width: 24rem !important;
        margin: 20px auto !important;
    }
    .sub_title{
    font-size:16px;
}
.ReferralDetails-head .card, .FranchiseeDashboard-head .card{
    width: 29rem !important;
}
.instagram-media{
    height: 480px !important;
    width: 100% !important;
}
.Copyright-left,
.Copyright-right{
    padding:0px;
}
}

/* Ensure proper spacing and alignment */
.mt-auto {
    margin-top: auto !important;
}

/* Remove any default margins */
.card-body * {
    margin-bottom: 0;
}
.font22{
    font-size:22px;
}
.height-100{
    height:100px !important;
}

.downloadfileSection .card, 
.downloadfileSection  .card{
    width: 100% !important;
}

.ReferralDetails-head .card, .FranchiseeDashboard-head .card{
    width: 100%;
}
.card-body{
    
    width: 100%;
    margin-bottom:0px;
}
.video_section{
    padding: 0 !important;
}

.card-body .ratio.ratio-16x9{
height:100%;
}
.h-100{
    height:92% !important;
}
</style>

<?php include '../includes/footer.php'; ?>





