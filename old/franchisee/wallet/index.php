<?php
// Include database connection
require('../../common/config.php');
require_once('../../common/verification_check.php');

// Check if franchisee is verified
$franchisee_email = $_SESSION['f_user_email'] ?? '';
$is_verified = isFranchiseeVerified($franchisee_email);

// Redirect to verification page if not verified
if(!$is_verified) {
    redirectToVerification();
}

// Handle wallet recharge redirect BEFORE including header to avoid "headers already sent" error
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['recharge_wallet'])) {
    $amount = floatval($_POST['recharge_amount']);
    
    if ($amount >= 500) {
        // Get franchisee email
        $franchisee_email = $_SESSION['f_user_email'] ?? '';
        
        // Get franchisee details (similar to original logic)
        $franchisee_query = mysqli_query($connect, 'SELECT * FROM franchisee_login WHERE f_user_email="' . $franchisee_email . '"');
        $franchisee_data = mysqli_fetch_array($franchisee_query);
        
        // Extract name parts (similar to original logic)
        $full_name = isset($franchisee_data['f_user_name']) ? $franchisee_data['f_user_name'] : '';
        $name_parts = explode(' ', $full_name, 2);
        $f_name = isset($name_parts[0]) ? $name_parts[0] : '';
        $l_name = isset($name_parts[1]) ? $name_parts[1] : '';
        $f_contact = isset($franchisee_data['f_user_contact']) ? $franchisee_data['f_user_contact'] : '';
        
        // Store in session (similar to original logic)
        $_SESSION['f_name'] = $f_name;
        $_SESSION['l_name'] = $l_name;
        $_SESSION['f_contact'] = $f_contact;
        
        // Redirect to payment page (similar to original logic)
        header("Location: payment-page/pay.php");
        exit();
    }
}

include '../header.php';

// Get franchisee email
$franchisee_email = $_SESSION['f_user_email'] ?? '';

// Get current wallet balance (similar to original logic)
$query_franchisee = mysqli_query($connect, 'SELECT * FROM wallet WHERE f_user_email="' . $franchisee_email . '" ORDER BY ID DESC LIMIT 1');
$row_franchisee = mysqli_fetch_array($query_franchisee);

// Get current balance safely (similar to original logic)
$current_balance = 0;
if($row_franchisee && isset($row_franchisee['w_balance'])) {
    $current_balance = (float)$row_franchisee['w_balance'];
}

// Handle wallet recharge redirect
$recharge_message = '';

// Show success message if payment was successful
if (isset($_GET['payment_success']) && $_GET['payment_success'] == '1') {
    $txn_id = $_GET['txn_id'] ?? '';
    $recharge_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa fa-check-circle"></i> Money added successfully! Your wallet has been recharged.
        ' . ($txn_id ? '<br><strong>Transaction ID:</strong> ' . $txn_id : '') . '
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

// Show error message if payment failed
if (isset($_GET['payment_error']) && $_GET['payment_error'] == '1') {
    $recharge_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa fa-exclamation-triangle"></i> Failed to add money! Please try again or contact support.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

// Show cancellation message if payment was cancelled
if (isset($_GET['payment_cancelled']) && $_GET['payment_cancelled'] == '1') {
    $recharge_message = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fa fa-times-circle"></i> Payment was cancelled. You can try again anytime.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

// Get wallet transaction history (similar to original logic)
$user_email = ($row_franchisee && isset($row_franchisee['f_user_email'])) ? $row_franchisee['f_user_email'] : $franchisee_email;
$transactions_query = mysqli_query($connect, 'SELECT * FROM wallet WHERE f_user_email="' . $user_email . '" ORDER BY ID DESC LIMIT 10');
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
        <div class="main-top">
        <span class="heading">Franchise Wallet</span>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a href="../dashboard/">Mini Website </a></li>
                  <li class="breadcrumb-item active" aria-current="page">Wallet</li>
                </ol>
            </nav>                              
        </div>
       
        <div class="card mb-4">
            <div class="card-body">
                <?php echo $recharge_message; ?>
                
                <div class="FranchiseeDashboard-head Wallet">
                    <div class="d-flex flex-wrap w-100 grid row-items-3">
                        <div class="card_area">
                            <div class="card">
                                <div class="img">
                                    <img class="img-fluid" style="height:auto" src="../../common/assets/img/wallet-bl.png" alt="">
                                </div>
                                <div class="content">
                                    <p>Wallet Balance</p>
                                    <p><i class="fa fa-inr" aria-hidden="true"></i> <?php echo number_format($current_balance, 2); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="card_area">
                            <div class="card">
                                <div class="img">
                                    <img class="img-fluid" style="height:auto" src="../../common/assets/img/TotalTransaction.png" alt="">
                                </div>
                                <div class="content">
                                    <p>Total Transactions</p>
                                    <p><?php echo mysqli_num_rows($transactions_query); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="card_area">
                            <div class="card RechargeWallet">
                                <p>Recharge Your Wallet</p>
                                <form method="POST" action="payment-page/pay.php" id=walletRechargeForm>
                                    <div class="recharge_input_section">
                                        <input type="number" name="recharge_amount" class="form-control recharge_amount" placeholder="Enter Recharge Amount" min="500" step="100" value="500" required>
                                        <!-- <small>Min Amount: ₹500/-</small> -->
                                    </div>
                                    <?php
                                    // Get franchisee details for hidden fields
                                    $franchisee_query = mysqli_query($connect, 'SELECT * FROM franchisee_login WHERE f_user_email="' . $franchisee_email . '"');
                                    $franchisee_data = mysqli_fetch_array($franchisee_query);
                                    
                                    // Extract name parts
                                    $full_name = isset($franchisee_data['f_user_name']) ? $franchisee_data['f_user_name'] : '';
                                    $name_parts = explode(' ', $full_name, 2);
                                    $f_name = isset($name_parts[0]) ? $name_parts[0] : '';
                                    $l_name = isset($name_parts[1]) ? $name_parts[1] : '';
                                    $f_contact = isset($franchisee_data['f_user_contact']) ? $franchisee_data['f_user_contact'] : '';
                                    ?>
                                    <input type="hidden" name="f_name" value="<?php echo htmlspecialchars($f_name); ?>">
                                    <input type="hidden" name="l_name" value="<?php echo htmlspecialchars($l_name); ?>">
                                    <input type="hidden" name="f_contact" value="<?php echo htmlspecialchars($f_contact); ?>">
                                    <button type="submit" name="add_money" class="btn btn-primary addMoney_btn">ADD MONEY</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                                <div class="ManageUsers">
                                    <h4 class="heading">Wallet Transactions: </h4>
                                    <div class="table-responsive">
                                        <table id="WalletTransactions" class="display table">
                                            <thead class="bg-secondary">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Time</th>
                                                    <th>Transaction ID</th>
                                                    <th>Deposit</th>
                                                    <th>Withdrawal</th>
                                                    <th>Invoice</th>
                                                    <th>Balance</th>
                                                    <th>Transaction Details</th>
                                                   
                                                </tr>
                                            </thead>
                                            <tbody>
                                                                                                 <?php
                                                 if(mysqli_num_rows($transactions_query) > 0) {
                                                     while($q_row = mysqli_fetch_array($transactions_query)) {
                                                         $date = date('d M y-h:s A', strtotime($q_row['uploaded_date']));
                                                         $balance = floatval($q_row['w_balance']);
                                                         $deposit = floatval($q_row['w_deposit']);
                                                         $withdrawal = floatval($q_row['w_withdraw']);
                                                         $order_id = $q_row['w_order_id'];
                                                         $txn_msg = $q_row['w_txn_msg'];
                                                 ?>
                                                <tr>
                                                     <td><?php echo date('d-m-Y', strtotime($q_row['uploaded_date'])); ?></td>
                                                     <td><?php echo date('h:i A', strtotime($q_row['uploaded_date'])); ?></td>
                                                     <td>
                                                         <span><?php echo $order_id; ?></span>
                                                     </td>
                                                     <td>
                                                         <?php if($deposit > 0): ?>
                                                             <span class="text-success">
                                                                 <i class="fa fa-inr" aria-hidden="true"></i> <?php echo number_format($deposit, 2); ?>
                                                             </span>
                                                         <?php else: ?>
                                                             <span class="text-muted">-</span>
                                                         <?php endif; ?>
                                                     </td>
                                                     <td>
                                                         <?php if($withdrawal > 0 || $withdrawal < 0): ?>
                                                             <span class="text-danger">
                                                                 <i class="fa fa-inr" aria-hidden="true"></i> <?php echo number_format($withdrawal, 2); ?>
                                                             </span>
                                                         <?php else: ?>
                                                             <span class="text-muted">-</span>
                                                         <?php endif; ?>
                                                     </td>
                                                     <td>
                                                         <?php 
                                                         $wallet_row_id = isset($q_row['id']) ? $q_row['id'] : '';
                                                         $invoice_ref = $wallet_row_id ? ('WALLET-' . $wallet_row_id) : ('WALLET-' . $order_id);                                                         
                                                         $inv_q = mysqli_query($connect, "SELECT id FROM invoice_details WHERE reference_number='" . mysqli_real_escape_string($connect, $invoice_ref) . "' LIMIT 1");
                                                         
                                                         if ($inv_q && mysqli_num_rows($inv_q) > 0) {
                                                             echo '<a href="../../payment_page/download_receipt.php?ref=' . htmlspecialchars($invoice_ref) . '" target="_blank" class="view_btn">View</a>';
                                                         } else {
                                                             
                                                             echo '<span class="text-muted">-</span>';
                                                         }
                                                         ?>
                                                     </td>
                                                     <td>
                                                         <strong><i class="fa fa-inr" aria-hidden="true"></i> <?php echo number_format($balance, 2); ?></strong>
                                                     </td>
                                                     <td>
                                                         <span><?php echo htmlspecialchars($txn_msg); ?></span>
                                                     </td>
                                                     
                                                 </tr>
                                                 <?php
                                                     }
                                                 } else {
                                                 ?>
                                                 <tr>
                                                     <td colspan="7" class="text-center">
                                                        Start by recharging your wallet to see transaction history.
                                                         
                                                     </td>
                                                 </tr>
                                                 <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </main>
                
                <script>
                function exportToCSV() {
                    // Get table data
                    const table = document.getElementById('WalletTransactions');
                    const rows = table.getElementsByTagName('tr');
                    let csv = [];
                    
                    for (let i = 0; i < rows.length; i++) {
                        const row = rows[i];
                        const cols = row.querySelectorAll('td, th');
                        let rowData = [];
                        
                        for (let j = 0; j < cols.length; j++) {
                            // Get text content without HTML tags
                            let text = cols[j].innerText || cols[j].textContent;
                            rowData.push('"' + text + '"');
                        }
                        
                        csv.push(rowData.join(','));
                    }
                    
                    // Download CSV file
                    const csvContent = csv.join('\n');
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', 'wallet_transactions.csv');
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
                
                // Auto-refresh wallet balance every 30 seconds
                setInterval(function() {
                    // You can add AJAX call here to refresh balance
                    console.log('Wallet balance auto-refresh enabled');
                }, 30000);
                
                // Custom alert close functionality
                document.addEventListener('DOMContentLoaded', function() {
                    // Add click event to all close buttons
                    const closeButtons = document.querySelectorAll('.btn-close');
                    closeButtons.forEach(function(button) {
                        button.addEventListener('click', function() {
                            const alert = this.closest('.alert');
                            if (alert) {
                                alert.style.transition = 'opacity 0.3s ease';
                                alert.style.opacity = '0';
                                setTimeout(function() {
                                    alert.remove();
                                }, 300);
                            }
                        });
                    });
                });
                </script>
                 <style>
                /* Custom alert close button styles */
                .alert-dismissible .btn-close {
                    position: absolute;
                    top: 0;
                    right: 0;
                    z-index: 2;
                    padding: 1.25rem 1rem;
                    background: transparent;
                    border: 0;
                    font-size: 1.25rem;
                    line-height: 1;
                    color: #000;
                    text-shadow: 0 1px 0 #fff;
                    opacity: .5;
                    cursor: pointer;
                }
                
                .alert-dismissible .btn-close:hover {
                    opacity: .75;
                }
                
                .alert-dismissible .btn-close::before {
                    content: "×";
                    font-weight: bold;
                }
                
                .alert {
                   
                    padding-right: 3rem;
                }
                
                .alert-success .btn-close {
                    color: #155724;
                }
                
                .alert-danger .btn-close {
                    color: #721c24;
                }
                
                .alert-warning .btn-close {
                    color: #856404;
                }
                .FranchiseeDashboard-head .card {
    justify-content: space-evenly ;
    gap: 10px;
    width: 18rem;
    height:14vh;
    
}
.FranchiseeDashboard-head .row-items-3{
    align-items:center;
}
.card .img{
    justify-content:flex-start ;
}
.FranchiseeDashboard-head.Wallet .RechargeWallet p{
    margin-bottom:0px;
    font-size:21px !important;
}
.recharge_amount{
    padding: 0px 10px;
    width: 8rem;
    height: 5vh;
}

#WalletTransactions thead tr th,
#WalletTransactions tbody tr td {
    padding: 14px 0px;
    
}
#WalletTransactions thead tr th:first-child, #WalletTransactions tr td:first-child {
    padding-left: 30px !important;
}
#WalletTransactions tbody tr:first-child td {
    padding-top: 20px;
    text-align:left;
}
#WalletTransactions td {
    padding: 10px 6px;
    text-align: left;
}
@media screen and (max-width: 768px) {
        .sb-topnav .navbar-brand img {
        max-height: 60px;
    }
    .FranchiseeDashboard-head .row-items-3 {
    justify-content: space-between;
    align-items: center;
}
.FranchiseeDashboard-head .card {
        width: 31rem !important;
    }
    .card-body {
    padding: 20px !important;
    padding-bottom: 100px !important;
}

.Copyright-left,
.Copyright-right{
    padding:0px;
}

.FranchiseeDashboard-head .card {
    
    padding: 10px 15px;
    font-weight: 600;
    margin: 30px auto;
}
 .FranchiseeDashboard-head .card {
    
    margin: 10px 0px !important;
    gap:3px;
}
.FranchiseeDashboard-head .card .img img {
        min-width: 53px;
        max-width: 50px;
    }
    .FranchiseeDashboard-head .card .content {
        
        padding-top: 0px;
    }
     .main-top {
        justify-content: flex-start;
        margin-left: 2px;
        padding: 20px 0px;
        padding-bottom: 0px;
    }
     .customer_content_area {
        padding: 0px 20px !important;
        margin-top: 33px;
    }
    .recharge_amount {
    padding: 0px 10px;
    width: 22rem;
    height: 5vh;
}
#WalletTransactions thead tr th,
#WalletTransactions tbody tr td {
        padding: 15px 18px !important;
        font-weight: 500 !important;
        font-size: 20px !important;
    }
    .ReferredUsers .heading, .ManageUsers .heading{
        font-size:22px;
    }
    }

    .FranchiseeDashboard-head .row-items-3{
        justify-content: space-evenly;
    }
.addMoney_btn{
    font-size:14px !important;
    padding: 11px;
    border: 1px solid yellow;
}
#WalletTransactions thead tr th ,
#WalletTransactions tbody tr td {
    padding: 15px 18px !important;
    font-weight: 500 !important;
    font-size: 16px !important;
}
#WalletTransactions .view_btn {
    color: #fff;
    background-color:#0dcaf0;
    text-decoration:none;
    padding: 4px 14px;
    border-radius: 5px;
}
#WalletTransactions .view_btn:hover {
    background-color: #17a2b8ad;
}
                </style>
                
                <?php include '../footer.php'; ?>