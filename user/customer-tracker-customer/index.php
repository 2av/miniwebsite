<?php
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/role_helper.php');
require_role('CUSTOMER', '/login/customer.php');

$owner_id = (int)(get_user_id() ?? 0);
$owner_email = (string)(get_user_email() ?? '');
if ($owner_id <= 0 && $owner_email !== '') {
    $stmtOwner = $connect->prepare("SELECT id FROM user_details WHERE email = ? AND role = 'CUSTOMER' LIMIT 1");
    if ($stmtOwner) {
        $stmtOwner->bind_param('s', $owner_email);
        $stmtOwner->execute();
        $resOwner = $stmtOwner->get_result();
        if ($resOwner && ($ownerRow = $resOwner->fetch_assoc())) $owner_id = (int)$ownerRow['id'];
        $stmtOwner->close();
    }
}
if ($owner_id <= 0) die('Unable to identify user.');

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
    source VARCHAR(18) DEFAULT 'Manual',
    status VARCHAR(18) DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_owner (owner_user_id, owner_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$connect->query("ALTER TABLE mw_customers MODIFY COLUMN label_tag VARCHAR(18) DEFAULT ''");
$connect->query("ALTER TABLE mw_customers MODIFY COLUMN source VARCHAR(18) DEFAULT 'Manual'");
$connect->query("ALTER TABLE mw_customers ADD COLUMN IF NOT EXISTS email_id VARCHAR(150) DEFAULT '' AFTER phone_number");
$connect->query("ALTER TABLE mw_customers ADD COLUMN IF NOT EXISTS company_name VARCHAR(150) DEFAULT '' AFTER email_id");
$connect->query("ALTER TABLE mw_customers ADD COLUMN IF NOT EXISTS website VARCHAR(255) DEFAULT '' AFTER company_name");
$connect->query("ALTER TABLE mw_customers ADD COLUMN IF NOT EXISTS status VARCHAR(18) DEFAULT 'Active' AFTER source");

function mw_phone_clean(string $v): string { return preg_replace('/[^0-9]/', '', $v) ?? ''; }
function mw_limit_text(string $v, int $max): string { return mb_substr(trim($v), 0, $max); }

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
        $source = mw_limit_text($_POST['source'] ?? 'Manual', 18);
        $status = mw_limit_text($_POST['status'] ?? 'Active', 18);
        if ($name === '' || $phone === '') {
            header('Location: index.php?msg=validation_error');
            exit;
        } elseif (strlen($phone) > 12) {
            header('Location: index.php?msg=phone_max_error');
            exit;
        } else {
            if ($id > 0) {
                $stmt = $connect->prepare("UPDATE mw_customers SET customer_name=?, label_tag=?, phone_number=?, email_id=?, company_name=?, website=?, source=?, status=?, address_line1=?, area_city=?, comments=? WHERE id=? AND owner_user_id=? AND owner_role='CUSTOMER'");
                if ($stmt) {
                    $stmt->bind_param('sssssssssssii', $name, $label, $phone, $emailId, $companyName, $website, $source, $status, $line1, $areaCity, $comments, $id, $owner_id);
                    $stmt->execute();
                    $stmt->close();
                }
                header('Location: index.php?msg=updated');
                exit;
            } else {
                $stmt = $connect->prepare("INSERT INTO mw_customers (owner_user_id, owner_role, customer_name, label_tag, phone_number, email_id, company_name, website, address_line1, area_city, comments, source, status) VALUES (?, 'CUSTOMER', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('isssssssssss', $owner_id, $name, $label, $phone, $emailId, $companyName, $website, $line1, $areaCity, $comments, $source, $status);
                    $stmt->execute();
                    $stmt->close();
                }
                header('Location: index.php?msg=saved');
                exit;
            }
        }
    } elseif ($action === 'delete_customer') {
        $id = (int)($_POST['customer_id'] ?? 0);
        $stmt = $connect->prepare("DELETE FROM mw_customers WHERE id = ? AND owner_user_id = ? AND owner_role = 'CUSTOMER'");
        if ($stmt) {
            $stmt->bind_param('ii', $id, $owner_id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: index.php?msg=deleted');
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
$sort_dir = strtolower(trim($_GET['sort_dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$sortable_columns = [
    'created_at' => 'created_at',
    'customer_name' => 'customer_name',
    'label_tag' => 'label_tag',
    'phone_number' => 'phone_number',
    'address' => "CONCAT(IFNULL(address_line1,''), ' ', IFNULL(area_city,''))",
    'comments' => 'comments'
];
if (!isset($sortable_columns[$sort_by])) {
    $sort_by = 'created_at';
}
$customers = [];
$sql = "SELECT * FROM mw_customers WHERE owner_user_id = ? AND owner_role = 'CUSTOMER'";
$types = 'i';
$args = [$owner_id];
if ($search !== '') {
    $sql .= " AND (customer_name LIKE ? OR phone_number LIKE ?)";
    $lk = '%' . $search . '%';
    $types .= 'ss';
    $args[] = $lk; $args[] = $lk;
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

$default_template = "Hello 😊\nThis is [Business Name] from [Area, City].\n\nWe have added some latest special offers for you.👇\n👉 [MiniWebsite Link]\n\nIf anything interests you, feel free to message us on WhatsApp.\n\nLimited time offers hain, jaldi check karein 👍\n\nThank you 🙏";
$default_whatsapp_template = "Hello 😊\nHope you are doing well.\n\nWe have added few new products & offers for you.👇\n👉 [MiniWebsite Link]\n\nYou can check. And please let us know if any requirements.\n\nThank you 🙏";
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
#customerTrackerTable thead a .sort-icon {
    margin-left: 4px;
    opacity: 0.8;
}
.tracker-modal .modal-header {
    background: #001b78;
    color: #fff;
    padding: 12px 16px;
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
.tracker-modal .form-control,
.tracker-modal .form-select {
    font-size: 13px;
    min-height: 40px;
    background: #fff;
    border: 1px solid #d8deea;
    border-radius: 8px;
    color: #4a5678;
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
#waTemplateList .template-item {
    border: 1px solid #e2e7f2;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
    background: #fff;
}
#waTemplateList .template-item.active {
    border-color: #cfe0ff;
    background: #f3f8ff;
}
.wa-template-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.wa-template-title {
    font-size: 13px;
    font-weight: 700;
    color: #2b3d66;
}
.wa-manage-link {
    font-size: 12px;
    color: #2d6adf;
    text-decoration: none;
    font-weight: 600;
}
.wa-manage-link:hover { color: #1f58c6; }
.template-name-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.template-preview {
    font-size: 12px;
    color: #6f7d9d;
    margin-top: 2px;
}
.template-default-badge {
    background: #28a745;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    border-radius: 4px;
    padding: 2px 6px;
}
.wa-create-btn {
    background: #fff;
    border: 1px solid #cfdbf2;
    color: #2d6adf;
    font-size: 13px;
    font-weight: 600;
}
.wa-create-btn:hover {
    background: #f7fbff;
    color: #1f58c6;
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
        <div class="d-flex flex-wrap justify-content-between mb-3">
            <button id="openCustomerModalBtn" class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#customerModal"><i class="fa fa-plus"></i>Add Customer</button>
            <form method="get" class="d-flex mb-2" style="gap:8px;">
                <input name="search" class="form-control" placeholder="Search Name / Phone" value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-outline-secondary"><i class="fa fa-search"></i></button>
            </form>
        </div>
        <div class="table-responsive"><table class="table table-bordered table-striped" id="customerTrackerTable">
            <thead><tr><th>SN</th><th><a href="?search=<?php echo urlencode($search); ?>&sort_by=created_at&sort_dir=<?php echo ($sort_by === 'created_at' && $sort_dir === 'ASC') ? 'desc' : 'asc'; ?>">Date Added <i class="fa <?php echo ($sort_by === 'created_at') ? ($sort_dir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'; ?> sort-icon"></i></a></th><th><a href="?search=<?php echo urlencode($search); ?>&sort_by=customer_name&sort_dir=<?php echo ($sort_by === 'customer_name' && $sort_dir === 'ASC') ? 'desc' : 'asc'; ?>">Customer Name <i class="fa <?php echo ($sort_by === 'customer_name') ? ($sort_dir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'; ?> sort-icon"></i></a></th><th><a href="?search=<?php echo urlencode($search); ?>&sort_by=label_tag&sort_dir=<?php echo ($sort_by === 'label_tag' && $sort_dir === 'ASC') ? 'desc' : 'asc'; ?>">Label <i class="fa <?php echo ($sort_by === 'label_tag') ? ($sort_dir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'; ?> sort-icon"></i></a></th><th><a href="?search=<?php echo urlencode($search); ?>&sort_by=phone_number&sort_dir=<?php echo ($sort_by === 'phone_number' && $sort_dir === 'ASC') ? 'desc' : 'asc'; ?>">Phone Number <i class="fa <?php echo ($sort_by === 'phone_number') ? ($sort_dir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'; ?> sort-icon"></i></a></th><th><a href="?search=<?php echo urlencode($search); ?>&sort_by=address&sort_dir=<?php echo ($sort_by === 'address' && $sort_dir === 'ASC') ? 'desc' : 'asc'; ?>">Address <i class="fa <?php echo ($sort_by === 'address') ? ($sort_dir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'; ?> sort-icon"></i></a></th><th><a href="?search=<?php echo urlencode($search); ?>&sort_by=comments&sort_dir=<?php echo ($sort_by === 'comments' && $sort_dir === 'ASC') ? 'desc' : 'asc'; ?>">Comments <i class="fa <?php echo ($sort_by === 'comments') ? ($sort_dir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'; ?> sort-icon"></i></a></th><th>Actions</th><th>Manage</th></tr></thead>
            <tbody>
            <?php if (empty($customers)): ?><tr><td colspan="9" class="text-center">No customers found.</td></tr><?php else: $sn=1; foreach($customers as $c): ?>
                <tr>
                    <td><?php echo $sn++; ?></td>
                    <td><?php echo date('d-m-Y', strtotime($c['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($c['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($c['label_tag'] ?: 'Regular'); ?></td>
                    <td><?php echo htmlspecialchars($c['phone_number']); ?></td>
                    <td><?php echo htmlspecialchars(trim(($c['address_line1'] ? $c['address_line1'] . ', ' : '') . ($c['area_city'] ?? ''), ' ,')); ?></td>
                    <td><?php echo htmlspecialchars($c['comments'] ?: '-'); ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="tel:<?php echo htmlspecialchars($c['phone_number']); ?>"><i class="fa fa-phone"></i></a>
                        <button class="btn btn-sm btn-outline-success wa-normal-btn" data-phone="<?php echo htmlspecialchars($c['phone_number']); ?>" data-name="<?php echo htmlspecialchars($c['customer_name']); ?>"><i class="fa-brands fa-whatsapp"></i></button>
                        <button class="btn btn-sm btn-outline-warning single-offer-btn" data-id="<?php echo (int)$c['id']; ?>"><i class="fa fa-gift"></i></button>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-info edit-btn" data-id="<?php echo (int)$c['id']; ?>" data-name="<?php echo htmlspecialchars($c['customer_name']); ?>" data-label="<?php echo htmlspecialchars($c['label_tag']); ?>" data-phone="<?php echo htmlspecialchars($c['phone_number']); ?>" data-email="<?php echo htmlspecialchars($c['email_id'] ?? ''); ?>" data-company="<?php echo htmlspecialchars($c['company_name'] ?? ''); ?>" data-website="<?php echo htmlspecialchars($c['website'] ?? ''); ?>" data-source="<?php echo htmlspecialchars($c['source'] ?? ''); ?>" data-status="<?php echo htmlspecialchars($c['status'] ?? ''); ?>" data-line1="<?php echo htmlspecialchars($c['address_line1']); ?>" data-areacity="<?php echo htmlspecialchars($c['area_city']); ?>" data-comments="<?php echo htmlspecialchars($c['comments']); ?>"><i class="fa fa-edit"></i></button>
                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this customer?');"><input type="hidden" name="action" value="delete_customer"><input type="hidden" name="customer_id" value="<?php echo (int)$c['id']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button></form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div></div>
    <div class="alert alert-light border mt-3 mb-0" id="customerTrackerSafetyTips">
        <strong>Action Button Details</strong>
        <ul class="mb-3 mt-2">
            <li><i class="fa fa-phone text-primary"></i> <strong>Call:</strong> Opens phone dialer with customer number.</li>
            <li><i class="fa-brands fa-whatsapp text-success"></i> <strong>WhatsApp:</strong> Opens normal WhatsApp message window.</li>
            <li><i class="fa fa-gift text-warning"></i> <strong>Share Offer:</strong> Opens offer preview and sends selected offer message.</li>
            <li><i class="fa fa-edit text-info"></i> <strong>Edit:</strong> Update customer name, label, phone, address, and comments.</li>
            <li><i class="fa fa-trash text-danger"></i> <strong>Delete:</strong> Permanently removes customer entry from your list.</li>
        </ul>
        <strong>WhatsApp Safety Tips</strong>
        <ul class="mb-0 mt-2">
            <li>Send only to known customers.</li>
            <li>Avoid sending too many messages at once.</li>
            <li>Add small gap between messages.</li>
            <li>Share useful updates only.</li>
        </ul>
    </div>
</div>
</main>

<div class="modal fade tracker-modal" id="customerModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><form method="post">
<div class="modal-header"><h5 class="modal-title">Quick Add Customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<input type="hidden" name="action" value="save_customer"><input type="hidden" name="customer_id" id="customer_id">
<div class="section-title"><i class="fa fa-user-circle me-1"></i> Basic Information</div>
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
    <div class="col-md-6"><label class="form-label">Label</label><select name="label_tag" id="label_tag" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Source</label><select name="source" id="source_select" class="form-select"></select></div>
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
    <div class="col-md-6"><label class="form-label">Label</label><select name="label_tag" id="label_tag_full" class="form-select"></select></div>
    <div class="col-md-6"><label class="form-label">Source</label><select name="source" id="source_select_full" class="form-select"></select></div>
</div>
<div class="section-title mt-3"><i class="fa fa-list-check me-1"></i> Additional Details (Optional)</div>
<div class="row g-2">
    <div class="col-md-6"><label class="form-label">Email ID</label><input type="email" name="email_id" id="email_id_full" class="form-control" placeholder="Enter email address"></div>
    <div class="col-md-6"><label class="form-label">Company Name</label><input name="company_name" id="company_name_full" class="form-control" placeholder="Enter company name"></div>
    <div class="col-md-6"><label class="form-label">Website</label><input name="website" id="website_full" class="form-control" placeholder="Enter website URL"></div>
    <div class="col-md-6"><label class="form-label">Address</label><input name="address_line1" id="address_line1_full" class="form-control" placeholder="House no., Building, Street"></div>
    <div class="col-md-6"><label class="form-label">Comments</label><textarea name="comments" id="comments_full" class="form-control" placeholder="Add any notes about this customer..."></textarea></div>
    <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="status_select_full" class="form-select"><option value="Active">Active</option><option value="Warm">Warm</option><option value="Cold">Cold</option><option value="Prospect">Prospect</option><option value="Customer">Customer</option></select></div>
    <div class="col-md-6"><label class="form-label">Area + City</label><input name="area_city" id="area_city_full" class="form-control" placeholder="Area + City"></div>
</div>
</div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn tracker-btn-yellow">Save Customer</button></div>
</form></div></div></div>

<div class="modal fade tracker-modal" id="shareOfferModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title wa-action-title"><i class="fa-brands fa-whatsapp"></i> <span id="waPopupTitleText">Send WhatsApp Message</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="row g-3">
        <div class="col-lg-7">
            <label class="form-label">Message</label>
            <textarea id="offerMessage" class="form-control" rows="12"><?php echo htmlspecialchars($preview_template); ?></textarea>
            <div class="small text-muted mt-2">Tip: Use placeholders: [Business Name], [Area, City], [MiniWebsite Link], [Customer Name]</div>
            <div class="tracker-subtle-box mt-3">
                <strong>Use templates for fast messaging</strong>
                <ul class="mt-2">
                    <li>Save your custom message as template.</li>
                    <li>Set any template as default for quick use.</li>
                </ul>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="wa-template-head">
                <span class="wa-template-title">Choose Template</span>
                <a href="javascript:void(0)" class="wa-manage-link" id="manageTemplatesLink"><i class="fa fa-gear"></i> Manage Templates</a>
            </div>
            <div id="waTemplateList"></div>
            <div class="d-grid mt-2">
                <button type="button" class="btn wa-create-btn btn-sm" id="createTemplateBtn"><i class="fa fa-plus"></i> Create New Template</button>
            </div>
        </div>
    </div>
</div>
<div class="modal-footer"><button class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button id="saveAsTemplateBtn" class="btn btn-outline-primary">Save as Template</button><button id="sendCurrentBtn" class="btn btn-success">Send on WhatsApp</button></div>
</div></div></div>

<div class="modal fade" id="leadCaptureModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post">
<div class="modal-header"><h5 class="modal-title">New customer inquiry received</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><p>Save this customer?</p><input type="hidden" name="action" value="save_customer"><input type="hidden" name="source" value="WhatsApp"><div class="mb-2"><label>Name</label><input name="customer_name" class="form-control" value="New Inquiry"></div><div class="mb-2"><label>Label</label><input name="label_tag" class="form-control" maxlength="18" value="New Lead"></div><div class="mb-2"><label>Phone Number *</label><input name="phone_number" class="form-control" required maxlength="12" inputmode="numeric" pattern="[0-9]{1,12}" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,12);"></div></div>







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

const businessName = <?php echo json_encode($business_name); ?>;
const businessAreaCity = <?php echo json_encode($area_city_business); ?>;
const miniLink = <?php echo json_encode($mini_link); ?>;
const defaultShareTemplate = <?php echo json_encode($default_template); ?>;
const defaultWhatsAppTemplate = <?php echo json_encode($default_whatsapp_template); ?>;
const templateStorageKey = 'mw_wa_templates_<?php echo (int)$owner_id; ?>';
const customersData = <?php echo json_encode(array_map(function($r){ return ['id'=>(int)$r['id'], 'name'=>$r['customer_name'], 'phone'=>$r['phone_number']]; }, $customers)); ?>;
const selectLabel = document.getElementById('label_tag');
const selectSource = document.getElementById('source_select');
const toggleAdditionalBtn = document.getElementById('toggleAdditionalBtn');
const customerModalEl = document.getElementById('customerModal');
const customerFullModalEl = document.getElementById('customerFullModal');
const selectLabelFull = document.getElementById('label_tag_full');
const selectSourceFull = document.getElementById('source_select_full');
const statusSelectFull = document.getElementById('status_select_full');
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
function ensureSelectHasOption(selectEl, value) {
    const safeVal = safeSlice(value, 18);
    if (!safeVal) return;
    if (![...selectEl.options].some(opt => opt.value === safeVal)) {
        const customOpt = document.createElement('option');
        customOpt.value = safeVal;
        customOpt.textContent = safeVal;
        selectEl.insertBefore(customOpt, selectEl.querySelector('option[value="__custom__"]'));
    }
    selectEl.value = safeVal;
}
function setupCustomizableSelect(selectEl, options) {
    selectEl.innerHTML = '';
    options.forEach(function (opt) {
        const option = document.createElement('option');
        option.value = opt;
        option.textContent = opt;
        selectEl.appendChild(option);
    });
    const custom = document.createElement('option');
    custom.value = '__custom__';
    custom.textContent = '+ Add Custom';
    selectEl.appendChild(custom);
    selectEl.addEventListener('change', function () {
        if (this.value !== '__custom__') return;
        const userValue = safeSlice(window.prompt('Enter custom value (max 18 characters):', ''), 18);
        if (userValue) {
            ensureSelectHasOption(this, userValue);
        } else {
            this.selectedIndex = 0;
        }
    });
}
setupCustomizableSelect(selectLabel, ['Regular', 'VIP', 'New Lead', 'Follow Up', 'Hot']);
setupCustomizableSelect(selectSource, ['Manual', 'WhatsApp', 'Reference', 'Website', 'Walk In']);
setupCustomizableSelect(selectLabelFull, ['Regular', 'VIP', 'New Lead', 'Follow Up', 'Hot']);
setupCustomizableSelect(selectSourceFull, ['Manual', 'WhatsApp', 'Reference', 'Website', 'Walk In']);

function toggleAdditionalDetails() {
    if (!customerFullModalEl) return;
    customer_id_full.value = customer_id.value || '';
    customer_name_full.value = customer_name.value || '';
    phone_number_full.value = phone_number.value || '';
    ensureSelectHasOption(selectLabelFull, selectLabel.value || 'Regular');
    ensureSelectHasOption(selectSourceFull, selectSource.value || 'Manual');
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

function getSeedTemplates() {
    return {
        defaultId: '',
        items: [
            { id: 'default_share_offer', name: 'Default Share Offer', text: defaultShareTemplate, locked: true },
            { id: 'default_whatsapp', name: 'Default WhatsApp', text: defaultWhatsAppTemplate, locked: true },
            { id: 'custom_1', name: 'Custom Template01', text: '', locked: false },
            { id: 'custom_2', name: 'Custom Template02', text: '', locked: false }
        ]
    };
}
function getNextCustomTemplateNumber(items) {
    let maxNum = 0;
    items.forEach(function (item) {
        const m = /^custom_(\d+)$/.exec(item.id || '');
        if (m) maxNum = Math.max(maxNum, parseInt(m[1], 10) || 0);
    });
    return maxNum + 1;
}
function loadTemplates() {
    try {
        const raw = localStorage.getItem(templateStorageKey);
        if (!raw) return getSeedTemplates();
        const parsed = JSON.parse(raw);
        if (!parsed || !Array.isArray(parsed.items)) return getSeedTemplates();
        const seed = getSeedTemplates();
        seed.defaultId = parsed.defaultId || '';
        seed.items.forEach(function (seedItem) {
            const existing = parsed.items.find(function (x) { return x.id === seedItem.id; });
            if (existing) seedItem.text = existing.text || seedItem.text;
        });
        return seed;
    } catch (e) {
        return getSeedTemplates();
    }
}
function saveTemplates() {
    localStorage.setItem(templateStorageKey, JSON.stringify(waTemplates));
}
function renderTemplateList() {
    const wrap = document.getElementById('waTemplateList');
    wrap.innerHTML = '';
    waTemplates.items.forEach(function (item) {
        const div = document.createElement('div');
        div.className = 'template-item' + (selectedTemplateId === item.id ? ' active' : '');
        const checked = selectedTemplateId === item.id ? 'checked' : '';
        const isDefault = waTemplates.defaultId === item.id ? '<span class="template-default-badge">DEFAULT</span>' : '';
        const preview = (item.text || '').replace(/\s+/g, ' ').slice(0, 70);
        div.innerHTML = '<label class="w-100 d-flex gap-2 align-items-start" style="cursor:pointer;">'
            + '<input type="radio" name="waTemplateRadio" value="' + item.id + '" ' + checked + '>'
            + '<span class="w-100"><span class="template-name-row"><strong>' + item.name + '</strong>' + isDefault + '</span><div class="template-preview">' + (preview || 'Empty template') + '</div></span>'
            + '</label>';
        wrap.appendChild(div);
    });
    wrap.querySelectorAll('input[name="waTemplateRadio"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            selectedTemplateId = this.value;
            const item = waTemplates.items.find(function (x) { return x.id === selectedTemplateId; });
            document.getElementById('offerMessage').value = item ? item.text : '';
            renderTemplateList();
        });
    });
}
function getContextDefaultTemplateId() {
    return activeContext === 'whatsapp' ? 'default_whatsapp' : 'default_share_offer';
}
function applyTemplateText() {
    const defaultId = waTemplates.defaultId || getContextDefaultTemplateId();
    selectedTemplateId = defaultId;
    const selected = waTemplates.items.find(function (x) { return x.id === defaultId; });
    document.getElementById('offerMessage').value = selected ? selected.text : defaultShareTemplate;
    renderTemplateList();
}
function saveIntoCustomTemplate(slotId) {
    const text = document.getElementById('offerMessage').value.trim();
    const target = waTemplates.items.find(function (x) { return x.id === slotId; });
    if (!target) return;
    target.text = text;
    selectedTemplateId = slotId;
    saveTemplates();
    renderTemplateList();
    alert(target.name + ' saved.');
}
function resolveMessageWithTags(rawMessage, customerName) {
    return rawMessage
        .replace(/\[Business Name\]/g, businessName)
        .replace(/\[Area, City\]/g, businessAreaCity)
        .replace(/\[MiniWebsite Link\]/g, miniLink)
        .replace(/\[Customer Name\]/g, customerName || 'Customer');
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
    selectLabel.selectedIndex = 0;
    selectSource.selectedIndex = 0;
    toggleAdditionalBtn.textContent = '+ Additional Details (Optional)';
    customerModalEl.classList.remove('expanded-view');
    customer_id_full.value = '';
    customer_name_full.value = '';
    phone_number_full.value = '';
    email_id_full.value = '';
    company_name_full.value = '';
    website_full.value = '';
    address_line1_full.value = '';
    area_city_full.value = '';
    comments_full.value = '';
    selectLabelFull.selectedIndex = 0;
    selectSourceFull.selectedIndex = 0;
    if (statusSelectFull) statusSelectFull.value = 'Active';
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
        area_city_full.value = this.dataset.areacity || '';
        comments_full.value = this.dataset.comments || '';
        ensureSelectHasOption(selectLabelFull, this.dataset.label || 'Regular');
        ensureSelectHasOption(selectSourceFull, this.dataset.source || 'Manual');
        if (statusSelectFull) ensureSelectHasOption(statusSelectFull, this.dataset.status || 'Active');
        const customerFullModal = getModalInstance(customerFullModalEl);
        if (customerFullModal) customerFullModal.show();
    });
});

document.querySelectorAll('.wa-normal-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        openWaComposer({ id: 0, name: this.dataset.name || 'Customer', phone: this.dataset.phone || '' }, 'whatsapp');
    });
});
document.querySelectorAll('.single-offer-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const customer = customersData.find(function (c) { return c.id === parseInt(btn.dataset.id, 10); });
        if (!customer) return;
        openWaComposer(customer, 'share_offer');
    });
});

document.getElementById('createTemplateBtn').addEventListener('click', function () {
    const nextNum = getNextCustomTemplateNumber(waTemplates.items);
    const nextId = 'custom_' + nextNum;
    const nextName = 'Custom Template' + String(nextNum).padStart(2, '0');
    waTemplates.items.push({ id: nextId, name: nextName, text: '', locked: false });
    selectedTemplateId = nextId;
    saveTemplates();
    renderTemplateList();
    document.getElementById('offerMessage').value = '';
});
document.getElementById('manageTemplatesLink').addEventListener('click', function () {
    alert('Template management panel will be available here.');
});
document.getElementById('saveAsTemplateBtn').addEventListener('click', function () {
    if (/^custom_\d+$/.test(selectedTemplateId || '')) {
        saveIntoCustomTemplate(selectedTemplateId);
        return;
    }
    const firstEmptyCustom = waTemplates.items.find(function (x) { return /^custom_\d+$/.test(x.id || '') && !(x.text || '').trim(); });
    if (firstEmptyCustom) {
        saveIntoCustomTemplate(firstEmptyCustom.id);
        return;
    }
    const nextNum = getNextCustomTemplateNumber(waTemplates.items);
    const nextId = 'custom_' + nextNum;
    waTemplates.items.push({ id: nextId, name: 'Custom Template' + String(nextNum).padStart(2, '0'), text: '', locked: false });
    selectedTemplateId = nextId;
    saveIntoCustomTemplate(nextId);
});
const setDefaultTemplateBtn = document.getElementById('setDefaultTemplateBtn');
if (setDefaultTemplateBtn) {
    setDefaultTemplateBtn.addEventListener('click', function () {
        waTemplates.defaultId = selectedTemplateId || getContextDefaultTemplateId();
        saveTemplates();
        renderTemplateList();
    });
}
document.getElementById('sendCurrentBtn').addEventListener('click', function () {
    if (!activeCustomer || !activeCustomer.phone) return;
    const phone = (activeCustomer.phone || '').replace(/\D/g, '');
    const message = resolveMessageWithTags(document.getElementById('offerMessage').value, activeCustomer.name || 'Customer');
    window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(message), '_blank');
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
