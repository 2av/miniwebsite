<?php
// Handle card_number from URL - store in session and cookie
// MUST be done before any output (before including header.php)
require_once(__DIR__ . '/../../app/config/database.php');

// Clear any existing card_id_inprocess when creating a new website (no card_number in URL or new=1 parameter)
if((!isset($_GET['card_number']) || empty($_GET['card_number'])) || (isset($_GET['new']) && $_GET['new'] == '1')) {
    // If no card_number in URL or explicitly creating new, clear session and cookie to start fresh
    unset($_SESSION['card_id_inprocess']);
    setcookie('card_id_inprocess', '', time() - 3600, '/'); // Delete cookie
}

if(isset($_GET['card_number']) && !empty($_GET['card_number'])) {
    $card_number = mysqli_real_escape_string($connect, $_GET['card_number']);
    $current_user_email = $_SESSION['user_email'] ?? '';
    
    // Validate that the card belongs to the current user before setting session/cookie
    $validate_query = mysqli_query($connect, 'SELECT id FROM digi_card WHERE id="'.$card_number.'" AND user_email="'.$current_user_email.'" LIMIT 1');
    if(mysqli_num_rows($validate_query) > 0) {
        $_SESSION['card_id_inprocess'] = $card_number;
        // Store in cookie for 24 hours
        setcookie('card_id_inprocess', $card_number, time() + (86400 * 1), '/');
    } else {
        // Card doesn't belong to current user, clear it
        unset($_SESSION['card_id_inprocess']);
        setcookie('card_id_inprocess', '', time() - 3600, '/');
    }
} elseif(isset($_COOKIE['card_id_inprocess']) && !empty($_COOKIE['card_id_inprocess'])) {
    // If card_number not in URL but exists in cookie, validate it belongs to current user
    $cookie_card_id = mysqli_real_escape_string($connect, $_COOKIE['card_id_inprocess']);
    $current_user_email = $_SESSION['user_email'] ?? '';
    
    // Validate that the card belongs to the current user
    $validate_query = mysqli_query($connect, 'SELECT id FROM digi_card WHERE id="'.$cookie_card_id.'" AND user_email="'.$current_user_email.'" LIMIT 1');
    if(mysqli_num_rows($validate_query) > 0) {
        // Valid card, restore to session
        $_SESSION['card_id_inprocess'] = $cookie_card_id;
    } else {
        // Card doesn't belong to current user, clear cookie and session
        unset($_SESSION['card_id_inprocess']);
        setcookie('card_id_inprocess', '', time() - 3600, '/');
    }
}

// Get franchisee email from sender_user_id (same logic as create_card.php)
$franchisee_email = "";
$user_email_escaped = mysqli_real_escape_string($connect, $_SESSION['user_email']);
$user_email_lower = strtolower(trim($user_email_escaped));
$query_customer = mysqli_query($connect, "SELECT sender_user_id FROM user_details WHERE LOWER(TRIM(email)) = '$user_email_lower' LIMIT 1");
$row_customer = mysqli_fetch_array($query_customer);
if(!empty($row_customer['sender_user_id'])){
    // Get the sender's email from user_details
    $sender_user_id = intval($row_customer['sender_user_id']);
    $query_sender = mysqli_query($connect, "SELECT email FROM user_details WHERE id = $sender_user_id AND role = 'FRANCHISEE' LIMIT 1");
    if($query_sender && mysqli_num_rows($query_sender) > 0){
        $sender_row = mysqli_fetch_array($query_sender);
        $sender_email = $sender_row['email'];
        
        // Get franchisee email from franchisee_login
        $query_franchisee = mysqli_query($connect, "SELECT f_user_email FROM franchisee_login WHERE f_user_email='".mysqli_real_escape_string($connect, $sender_email)."' LIMIT 1");
        if($query_franchisee && mysqli_num_rows($query_franchisee) > 0){
            $row_franchisee = mysqli_fetch_array($query_franchisee);
            $franchisee_email = $row_franchisee['f_user_email'];
        }
    }
}

// Handle form submission (Save button - no redirect) - MUST be before header.php
if(isset($_POST['process1'])){
    $comp_name = preg_replace('/[^A-Za-z0-9\s\-]/', '', trim($_POST['d_comp_name'] ?? ''));
    if ($comp_name === '') {
        $_SESSION['save_error'] = "Please enter a valid business name (only letters, numbers, spaces and hyphen allowed).";
        header('Location: business-name.php');
        exit;
    }
    $comp_name = mysqli_real_escape_string($connect, $comp_name);
    
    // Check if company name already exists - MUST be unique
    $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE d_comp_name="'.$comp_name.'"');
    if(mysqli_num_rows($query) == 0){
        // Company name is unique, create new card
        $card_id = str_replace(array(' ','.','&','/','','[',']'), array('-','','','-','',''), $comp_name);
        $date = date('Y-m-d H:i:s');
        
        $insert = mysqli_query($connect, 'INSERT INTO digi_card (d_comp_name,uploaded_date,d_payment_status,user_email,d_card_status,card_id,f_user_email,validity_date) VALUES ("'.$comp_name.'","'.$date.'","Created","'.$_SESSION['user_email'].'","Active","'.$card_id.'","'.$franchisee_email.'",DATE_ADD("'.$date.'", INTERVAL 1 YEAR))');
        
        if($insert){
            // Insert data in 2nd and 3rd database tables
            $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE d_comp_name="'.$comp_name.'" AND user_email="'.$_SESSION['user_email'].'" order by id desc limit 1');
            $row = mysqli_fetch_array($query);
            
            $insert_digi2 = mysqli_query($connect, 'INSERT INTO digi_card2 (id,user_email) VALUES ("'.$row['id'].'","'.$_SESSION['user_email'].'")');
            $insert_digi3 = mysqli_query($connect, 'INSERT INTO digi_card3 (id,user_email) VALUES ("'.$row['id'].'","'.$_SESSION['user_email'].'")');
            
            $_SESSION['card_id_inprocess'] = $row['id'];
            // Save success message in session to display after redirect
            $_SESSION['save_success'] = "Company Name Saved. CARD Number is: ".$row['id'];
            // Redirect to same page to show success message
            header('Location: business-name.php?card_number='.$row['id']);
            exit;
        }
    } else {
        // Company name already exists - show error
        $_SESSION['save_error'] = "Company Name already exists. Please choose a different name.";
        header('Location: business-name.php');
        exit;
    }
}

// Handle update functionality (process2 - Save button, no redirect) - MUST be before header.php
if(isset($_POST['process2'])){
    $comp_name = preg_replace('/[^A-Za-z0-9\s\-]/', '', trim($_POST['d_comp_name'] ?? ''));
    if ($comp_name === '') {
        $_SESSION['save_error'] = "Please enter a valid business name (only letters, numbers, spaces and hyphen allowed).";
        header('Location: business-name.php?card_number='.$_SESSION['card_id_inprocess']);
        exit;
    }
    $comp_name = mysqli_real_escape_string($connect, $comp_name);

    // Ensure the name-change counter column exists; if not, add it with default 0
    $col_check = mysqli_query($connect, "SHOW COLUMNS FROM digi_card LIKE 'd_name_change_count'");
    if(!$col_check || mysqli_num_rows($col_check) == 0){
        // Try to add the column (silent failure will be handled later)
        @mysqli_query($connect, "ALTER TABLE digi_card ADD COLUMN d_name_change_count INT NOT NULL DEFAULT 0");
    }

    // Load current record to check change count and current name
    $current_id = intval($_SESSION['card_id_inprocess']);
    $current_query = mysqli_query($connect, 'SELECT d_comp_name, d_name_change_count FROM digi_card WHERE id="'.$current_id.'" LIMIT 1');
    $current_row = mysqli_fetch_array($current_query);
    $current_change_count = isset($current_row['d_name_change_count']) ? intval($current_row['d_name_change_count']) : 0;

    // Enforce maximum 2 name changes
    if($current_change_count >= 2){
        $_SESSION['save_error'] = "You have already reached the maximum of 2 business name changes. Contact support for further assistance.";
        header('Location: business-name.php?card_number='.$_SESSION['card_id_inprocess']);
        exit;
    }

    // If the submitted name is the same as current, no-op
    if(isset($current_row['d_comp_name']) && $comp_name === $current_row['d_comp_name']){
        $_SESSION['save_success'] = "No changes detected to the company name.";
        header('Location: business-name.php?card_number='.$_SESSION['card_id_inprocess']);
        exit;
    }

    // Check if company name already exists for a different record
    $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE d_comp_name="'.$comp_name.'" AND id != "'.$_SESSION['card_id_inprocess'].'"');

    if(mysqli_num_rows($query) == 0){
        // Company name is unique (or belongs to current record), allow update and increment change counter
        $card_id = str_replace(array(' ','.','&','/','','[',']'), array('-','','','-','',''), $comp_name);
        $update = mysqli_query($connect, 'UPDATE digi_card SET d_comp_name="'.$comp_name.'", card_id="'.$card_id.'", d_name_change_count = d_name_change_count + 1 WHERE id="'.$_SESSION['card_id_inprocess'].'"');
        if($update){
            $_SESSION['save_success'] = "Company Name Updated Successfully";
            header('Location: business-name.php?card_number='.$_SESSION['card_id_inprocess']);
            exit;
        } else {
            $_SESSION['save_error'] = "Failed to update company name. Please try again.";
            header('Location: business-name.php?card_number='.$_SESSION['card_id_inprocess']);
            exit;
        }
    } else {
        // Company name already exists for a different record - show error
        $_SESSION['save_error'] = "Company Name already exists. Please choose a different name.";
        header('Location: business-name.php?card_number='.$_SESSION['card_id_inprocess']);
        exit;
    }
}

// AJAX: check if business name already exists (for live validation)
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($is_ajax && (isset($_GET['check_business_name']) || isset($_POST['check_business_name']))) {
    header('Content-Type: application/json');
    $name = isset($_REQUEST['d_comp_name']) ? preg_replace('/[^A-Za-z0-9\s\-]/', '', trim($_REQUEST['d_comp_name'])) : '';
    $exclude_id = isset($_REQUEST['card_number']) ? intval($_REQUEST['card_number']) : (isset($_SESSION['card_id_inprocess']) ? intval($_SESSION['card_id_inprocess']) : 0);
    $exists = false;
    if ($name !== '') {
        $name_esc = mysqli_real_escape_string($connect, $name);
        $q = 'SELECT id FROM digi_card WHERE d_comp_name="' . $name_esc . '"';
        if ($exclude_id > 0) {
            $q .= ' AND id != "' . $exclude_id . '"';
        }
        $q .= ' LIMIT 1';
        $res = mysqli_query($connect, $q);
        $exists = $res && mysqli_num_rows($res) > 0;
    }
    echo json_encode(array('exists' => $exists));
    exit;
}

include '../includes/header.php';
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
        <div class="main-top">
        <span class="heading">Business Name</span>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
                </ol>
            </nav>
        </div>
        
        <?php if(isset($_GET['card_number'])): ?>
            <?php
            $_SESSION['card_id_inprocess'] = $_GET['card_number'];
            $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');
            $row = mysqli_fetch_array($query);
            
            if(mysqli_num_rows($query) == 0): ?>
                <div class="alert alert-danger">Card id Removed/Not available.</div>
            <?php else: ?>
                <?php if(isset($_SESSION['save_success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['save_success']; unset($_SESSION['save_success']); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                <?php if(isset($_SESSION['save_error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['save_error']; unset($_SESSION['save_error']); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                <div class="card mb-4">
                    <div class="card-body">
                        
                        <form action="#" method="POST" enctype="multipart/form-data" class="business_name_form" data-card-number="<?php echo isset($_GET['card_number']) ? (int)$_GET['card_number'] : ''; ?>">
                            <div class="form-group top_header_section">
                                <label for="d_comp_name">Business or Company Name: <span class="text-danger">*</span></label>
                                <input type="text" name="d_comp_name" class="form-control d_comp_name" maxlength="199" value="<?php echo htmlspecialchars($row['d_comp_name']); ?>" placeholder="Enter Your Business or Company Name*" pattern="[A-Za-z0-9\s\-]+" title="Only letters, numbers, spaces and hyphen (-) are allowed." required>
                                <div class="business_name_preview mt-2 d-none" aria-live="polite">
                                    <strong>Preview:</strong> <span class="preview_url_text"><?php echo htmlspecialchars($business_url); ?></span>
                                </div>
                                <br/>
                                <?php
                                    // Build public business URL (based on current host)
                                    // Use n.php?n=slug for preview pages (consistent with site preview router)
                                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                    $site_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
                                    $business_slug = !empty($row['card_id']) ? $row['card_id'] : preg_replace('/[^A-Za-z0-9\-]/', '-', strtolower(trim($row['d_comp_name'])));
                                    $business_url = $site_url . '/' . urlencode($business_slug);
                                    $change_count = isset($row['d_name_change_count']) ? intval($row['d_name_change_count']) : 0;
                                    $remaining = max(0, 2 - $change_count);
                                ?>
                                <sup>URL: <a href="<?php echo htmlspecialchars($business_url); ?>" target="_blank"><?php echo htmlspecialchars($business_url); ?></a></sup>
                                <br>
                                <sup>
                                    <?php if($remaining > 0): ?>
                                        You can change your business name <?php echo $remaining; ?> more time<?php echo ($remaining > 1) ? 's' : ''; ?>.
                                    <?php else: ?>
                                        You have reached the maximum of 2 business name changes.
                                    <?php endif; ?>
                                </sup>
                                
                                <div class="business_name_exists_msg mt-2 d-none text-danger" role="alert">
                                    This business name already exists. Please choose a different name.
                                </div>
                            </div>
                            <div class="Product-ServicesBtn" style="margin-top: 20px; width: 86%;">
                                <a href="../dashboard/" class="btn btn-secondary align-left">
                                    <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                                    <span>Back</span>
                                </a>
                                <button type="submit" name="process2" class="btn btn-primary align-center save_btn">
                                    <img src="../../assets/images/Save.png" class="img-fluid" width="35px" alt=""> 
                                    <span>Save</span>
                                </button>
                                <a href="select-theme.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right">
                                    <span>Next</span>
                                    <span class="right_angle angle"><i class="fa fa-angle-right"></i></span>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php if(isset($_SESSION['save_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['save_error']; unset($_SESSION['save_error']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            <div class="card mb-4">
                <div class="card-body">
                    
                    <form action="#" method="POST" enctype="multipart/form-data" class="business_name_form" data-card-number="">
                        <div class="form-group top_header_section">
                            <label for="d_comp_name">Business or Company Name: <span class="text-danger">*</span></label>
                            <input type="text" name="d_comp_name" class="form-control d_comp_name" maxlength="199" placeholder="Enter Your Business or Company Name*" pattern="[A-Za-z0-9\s\-]+" title="Only letters, numbers, spaces and hyphen (-) are allowed." required>
                            <sup>This name will not be changed later on so choose wisely</sup>
                            <div class="business_name_preview mt-2 d-none" aria-live="polite">
                                <strong>Preview:</strong> <span class="preview_url_text">—</span>
                            </div>
                            <div class="business_name_exists_msg alert alert-warning mt-2 d-none" role="alert">
                                This business name already exists. Please choose a different name.
                            </div>
                        </div>
                        <div class="Product-ServicesBtn" style="margin-top: 20px; width: 86%;">
                            <a href="../dashboard/" class="btn btn-secondary align-left">
                                <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                                <span>Back</span>
                            </a>
                            <button type="submit" name="process1" class="btn btn-primary align-center save_btn">
                                <img src="../../../assets/images/Save.png" class="img-fluid" width="35px" alt=""> 
                                <span>Save</span>
                            </button>
                            <a href="select-theme.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right">
                                <span>Skip</span>
                                <span class="right_angle angle"><i class="fa fa-angle-right"></i></span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var nameInput = document.querySelector('input[name="d_comp_name"]');
    var saveNextBtn = document.querySelector('.Product-ServicesBtn .btn.btn-primary.align-center');
    var previewEl = document.querySelector('.preview_url_text');
    var previewContainer = document.querySelector('.business_name_preview');
    var existsMsgEl = document.querySelector('.business_name_exists_msg');
    var form = nameInput ? nameInput.closest('form') : null;
    var cardNumber = form && form.getAttribute('data-card-number') ? form.getAttribute('data-card-number') : '';
    var checkTimeout = null;
    var lastCheckedName = '';
    var nameExists = false;

    // Build URL slug from business name (same logic as PHP: space . & / [ ] )
    function nameToSlug(name) {
        if (!name || typeof name !== 'string') return '';
        return name.replace(/\s/g, '-').replace(/\./g, '').replace(/&/g, '').replace(/\//g, '-').replace(/\[/g, '').replace(/\]/g, '').trim();
    }

    // Update preview as user types; show preview only when typing, hide when empty or after save
    function updatePreview() {
        if (!nameInput || !previewEl) return;
        var name = nameInput.value.trim();
        if (name === '') {
            if (previewContainer) previewContainer.classList.add('d-none');
            previewEl.textContent = '—';
            return;
        }
        if (previewContainer) previewContainer.classList.remove('d-none');
        var slug = nameToSlug(name);
        var url = window.location.origin + '/' + (slug || 'your-business');
        previewEl.textContent = url;
    }

    // Check if name exists (debounced)
    function checkNameExists() {
        var name = nameInput.value.trim();
        if (name === '') {
            if (existsMsgEl) existsMsgEl.classList.add('d-none');
            nameExists = false;
            setSaveDisabled(false);
            return;
        }
        if (name === lastCheckedName) {
            setSaveDisabled(nameExists);
            return;
        }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'business-name.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            try {
                var data = JSON.parse(xhr.responseText || '{}');
                lastCheckedName = name;
                nameExists = data.exists === true;
                if (existsMsgEl) {
                    if (nameExists) existsMsgEl.classList.remove('d-none');
                    else existsMsgEl.classList.add('d-none');
                }
                setSaveDisabled(nameExists);
            } catch (e) {
                setSaveDisabled(false);
            }
        };
        xhr.send('check_business_name=1&d_comp_name=' + encodeURIComponent(name) + (cardNumber ? '&card_number=' + encodeURIComponent(cardNumber) : ''));
    }

    function setSaveDisabled(disabled) {
        if (!saveNextBtn) return;
        if (disabled) {
            saveNextBtn.disabled = true;
            saveNextBtn.setAttribute('aria-disabled', 'true');
        } else {
            saveNextBtn.disabled = false;
            saveNextBtn.removeAttribute('aria-disabled');
        }
    }

    function scheduleCheck() {
        if (checkTimeout) clearTimeout(checkTimeout);
        checkTimeout = setTimeout(function () {
            checkTimeout = null;
            checkNameExists();
        }, 500);
    }

    // Show Save when textbox is empty; hide when it has value (original behavior)
    function toggleSaveNextVisibility() {
        if (!nameInput || !saveNextBtn) return;
        if (nameInput.value.trim() !== '') {
            saveNextBtn.classList.add('d-none');
        } else {
            saveNextBtn.classList.remove('d-none');
        }
    }

    // Allow only letters, numbers, spaces and hyphen (-)
    function sanitizeBusinessName(val) {
        return (val || '').replace(/[^A-Za-z0-9\s\-]/g, '');
    }

    if (nameInput) {
        nameInput.addEventListener('input', function () {
            var start = this.selectionStart, end = this.selectionEnd;
            var sanitized = sanitizeBusinessName(this.value);
            if (sanitized !== this.value) {
                this.value = sanitized;
                var len = sanitized.length;
                this.setSelectionRange(Math.min(start, len), Math.min(end, len));
            }
            updatePreview();
            scheduleCheck();
            toggleSaveNextVisibility();
        });
        nameInput.addEventListener('change', function () {
            var sanitized = sanitizeBusinessName(this.value);
            if (sanitized !== this.value) this.value = sanitized;
            updatePreview();
            scheduleCheck();
            toggleSaveNextVisibility();
        });
        updatePreview();
        toggleSaveNextVisibility();
        // Initial check if field has value (e.g. edit form)
        if (nameInput.value.trim() !== '') scheduleCheck();
    }
});
</script>


<style>
    .business_name_form{
        display: flex;
        align-items: center;
         gap: 20px;
         flex-direction: column;
    }
    .business_name_form label{
       font-size:24px !important;
    }
    .business_name_form button{
        padding: 8px;
        margin-top: 5px !important;
        width: 165px;
        font-size: 17px !important;
    }

    .business_name_form sup{
        font-size: 20px;
        top: 5px;
        left: 3px;
    }
    .Product-ServicesBtn{
        padding: 0px 40px;
        display: flex;
        justify-content: space-between;
        margin-top: 30px;
    }
    .Product-ServicesBtn button,
    .Product-ServicesBtn a{
        display: flex !important;
        color: #fff !important;
        justify-content: center;
        align-items: center;
        gap: 10px;
        text-decoration: none;
    }
    .Product-ServicesBtn button .angle,
    .Product-ServicesBtn a .angle{
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #fff !important;
        color:#000;
        font-weight:bold;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .top_header_section{
        width: 80%; margin-top: 20px; margin-bottom: 0px;
    }
    .Product-ServicesBtn button span:not(.angle),
    .Product-ServicesBtn a span:not(.angle){
        font-weight:500;
        font-size:16px;
    }
    .Product-ServicesBtn .align-center{
        padding: 4px 10px;
    }
    .Product-ServicesBtn .align-center img{
        width: 23px;
    }
    .Product-ServicesBtn .align-center span{
        color:#000;
    }

    .Product-ServicesBtn  .btn{
        line-height:24px !important;
    }
    .Product-ServicesBtn button {
        padding: 7px !important;
        margin-top: 22px !important;
    }

    @media screen and (max-width: 768px) {
.top_header_section{
        width: 100%; margin-top: 0px; margin-bottom: 0px;
    }
    .card-body {
    padding: 30px 20px!important;
    padding-bottom: 100px !important;
}
.business_name_form label {
    font-size: 22px !important;
}
.d_comp_name{
    padding:20px 10px;
    font-size:16px;
}
.business_name_form sup {
    font-size: 16px;
    top: 5px;
    left: 3px;
}
.Product-ServicesBtn{
    width: 80% !important;
    padding:0px;
            margin-top: 40px !important;
}
.save_btn{
        position: absolute;
    bottom: 150px;
    width: 145px !important;
    left: 96px;
    height: 36px;
}
.Copyright-left,
.Copyright-right{
    padding:0px;
}

    }
    .save_btn{
    width: 115px !important;
  
}
</style>

<?php include '../includes/footer.php'; ?>






