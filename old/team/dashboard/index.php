<?php include '../header.php'; ?>
<?php
// Clear any applied promocodes when accessing customer dashboard
// This ensures promocodes are only valid during the payment flow
if (isset($_SESSION['promo_code'])) {
    unset($_SESSION['promo_code']);
}
if (isset($_SESSION['promo_discount'])) {
    unset($_SESSION['promo_discount']);
}
if (isset($_SESSION['auto_applied_promo'])) {
    unset($_SESSION['auto_applied_promo']);
}

// Clear any promo check keys to reset the auto-apply logic
foreach ($_SESSION as $key => $value) {
    if (strpos($key, 'promo_check_') === 0) {
        unset($_SESSION[$key]);
    }
}

// Check if user just registered and show welcome message
$show_welcome = false;
if (isset($_SESSION['just_registered']) && $_SESSION['just_registered'] === true) {
    $show_welcome = true;
    unset($_SESSION['just_registered']); // Clear the flag
}

// Get user's cards - assuming user email is stored in session
$user_email = $_SESSION['user_email'] ?? '';
$user_referral_code = $_SESSION['user_referral_code'] ?? '';

// If referral code is not in session, get it from team_members table
if (empty($user_referral_code)) {
    $team_stmt = $connect->prepare("SELECT referral_code FROM team_members WHERE member_email = ?");
    if ($team_stmt) {
        $team_stmt->bind_param("s", $user_email);
        $team_stmt->execute();
        $team_result = $team_stmt->get_result();
        $team_data = $team_result->fetch_assoc();
        $team_stmt->close();
        
        if ($team_data && !empty($team_data['referral_code'])) {
            $user_referral_code = $team_data['referral_code'];
            $_SESSION['user_referral_code'] = $user_referral_code;
        } else {
            // Generate referral code if it doesn't exist
            $user_referral_code = strtoupper(substr(md5($user_email . time()), 0, 8));
            $update_stmt = $connect->prepare("UPDATE team_members SET referral_code = ? WHERE member_email = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("ss", $user_referral_code, $user_email);
                $update_stmt->execute();
                $update_stmt->close();
            }
            $_SESSION['user_referral_code'] = $user_referral_code;
        }
    }
}

$query = mysqli_query($connect, "SELECT * FROM digi_card WHERE user_email='$user_email' ORDER BY id DESC");

// Refund status for this user (controls conditional column)
$user_email_lower = strtolower(trim($user_email));
$refund_meta = mysqli_query($connect, "SELECT refund_status, refund_status_date FROM user_details WHERE LOWER(TRIM(email))='$user_email_lower' LIMIT 1");
$refund_status = 'None';
$refund_status_date = '';
if ($refund_meta && mysqli_num_rows($refund_meta) > 0) {
    $rm = mysqli_fetch_array($refund_meta);
    $refund_status = $rm['refund_status'] ?? 'None';
    $refund_status_date = $rm['refund_status_date'] ?? '';
}
$show_refund_status_col = ($refund_status !== 'None');

// Check if any cards were created by the user themselves (not by franchisees)
$show_invoice_column = false;
$temp_query = mysqli_query($connect, "SELECT COUNT(*) as self_created_count FROM digi_card WHERE user_email='$user_email' AND (f_user_email IS NULL OR f_user_email = '')");
if ($temp_query && mysqli_num_rows($temp_query) > 0) {
    $temp_result = mysqli_fetch_array($temp_query);
    $show_invoice_column = $temp_result['self_created_count'] > 0;
}

// Get MW Referral ID status from user_details table
$mw_referral_id = 0;
$mw_referral_query = mysqli_query($connect, "SELECT mw_referral_id FROM user_details WHERE LOWER(TRIM(email))='$user_email_lower' LIMIT 1");
if ($mw_referral_query && mysqli_num_rows($mw_referral_query) > 0) {
    $mw_referral_data = mysqli_fetch_array($mw_referral_query);
    $mw_referral_id = intval($mw_referral_data['mw_referral_id'] ?? 0);
}
?>

<main class="Dashboard">
    <div class="container-fluid  customer_content_area">
        <div class="main-top">
        <span class="heading"><?php echo $page_title; ?></span> 
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
                </ol>
            </nav>
        </div>
        
        <?php if ($show_welcome): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <p>Welcome! Your account is active. Create your Mini Website to get started.</p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <div class="CustomerDashboard-head">
                    <div class="row">
                        <div class="col-sm-3 top_section">
                            <a href="../website/business-name.php?new=1">
                                <div class="card">
                                    <div class="img">
                                        <img class="img-fluid" src="../../customer/assets/img/Edit-icon.png" alt="">
                                    </div>
                                    <div class="content">
                                        <p> Create New <br>Mini Website</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table id="ReferredUsers" class="display table" style="text-align: center;">
                        <thead class="bg-secondary">
                            <tr>
                                <th>MW ID</th>
                                <th>Company Name</th>
                                <th>Date Created</th>
                                <th>Validity Date</th>
                                <th style="text-align: left;">MW Status</th>
                                <th>View/Edit/Share</th>
                                <th style="text-align: left;">User Payment Status</th>
                                <?php if ($show_invoice_column): ?>
                                <th>Invoice</th>
                                <?php endif; ?>
                                <?php if ($show_refund_status_col): ?>
                                <th>Refund Status</th>
                                <?php endif; ?>
                                
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if(mysqli_num_rows($query) > 0) {
                                while($row = mysqli_fetch_array($query)) {
                                    // Use the validity_date field if available, otherwise calculate based on payment status
                                    if(!empty($row['validity_date'])) {
                                        $validity_date = date('d-m-Y', strtotime($row['validity_date']));
                                    } else {
                                        // Fallback for old records without validity_date
                                        if($row['d_payment_status'] == 'Success') {
                                            $validity_date = date('d-m-Y', strtotime($row['d_payment_date'] . ' +1 year'));
                                        } else {
                                            $validity_date = date('d-m-Y', strtotime($row['uploaded_date'] . ' +7 days'));
                                        }
                                    }
                                    $payment_status = $row['d_payment_status'];
                                     
                                    // Check if user has collaboration enabled
                                    if($row['complimentary_enabled'] == 'Yes') {
                                        $status_class = 'bg-success';
                                        $status_text = 'Active';
                                    } else if ($payment_status == 'Success') {
                                        // Paid: check validity_date for expiry
                                        $is_expired = (!empty($row['validity_date']) && $row['validity_date'] != '0000-00-00 00:00:00') ? (strtotime($row['validity_date']) < time()) : false;
                                        if ($is_expired) {
                                            $status_class = 'bg-secondary';
                                            $status_text = 'Expired <br/>on ' . date('d-m-Y', strtotime($row['validity_date']));
                                        } else {
                                            $status_class = 'bg-success';
                                            $status_text = 'Active';
                                        }
                                    } else {
                                        // Trial logic: show 7 Day Trial or Inactive after 7 days
                                        $trial_end = date('Y-m-d H:i:s', strtotime($row['uploaded_date'] . ' +7 days'));
                                        if (strtotime($trial_end) < time()) {
                                            $status_class = 'bg-secondary';
                                            $status_text = 'Inactive';
                                        } else {
                                            $status_class = 'bg-pending';
                                            $status_text = '7 Day Trial';
                                        }
                                    }
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td>
                                     <?php echo $row['d_comp_name']; ?>
                                </td>
                                <td><?php echo date('d-m-Y', strtotime($row['uploaded_date'])); ?></td>
                                <td><?php echo $validity_date; ?></td>
                                <td><span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                <td style="display:flex; align-items: center; gap: 5px;">
                                    <?php 
                                    // Check if user is akhilesh@yopmail.com for new flow, otherwise use old flow
                                    $edit_link = "../website/business-name.php?card_number=" . $row['id'];

                                    ?>
                                    <span class="view">                                <a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/n.php?n=<?php echo $row['card_id']; ?>" target="_blank" style="text-decoration: none; color: inherit;">
                                    <span class="view_icon_style"><i class="fa-regular fa-eye"></i></span>  
                                   
                                    </a></span>
                                    <span class="edit"><a href="<?php echo $edit_link; ?>"><span class="edit_icon_style"><i class="fa-solid fa-pen"></i></span></a></span>
                                    <span class="share">
                                        <a href="https://api.whatsapp.com/send?text=<?php echo urlencode('https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id']); ?>" target="_blank">
                                        <span class="share_icon_style"><i class="fa-solid fa-share-nodes"></i></span>
                                        </a></span>
                                </td>
                                <td>
                                    <?php if($row['complimentary_enabled'] == 'Yes') { ?>
                                        <span class="badge bg-info">Complimentary</span>
                                    <?php } else if($payment_status != 'Success') { ?>
                                        <button class="btn btn-primary hoverEffect" onclick="window.location.href='../../panel/login/payment_page/pay.php?id=<?php echo $row['id']; ?>&source=team'">Pay Now</button>
                                    <?php } else { 
                                        $paid_on = !empty($row['d_payment_date']) ? date('d-m-Y', strtotime($row['d_payment_date'])) : '';
                                        if ($paid_on) { ?>
                                            <span class="badge bg-success">Paid on <?php echo $paid_on; ?></span>
                                        <?php } else { ?>
                                            <span class="badge bg-success">Paid</span>
                                        <?php } 
                                    } ?>
                                </td>
                                <?php if ($show_invoice_column): ?>
                                <td style="text-align: left;">
                                    <?php 
                                    // Only show invoice options for cards created by the user themselves
                                    if(empty($row['f_user_email'])) {
                                        // Check if invoice details exist for this card
                                        $invoice_check_query = mysqli_query($connect, "SELECT COUNT(*) as invoice_count FROM invoice_details WHERE card_id = '" . mysqli_real_escape_string($connect, $row['id']) . "'");
                                        $invoice_check_result = mysqli_fetch_array($invoice_check_query);
                                        $has_invoices = $invoice_check_result['invoice_count'] > 0;
                                        
                                        if($payment_status == 'Success') { 
                                        ?>
                                           <?php if($has_invoices) { ?>
                                                <div class="d-flex  align-items-center">
                                                    <button class="btn btn-info btn-sm view_btn" onclick="viewInvoiceHistory(<?php echo $row['id']; ?>)" title="View Invoice History">
                                                          View
                                                    </button>
                                                </div>
                                            <?php } else { ?>
                                                 <div class="d-flex align-items-center">
                                                 <span class="download"><a target="_blank" href="download_invoice.php?id=<?php echo $row['id']; ?>" title="Download Invoice"><img src="../../customer/assets/img/download.png" alt=""></a></span>  </div>
                                             <?php } ?>
                                        <?php } else { ?>
                                            <div class="d-flex  align-items-center">
                                                <span class="download"  title="Payment required to download invoice"><img src="../../customer/assets/img/download.png" alt="" style="filter: grayscale(100%); opacity: 0.5;"></span>
                                                
                                            </div>
                                        <?php } 
                                    } else {
                                        // For franchisee-created cards, show hyphens
                                        echo '<span style="color: #6c757d; font-size: 18px;">-</span>';
                                    } ?>
                                </td>
                                <?php endif; ?>
                                <?php if ($show_refund_status_col): ?>
                                <td style="text-align: left;">
                                    <?php 
                                        if ($refund_status !== 'None') {
                                            $label = ($refund_status === 'Refund Settled') ? 'Refund Settled' : 'Refund Claimed';
                                            $date_text = '';
                                            if (!empty($refund_status_date) && $refund_status_date !== '0000-00-00 00:00:00') {
                                                $date_text = date('d-m-Y', strtotime($refund_status_date));
                                            }
                                            echo '<span class="badge '.($refund_status === 'Refund Settled' ? 'bg-success' : 'bg-warning').'">'.$label.($date_text ? ' <br/>on '.$date_text : '').'</span>';
                                        }
                                    ?>
                                </td>
                                <?php endif; ?>
                                
                            </tr>
                            <?php 
                                }
                            } else {
                            ?>
                            <tr>
                                <?php 
                                    $base_cols = $show_invoice_column ? 8 : 7; 
                                    if ($show_refund_status_col) { $base_cols += 1; }
                                ?>
                                <td colspan="<?php echo $base_cols; ?>" class="text-center">No mini websites found. Create your first one!</td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <br/><br/>
                
                        <div class="referral-id">Mini websites Referral ID</div>                
                                <div class="referral-container">                                
                                    <div class="referral-box col-md-6">
                                        <p>https://miniwebsite.in/panel/login/create-account.php?ref=<?php echo $user_referral_code; ?></p>
                                        <button class="copy-btn" onclick="copyToClipboard('regular_link')">COPY LINK</button>
                                    </div>
                                    <div class="referral-box col-md-6">
                                        <p><?php echo $user_referral_code; ?></p>
                                        <button class="copy-btn" onclick="copyToClipboard('regular_code')">COPY CODE</button>
                                    </div>
                                </div>

                                <div class="social-icons">
                                    <p>Refer Mini Website</p>
                                    <ul>
                                        <li><a href="https://api.whatsapp.com/send?text=<?php echo urlencode('Join using my referral link: https://miniwebsite.in/panel/login/create-account.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../customer/assets/img/whatsapp.png" alt=""></a></li>
                                        <li><a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://miniwebsite.in/panel/login/create-account.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../customer/assets/img/facebook.png" alt=""></a></li>
                                        <li><a href="https://www.instagram.com/share?url=<?php echo urlencode('https://miniwebsite.in/panel/login/create-account.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../customer/assets/img/instagram.png" alt=""></a></li>
                                        <li><a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Join using my referral link: https://miniwebsite.in/panel/login/create-account.php?ref='.$user_referral_code); ?>&url=<?php echo urlencode('https://miniwebsite.in/panel/login/create-account.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../customer/assets/img/twitter.png" alt=""></a></li>
                                        <li><a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode('https://miniwebsite.in/panel/login/create-account.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../customer/assets/img/linkedin.png" alt=""></a></li>
                                    </ul>
                                </div>
<hr/>

                                <div class="referral-id">Referral ID (For Franchisee)</div>
                                <div class="referral-container">
                                    <div class="referral-box col-md-6">
                                        <p>https://miniwebsite.in/panel/login/create-franchisee-account.php?ref=<?php echo $user_referral_code; ?></p>
                                        <button class="copy-btn" onclick="copyToClipboard('collab_link')">COPY LINK</button>
                                    </div>
                                    <div class="referral-box col-md-6">
                                        <p><?php echo $user_referral_code; ?></p>
                                        <button class="copy-btn" onclick="copyToClipboard('collab_code')">COPY CODE</button>
                                    </div>
                                </div>

                                <div class="social-icons">
                                    <p>Refer Franchise</p>
                                    <ul>
                                        <li><a href="https://api.whatsapp.com/send?text=<?php echo urlencode('Join using my collaboration link: https://miniwebsite.in/panel/login/create-franchisee-account.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../customer/assets/img/whatsapp.png" alt=""></a></li>
                                        <li><a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://miniwebsite.in/panel/login/create-franchisee-account.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../customer/assets/img/facebook.png" alt=""></a></li>
                                        <li><a href="https://www.instagram.com/share?url=<?php echo urlencode('https://miniwebsite.in/panel/login/create-franchisee-account.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../customer/assets/img/instagram.png" alt=""></a></li>
                                        <li><a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Join using my collaboration link: https://miniwebsite.in/panel/login/create-franchisee-account.php?ref='.$user_referral_code); ?>&url=<?php echo urlencode('https://miniwebsite.in/panel/login/create-franchisee-account.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../customer/assets/img/twitter.png" alt=""></a></li>
                                        <li><a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode('https://miniwebsite.in/panel/login/create-franchisee-account.php?ref='.$user_referral_code); ?>" target="_blank"><img src="../../customer/assets/img/linkedin.png" alt=""></a></li>
                                    </ul>
                                </div>
                        </div>
            
                                    
            
        </div>
    </div>
</main>

<!-- Invoice History Modal -->
<div class="modal fade" id="invoiceHistoryModal" tabindex="-1" aria-labelledby="invoiceHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title" id="invoiceHistoryModalLabel">Invoice History</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="invoiceHistoryContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewInvoiceHistory(cardId) {
    // Show loading
    document.getElementById('invoiceHistoryContent').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    // Show modal
    var modal = new bootstrap.Modal(document.getElementById('invoiceHistoryModal'));
    modal.show();
    
    // Fetch invoice history via AJAX
    fetch('../../customer/dashboard/view_invoice_history.php?card_id=' + cardId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('invoiceHistoryContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('invoiceHistoryContent').innerHTML = '<div class="alert alert-danger">Error loading invoice history: ' + error.message + '</div>';
        });
}
</script>

<style>
    #ReferredUsers thead tr th, #ReferredUsers tr td {
    padding-left: 30px !important;
}
.view_icon_style:hover, .share_icon_style:hover {
    background: #278de6ad;
    transition: 0.3s;
}
.hoverEffect:hover{
opacity: 0.7;
}
@media (max-width: 768px) {
    
    .Copyright-left,
.Copyright-right{
    padding:0px;
}}
</style>

<?php include '../footer.php'; ?>
