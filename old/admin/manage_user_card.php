<?php
require('connect.php');
require('header.php');
?>
<link rel="stylesheet" href="assets/css/common-admin.css">

<?php
// Handle deal mapping
if(isset($_POST['map_deal'])) {
    $user_email = mysqli_real_escape_string($connect, $_POST['user_email']);
    $deal_id = mysqli_real_escape_string($connect, $_POST['deal_id']);
    
    if(!empty($deal_id)) {
        $check_query = mysqli_query($connect, "SELECT * FROM deal_customer_mapping WHERE deal_id='$deal_id' AND customer_email='$user_email'");
        if(mysqli_num_rows($check_query) == 0) {
            $created_by = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : 'admin';
            $insert = mysqli_query($connect, "INSERT INTO deal_customer_mapping (deal_id, customer_email, created_by, created_date) VALUES ('$deal_id', '$user_email', '$created_by', NOW())");
            if($insert) {
                echo '<div class="alert success">Deal mapped successfully!</div>';
            }
        }
    }
}

// Handle deal removal
if(isset($_GET['remove_deal'])) {
    $mapping_id = mysqli_real_escape_string($connect, $_GET['remove_deal']);
    mysqli_query($connect, "DELETE FROM deal_customer_mapping WHERE id='$mapping_id'");
}

// Set filter variable BEFORE using it
if(isset($_GET['filter'])){
    if($_GET['filter_option']=='Payment Done'){$filter="Success";}
    else if($_GET['filter_option']=='Payment Not Done'){$filter="Created";}
    else if($_GET['filter_option']=='Trail Cards'){$filter="Created";}
    else {$filter="All";}
} else {
    $filter="All";
}

// Pagination setup
$page_no = isset($_GET['page_no']) ? (int)$_GET['page_no'] : 1;
if ($page_no < 1) { $page_no = 1; }
$limit = 10; // show top 10 records per page
$start_from = ($page_no - 1) * $limit;

// Get all active deals once (outside the loop)
$deals_query = mysqli_query($connect, "SELECT id, deal_name FROM deals WHERE deal_status='Active' ORDER BY deal_name");
$active_deals = [];
while($deal = mysqli_fetch_array($deals_query)) {
    $active_deals[] = $deal;
}

// Build search condition
$search_condition = "";
if(isset($_GET['search_item']) && !empty($_GET['search_item'])) {
    $search_term = mysqli_real_escape_string($connect, $_GET['search_item']);
    $search_condition = " AND (dc.d_comp_name LIKE '%$search_term%' OR dc.user_email LIKE '%$search_term%' OR dc.f_user_email LIKE '%$search_term%' OR dc.id LIKE '%$search_term%')";
}

// Main query with search functionality (using unified user_details)
$query = mysqli_query($connect, "
    SELECT 
        dc.*,
        dc.complimentary_enabled,
        u.id   AS user_id,
        u.name AS user_name,
        u.phone AS user_contact,
        u.collaboration_enabled,
        u.referred_by,
        dcm.id as mapping_id,
        d.deal_name,
        d.coupon_code,
        inv.billing_name,
        inv.total_amount
    FROM digi_card dc
    LEFT JOIN user_details u 
        ON BINARY dc.user_email = BINARY u.email
    LEFT JOIN deal_customer_mapping dcm 
        ON BINARY dc.user_email = BINARY dcm.customer_email
    LEFT JOIN deals d ON dcm.deal_id = d.id
    LEFT JOIN invoice_details inv ON dc.id = inv.card_id
    WHERE 
        CASE
            WHEN '$filter' = 'All' THEN dc.d_payment_status LIKE '%'
            ELSE dc.d_payment_status = '$filter'
        END
        AND dc.f_user_email = ''
        $search_condition
    ORDER BY dc.id DESC 
    LIMIT $start_from, $limit
");
?>

<div class="main-content">
    <div class="page-header">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
        <h2><i class="fas fa-globe me-3"></i>User's MiniWebsite Manager</h2>
        <p>Manage user websites and their status</p>
    </div>

    <!-- Filters Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="table-card">
                <div class="card-header">
                    <i class="fas fa-filter me-2"></i>
                    Filters & Search
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Filter</label>
                            <select name="filter_option" class="form-select">
                                <option value="">-Select-</option>
                                <option <?php echo (isset($_GET['filter_option']) && $_GET['filter_option']=='Payment Done') ? 'selected' : ''; ?>>Payment Done</option>
                                <option <?php echo (isset($_GET['filter_option']) && $_GET['filter_option']=='Payment Not Done') ? 'selected' : ''; ?>>Payment Not Done</option>
                                <option <?php echo (isset($_GET['filter_option']) && $_GET['filter_option']=='Trail Cards') ? 'selected' : ''; ?>>Trail Cards</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="search" name="search_item" class="form-control" placeholder="Search ID/Company/Franchisee/User" value="<?php echo isset($_GET['search_item']) ? htmlspecialchars($_GET['search_item']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="submit" name="filter" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i>Apply
                            </button>
                            <a href="manage_user_card.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                        
                        <?php if(isset($_GET['filter_option'])): ?>
                            <input type="hidden" name="filter_option" value="<?php echo htmlspecialchars($_GET['filter_option']); ?>">
                            <input type="hidden" name="filter" value="Submit">
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Websites Table -->
    <div class="table-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-table me-2"></i>
                User Websites Management
            </div>
        </div>
        <div class="table-responsive">
            <table class="user-management-table">
        <thead>
            <tr>
                <th>User ID</th>
                <th>MW ID</th>
                <th>User Email</th>
                <th>User Name</th>
                <th>User Number</th>
                <th>Referral Source</th>
                <th>Company Name</th>
                <th>Date Created</th>
                <th>Validity Date</th>
                <th>MW Status</th>
                <th>View/Edit/Share</th>
                <th>User Payment Status</th>
                <th>Total Order Value</th>
                <th>Invoice</th>
                <th>Complimentary</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if(mysqli_num_rows($query) > 0) {
                while($row = mysqli_fetch_array($query)) {
                    $has_deal = !empty($row['card_id']);
                    
                    echo '<tr>';
                    
                    // User ID (show '-' when no linked user_details record)
                    $uid = isset($row['user_id']) && $row['user_id'] !== '' ? (int)$row['user_id'] : '-';
                    echo '<td class="id-cell">'.$uid.'</td>';
                    
                    // MW ID
                    echo '<td class="id-cell">'.$row['id'].'</td>';
                    
                    // User Email
                    $user_email = $row['user_email'] ?? '';
                    echo '<td class="email-cell">'.substr($user_email, 0, 30).'</td>';
                    
                    // User Name
                    $user_name = $row['user_name'] ?? '';
                    echo '<td class="email-cell">'.substr($user_name, 0, 30).'</td>';
                    
                    // User Number (from unified table)
                    $user_contact = !empty($row['user_contact']) ? $row['user_contact'] : '-';
                    echo '<td class="email-cell">'.$user_contact.'</td>';
                    
                    // Referral Source (using referred_by from unified table)
                    $ref_source = !empty($row['referred_by']) ? $row['referred_by'] : 'Direct';
                    echo '<td class="email-cell">'.substr($ref_source, 0, 20).'</td>';

                    // Company Name
                    $billing_name = !empty($row['billing_name']) ? substr($row['billing_name'], 0, 25) : '-';
                    $comp_name = $billing_name != '-' ? $billing_name : ($row['d_comp_name'] ?? '');    
                    echo '<td class="company-cell">'.substr($comp_name, 0, 25).'</td>';
                    
                    // Date Created
                    $upload_date = !empty($row['uploaded_date']) && $row['uploaded_date'] != '0000-00-00 00:00:00' ? date('d-m-Y', strtotime($row['uploaded_date'])) : '-';
                    echo '<td class="date-cell">'.$upload_date.'</td>';
                    
                    // Determine MW Status first
                    $mw_status = 'Inactive';
                    $is_trial = false;
                    if ($row['complimentary_enabled'] == 'Yes') {
                        $mw_status = 'Active';
                    } else {
                        if ($row['d_payment_status'] == 'Success') {
                            $mw_status = 'Active';
                        } else {
                            // Trial / Inactive logic for unpaid cards
                            $uploaded_ts = !empty($row['uploaded_date']) && $row['uploaded_date'] != '0000-00-00 00:00:00'
                                ? strtotime($row['uploaded_date'])
                                : 0;
                            
                            if ($uploaded_ts > 0) {
                                $trial_end_ts = strtotime('+7 days', $uploaded_ts);
                                if ($trial_end_ts < time()) {
                                    // Trial over and no subscription taken
                                    $mw_status = 'Inactive';
                                } else {
                                    // Within 7‑day trial
                                    $mw_status = '7 Day Trial';
                                    $is_trial = true;
                                }
                            } else {
                                // Fallback if uploaded_date is missing
                                $mw_status = 'Inactive';
                            }
                        }
                    }
                    
                    // Validity Date - Calculate based on MW Status
                    $validity_date = '-';
                    $validity_class = '';
                    $is_expired = false;
                    
                    if ($mw_status == '7 Day Trial') {
                        // For 7-day trial, always show 7 days after creation date
                        if (!empty($row['uploaded_date']) && $row['uploaded_date'] != '0000-00-00 00:00:00') {
                            $validity_date = date('d-m-Y', strtotime($row['uploaded_date'] . ' +7 days'));
                            $is_expired = strtotime($row['uploaded_date'] . ' +7 days') < time();
                            $validity_class = $is_expired ? 'expired' : 'valid';
                        }
                    } elseif ($mw_status == 'Inactive') {
                        // For Inactive (expired trial), show the expired trial end date (7 days after creation)
                        if (!empty($row['uploaded_date']) && $row['uploaded_date'] != '0000-00-00 00:00:00') {
                            $validity_date = date('d-m-Y', strtotime($row['uploaded_date'] . ' +7 days'));
                            $is_expired = true; // Always expired for inactive cards
                            $validity_class = 'expired';
                        }
                    } elseif ($mw_status == 'Active') {
                        // For Active status (paid or complimentary)
                        if (!empty($row['validity_date']) && $row['validity_date'] != '0000-00-00 00:00:00') {
                            $validity_date = date('d-m-Y', strtotime($row['validity_date']));
                            $is_expired = strtotime($row['validity_date']) < time();
                            $validity_class = $is_expired ? 'expired' : 'valid';
                        } elseif (!empty($row['uploaded_date']) && $row['uploaded_date'] != '0000-00-00 00:00:00') {
                            if ($row['complimentary_enabled'] == 'Yes') {
                                $validity_date = date('d-m-Y', strtotime($row['uploaded_date'] . ' +1 year'));
                                $is_expired = strtotime($row['uploaded_date'] . ' +1 year') < time();
                                $validity_class = $is_expired ? 'expired' : 'valid';
                            } else {
                                if (!empty($row['d_payment_date']) && $row['d_payment_date'] != '0000-00-00 00:00:00') {
                                    $validity_date = date('d-m-Y', strtotime($row['d_payment_date'] . ' +1 year'));
                                    $is_expired = strtotime($row['d_payment_date'] . ' +1 year') < time();
                                    $validity_class = $is_expired ? 'expired' : 'valid';
                                } else {
                                    // Fallback: if somehow active but no payment date, use creation + 1 year
                                    $validity_date = date('d-m-Y', strtotime($row['uploaded_date'] . ' +1 year'));
                                    $is_expired = strtotime($row['uploaded_date'] . ' +1 year') < time();
                                    $validity_class = $is_expired ? 'expired' : 'valid';
                                }
                            }
                        }
                    }
                    
                    // Display Validity Date
                    if ($validity_date != '-') {
                        echo '<td class="date-cell '.$validity_class.'">'.$validity_date.'</td>';
                    } else {
                        echo '<td class="date-cell">-</td>';
                    }
                    
                    // Display MW Status
                    echo '<td class="status-cell">';
                    if ($mw_status == 'Active') {
                        echo '<span class="badge bg-success">Active</span>';
                    } elseif ($mw_status == '7 Day Trial') {
                        echo '<span class="badge bg-pending">7 Day Trial</span>';
                    } else {
                        echo '<span class="badge bg-secondary">Inactive</span>';
                    }
                    echo '</td>';
                    
                    // View/Edit/Share
                    echo '<td class="actions-cell">';
                    echo '<a href="https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id'].'" target="_blank" title="View" style="margin-right: 5px;"><i class="fa fa-eye"></i></a>';
                    echo '<a href="select_theme.php?card_number='.$row['id'].'&user_email='.$row['user_email'].'" title="Edit" style="margin-right: 5px;"><i class="fa fa-edit"></i></a>';
                    echo '<a href="#" onclick="shareCard(\''.$row['card_id'].'\')" title="Share"><i class="fa fa-share"></i></a>';
                    echo '</td>';
                    
                    // User Payment Status
                    echo '<td class="status-cell">';
                    if($row['d_payment_status'] == 'Success' && !empty($row['d_payment_date']) && $row['d_payment_date'] != '0000-00-00 00:00:00') {
                        $paid_on = date('d-m-Y', strtotime($row['d_payment_date']));
                        echo '<span class="badge bg-success">Paid on '.$paid_on.'</span>';
                    } else {
                        echo '<span class="badge bg-secondary">Unpaid</span>';
                    }
                    echo '</td>';
                    
                    // Total Order Value (show '-' if no order)
                    $totalAmount = isset($row['total_amount']) ? trim((string)$row['total_amount']) : '';
                    if ($totalAmount !== '' && $totalAmount !== '0' && $totalAmount !== '0.00') {
                        echo '<td class="date-cell">₹'.htmlspecialchars($totalAmount).'</td>';
                    } else {
                        echo '<td class="date-cell">-</td>';
                    }
                    
                    // Invoice Download (show '-' if no order placed)
                    echo '<td class="invoice-cell">';
                    $hasOrder = ($totalAmount !== '' && $totalAmount !== '0' && $totalAmount !== '0.00');
                    if(!$hasOrder) {
                        echo '-';
                    } else {
                        if($row['d_payment_status'] == 'Success') {
                            $invoice_check_query = mysqli_query($connect, "SELECT COUNT(*) as invoice_count FROM invoice_details WHERE card_id = '" . mysqli_real_escape_string($connect, $row['id']) . "'");
                            $invoice_check_result = mysqli_fetch_array($invoice_check_query);
                            $has_invoices = $invoice_check_result['invoice_count'] > 0;
                            
                            if($has_invoices) {
                                echo '<a href="invoice_admin_access.php?id='.$row['id'].'" target="_blank" title="Download Invoice" class="download-btn">';
                                echo '<i class="fa fa-download"></i>';
                                echo '</a>';
                            } else {
                                echo '<span title="No invoice available" style="color: #ccc;"><i class="fa fa-download"></i></span>';
                            }
                        } else {
                            echo '<span title="Payment required to download invoice" style="color: #ccc;"><i class="fa fa-download"></i></span>';
                        }
                    }
                    echo '</td>';
                    
                    // Complimentary Status
                    echo '<td class="complimentary-cell">';
                    $complimentary_status = isset($row['complimentary_enabled']) ? $row['complimentary_enabled'] : 'No';
                    $is_payment_success = ($row['d_payment_status'] == 'Success');

                    if ($is_payment_success) {
                        echo '<label class="switch" title="Complimentary disabled for paid cards">';
                        echo '<input type="checkbox" class="complimentary-toggle" data-card-id="'.$row['id'].'" disabled style="opacity: 0.5;">';
                        echo '<span class="slider" style="background-color: #ccc; cursor: not-allowed;"></span>';
                        echo '</label>';
                    } else {
                        echo '<label class="switch">';
                        echo '<input type="checkbox" class="complimentary-toggle" data-card-id="'.$row['id'].'" '.($complimentary_status == 'Yes' ? 'checked' : '').'>';
                        echo '<span class="slider"></span>';
                        echo '</label>';
                    }
                    echo '</td>';
                    
                    echo '</tr>';
                    
                }
            } else {
                echo '<tr><td colspan="13" class="no-data">No Data Available...</td></tr>';
            }
            ?>
        </tbody>
    </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="pagination-modern">
        <?php 
        // Build count query with same filters/search as main query
        $search_condition_pagination = "";
        if(isset($_GET['search_item']) && !empty($_GET['search_item'])) {
            $search_term = mysqli_real_escape_string($connect, $_GET['search_item']);
            $search_condition_pagination = " AND (d_comp_name LIKE '%$search_term%' OR user_email LIKE '%$search_term%' OR f_user_email LIKE '%$search_term%' OR id LIKE '%$search_term%')";
        }

        $countSql = "
            SELECT COUNT(*) AS total
            FROM digi_card 
            WHERE
                CASE
                    WHEN '$filter' = 'All' THEN d_payment_status LIKE '%'
                    ELSE d_payment_status = '$filter'
                END
                AND f_user_email = ''  
                $search_condition_pagination
        ";
        $countRes = mysqli_query($connect, $countSql);
        $rowCount = $countRes ? mysqli_fetch_assoc($countRes) : ['total' => 0];
        $pages = ($limit > 0) ? ceil(($rowCount['total'] ?? 0) / $limit) : 1;

        if ($pages > 1) {
            $current = isset($_GET['page_no']) ? max(1, (int)$_GET['page_no']) : 1;

            // Preserve existing query params (search/filter) when changing page
            $baseParams = $_GET;
            unset($baseParams['page_no']);

            $buildUrl = function($page) use ($baseParams) {
                $params = $baseParams;
                $params['page_no'] = $page;
                return '?'.http_build_query($params);
            };

            // First & Prev
            if ($current > 1) {
                echo '<a href="'.$buildUrl(1).'" class="page-btn-modern">&laquo;</a>';
                echo '<a href="'.$buildUrl($current-1).'" class="page-btn-modern">&lsaquo;</a>';
            }

            // Window of max 5 pages
            $window = 5;
            $start = max(1, $current - 2);
            $end   = min($pages, $start + $window - 1);
            $start = max(1, $end - $window + 1);

            for ($i = $start; $i <= $end; $i++) {
                $activeClass = ($i === $current) ? ' active' : '';
                echo '<a href="'.$buildUrl($i).'" class="page-btn-modern'.$activeClass.'">'.$i.'</a>';
            }

            // Next & Last
            if ($current < $pages) {
                echo '<a href="'.$buildUrl($current+1).'" class="page-btn-modern">&rsaquo;</a>';
                echo '<a href="'.$buildUrl($pages).'" class="page-btn-modern">&raquo;</a>';
            }
        }
        ?>
    </div>
</div>

<script>
							
							// if approved
								function activateUser(id){
										
										$('.idact'+id).css('color','blue').html('Wait...');
									
										$.ajax({
											url:'js_request.php',
											method:'POST',
											data:{card_id:id,activate_user:'YES'},
											dataType:'text',
											success:function(data){
												$('.idact'+id).html(data);
											}
											
										});
										
									}
									
							</script>
							
</div>

 <footer class="footer-area">
     <br /><br />
     <center>
           <br />
                    <a href="index.html" class="footer-logo">
                        						<img src="../panel/images/f_logo.png" alt="Vcard" width="auto" height="50px">
						                    </a>
                    <p>&copy; Copyright 2025 - All Rights Reserved. Crafted With <?php echo $_SERVER['HTTP_HOST']; ?> for Someone Special ! </p> 
					<p><a target="_blank" href="https://support.ajooba.io">Support Forum</a> | <a target="_blank" href="https://support.ajooba.io/faq">Faq's</a> | <a target="_blank" href="https://support.ajooba.io/articles/category/digital-vcard">Knowlege Base</a> </p>
			
        </center></footer>
<!-- Bootstrap badge styles for payment status -->
<style>
.badge {
    display: inline-block;
    padding: 0.35em 0.65em;
    font-size: 0.75em;
    font-weight: 700;
    line-height: 1;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.375rem;
}

.bg-info {
    background-color: #0dcaf0 !important;
}

.bg-warning {
    background-color: #ffc107 !important;
    color: #000 !important;
}

.bg-success {
    background-color: #198754 !important;
}

.bg-pending {
    background-color: #ffc107 !important;
    color: #000 !important;
}

.bg-secondary {
    background-color: #6c757d !important;
}
</style>

<script>
// Share card on WhatsApp using the same URL as View
function shareCard(cardId) {
    const shareUrl = window.location.origin + '/' + cardId;
    const whatsappWebUrl = 'https://wa.me/?text=' + encodeURIComponent(shareUrl);
    const whatsappAppUrl = 'whatsapp://send?text=' + encodeURIComponent(shareUrl);
    const isMobile = /Android|iPhone|iPad|iPod|Windows Phone/i.test(navigator.userAgent);
    if (isMobile) {
        // Try opening WhatsApp app on mobile
        window.location.href = whatsappAppUrl;
    } else {
        // Open WhatsApp Web on desktop
        window.open(whatsappWebUrl, '_blank');
    }
}

$(document).ready(function() {
    console.log('Document ready - looking for complimentary toggles');
    
    // Test if jQuery is working
    console.log('jQuery version:', $.fn.jquery);
    
    // Check if toggles exist
    console.log('Found toggles:', $('.complimentary-toggle').length);
    
    $(document).on('change', '.complimentary-toggle', function() {
        console.log('Toggle clicked!');
        
        // Check if the toggle is disabled (for paid cards)
        if ($(this).is(':disabled')) {
            console.log('Toggle is disabled - cannot change complimentary status for paid cards');
            return false;
        }
        
        const cardId = $(this).data('card-id');
        const isEnabled = $(this).is(':checked') ? 'Yes' : 'No';
        const toggleElement = $(this);
        
        console.log('Card ID:', cardId, 'Status:', isEnabled);
        
        $.ajax({
            url: 'toggle_complimentary.php',
            method: 'POST',
            data: {
                card_id: cardId,
                complimentary_status: isEnabled
            },
            success: function(data) {
                console.log('Response:', data);
                if(data.includes('success')) {
                    console.log('Complimentary status updated to: ' + isEnabled);
                   
                } else {
                    console.log('Error: ' + data);
                    toggleElement.prop('checked', !toggleElement.is(':checked'));
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error: ' + error);
                toggleElement.prop('checked', !toggleElement.is(':checked'));
            }
        });
    });

    // Card Status Toggle Handler
    $(document).on('change', '.card-status-toggle', function() {
        console.log('Card status toggle clicked!');
        
        const cardId = $(this).data('card-id');
        const newStatus = $(this).is(':checked') ? 'Active' : 'Inactive';
        const toggleElement = $(this);
        
        console.log('Card ID:', cardId, 'New Status:', newStatus);
        
        $.ajax({
            url: 'toggle_card_status.php',
            method: 'POST',
            data: {
                card_id: cardId,
                card_status: newStatus
            },
            success: function(data) {
                console.log('Card status response:', data);
                if(data.includes('success')) {
                    console.log('Card status updated to: ' + newStatus);
                } else {
                    console.log('Error updating card status: ' + data);
                    // Revert toggle on error
                    toggleElement.prop('checked', !toggleElement.is(':checked'));
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error: ' + error);
                // Revert toggle on error
                toggleElement.prop('checked', !toggleElement.is(':checked'));
            }
        });
    });

    // Collaboration Toggle Handler
    $(document).on('change', '.collaboration-toggle', function() {
        console.log('Collaboration toggle clicked!');
        
        const userEmail = $(this).data('user-email');
        const newStatus = $(this).is(':checked') ? 'YES' : 'NO';
        const toggleElement = $(this);
        
        console.log('User Email:', userEmail, 'New Status:', newStatus);
        
        $.ajax({
            url: 'js_request.php',
            method: 'POST',
            data: {
                user_email: userEmail,
                toggle_user_collaboration: 'YES',
                collaboration_status: newStatus
            },
            success: function(data) {
                console.log('Collaboration response:', data);
                if(data.includes('success')) {
                    console.log('Collaboration status updated to: ' + newStatus);
                } else {
                    console.log('Error updating collaboration status: ' + data);
                    // Revert toggle on error
                    toggleElement.prop('checked', !toggleElement.is(':checked'));
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error: ' + error);
                // Revert toggle on error
                toggleElement.prop('checked', !toggleElement.is(':checked'));
            }
        });
    });
});
</script>
