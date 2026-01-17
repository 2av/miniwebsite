<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require('connect.php');
require('header.php');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Franchisee Distributor Management</title>


<div class="container-fluid" style="padding:20px;">
    <div class="row">
        <div class="col-12">
            <div class="card" style="border:none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center" style="gap:10px;">
                            <button type="button" class="btn btn-outline-secondary" onclick="history.back()"><i class="fas fa-arrow-left"></i> Back</button>
                            <h4 class="mb-0">Franchisee Distributor</h4>
                        </div>
                        <form method="GET" class="d-flex" style="gap:10px;">
                            <input type="text" class="form-control" name="search" placeholder="Search name/email/number" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <button class="btn btn-primary" type="submit">Search</button>
                        </form>
                    </div>

                    <div class="table-card">
                        <div class="table-responsive table-container">
                            <table class="table table-striped table-hover modern-table" style="text-align: center;">
                                <thead class="bg-secondary">
                                    <tr>
                                        <th>USER ID</th>
                                        <th>User Email</th>
                                        <th>User Name</th>
                                        <th>User Number</th>
                                        <th>Joined On</th>
                                        <th>Referral Source</th>
                                        <th>Company Name</th>
                                        <th>FRD Status</th>
                                        <th>View/Edit/Share</th>
                                        <th>User Payment Status</th>
                                        <th>FRD Fee</th>
                                        <th>Invoice</th>
                                        <th>No. of MW</th>
                                        <th>Pending Amount</th>
                                        <th>Collaboration Details</th>
                                        <th>Deals for MW</th>
                                        <th>Deals for Franchisee</th>
                                        <th>Joining Deal</th>
                                        <th>Collaboration</th>
                                        <th>MW Referral ID</th>
                                        <th>Reset Password</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if(isset($_GET['page_no'])){
                                    } else { $_GET['page_no'] = '1'; }

                                    $limit = 15;
                                    $start_from = ((int)$_GET['page_no']-1)*$limit;

                                    $where_conditions = array("collaboration_enabled='YES'", "role='CUSTOMER'");
                                    if(isset($_GET['search']) && $_GET['search']!='') {
                                        $search = mysqli_real_escape_string($connect, $_GET['search']);
                                        $where_conditions[] = "(name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
                                    }
                                    $where_clause = 'WHERE '. implode(' AND ', $where_conditions);

                                    // Query user_details table for customers with collaboration enabled
                                    $query = mysqli_query($connect, "SELECT * FROM user_details $where_clause GROUP BY email ORDER BY id DESC LIMIT $start_from, $limit");

                                    if($query && mysqli_num_rows($query)>0){
                                        while($row = mysqli_fetch_array($query)){
                                            // Map user_details fields to old field names for compatibility
                                            $user_email = $row['email'] ?? '';
                                            $user_name = $row['name'] ?? '';
                                            $user_contact = $row['phone'] ?? '';
                                            
                                            // Miniwebsites count
                                            $cards_q = mysqli_query($connect, 'SELECT * FROM digi_card WHERE user_email="'.mysqli_real_escape_string($connect, $user_email).'"');
                                            $website_count = $cards_q ? mysqli_num_rows($cards_q) : 0;
                                            $first_card = ($cards_q && $website_count>0) ? mysqli_fetch_array(mysqli_query($connect, 'SELECT * FROM digi_card WHERE user_email="'.mysqli_real_escape_string($connect, $user_email).'" ORDER BY id DESC LIMIT 1')) : null;

                                            // Referral source (reuse logic from manage_user.php) - using user_details
                                            $ref_source_display = 'Direct';
                                            $ref_by = isset($row['referred_by']) ? trim($row['referred_by']) : '';
                                            if($ref_by !== ''){
                                                // Check in user_details for referrer
                                                $ref_user_q = mysqli_query($connect, "SELECT id, role FROM user_details WHERE email='".$ref_by."' LIMIT 1");
                                                if($ref_user_q && mysqli_num_rows($ref_user_q) > 0){
                                                    $ref_user = mysqli_fetch_array($ref_user_q);
                                                    $ref_id = intval($ref_user['id']);
                                                    $ref_role = strtoupper($ref_user['role'] ?? '');
                                                    
                                                    if ($ref_role === 'FRANCHISEE') {
                                                        $ref_source_display = 'FR - '.str_pad($ref_id, 3, '0', STR_PAD_LEFT);
                                                    } elseif ($ref_role === 'TEAM') {
                                                        $ref_source_display = 'Team - '.$ref_id;
                                                    } elseif ($ref_role === 'ADMIN') {
                                                        $ref_source_display = 'Admin - '.$ref_id;
                                                    } else {
                                                        $ref_source_display = 'User - '.$ref_id;
                                                    }
                                                    
                                                    $frd_q = mysqli_query($connect, "SELECT 1 FROM referral_earnings WHERE referrer_email COLLATE utf8mb3_general_ci='".$ref_by."' AND referred_email COLLATE utf8mb3_general_ci='".$user_email."' AND is_collaboration='YES' LIMIT 1");
                                                    if($frd_q && mysqli_num_rows($frd_q) > 0){ $ref_source_display .= ' (FRD)'; }
                                                }
                                            }

                                            // Company name from last/any card
                                            $company_name = ($first_card && !empty($first_card['d_comp_name'])) ? $first_card['d_comp_name'] : '-';

                                            // User payment status (from latest card)
                                            $user_payment_status = ($first_card && !empty($first_card['d_payment_status'])) ? $first_card['d_payment_status'] : '-';

                                            // FRD Fee & Invoice (reuse from manage_franchisee.php pattern)
                                            $ff = null; $invoiceId = null; $paymentBadge = '<span class="badge bg-secondary">Unpaid</span>';
                                            $franchise_fee_query = mysqli_query($connect, 'SELECT * FROM invoice_details WHERE user_email="'.mysqli_real_escape_string($connect, $user_email).'" AND service_name="Franchisee Registration" ORDER BY id DESC LIMIT 1');
                                            if ($franchise_fee_query && mysqli_num_rows($franchise_fee_query)>0) {
                                                $ff = mysqli_fetch_array($franchise_fee_query);
                                                $invoiceId = $ff['id'] ?? null;
                                                if (($ff['payment_status'] ?? '')==='Success') {
                                                    $paid_on = !empty($ff['invoice_date']) ? date('d-m-Y', strtotime($ff['invoice_date'])) : date('d-m-Y');
                                                    $paymentBadge = '<span class="badge bg-success">Paid on '.$paid_on.'</span>';
                                                }
                                            }
                                            
                                            // Check for joining deal invoice
                                            $joining_deal_invoice_query = mysqli_query($connect, 'SELECT * FROM invoice_details WHERE user_email="'.mysqli_real_escape_string($connect, $user_email).'" AND service_name="Joining Deal Payment" ORDER BY id DESC LIMIT 1');
                                            $joining_deal_invoice_id = null;
                                            if ($joining_deal_invoice_query && mysqli_num_rows($joining_deal_invoice_query)>0) {
                                                $joining_deal_invoice = mysqli_fetch_array($joining_deal_invoice_query);
                                                $joining_deal_invoice_id = $joining_deal_invoice['id'] ?? null;
                                            }

                                            // Pending referral amount for this user (as referrer)
                                            $ref_summary_q = mysqli_query($connect, "SELECT 
                                                COALESCE(SUM(re.amount), 0) AS total_referral_amount,
                                                COALESCE((
                                                    SELECT SUM(rph2.amount) 
                                                    FROM referral_payment_history rph2 
                                                    INNER JOIN referral_earnings re2 ON rph2.referral_id = re2.id 
                                                    WHERE re2.referrer_email COLLATE utf8mb3_general_ci = re.referrer_email COLLATE utf8mb3_general_ci
                                                ), 0) AS total_paid_amount
                                                FROM referral_earnings re 
                                                WHERE re.referrer_email COLLATE utf8mb3_general_ci = '".$user_email."'");
                                            $total_referral_amount = 0; $total_paid_amount = 0;
                                            if($ref_summary_q && mysqli_num_rows($ref_summary_q) > 0){
                                                $ref_summary = mysqli_fetch_array($ref_summary_q);
                                                $total_referral_amount = (float)($ref_summary['total_referral_amount'] ?? 0);
                                                $total_paid_amount = (float)($ref_summary['total_paid_amount'] ?? 0);
                                            }
                                            $pending_amount = max(0, $total_referral_amount - $total_paid_amount);

                                            echo '<tr>';
                                            echo '<td>'.intval($row['id']).'</td>';
                                            echo '<td>'.htmlspecialchars($user_email).'</td>';
                                            echo '<td>'.htmlspecialchars($user_name).'</td>';
                                            echo '<td>'.htmlspecialchars($user_contact).'</td>';
                                            $uploaded_date = $row['created_at'] ?? '';
                                            echo '<td><small class="text-muted">'.(!empty($uploaded_date) ? date('d-m-Y', strtotime($uploaded_date)) : '-').'</small></td>';
                                            echo '<td>'.htmlspecialchars($ref_source_display).'</td>';
                                            echo '<td>'.htmlspecialchars($company_name).'</td>';
                                            // FRD Status
                                            $collab_status = isset($row['collaboration_enabled']) ? $row['collaboration_enabled'] : 'NO';
                                            echo '<td>'.($collab_status==='YES' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>').'</td>';
                                            // View/Edit/Share (route to card management search by email)
                                            $action_url = 'manage_user_card.php?search='.urlencode($user_email);
                                            echo '<td class="actions-cell"><a class="btn btn-sm btn-outline-primary" href="'.$action_url.'" target="_blank">Open</a></td>';
                                            echo '<td>'.htmlspecialchars($user_payment_status).'</td>';
                                            echo '<td>'.($ff ? '₹'.number_format((float)($ff['total_amount'] ?? 0),2) : '₹0.00').'</td>';
                                            // Display invoice download icon - check both franchise registration and joining deal invoices
                                            $invoice_display = '-';
                                            if ($invoiceId) {
                                                $invoice_display = '<a href="invoice_admin_access.php?invoice_id='.$invoiceId.'" target="_blank" class="download-btn" title="Download Franchise Registration Invoice"><i class="fa fa-download"></i></a>';
                                            } elseif ($joining_deal_invoice_id) {
                                                $invoice_display = '<a href="invoice_admin_access.php?invoice_id='.$joining_deal_invoice_id.'" target="_blank" class="download-btn" title="Download Joining Deal Invoice"><i class="fa fa-download"></i></a>';
                                            }
                                            echo '<td class="invoice-cell">'.$invoice_display.'</td>';
                                            echo '<td>'.$website_count.'</td>';
                                            echo '<td>₹'.number_format($pending_amount, 0).'</td>';
                                            echo '<td><button type="button" class="btn btn-sm btn-outline-primary" onclick="showReferralDetails(\''.$user_email.'\')">View</button></td>';

                                            // Deal for MW
                                            // Check mapping existence
                                            $mapped_mw_deal_query = mysqli_query($connect, "SELECT dcm.*, d.deal_name FROM deal_customer_mapping dcm JOIN deals d ON dcm.deal_id = d.id WHERE dcm.customer_email='".$user_email."' AND d.plan_type='MiniWebsite' LIMIT 1");
                                            $has_mw_deal = $mapped_mw_deal_query && mysqli_num_rows($mapped_mw_deal_query) > 0;
                                            echo '<td>';
                                            if($has_mw_deal){
                                                $deal_row = mysqli_fetch_array($mapped_mw_deal_query);
                                                echo htmlspecialchars($deal_row['deal_name']);
                                            } else {
                                                echo '-';
                                            }
                                            echo '</td>';

                                            // Deal for Franchisee
                                            $mapped_fr_deal_query = mysqli_query($connect, "SELECT dcm.*, d.deal_name FROM deal_customer_mapping dcm JOIN deals d ON dcm.deal_id = d.id WHERE dcm.customer_email='".$user_email."' AND d.plan_type='Franchise' LIMIT 1");
                                            $has_fr_deal = $mapped_fr_deal_query && mysqli_num_rows($mapped_fr_deal_query) > 0;
                                            echo '<td>';
                                            if($has_fr_deal){
                                                $deal_row2 = mysqli_fetch_array($mapped_fr_deal_query);
                                                echo htmlspecialchars($deal_row2['deal_name']);
                                            } else {
                                                echo '-';
                                            }
                                            echo '</td>';

                                            // Joining deal manage - check if user has active joining deal
                                            // First check for pending upgrade deals
                                            $pending_upgrade_query = mysqli_query($connect, "SELECT ujdm.*, jd.deal_name, jd.deal_code, jd.total_fees 
                                                FROM user_joining_deals_mapping ujdm 
                                                JOIN joining_deals jd ON ujdm.joining_deal_id = jd.id 
                                                WHERE ujdm.user_email = '".$user_email."' 
                                                AND ujdm.mapping_status = 'ACTIVE' 
                                                AND ujdm.payment_status = 'PENDING'
                                                AND (ujdm.expiry_date IS NULL OR ujdm.expiry_date > NOW()) 
                                                ORDER BY ujdm.created_at DESC LIMIT 1");
                                            
                                            // Then check for active paid deals
                                            $active_deal_query = mysqli_query($connect, "SELECT ujdm.*, jd.deal_name, jd.deal_code, jd.total_fees 
                                                FROM user_joining_deals_mapping ujdm 
                                                JOIN joining_deals jd ON ujdm.joining_deal_id = jd.id 
                                                WHERE ujdm.user_email = '".$user_email."' 
                                                AND ujdm.mapping_status = 'ACTIVE' 
                                                AND ujdm.payment_status = 'PAID'
                                                AND (ujdm.expiry_date IS NULL OR ujdm.expiry_date > NOW()) 
                                                ORDER BY ujdm.created_at DESC LIMIT 1");
                                            
                                            echo '<td>';
                                            
                                            // Check if there's a pending upgrade
                                            if($pending_upgrade_query && mysqli_num_rows($pending_upgrade_query) > 0) {
                                                $pending_deal = mysqli_fetch_array($pending_upgrade_query);
                                                
                                                // Show pending upgrade deal
                                                $payment_url = 'https://www.miniwebsite.in/franchisee-distributer-agreement.php?email=' . urlencode($user_email);
                                                
                                                // Check if this is an upgrade (has notes about upgrade)
                                                $is_upgrade = strpos($pending_deal['notes'], 'Upgraded from') !== false;
                                                $amount_label = $is_upgrade ? 'Remaining Amount' : 'Amount';
                                                
                                                echo '<div style="font-size: 10px; display: flex; gap: 6px; flex-wrap: nowrap; flex-direction: column;">';
                                                echo '<div style="color: #ffc107; font-weight: bold;">🔄 UPGRADE PENDING</div>';
                                                echo '<div>' . htmlspecialchars($pending_deal['deal_name']) . '</div>';
                                                echo '<div><strong>' . $amount_label . ':</strong> ₹' . number_format($pending_deal['amount_paid'], 0) . '</div>';
                                                echo '<div><strong>Requested:</strong> ' . (!empty($pending_deal['created_at']) ? date('d-m-Y H:i', strtotime($pending_deal['created_at'])) : 'N/A') . '</div>';
                                                echo '<div><strong>Status:</strong> <span style="color: #dc3545;">Pending Payment</span></div>';
                                                echo '<a href="' . $payment_url . '" target="_blank" class="btn btn-sm btn-success" style="font-size: 10px; padding: 2px 6px;">Pay Now</a>';
                                                echo '</div>';
                                                
                                                // Also show current active deal below
                                                if($active_deal_query && mysqli_num_rows($active_deal_query) > 0) {
                                                    $active_deal = mysqli_fetch_array($active_deal_query);
                                                    $start_date_formatted = !empty($active_deal['start_date']) ? date('d-m-Y', strtotime($active_deal['start_date'])) : '-';
                                                    $expiry_date_formatted = !empty($active_deal['expiry_date']) ? date('d-m-Y', strtotime($active_deal['expiry_date'])) : '';
                                                    
                                                    echo '<div style="font-size: 9px; margin-top: 8px; padding-top: 8px; border-top: 1px solid #e9ecef; color: #6c757d;">';
                                                    echo '<div style="color: #28a745; font-weight: bold;">✓ CURRENT ACTIVE</div>';
                                                    echo '<div>' . htmlspecialchars($active_deal['deal_name']) . '</div>';
                                                    echo '<div><strong>Status:</strong> <span style="color: #28a745;">Paid</span></div>';
                                                    echo '<div><strong>Valid:</strong> ' . $start_date_formatted . ' to ' . $expiry_date_formatted . '</div>';
                                                    echo '</div>';
                                                }
                                                
                                            } else if($active_deal_query && mysqli_num_rows($active_deal_query) > 0) {
                                                // Show only active deal (no pending upgrade)
                                                $active_deal = mysqli_fetch_array($active_deal_query);
                                                $start_date_formatted = !empty($active_deal['start_date']) ? date('d-m-Y', strtotime($active_deal['start_date'])) : '-';
                                                $expiry_date_formatted = !empty($active_deal['expiry_date']) ? date('d-m-Y', strtotime($active_deal['expiry_date'])) : '-';
                                                
                                                echo '<div style="font-size: 10px; display: flex; gap: 6px; flex-wrap: nowrap; flex-direction: column;">';
                                                echo '<div>' . htmlspecialchars($active_deal['deal_name']) . '</div>';
                                                echo '<div><strong>Status:</strong> <span style="color: #28a745;">Paid</span></div>';
                                                echo '<div><strong>Start:</strong> ' . $start_date_formatted . '</div>';
                                                echo '<div><strong>Expire:</strong> ' . $expiry_date_formatted . '</div>';
                                                
                                                // Action buttons row
                                                echo '<div style="display: flex;gap: 4px;align-content: space-between;flex-direction: column;align-items: center;">';
                                                $startIso = !empty($active_deal['start_date']) ? date('Y-m-d', strtotime($active_deal['start_date'])) : '';
                                                $expiryIso = !empty($active_deal['expiry_date']) ? date('Y-m-d', strtotime($active_deal['expiry_date'])) : '';
                                                echo '<a href="#" title="Edit dates" onclick="openEditJoiningDates(' . intval($active_deal['id']) . ', \'' . $startIso . '\', \'' . $expiryIso . '\'); return false;" style="color:#0d6efd; text-decoration:none; font-size:12px;"><i class="fas fa-pen"></i></a>';
                                                echo '<button type="button" class="btn btn-sm btn-warning" onclick="openTopupModal(\''.$user_email.'\', \''.htmlspecialchars($user_name).'\', \''.$active_deal['deal_code'].'\', \''.$active_deal['deal_name'].'\')" style="font-size: 8px; padding: 1px 4px;">Topup</button>';
                                                echo '</div>';
                                                echo '</div>';
                                                
                                            } else {
                                                echo '<button type="button" class="btn btn-sm btn-info" onclick="showJoiningDealsModal(\''.$user_email.'\', \' '.htmlspecialchars($user_name).'\')">Manage</button>';
                                            }
                                            
                                            echo '</td>';

                                            // Collaboration toggle
                                            echo '<td class="collab-cell">';
                                            echo '<label class="switch">';
                                            echo '<input type="checkbox" class="collaboration-toggle" data-user-email="'.htmlspecialchars($user_email).'" '.($collab_status==='YES' ? 'checked' : '').'>';
                                            echo '<span class="slider"></span>';
                                            echo '</label>';
                                            echo '</td>';

                                            // MW Referral ID toggle
                                            $mw_referral_status = isset($row['mw_referral_id']) ? $row['mw_referral_id'] : 0;
                                            echo '<td class="mw-referral-cell">';
                                            echo '<label class="switch">';
                                            echo '<input type="checkbox" class="mw-referral-toggle" data-user-email="'.htmlspecialchars($user_email).'" '.($mw_referral_status ? 'checked' : '').'>';
                                            echo '<span class="slider"></span>';
                                            echo '</label>';
                                            echo '</td>';

                                            // Reset password
                                            echo '<td><a class="btn btn-sm btn-outline-danger" href="change-password.php?email='.urlencode($user_email).'">Reset</a></td>';

                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="21" class="text-center py-4">No collaboration-enabled users found</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="pagination-modern">
                    <?php 
                        // Query user_details table for customers with collaboration enabled
                        $cnt_q = mysqli_query($connect, "SELECT COUNT(DISTINCT email) as c FROM user_details WHERE collaboration_enabled='YES' AND role='CUSTOMER'" . (isset($search) && $search!='' ? " AND (name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')" : ''));
                        $total_rows = ($cnt_q && ($cr = mysqli_fetch_array($cnt_q))) ? (int)$cr['c'] : 0;
                        $pages = ($limit > 0) ? ceil($total_rows/$limit) : 1;
                        for($i=1;$i<=$pages;$i++){
                            $params = $_GET; $params['page_no'] = $i; $href='?'.http_build_query($params);
                            if($_GET['page_no']==$i){ echo '<a href="'.$href.'" class="page-btn-modern active">'.$i.'</a>'; } else { echo '<a href="'.$href.'" class="page-btn-modern">'.$i.'</a>'; }
                        }
                    ?>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Open referral details modal
function showReferralDetails(userEmail){
    fetch('get_collaboration_details.php?referrer_email=' + encodeURIComponent(userEmail))
        .then(resp => resp.text())
        .then(html => {
            const modal = document.getElementById('refDetailsModal');
            const body = document.getElementById('refDetailsBody');
            body.innerHTML = html;
            const scripts = Array.from(body.querySelectorAll('script'));
            scripts.forEach((oldScript) => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                if (!oldScript.src) { newScript.textContent = oldScript.textContent; }
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });
            modal.style.display = 'block';
        })
        .catch(() => alert('Failed to load referral details'));
}

// Collaboration toggle handler
document.addEventListener('change', function(e) {
    if (e.target && e.target.classList.contains('collaboration-toggle')) {
        const userEmail = e.target.getAttribute('data-user-email');
        const newStatus = e.target.checked ? 'YES' : 'NO';
        const toggleElement = e.target;
        
        // Show confirmation popup
        const action = newStatus === 'YES' ? 'enable' : 'disable';
        const confirmMessage = `Are you sure you want to ${action} collaboration for user: ${userEmail}?`;
        
        if (!confirm(confirmMessage)) {
            // User cancelled, revert the toggle
            toggleElement.checked = !toggleElement.checked;
            return;
        }
        
        fetch('js_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                user_email: userEmail,
                toggle_user_collaboration: 'YES',
                collaboration_status: newStatus
            })
        })
        .then(resp => resp.text())
        .then(text => { if (!text.includes('success')) { toggleElement.checked = !toggleElement.checked; } })
        .catch(() => { toggleElement.checked = !toggleElement.checked; });
    }
    if (e.target && e.target.classList.contains('mw-referral-toggle')) {
        const userEmail = e.target.getAttribute('data-user-email');
        const newStatus = e.target.checked ? 1 : 0;
        const toggleElement = e.target;
        
        // Show confirmation popup
        const action = newStatus === 1 ? 'enable' : 'disable';
        const confirmMessage = `Are you sure you want to ${action} MW Referral ID for user: ${userEmail}?`;
        
        if (!confirm(confirmMessage)) {
            // User cancelled, revert the toggle
            toggleElement.checked = !toggleElement.checked;
            return;
        }
        
        fetch('js_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                user_email: userEmail,
                toggle_mw_referral: 'YES',
                mw_referral_status: newStatus
            })
        })
        .then(resp => resp.text())
        .then(text => { if (!text.includes('success')) { toggleElement.checked = !toggleElement.checked; } })
        .catch(() => { toggleElement.checked = !toggleElement.checked; });
    }
});

// Open edit dates modal
function openEditJoiningDates(mappingId, startDate, expiryDate){
    document.getElementById('editMappingId').value = mappingId;
    document.getElementById('editStartDate').value = startDate || '';
    document.getElementById('editExpiryDate').value = expiryDate || '';
    document.getElementById('editJoiningDatesModal').style.display = 'block';
}

// Save edited dates
function saveJoiningDates(){
    const mappingId = document.getElementById('editMappingId').value;
    const startDate = document.getElementById('editStartDate').value;
    const expiryDate = document.getElementById('editExpiryDate').value;
    
    if(!startDate || !expiryDate){
        alert('Please select both start and expiry dates');
        return;
    }
    
    const formData = new FormData();
    formData.append('update_joining_deal_dates', '1');
    formData.append('mapping_id', mappingId);
    formData.append('start_date', startDate + ' 00:00:00');
    formData.append('expiry_date', expiryDate + ' 23:59:59');
    
    fetch('update_joining_deal_payment.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(t => {
            if(t.includes('success')){
                alert('Dates updated successfully');
                document.getElementById('editJoiningDatesModal').style.display = 'none';
                window.location.reload();
            } else {
                alert('Failed to update dates: ' + t);
            }
        })
        .catch(() => alert('Failed to update dates'));
}

// Topup modal handlers
function openTopupModal(userEmail, userName, currentDealCode, currentDealName) {
    document.getElementById('topupModal').style.display = 'block';
    document.getElementById('topupUserEmail').value = userEmail;
    document.getElementById('topupUserName').textContent = userName;
    document.getElementById('topupCurrentDeal').textContent = currentDealName;
    document.getElementById('topupCurrentDealCode').value = currentDealCode;
    
    // Load available upgrade deals
    loadUpgradeDeals(currentDealCode);
}

function loadUpgradeDeals(currentDealCode) {
    const container = document.getElementById('topupOptionsContainer');
    container.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading upgrade options...</div>';
    
    fetch('get_upgrade_deals.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'current_deal_code=' + encodeURIComponent(currentDealCode)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayUpgradeDeals(data.deals);
        } else {
            container.innerHTML = '<div class="alert alert-danger">Error loading upgrade options: ' + data.message + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = '<div class="alert alert-danger">Error loading upgrade options</div>';
    });
}

function displayUpgradeDeals(deals) {
    const container = document.getElementById('topupOptionsContainer');
    
    if (deals.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No upgrade options available for this deal.</div>';
        return;
    }
    
    let html = '';
    deals.forEach(deal => {
        const fees_display = deal.total_fees > 0 ? 
            `Fees: Rs ${parseInt(deal.fees).toLocaleString()} + ${parseInt(deal.gst_amount).toLocaleString()} (18% GST) = Rs ${parseInt(deal.total_fees).toLocaleString()}/-` : 
            'Fees: Rs 0/-';
        
        const benefits_display = `Benefits: Rs ${parseInt(deal.commission_amount).toLocaleString()}/- Commission`;
        
        // Build mapped deals display
        let mapped_deals_html = '';
        if(deal.mapped_deals && (deal.mapped_deals.mw || deal.mapped_deals.franchise)) {
            mapped_deals_html = '<div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e9ecef;">';
            mapped_deals_html += '<p style="margin: 0; font-size: 12px; color: #666; font-weight: 600;">Mapped Deals:</p>';
            mapped_deals_html += '<div style="margin-top: 4px;">';
            
            if(deal.mapped_deals.mw) {
                mapped_deals_html += `<span style="color: #28a745; font-size: 12px; background: #d4edda; padding: 2px 6px; border-radius: 4px; margin-right: 5px;">MW: ${deal.mapped_deals.mw.name}</span>`;
            }
            
            if(deal.mapped_deals.franchise) {
                mapped_deals_html += `<span style="color: #007bff; font-size: 12px; background: #cce7ff; padding: 2px 6px; border-radius: 4px;">Franchise: ${deal.mapped_deals.franchise.name}</span>`;
            }
            
            mapped_deals_html += '</div></div>';
        }
        
        html += `
            <div class="deal-option mb-4">
                <label class="deal-label">
                    <input type="radio" name="upgrade_deal" value="${deal.deal_code}" class="deal-radio">
                    <div class="deal-card" style="border: 2px solid #28a745;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="badge bg-success" style="font-size: 12px;">Tier ${deal.upgrade_order || 'N/A'}</span>
                                <h6 class="deal-title" style="color: #28a745;">${deal.deal_name}</h6>
                            </div>
                            <span style="background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">UPGRADE</span>
                        </div>
                        <p class="deal-fees">${fees_display}</p>
                        <p class="deal-benefits">${benefits_display}</p>
                        ${mapped_deals_html}
                    </div>
                </label>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function submitTopup() {
    const form = document.getElementById('topupForm');
    const formData = new FormData(form);
    const userEmail = document.getElementById('topupUserEmail').value;
    const selectedDeal = formData.get('upgrade_deal');
    const currentDealCode = document.getElementById('topupCurrentDealCode').value;
    
    if (!selectedDeal) { 
        alert('Please select an upgrade deal'); 
        return; 
    }
    
    if (!confirm('Upgrade user ' + userEmail + ' to the selected deal?')) { 
        return; 
    }
    
    const submitBtn = document.querySelector('button[onclick="submitTopup()"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Upgrading...';
    submitBtn.disabled = true;
    
    fetch('process_deal_upgrade_simple.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'user_email=' + encodeURIComponent(userEmail) + 
              '&current_deal_code=' + encodeURIComponent(currentDealCode) + 
              '&new_deal_code=' + encodeURIComponent(selectedDeal)
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        
        if (data.success) {
            alert('Deal upgraded successfully!');
            document.getElementById('topupModal').style.display = 'none';
            window.location.reload();
        } else {
            alert('Error upgrading deal: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        alert('Error upgrading deal');
    });
}

// Joining deals modal handlers (reuse from manage_user.php)
function showJoiningDealsModal(userEmail, userName) {
    document.getElementById('joiningDealsModal').style.display = 'block';
    document.getElementById('joiningDealsUserEmail').value = userEmail;
    document.getElementById('joiningDealsUserName').textContent = userName;
}
function submitJoiningDeal() {
    const form = document.getElementById('joiningDealsForm');
    const formData = new FormData(form);
    const userEmail = document.getElementById('joiningDealsUserEmail').value;
    const selectedDeal = formData.get('joining_deal');
    
    console.log('Submitting joining deal:', { userEmail, selectedDeal });
    
    if (!selectedDeal) { 
        alert('Please select a joining deal'); 
        return; 
    }
    
    if (!confirm('Send joining deal email to ' + userEmail + '?')) { 
        return; 
    }
    
    formData.append('user_email', userEmail);
    formData.append('send_joining_deal_email', 'YES');
    
    // Show loading state
    const submitBtn = document.querySelector('button[onclick="submitJoiningDeal()"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Sending...';
    submitBtn.disabled = true;
    
    fetch('send_joining_deal_email.php', { 
        method: 'POST', 
        body: formData 
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text();
    })
    .then(result => {
        console.log('Response result:', result);
        
        // Reset button
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        
        if (result.includes('success')) {
            alert('Joining deal email sent successfully!');
            document.getElementById('joiningDealsModal').style.display = 'none';
            form.reset();
            window.location.reload();
        } else { 
            alert('Failed to send email: ' + result); 
        }
    })
    .catch(error => { 
        console.error('Error:', error);
        
        // Reset button
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        
        alert('Error sending email: ' + error); 
    });
}

// Edit deal function
function editDeal(dealId) {
    // Load deal details and open edit modal
    const formData = new FormData();
    formData.append('get_deal_details', '1');
    formData.append('deal_id', dealId);
    
    fetch('update_joining_deal_payment.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data && !data.error) {
                // Populate edit modal with deal data
                document.getElementById('editDealId').value = dealId;
                document.getElementById('editDealName').value = data.deal_name || '';
                document.getElementById('editTotalFees').value = data.total_fees || '';
                document.getElementById('editCommissionAmount').value = data.commission_amount || '';
                document.getElementById('editDiscountAmount').value = data.discount_amount || '';
                document.getElementById('editBaseFees').value = data.fees || '';
                
                // Populate dropdowns with mapped deals
                document.getElementById('editMwDeal').value = data.mw_deal_id || '';
                document.getElementById('editFranchiseDeal').value = data.franchise_deal_id || '';
                
                // Show edit modal
                document.getElementById('editDealModal').style.display = 'block';
            } else {
                alert('Error loading deal details');
            }
        })
        .catch(error => {
            alert('Error: ' + error);
        });
}

// Save deal changes
function saveDealChanges() {
    const dealId = document.getElementById('editDealId').value;
    const dealName = document.getElementById('editDealName').value;
    const baseFees = document.getElementById('editBaseFees').value;
    const totalFees = document.getElementById('editTotalFees').value;
    const commissionAmount = document.getElementById('editCommissionAmount').value;
    const discountAmount = document.getElementById('editDiscountAmount').value;
    const mwDealId = document.getElementById('editMwDeal').value;
    const franchiseDealId = document.getElementById('editFranchiseDeal').value;
    
    if (!dealName || !baseFees || !totalFees || !commissionAmount) {
        alert('Please fill all required fields');
        return;
    }
    
    const formData = new FormData();
    formData.append('update_deal_details', '1');
    formData.append('deal_id', dealId);
    formData.append('deal_name', dealName);
    formData.append('fees', baseFees);
    formData.append('total_fees', totalFees);
    formData.append('commission_amount', commissionAmount);
    formData.append('discount_amount', discountAmount);
    formData.append('mw_deal_id', mwDealId);
    formData.append('franchise_deal_id', franchiseDealId);
    
    // Show loading
    const saveBtn = document.querySelector('button[onclick="saveDealChanges()"]');
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Saving...';
    saveBtn.disabled = true;
    
    fetch('update_joining_deal_payment.php', { method: 'POST', body: formData })
        .then(response => response.text())
        .then(result => {
            if (result.includes('success')) {
                alert('Deal updated successfully!');
                document.getElementById('editDealModal').style.display = 'none';
                window.location.reload();
            } else {
                alert('Error updating deal: ' + result);
            }
        })
        .catch(error => {
            alert('Error: ' + error);
        })
        .finally(() => {
            saveBtn.textContent = originalText;
            saveBtn.disabled = false;
        });
}
</script>

<!-- Edit Joining Dates Modal -->
<div class="modal" id="editJoiningDatesModal" style="display:none;">
    <div class="modal-content" style="max-width:420px;width:95%;margin:8% auto;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.25);overflow:hidden;">
        <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;background:#1a2b4c;color:#fff;padding:14px 18px;">
            <h3 style="margin:0;font-size:16px;">Edit Joining Deal Dates</h3>
            <span class="close" onclick="document.getElementById('editJoiningDatesModal').style.display='none'" style="cursor:pointer;font-size:22px;">&times;</span>
        </div>
        <div class="modal-body" style="padding:18px;">
            <form id="editJoiningDatesForm" onsubmit="return false;">
                <input type="hidden" id="editMappingId">
                <div class="mb-2">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Start Date</label>
                    <input type="date" id="editStartDate" class="form-control" style="width:100%;padding:8px 10px;border:1px solid #e0e0e0;border-radius:8px;">
                </div>
                <div class="mb-2">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Expiry Date</label>
                    <input type="date" id="editExpiryDate" class="form-control" style="width:100%;padding:8px 10px;border:1px solid #e0e0e0;border-radius:8px;">
                </div>
                <div class="text-end" style="margin-top:10px;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('editJoiningDatesModal').style.display='none'">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveJoiningDates()" style="margin-left:8px;">Save</button>
                </div>
            </form>
        </div>
    </div>
    
</div>

<!-- Joining Deals Modal -->
<div class="modal" id="joiningDealsModal" style="display:none;">
    <div class="modal-content" style="max-width:700px;width:95%;margin:3% auto;background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,0.3);overflow:hidden;">
        <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;background:linear-gradient(135deg,#1a2b4c,#2c3e50);color:#fff;padding:25px;position:relative;">
            <h3 style="margin:0;font-size:22px;font-weight:700;text-align:center;flex:1;letter-spacing:0.5px;">Select the Joining Deal</h3>
            <span class="close" onclick="document.getElementById('joiningDealsModal').style.display='none'" style="cursor:pointer;font-size:28px;font-weight:bold;position:absolute;right:25px;top:50%;transform:translateY(-50%);transition:all 0.3s ease;">&times;</span>
            <div style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);width:80px;height:4px;background:linear-gradient(90deg,#ffc107,#ff6b35);border-radius:2px;"></div>
        </div>
        <div class="modal-body" style="padding:35px;max-height:70vh;overflow-y:auto;">
            <div class="mb-4" style="display:none;">
                <p class="text-muted mb-0" style="font-size:14px;">User: <strong id="joiningDealsUserName" style="color:#333;"></strong></p>
                <input type="hidden" id="joiningDealsUserEmail">
            </div>
            <form id="joiningDealsForm">
                <div class="deal-options" id="dealOptionsContainer" style="margin-bottom:25px;">
                    <?php
                    $deals_query = mysqli_query($connect, "SELECT * FROM joining_deals WHERE is_active='YES' ORDER BY upgrade_order ASC");
                    if($deals_query && mysqli_num_rows($deals_query) > 0) {
                        while($deal = mysqli_fetch_array($deals_query)) {
                            $fees_display = '';
                            if($deal['total_fees'] > 0) {
                                if($deal['gst_amount'] > 0) {
                                    $fees_display = 'Fees: Rs ' . number_format($deal['fees'], 0) . ' + ' . number_format($deal['gst_amount'], 0) . ' (18% GST) = Rs ' . number_format($deal['total_fees'], 0) . '/-';
                                } else {
                                    $fees_display = 'Fees: Rs ' . number_format($deal['total_fees'], 0) . '/-';
                                }
                            } else { 
                                $fees_display = 'Fees: Rs 0/-'; 
                            }
                            $benefits_display = 'Benefits: Rs ' . number_format($deal['commission_amount'], 0) . '/- Commission';
                            if($deal['discount_amount'] > 0) { 
                                $benefits_display .= ' + Rs ' . number_format($deal['discount_amount'], 0) . '/- Discount to Customer'; 
                            }
                            
                            // Get mapped deals information
                            $mapped_deals_display = '';
                            if(!empty($deal['mw_deal_id']) && $deal['mw_deal_id'] > 0) {
                                $mw_deal_query = mysqli_query($connect, "SELECT deal_name FROM deals WHERE id = " . intval($deal['mw_deal_id']));
                                if($mw_deal_query && mysqli_num_rows($mw_deal_query) > 0) {
                                    $mw_deal = mysqli_fetch_array($mw_deal_query);
                                    $mapped_deals_display .= '<span style="color: #28a745; font-size: 12px; background: #d4edda; padding: 2px 6px; border-radius: 4px; margin-right: 5px;">MW: ' . htmlspecialchars($mw_deal['deal_name']) . '</span>';
                                }
                            }
                            
                            if(!empty($deal['franchise_deal_id']) && $deal['franchise_deal_id'] > 0) {
                                $franchise_deal_query = mysqli_query($connect, "SELECT deal_name FROM deals WHERE id = " . intval($deal['franchise_deal_id']));
                                if($franchise_deal_query && mysqli_num_rows($franchise_deal_query) > 0) {
                                    $franchise_deal = mysqli_fetch_array($franchise_deal_query);
                                    $mapped_deals_display .= '<span style="color: #007bff; font-size: 12px; background: #cce7ff; padding: 2px 6px; border-radius: 4px;">Franchise: ' . htmlspecialchars($franchise_deal['deal_name']) . '</span>';
                                }
                            }
                            
                            ?>
                            <div class="deal-option mb-4">
                                <label class="deal-label">
                                    <input type="radio" name="joining_deal" value="<?php echo htmlspecialchars($deal['deal_code']); ?>" class="deal-radio">
                                    <div class="deal-card">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <span class="badge bg-primary" style="font-size: 12px;">Tier <?php echo intval($deal['upgrade_order'] ?? 0); ?></span>
                                                <h6 class="deal-title"><?php echo htmlspecialchars($deal['deal_name']); ?></h6>
                                            </div>
                                            <i class="fas fa-edit edit-deal-icon" onclick="editDeal(<?php echo $deal['id']; ?>)" style="color: #007bff; cursor: pointer; font-size: 16px; margin-left: 10px;" title="Edit Deal"></i>
                                        </div>
                                        <p class="deal-fees"><?php echo $fees_display; ?></p>
                                        <p class="deal-benefits"><?php echo $benefits_display; ?></p>
                                        <?php if(!empty($mapped_deals_display)): ?>
                                        <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e9ecef;">
                                            <p style="margin: 0; font-size: 12px; color: #666; font-weight: 600;">Mapped Deals:</p>
                                            <div style="margin-top: 4px;">
                                                <?php echo $mapped_deals_display; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            </div>
                            <?php
                        }
                    } else { 
                        echo '<div class="alert alert-warning" style="border-radius:10px;border:none;background:#fff3cd;color:#856404;padding:15px;text-align:center;">No active joining deals found. Please contact administrator.</div>'; 
                    }
                    ?>
                </div>
                <div class="text-center mt-5">
                    <button type="button" class="btn btn-lg px-5" onclick="submitJoiningDeal()" style="background:linear-gradient(135deg,#ffc107,#ff8c00);border:none;color:#000;border-radius:30px;font-weight:700;padding:15px 40px;font-size:16px;letter-spacing:0.5px;text-transform:uppercase;box-shadow:0 4px 15px rgba(255,193,7,0.3);">Submit Selection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Deal Modal -->
<div class="modal" id="editDealModal" style="display:none;">
    <div class="modal-content" style="max-width:600px;width:95%;margin:5% auto;background:#fff;border-radius:15px;box-shadow:0 15px 35px rgba(0,0,0,0.3);overflow:hidden;">
        <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;background:linear-gradient(135deg,#007bff,#0056b3);color:#fff;padding:20px;">
            <h3 style="margin:0;font-size:18px;font-weight:600;"><i class="fas fa-edit"></i> Edit Deal Details</h3>
            <span class="close" onclick="document.getElementById('editDealModal').style.display='none'" style="cursor:pointer;font-size:24px;">&times;</span>
        </div>
        <div class="modal-body" style="padding:25px;">
            <form id="editDealForm" onsubmit="return false;">
                <input type="hidden" id="editDealId">
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label style="display:block;font-weight:600;margin-bottom:8px;">Deal Name</label>
                        <input type="text" id="editDealName" class="form-control" placeholder="Enter deal name" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label style="display:block;font-weight:600;margin-bottom:8px;">Base Fees (₹)</label>
                        <input type="number" id="editBaseFees" class="form-control" step="0.01" placeholder="Enter base fees" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                    </div>
                    <div class="col-md-6">
                        <label style="display:block;font-weight:600;margin-bottom:8px;">Total Fees (₹)</label>
                        <input type="number" id="editTotalFees" class="form-control" step="0.01" placeholder="Enter total fees" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label style="display:block;font-weight:600;margin-bottom:8px;">Commission Amount (₹)</label>
                        <input type="number" id="editCommissionAmount" class="form-control" step="0.01" placeholder="Enter commission amount" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                    </div>
                    <div class="col-md-6">
                        <label style="display:block;font-weight:600;margin-bottom:8px;">Discount Amount (₹)</label>
                        <input type="number" id="editDiscountAmount" class="form-control" step="0.01" placeholder="Enter discount amount" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label style="display:block;font-weight:600;margin-bottom:8px;">Deals for MW</label>
                        <select id="editMwDeal" class="form-control" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                            <option value="">Select MiniWebsite Deal</option>
                            <?php
                            $mw_deals_query = mysqli_query($connect, "SELECT * FROM deals WHERE deal_status='Active' AND plan_type='MiniWebsite' ORDER BY deal_name");
                            if($mw_deals_query && mysqli_num_rows($mw_deals_query) > 0){
                                while($deal = mysqli_fetch_array($mw_deals_query)){
                                    echo '<option value="'.intval($deal['id']).'">'.htmlspecialchars($deal['deal_name']).'</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label style="display:block;font-weight:600;margin-bottom:8px;">Deals for Franchisee</label>
                        <select id="editFranchiseDeal" class="form-control" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                            <option value="">Select Franchise Deal</option>
                            <?php
                            $franchise_deals_query = mysqli_query($connect, "SELECT * FROM deals WHERE deal_status='Active' AND plan_type='Franchise' ORDER BY deal_name");
                            if($franchise_deals_query && mysqli_num_rows($franchise_deals_query) > 0){
                                while($deal = mysqli_fetch_array($franchise_deals_query)){
                                    echo '<option value="'.intval($deal['id']).'">'.htmlspecialchars($deal['deal_name']).'</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="text-end" style="margin-top:20px;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('editDealModal').style.display='none'" style="margin-right:10px;">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveDealChanges()" style="background:#007bff;border:none;padding:10px 20px;border-radius:8px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Referral Details Modal -->
<div class="modal" id="refDetailsModal" style="display:none;">
    <div class="modal-content" style="max-width:1100px;width:95%;margin:3% auto;">
        <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;background:#4a90e2;color:#fff;padding:12px 16px;">
            <h3 style="margin:0;font-size:18px;">Referral Details</h3>
            <span class="close" onclick="document.getElementById('refDetailsModal').style.display='none'" style="cursor:pointer;font-size:22px;">&times;</span>
        </div>
        <div class="modal-body" id="refDetailsBody" style="padding:0;max-height:75vh;overflow:auto;"></div>
    </div>
</div>

<!-- Topup Modal -->
<div class="modal" id="topupModal" style="display:none;">
    <div class="modal-content" style="max-width:700px;width:95%;margin:3% auto;background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,0.3);overflow:hidden;">
        <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;background:linear-gradient(135deg,#ffc107,#ff8c00);color:#000;padding:25px;position:relative;">
            <h3 style="margin:0;font-size:22px;font-weight:700;text-align:center;flex:1;letter-spacing:0.5px;">Upgrade Joining Deal</h3>
            <span class="close" onclick="document.getElementById('topupModal').style.display='none'" style="cursor:pointer;font-size:28px;font-weight:bold;position:absolute;right:25px;top:50%;transform:translateY(-50%);transition:all 0.3s ease;">&times;</span>
            <div style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);width:80px;height:4px;background:linear-gradient(90deg,#ff6b35,#ffc107);border-radius:2px;"></div>
        </div>
        <div class="modal-body" style="padding:35px;max-height:70vh;overflow-y:auto;">
            <div class="mb-4">
                <p class="text-muted mb-0" style="font-size:14px;">User: <strong id="topupUserName" style="color:#333;"></strong></p>
                <p class="text-muted mb-0" style="font-size:14px;">Current Deal: <strong id="topupCurrentDeal" style="color:#ffc107;"></strong></p>
                <input type="hidden" id="topupUserEmail">
                <input type="hidden" id="topupCurrentDealCode">
            </div>
            <form id="topupForm">
                <div class="deal-options" id="topupOptionsContainer" style="margin-bottom:25px;">
                    <!-- Available upgrade deals will be loaded here -->
                </div>
                <div class="text-center mt-5">
                    <button type="button" class="btn btn-lg px-5" onclick="submitTopup()" style="background:linear-gradient(135deg,#28a745,#20c997);border:none;color:#fff;border-radius:30px;font-weight:700;padding:15px 40px;font-size:16px;letter-spacing:0.5px;text-transform:uppercase;box-shadow:0 4px 15px rgba(40,167,69,0.3);">Upgrade Deal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Joining Deals Modal Styles */
.deal-label {
    cursor: pointer;
    display: block;
    margin: 0;
    position: relative;
}

.deal-radio {
    display: none;
}

.deal-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 15px;
    transition: all 0.3s ease;
    background: #fff;
    position: relative;
    margin-left: 30px;
    margin-bottom: 10px;
}

.deal-radio:checked + .deal-card {
    border-color: #667eea;
    background: linear-gradient(135deg, #f8f9ff, #e8f0ff);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
}

.deal-card::before {
    content: '';
    position: absolute;
    left: -25px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    border: 2px solid #e9ecef;
    border-radius: 3px;
    background: #fff;
    transition: all 0.3s ease;
}

.deal-radio:checked + .deal-card::before {
    background: #667eea;
    border-color: #667eea;
}

.deal-radio:checked + .deal-card::after {
    content: '✓';
    position: absolute;
    left: -22px;
    top: 50%;
    transform: translateY(-50%);
    color: #fff;
    font-size: 12px;
    font-weight: bold;
}

.deal-title {
    color: #333;
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 16px;
}

.deal-fees {
    color: #666;
    font-weight: 500;
    margin-bottom: 5px;
    font-size: 14px;
}

.deal-benefits {
    color: #666;
    font-weight: 500;
    margin-bottom: 0;
    font-size: 14px;
}

/* Modal improvements */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}

.modal-content {
    position: relative;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.close {
    transition: all 0.3s ease;
}

.close:hover {
    transform: scale(1.1);
    color: #ffc107;
}

/* Deal option hover effects */
.deal-option:hover .deal-card {
    border-color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 2px 10px rgba(102, 126, 234, 0.1);
}

/* Submit button improvements */
.btn-lg {
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-lg:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 193, 7, 0.4);
}

.btn-lg:active {
    transform: translateY(0);
}

/* Joining Deal Info Styles */
.joining-deal-info {
    background: linear-gradient(135deg, #f8f9ff, #e8f0ff);
    border: 1px solid #667eea;
    border-radius: 8px;
    padding: 8px 12px;
    margin: 2px 0;
}

.joining-deal-info div {
    margin-bottom: 2px;
    color: #333;
}

.joining-deal-info div:last-child {
    margin-bottom: 0;
}

.joining-deal-info strong {
    color: #667eea;
    font-weight: 600;
}

/* Tier badge styles */
.badge.bg-primary {
    background: linear-gradient(135deg, #007bff, #0056b3) !important;
    font-weight: 600;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.badge.bg-success {
    background: linear-gradient(135deg, #28a745, #1e7e34) !important;
    font-weight: 600;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

/* Deal card enhancements */
.deal-card {
    transition: all 0.3s ease;
    border-radius: 8px;
}

.deal-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>

<?php include('footer.php'); ?>
</head>
</html>


