<?php
require_once(__DIR__ . '/../app/config/database.php');
require('header.php');

 

// Update referral status with payment details (partial payments allowed)
if(isset($_POST['update_referral'])) {
    $referral_id = mysqli_real_escape_string($connect, $_POST['referral_id']);
    $payment_amount = mysqli_real_escape_string($connect, $_POST['amount']);
    $transaction_number = mysqli_real_escape_string($connect, $_POST['transaction_number']);
    $payment_method = mysqli_real_escape_string($connect, $_POST['payment_method']);
    $payment_notes = mysqli_real_escape_string($connect, $_POST['payment_notes']);
    
    // Insert payment history
    $processed_by = isset($_SESSION['admin_email']) ? mysqli_real_escape_string($connect, $_SESSION['admin_email']) : 'admin';
    $insert_history = mysqli_query($connect, "INSERT INTO referral_payment_history 
        (referral_id, amount, transaction_number, payment_method, payment_notes, payment_date, processed_by) 
        VALUES 
        ('$referral_id', '$payment_amount', '$transaction_number', '$payment_method', '$payment_notes', NOW(), '$processed_by')");
    
    // Calculate total paid amount
    $total_paid_query = mysqli_query($connect, "SELECT SUM(amount) as total_paid FROM referral_payment_history WHERE referral_id='$referral_id'");
    $total_paid_data = mysqli_fetch_array($total_paid_query);
    $total_paid = $total_paid_data['total_paid'];
    
    // Get original referral amount
    $referral_query = mysqli_query($connect, "SELECT amount FROM referral_earnings WHERE id='$referral_id'");
    $referral_data = mysqli_fetch_array($referral_query);
    $original_amount = $referral_data['amount'];
    
    // Update status based on payment completion
    if($total_paid >= $original_amount) {
        $status = 'Paid';
    } else {
        $status = 'Partial';  // Changed from 'Partially Paid' to 'Partial'
    }
    
    // Update referral_earnings table
    $update = mysqli_query($connect, "UPDATE referral_earnings SET 
        status='$status',
        payment_date=".($status=='Paid' ? 'NOW()' : 'NULL')."
        WHERE id='$referral_id'");
    
    if($insert_history && $update) {
        echo '<div class="alert success">Payment of ₹'.$payment_amount.' processed successfully!</div>';
    }
}

// Update bank details
if(isset($_POST['update_bank_details'])) {
    $user_email = mysqli_real_escape_string($connect, $_POST['user_email']);
    $account_holder_name = mysqli_real_escape_string($connect, $_POST['account_holder_name']);
    $account_number = mysqli_real_escape_string($connect, $_POST['account_number']);
    $ifsc_code = mysqli_real_escape_string($connect, $_POST['ifsc_code']);
    $bank_name = mysqli_real_escape_string($connect, $_POST['bank_name']);
    $upi_id = mysqli_real_escape_string($connect, $_POST['upi_id']);
    $upi_name = mysqli_real_escape_string($connect, $_POST['upi_name']);
    
    // Check if bank details exist
    $check_query = mysqli_query($connect, "SELECT * FROM user_bank_details WHERE user_email='$user_email'");
    
    if(mysqli_num_rows($check_query) > 0) {
        // Update existing record
        $update_query = mysqli_query($connect, "UPDATE user_bank_details SET 
            account_holder_name='$account_holder_name',
            account_number='$account_number',
            ifsc_code='$ifsc_code',
            bank_name='$bank_name',
            upi_id='$upi_id',
            upi_name='$upi_name',
            updated_at=NOW()
            WHERE user_email='$user_email'");
    } else {
        // Insert new record
        $update_query = mysqli_query($connect, "INSERT INTO user_bank_details 
            (user_email, account_holder_name, account_number, ifsc_code, bank_name, upi_id, upi_name, created_at, updated_at) 
            VALUES 
            ('$user_email', '$account_holder_name', '$account_number', '$ifsc_code', '$bank_name', '$upi_id', '$upi_name', NOW(), NOW())");
    }
    
    if($update_query) {
        echo '<div class="alert success">Bank details updated successfully!</div>';
    } else {
        echo '<div class="alert error">Error updating bank details: ' . mysqli_error($connect) . '</div>';
    }
}
?>

<div class="main-content">
    <div class="page-header">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
        <h2><i class="fas fa-users me-3"></i>Manage Referrals</h2>
        <p>Referrals with payment and bank details</p>
    </div>

    <div class="table-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-table me-2"></i>
                Referrals Detail
            </div>
            <form method="GET" class="d-flex" style="gap:10px;">
                <input type="text" class="form-control" name="search" placeholder="Search referrer or referred email" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button class="btn btn-primary" type="submit">Search</button>
            </form>
        </div>
        <div class="table-responsive table-container">
            <table class="table table-striped table-hover modern-table" style="text-align: center;">
                <thead class="bg-secondary">
                    <tr>
                        <th>USER ID</th>
                        <th>User Email</th>
                        <th>User Name</th>
                        <th>User Number</th>
                        <th>Referred to</th>
                        <th>Referral Details</th>
                        <th>Referral Amt.</th>
                        <th>Refund</th>
                        <th>MW Payment Status</th>
                        <th>Bank Details</th>
                        <th>MW Payment Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Build search filter
                $ref_where = '';
                if(isset($_GET['search']) && $_GET['search']!=''){
                    $s = mysqli_real_escape_string($connect, $_GET['search']);
                    $ref_where = "WHERE (re.referrer_email LIKE '%$s%' OR re.referred_email LIKE '%$s%')";
                }

                // Join to latest card for referred user
                $details_sql = "SELECT 
                    re.*, 
                    r.id AS ref_user_id,
                    r.name AS ref_user_name,
                    r.phone AS ref_user_contact,
                    r.refund_status AS ref_user_refund_status,
                    u.id AS referred_user_id,
                    dcl.latest_card_id,
                    dc.d_payment_status,
                    dc.d_payment_date,
                    fl.id AS franchisee_id
                    FROM referral_earnings re
                    LEFT JOIN user_details r ON BINARY re.referrer_email = BINARY r.email AND r.role='CUSTOMER'
                    LEFT JOIN user_details u ON BINARY re.referred_email = BINARY u.email AND u.role='CUSTOMER'
                    LEFT JOIN (SELECT user_email, MAX(id) AS latest_card_id FROM digi_card GROUP BY user_email) dcl ON BINARY dcl.user_email = BINARY re.referred_email
                    LEFT JOIN digi_card dc ON dc.id = dcl.latest_card_id
                    LEFT JOIN user_details fl ON BINARY fl.email = BINARY re.referred_email AND fl.role='FRANCHISEE'
                    $ref_where
                    ORDER BY re.id DESC
                    LIMIT 300";
                $details_q = mysqli_query($connect, $details_sql);

                if($details_q && mysqli_num_rows($details_q) > 0){
                    while($row = mysqli_fetch_array($details_q)){
                        $userId = isset($row['ref_user_id']) ? str_pad(intval($row['ref_user_id']), 5, '0', STR_PAD_LEFT) : '-';
                        $userEmail = $row['referrer_email'] ?? '-';
                        $userName = $row['ref_user_name'] ?? '-';
                        $userNumber = $row['ref_user_contact'] ?? '-';

                        // Referred to string
                        if(($row['is_collaboration'] ?? 'NO') === 'YES'){
                            $referredTo = 'FR - ' . (isset($row['franchisee_id']) ? intval($row['franchisee_id']) : ($row['referred_email'] ?? '-'));
                        } else {
                            $referredTo = 'MW - ' . (isset($row['latest_card_id']) && $row['latest_card_id'] ? intval($row['latest_card_id']) : ($row['referred_email'] ?? '-'));
                        }

                        // Referral amount
                        $refAmt = '₹' . number_format((float)($row['amount'] ?? 0), 0);

                        // Refund status (referrer)
                        $refundStatus = $row['ref_user_refund_status'] ?? 'None';

                        // MW payment status (latest card) with formatted display
                        $paymentStatus = $row['d_payment_status'] ?? '';
                        $paymentDate = $row['d_payment_date'] ?? '';
                        
                        if($paymentStatus === 'Success' && !empty($paymentDate) && $paymentDate !== '0000-00-00 00:00:00') {
                            $formattedDate = date('d-m-Y', strtotime($paymentDate));
                            $mwPayStatus = '<span class="badge bg-success">Paid on ' . $formattedDate . '</span>';
                        } elseif($paymentStatus === 'Failed') {
                            $mwPayStatus = '<span class="badge bg-danger">Not Eligible</span>';
                        } elseif($paymentStatus === 'Created' || $paymentStatus === 'Pending' || empty($paymentStatus)) {
                            $mwPayStatus = '<span class="badge bg-warning">Pending</span>';
                        } else {
                            $mwPayStatus = '<span class="badge bg-secondary">' . htmlspecialchars($paymentStatus) . '</span>';
                        }

                        // Bank details button
                        $bankBtn = '<button onclick="showBankDetails(\'' . htmlspecialchars($userEmail) . '\')" class="btn btn-sm btn-outline-primary">View</button>';

                        // Invoice link if any invoice for latest card
                        $invoiceLink = '-';
                        if(!empty($row['latest_card_id'])){
                            $invCheck = mysqli_query($connect, "SELECT COUNT(*) as c FROM invoice_details WHERE card_id='".mysqli_real_escape_string($connect, $row['latest_card_id'])."'");
                            $hasInv = ($invCheck && ($c = mysqli_fetch_array($invCheck)) && intval($c['c']) > 0);
                            if($hasInv){
                                $invoiceLink = '<a href="invoice_admin_access.php?id='.intval($row['latest_card_id']).'" target="_blank" class="btn btn-sm btn-outline-secondary">Download</a>';
                            }
                        }

                        echo '<tr>';
                        echo '<td>'.$userId.'</td>';
                        echo '<td>'.htmlspecialchars($userEmail).'</td>';
                        echo '<td>'.htmlspecialchars($userName).'</td>';
                        echo '<td>'.htmlspecialchars($userNumber).'</td>';
                        echo '<td>'.htmlspecialchars($referredTo).'</td>';
                        echo '<td><button onclick="showReferralDetails(\''.$userEmail.'\')" class="btn btn-sm btn-outline-primary">View</button></td>';
                        echo '<td>'.$refAmt.'</td>';
                        echo '<td>'.htmlspecialchars($refundStatus).'</td>';
                        echo '<td>'.$mwPayStatus.'</td>';
                        echo '<td>'.$bankBtn.'</td>';
                        echo '<td>'.$invoiceLink.'</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="11" class="text-center py-4">No referral records found</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Removed older summary table; replaced with modern table above -->

<!-- Referral Details Modal -->
<div id="referralModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Referral Details</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="referralDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Bank Details Modal -->

<div id="bankModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Bank Details</h3>
            <span class="close" onclick="closeBankModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="bankDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Payment Form Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Process Referral Payment</h3>
            <span class="close" onclick="closePaymentModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" id="paymentForm">
                <input type="hidden" name="referral_id" id="referral_id">
                <input type="hidden" name="status" value="Paid">
                <input type="hidden" name="update_referral" value="1">
                
                <div class="form-group">
                    <label>Amount (₹):</label>
                    <input type="number" name="amount" id="payment_amount" required>
                </div>
                
                <div class="form-group">
                    <label>Transaction Number:</label>
                    <input type="text" name="transaction_number" placeholder="Enter transaction/reference number" required>
                </div>
                
                <div class="form-group">
                    <label>Payment Method:</label>
                    <select name="payment_method" required>
                        <option value="">Select Method</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="UPI">UPI</option>
                        <option value="Cash">Cash</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Notes (Optional):</label>
                    <textarea name="payment_notes" placeholder="Additional notes about payment"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-confirm">Process Payment</button>
                    <button type="button" onclick="closePaymentModal()" class="btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment History Modal -->
<div id="historyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Payment History</h3>
            <span class="close" onclick="closeHistoryModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="paymentHistoryContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Edit Bank Details Modal -->
<div id="editBankModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Bank Details</h3>
            <span class="close" onclick="closeEditBankModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" id="editBankForm">
                <input type="hidden" name="user_email" id="edit_user_email">
                <input type="hidden" name="update_bank_details" value="1">
                
                <div class="form-group">
                    <label>Account Holder Name:</label>
                    <input type="text" name="account_holder_name" id="edit_account_holder_name" required>
                </div>
                
                <div class="form-group">
                    <label>Account Number:</label>
                    <input type="text" name="account_number" id="edit_account_number" required>
                </div>
                
                <div class="form-group">
                    <label>IFSC Code:</label>
                    <input type="text" name="ifsc_code" id="edit_ifsc_code" required>
                </div>
                
                <div class="form-group">
                    <label>Bank Name:</label>
                    <input type="text" name="bank_name" id="edit_bank_name" required>
                </div>
                
                <div class="form-group">
                    <label>UPI ID:</label>
                    <input type="text" name="upi_id" id="edit_upi_id">
                </div>
                
                <div class="form-group">
                    <label>UPI Name:</label>
                    <input type="text" name="upi_name" id="edit_upi_name">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-confirm">Update Bank Details</button>
                    <button type="button" onclick="closeEditBankModal()" class="btn-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.container {
    max-width: 100%;
    padding: 20px;
}

.summary-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.summary-table thead {
    background: #4a90e2;
    color: white;
}

.summary-table th {
    padding: 15px;
    text-align: center;
    font-weight: bold;
    border-right: 1px solid rgba(255,255,255,0.2);
}

.summary-table th:last-child {
    border-right: none;
}

.summary-table td {
    padding: 15px;
    text-align: center;
    border-bottom: 1px solid #eee;
    border-right: 1px solid #f0f0f0;
    vertical-align: middle;
}

.summary-table td:last-child {
    border-right: none;
}

.summary-table tbody tr:hover {
    background: #f8f9fa;
}

.user-cell {
    text-align: left !important;
    min-width: 200px;
}

.amount-cell {
    font-weight: bold;
    color: #28a745;
    min-width: 120px;
}

.count-cell {
    font-weight: bold;
    color: #007bff;
    min-width: 100px;
}

.action-cell {
    min-width: 120px;
}

.btn-details {
    background: #17a2b8;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 12px;
    transition: background-color 0.2s;
}

.btn-details:hover {
    background: #138496;
}

.btn-edit {
    background: #ff6b35;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 12px;
    transition: background-color 0.2s;
}

.btn-edit:hover {
    background: #e55a2b;
}

.btn-history {
    background: #6c757d;
    color: white;
    border: none;
    padding: 4px 8px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 10px;
    margin-top: 2px;
}

.btn-history:hover {
    background: #5a6268;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 1000px;
    max-height: 80vh;
    overflow: hidden;
}

.modal-header {
    background: #4a90e2;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.close {
    color: white;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    opacity: 0.7;
}

.modal-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}

.details-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.details-table th {
    background: #f8f9fa;
    padding: 10px;
    text-align: center;
    border: 1px solid #ddd;
    font-size: 12px;
}

.details-table td {
    padding: 10px;
    text-align: center;
    border: 1px solid #ddd;
    font-size: 12px;
}

.status-pending {
    background: #ffc107;
    color: #212529;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 10px;
}

.status-paid {
    background: #28a745;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 10px;
}

.status-partial {
    background: #fd7e14;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 10px;
}

.btn-manual {
    background: #28a745;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 11px;
}

.btn-manual:hover {
    background: #218838;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    height: 60px;
    resize: vertical;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

.btn-confirm {
    background: #28a745;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
}

.btn-confirm:hover {
    background: #218838;
}

.btn-cancel {
    background: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
}

.btn-cancel:hover {
    background: #5a6268;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.history-table th,
.history-table td {
    padding: 8px;
    text-align: left;
    border: 1px solid #ddd;
    font-size: 12px;
}

.history-table th {
    background: #f8f9fa;
    font-weight: bold;
}
 

.details-table td:nth-child(5) {
    color: #28a745;
}

.details-table td:nth-child(6) {
    color: #dc3545;
}

/* MW Payment Status Badge Styles */
.badge {
    display: inline-block;
    padding: 0.25em 0.4em;
    font-size: 0.75em;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
}

.bg-success {
    background-color: #28a745 !important;
    color: white !important;
}

.bg-danger {
    background-color: #dc3545 !important;
    color: white !important;
}

.bg-warning {
    background-color: #ffc107 !important;
    color: #212529 !important;
}

.bg-secondary {
    background-color: #6c757d !important;
    color: white !important;
}
</style>

<script>
function showReferralDetails(referrerEmail) {
    // Show modal
    document.getElementById('referralModal').style.display = 'block';
    
    // Load referral details via AJAX
    fetch('get_referral_details.php?referrer_email=' + encodeURIComponent(referrerEmail))
        .then(response => response.text())
        .then(data => {
            document.getElementById('referralDetailsContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('referralDetailsContent').innerHTML = '<p>Error loading details</p>';
        });
}

function showBankDetails(referrerEmail) {
    // Show modal
    document.getElementById('bankModal').style.display = 'block';
    
    // Load bank details via AJAX
    fetch('get_bank_details.php?referrer_email=' + encodeURIComponent(referrerEmail))
        .then(response => response.text())
        .then(data => {
            document.getElementById('bankDetailsContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('bankDetailsContent').innerHTML = '<p>Error loading bank details</p>';
        });
}

function closeModal() {
    document.getElementById('referralModal').style.display = 'none';
}

function closeBankModal() {
    document.getElementById('bankModal').style.display = 'none';
}


function showPaymentForm(referralId, amount) {
    document.getElementById('referral_id').value = referralId;
    document.getElementById('payment_amount').value = amount;
    document.getElementById('paymentModal').style.display = 'block';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function showPaymentHistory(referralId) {
    document.getElementById('historyModal').style.display = 'block';
    
    // Load payment history via AJAX
    fetch('get_payment_history.php?referral_id=' + referralId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('paymentHistoryContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('paymentHistoryContent').innerHTML = '<p>Error loading payment history</p>';
        });
}

function closeHistoryModal() {
    document.getElementById('historyModal').style.display = 'none';
}

function editBankDetails(userEmail) {
    // Show edit modal
    document.getElementById('editBankModal').style.display = 'block';
    
    // Set user email
    document.getElementById('edit_user_email').value = userEmail;
    
    // Load current bank details via AJAX
    fetch('get_bank_details_for_edit.php?user_email=' + encodeURIComponent(userEmail))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_account_holder_name').value = data.bank_details.account_holder_name || '';
                document.getElementById('edit_account_number').value = data.bank_details.account_number || '';
                document.getElementById('edit_ifsc_code').value = data.bank_details.ifsc_code || '';
                document.getElementById('edit_bank_name').value = data.bank_details.bank_name || '';
                document.getElementById('edit_upi_id').value = data.bank_details.upi_id || '';
                document.getElementById('edit_upi_name').value = data.bank_details.upi_name || '';
            } else {
                alert('Error loading bank details: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading bank details');
        });
}

function closeEditBankModal() {
    document.getElementById('editBankModal').style.display = 'none';
}

function updateReferral(id) {
    // This function is now replaced by showPaymentForm
    showPaymentForm(id, 0);
}

// Inline edit functionality for bank details
function toggleEditMode(userEmail) {
    const editBtn = document.getElementById('editBtn');
    const displayTexts = document.querySelectorAll('.display-text');
    const editInputs = document.querySelectorAll('.edit-input');
    const form = document.getElementById('bankDetailsForm');
    const noBankDetails = document.querySelector('.no-bank-details');
    
    if (editBtn && editBtn.textContent.includes('Edit')) {
        // Switch to edit mode
        editBtn.innerHTML = '<i class="fas fa-save"></i> Update';
        editBtn.style.background = '#28a745';
        
        // Hide "no bank details" message if it exists
        if (noBankDetails) {
            noBankDetails.style.display = 'none';
        }
        
        // Show the form if it was hidden
        if (form) {
            form.style.display = 'block';
        }
        
        // Hide display text, show input fields
        displayTexts.forEach(text => text.style.display = 'none');
        editInputs.forEach(input => input.style.display = 'block');
        
    } else if (editBtn && editBtn.textContent.includes('Update')) {
        // Submit the form via AJAX
        if (form) {
            submitBankDetailsForm();
        }
    }
}

// Function to handle bank details form submission
function submitBankDetailsForm() {
    const form = document.getElementById('bankDetailsForm');
    if (!form) return;
    
    const formData = new FormData(form);
    
    fetch('update_bank_details_inline.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        console.log('Response:', data); // Debug log
        if (data.includes('success')) {
            // Success - switch back to display mode
            const editBtn = document.getElementById('editBtn');
            const displayTexts = document.querySelectorAll('.display-text');
            const editInputs = document.querySelectorAll('.edit-input');
            
            if (editBtn) {
                editBtn.innerHTML = '<i class="fas fa-edit"></i> Edit';
                editBtn.style.background = '#ff6b35';
            }
            
            // Update display text with new values
            editInputs.forEach((input, index) => {
                if (displayTexts[index]) {
                    displayTexts[index].textContent = input.value;
                }
            });
            
            // Hide input fields, show display text
            displayTexts.forEach(text => text.style.display = 'inline');
            editInputs.forEach(input => input.style.display = 'none');
            
            // Show success message
            alert('Bank details updated successfully!');
            
            // Reload the bank details to show updated data
            const userEmail = document.querySelector('input[name="user_email"]').value;
            showBankDetails(userEmail);
        } else {
            alert('Error updating bank details: ' + data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating bank details. Please try again.');
    });
}


// Close modal when clicking outside
window.onclick = function(event) {
    var modal = document.getElementById('referralModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>







