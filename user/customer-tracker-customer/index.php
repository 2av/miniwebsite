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
    label_tag VARCHAR(10) DEFAULT '',
    phone_number VARCHAR(25) NOT NULL,
    address_line1 VARCHAR(255) DEFAULT '',
    area_city VARCHAR(120) DEFAULT '',
    comments TEXT,
    source VARCHAR(30) DEFAULT 'manual',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_owner (owner_user_id, owner_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

function mw_phone_clean(string $v): string { return preg_replace('/[^0-9]/', '', $v) ?? ''; }

$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_customer') {
        $id = (int)($_POST['customer_id'] ?? 0);
        $name = trim($_POST['customer_name'] ?? '');
        $label = substr(trim($_POST['label_tag'] ?? ''), 0, 10);
        $phone = mw_phone_clean(trim($_POST['phone_number'] ?? ''));
        $line1 = trim($_POST['address_line1'] ?? '');
        $areaCity = trim($_POST['area_city'] ?? '');
        $comments = trim($_POST['comments'] ?? '');
        $source = trim($_POST['source'] ?? 'manual');
        if ($name === '' || $phone === '') {
            header('Location: index.php?msg=validation_error');
            exit;
        } elseif (strlen($phone) > 12) {
            header('Location: index.php?msg=phone_max_error');
            exit;
        } else {
            if ($id > 0) {
                $stmt = $connect->prepare("UPDATE mw_customers SET customer_name=?, label_tag=?, phone_number=?, address_line1=?, area_city=?, comments=? WHERE id=? AND owner_user_id=? AND owner_role='CUSTOMER'");
                if ($stmt) {
                    $stmt->bind_param('ssssssii', $name, $label, $phone, $line1, $areaCity, $comments, $id, $owner_id);
                    $stmt->execute();
                    $stmt->close();
                }
                header('Location: index.php?msg=updated');
                exit;
            } else {
                $stmt = $connect->prepare("INSERT INTO mw_customers (owner_user_id, owner_role, customer_name, label_tag, phone_number, address_line1, area_city, comments, source) VALUES (?, 'CUSTOMER', ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('isssssss', $owner_id, $name, $label, $phone, $line1, $areaCity, $comments, $source);
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

$default_template = "Hello 😊\nThis is [Business Name] from [Area, City].\nWe have added some latest offers on our page.\n\nYou can check all offers here:\n👉 [MiniWebsite Link]\n\nIf anything interests you, feel free to message us on WhatsApp.\n\nThank you for your support 🙏\n(If you prefer not to receive updates, please let us know.)";
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
            <button class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#customerModal"><i class="fa fa-plus"></i> Add Customer</button>
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
                        <button class="btn btn-sm btn-outline-info edit-btn" data-id="<?php echo (int)$c['id']; ?>" data-name="<?php echo htmlspecialchars($c['customer_name']); ?>" data-label="<?php echo htmlspecialchars($c['label_tag']); ?>" data-phone="<?php echo htmlspecialchars($c['phone_number']); ?>" data-line1="<?php echo htmlspecialchars($c['address_line1']); ?>" data-areacity="<?php echo htmlspecialchars($c['area_city']); ?>" data-comments="<?php echo htmlspecialchars($c['comments']); ?>"><i class="fa fa-edit"></i></button>
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

<div class="modal fade" id="customerModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post">
<div class="modal-header"><h5 class="modal-title">Add Customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<input type="hidden" name="action" value="save_customer"><input type="hidden" name="customer_id" id="customer_id"><input type="hidden" name="source" id="customer_source" value="manual">
<div class="mb-2"><label>Customer Name *</label><input name="customer_name" id="customer_name" class="form-control" required></div>
<div class="mb-2"><label>Label (Max 10 char)</label><input name="label_tag" id="label_tag" maxlength="10" class="form-control"></div>
<div class="mb-2"><label>Phone Number *</label><input name="phone_number" id="phone_number" class="form-control" required maxlength="12" inputmode="numeric" pattern="[0-9]{1,12}" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,12);"></div>
<div class="mb-2"><label>Address Line 1</label><input name="address_line1" id="address_line1" class="form-control"></div>
<div class="mb-2"><label>Area + City</label><input name="area_city" id="area_city" class="form-control"></div>
<div><label>Comments</label><textarea name="comments" id="comments" class="form-control"></textarea></div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
</form></div></div></div>

<div class="modal fade" id="shareOfferModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Message Preview</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<textarea id="offerMessage" class="form-control" rows="8"><?php echo htmlspecialchars($preview_template); ?></textarea>
<div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="addCustomerNameToggle"><label class="form-check-label" for="addCustomerNameToggle">Add customer name</label></div>
</div>
<div class="modal-footer"><button id="sendCurrentBtn" class="btn btn-success">Send to Customer</button><button id="nextCustomerBtn" class="btn btn-warning">Next Customer</button><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button></div>
</div></div></div>

<div class="modal fade" id="leadCaptureModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post">
<div class="modal-header"><h5 class="modal-title">New customer inquiry received</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><p>Save this customer?</p><input type="hidden" name="action" value="save_customer"><input type="hidden" name="source" value="whatsapp_lead"><div class="mb-2"><label>Name</label><input name="customer_name" class="form-control" value="New Inquiry"></div><div class="mb-2"><label>Label</label><input name="label_tag" class="form-control" maxlength="10" value="New Lead"></div><div class="mb-2"><label>Phone Number *</label><input name="phone_number" class="form-control" required maxlength="12" inputmode="numeric" pattern="[0-9]{1,12}" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,12);"></div></div>







<div class="modal-footer"><button class="btn btn-primary">Save Customer</button></div>
</form></div></div></div>

<script>
// Keep Bootstrap modals attached to <body> so they center against full viewport,
// not inside sidebar/content container.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#customerModal, #shareOfferModal, #leadCaptureModal').forEach(function (modalEl) {
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
const customersData = <?php echo json_encode(array_map(function($r){ return ['id'=>(int)$r['id'], 'name'=>$r['customer_name'], 'phone'=>$r['phone_number']]; }, $customers)); ?>;
let shareQueue = [], queueIndex = 0;
document.querySelectorAll('.edit-btn').forEach(btn=>btn.addEventListener('click',function(){customer_id.value=this.dataset.id||'';customer_name.value=this.dataset.name||'';label_tag.value=this.dataset.label||'';phone_number.value=this.dataset.phone||'';address_line1.value=this.dataset.line1||'';area_city.value=this.dataset.areacity||'';comments.value=this.dataset.comments||'';(new bootstrap.Modal(document.getElementById('customerModal'))).show();}));
document.querySelectorAll('.wa-normal-btn').forEach(btn=>btn.addEventListener('click',function(){const p=(this.dataset.phone||'').replace(/\D/g,'');const n=this.dataset.name||'Customer';window.open('https://wa.me/'+p+'?text='+encodeURIComponent('Hello '+n+', thank you for connecting with '+businessName+'.'),'_blank');}));
document.querySelectorAll('.single-offer-btn').forEach(btn=>btn.addEventListener('click',function(){shareQueue=customersData.filter(c=>c.id===parseInt(this.dataset.id,10));queueIndex=0;(new bootstrap.Modal(document.getElementById('shareOfferModal'))).show();}));
function msgFor(name){let m=document.getElementById('offerMessage').value;m=m.replace(/\[Business Name\]/g,businessName).replace(/\[Area, City\]/g,businessAreaCity).replace(/\[MiniWebsite Link\]/g,miniLink);if(document.getElementById('addCustomerNameToggle').checked)m='Hi '+name+',\n\n'+m;return m;}
document.getElementById('sendCurrentBtn').addEventListener('click',function(){if(!shareQueue.length)return;const c=shareQueue[queueIndex];window.open('https://wa.me/'+(c.phone||'').replace(/\D/g,'')+'?text='+encodeURIComponent(msgFor(c.name)),'_blank');});
document.getElementById('nextCustomerBtn').addEventListener('click',function(){if(!shareQueue.length)return;if(queueIndex<shareQueue.length-1){queueIndex++;return;}alert('Offer flow completed for selected customer.');});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
