<?php
require('config.php');
require('razorpay-php/Razorpay.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

$success = true;
$error = "Payment Failed";

if (empty($_POST['razorpay_payment_id']) === false) {
    $api = new Api($keyId, $keySecret);

    try {
        // Verify the payment signature
        $attributes = array(
            'razorpay_order_id' => $_SESSION['razorpay_order_id'],
            'razorpay_payment_id' => $_POST['razorpay_payment_id'],
            'razorpay_signature' => $_POST['razorpay_signature']
        );

        $api->utility->verifyPaymentSignature($attributes);
    } catch(SignatureVerificationError $e) {
        $success = false;
        $error = 'Razorpay Error: ' . $e->getMessage();
    }
}

if ($success === true) {
    // Get current wallet balance
    $query = mysqli_query($connect, 'SELECT * FROM wallet WHERE f_user_email="' . $_SESSION['f_user_email'] . '" ORDER BY id DESC LIMIT 1');
    
    if (mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_array($query);
        $balance = $_SESSION['amount'] + $row['w_balance'];
    } else {
        $balance = $_SESSION['amount'];
    }
    
    // Insert transaction into wallet table
    $insert = mysqli_query($connect, 'INSERT INTO wallet (
        f_user_email,
        w_deposit,
        w_order_id,
        w_balance,
        w_txn_msg,
        uploaded_date
    ) VALUES (
        "' . $_SESSION['f_user_email'] . '",
        "' . $_SESSION['amount'] . '",
        "' . $_SESSION['reference_number'] . '",
        "' . $balance . '",
        "Payment ID: ' . $_POST['razorpay_payment_id'] . '",
        "' . date('Y-m-d H:i:s') . '"
    )');
    
    if ($insert) {
        ?>
        <div class="payment_confirmation">
            <h2>Payment Successful!</h2>
            <p>Amount Added: <?php echo $_SESSION['amount']; ?> Rs.</p>
            <p>New Balance: <?php echo $balance; ?> Rs.</p>
            <p>Transaction ID: <?php echo $_POST['razorpay_payment_id']; ?></p>
            <p>Order ID: <?php echo $_SESSION['reference_number']; ?></p>
            <a href="../../../franchisee/wallet/index.php?payment_success=1&txn_id=<?php echo $_POST['razorpay_payment_id']; ?>" class="btn1">Go to Wallet</a>
        </div>
        <?php
        // Redirect after 3 seconds to franchisee wallet
        echo '<meta http-equiv="refresh" content="3;URL=../../../franchisee/wallet/index.php?payment_success=1&txn_id=' . $_POST['razorpay_payment_id'] . '">';
    } else {
        ?>
        <div class="payment_error">
            <h2>Database Error</h2>
            <p>Your payment was successful, but we couldn't update your wallet.</p>
            <p>Please contact support with your payment ID: <?php echo $_POST['razorpay_payment_id']; ?></p>
            <a href="../../../franchisee/wallet/index.php?payment_error=1" class="btn1">Go to Wallet</a>
        </div>
        <?php
    }
} else {
    ?>
    <div class="payment_error">
        <h2>Payment Failed</h2>
        <p><?php echo $error; ?></p>
        <a href="../../../franchisee/wallet/index.php?payment_error=1" class="btn1">Try Again</a>
    </div>
    <?php
    echo '<meta http-equiv="refresh" content="5;URL=../../../franchisee/wallet/index.php?payment_error=1">';
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    background-color: #f5f5f5;
    margin: 0;
    padding: 0;
}

.payment_confirmation {
    border: 1px solid #4CAF50;
    width: 80%;
    max-width: 500px;
    padding: 30px;
    font-size: 16px;
    background: #e8f5e9;
    color: #2e7d32;
    font-family: sans-serif;
    margin: 50px auto;
    text-align: center;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.payment_error {
    border: 1px solid #f44336;
    width: 80%;
    max-width: 500px;
    padding: 30px;
    font-size: 16px;
    background: #ffebee;
    color: #c62828;
    font-family: sans-serif;
    margin: 50px auto;
    text-align: center;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

h2 {
    margin-top: 0;
    font-size: 24px;
}

.btn1 {
    display: inline-block;
    background: #ff5476;
    color: white;
    padding: 12px 25px;
    text-decoration: none;
    border-radius: 4px;
    margin-top: 20px;
    font-weight: bold;
    transition: background 0.3s;
}

.btn1:hover {
    background: #e91e63;
}
</style>
