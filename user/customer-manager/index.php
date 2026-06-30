<?php
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/role_helper.php');
require_once(__DIR__ . '/../../app/helpers/role_access_helper.php');
require_once(__DIR__ . '/../../app/helpers/access_control.php');
require_once(__DIR__ . '/../../app/includes/product_categories_helper.php');

require_login('/login/customer.php');
require_page_access('/customer-manager');

$current_role = get_current_user_role();
$collaboration_enabled = isset($_SESSION['collaboration_enabled']) && $_SESSION['collaboration_enabled'];

$ras_cm = get_current_user_role_access_settings($connect);
$profile_key_cm = $ras_cm['profile_key'] ?? null;
$user_email_cm = get_user_email() ?? '';
if (!is_role_access_feature_visible_for_user($connect, $profile_key_cm, 'customer_manager', 'text', $user_email_cm, $current_role)) {
    header('Location: ../dashboard/');
    exit;
}

$can_use_team_portal = (
    $current_role === 'TEAM'
    || $current_role === 'FRANCHISEE'
    || ($current_role === 'CUSTOMER' && $collaboration_enabled)
);

if (defined('MW_TRACKER_PORTAL')) {
    $tracker_portal = MW_TRACKER_PORTAL;
} elseif ($can_use_team_portal) {
    $tracker_portal = 'team';
} else {
    $tracker_portal = 'customer';
}

if ($tracker_portal === 'customer') {
    if ($current_role !== 'CUSTOMER') {
        header('Location: /login/customer.php');
        exit;
    }
    $tracker_variant = 'customer';
    $owner_role_db = 'CUSTOMER';
} else {
    if (!$can_use_team_portal) {
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

$show_approached_for = ($tracker_variant === 'team' && $current_role !== 'FRANCHISEE');

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
$connect->query("CREATE TABLE IF NOT EXISTS mw_customer_followups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    owner_user_id INT UNSIGNED NOT NULL,
    owner_role VARCHAR(20) NOT NULL,
    followup_datetime DATETIME NOT NULL,
    followup_method VARCHAR(150) NOT NULL DEFAULT '',
    followup_status VARCHAR(40) NOT NULL DEFAULT '',
    comments TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_owner (customer_id, owner_user_id, owner_role),
    INDEX idx_followup_datetime (followup_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

function mw_get_customer_followups(mysqli $connect, int $customerId, int $ownerId, string $ownerRole): array {
    $rows = [];
    $stmt = $connect->prepare(
        "SELECT id, followup_datetime, followup_method, followup_status, comments
         FROM mw_customer_followups
         WHERE customer_id = ? AND owner_user_id = ? AND owner_role = ?
         ORDER BY followup_datetime DESC, id DESC"
    );
    if ($stmt) {
        $stmt->bind_param('iis', $customerId, $ownerId, $ownerRole);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmt->close();
    }
    return $rows;
}

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

function mw_tracker_render_table_cell(string $key, array $c): string {
    switch ($key) {
        case 'created_at':
            return date('d-m-Y', strtotime($c['created_at']));
        case 'customer_name':
            return htmlspecialchars($c['customer_name']);
        case 'phone_number':
            return htmlspecialchars($c['phone_number']);
        case 'business_type':
            $bt = trim((string)($c['business_type'] ?? ''));
            return $bt !== '' ? htmlspecialchars($bt) : '-';
        case 'approached_for':
            $af = trim((string)($c['approached_for'] ?? ''));
            return $af !== '' ? htmlspecialchars($af) : '-';
        case 'followup_method':
            $fm = trim((string)($c['followup_method'] ?? ''));
            return $fm !== '' ? htmlspecialchars($fm) : '-';
        case 'label_tag':
            return htmlspecialchars($c['label_tag'] ?: 'Regular');
        case 'source':
            $src = trim((string)($c['source'] ?? ''));
            return $src !== '' ? htmlspecialchars($src) : '-';
        case 'email_id':
            $em = trim((string)($c['email_id'] ?? ''));
            return $em !== '' ? htmlspecialchars($em) : '-';
        case 'company_name':
            $co = trim((string)($c['company_name'] ?? ''));
            return $co !== '' ? htmlspecialchars($co) : '-';
        case 'website':
            $web = trim((string)($c['website'] ?? ''));
            return $web !== '' ? htmlspecialchars($web) : '-';
        case 'address':
            $a1 = trim((string)($c['address_line1'] ?? ''));
            $ac = trim((string)($c['area_city'] ?? ''));
            $addrMerged = trim($a1 . (($a1 !== '' && $ac !== '') ? ', ' : '') . $ac);
            return $addrMerged !== '' ? htmlspecialchars($addrMerged) : '-';
        case 'last_shared_at':
            return htmlspecialchars(mw_format_last_shared($c['last_shared_at'] ?? null));
        case 'last_updated':
            $lu = $c['last_updated'] ?? '';
            if ($lu === '' || $lu === '0000-00-00 00:00:00') {
                return '-';
            }
            $t = strtotime((string)$lu);
            return $t !== false ? date('d-m-Y H:i', $t) : '-';
        case 'comments':
            $comm = (string)($c['comments'] ?? '');
            return trim($comm) === '' ? '-' : htmlspecialchars($comm);
        case 'status':
            $st = trim((string)($c['status'] ?? ''));
            return $st !== '' ? htmlspecialchars($st) : '-';
        default:
            return '-';
    }
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
        if ($tracker_variant === 'team' && $id > 0 && $comments === '') {
            $stmtComments = $connect->prepare("SELECT comments FROM mw_customers WHERE id = ? AND owner_user_id = ? AND owner_role = ? LIMIT 1");
            if ($stmtComments) {
                $stmtComments->bind_param('iis', $id, $owner_id, $owner_role_db);
                $stmtComments->execute();
                $resComments = $stmtComments->get_result();
                if ($resComments && ($rowComments = $resComments->fetch_assoc())) {
                    $comments = (string)($rowComments['comments'] ?? '');
                }
                $stmtComments->close();
            }
        }
        $customerSourcePredefined = ['Direct', 'WhatsApp', 'Referral', 'Existing Contact', 'Walk-In', 'Website'];
        $teamSourcePredefined = ['Direct', 'WhatsApp', 'Referral'];
        $sourceRaw = trim((string)($_POST['source'] ?? 'Direct'));
        if ($tracker_variant === 'customer') {
            $source = in_array($sourceRaw, $customerSourcePredefined, true)
                ? mw_limit_text($sourceRaw, 18)
                : mw_limit_text($sourceRaw, 18);
        } else {
            $source = in_array($sourceRaw, $teamSourcePredefined, true)
                ? mw_limit_text($sourceRaw, 18)
                : mw_limit_text($sourceRaw, 18);
        }
        $customerStatusPredefined = ['Followup required', 'Phone Busy/Not Picked', 'Important', 'Deal Done', 'Profile Shared', 'Interested', 'Not Interested'];
        $teamStatusPredefined = ['Followup required', 'Phone Busy/Not Picked', 'Important', 'Joined', 'Interested', 'Not Interested'];
        $statusPredefined = ($tracker_variant === 'customer') ? $customerStatusPredefined : $teamStatusPredefined;
        $statusRaw = trim((string)($_POST['status'] ?? 'Followup required'));
        $status = in_array($statusRaw, $statusPredefined, true)
            ? mw_limit_text($statusRaw, 40)
            : mw_limit_text($statusRaw, 40);
        $businessType = mw_limit_text($_POST['business_type'] ?? '', 150);
        $approachedFor = mw_limit_text($_POST['approached_for'] ?? '', 150);
        if ($current_role === 'FRANCHISEE') {
            $approachedFor = '';
        } elseif ($show_approached_for) {
            $allowedApproached = ['MW Sales', 'Franchise Sale'];
            if ($approachedFor !== '' && !in_array($approachedFor, $allowedApproached, true)) {
                $approachedFor = '';
            }
        }
        $followupMethod = mw_limit_text($_POST['followup_method'] ?? '', 150);
        $teamFollowupPredefined = ['Call', 'Visited', 'WhatsApp', 'Email'];
        if ($tracker_variant === 'customer') {
            $followupMethod = '';
            if ($id > 0) {
                $stmtFollowup = $connect->prepare("SELECT followup_method FROM mw_customers WHERE id = ? AND owner_user_id = ? AND owner_role = ? LIMIT 1");
                if ($stmtFollowup) {
                    $stmtFollowup->bind_param('iis', $id, $owner_id, $owner_role_db);
                    $stmtFollowup->execute();
                    $resFollowup = $stmtFollowup->get_result();
                    if ($resFollowup && ($rowFollowup = $resFollowup->fetch_assoc())) {
                        $followupMethod = mw_limit_text((string)($rowFollowup['followup_method'] ?? ''), 150);
                    }
                    $stmtFollowup->close();
                }
            }
        } elseif ($tracker_variant === 'team') {
            if ($followupMethod !== '' && !in_array($followupMethod, $teamFollowupPredefined, true)) {
                $followupMethod = '';
            }
        }
        if ($id > 0 && $tracker_variant === 'team') {
            $stmtExisting = $connect->prepare("SELECT customer_name, phone_number FROM mw_customers WHERE id = ? AND owner_user_id = ? AND owner_role = ? LIMIT 1");
            if ($stmtExisting) {
                $stmtExisting->bind_param('iis', $id, $owner_id, $owner_role_db);
                $stmtExisting->execute();
                $resExisting = $stmtExisting->get_result();
                $rowExisting = ($resExisting && ($tmp = $resExisting->fetch_assoc())) ? $tmp : null;
                $stmtExisting->close();
                if (!$rowExisting) {
                    header('Location: index.php?msg=validation_error');
                    exit;
                }
                $name = trim((string)($rowExisting['customer_name'] ?? ''));
                $phone = mw_phone_clean(trim((string)($rowExisting['phone_number'] ?? '')));
            } else {
                header('Location: index.php?msg=validation_error');
                exit;
            }
        }
        if ($name === '' || $phone === '') {
            header('Location: index.php?msg=validation_error');
            exit;
        } elseif (strlen($phone) > 12) {
            header('Location: index.php?msg=phone_max_error');
            exit;
        } else {
            if ($id > 0) {
                if ($tracker_variant === 'team') {
                    $stmt = $connect->prepare("UPDATE mw_customers SET label_tag=?, email_id=?, company_name=?, website=?, source=?, status=?, address_line1=?, area_city=?, comments=?, business_type=?, approached_for=?, followup_method=?, updated_at=NOW() WHERE id=? AND owner_user_id=? AND owner_role=?");
                    if ($stmt) {
                        $stmt->bind_param('ssssssssssssiis', $label, $emailId, $companyName, $website, $source, $status, $line1, $areaCity, $comments, $businessType, $approachedFor, $followupMethod, $id, $owner_id, $owner_role_db);
                        $stmt->execute();
                        $stmt->close();
                    }
                } else {
                    $stmt = $connect->prepare("UPDATE mw_customers SET customer_name=?, label_tag=?, phone_number=?, email_id=?, company_name=?, website=?, source=?, status=?, address_line1=?, area_city=?, comments=?, business_type=?, approached_for=?, followup_method=? WHERE id=? AND owner_user_id=? AND owner_role=?");
                    if ($stmt) {
                        $stmt->bind_param('ssssssssssssssiis', $name, $label, $phone, $emailId, $companyName, $website, $source, $status, $line1, $areaCity, $comments, $businessType, $approachedFor, $followupMethod, $id, $owner_id, $owner_role_db);
                        $stmt->execute();
                        $stmt->close();
                    }
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
    } elseif ($action === 'add_followup' && $tracker_variant === 'team') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $followupDatetimeRaw = trim((string)($_POST['followup_datetime'] ?? ''));
        $followupMethodFu = mw_limit_text($_POST['followup_method'] ?? '', 150);
        $followupStatusFu = mw_limit_text($_POST['followup_status'] ?? '', 40);
        $followupCommentsFu = trim((string)($_POST['followup_comments'] ?? ''));
        $teamFollowupMethods = ['Call', 'Visited', 'WhatsApp', 'Email'];
        $teamFollowupStatuses = ['Followup required', 'Phone Busy/Not Picked', 'Important', 'Joined', 'Interested', 'Not Interested'];
        $followupDatetime = '';
        if ($followupDatetimeRaw !== '') {
            $ts = strtotime($followupDatetimeRaw);
            if ($ts !== false) {
                $followupDatetime = date('Y-m-d H:i:s', $ts);
            }
        }
        if ($customerId <= 0 || $followupDatetime === '' || $followupMethodFu === '' || $followupStatusFu === '') {
            header('Location: index.php?msg=followup_validation_error');
            exit;
        }
        if (!in_array($followupMethodFu, $teamFollowupMethods, true)) {
            $followupMethodFu = mw_limit_text($followupMethodFu, 150);
        }
        if (!in_array($followupStatusFu, $teamFollowupStatuses, true)) {
            $followupStatusFu = mw_limit_text($followupStatusFu, 40);
        }
        $ins = $connect->prepare(
            "INSERT INTO mw_customer_followups (customer_id, owner_user_id, owner_role, followup_datetime, followup_method, followup_status, comments)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if ($ins) {
            $ins->bind_param('iisssss', $customerId, $owner_id, $owner_role_db, $followupDatetime, $followupMethodFu, $followupStatusFu, $followupCommentsFu);
            $ins->execute();
            $ins->close();
        }
        $upd = $connect->prepare(
            "UPDATE mw_customers SET status = ?, followup_method = ?, comments = ?, updated_at = NOW()
             WHERE id = ? AND owner_user_id = ? AND owner_role = ?"
        );
        if ($upd) {
            $upd->bind_param('sssiis', $followupStatusFu, $followupMethodFu, $followupCommentsFu, $customerId, $owner_id, $owner_role_db);
            $upd->execute();
            $upd->close();
        }
        header('Location: index.php?msg=followup_added');
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
    } elseif ($msg === 'followup_added') {
        $message = 'Followup added successfully.';
        $message_type = 'success';
    } elseif ($msg === 'followup_validation_error') {
        $message = 'Please fill Date & Time, Followup Method, and Status.';
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
    'last_updated' => 'last_updated',
];
if (!isset($sortable_columns[$sort_by])) {
    $sort_by = 'created_at';
}
$tracker_clear_search_href = '?' . http_build_query([
    'sort_by' => $sort_by,
    'sort_dir' => strtolower($sort_dir),
]);
$trackerThSort = function (string $col, string $label) use ($search, $sort_by, $sort_dir, $sortable_columns, $tracker_variant): string {
    if ($tracker_variant !== 'team' && $col === 'last_updated') {
        return '';
    }
    if (!isset($sortable_columns[$col])) {
        return '<th>' . htmlspecialchars($label) . '</th>';
    }
    $nextDir = ($sort_by === $col && $sort_dir === 'ASC') ? 'desc' : 'asc';
    $iconClass = ($sort_by === $col) ? ($sort_dir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';
    $href = '?' . http_build_query(['search' => $search, 'sort_by' => $col, 'sort_dir' => $nextDir]);
    return '<th><a href="' . htmlspecialchars($href) . '">' . htmlspecialchars($label) . ' <i class="fa ' . $iconClass . ' sort-icon"></i></a></th>';
};

if ($tracker_variant === 'team') {
    $team_table_tail = [
        ['key' => 'followup_method', 'label' => 'Follow-up Method'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'source', 'label' => 'Source'],
        ['key' => 'email_id', 'label' => 'Email ID'],
        ['key' => 'company_name', 'label' => 'Company Name'],
        ['key' => 'website', 'label' => 'Website'],
        ['key' => 'address', 'label' => 'Address'],
        ['key' => 'last_updated', 'label' => 'Last updated'],
        ['key' => 'comments', 'label' => 'Comment'],
    ];
    $team_table_lead = [
        ['key' => 'created_at', 'label' => 'Date Added'],
        ['key' => 'customer_name', 'label' => 'Customer Name'],
        ['key' => 'phone_number', 'label' => 'Phone Number'],
        ['key' => 'business_type', 'label' => 'Business Type'],
    ];
    if ($current_role === 'FRANCHISEE') {
        // Franchisee: no Approached For column
        $tracker_table_columns = array_merge($team_table_lead, $team_table_tail);
    } else {
        // Team / collaboration: Approached For after Business Type
        $tracker_table_columns = array_merge($team_table_lead, [
            ['key' => 'approached_for', 'label' => 'Approached For'],
        ], $team_table_tail);
    }
} else {
    $tracker_table_columns = [
        ['key' => 'created_at', 'label' => 'Date Added'],
        ['key' => 'customer_name', 'label' => 'Customer Name'],
        ['key' => 'phone_number', 'label' => 'Phone Number'],
        ['key' => 'label_tag', 'label' => 'Label'],
        ['key' => 'source', 'label' => 'Source'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'comments', 'label' => 'Comment'],
        ['key' => 'email_id', 'label' => 'Email ID'],
        ['key' => 'company_name', 'label' => 'Company Name'],
        ['key' => 'website', 'label' => 'Website'],
        ['key' => 'address', 'label' => 'Address'],
        ['key' => 'last_shared_at', 'label' => 'Last shared'],
    ];
}
$tracker_action_cols = ($tracker_variant === 'team') ? 1 : 2;
$tracker_table_colspan = 1 + count($tracker_table_columns) + $tracker_action_cols;

$lastUpdatedExpr = "COALESCE((SELECT MAX(f.followup_datetime) FROM mw_customer_followups f WHERE f.customer_id = c.id AND f.owner_user_id = c.owner_user_id AND f.owner_role = c.owner_role), c.updated_at)";
$customers = [];
if ($tracker_variant === 'team') {
    $sql = "SELECT c.*, ({$lastUpdatedExpr}) AS last_updated FROM mw_customers c WHERE c.owner_user_id = ? AND c.owner_role = ?";
    $teamSortColumns = [
        'created_at' => 'c.created_at',
        'customer_name' => 'c.customer_name',
        'phone_number' => 'c.phone_number',
        'label_tag' => 'c.label_tag',
        'source' => 'c.source',
        'email_id' => 'c.email_id',
        'company_name' => 'c.company_name',
        'website' => 'c.website',
        'address' => "CONCAT(IFNULL(c.address_line1,''), ' ', IFNULL(c.area_city,''))",
        'last_shared_at' => 'c.last_shared_at',
        'comments' => 'c.comments',
        'status' => 'c.status',
        'business_type' => 'c.business_type',
        'approached_for' => 'c.approached_for',
        'followup_method' => 'c.followup_method',
        'last_updated' => 'last_updated',
    ];
} else {
    $sql = "SELECT * FROM mw_customers WHERE owner_user_id = ? AND owner_role = ?";
    $teamSortColumns = null;
}
$types = 'is';
$args = [$owner_id, $owner_role_db];
if ($search !== '') {
    if ($tracker_variant === 'team') {
        $sql .= " AND (c.customer_name LIKE ? OR c.phone_number LIKE ? OR c.email_id LIKE ? OR c.company_name LIKE ? OR c.source LIKE ? OR c.website LIKE ? OR c.address_line1 LIKE ? OR c.area_city LIKE ? OR c.comments LIKE ? OR c.label_tag LIKE ? OR c.status LIKE ? OR c.business_type LIKE ? OR c.approached_for LIKE ? OR c.followup_method LIKE ?)";
    } else {
        $sql .= " AND (customer_name LIKE ? OR phone_number LIKE ? OR email_id LIKE ? OR company_name LIKE ? OR source LIKE ? OR website LIKE ? OR address_line1 LIKE ? OR area_city LIKE ? OR comments LIKE ? OR label_tag LIKE ? OR status LIKE ? OR business_type LIKE ? OR approached_for LIKE ? OR followup_method LIKE ?)";
    }
    $lk = '%' . $search . '%';
    $types .= str_repeat('s', 14);
    for ($si = 0; $si < 14; $si++) {
        $args[] = $lk;
    }
}
if ($tracker_variant === 'team') {
    $orderCol = $teamSortColumns[$sort_by] ?? 'c.created_at';
    $sql .= " ORDER BY {$orderCol} {$sort_dir}, c.id DESC";
} else {
    $sql .= " ORDER BY " . $sortable_columns[$sort_by] . " " . $sort_dir . ", id DESC";
}
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
require_once __DIR__ . '/../../common/mw_modal.php';
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
#customerModal .mw-modal-panel {
    max-width: 29.375rem;
}
#customerModal.expanded-view .mw-modal-panel {
    max-width: 860px;
}
#customerModal:not(.expanded-view) #customerNameCol,
#customerModal:not(.expanded-view) #phoneNumberCol {
    flex: 0 0 100%;
    max-width: 100%;
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
@media (max-width: 767.98px) {
    #offerMessage {
        font-size: 13px;
    }
}
</style>
<main class="Dashboard">
<div class="container-fluid customer_content_area">
    <div class="main-top mw-page-header">
        <h1 class="heading mw-page-title">Customer Tracker</h1>
    </div>
    <?php if ($message !== ''): ?>
        <div id="trackerFlashMessage" class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <div class="card mb-4"><div class="card-body">
        <div class="d-flex flex-wrap flex-md-nowrap justify-content-between align-items-center gap-2 mb-3">
            <button id="openCustomerModalBtn" class="btn btn-primary flex-shrink-0" type="button" data-mw-modal-open="customerModal"><i class="fa fa-plus"></i>Add Customer</button>
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
            <thead><tr>
                <th>SN</th>
                <?php foreach ($tracker_table_columns as $col): ?>
                    <?php echo $trackerThSort($col['key'], $col['label']); ?>
                <?php endforeach; ?>
                <th>Action</th><?php if ($tracker_variant !== 'team'): ?><th>Manage</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php if (empty($customers)): ?>
                <tr><td colspan="<?php echo (int)$tracker_table_colspan; ?>" class="text-center">No customers found.</td></tr>
            <?php else: $sn = 1; foreach ($customers as $c): ?>
                <tr>
                    <td><?php echo $sn++; ?></td>
                    <?php foreach ($tracker_table_columns as $col): ?>
                        <td<?php echo $col['key'] === 'comments' ? ' class="ct-table-comment"' : ''; ?>><?php echo mw_tracker_render_table_cell($col['key'], $c); ?></td>
                    <?php endforeach; ?>
                    <?php if ($tracker_variant === 'team'): ?>
                    <td class="ct-cell-action">
                        <div class="d-flex flex-wrap gap-1">
                             <button type="button" class="btn btn-sm btn-warning edit-btn" data-id="<?php echo (int)$c['id']; ?>" data-name="<?php echo htmlspecialchars($c['customer_name']); ?>" data-label="<?php echo htmlspecialchars($c['label_tag']); ?>" data-business-type="<?php echo htmlspecialchars($c['business_type'] ?? ''); ?>" data-approached-for="<?php echo htmlspecialchars($c['approached_for'] ?? ''); ?>" data-followup-method="<?php echo htmlspecialchars($c['followup_method'] ?? ''); ?>" data-phone="<?php echo htmlspecialchars($c['phone_number']); ?>" data-email="<?php echo htmlspecialchars($c['email_id'] ?? ''); ?>" data-company="<?php echo htmlspecialchars($c['company_name'] ?? ''); ?>" data-website="<?php echo htmlspecialchars($c['website'] ?? ''); ?>" data-source="<?php echo htmlspecialchars($c['source'] ?? ''); ?>" data-status="<?php echo htmlspecialchars($c['status'] ?? ''); ?>" data-line1="<?php echo htmlspecialchars($c['address_line1']); ?>" data-areacity="<?php echo htmlspecialchars($c['area_city'] ?? ''); ?>" data-comments="<?php echo htmlspecialchars($c['comments']); ?>" title="Edit"><i class="fa fa-edit"></i> Edit</button>
                            <button type="button" class="btn btn-sm btn-primary" style="background-color:#278de6;border-color:#278de6;" data-mw-modal-open="historyModal<?php echo (int)$c['id']; ?>" title="Add Followup"><i class="fa fa-history"></i> Add/View Followup</button>
                        </div>
                    </td>
                    <?php else: ?>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="tel:<?php echo htmlspecialchars($c['phone_number']); ?>"><i class="fa fa-phone"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-success wa-normal-btn" data-id="<?php echo (int)$c['id']; ?>" data-phone="<?php echo htmlspecialchars($c['phone_number']); ?>" data-name="<?php echo htmlspecialchars($c['customer_name']); ?>"><i class="fa-brands fa-whatsapp"></i></button>
                        <button class="btn btn-sm btn-outline-warning single-offer-btn" data-id="<?php echo (int)$c['id']; ?>"><i class="fa fa-gift"></i></button>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-info edit-btn" data-id="<?php echo (int)$c['id']; ?>" data-name="<?php echo htmlspecialchars($c['customer_name']); ?>" data-label="<?php echo htmlspecialchars($c['label_tag']); ?>" data-phone="<?php echo htmlspecialchars($c['phone_number']); ?>" data-email="<?php echo htmlspecialchars($c['email_id'] ?? ''); ?>" data-company="<?php echo htmlspecialchars($c['company_name'] ?? ''); ?>" data-website="<?php echo htmlspecialchars($c['website'] ?? ''); ?>" data-source="<?php echo htmlspecialchars($c['source'] ?? ''); ?>" data-status="<?php echo htmlspecialchars($c['status'] ?? ''); ?>" data-line1="<?php echo htmlspecialchars($c['address_line1']); ?>" data-areacity="<?php echo htmlspecialchars($c['area_city'] ?? ''); ?>" data-comments="<?php echo htmlspecialchars($c['comments']); ?>"><i class="fa fa-edit"></i></button>
                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this customer?');"><input type="hidden" name="action" value="delete_customer"><input type="hidden" name="customer_id" value="<?php echo (int)$c['id']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button></form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div></div>
    <?php if ($tracker_variant === 'team' && !empty($customers)): ?>
        <?php foreach ($customers as $c):
            $cid = (int)$c['id'];
            $followups = mw_get_customer_followups($connect, $cid, $owner_id, $owner_role_db);
        ?>
        <div class="mw-modal" id="viewModal<?php echo $cid; ?>" role="dialog" aria-modal="true" aria-labelledby="viewModalTitle<?php echo $cid; ?>" hidden>
            <div class="mw-modal-backdrop" data-mw-modal-close aria-hidden="true"></div>
            <div class="mw-modal-panel mw-modal-lg">
                    <div class="mw-modal-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h2 class="mw-modal-title mb-0" id="viewModalTitle<?php echo $cid; ?>"><i class="fa fa-eye me-1"></i> Followup History — <?php echo htmlspecialchars($c['customer_name']); ?></h2>
                        <div class="d-flex align-items-center gap-2 ms-auto">
                            <button type="button" class="btn btn-sm btn-add-followup-from-view" data-history-modal="historyModal<?php echo $cid; ?>">Add Followup</button>
                            <button type="button" class="mw-modal-close" data-mw-modal-close aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    </div>
                    <div class="mw-modal-body">
                        <?php if (empty($followups)): ?>
                            <p class="mw-followup-empty-text mb-0">No followups recorded yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mw-followup-history-table">
                                    <thead><tr><th>Date &amp; Time</th><th>Method</th><th>Status</th><th>Comments</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($followups as $f): ?>
                                        <tr>
                                            <td><?php echo !empty($f['followup_datetime']) ? date('d-m-Y H:i', strtotime($f['followup_datetime'])) : '-'; ?></td>
                                            <td><?php echo htmlspecialchars($f['followup_method'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($f['followup_status'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($f['comments'] ?: '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mw-modal-footer">
                        <button type="button" class="btn btn-light border btn-followup-close" data-mw-modal-close>Close</button>
                    </div>
            </div>
        </div>
        <div class="mw-modal" id="historyModal<?php echo $cid; ?>" role="dialog" aria-modal="true" aria-labelledby="historyModalTitle<?php echo $cid; ?>" hidden>
            <div class="mw-modal-backdrop" data-mw-modal-close aria-hidden="true"></div>
            <div class="mw-modal-panel mw-modal-lg">
                    <form method="post" class="mw-modal-form">
                    <div class="mw-modal-header">
                        <h2 class="mw-modal-title" id="historyModalTitle<?php echo $cid; ?>"><i class="fa fa-history me-1"></i> Add Followup — <?php echo htmlspecialchars($c['customer_name']); ?></h2>
                        <button type="button" class="mw-modal-close" data-mw-modal-close aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="mw-modal-body">
                            <input type="hidden" name="action" value="add_followup">
                            <input type="hidden" name="customer_id" value="<?php echo $cid; ?>">
                            <div class="mw-modal-section-title"><i class="fa fa-plus-circle me-1"></i> New followup</div>
                            <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Date &amp; Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="followup_datetime" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Followup Method <span class="text-danger">*</span></label>
                                <select name="followup_method" class="form-select" required>
                                    <option value="">Select method</option>
                                    <option value="Call">Call</option>
                                    <option value="Visited">Visited</option>
                                    <option value="WhatsApp">WhatsApp</option>
                                    <option value="Email">Email</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Followup Status <span class="text-danger">*</span></label>
                                <select name="followup_status" class="form-select" required>
                                    <option value="Followup required">Followup required</option>
                                    <option value="Phone Busy/Not Picked">Phone Busy/Not Picked</option>
                                    <option value="Important">Important</option>
                                    <option value="Joined">Joined</option>
                                    <option value="Interested">Interested</option>
                                    <option value="Not Interested">Not Interested</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Comments</label>
                                <textarea name="followup_comments" class="form-control" rows="2" placeholder="Notes (optional)"></textarea>
                            </div>
                            </div>
                        <?php if (!empty($followups)): ?>
                            <div class="mw-modal-section-title mt-3"><i class="fa fa-list me-1"></i> Previous followups</div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mw-followup-history-table">
                                    <thead><tr><th>Date &amp; Time</th><th>Method</th><th>Status</th><th>Comments</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($followups as $f): ?>
                                        <tr>
                                            <td><?php echo !empty($f['followup_datetime']) ? date('d-m-Y H:i', strtotime($f['followup_datetime'])) : '-'; ?></td>
                                            <td><?php echo htmlspecialchars($f['followup_method'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($f['followup_status'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($f['comments'] ?: '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mw-modal-footer">
                        <button type="button" class="btn btn-light border" data-mw-modal-close>Cancel</button>
                        <button type="submit" class="btn mw-btn-accent">Save Followup</button>
                    </div>
                    </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
     
</div>
</main>

<div class="mw-modal" id="customerModal" role="dialog" aria-modal="true" aria-labelledby="customerModalTitle" hidden>
<div class="mw-modal-backdrop" data-mw-modal-close aria-hidden="true"></div>
<div class="mw-modal-panel mw-modal-sm"><form method="post" class="mw-modal-form">
<div class="mw-modal-header">
    <div class="mw-modal-header-main">
        <span class="mw-modal-header-icon" aria-hidden="true"><i class="fa fa-bolt"></i></span>
        <div class="mw-modal-header-text-wrap">
            <h2 class="mw-modal-title" id="customerModalTitle">Quick Add Customer</h2>
       
        </div>
    </div>
    <button type="button" class="mw-modal-close" data-mw-modal-close aria-label="Close"><span aria-hidden="true">&times;</span></button>
</div>
<div class="mw-modal-body">
<input type="hidden" name="action" value="save_customer"><input type="hidden" name="customer_id" id="customer_id">

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
    <?php if ($show_approached_for): ?>
    <div class="col-md-6"><label class="form-label">Approached For</label><select name="approached_for" id="approached_for_quick" class="form-select"></select></div>
    <?php endif; ?>
    <div class="col-md-6"><label class="form-label">Followed-Up Method</label><select name="followup_method" id="followup_method_quick" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="status_select" class="form-select"></select></div>
    <?php else: ?>
    <div class="col-md-6"><label class="form-label">Label</label><select name="label_tag" id="label_tag" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Source</label><select name="source" id="source_select" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="status_select" class="form-select"></select></div>
    <div class="col-12"><label class="form-label">Comment</label><textarea name="comments" id="comments_quick" class="form-control" rows="2" placeholder="Notes (optional)"></textarea></div>
    <?php endif; ?>
</div>
<button class="mw-modal-expand-trigger border-0" type="button" id="toggleAdditionalBtn"><i class="fa fa-plus-circle" aria-hidden="true"></i> Additional Details (Optional)</button>
</div><div class="mw-modal-footer"><button type="button" class="btn btn-light border" data-mw-modal-close>Cancel</button><button class="btn mw-btn-accent" type="submit"><i class="fa fa-save me-1" aria-hidden="true"></i> Save Customer</button></div>
</form></div></div>

<div class="mw-modal" id="customerFullModal" role="dialog" aria-modal="true" aria-labelledby="customerFullModalTitle" hidden>
<div class="mw-modal-backdrop" data-mw-modal-close aria-hidden="true"></div>
<div class="mw-modal-panel mw-modal-lg"><form method="post" class="mw-modal-form">
<div class="mw-modal-header">
    <div class="mw-modal-header-main">
        <span class="mw-modal-header-icon" aria-hidden="true"><i class="fa fa-user-plus"></i></span>
        <div class="mw-modal-header-text-wrap">
            <h2 class="mw-modal-title" id="customerFullModalTitle">Add Customer</h2>
           
        </div>
    </div>
    <button type="button" class="mw-modal-close" data-mw-modal-close aria-label="Close"><span aria-hidden="true">&times;</span></button>
</div>
<div class="mw-modal-body">
<input type="hidden" name="action" value="save_customer"><input type="hidden" name="customer_id" id="customer_id_full">
<div class="mw-modal-section-title"><i class="fa fa-user-circle me-1"></i> Basic Information</div>
<div class="row g-2">
    <div class="col-md-6"><label class="form-label">Customer Name *</label><div class="tracker-input-wrap"><i class="fa fa-user field-icon"></i><input name="customer_name" id="customer_name_full" class="form-control" placeholder="Enter customer name" required<?php if ($tracker_variant === 'team'): ?> data-lock-on-edit="1"<?php endif; ?>></div></div>
    <div class="col-md-6"><label class="form-label">Phone Number *</label><div class="input-group phone-group"><span class="input-group-text bg-white border-end-0"><i class="fa fa-phone text-muted"></i></span><select class="form-select country-code-select border-start-0 border-end-0" id="phone_country_code_full"><option value="+91" selected>+91</option></select><input name="phone_number" id="phone_number_full" class="form-control phone-input" placeholder="Enter phone number" required maxlength="12" inputmode="numeric" pattern="[0-9]{1,12}" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,12);"<?php if ($tracker_variant === 'team'): ?> data-lock-on-edit="1"<?php endif; ?>></div></div>
    <?php if ($tracker_variant === 'team'): ?>
    <div class="col-md-6"><label class="form-label">Business Type</label><select name="business_type" id="business_type_full" class="form-select"></select></div>
    <?php if ($show_approached_for): ?>
    <div class="col-md-6"><label class="form-label">Approached For</label><select name="approached_for" id="approached_for_full" class="form-select"></select></div>
    <?php endif; ?>
    <div class="col-md-6"><label class="form-label">Followed-Up Method</label><select name="followup_method" id="followup_method_full" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="status_select_full" class="form-select"></select></div>
    <?php else: ?>
    <div class="col-md-6"><label class="form-label">Label</label><select name="label_tag" id="label_tag_full" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="status_select_full" class="form-select"></select></div>
    <?php endif; ?>
</div>
<div class="mw-modal-section-title mt-3"><i class="fa fa-list-check me-1"></i> Additional Details (Optional)</div>
<div class="row g-2">
    <?php if ($tracker_variant === 'team'): ?>
    <div class="col-md-6"><label class="form-label">Source</label><select name="source" id="source_select_advanced" class="form-select"></select></div>
    <?php else: ?>
    <div class="col-md-6"><label class="form-label">Source</label><select name="source" id="source_select_full" class="form-select"></select></div>
    <?php endif; ?>
    <div class="col-md-6"><label class="form-label">Email ID</label><input type="email" name="email_id" id="email_id_full" class="form-control" placeholder="Enter email address"></div>
    <div class="col-md-6"><label class="form-label">Company Name</label><input name="company_name" id="company_name_full" class="form-control" placeholder="Enter company name"></div>
    <div class="col-md-6"><label class="form-label">Website</label><input name="website" id="website_full" class="form-control" placeholder="Enter website URL"></div>
    <div class="col-md-6"><label class="form-label">Address</label><input name="address_line1" id="address_line1_full" class="form-control" placeholder="House no., Building, Street, area, city"></div>
    <?php if ($tracker_variant === 'team'): ?>
    <div class="col-12"><label class="form-label">Comment</label><textarea name="comments" id="comments_full" class="form-control" rows="2" placeholder="Notes (optional)"></textarea></div>
    <?php else: ?>
    <div class="col-md-6"><label class="form-label">Comment</label><textarea name="comments" id="comments_full" class="form-control" rows="2" placeholder="Notes (optional)"></textarea></div>
    <?php endif; ?>
</div>
<input type="hidden" name="area_city" id="area_city_hidden" value="">
</div><div class="mw-modal-footer"><button type="button" class="btn btn-light border" data-mw-modal-close>Cancel</button><button class="btn mw-btn-accent" type="submit" id="customerFullSaveBtn"><i class="fa fa-save me-1" aria-hidden="true"></i> Save Customer</button></div>
</form></div></div>

<div class="mw-modal mw-modal-light" id="shareOfferModal" role="dialog" aria-modal="true" aria-labelledby="shareOfferModalTitle" hidden>
<div class="mw-modal-backdrop" data-mw-modal-close aria-hidden="true"></div>
<div class="mw-modal-panel mw-modal-xl">
<div class="mw-modal-header"><h2 class="mw-modal-title mw-modal-action-title" id="shareOfferModalTitle"><i class="fa-brands fa-whatsapp"></i> <span id="waPopupTitleText">Send WhatsApp Message</span></h2><button type="button" class="mw-modal-close" data-mw-modal-close aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
<div class="mw-modal-body">
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
<div class="mw-modal-footer wa-popup-footer flex-column flex-md-row align-items-stretch align-items-md-center justify-content-md-end">
    <button type="button" id="sendCurrentBtn" class="btn btn-success order-md-1"><i class="fa-brands fa-whatsapp me-1"></i>Send on WhatsApp</button>
    <button type="button" id="saveAsTemplateBtn" class="btn btn-outline-primary order-md-2">Save Template</button>
    <button type="button" id="setDefaultTemplateBtn" class="btn btn-outline-primary order-md-3">Save as Default</button>
    <button type="button" class="btn btn-danger order-md-4" data-mw-modal-close>Cancel</button>
</div>
</div></div>

<div class="mw-modal" id="leadCaptureModal" role="dialog" aria-modal="true" aria-labelledby="leadCaptureModalTitle" hidden>
<div class="mw-modal-backdrop" data-mw-modal-close aria-hidden="true"></div>
<div class="mw-modal-panel mw-modal-sm"><form method="post" class="mw-modal-form">
<div class="mw-modal-header"><h2 class="mw-modal-title" id="leadCaptureModalTitle">New customer inquiry received</h2><button type="button" class="mw-modal-close" data-mw-modal-close aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
<div class="mw-modal-body"><p>Save this customer?</p><input type="hidden" name="action" value="save_customer"><input type="hidden" name="source" value="WhatsApp"><div class="mb-2"><label>Name</label><input name="customer_name" class="form-control" value="New Inquiry"></div><div class="mb-2"><label>Label</label><input name="label_tag" class="form-control" maxlength="18" value="New"></div><div class="mb-2"><label>Phone Number *</label><input name="phone_number" class="form-control" required maxlength="12" inputmode="numeric" pattern="[0-9]{1,12}" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,12);"></div></div>
<div class="mw-modal-footer"><button class="btn btn-primary">Save Customer</button></div>
</form></div></div>

<script>
// Keep modals attached to <body> so they center against full viewport,
// not inside sidebar/content container.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#customerModal, #customerFullModal, #shareOfferModal, #leadCaptureModal, .mw-modal[id^="viewModal"], .mw-modal[id^="historyModal"]').forEach(function (modalEl) {
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
const customerFullModalTitleEl = document.getElementById('customerFullModalTitle');
const customerFullSaveBtnEl = document.getElementById('customerFullSaveBtn');
const phoneCountryCodeFullEl = document.getElementById('phone_country_code_full');
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

function mwOpen(modalId) {
    if (typeof MwModal !== 'undefined' && MwModal.open) {
        MwModal.open(modalId);
    }
}
function mwClose(modalId) {
    if (typeof MwModal !== 'undefined' && MwModal.close) {
        MwModal.close(modalId);
    }
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
function setupFixedSelect(selectEl, options) {
    selectEl.innerHTML = '';
    options.forEach(function (opt) {
        const option = document.createElement('option');
        option.value = opt;
        option.textContent = opt;
        selectEl.appendChild(option);
    });
}
const showApproachedFor = <?php echo $show_approached_for ? 'true' : 'false'; ?>;
const customerSourceOptionsList = ['Direct', 'WhatsApp', 'Referral', 'Existing Contact', 'Walk-In', 'Website'];
const teamSourceOptionsList = ['Direct', 'WhatsApp', 'Referral'];
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
const approachedForOptionsList = ['MW Sales', 'Franchise Sale'];
const teamFollowupMethodOptionsList = ['Call', 'Visited', 'WhatsApp', 'Email'];
const customerStatusOptions = ['Followup required', 'Phone Busy/Not Picked', 'Important', 'Deal Done', 'Profile Shared', 'Interested', 'Not Interested'];
const teamStatusOptions = ['Followup required', 'Phone Busy/Not Picked', 'Important', 'Joined', 'Interested', 'Not Interested'];
const statusOptionsList = (trackerVariant === 'customer') ? customerStatusOptions : teamStatusOptions;

if (trackerVariant === 'customer') {
    if (selectLabel) setupCustomizableSelect(selectLabel, labelOptionsList);
    if (selectSource) setupCustomizableSelect(selectSource, customerSourceOptionsList);
    if (selectLabelFull) setupCustomizableSelect(selectLabelFull, labelOptionsList);
    if (selectSourceFull) setupCustomizableSelect(selectSourceFull, customerSourceOptionsList);
} else {
    ['business_type_quick', 'business_type_full'].forEach(function (tid) {
        const el = document.getElementById(tid);
        if (el) setupCustomizableSelect(el, businessTypeOptionsList, 80);
    });
    if (showApproachedFor) {
        ['approached_for_quick', 'approached_for_full'].forEach(function (tid) {
            const el = document.getElementById(tid);
            if (el) setupFixedSelect(el, approachedForOptionsList);
        });
    }
    ['followup_method_quick', 'followup_method_full'].forEach(function (tid) {
        const el = document.getElementById(tid);
        if (el) setupFixedSelect(el, teamFollowupMethodOptionsList);
    });
    if (selectSourceAdvanced) setupCustomizableSelect(selectSourceAdvanced, teamSourceOptionsList);
}
if (statusSelectFull) {
    setupCustomizableSelect(statusSelectFull, statusOptionsList, 40);
}
if (statusSelectQuick) {
    setupCustomizableSelect(statusSelectQuick, statusOptionsList, 40);
}

function setCustomerIdentityFieldsLocked(isLocked) {
    if (trackerVariant !== 'team') {
        return;
    }
    const nameEl = document.getElementById('customer_name_full');
    const phoneEl = document.getElementById('phone_number_full');
    [nameEl, phoneEl].forEach(function (el) {
        if (!el) return;
        el.readOnly = isLocked;
        el.classList.toggle('bg-light', isLocked);
        el.classList.toggle('text-muted', isLocked);
        if (isLocked) {
            el.setAttribute('aria-readonly', 'true');
            el.title = 'Customer name and phone cannot be changed when editing';
        } else {
            el.removeAttribute('aria-readonly');
            el.removeAttribute('title');
        }
    });
    if (phoneCountryCodeFullEl) {
        phoneCountryCodeFullEl.disabled = isLocked;
    }
    if (customerFullModalTitleEl) {
        customerFullModalTitleEl.textContent = isLocked ? 'Edit Customer' : 'Add Customer';
    }
    if (customerFullSaveBtnEl) {
        customerFullSaveBtnEl.innerHTML = isLocked
            ? '<i class="fa fa-save me-1" aria-hidden="true"></i> Update Customer'
            : '<i class="fa fa-save me-1" aria-hidden="true"></i> Save Customer';
    }
}

function toggleAdditionalDetails() {
    if (!customerFullModalEl) return;
    customer_id_full.value = customer_id.value || '';
    customer_name_full.value = customer_name.value || '';
    phone_number_full.value = phone_number.value || '';
    if (trackerVariant === 'customer') {
        if (selectLabelFull && selectLabel) ensureSelectHasOption(selectLabelFull, selectLabel.value || 'Regular');
        if (selectSourceFull && selectSource) ensureSelectHasOption(selectSourceFull, selectSource.value || 'Direct');
        if (statusSelectFull && statusSelectQuick) {
            ensureSelectHasOption(statusSelectFull, statusSelectQuick.value || 'Followup required', 40);
        }
        if (commentsFullEl && commentsQuickEl) {
            commentsFullEl.value = commentsQuickEl.value || '';
        }
    } else {
        var syncPairs = [['business_type_full', 'business_type_quick'], ['followup_method_full', 'followup_method_quick']];
        if (showApproachedFor) {
            syncPairs.push(['approached_for_full', 'approached_for_quick']);
        }
        syncPairs.forEach(function (pair) {
            const fullEl = document.getElementById(pair[0]);
            const quickEl = document.getElementById(pair[1]);
            if (fullEl && quickEl) ensureSelectHasOption(fullEl, quickEl.value || '', 80);
        });
    }
    if (statusSelectFull && statusSelectQuick) {
        ensureSelectHasOption(statusSelectFull, statusSelectQuick.value || 'Followup required', 40);
    }
    setCustomerIdentityFieldsLocked(false);
    mwClose('customerModal');
    mwOpen('customerFullModal');
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
    mwOpen('shareOfferModal');
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
        if (statusSelectQuick) statusSelectQuick.selectedIndex = 0;
        if (statusSelectFull) statusSelectFull.selectedIndex = 0;
    } else {
        var resetIds = ['business_type_quick', 'business_type_full', 'followup_method_quick', 'followup_method_full'];
        if (showApproachedFor) {
            resetIds.push('approached_for_quick', 'approached_for_full');
        }
        resetIds.forEach(function (tid) {
            const el = document.getElementById(tid);
            if (el) el.selectedIndex = 0;
        });
        if (selectSourceAdvanced) selectSourceAdvanced.selectedIndex = 0;
    }
    if (commentsQuickEl) commentsQuickEl.value = '';
    if (commentsFullEl) commentsFullEl.value = '';
    setCustomerIdentityFieldsLocked(false);
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
            if (showApproachedFor && apf) ensureSelectHasOption(apf, this.dataset.approachedFor || '', 80);
            if (fmf) ensureSelectHasOption(fmf, this.dataset.followupMethod || '', 80);
            if (selectSourceAdvanced) ensureSelectHasOption(selectSourceAdvanced, this.dataset.source || 'Direct');
            if (commentsFullEl) commentsFullEl.value = this.dataset.comments || '';
        } else {
            if (selectLabelFull) ensureSelectHasOption(selectLabelFull, this.dataset.label || 'Regular');
            if (selectSourceFull) ensureSelectHasOption(selectSourceFull, this.dataset.source || 'Direct');
            var commentVal = this.dataset.comments || '';
            if (commentsFullEl) commentsFullEl.value = commentVal;
            if (commentsQuickEl) commentsQuickEl.value = commentVal;
        }
        if (statusSelectFull) {
            ensureSelectHasOption(statusSelectFull, this.dataset.status || 'Followup required', 40);
        }
        setCustomerIdentityFieldsLocked(trackerVariant === 'team' && !!(this.dataset.id || ''));
        mwOpen('customerFullModal');
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

document.querySelectorAll('.btn-add-followup-from-view').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const historyId = this.getAttribute('data-history-modal');
        if (!historyId) return;
        const viewModal = this.closest('.mw-modal');
        if (viewModal) mwClose(viewModal.id);
        setTimeout(function () {
            mwOpen(historyId);
        }, 200);
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
