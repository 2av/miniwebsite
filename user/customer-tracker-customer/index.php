<?php
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/role_helper.php');
require_once(__DIR__ . '/../../app/includes/product_categories_helper.php');

$current_role = get_current_user_role();
$collaboration_enabled = isset($_SESSION['collaboration_enabled']) && $_SESSION['collaboration_enabled'];
$tracker_portal = defined('MW_TRACKER_PORTAL') ? MW_TRACKER_PORTAL : 'customer';

if (!function_exists('mw_tracker_portal_redirect')) {
    function mw_tracker_portal_redirect(string $path): void {
        $script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $base = preg_replace('#/user(/.*)?$#', '', $script_dir);
        if ($base === '/') {
            $base = '';
        }
        header('Location: ' . $base . '/user/' . ltrim($path, '/'));
        exit;
    }
}

$can_use_team_portal = (
    $current_role === 'TEAM'
    || $current_role === 'FRANCHISEE'
    || ($current_role === 'CUSTOMER' && $collaboration_enabled)
);

if ($tracker_portal === 'customer') {
    if ($current_role !== 'CUSTOMER') {
        header('Location: /login/customer.php');
        exit;
    }
    if ($collaboration_enabled) {
        mw_tracker_portal_redirect('customer-tracker/');
    }
    $tracker_variant = 'customer';
    $owner_role_db = 'CUSTOMER';
} else {
    if (!$can_use_team_portal) {
        if ($current_role === 'CUSTOMER') {
            mw_tracker_portal_redirect('customer-tracker-customer/');
        }
        if ($current_role === 'TEAM') {
            header('Location: /login/team.php');
            exit;
        }
        header('Location: /login/customer.php');
        exit;
    }
    $tracker_variant = 'team';
    $owner_role_db = $current_role;
}

$owner_id = (int)(get_user_id() ?? 0);
$owner_email = (string)(get_user_email() ?? '');
if ($owner_id <= 0 && $owner_email !== '') {
    $roleLookup = $current_role;
    $stmtOwner = $connect->prepare("SELECT id FROM user_details WHERE email = ? AND role = ? LIMIT 1");
    if ($stmtOwner) {
        $stmtOwner->bind_param('ss', $owner_email, $roleLookup);
        $stmtOwner->execute();
        $resOwner = $stmtOwner->get_result();
        if ($resOwner && ($ownerRow = $resOwner->fetch_assoc())) {
            $owner_id = (int)$ownerRow['id'];
        }
        $stmtOwner->close();
    }
}
if ($owner_id <= 0) {
    die('Unable to identify user.');
}

$connect->query("CREATE TABLE IF NOT EXISTS mw_customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    owner_role VARCHAR(20) NOT NULL DEFAULT 'CUSTOMER',
    customer_name VARCHAR(150) NOT NULL,
    label_tag VARCHAR(18) DEFAULT '',
    phone_number VARCHAR(25) NOT NULL,
    email_id VARCHAR(150) DEFAULT '',
    company_name VARCHAR(150) DEFAULT '',
    website VARCHAR(255) DEFAULT '',
    address_line1 VARCHAR(255) DEFAULT '',
    area_city VARCHAR(120) DEFAULT '',
    comments TEXT,
    source VARCHAR(18) DEFAULT 'Direct',
    status VARCHAR(40) DEFAULT 'Followup required',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_shared_at DATETIME NULL DEFAULT NULL,
    INDEX idx_owner (owner_user_id, owner_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$connect->query("ALTER TABLE mw_customers ADD COLUMN IF NOT EXISTS last_shared_at DATETIME NULL DEFAULT NULL");
$connect->query("ALTER TABLE mw_customers MODIFY COLUMN label_tag VARCHAR(18) DEFAULT ''");
$connect->query("ALTER TABLE mw_customers MODIFY COLUMN source VARCHAR(18) DEFAULT 'Direct'");
$connect->query("ALTER TABLE mw_customers ADD COLUMN IF NOT EXISTS email_id VARCHAR(150) DEFAULT '' AFTER phone_number");
$connect->query("ALTER TABLE mw_customers ADD COLUMN IF NOT EXISTS company_name VARCHAR(150) DEFAULT '' AFTER email_id");
$connect->query("ALTER TABLE mw_customers ADD COLUMN IF NOT EXISTS website VARCHAR(255) DEFAULT '' AFTER company_name");
$connect->query("ALTER TABLE mw_customers ADD COLUMN IF NOT EXISTS status VARCHAR(40) DEFAULT 'Followup required' AFTER source");
$connect->query("ALTER TABLE mw_customers MODIFY COLUMN status VARCHAR(40) DEFAULT 'Followup required'");
$connect->query("ALTER TABLE mw_customers ADD COLUMN IF NOT EXISTS business_type VARCHAR(150) DEFAULT '' AFTER label_tag");
$connect->query("ALTER TABLE mw_customers ADD COLUMN IF NOT EXISTS approached_for VARCHAR(150) DEFAULT '' AFTER business_type");
$connect->query("ALTER TABLE mw_customers ADD COLUMN IF NOT EXISTS followup_method VARCHAR(150) DEFAULT '' AFTER approached_for");

function mw_phone_clean(string $v): string { return preg_replace('/[^0-9]/', '', $v) ?? ''; }
function mw_limit_text(string $v, int $max): string { return mb_substr(trim($v), 0, $max); }
function mw_format_last_shared($v): string {
    if ($v === null || $v === '' || $v === '0000-00-00 00:00:00') {
        return '-';
    }
    $t = strtotime((string)$v);
    if ($t === false) {
        return '-';
    }
    return date('d-m-Y H:i', $t);
}

$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_customer') {
        $id = (int)($_POST['customer_id'] ?? 0);
        $name = trim($_POST['customer_name'] ?? '');
        $label = mw_limit_text($_POST['label_tag'] ?? '', 18);
        $phone = mw_phone_clean(trim($_POST['phone_number'] ?? ''));
        $emailId = trim($_POST['email_id'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $line1 = trim($_POST['address_line1'] ?? '');
        $areaCity = trim($_POST['area_city'] ?? '');
        $comments = trim($_POST['comments'] ?? '');
        $source = mw_limit_text($_POST['source'] ?? 'Direct', 18);
        $statusPredefined = ['Followup required', 'Phone Busy/Not Picked', 'Important', 'Deal Done', 'Profile Shared', 'Interested', 'Not Interested'];
        $statusRaw = trim((string)($_POST['status'] ?? 'Followup required'));
        $status = in_array($statusRaw, $statusPredefined, true)
            ? mw_limit_text($statusRaw, 40)
            : mw_limit_text($statusRaw, 18);
        $businessType = mw_limit_text($_POST['business_type'] ?? '', 150);
        $approachedFor = mw_limit_text($_POST['approached_for'] ?? '', 150);
        $followupMethod = mw_limit_text($_POST['followup_method'] ?? '', 150);
        if ($name === '' || $phone === '') {
            header('Location: index.php?msg=validation_error');
            exit;
        } elseif (strlen($phone) > 12) {
            header('Location: index.php?msg=phone_max_error');
            exit;
        } else {
            if ($id > 0) {
                $stmt = $connect->prepare("UPDATE mw_customers SET customer_name=?, label_tag=?, phone_number=?, email_id=?, company_name=?, website=?, source=?, status=?, address_line1=?, area_city=?, comments=?, business_type=?, approached_for=?, followup_method=? WHERE id=? AND owner_user_id=? AND owner_role=?");
                if ($stmt) {
                    $stmt->bind_param('ssssssssssssssiis', $name, $label, $phone, $emailId, $companyName, $website, $source, $status, $line1, $areaCity, $comments, $businessType, $approachedFor, $followupMethod, $id, $owner_id, $owner_role_db);
                    $stmt->execute();
                    $stmt->close();
                }
                header('Location: index.php?msg=updated');
                exit;
            } else {
                $stmt = $connect->prepare("INSERT INTO mw_customers (owner_user_id, owner_role, customer_name, label_tag, phone_number, email_id, company_name, website, address_line1, area_city, comments, source, status, business_type, approached_for, followup_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('isssssssssssssss', $owner_id, $owner_role_db, $name, $label, $phone, $emailId, $companyName, $website, $line1, $areaCity, $comments, $source, $status, $businessType, $approachedFor, $followupMethod);
                    $stmt->execute();
                    $stmt->close();
                }
                header('Location: index.php?msg=saved');
                exit;
            }
        }
    } elseif ($action === 'delete_customer') {
        $id = (int)($_POST['customer_id'] ?? 0);
        $stmt = $connect->prepare("DELETE FROM mw_customers WHERE id = ? AND owner_user_id = ? AND owner_role = ?");
        if ($stmt) {
            $stmt->bind_param('iis', $id, $owner_id, $owner_role_db);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: index.php?msg=deleted');
        exit;
    } elseif ($action === 'record_last_shared') {
        header('Content-Type: application/json; charset=utf-8');
        $cid = (int)($_POST['customer_id'] ?? 0);
        if ($cid <= 0) {
            echo json_encode(['ok' => false]);
            exit;
        }
        $stmt = $connect->prepare("UPDATE mw_customers SET last_shared_at = NOW() WHERE id = ? AND owner_user_id = ? AND owner_role = ?");
        if ($stmt) {
            $stmt->bind_param('iis', $cid, $owner_id, $owner_role_db);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['ok' => true]);
        exit;
    }
}

if (isset($_GET['msg'])) {
    $msg = trim((string)$_GET['msg']);
    if ($msg === 'updated') {
        $message = 'Customer updated successfully.';
        $message_type = 'success';
    } elseif ($msg === 'saved') {
        $message = 'Customer saved successfully.';
        $message_type = 'success';
    } elseif ($msg === 'deleted') {
        $message = 'Customer deleted.';
        $message_type = 'success';
    } elseif ($msg === 'validation_error') {
        $message = 'Customer Name and Phone Number are required.';
        $message_type = 'danger';
    } elseif ($msg === 'phone_max_error') {
        $message = 'Phone number must be maximum 12 digits.';
        $message_type = 'danger';
    }
}

$search = trim($_GET['search'] ?? '');
$sort_by = trim($_GET['sort_by'] ?? 'created_at');
if ($sort_by === 'address_line1' || $sort_by === 'area_city') {
    $sort_by = 'address';
}
$sort_dir = strtolower(trim($_GET['sort_dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$sortable_columns = [
    'created_at' => 'created_at',
    'customer_name' => 'customer_name',
    'phone_number' => 'phone_number',
    'label_tag' => 'label_tag',
    'source' => 'source',
    'email_id' => 'email_id',
    'company_name' => 'company_name',
    'website' => 'website',
    'address' => "CONCAT(IFNULL(address_line1,''), ' ', IFNULL(area_city,''))",
    'last_shared_at' => 'last_shared_at',
    'comments' => 'comments',
    'status' => 'status',
    'business_type' => 'business_type',
    'approached_for' => 'approached_for',
    'followup_method' => 'followup_method',
];
if (!isset($sortable_columns[$sort_by])) {
    $sort_by = 'created_at';
}
$tracker_clear_search_href = '?' . http_build_query([
    'sort_by' => $sort_by,
    'sort_dir' => strtolower($sort_dir),
]);
$trackerThSort = function (string $col, string $label) use ($search, $sort_by, $sort_dir, $sortable_columns): string {
    if (!isset($sortable_columns[$col])) {
        return '<th>' . htmlspecialchars($label) . '</th>';
    }
    $nextDir = ($sort_by === $col && $sort_dir === 'ASC') ? 'desc' : 'asc';
    $iconClass = ($sort_by === $col) ? ($sort_dir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';
    $href = '?' . http_build_query(['search' => $search, 'sort_by' => $col, 'sort_dir' => $nextDir]);
    return '<th><a href="' . htmlspecialchars($href) . '">' . htmlspecialchars($label) . ' <i class="fa ' . $iconClass . ' sort-icon"></i></a></th>';
};
$customers = [];
$sql = "SELECT * FROM mw_customers WHERE owner_user_id = ? AND owner_role = ?";
$types = 'is';
$args = [$owner_id, $owner_role_db];
if ($search !== '') {
    $sql .= " AND (customer_name LIKE ? OR phone_number LIKE ? OR email_id LIKE ? OR company_name LIKE ? OR source LIKE ? OR website LIKE ? OR address_line1 LIKE ? OR area_city LIKE ? OR comments LIKE ? OR label_tag LIKE ? OR status LIKE ? OR business_type LIKE ? OR approached_for LIKE ? OR followup_method LIKE ?)";
    $lk = '%' . $search . '%';
    $types .= str_repeat('s', 14);
    for ($si = 0; $si < 14; $si++) {
        $args[] = $lk;
    }
}
$sql .= " ORDER BY " . $sortable_columns[$sort_by] . " " . $sort_dir . ", id DESC";
$stmt = $connect->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$args);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $customers[] = $row;
    $stmt->close();
}

$business_name = 'Our Business';
$area_city_business = 'your area';
$mini_link = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'miniwebsite.in');
$stmtBiz = $connect->prepare("SELECT * FROM digi_card WHERE user_email = ? ORDER BY id DESC LIMIT 1");
if ($stmtBiz) {
    $stmtBiz->bind_param('s', $owner_email);
    $stmtBiz->execute();
    $resBiz = $stmtBiz->get_result();
    if ($resBiz && ($biz = $resBiz->fetch_assoc())) {
        $biz_name = trim((string)($biz['d_comp_name'] ?? ''));
        $biz_city = trim((string)($biz['d_city'] ?? ''));
        $biz_state = trim((string)($biz['d_state'] ?? ''));
        if ($biz_name !== '') $business_name = $biz_name;
        if ($biz_city !== '' || $biz_state !== '') $area_city_business = trim($biz_city . ', ' . $biz_state, ' ,');
        if (!empty($biz['card_id'])) $mini_link = $mini_link . '/n.php?n=' . urlencode($biz['card_id']);
    }
    $stmtBiz->close();
}

$business_primary_options = getBusinessPrimaryCategoryOptions($connect, $owner_id);

$default_template = "Hello ðŸ˜Š\nThis is [Business Name] from [Area, City].\n\nWe have added some latest special offers for you.ðŸ‘‡\nðŸ‘‰ [MiniWebsite Link]\n\nIf anything interests you, feel free to message us on WhatsApp.\n\nLimited time offers hain, jaldi check karein ðŸ‘\n\nThank you ðŸ™";
$default_whatsapp_template = "Hello ðŸ˜Š\nHope you are doing well.\n\nWe have added few new products & offers for you.ðŸ‘‡\nðŸ‘‰ [MiniWebsite Link]\n\nYou can check. And please let us know if any requirements.\n\nThank you ðŸ™";
$preview_template = str_replace(
    ['[Business Name]', '[Area, City]', '[MiniWebsite Link]'],
    [$business_name, $area_city_business, $mini_link],
    $default_template
);
include __DIR__ . '/../includes/header.php';
?>
<style>
#customerTrackerSafetyTips {
    position: static !important;
    right: auto !important;
    top: auto !important;
    left: auto !important;
    float: none !important;
    margin-top: 16px !important;
}
#customerTrackerTable thead a {
    color: inherit !important;
    text-decoration: none !important;
    font-weight: 600;
}
#customerTrackerSearchForm {
    flex: 1 1 auto;
    min-width: 0;
    max-width: 100%;
}
#customerTrackerSearchForm .tracker-search-input-wrap {
    position: relative;
    min-width: 0;
    flex: 1 1 200px;
    max-width: 360px;
}
#customerTrackerSearchForm .tracker-search-input-wrap .form-control {
    padding-right: 2.25rem;
}
#customerTrackerSearchForm .tracker-search-input-clear {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: #6e7a97;
    padding: 4px 6px;
    line-height: 1;
    border-radius: 6px;
    z-index: 2;
    cursor: pointer;
}
#customerTrackerSearchForm .tracker-search-input-clear:hover {
    color: #dc3545;
    background: rgba(0,0,0,0.05);
}
#customerTrackerSearchForm .tracker-search-input-clear[hidden] {
    display: none !important;
}
#customerTrackerTable thead a .sort-icon {
    margin-left: 4px;
    opacity: 0.8;
}
/* Wide table: horizontal scroll; columns keep readable minimum widths */
#customerTrackerTableWrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    max-width: 100%;
}
#customerTrackerTable {
    width: max-content;
    min-width: 100%;
    margin-bottom: 0;
    table-layout: auto;
}
#customerTrackerTable thead th,
#customerTrackerTable tbody td {
    vertical-align: middle;
}
/* Default: donâ€™t crush text â€” scroll instead */
#customerTrackerTable thead th {
    white-space: nowrap;
}
#customerTrackerTable tbody td {
    white-space: nowrap;
}
/* Address: street + area/city (merged); allow wrap */
#customerTrackerTable thead th:nth-child(10),
#customerTrackerTable tbody td:nth-child(10) {
    white-space: normal;
    min-width: 11rem;
    max-width: 22rem;
    word-break: break-word;
}
/* Comments: wrap inside a sensible column width */
#customerTrackerTable .ct-table-comment {
    white-space: normal;
    min-width: 11rem;
    max-width: 18rem;
    word-break: break-word;
}
/* Per-column minimum widths (14 cols) */
#customerTrackerTable th:nth-child(1),
#customerTrackerTable td:nth-child(1) { min-width: 2.75rem; }
#customerTrackerTable th:nth-child(2),
#customerTrackerTable td:nth-child(2) { min-width: 6.5rem; }
#customerTrackerTable th:nth-child(3),
#customerTrackerTable td:nth-child(3) { min-width: 9rem; }
#customerTrackerTable th:nth-child(4),
#customerTrackerTable td:nth-child(4) { min-width: 7.5rem; }
#customerTrackerTable th:nth-child(5),
#customerTrackerTable td:nth-child(5) { min-width: 7rem; }
#customerTrackerTable th:nth-child(6),
#customerTrackerTable td:nth-child(6) { min-width: 7rem; }
#customerTrackerTable th:nth-child(7),
#customerTrackerTable td:nth-child(7) { min-width: 10rem; }
#customerTrackerTable th:nth-child(8),
#customerTrackerTable td:nth-child(8) { min-width: 9rem; }
#customerTrackerTable th:nth-child(9),
#customerTrackerTable td:nth-child(9) { min-width: 9rem; }
#customerTrackerTable th:nth-child(12),
#customerTrackerTable td:nth-child(12) { min-width: 8.5rem; }
#customerTrackerTable th:nth-child(13),
#customerTrackerTable td:nth-child(13) { min-width: 8.5rem; }
#customerTrackerTable th:nth-child(14),
#customerTrackerTable td:nth-child(14) { min-width: 7rem; }
.tracker-modal .modal-header {
    background: #001b78;
    color: #fff;
    padding: 12px 16px;
}
.tracker-modal .modal-header .modal-title {
    color: #fff;
    font-size: 17px;
    font-weight: 600;
}
.tracker-modal .modal-header .btn-close {
    filter: invert(1);
}
.tracker-modal .modal-content {
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #e4e8f0;
    background: #f4f6fb;
}
#customerModal .modal-body {
    background: #f4f6fb;
}
#customerModal .modal-dialog {
    max-width: 470px;
}
#customerModal.expanded-view .modal-dialog {
    max-width: 860px;
}
#customerModal:not(.expanded-view) #customerNameCol,
#customerModal:not(.expanded-view) #phoneNumberCol {
    flex: 0 0 100%;
    max-width: 100%;
}
#customerModal .modal-footer {
    background: #f4f6fb;
    border-top: 0;
    padding: 14px 16px 16px;
}
.tracker-modal .form-label {
    font-size: 13px;
    font-weight: 600;
    color: #27345a;
    margin-bottom: 6px;
}
.tracker-modal .form-control {
    font-size: 13px;
    min-height: 40px;
    background: #fff;
    border: 1px solid #d8deea;
    border-radius: 8px;
    color: #4a5678;
}
/* Do not use background shorthand on selects â€” it removes Bootstrapâ€™s caret. */
.tracker-modal .form-select {
    font-size: 13px;
    min-height: 40px;
    border: 1px solid #d8deea;
    border-radius: 8px;
    color: #4a5678;
    background-color: #fff;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%234a5678' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.7' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.65rem center;
    background-size: 14px 10px;
    padding-right: 2.25rem;
}
.tracker-input-wrap {
    position: relative;
}
.tracker-input-wrap .field-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #6e7a97;
    font-size: 13px;
    pointer-events: none;
}
.tracker-input-wrap .form-control,
.tracker-input-wrap .form-select {
    padding-left: 34px;
}
.phone-group .country-code-select {
    max-width: 110px;
}
.phone-group .country-code-select,
.phone-group .phone-input {
    min-height: 40px;
}
.phone-group .country-code-select {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}
.phone-group .phone-input {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}
.tracker-modal .form-control::placeholder {
    color: #8b96b2;
}
.section-title {
    font-size: 15px;
    font-weight: 700;
    color: #0d4fbf;
    margin-bottom: 8px;
    border-bottom: 1px solid #e8edf6;
    padding-bottom: 6px;
}
#shareOfferModal .modal-body {
    background: #fff;
}
#shareOfferModal .modal-content {
    background: #fff;
}
#shareOfferModal .modal-dialog {
    max-width: 1140px;
}
/* Mobile: vertical centering clips tall modals â€” pin to top + safe area */
@media (max-width: 767.98px) {
    #shareOfferModal.modal {
        align-items: flex-start !important;
        padding-top: max(12px, env(safe-area-inset-top, 0px));
        padding-bottom: max(12px, env(safe-area-inset-bottom, 0px));
        padding-left: max(8px, env(safe-area-inset-left, 0px));
        padding-right: max(8px, env(safe-area-inset-right, 0px));
        overflow-y: auto !important;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
    }
    #shareOfferModal .modal-dialog {
        max-width: calc(100% - 1rem);
        margin: 0 auto !important;
        min-height: 0 !important;
        align-items: flex-start !important;
        display: flex;
    }
    #shareOfferModal .modal-dialog.modal-dialog-centered {
        min-height: 0 !important;
    }
    #shareOfferModal .modal-content {
        max-height: calc(100vh - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px) - 40px);
        max-height: calc(100dvh - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px) - 40px);
        display: flex;
        flex-direction: column;
    }
    #shareOfferModal .modal-header {
        flex-shrink: 0;
    }
    #shareOfferModal .modal-body {
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        flex: 1 1 auto;
        min-height: 0;
    }
    #shareOfferModal .wa-popup-footer,
    #shareOfferModal .modal-footer.wa-popup-footer {
        flex-shrink: 0;
    }
}
.wa-message-field {
    position: relative;
}
.wa-message-field #offerMessage {
    padding-bottom: 1.75rem;
    min-height: 200px;
}
.wa-char-count {
    position: absolute;
    right: 10px;
    bottom: 8px;
    font-size: 11px;
    color: #8b96b2;
    pointer-events: none;
}
#waTemplateList .template-item {
    border: 1px solid #e2e7f2;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
    background: #fff;
}
#waTemplateList .template-item.active {
    border-color: #6ea8ff;
    background: #eef5ff;
}
#waTemplateList .template-name {
    font-size: 13px;
    font-weight: 700;
    color: #0d4fbf;
}
#waTemplateList .template-item label {
    color: inherit;
}
.wa-template-head {
    margin-bottom: 10px;
}
.wa-template-title {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: #1a2744;
}
.template-name-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.template-preview {
    font-size: 12px;
    color: #5c6b85;
    margin-top: 4px;
    line-height: 1.35;
}
.template-default-badge {
    background: #28a745;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    border-radius: 4px;
    padding: 2px 6px;
}
.wa-popup-footer {
    background: #fff !important;
    border-top: 1px solid #e4e8f0 !important;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}
.wa-popup-footer .btn-success {
    font-weight: 600;
}
.wa-popup-footer .btn-outline-primary {
    border-color: #2d6adf;
    color: #2d6adf;
    font-weight: 600;
    background: #fff;
}
.wa-popup-footer .btn-outline-primary:hover {
    background: #f0f6ff;
    border-color: #1f58c6;
    color: #1f58c6;
}
.wa-popup-footer .btn-danger {
    font-weight: 600;
}
.tracker-subtle-box {
    background: #f2f7ff;
    border: 1px solid #d9e7ff;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 12px;
    color: #2c456e;
}
.tracker-subtle-box ul {
    margin-bottom: 0;
    padding-left: 18px;
}
.tracker-btn-yellow {
    background: #f8c21c;
    border-color: #f8c21c;
    color: #212529;
    font-weight: 600;
    min-width: 92px;
    border-radius: 8px;
}
.tracker-btn-yellow:hover {
    background: #efb400;
    border-color: #efb400;
    color: #212529;
}
#customerModal .btn.btn-light.border {
    background: #f7f8fb;
    border: 1px solid #c8cfdd !important;
    color: #4e5873;
    border-radius: 8px;
    min-width: 80px;
}
#toggleAdditionalBtn {
    width: 100%;
    text-align: center;
    border: 1px dashed #d4dced;
    border-radius: 8px;
    color: #2d6adf;
    text-decoration: none;
    font-weight: 600;
    background: #f7f9ff;
    padding: 10px 12px;
}
#toggleAdditionalBtn:hover {
    color: #1f58c6;
    background: #f1f6ff;
}
#customerModal .expanded-only {
    display: none;
}
#customerModal.expanded-view .expanded-only {
    display: block;
}
.wa-action-title {
    display: flex;
    align-items: center;
    gap: 8px;
}
.wa-action-title .fa-whatsapp {
    color: #25d366;
    font-size: 18px;
}
@media (max-width: 767.98px) {
    .tracker-modal .modal-dialog {
        margin: 0.5rem;
    }
    .tracker-modal .modal-title {
        font-size: 16px;
    }
    .tracker-modal .form-label,
    .tracker-modal .form-control,
    .tracker-modal .form-select,
    #offerMessage {
        font-size: 13px;
    }
    .tracker-modal .modal-footer .btn {
        font-size: 13px;
    }
    .wa-popup-footer {
        flex-direction: column;
        align-items: stretch !important;
    }
    .wa-popup-footer .btn {
        width: 100%;
        min-height: 44px;
    }
}
</style>
<main class="Dashboard">
<div class="container-fluid customer_content_area">
    <div class="main-top"><span class="heading">Customer Tracker</span></div>
    <?php if ($message !== ''): ?>
        <div id="trackerFlashMessage" class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <div class="card mb-4"><div class="card-body">
        <div class="d-flex flex-wrap flex-md-nowrap justify-content-between align-items-center gap-2 mb-3">
            <button id="openCustomerModalBtn" class="btn btn-primary flex-shrink-0" data-bs-toggle="modal" data-bs-target="#customerModal"><i class="fa fa-plus"></i>Add Customer</button>
            <form method="get" id="customerTrackerSearchForm" class="d-flex flex-nowrap align-items-stretch ms-md-auto" style="gap:8px;min-width:0;">
                <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
                <input type="hidden" name="sort_dir" value="<?php echo htmlspecialchars(strtolower($sort_dir)); ?>">
                <div class="tracker-search-input-wrap">
                    <input name="search" id="trackerSearchInput" class="form-control" placeholder="Search name, phone, email, company, sourceâ€¦" value="<?php echo htmlspecialchars($search); ?>" autocomplete="off" inputmode="search"<?php echo $search !== '' ? ' aria-label="Search (filter active; use clear to show all)"' : ''; ?>>
                    <button type="button" class="tracker-search-input-clear" id="trackerSearchInputClear" title="Clear" aria-label="Clear search" hidden><i class="fa fa-times"></i></button>
                </div>
                <button type="submit" class="btn btn-outline-secondary flex-shrink-0" title="Search"><i class="fa fa-search"></i></button>
            </form>
        </div>
        <div class="table-responsive" id="customerTrackerTableWrap"><table class="table table-bordered table-striped" id="customerTrackerTable">
            <?php if ($tracker_variant === 'team'): ?>
            <thead><tr><th>SN</th><?php echo $trackerThSort('created_at', 'Date Added') . $trackerThSort('customer_name', 'Customer Name') . $trackerThSort('phone_number', 'Phone Number') . $trackerThSort('business_type', 'Business Type') . $trackerThSort('approached_for', 'Approached For') . $trackerThSort('followup_method', 'Followed-Up Method') . $trackerThSort('source', 'Source') . $trackerThSort('email_id', 'Email ID') . $trackerThSort('company_name', 'Company Name') . $trackerThSort('website', 'Website') . $trackerThSort('address', 'Address') . $trackerThSort('last_shared_at', 'Last shared') . $trackerThSort('comments', 'Comment') . $trackerThSort('status', 'Status'); ?><th>Actions</th><th>Manage</th></tr></thead>
            <tbody>
            <?php if (empty($customers)): ?><tr><td colspan="17" class="text-center">No customers found.</td></tr><?php else: $sn=1; foreach($customers as $c): ?>
                <tr>
                    <td><?php echo $sn++; ?></td>
                    <td><?php echo date('d-m-Y', strtotime($c['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($c['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($c['phone_number']); ?></td>
                    <td><?php $bt = trim((string)($c['business_type'] ?? '')); echo htmlspecialchars($bt !== '' ? $bt : '-'); ?></td>
                    <td><?php $af = trim((string)($c['approached_for'] ?? '')); echo htmlspecialchars($af !== '' ? $af : '-'); ?></td>
                    <td><?php $fm = trim((string)($c['followup_method'] ?? '')); echo htmlspecialchars($fm !== '' ? $fm : '-'); ?></td>
                    <td><?php $src = trim((string)($c['source'] ?? '')); echo htmlspecialchars($src !== '' ? $src : '-'); ?></td>
                    <td><?php $em = trim((string)($c['email_id'] ?? '')); echo htmlspecialchars($em !== '' ? $em : '-'); ?></td>
                    <td><?php $co = trim((string)($c['company_name'] ?? '')); echo htmlspecialchars($co !== '' ? $co : '-'); ?></td>
                    <td><?php $web = trim((string)($c['website'] ?? '')); echo htmlspecialchars($web !== '' ? $web : '-'); ?></td>
                    <td><?php
                        $a1 = trim((string)($c['address_line1'] ?? ''));
                        $ac = trim((string)($c['area_city'] ?? ''));
                        $addrMerged = trim($a1 . (($a1 !== '' && $ac !== '') ? ', ' : '') . $ac);
                        echo htmlspecialchars($addrMerged !== '' ? $addrMerged : '-');
                    ?></td>
                    <td><?php echo htmlspecialchars(mw_format_last_shared($c['last_shared_at'] ?? null)); ?></td>
                    <td class="ct-table-comment"><?php $comm = (string)($c['comments'] ?? ''); echo trim($comm) === '' ? '-' : htmlspecialchars($comm); ?></td>
                    <td><?php $st = trim((string)($c['status'] ?? '')); echo htmlspecialchars($st !== '' ? $st : '-'); ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="tel:<?php echo htmlspecialchars($c['phone_number']); ?>"><i class="fa fa-phone"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-success wa-normal-btn" data-id="<?php echo (int)$c['id']; ?>" data-phone="<?php echo htmlspecialchars($c['phone_number']); ?>" data-name="<?php echo htmlspecialchars($c['customer_name']); ?>"><i class="fa-brands fa-whatsapp"></i></button>
                        <button class="btn btn-sm btn-outline-warning single-offer-btn" data-id="<?php echo (int)$c['id']; ?>"><i class="fa fa-gift"></i></button>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-info edit-btn" data-id="<?php echo (int)$c['id']; ?>" data-name="<?php echo htmlspecialchars($c['customer_name']); ?>" data-label="<?php echo htmlspecialchars($c['label_tag']); ?>" data-business-type="<?php echo htmlspecialchars($c['business_type'] ?? ''); ?>" data-approached-for="<?php echo htmlspecialchars($c['approached_for'] ?? ''); ?>" data-followup-method="<?php echo htmlspecialchars($c['followup_method'] ?? ''); ?>" data-phone="<?php echo htmlspecialchars($c['phone_number']); ?>" data-email="<?php echo htmlspecialchars($c['email_id'] ?? ''); ?>" data-company="<?php echo htmlspecialchars($c['company_name'] ?? ''); ?>" data-website="<?php echo htmlspecialchars($c['website'] ?? ''); ?>" data-source="<?php echo htmlspecialchars($c['source'] ?? ''); ?>" data-status="<?php echo htmlspecialchars($c['status'] ?? ''); ?>" data-line1="<?php echo htmlspecialchars($c['address_line1']); ?>" data-areacity="<?php echo htmlspecialchars($c['area_city'] ?? ''); ?>" data-comments="<?php echo htmlspecialchars($c['comments']); ?>"><i class="fa fa-edit"></i></button>
                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this customer?');"><input type="hidden" name="action" value="delete_customer"><input type="hidden" name="customer_id" value="<?php echo (int)$c['id']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button></form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
            <?php else: ?>
            <thead><tr><th>SN</th><?php echo $trackerThSort('created_at', 'Date Added') . $trackerThSort('customer_name', 'Customer Name') . $trackerThSort('phone_number', 'Phone Number') . $trackerThSort('label_tag', 'Label') . $trackerThSort('source', 'Source') . $trackerThSort('email_id', 'Email ID') . $trackerThSort('company_name', 'Company Name') . $trackerThSort('website', 'Website') . $trackerThSort('address', 'Address') . $trackerThSort('last_shared_at', 'Last shared') . $trackerThSort('comments', 'Comment') . $trackerThSort('status', 'Status'); ?><th>Actions</th><th>Manage</th></tr></thead>
            <tbody>
            <?php if (empty($customers)): ?><tr><td colspan="15" class="text-center">No customers found.</td></tr><?php else: $sn=1; foreach($customers as $c): ?>
                <tr>
                    <td><?php echo $sn++; ?></td>
                    <td><?php echo date('d-m-Y', strtotime($c['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($c['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($c['phone_number']); ?></td>
                    <td><?php echo htmlspecialchars($c['label_tag'] ?: 'Regular'); ?></td>
                    <td><?php $src = trim((string)($c['source'] ?? '')); echo htmlspecialchars($src !== '' ? $src : '-'); ?></td>
                    <td><?php $em = trim((string)($c['email_id'] ?? '')); echo htmlspecialchars($em !== '' ? $em : '-'); ?></td>
                    <td><?php $co = trim((string)($c['company_name'] ?? '')); echo htmlspecialchars($co !== '' ? $co : '-'); ?></td>
                    <td><?php $web = trim((string)($c['website'] ?? '')); echo htmlspecialchars($web !== '' ? $web : '-'); ?></td>
                    <td><?php
                        $a1 = trim((string)($c['address_line1'] ?? ''));
                        $ac = trim((string)($c['area_city'] ?? ''));
                        $addrMerged = trim($a1 . (($a1 !== '' && $ac !== '') ? ', ' : '') . $ac);
                        echo htmlspecialchars($addrMerged !== '' ? $addrMerged : '-');
                    ?></td>
                    <td><?php echo htmlspecialchars(mw_format_last_shared($c['last_shared_at'] ?? null)); ?></td>
                    <td class="ct-table-comment"><?php $comm = (string)($c['comments'] ?? ''); echo trim($comm) === '' ? '-' : htmlspecialchars($comm); ?></td>
                    <td><?php $st = trim((string)($c['status'] ?? '')); echo htmlspecialchars($st !== '' ? $st : '-'); ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="tel:<?php echo htmlspecialchars($c['phone_number']); ?>"><i class="fa fa-phone"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-success wa-normal-btn" data-id="<?php echo (int)$c['id']; ?>" data-phone="<?php echo htmlspecialchars($c['phone_number']); ?>" data-name="<?php echo htmlspecialchars($c['customer_name']); ?>"><i class="fa-brands fa-whatsapp"></i></button>
                        <button class="btn btn-sm btn-outline-warning single-offer-btn" data-id="<?php echo (int)$c['id']; ?>"><i class="fa fa-gift"></i></button>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-info edit-btn" data-id="<?php echo (int)$c['id']; ?>" data-name="<?php echo htmlspecialchars($c['customer_name']); ?>" data-label="<?php echo htmlspecialchars($c['label_tag']); ?>" data-phone="<?php echo htmlspecialchars($c['phone_number']); ?>" data-email="<?php echo htmlspecialchars($c['email_id'] ?? ''); ?>" data-company="<?php echo htmlspecialchars($c['company_name'] ?? ''); ?>" data-website="<?php echo htmlspecialchars($c['website'] ?? ''); ?>" data-source="<?php echo htmlspecialchars($c['source'] ?? ''); ?>" data-status="<?php echo htmlspecialchars($c['status'] ?? ''); ?>" data-line1="<?php echo htmlspecialchars($c['address_line1']); ?>" data-areacity="<?php echo htmlspecialchars($c['area_city'] ?? ''); ?>" data-comments="<?php echo htmlspecialchars($c['comments']); ?>"><i class="fa fa-edit"></i></button>
                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this customer?');"><input type="hidden" name="action" value="delete_customer"><input type="hidden" name="customer_id" value="<?php echo (int)$c['id']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button></form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
            <?php endif; ?>
        </table></div>
    </div></div>
    <div class="alert alert-light border mt-3 mb-0" id="customerTrackerSafetyTips">
        <strong>Action Button Details</strong>
        <ul class="mb-3 mt-2">
            <li><i class="fa fa-phone text-primary"></i> <strong>Call:</strong> Opens phone dialer with customer number.</li>
            <li><i class="fa-brands fa-whatsapp text-success"></i> <strong>WhatsApp:</strong> Opens normal WhatsApp message window.</li>
            <li><i class="fa fa-gift text-warning"></i> <strong>Share Offer:</strong> Opens offer preview and sends selected offer message.</li>
            <li><i class="fa fa-edit text-info"></i> <strong>Edit:</strong> Update customer details (same fields as the add form).</li>
            <li><i class="fa fa-trash text-danger"></i> <strong>Delete:</strong> Permanently removes customer entry from your list.</li>
        </ul>
         
    </div>
</div>
</main>

<div class="modal fade tracker-modal" id="customerModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><form method="post">
<div class="modal-header"><h5 class="modal-title">Quick Add Customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<input type="hidden" name="action" value="save_customer"><input type="hidden" name="customer_id" id="customer_id">
<div class="section-title"><i class="fa fa-bolt me-1"></i> Quick action</div>
<div class="row g-2">
    <div class="col-12 col-md-6" id="customerNameCol">
        <label class="form-label">Customer Name *</label>
        <div class="tracker-input-wrap">
            <i class="fa fa-user field-icon"></i>
            <input name="customer_name" id="customer_name" class="form-control" placeholder="Enter customer name" required>
        </div>
    </div>
    <div class="col-12 col-md-6" id="phoneNumberCol">
        <label class="form-label">Phone Number *</label>
        <div class="input-group phone-group">
            <span class="input-group-text bg-white border-end-0"><i class="fa fa-phone text-muted"></i></span>
            <select class="form-select country-code-select border-start-0 border-end-0" id="country_code">
                <option value="+91" selected>+91</option>
            </select>
            <input name="phone_number" id="phone_number" class="form-control phone-input" placeholder="Enter phone number" required maxlength="12" inputmode="numeric" pattern="[0-9]{1,12}" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,12);">
        </div>
    </div>
    <?php if ($tracker_variant === 'team'): ?>
    <div class="col-md-6"><label class="form-label">Business Type</label><select name="business_type" id="business_type_quick" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Approached For</label><select name="approached_for" id="approached_for_quick" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Followed-Up Method</label><select name="followup_method" id="followup_method_quick" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="status_select" class="form-select"></select></div>
    <?php else: ?>
    <div class="col-md-6"><label class="form-label">Label</label><select name="label_tag" id="label_tag" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Source</label><select name="source" id="source_select" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="status_select" class="form-select"></select></div>
    <?php endif; ?>
    <div class="col-12"><label class="form-label">Comment</label><textarea name="comments" id="comments_quick" class="form-control" rows="2" placeholder="Notes (optional)"></textarea></div>
</div>
<button class="btn btn-link px-0 mt-2" type="button" id="toggleAdditionalBtn">+ Additional Details (Optional)</button>
</div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn tracker-btn-yellow">Save Customer</button></div>
</form></div></div></div>

<div class="modal fade tracker-modal" id="customerFullModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content"><form method="post">
<div class="modal-header"><h5 class="modal-title">Add Customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<input type="hidden" name="action" value="save_customer"><input type="hidden" name="customer_id" id="customer_id_full">
<div class="section-title"><i class="fa fa-user-circle me-1"></i> Basic Information</div>
<div class="row g-2">
    <div class="col-md-6"><label class="form-label">Customer Name *</label><div class="tracker-input-wrap"><i class="fa fa-user field-icon"></i><input name="customer_name" id="customer_name_full" class="form-control" placeholder="Enter customer name" required></div></div>
    <div class="col-md-6"><label class="form-label">Phone Number *</label><div class="input-group phone-group"><span class="input-group-text bg-white border-end-0"><i class="fa fa-phone text-muted"></i></span><select class="form-select country-code-select border-start-0 border-end-0"><option value="+91" selected>+91</option></select><input name="phone_number" id="phone_number_full" class="form-control phone-input" placeholder="Enter phone number" required maxlength="12" inputmode="numeric" pattern="[0-9]{1,12}" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,12);"></div></div>
    <?php if ($tracker_variant === 'team'): ?>
    <div class="col-md-6"><label class="form-label">Business Type</label><select name="business_type" id="business_type_full" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Approached For</label><select name="approached_for" id="approached_for_full" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Followed-Up Method</label><select name="followup_method" id="followup_method_full" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="status_select_full" class="form-select"></select></div>
    <?php else: ?>
    <div class="col-md-6"><label class="form-label">Label</label><select name="label_tag" id="label_tag_full" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Source</label><select name="source" id="source_select_full" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="status_select_full" class="form-select"></select></div>
    <?php endif; ?>
</div>
<div class="section-title mt-3"><i class="fa fa-list-check me-1"></i> Additional Details (Optional)</div>
<div class="row g-2">
    <?php if ($tracker_variant === 'team'): ?>
    <div class="col-md-6"><label class="form-label">Source</label><select name="source" id="source_select_advanced" class="form-select"></select></div>
    <?php endif; ?>
    <div class="col-md-6"><label class="form-label">Email ID</label><input type="email" name="email_id" id="email_id_full" class="form-control" placeholder="Enter email address"></div>
    <div class="col-md-6"><label class="form-label">Company Name</label><input name="company_name" id="company_name_full" class="form-control" placeholder="Enter company name"></div>
    <div class="col-md-6"><label class="form-label">Website</label><input name="website" id="website_full" class="form-control" placeholder="Enter website URL"></div>
    <div class="col-md-6"><label class="form-label">Address</label><input name="address_line1" id="address_line1_full" class="form-control" placeholder="House no., Building, Street, area, city"></div>
    <?php if ($tracker_variant === 'team'): ?>
    <div class="col-md-6"><label class="form-label">Comment</label><textarea name="comments" id="comments_full" class="form-control" rows="2" placeholder="Notes (optional)"></textarea></div>
    <?php else: ?>
    <div class="col-md-6"><label class="form-label">Comment</label><textarea name="comments" id="comments_full" class="form-control" rows="2" placeholder="Notes (optional)"></textarea></div>
    <?php endif; ?>
</div>
<input type="hidden" name="area_city" id="area_city_hidden" value="">
</div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn tracker-btn-yellow">Save Customer</button></div>
</form></div></div></div>

<div class="modal fade tracker-modal" id="shareOfferModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title wa-action-title"><i class="fa-brands fa-whatsapp"></i> <span id="waPopupTitleText">Send WhatsApp Message</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body">
    <div class="row g-3 g-lg-4">
        <div class="col-12 col-lg-7 order-1">
            <label class="form-label" for="offerMessage">Message</label>
            <div class="wa-message-field">
                <textarea id="offerMessage" class="form-control" rows="12" maxlength="2000" placeholder="Your messageâ€¦"><?php echo htmlspecialchars($preview_template); ?></textarea>
                <div class="wa-char-count" id="offerMessageCharWrap" aria-hidden="true"><span id="offerMessageCharCount">0</span>/2000</div>
            </div>
            <p class="small text-muted mt-2 mb-0">Placeholders: [Business Name], [Area, City], [MiniWebsite Link], [Customer Name]</p>
            <div class="tracker-subtle-box mt-3">
                <strong>Use templates for fast messaging</strong>
                <ul class="mt-2 mb-0">
                    <li>Save your custom message as template.</li>
                    <li>Set any template as default for quick use.</li>
                </ul>
            </div>
        </div>
        <div class="col-12 col-lg-5 order-2 order-lg-2">
            <div class="wa-template-head">
                <span class="wa-template-title">Choose Template</span>
            </div>
            <div id="waTemplateList" role="radiogroup" aria-label="Templates"></div>
        </div>
    </div>
</div>
<div class="modal-footer wa-popup-footer flex-column flex-md-row align-items-stretch align-items-md-center justify-content-md-end">
    <button type="button" id="sendCurrentBtn" class="btn btn-success order-md-1"><i class="fa-brands fa-whatsapp me-1"></i>Send on WhatsApp</button>
    <button type="button" id="saveAsTemplateBtn" class="btn btn-outline-primary order-md-2">Save Template</button>
    <button type="button" id="setDefaultTemplateBtn" class="btn btn-outline-primary order-md-3">Save as Default</button>
    <button type="button" class="btn btn-danger order-md-4" data-bs-dismiss="modal">Cancel</button>
</div>
</div></div></div>

<div class="modal fade" id="leadCaptureModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post">
<div class="modal-header"><h5 class="modal-title">New customer inquiry received</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><p>Save this customer?</p><input type="hidden" name="action" value="save_customer"><input type="hidden" name="source" value="WhatsApp"><div class="mb-2"><label>Name</label><input name="customer_name" class="form-control" value="New Inquiry"></div><div class="mb-2"><label>Label</label><input name="label_tag" class="form-control" maxlength="18" value="New"></div><div class="mb-2"><label>Phone Number *</label><input name="phone_number" class="form-control" required maxlength="12" inputmode="numeric" pattern="[0-9]{1,12}" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,12);"></div></div>







<div class="modal-footer"><button class="btn btn-primary">Save Customer</button></div>
</form></div></div></div>

<script>
// Keep Bootstrap modals attached to <body> so they center against full viewport,
// not inside sidebar/content container.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#customerModal, #customerFullModal, #shareOfferModal, #leadCaptureModal').forEach(function (modalEl) {
        if (modalEl && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
    });

    var flash = document.getElementById('trackerFlashMessage');
    if (flash) {
        setTimeout(function () {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                bootstrap.Alert.getOrCreateInstance(flash).close();
            } else {
                flash.remove();
            }
        }, 3500);
    }
});

(function initTrackerSearchBar() {
    var form = document.getElementById('customerTrackerSearchForm');
    var input = document.getElementById('trackerSearchInput');
    var clearBtn = document.getElementById('trackerSearchInputClear');
    if (!form || !input || !clearBtn) return;
    var clearFilterHref = <?php echo json_encode($tracker_clear_search_href); ?>;
    function updateClearVisibility() {
        clearBtn.hidden = (input.value || '').trim().length === 0;
    }
    function onClearClick() {
        var hasFilterInUrl = window.location.search.indexOf('search=') !== -1;
        if (hasFilterInUrl) {
            window.location.href = clearFilterHref;
        } else {
            input.value = '';
            input.focus();
            updateClearVisibility();
        }
    }
    input.addEventListener('input', updateClearVisibility);
    input.addEventListener('keyup', updateClearVisibility);
    clearBtn.addEventListener('click', onClearClick);
    updateClearVisibility();
})();

const businessName = <?php echo json_encode($business_name); ?>;
const businessAreaCity = <?php echo json_encode($area_city_business); ?>;
const miniLink = <?php echo json_encode($mini_link); ?>;
const defaultShareTemplate = <?php echo json_encode($default_template); ?>;
const defaultWhatsAppTemplate = <?php echo json_encode($default_whatsapp_template); ?>;
const templateStorageKey = 'mw_wa_templates_<?php echo (int)$owner_id; ?>';
const customersData = <?php echo json_encode(array_map(function($r){ return ['id'=>(int)$r['id'], 'name'=>$r['customer_name'], 'phone'=>$r['phone_number']]; }, $customers)); ?>;
const trackerVariant = <?php echo json_encode($tracker_variant); ?>;
const selectLabel = document.getElementById('label_tag');
const selectSource = document.getElementById('source_select');
const toggleAdditionalBtn = document.getElementById('toggleAdditionalBtn');
const customerModalEl = document.getElementById('customerModal');
const customerFullModalEl = document.getElementById('customerFullModal');
const selectLabelFull = document.getElementById('label_tag_full');
const selectSourceFull = document.getElementById('source_select_full');
const selectSourceAdvanced = document.getElementById('source_select_advanced');
const statusSelectFull = document.getElementById('status_select_full');
const statusSelectQuick = document.getElementById('status_select');
const commentsQuickEl = document.getElementById('comments_quick');
const commentsFullEl = document.getElementById('comments_full');
const areaCityHiddenEl = document.getElementById('area_city_hidden');
const waModalEl = document.getElementById('shareOfferModal');
let activeCustomer = null;
let activeContext = 'share_offer';
let selectedTemplateId = '';
let waTemplates = null;

function getModalInstance(modalEl) {
    if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return null;
    return bootstrap.Modal.getOrCreateInstance(modalEl);
}

function safeSlice(v, max) { return (v || '').toString().trim().slice(0, max); }
function ensureSelectHasOption(selectEl, value, maxLen) {
    maxLen = maxLen == null ? 18 : maxLen;
    const safeVal = safeSlice(value, maxLen);
    if (!safeVal) return;
    if (![...selectEl.options].some(opt => opt.value === safeVal)) {
        const customOpt = document.createElement('option');
        customOpt.value = safeVal;
        customOpt.textContent = safeVal;
        selectEl.insertBefore(customOpt, selectEl.querySelector('option[value="__custom__"]'));
    }
    selectEl.value = safeVal;
}
function setupCustomizableSelect(selectEl, options, maxCustomLen) {
    maxCustomLen = maxCustomLen == null ? 18 : maxCustomLen;
    selectEl.innerHTML = '';
    const optgroups = {};
    options.forEach(function (opt) {
        const normalized = (opt && typeof opt === 'object')
            ? { value: (opt.value ?? ''), label: (opt.label ?? opt.value ?? ''), group: (opt.group ?? '') }
            : { value: opt, label: opt, group: '' };
        const option = document.createElement('option');
        option.value = normalized.value;
        option.textContent = normalized.label;
        if (normalized.group) {
            if (!optgroups[normalized.group]) {
                const grp = document.createElement('optgroup');
                grp.label = normalized.group;
                optgroups[normalized.group] = grp;
                selectEl.appendChild(grp);
            }
            optgroups[normalized.group].appendChild(option);
        } else {
            selectEl.appendChild(option);
        }
    });
    const custom = document.createElement('option');
    custom.value = '__custom__';
    custom.textContent = 'Add Custom';
    selectEl.appendChild(custom);
    selectEl.addEventListener('change', function () {
        if (this.value !== '__custom__') return;
        const userValue = safeSlice(window.prompt('Enter custom value (max ' + maxCustomLen + ' characters):', ''), maxCustomLen);
        if (userValue) {
            ensureSelectHasOption(this, userValue, maxCustomLen);
        } else {
            this.selectedIndex = 0;
        }
    });
}
const sourceOptionsList = ['Direct', 'WhatsApp', 'Referral', 'Existing Contact', 'Walk-in', 'Website'];
const labelOptionsList = ['Regular', 'New', 'High Value', 'Repeat Customer'];
const businessTypeOptionsList = <?php echo json_encode(!empty($business_primary_options) ? $business_primary_options : [
    ['value' => '', 'label' => '-- Select Primary Category --'],
    ['value' => 'Retail', 'label' => 'Retail'],
    ['value' => 'Wholesale', 'label' => 'Wholesale'],
    ['value' => 'Service', 'label' => 'Service'],
    ['value' => 'Online', 'label' => 'Online'],
    ['value' => 'Manufacturing', 'label' => 'Manufacturing'],
    ['value' => 'Other', 'label' => 'Other']
], JSON_UNESCAPED_UNICODE); ?>;
const approachedForOptionsList = ['Product Demo', 'Pricing / Quote', 'Partnership', 'Support', 'General Inquiry', 'Other'];
const followupMethodOptionsList = ['Phone Call', 'WhatsApp', 'Email', 'In Person', 'Not Followed Up Yet', 'Other'];
const statusOptions = ['Followup required', 'Phone Busy/Not Picked', 'Important', 'Deal Done', 'Profile Shared', 'Interested', 'Not Interested'];

if (trackerVariant === 'customer') {
    if (selectLabel) setupCustomizableSelect(selectLabel, labelOptionsList);
    if (selectSource) setupCustomizableSelect(selectSource, sourceOptionsList);
    if (selectLabelFull) setupCustomizableSelect(selectLabelFull, labelOptionsList);
    if (selectSourceFull) setupCustomizableSelect(selectSourceFull, sourceOptionsList);
} else {
    ['business_type_quick', 'business_type_full'].forEach(function (tid) {
        const el = document.getElementById(tid);
        if (el) setupCustomizableSelect(el, businessTypeOptionsList, 80);
    });
    ['approached_for_quick', 'approached_for_full'].forEach(function (tid) {
        const el = document.getElementById(tid);
        if (el) setupCustomizableSelect(el, approachedForOptionsList, 80);
    });
    ['followup_method_quick', 'followup_method_full'].forEach(function (tid) {
        const el = document.getElementById(tid);
        if (el) setupCustomizableSelect(el, followupMethodOptionsList, 80);
    });
    if (selectSourceAdvanced) setupCustomizableSelect(selectSourceAdvanced, sourceOptionsList);
}
if (statusSelectFull) {
    setupCustomizableSelect(statusSelectFull, statusOptions, 18);
}
if (statusSelectQuick) {
    setupCustomizableSelect(statusSelectQuick, statusOptions, 18);
}

function toggleAdditionalDetails() {
    if (!customerFullModalEl) return;
    customer_id_full.value = customer_id.value || '';
    customer_name_full.value = customer_name.value || '';
    phone_number_full.value = phone_number.value || '';
    if (trackerVariant === 'customer') {
        if (selectLabelFull && selectLabel) ensureSelectHasOption(selectLabelFull, selectLabel.value || 'Regular');
        if (selectSourceFull && selectSource) ensureSelectHasOption(selectSourceFull, selectSource.value || 'Direct');
    } else {
        [['business_type_full', 'business_type_quick'], ['approached_for_full', 'approached_for_quick'], ['followup_method_full', 'followup_method_quick']].forEach(function (pair) {
            const fullEl = document.getElementById(pair[0]);
            const quickEl = document.getElementById(pair[1]);
            if (fullEl && quickEl) ensureSelectHasOption(fullEl, quickEl.value || '', 80);
        });
    }
    if (commentsFullEl && commentsQuickEl) commentsFullEl.value = commentsQuickEl.value || '';
    if (statusSelectFull && statusSelectQuick) {
        ensureSelectHasOption(statusSelectFull, statusSelectQuick.value || 'Followup required', 40);
    }
    const quickModal = getModalInstance(customerModalEl);
    if (quickModal) quickModal.hide();
    const fullModal = getModalInstance(customerFullModalEl);
    if (fullModal) fullModal.show();
}

if (toggleAdditionalBtn && customerModalEl) {
    toggleAdditionalBtn.addEventListener('click', function (e) {
        e.preventDefault();
        toggleAdditionalDetails();
    });
}

if (customerModalEl) {
    customerModalEl.addEventListener('click', function (e) {
        const trigger = e.target.closest('#toggleAdditionalBtn');
        if (!trigger) return;
        e.preventDefault();
        toggleAdditionalDetails();
    });
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}
function getSeedTemplates() {
    return {
        defaultId: '',
        defaults: {
            share_offer: defaultShareTemplate,
            whatsapp: defaultWhatsAppTemplate
        },
        customs: [
            { id: 'custom_1', name: 'Custom Template 01', text: '', locked: false },
            { id: 'custom_2', name: 'Custom Template 02', text: '', locked: false },
            { id: 'custom_3', name: 'Custom Template 03', text: '', locked: false }
        ]
    };
}
function migrateOldTemplates(parsed) {
    const seed = getSeedTemplates();
    seed.defaultId = parsed.defaultId || '';
    if (parsed.defaults && parsed.customs && Array.isArray(parsed.customs)) {
        if (parsed.defaults.share_offer) seed.defaults.share_offer = parsed.defaults.share_offer;
        if (parsed.defaults.whatsapp) seed.defaults.whatsapp = parsed.defaults.whatsapp;
        parsed.customs.forEach(function (pc, i) {
            if (seed.customs[i] && pc && pc.text !== undefined) {
                seed.customs[i].text = pc.text || '';
            }
        });
    } else if (parsed.items && Array.isArray(parsed.items)) {
        parsed.items.forEach(function (x) {
            if (x.id === 'default_share_offer') seed.defaults.share_offer = x.text || seed.defaults.share_offer;
            if (x.id === 'default_whatsapp') seed.defaults.whatsapp = x.text || seed.defaults.whatsapp;
            if (x.id === 'custom_1' || x.id === 'custom_2' || x.id === 'custom_3') {
                const cu = seed.customs.find(function (c) { return c.id === x.id; });
                if (cu) cu.text = x.text || '';
            }
        });
    }
    var allowedDef = ['default', 'custom_1', 'custom_2', 'custom_3'];
    if (seed.defaultId === 'default_share_offer' || seed.defaultId === 'default_whatsapp') {
        seed.defaultId = 'default';
    }
    if (allowedDef.indexOf(seed.defaultId) === -1) {
        seed.defaultId = '';
    }
    return seed;
}
function loadTemplates() {
    try {
        const raw = localStorage.getItem(templateStorageKey);
        if (!raw) return getSeedTemplates();
        const parsed = JSON.parse(raw);
        if (!parsed) return getSeedTemplates();
        return migrateOldTemplates(parsed);
    } catch (e) {
        return getSeedTemplates();
    }
}
function saveTemplates() {
    localStorage.setItem(templateStorageKey, JSON.stringify({
        defaultId: waTemplates.defaultId,
        defaults: waTemplates.defaults,
        customs: waTemplates.customs
    }));
}
function getVisibleTemplateRows() {
    const ctx = activeContext === 'whatsapp' ? 'whatsapp' : 'share_offer';
    const defText = waTemplates.defaults[ctx];
    return [
        { id: 'default', name: 'Default Message', text: defText, locked: true },
        { id: waTemplates.customs[0].id, name: waTemplates.customs[0].name, text: waTemplates.customs[0].text, locked: false },
        { id: waTemplates.customs[1].id, name: waTemplates.customs[1].name, text: waTemplates.customs[1].text, locked: false },
        { id: waTemplates.customs[2].id, name: waTemplates.customs[2].name, text: waTemplates.customs[2].text, locked: false }
    ];
}
function getTemplateTextById(templateId) {
    const rows = getVisibleTemplateRows();
    const row = rows.find(function (r) { return r.id === templateId; });
    return row ? row.text : '';
}
function renderTemplateList() {
    const wrap = document.getElementById('waTemplateList');
    wrap.innerHTML = '';
    const rows = getVisibleTemplateRows();
    const savedDefaultSlot = waTemplates.defaultId || '';
    rows.forEach(function (item) {
        const div = document.createElement('div');
        div.className = 'template-item' + (selectedTemplateId === item.id ? ' active' : '');
        const checked = selectedTemplateId === item.id ? 'checked' : '';
        const isOpenDefault = savedDefaultSlot === item.id ? '<span class="template-default-badge">DEFAULT</span>' : '';
        const rawPrev = (item.text || '').replace(/\s+/g, ' ').trim().slice(0, 80);
        const previewHtml = rawPrev ? escapeHtml(rawPrev) : '<span class="text-muted">Empty template</span>';
        div.innerHTML = '<label class="w-100 d-flex gap-2 align-items-start" style="cursor:pointer;">'
            + '<input type="radio" name="waTemplateRadio" value="' + escapeHtml(item.id) + '" ' + checked + '>'
            + '<span class="w-100"><span class="template-name-row"><span class="template-name">' + escapeHtml(item.name) + '</span>' + isOpenDefault + '</span><div class="template-preview">' + previewHtml + '</div></span>'
            + '</label>';
        wrap.appendChild(div);
    });
    wrap.querySelectorAll('input[name="waTemplateRadio"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            selectedTemplateId = this.value;
            document.getElementById('offerMessage').value = getTemplateTextById(selectedTemplateId);
            updateOfferMessageCharCount();
            renderTemplateList();
        });
    });
}
function applyTemplateText() {
    const rows = getVisibleTemplateRows();
    let pick = waTemplates.defaultId;
    const allowed = ['default', 'custom_1', 'custom_2', 'custom_3'];
    if (!pick || allowed.indexOf(pick) === -1) {
        pick = 'default';
    }
    selectedTemplateId = pick;
    const selected = rows.find(function (r) { return r.id === pick; });
    document.getElementById('offerMessage').value = selected ? selected.text : '';
    updateOfferMessageCharCount();
    renderTemplateList();
}
function saveSelectedTemplateFromEditor() {
    const text = document.getElementById('offerMessage').value;
    const ctx = activeContext === 'whatsapp' ? 'whatsapp' : 'share_offer';
    if (selectedTemplateId === 'default') {
        waTemplates.defaults[ctx] = text;
    } else {
        const target = waTemplates.customs.find(function (x) { return x.id === selectedTemplateId; });
        if (!target) return;
        target.text = text;
    }
    saveTemplates();
    renderTemplateList();
    alert('Template saved.');
}
function updateOfferMessageCharCount() {
    const ta = document.getElementById('offerMessage');
    const n = document.getElementById('offerMessageCharCount');
    if (!ta || !n) return;
    const len = (ta.value || '').length;
    n.textContent = String(len);
}
function resolveMessageWithTags(rawMessage, customerName) {
    return rawMessage
        .replace(/\[Business Name\]/g, businessName)
        .replace(/\[Area, City\]/g, businessAreaCity)
        .replace(/\[MiniWebsite Link\]/g, miniLink)
        .replace(/\[Customer Name\]/g, customerName || 'Customer');
}
function recordLastShared(customerId) {
    var id = parseInt(customerId, 10) || 0;
    if (id <= 0) return;
    var fd = new FormData();
    fd.append('action', 'record_last_shared');
    fd.append('customer_id', String(id));
    fetch('index.php', { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {});
}
function openWaComposer(customer, contextType) {
    activeCustomer = customer;
    activeContext = contextType;
    const titleEl = document.getElementById('waPopupTitleText');
    if (titleEl) titleEl.textContent = contextType === 'whatsapp' ? 'Send WhatsApp Message' : 'Share Offer on WhatsApp';
    waTemplates = loadTemplates();
    applyTemplateText();
    const waModal = getModalInstance(waModalEl);
    if (waModal) waModal.show();
}

document.getElementById('openCustomerModalBtn').addEventListener('click', function () {
    customer_id.value = '';
    customer_name.value = '';
    phone_number.value = '';
    toggleAdditionalBtn.textContent = '+ Additional Details (Optional)';
    customerModalEl.classList.remove('expanded-view');
    customer_id_full.value = '';
    customer_name_full.value = '';
    phone_number_full.value = '';
    email_id_full.value = '';
    company_name_full.value = '';
    website_full.value = '';
    address_line1_full.value = '';
    if (areaCityHiddenEl) areaCityHiddenEl.value = '';
    if (statusSelectFull) statusSelectFull.selectedIndex = 0;
    if (statusSelectQuick) statusSelectQuick.selectedIndex = 0;
    if (trackerVariant === 'customer') {
        if (selectLabel) selectLabel.selectedIndex = 0;
        if (selectSource) selectSource.selectedIndex = 0;
        if (selectLabelFull) selectLabelFull.selectedIndex = 0;
        if (selectSourceFull) selectSourceFull.selectedIndex = 0;
    } else {
        ['business_type_quick', 'business_type_full', 'approached_for_quick', 'approached_for_full', 'followup_method_quick', 'followup_method_full'].forEach(function (tid) {
            const el = document.getElementById(tid);
            if (el) el.selectedIndex = 0;
        });
        if (selectSourceAdvanced) selectSourceAdvanced.selectedIndex = 0;
    }
    if (commentsQuickEl) commentsQuickEl.value = '';
    if (commentsFullEl) commentsFullEl.value = '';
});

document.querySelectorAll('.edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        customer_id_full.value = this.dataset.id || '';
        customer_name_full.value = this.dataset.name || '';
        phone_number_full.value = this.dataset.phone || '';
        email_id_full.value = this.dataset.email || '';
        company_name_full.value = this.dataset.company || '';
        website_full.value = this.dataset.website || '';
        address_line1_full.value = this.dataset.line1 || '';
        if (areaCityHiddenEl) areaCityHiddenEl.value = this.dataset.areacity || '';
        if (trackerVariant === 'team') {
            var btf = document.getElementById('business_type_full');
            var apf = document.getElementById('approached_for_full');
            var fmf = document.getElementById('followup_method_full');
            if (btf) ensureSelectHasOption(btf, this.dataset.businessType || '', 80);
            if (apf) ensureSelectHasOption(apf, this.dataset.approachedFor || '', 80);
            if (fmf) ensureSelectHasOption(fmf, this.dataset.followupMethod || '', 80);
            if (selectSourceAdvanced) ensureSelectHasOption(selectSourceAdvanced, this.dataset.source || 'Direct');
            var commentValTeam = this.dataset.comments || '';
            if (commentsFullEl) commentsFullEl.value = commentValTeam;
            if (commentsQuickEl) commentsQuickEl.value = commentValTeam;
        } else {
            if (selectLabelFull) ensureSelectHasOption(selectLabelFull, this.dataset.label || 'Regular');
            if (selectSourceFull) ensureSelectHasOption(selectSourceFull, this.dataset.source || 'Direct');
            var commentVal = this.dataset.comments || '';
            if (commentsFullEl) commentsFullEl.value = commentVal;
            if (commentsQuickEl) commentsQuickEl.value = commentVal;
        }
        if (statusSelectFull) ensureSelectHasOption(statusSelectFull, this.dataset.status || 'Followup required', 40);
        const customerFullModal = getModalInstance(customerFullModalEl);
        if (customerFullModal) customerFullModal.show();
    });
});

document.querySelectorAll('.wa-normal-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        openWaComposer({ id: parseInt(this.dataset.id, 10) || 0, name: this.dataset.name || 'Customer', phone: this.dataset.phone || '' }, 'whatsapp');
    });
});
document.querySelectorAll('.single-offer-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const cid = parseInt(this.dataset.id, 10);
        const customer = customersData.find(function (c) { return c.id === cid; });
        if (!customer) return;
        openWaComposer(customer, 'share_offer');
    });
});

document.getElementById('saveAsTemplateBtn').addEventListener('click', function () {
    saveSelectedTemplateFromEditor();
});
document.getElementById('setDefaultTemplateBtn').addEventListener('click', function () {
    waTemplates.defaultId = selectedTemplateId || 'default';
    saveTemplates();
    renderTemplateList();
});
(function bindOfferMessageCounter() {
    const ta = document.getElementById('offerMessage');
    if (ta) {
        ta.addEventListener('input', updateOfferMessageCharCount);
        updateOfferMessageCharCount();
    }
})();
document.getElementById('sendCurrentBtn').addEventListener('click', function () {
    if (!activeCustomer || !activeCustomer.phone) return;
    recordLastShared(activeCustomer.id);
    const phone = (activeCustomer.phone || '').replace(/\D/g, '');
    const message = resolveMessageWithTags(document.getElementById('offerMessage').value, activeCustomer.name || 'Customer');
    window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(message), '_blank');
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
