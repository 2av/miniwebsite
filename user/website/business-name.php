<?php
// Handle card_number from URL - store in session and cookie
// MUST be done before any output (before including header.php)
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/role_helper.php');
require_once(__DIR__ . '/../../app/helpers/role_access_helper.php');
require_login('/login/customer.php');

// Clear any existing card_id_inprocess when creating a new website (no card_number in URL or new=1 parameter)
if((!isset($_GET['card_number']) || empty($_GET['card_number'])) || (isset($_GET['new']) && $_GET['new'] == '1')) {
    // If no card_number in URL or explicitly creating new, clear session and cookie to start fresh
    unset($_SESSION['card_id_inprocess']);
    setcookie('card_id_inprocess', '', time() - 3600, '/'); // Delete cookie
    unset(
        $_SESSION['pending_mw_create'],
        $_SESSION['pending_mw_comp_name'],
        $_SESSION['pending_mw_display_name'],
        $_SESSION['pending_mw_card_slug'],
        $_SESSION['pending_mw_f_user_email'],
        $_SESSION['pending_mw_user_email']
    );
}

// Count existing Mini Websites for this user (1st MW is free; 2nd+ requires payment before creation)
$user_mw_count = 0;
$requires_pay_for_new_mw = false;
if (!empty($_SESSION['user_email'])) {
    $user_email_cnt_esc = mysqli_real_escape_string($connect, $_SESSION['user_email']);
    $mw_count_query = mysqli_query($connect, 'SELECT COUNT(*) AS cnt FROM digi_card WHERE user_email="' . $user_email_cnt_esc . '"');
    if ($mw_count_query && ($mw_count_row = mysqli_fetch_assoc($mw_count_query))) {
        $user_mw_count = (int) ($mw_count_row['cnt'] ?? 0);
    }
}
if ((!isset($_GET['card_number']) || empty($_GET['card_number'])) && $user_mw_count >= 1) {
    $requires_pay_for_new_mw = true;
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
    $display_name = trim($_POST['d_display_name'] ?? '');
    
    if ($comp_name === '') {
        $_SESSION['save_error'] = "Please enter a valid business name (only letters, numbers, spaces and hyphen allowed).";
        header('Location: business-name.php');
        exit;
    }
    if ($display_name === '') {
        $_SESSION['save_error'] = "Please enter a Business Name.";
        header('Location: business-name.php');
        exit;
    }
    
    $comp_name = mysqli_real_escape_string($connect, $comp_name);
    $display_name = mysqli_real_escape_string($connect, $display_name);
    
    // Check if company name already exists - MUST be unique
    $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE d_comp_name="'.$comp_name.'"');
    if(mysqli_num_rows($query) == 0){
        $card_id = str_replace(array(' ','.','&','/','','[',']'), array('-','','','-','',''), $comp_name);
        $card_id_esc = mysqli_real_escape_string($connect, $card_id);
        $slug_taken = mysqli_query($connect, 'SELECT id FROM digi_card WHERE card_id="'.$card_id_esc.'" LIMIT 1');
        $slug_prev = mysqli_query($connect, 'SELECT digi_card_id FROM digi_card_previous_slug WHERE previous_slug="'.$card_id_esc.'" LIMIT 1');
        if (mysqli_num_rows($slug_taken) > 0 || mysqli_num_rows($slug_prev) > 0) {
            $_SESSION['save_error'] = "This MiniWebsite URL is reserved or locked. Please choose a different URL.";
            header('Location: business-name.php');
            exit;
        }

        // 2nd+ Mini Website: payment required before creating the card entry
        if ($requires_pay_for_new_mw) {
            $_SESSION['pending_mw_create'] = true;
            $_SESSION['pending_mw_comp_name'] = $comp_name;
            $_SESSION['pending_mw_display_name'] = $display_name;
            $_SESSION['pending_mw_card_slug'] = $card_id_esc;
            $_SESSION['pending_mw_f_user_email'] = $franchisee_email;
            $_SESSION['pending_mw_user_email'] = $_SESSION['user_email'];
            header('Location: ../../payment/pay_miniwebsite.php?new_mw=1');
            exit;
        }

        // First Mini Website — create card immediately (free; complimentary per role profile)
        $date = date('Y-m-d H:i:s');
        $ras_create = get_current_user_role_access_settings($connect);
        $complimentary_rules = get_complimentary_website_rules($connect, $ras_create['profile_key'] ?? null);
        $complimentary_flag = 'No';
        $validity_sql = 'DATE_ADD("' . $date . '", INTERVAL 7 DAY)';
        if (!empty($complimentary_rules['apply'])) {
            $complimentary_flag = 'Yes';
            $validity_sql = complimentary_validity_sql($complimentary_rules);
        }

        $insert = mysqli_query($connect, 'INSERT INTO digi_card (d_comp_name,d_display_name,uploaded_date,d_payment_status,user_email,d_card_status,card_id,f_user_email,validity_date,complimentary_enabled) VALUES ("'.$comp_name.'","'.$display_name.'","'.$date.'","Created","'.$_SESSION['user_email'].'","Active","'.$card_id_esc.'","'.$franchisee_email.'",'.$validity_sql.',"'.$complimentary_flag.'")');
        
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
    $display_name = trim($_POST['d_display_name'] ?? '');
    
    if ($comp_name === '') {
        $_SESSION['save_error'] = "Please enter a valid business name (only letters, numbers, spaces and hyphen allowed).";
        header('Location: business-name.php?card_number='.$_SESSION['card_id_inprocess']);
        exit;
    }
    if ($display_name === '') {
        $_SESSION['save_error'] = "Please enter a Business Name.";
        header('Location: business-name.php?card_number='.$_SESSION['card_id_inprocess']);
        exit;
    }
    
    $comp_name = mysqli_real_escape_string($connect, $comp_name);
    $display_name = mysqli_real_escape_string($connect, $display_name);

    // Ensure the name-change counter column exists; if not, add it with default 0
    $col_check = mysqli_query($connect, "SHOW COLUMNS FROM digi_card LIKE 'd_name_change_count'");
    if(!$col_check || mysqli_num_rows($col_check) == 0){
        // Try to add the column (silent failure will be handled later)
        @mysqli_query($connect, "ALTER TABLE digi_card ADD COLUMN d_name_change_count INT NOT NULL DEFAULT 0");
    }
    // Load current record to check change count and current name
    $current_id = intval($_SESSION['card_id_inprocess']);
    $current_query = mysqli_query($connect, 'SELECT d_comp_name, d_name_change_count, card_id FROM digi_card WHERE id="'.$current_id.'" LIMIT 1');
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

    $card_id = str_replace(array(' ','.','&','/','','[',']'), array('-','','','-','',''), $comp_name);
    $card_id_esc = mysqli_real_escape_string($connect, $card_id);
    $cid = intval($_SESSION['card_id_inprocess']);
    // Block taking a URL slug reserved as another card's previous (locked) URL
    $slug_taken = mysqli_query($connect, 'SELECT id FROM digi_card WHERE card_id="'.$card_id_esc.'" AND id != '.$cid.' LIMIT 1');
    $slug_prev = mysqli_query($connect, 'SELECT digi_card_id FROM digi_card_previous_slug WHERE previous_slug="'.$card_id_esc.'" AND digi_card_id != '.$cid.' LIMIT 1');
    if(mysqli_num_rows($query) == 0 && mysqli_num_rows($slug_taken) == 0 && mysqli_num_rows($slug_prev) == 0){
        // Company name is unique; slug not locked by another card — allow update and increment change counter
        $old_card_id = mysqli_real_escape_string($connect, isset($current_row['card_id']) ? (string) $current_row['card_id'] : '');
        $update = mysqli_query($connect, 'UPDATE digi_card SET d_comp_name="'.$comp_name.'", d_display_name="'.$display_name.'", card_id="'.$card_id_esc.'", d_name_change_count = d_name_change_count + 1 WHERE id="'.$_SESSION['card_id_inprocess'].'"');
        if($update){
            // Preserve overflow/meta columns stored on digi_card_previous_slug when slug row is rotated
            $meta_btype = '';
            $meta_barea = '';
            $meta_blocs = '';
            $meta_res = @mysqli_query($connect, 'SELECT d_business_type, d_business_operation_area, d_business_operation_locations FROM digi_card_previous_slug WHERE digi_card_id='.$cid.' LIMIT 1');
            if ($meta_res && mysqli_num_rows($meta_res) > 0) {
                $meta_row = mysqli_fetch_assoc($meta_res);
                $meta_btype = isset($meta_row['d_business_type']) ? mysqli_real_escape_string($connect, $meta_row['d_business_type']) : '';
                $meta_barea = isset($meta_row['d_business_operation_area']) ? mysqli_real_escape_string($connect, $meta_row['d_business_operation_area']) : '';
                $meta_blocs = isset($meta_row['d_business_operation_locations']) ? mysqli_real_escape_string($connect, $meta_row['d_business_operation_locations']) : '';
            }

            mysqli_query($connect, 'DELETE FROM digi_card_previous_slug WHERE digi_card_id='.$cid);
            if ($old_card_id !== '') {
                mysqli_query($connect, 'INSERT INTO digi_card_previous_slug (digi_card_id, previous_slug, d_business_type, d_business_operation_area, d_business_operation_locations) VALUES ('.$cid.', "'.$old_card_id.'", "'.$meta_btype.'", "'.$meta_barea.'", "'.$meta_blocs.'")');
            }
            $_SESSION['save_success'] = "Company Name Updated Successfully";
            header('Location: business-name.php?card_number='.$_SESSION['card_id_inprocess']);
            exit;
        } else {
            $_SESSION['save_error'] = "Failed to update company name. Please try again.";
            header('Location: business-name.php?card_number='.$_SESSION['card_id_inprocess']);
            exit;
        }
    } else {
        if (mysqli_num_rows($query) > 0) {
            $_SESSION['save_error'] = "Company Name already exists. Please choose a different name.";
        } else {
            $_SESSION['save_error'] = "This MiniWebsite URL is reserved or locked. Please choose a different URL.";
        }
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
        $cid_raw = str_replace(array(' ','.','&','/','','[',']'), array('-','','','-','',''), $name);
        $cid_esc = mysqli_real_escape_string($connect, $cid_raw);
        $q = 'SELECT id FROM digi_card WHERE d_comp_name="' . $name_esc . '"';
        if ($exclude_id > 0) {
            $q .= ' AND id != "' . $exclude_id . '"';
        }
        $q .= ' LIMIT 1';
        $res = mysqli_query($connect, $q);
        $exists = $res && mysqli_num_rows($res) > 0;
        if (!$exists) {
            $q2 = 'SELECT id FROM digi_card WHERE card_id="' . $cid_esc . '"';
            if ($exclude_id > 0) {
                $q2 .= ' AND id != "' . $exclude_id . '"';
            }
            $q2 .= ' LIMIT 1';
            $res2 = mysqli_query($connect, $q2);
            $exists = $res2 && mysqli_num_rows($res2) > 0;
        }
        if (!$exists) {
            $q3 = 'SELECT digi_card_id FROM digi_card_previous_slug WHERE previous_slug="' . $cid_esc . '"';
            if ($exclude_id > 0) {
                $q3 .= ' AND digi_card_id != "' . $exclude_id . '"';
            }
            $q3 .= ' LIMIT 1';
            $res3 = mysqli_query($connect, $q3);
            $exists = $res3 && mysqli_num_rows($res3) > 0;
        }
    }
    echo json_encode(array('exists' => $exists));
    exit;
}

include '../includes/header.php';

$default_business_name = '';
if (!isset($_GET['card_number']) || empty($_GET['card_number'])) {
    $default_business_name = trim((string)($_SESSION['user_name'] ?? ''));
    if ($default_business_name === '' && !empty($_SESSION['user_email'])) {
        $safe_user_email = mysqli_real_escape_string($connect, (string)$_SESSION['user_email']);
        $name_query = mysqli_query($connect, "SELECT name FROM user_details WHERE role = 'CUSTOMER' AND email = '$safe_user_email' LIMIT 1");
        if ($name_query && mysqli_num_rows($name_query) > 0) {
            $name_row = mysqli_fetch_array($name_query);
            $default_business_name = trim((string)($name_row['name'] ?? ''));
        }
    }
    $default_business_name = preg_replace('/\s+/', ' ', $default_business_name);
}
?>

<!-- Phase B · Step 5 — business-name.php uses central .mw-* design system + mw_button helpers.
     JS hooks: .business_name_form, .save_btn, .preview_url_text, .business_name_preview, etc. -->
<?php
$mw_step_next_href = 'select-theme.php' . (!empty($_SESSION['card_id_inprocess']) ? '?card_number=' . (int)$_SESSION['card_id_inprocess'] : '');
$mw_save_icon = '../../assets/images/Save.png';
?>
<main class="Dashboard mw-page">
    <div class="container-fluid customer_content_area px-4">
        <div class="main-top mw-page-header">
            <h1 class="mw-page-title">Business Name</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mw-breadcrumb">
                    <li class="breadcrumb-item mw-breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item mw-breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
                </ol>
            </nav>
        </div>

        <?php if(isset($_GET['card_number'])): ?>
            <?php
            $_SESSION['card_id_inprocess'] = $_GET['card_number'];
            $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');
            $row = mysqli_fetch_array($query);
            $display_name_value = '';
            $row_previous_slug = '';
            if ($row && mysqli_num_rows($query) > 0) {
                // Auto-fill Business Name from Business URL when display name is blank (common in franchisee-created MW)
                $display_name_value = trim((string)($row['d_display_name'] ?? ''));
                if ($display_name_value === '') {
                    $display_name_value = trim((string)($row['d_comp_name'] ?? ''));
                    if ($display_name_value !== '') {
                        $cid_fill = intval($row['id']);
                        mysqli_query(
                            $connect,
                            'UPDATE digi_card SET d_display_name="' . mysqli_real_escape_string($connect, $display_name_value) . '" WHERE id="' . $cid_fill . '" LIMIT 1'
                        );
                    }
                }
                $cid_view = intval($row['id']);
                $q_ps = mysqli_query($connect, 'SELECT previous_slug FROM digi_card_previous_slug WHERE digi_card_id='.$cid_view.' LIMIT 1');
                if ($q_ps && mysqli_num_rows($q_ps) > 0) {
                    $rps = mysqli_fetch_array($q_ps);
                    $row_previous_slug = (string) ($rps['previous_slug'] ?? '');
                }
            }

            if(mysqli_num_rows($query) == 0): ?>
                <div class="alert alert-danger mw-alert mw-alert-danger" role="alert">
                    <i class="fa fa-exclamation-circle mw-alert-icon" aria-hidden="true"></i>
                    <div class="mw-alert-body">Card id Removed/Not available.</div>
                </div>
            <?php else: ?>
                <?php if(isset($_SESSION['save_success'])): ?>
                    <div class="alert alert-dismissible fade show mw-alert mw-alert-success" role="alert">
                        <i class="fa fa-check-circle mw-alert-icon" aria-hidden="true"></i>
                        <div class="mw-alert-body"><?php echo $_SESSION['save_success']; unset($_SESSION['save_success']); ?></div>
                        <button type="button" class="close mw-alert-close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                <?php endif; ?>
                <?php if(isset($_SESSION['save_error'])): ?>
                    <div class="alert alert-dismissible fade show mw-alert mw-alert-danger" role="alert">
                        <i class="fa fa-exclamation-circle mw-alert-icon" aria-hidden="true"></i>
                        <div class="mw-alert-body"><?php echo $_SESSION['save_error']; unset($_SESSION['save_error']); ?></div>
                        <button type="button" class="close mw-alert-close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4 mw-card">
                    <div class="card-body mw-card-body">
                        <form action="#" method="POST" enctype="multipart/form-data" class="business_name_form mw-form mw-business-name-form" data-card-number="<?php echo isset($_GET['card_number']) ? (int)$_GET['card_number'] : ''; ?>">
                            <div class="mw-business-name-fields-row">
                                <div class="form-group top_header_section mw-form-group">
                                    <label class="mw-label mw-label-lg" for="d_display_name">Business Name<span class="req">*</span></label>
                                    <input type="text" id="d_display_name" name="d_display_name" class="form-control d_display_name mw-input mw-input-lg" maxlength="199" value="<?php echo htmlspecialchars($display_name_value); ?>" placeholder="Enter Business Name" required>
                                </div>
                                <div class="form-group top_header_section mw-form-group">
                                    <label class="mw-label mw-label-lg" for="d_comp_name">Business URL<span class="req">*</span></label>
                                    <input type="text" id="d_comp_name" name="d_comp_name" class="form-control d_comp_name mw-input mw-input-lg" maxlength="199" value="<?php echo htmlspecialchars($row['d_comp_name']); ?>" placeholder="Enter Your Business URL" pattern="[A-Za-z0-9\s\-]+" title="Only letters, numbers, spaces and hyphen (-) are allowed." required>
                                </div>
                            </div>
                            <div class="mw-business-name-url-extra">
                                <?php
                                    // Build public business URL (based on current host)
                                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                    $site_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
                                    $business_slug = !empty($row['card_id']) ? $row['card_id'] : preg_replace('/[^A-Za-z0-9\-]/', '-', strtolower(trim($row['d_comp_name'])));
                                    $business_url = $site_url . '/' . urlencode($business_slug);
                                    $change_count = isset($row['d_name_change_count']) ? intval($row['d_name_change_count']) : 0;
                                    $remaining = max(0, 2 - $change_count);
                                ?>
                                <div class="business_name_preview mt-2 d-none mw-alert mw-alert-info mw-alert-compact" aria-live="polite">
                                    <i class="fa fa-eye mw-alert-icon" aria-hidden="true"></i>
                                    <div class="mw-alert-body"><strong>Preview:</strong>
                                        <span class="preview_url_text" style="margin-left:0.25rem;word-break:break-all;"><?php echo htmlspecialchars($business_url); ?></span>
                                    </div>
                                </div>
                                <div class="business_name_exists_msg mt-2 d-none mw-alert mw-alert-danger mw-alert-compact" role="alert">
                                    <i class="fa fa-exclamation-circle mw-alert-icon" aria-hidden="true"></i>
                                    <div class="mw-alert-body">This URL is already taken. Please choose something else.</div>
                                </div>
                                <div class="mw-meta">
                                    <p class="mw-meta-line">
                                        <i class="fa fa-link" aria-hidden="true"></i>
                                        <span class="mw-meta-label">URL:</span>
                                        <a class="mw-meta-link" href="<?php echo htmlspecialchars($business_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($business_url); ?></a>
                                    </p>
                                    <?php if (!empty($row_previous_slug)):
                                        $prev_slug = $row_previous_slug;
                                        $prev_url = $site_url . '/' . urlencode($prev_slug);
                                    ?>
                                    <p class="mw-meta-line">
                                        <i class="fa fa-history" aria-hidden="true"></i>
                                        <span class="mw-meta-label">Previous URL (reference):</span>
                                        <a class="mw-meta-link mw-meta-link--muted" href="<?php echo htmlspecialchars($prev_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($prev_url); ?></a>
                                    </p>
                                    <?php endif; ?>
                                    <p class="mw-meta-line <?php echo $remaining > 0 ? 'is-warning' : 'is-danger'; ?>">
                                        <i class="fa <?php echo $remaining > 0 ? 'fa-info-circle' : 'fa-lock'; ?>" aria-hidden="true"></i>
                                        <?php if($remaining > 0): ?>
                                            <span>You can change your business URL <strong><?php echo $remaining; ?></strong> more time<?php echo ($remaining > 1) ? 's' : ''; ?>.</span>
                                        <?php else: ?>
                                            <span>You have reached the maximum of 2 business name changes.</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <?php
                            echo mw_button_row_step([
                                'back' => ['href' => '../dashboard/', 'label' => 'Back'],
                                'save' => ['type' => 'submit', 'name' => 'process2', 'label' => 'Save', 'class' => 'save_btn', 'img' => $mw_save_icon],
                                'next' => ['href' => $mw_step_next_href, 'label' => 'Next'],
                            ]);
                            ?>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php if(isset($_SESSION['save_error'])): ?>
                <div class="alert alert-dismissible fade show mw-alert mw-alert-danger" role="alert">
                    <i class="fa fa-exclamation-circle mw-alert-icon" aria-hidden="true"></i>
                    <div class="mw-alert-body"><?php echo $_SESSION['save_error']; unset($_SESSION['save_error']); ?></div>
                    <button type="button" class="close mw-alert-close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>
            <div class="card mb-4 mw-card">
                <div class="card-body mw-card-body">
                    <form action="#" method="POST" enctype="multipart/form-data" class="business_name_form mw-form mw-business-name-form" data-card-number="" data-requires-payment="<?php echo $requires_pay_for_new_mw ? '1' : '0'; ?>">
                        <div class="mw-business-name-fields-row">
                            <div class="form-group top_header_section mw-form-group">
                                <label class="mw-label mw-label-lg" for="d_display_name">Business Name<span class="req">*</span></label>
                                <input type="text" id="d_display_name" name="d_display_name" class="form-control d_display_name mw-input mw-input-lg" maxlength="199" placeholder="Enter Business Name" value="<?php echo htmlspecialchars($default_business_name); ?>" required>
                            </div>
                            <div class="form-group top_header_section mw-form-group">
                                <label class="mw-label mw-label-lg" for="d_comp_name">Business URL<span class="req">*</span></label>
                                <input type="text" id="d_comp_name" name="d_comp_name" class="form-control d_comp_name mw-input mw-input-lg" maxlength="199" placeholder="Enter Your Business URL" value="<?php echo htmlspecialchars($default_business_name); ?>" pattern="[A-Za-z0-9\s\-]+" title="Only letters, numbers, spaces and hyphen (-) are allowed." required>
                            </div>
                        </div>
                        <?php if ($requires_pay_for_new_mw): ?>
                            <div class=" mw-alert mw-alert-info mt-3">
                                <i class="fa fa-info-circle mw-alert-icon" aria-hidden="true"></i>
                                <div class="mw-alert-body">You already have a Mini Website. To create another one, complete payment after entering your business details below.</div>
                            </div>
                        <?php endif; ?>
                        <div class="mw-business-name-url-extra">
                            <div class="business_name_preview mt-2 d-none mw-alert mw-alert-info mw-alert-compact" aria-live="polite">
                                <i class="fa fa-eye mw-alert-icon" aria-hidden="true"></i>
                                <div class="mw-alert-body"><strong>Preview:</strong>
                                    <span class="preview_url_text" style="margin-left:0.25rem;word-break:break-all;">&mdash;</span>
                                </div>
                            </div>
                            <div class="business_name_exists_msg mt-2 d-none mw-alert mw-alert-danger mw-alert-compact" role="alert">
                                <i class="fa fa-exclamation-circle mw-alert-icon" aria-hidden="true"></i>
                                <div class="mw-alert-body">This URL is already taken. Please choose something else.</div>
                            </div>
                        </div>
                        <?php
                        $new_mw_save_btn = $requires_pay_for_new_mw
                            ? ['type' => 'submit', 'name' => 'process1', 'label' => 'Pay Now', 'class' => 'save_btn', 'variant' => 'primary', 'icon' => 'fa-credit-card']
                            : ['type' => 'submit', 'name' => 'process1', 'label' => 'Save', 'class' => 'save_btn', 'img' => $mw_save_icon];
                        $new_mw_step_row = [
                            'back' => ['href' => '../dashboard/', 'label' => 'Back'],
                            'save' => $new_mw_save_btn,
                        ];
                        if (!$requires_pay_for_new_mw) {
                            $new_mw_step_row['next'] = ['href' => $mw_step_next_href, 'label' => 'Skip'];
                        }
                        echo mw_button_row_step($new_mw_step_row);
                        ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
.mw-business-name-form {
    width: 100%;
    max-width: none;
    margin-inline: 0;
}
.mw-business-name-fields-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 1rem 1.5rem;
    width: 100%;
}
.mw-business-name-fields-row > .mw-form-group {
    flex: 1 1 0;
    min-width: min(100%, 16rem);
    margin-bottom: 0;
}
.mw-business-name-fields-row > .mw-form-group:first-child {
    padding-right: 0.5rem;
}
.mw-business-name-fields-row > .mw-form-group:last-child {
    padding-left: 0.5rem;
}
.mw-business-name-fields-row .form-control {
    width: 100%;
}
.mw-business-name-url-extra {
    width: 100%;
    margin-top: 1rem;
}
@media (max-width: 767.98px) {
    .mw-business-name-fields-row {
        flex-direction: column;
        gap: 1rem;
    }
    .mw-business-name-fields-row > .mw-form-group:first-child,
    .mw-business-name-fields-row > .mw-form-group:last-child {
        padding-left: 0;
        padding-right: 0;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var urlInput = document.querySelector('input[name="d_comp_name"]');
    var businessNameInput = document.querySelector('input[name="d_display_name"]');
    var form = urlInput ? urlInput.closest('form') : null;
    var saveNextBtn = form ? form.querySelector('.save_btn') : null;
    var previewEl = form ? form.querySelector('.preview_url_text') : null;
    var previewContainer = form ? form.querySelector('.business_name_preview') : null;
    var existsMsgEl = form ? form.querySelector('.business_name_exists_msg') : null;
    var cardNumber = form && form.getAttribute('data-card-number') ? form.getAttribute('data-card-number') : '';
    var requiresPayment = form && form.getAttribute('data-requires-payment') === '1';
    var checkTimeout = null;
    var lastCheckedName = '';
    var nameExists = false;
    var originalValue = urlInput ? urlInput.value.trim() : '';
    var urlManuallyEdited = false;
    var userStartedTyping = false;

    // Build URL slug from business name (same logic as PHP: space . & / [ ] )
    function nameToSlug(name) {
        if (!name || typeof name !== 'string') return '';
        return name.replace(/\s/g, '-').replace(/\./g, '').replace(/&/g, '').replace(/\//g, '-').replace(/\[/g, '').replace(/\]/g, '').trim();
    }

    function hidePreview() {
        if (previewContainer) previewContainer.classList.add('d-none');
        if (previewEl) previewEl.textContent = '—';
    }

    // Show preview only after user starts typing; hide when field is empty
    function updatePreview() {
        if (!urlInput || !previewEl) return;
        var name = urlInput.value.trim();
        if (!userStartedTyping || name === '') {
            hidePreview();
            return;
        }
        if (previewContainer) previewContainer.classList.remove('d-none');
        var slug = nameToSlug(name);
        var url = window.location.origin + '/' + (slug || 'your-business');
        previewEl.textContent = url;
    }

    // Check if name exists (debounced)
    function checkNameExists() {
        var name = urlInput.value.trim();
        if (name === '') {
            if (existsMsgEl) existsMsgEl.classList.add('d-none');
            nameExists = false;
            setSaveDisabled(!canSubmitNewMw());
            updatePreview();
            return;
        }
        if (name === lastCheckedName) {
            setSaveDisabled(!canSubmitNewMw());
            updatePreview();
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
                setSaveDisabled(!canSubmitNewMw());
                updatePreview();
            } catch (e) {
                setSaveDisabled(!canSubmitNewMw());
                updatePreview();
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

    function toggleSaveNextVisibility() {
        if (!urlInput || !saveNextBtn) return;
        if (requiresPayment) {
            saveNextBtn.classList.remove('d-none');
            return;
        }
        if (urlInput.value.trim() !== '') {
            saveNextBtn.classList.add('d-none');
        } else {
            saveNextBtn.classList.remove('d-none');
        }
    }

    function canSubmitNewMw() {
        if (!urlInput || !businessNameInput) return false;
        var urlVal = urlInput.value.trim();
        var nameVal = businessNameInput.value.trim();
        if (urlVal === '' || nameVal === '') return false;
        if (nameExists) return false;
        if (requiresPayment) {
            return true;
        }
        return hasValueChanged() && urlVal !== '';
    }

    // Allow only letters, numbers, spaces and hyphen (-)
    function sanitizeBusinessName(val) {
        return (val || '').replace(/[^A-Za-z0-9\s\-]/g, '');
    }

    // Check if value has changed from original
    function hasValueChanged() {
        if (!urlInput) return false;
        return urlInput.value.trim() !== originalValue;
    }

    // Auto-fill business URL with business name if user hasn't manually edited it
    if (businessNameInput) {
        businessNameInput.addEventListener('input', function () {
            userStartedTyping = true;
            if (urlInput && !urlManuallyEdited) {
                urlInput.value = this.value;
                updatePreview();
                scheduleCheck();
            }
            setSaveDisabled(!canSubmitNewMw());
        });
    }

    if (urlInput) {
        urlInput.addEventListener('input', function () {
            userStartedTyping = true;
            // Mark as manually edited if user types something different from what auto-fill would provide
            if (businessNameInput && this.value !== businessNameInput.value) {
                urlManuallyEdited = true;
            }
            var start = this.selectionStart, end = this.selectionEnd;
            var sanitized = sanitizeBusinessName(this.value);
            if (sanitized !== this.value) {
                this.value = sanitized;
                var len = sanitized.length;
                this.setSelectionRange(Math.min(start, len), Math.min(end, len));
            }
            updatePreview();
            
            setSaveDisabled(!canSubmitNewMw());
            
            scheduleCheck();
            toggleSaveNextVisibility();
        });
        urlInput.addEventListener('change', function () {
            if (this.value.trim() !== '') userStartedTyping = true;
            var sanitized = sanitizeBusinessName(this.value);
            if (sanitized !== this.value) this.value = sanitized;
            updatePreview();
            scheduleCheck();
            toggleSaveNextVisibility();
        });
        hidePreview();
        toggleSaveNextVisibility();
        setSaveDisabled(!canSubmitNewMw());
    }

});
</script>


<?php include '../includes/footer.php'; ?>






