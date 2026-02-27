<?php
session_start();

require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../app/config/payment.php');

// Check if user is logged in
if (!isset($_SESSION['franchisee_email']) && !isset($_SESSION['user_email'])) {
    header('Location: ../user/login');
    exit;
}

$franchisee_email = $_SESSION['franchisee_email'] ?? $_SESSION['user_email'] ?? '';

// Initialize Razorpay SDK
require_once(__DIR__ . '/razorpay-php/Razorpay.php');
use Razorpay\Api\Api;

$keyId = 'rzp_live_xU57a1JhH7To1G';
$keySecret = 'VHJzQnCxqF5HTNsE3LUTZtnI';
$api = new Api($keyId, $keySecret);

// Get payment details from form
$razorpay_payment_id = isset($_POST['razorpay_payment_id']) ? $_POST['razorpay_payment_id'] : '';
$razorpay_order_id = isset($_POST['razorpay_order_id']) ? $_POST['razorpay_order_id'] : '';
$razorpay_signature = isset($_POST['razorpay_signature']) ? $_POST['razorpay_signature'] : '';

$success = false;
$error_message = '';

// Verify payment signature
if (empty($razorpay_payment_id) || empty($razorpay_order_id) || empty($razorpay_signature)) {
    $error_message = 'Payment verification failed: Missing payment details';
} else {
    try {
        // Verify the signature
        $attributes = [
            'razorpay_order_id' => $razorpay_order_id,
            'razorpay_payment_id' => $razorpay_payment_id,
            'razorpay_signature' => $razorpay_signature
        ];
        
        $api->utility->verifyPaymentSignature($attributes);
        
        // Signature verified, now fetch payment details
        $payment = $api->payment->fetch($razorpay_payment_id);
        
        if ($payment['status'] === 'captured') {
            $success = true;
            
            // Get recharge details from session
            $recharge_amount = $_SESSION['original_amount'] ?? 0;
            $final_amount = $_SESSION['final_total'] ?? 0;
            $igst = $_SESSION['igst_amount'] ?? 0;
            $reference_number = $_SESSION['reference_number'] ?? '';
            $user_email = $_SESSION['user_email'] ?? $franchisee_email;
            $user_name = $_SESSION['user_name'] ?? '';
            $user_contact = $_SESSION['user_contact'] ?? '';
            
            // Update wallet in database
            $wallet_query = "SELECT * FROM franchisee_wallet WHERE f_email='" . mysqli_real_escape_string($connect, $user_email) . "' LIMIT 1";
            $wallet_result = mysqli_query($connect, $wallet_query);
            
            if (mysqli_num_rows($wallet_result) > 0) {
                $wallet_row = mysqli_fetch_array($wallet_result);
                $current_balance = floatval($wallet_row['w_balance']);
                $new_balance = $current_balance + $recharge_amount;
                
                // Update wallet balance
                $update_wallet = "UPDATE franchisee_wallet SET 
                    w_balance = '" . $new_balance . "',
                    w_deposit = w_deposit + '" . $recharge_amount . "',
                    w_order_id = '" . mysqli_real_escape_string($connect, $razorpay_order_id) . "',
                    w_txn_msg = 'Wallet Recharge Payment',
                    uploaded_date = NOW()
                    WHERE f_email='" . mysqli_real_escape_string($connect, $user_email) . "'";
                
                if (mysqli_query($connect, $update_wallet)) {
                    // Insert transaction record
                    $insert_transaction = "INSERT INTO franchisee_wallet_transactions (
                        f_email,
                        w_deposit,
                        w_withdraw,
                        w_balance,
                        w_order_id,
                        w_txn_msg,
                        uploaded_date
                    ) VALUES (
                        '" . mysqli_real_escape_string($connect, $user_email) . "',
                        '" . $recharge_amount . "',
                        '0',
                        '" . $new_balance . "',
                        '" . mysqli_real_escape_string($connect, $razorpay_order_id) . "',
                        'Wallet Recharge - Payment ID: " . mysqli_real_escape_string($connect, $razorpay_payment_id) . "',
                        NOW()
                    )";
                    
                    mysqli_query($connect, $insert_transaction);
                    
                    // Create invoice record
                    $invoice_reference = 'WALLET-' . $razorpay_order_id;
                    $insert_invoice = "INSERT INTO invoice_details (
                        email,
                        reference_number,
                        invoice_date,
                        total_amount,
                        subtotal,
                        tax_amount,
                        tax_type,
                        description,
                        created_at
                    ) VALUES (
                        '" . mysqli_real_escape_string($connect, $user_email) . "',
                        '" . mysqli_real_escape_string($connect, $invoice_reference) . "',
                        NOW(),
                        '" . $final_amount . "',
                        '" . $recharge_amount . "',
                        '" . $igst . "',
                        'IGST',
                        'Wallet Recharge',
                        NOW()
                    )";
                    
                    mysqli_query($connect, $insert_invoice);
                    
                    // Clear session variables
                    unset($_SESSION['original_amount']);
                    unset($_SESSION['subtotal_amount']);
                    unset($_SESSION['cgst_amount']);
                    unset($_SESSION['sgst_amount']);
                    unset($_SESSION['igst_amount']);
                    unset($_SESSION['final_total']);
                    unset($_SESSION['recharge_amount']);
                    unset($_SESSION['reference_number']);
                    unset($_SESSION['razorpay_order_id']);
                    unset($_SESSION['service_type']);
                } else {
                    $success = false;
                    $error_message = 'Failed to update wallet. Please contact support.';
                }
            } else {
                $success = false;
                $error_message = 'Wallet not found for this account.';
            }
        } else {
            $error_message = 'Payment status: ' . $payment['status'];
        }
    } catch (Exception $e) {
        $error_message = 'Signature verification failed: ' . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' />
    <title><?php echo $success ? 'Payment Successful' : 'Payment Failed'; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            max-width: 500px;
            width: 100%;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }
        .error-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #dc3545;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }
        h2 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 24px;
        }
        p {
            margin: 10px 0;
            font-size: 16px;
            line-height: 1.6;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            margin-top: 20px;
            background: #002169;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .button:hover {
            background: #001a4d;
            transform: translateY(-2px);
        }
        .details {
            text-align: left;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid currentColor;
            opacity: 0.8;
            font-size: 14px;
        }
        .details div {
            margin: 8px 0;
            word-break: break-all;
        }
    </style>
</head>
<body>

<?php if ($success): ?>
    <div class="container success">
        <div class="success-icon">✓</div>
        <h2>Payment Successful!</h2>
        <p>Your wallet has been recharged successfully.</p>
        <p>Thank you for your payment. Your wallet balance has been updated.</p>
        <div class="details">
            <div><strong>Order ID:</strong> <?php echo htmlspecialchars($razorpay_order_id); ?></div>
            <div><strong>Payment ID:</strong> <?php echo htmlspecialchars($razorpay_payment_id); ?></div>
            <div><strong>Amount:</strong> ₹<?php echo number_format($recharge_amount, 2); ?></div>
            <div><strong>New Balance:</strong> ₹<?php echo number_format($new_balance, 2); ?></div>
        </div>
        <a href="../user/wallet" class="button">Go to Wallet</a>
    </div>
<?php else: ?>
    <div class="container error">
        <div class="error-icon">✕</div>
        <h2>Payment Failed</h2>
        <p><?php echo htmlspecialchars($error_message); ?></p>
        <p>Please try again or contact support if the issue persists.</p>
        <div class="details">
            <div><strong>Order ID:</strong> <?php echo htmlspecialchars($razorpay_order_id); ?></div>
            <div><strong>Payment ID:</strong> <?php echo htmlspecialchars($razorpay_payment_id); ?></div>
        </div>
        <a href="../user/wallet" class="button">Back to Wallet</a>
    </div>
<?php endif; ?>

</body>
</html>
