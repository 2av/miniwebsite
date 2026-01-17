<?php
// Disable deprecation notices
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

// Import Razorpay namespace at the top of the file
use Razorpay\Api\Api;

// Create a log file for debugging
$log_file = __DIR__ . '/payment-debug.log';
file_put_contents($log_file, "=== Payment Session Started: " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);

function log_step($message) {
    global $log_file;
    file_put_contents($log_file, date('H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

log_step("Starting payment process");

// Check if config.php exists
if (!file_exists(__DIR__ . '/config.php')) {
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
        <h2>Configuration Error</h2>
        <p>The payment configuration file is missing. Please contact the administrator.</p>
        <p><a href="../wallet.php" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Return to Wallet</a></p>
    </div>';
    exit;
}

// Check if Razorpay SDK exists
if (!file_exists(__DIR__ . '/razorpay-php/Razorpay.php')) {
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
        <h2>Payment SDK Error</h2>
        <p>The payment processing library is missing. Please contact the administrator.</p>
        <p><a href="../wallet.php" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Return to Wallet</a></p>
    </div>';
    exit;
}

try {
    require(__DIR__ . '/config.php');
    log_step("Loaded config.php");
} catch (Exception $e) {
    log_step("ERROR: Failed to load config.php: " . $e->getMessage());
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
        <h2>Configuration Error</h2>
        <p>There was an error loading the payment configuration. Please contact the administrator.</p>
        <p>Error: ' . $e->getMessage() . '</p>
        <p><a href="../wallet.php" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Return to Wallet</a></p>
    </div>';
    exit;
}

try {
    require(__DIR__ . '/razorpay-php/Razorpay.php');
    log_step("Loaded Razorpay.php");
} catch (Exception $e) {
    log_step("ERROR: Failed to load Razorpay.php: " . $e->getMessage());
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
        <h2>Payment SDK Error</h2>
        <p>There was an error loading the payment processing library. Please contact the administrator.</p>
        <p>Error: ' . $e->getMessage() . '</p>
        <p><a href="../wallet.php" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Return to Wallet</a></p>
    </div>';
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    log_step("Started session");
}

// Check if form was submitted from wallet page
if(isset($_POST['add_money']) && isset($_POST['deposit'])) {
    log_step("Form submitted with deposit amount: " . $_POST['deposit']);
    $deposit_amount = $_POST['deposit'];
    
    // Get user details from POST or fallback to session
    $f_name = isset($_POST['f_name']) ? $_POST['f_name'] : (isset($_SESSION['f_name']) ? $_SESSION['f_name'] : '');
    $l_name = isset($_POST['l_name']) ? $_POST['l_name'] : (isset($_SESSION['l_name']) ? $_SESSION['l_name'] : '');
    $f_contact = isset($_POST['f_contact']) ? $_POST['f_contact'] : (isset($_SESSION['f_contact']) ? $_SESSION['f_contact'] : '');
    
    // Store in session for later use
    $_SESSION['f_name'] = $f_name;
    $_SESSION['l_name'] = $l_name;
    $_SESSION['f_contact'] = $f_contact;
    
    //Validate amount
    if($deposit_amount < 500) {
        log_step("Invalid amount: " . $deposit_amount);
        echo '<div class="alert danger">Invalid amount. Minimum amount is 500 Rs.</div>';
        echo '<meta http-equiv="refresh" content="3;URL=../wallet.php">';
        exit;
    }
    
    // Set session variables for payment
    $_SESSION['amount'] = $deposit_amount;
    $_SESSION['reference_number'] = 'WAL'.rand(1000,9999).date('dmYHis');
    $_SESSION['user_name'] = $f_name . ' ' . $l_name;
    $_SESSION['user_contact'] = $f_contact;
    log_step("Set session variables for payment");
}

// If no amount is set, redirect back to wallet
if(!isset($_SESSION['amount'])) {
    log_step("No amount specified for payment");
    echo '<div class="alert danger">No amount specified for payment.</div>';
    echo '<meta http-equiv="refresh" content="3;URL=../wallet.php">';
    exit;
}
?>

<meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' />
<link rel="icon" href="images/favicon.png" type="image/png" />
<link rel="stylesheet" href="css.css">
<link rel="stylesheet" href="mobile_css.css">
<script src="master_js.js"></script>
<script src="js.js"></script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link href='https://fonts.googleapis.com/css?family=Aclonica' rel='stylesheet'>

<style>
/* Your existing styles */
.form_preview {
    color: #3c3b3b;
    margin: 68px auto;
    padding: 7px;
    width: 500px;
    border-radius: 7px;
    font-family: sans-serif;
    box-shadow: 0px 0px 7px 1px green;
    position: relative;
    text-transform: capitalize;
}

.verify_details {
    padding: 20px;
    background: #f9f9f9;
    border-radius: 5px;
}

.verify_details h1 {
    text-align: center;
    color: #333;
    margin-bottom: 20px;
}

.verify_details div {
    display: flex;
    background: #f0f0f0;
    padding: 12px;
    margin-bottom: 10px;
    border-radius: 4px;
}

.verify_details div p {
    margin: 0;
    padding: 0;
    flex: 1;
}

.verify_details div p:first-child {
    font-weight: bold;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
    text-align: center;
}

.danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.warning {
    color: #856404;
    background-color: #fff3cd;
    border-color: #ffeeba;
}

@media screen and (max-width:700px){
    .form_preview {
        width: auto;
        margin: 20px auto;

        box-shadow: 0px 0px 0px 0px green;
        width: -webkit-fill-available;
    }
}
</style>

<div class="clippath1"></div>

<div class="application_form" style="display:none">
    <div class="form_preview">
        <div class="verify_details">
            <h1><img src="favicon.png" width="50"> Add Money to Wallet</h1>
            
            <div><p><b>Order Id:</b></p><p><?php echo $_SESSION['reference_number']; ?></p></div>
            <div><p><b>Name:</b></p><p><?php echo $_SESSION['user_name']; ?></p></div>
            <div><p><b>Contact:</b></p><p><?php echo $_SESSION['user_contact']; ?></p></div>
            <div><p><b>Amount:</b></p><p><?php echo $_SESSION['amount']; ?> Rs</p></div>
        </div>

<?php
// Create the Razorpay Order
try {
    log_step("Creating Razorpay order");
    
    $api = new Api($keyId, $keySecret);
    log_step("Created Razorpay API instance");

    $payment_currency = 'INR';
    $_SESSION['payment_currency'] = $payment_currency;

    $orderData = [
        'receipt'         => $_SESSION['user_contact'].date('dhsi'),
        'amount'          => $_SESSION['amount'] * 100, // amount in paise
        'currency'        => 'INR',
        'payment_capture' => 1 // auto capture
    ];

    $razorpayOrder = $api->order->create($orderData);
    $razorpayOrderId = $razorpayOrder['id'];
    $_SESSION['razorpay_order_id'] = $razorpayOrderId;
    log_step("Created Razorpay order: " . $razorpayOrderId);

    $displayAmount = $orderData['amount'];
    
    if (isset($displayCurrency) && $displayCurrency !== 'INR') {
        $url = "https://api.fixer.io/latest?symbols=$displayCurrency&base=INR";
        $exchange = json_decode(file_get_contents($url), true);
    
        $displayAmount = $exchange['rates'][$displayCurrency] * $amount / 100;
    }
    
    $data = [
        "key"               => $keyId,
        "amount"            => $orderData['amount'],
        "name"              => "KIROVA SOLUTIONS LLP",
        "description"       => "Wallet Recharge",
        "image"             => "",
        "prefill"           => [
            "name"          => $_SESSION['user_name'],
            "email"         => $_SESSION['f_user_email'],
            "contact"       => $_SESSION['user_contact'],
        ],
        "notes"             => [
            "address"       => "NA",
            "merchant_order_id" => $_SESSION['reference_number'],
        ],
        "theme"             => [
            "color"         => "#ff5476"
        ],
        "order_id"          => $razorpayOrderId,
    ];
    
    $json = json_encode($data);
    log_step("Prepared payment data");
    
    ?>
    <form action="verify.php" method="POST" name="razorpayform">
        <script
            src="https://checkout.razorpay.com/v1/checkout.js"
            data-key="<?php echo $data['key']; ?>"
            data-amount="<?php echo $data['amount']; ?>"
            data-currency="INR"
            data-name="<?php echo $data['name']; ?>"
            data-image="<?php echo $data['image']; ?>"
            data-description="<?php echo $data['description']; ?>"
            data-prefill.name="<?php echo $data['prefill']['name']; ?>"
            data-prefill.email="<?php echo $data['prefill']['email']; ?>"
            data-prefill.contact="<?php echo $data['prefill']['contact']; ?>"
            data-order_id="<?php echo $data['order_id']; ?>"
            data-notes.shopping_order_id="<?php echo $data['notes']['merchant_order_id']; ?>"
            data-theme.color="<?php echo $data['theme']['color']; ?>">
        </script>
        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
        <input type="hidden" name="razorpay_signature" id="razorpay_signature">
        <input type="hidden" name="razorpay_order_id" value="<?php echo $razorpayOrderId; ?>">
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var options = <?php echo $json; ?>;
        options.handler = function (response) {
            document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
            document.getElementById('razorpay_signature').value = response.razorpay_signature;
            document.razorpayform.submit();
        };
        
        // Handle payment cancellation
        options.modal = {
            ondismiss: function() {
                // Redirect to franchisee wallet page when payment is cancelled
                window.location.href = '../../../franchisee/wallet/index.php?payment_cancelled=1';
            }
        };
        
        var rzp = new Razorpay(options);
        rzp.open();
        
        // Add a button to reopen payment if closed
        document.getElementById('rzp-button1').onclick = function(e){
            rzp.open();
            e.preventDefault();
        }
    });
    </script>
    
    <!-- Add a button to reopen payment if closed -->
     <?php
    
} catch (Exception $e) {
    log_step("ERROR: " . $e->getMessage());
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
        <h2>Payment Error</h2>
        <p>There was an error setting up the payment. Please try again later.</p>
        <p>Error: ' . $e->getMessage() . '</p>
        <p><a href="../wallet.php" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Return to Wallet</a></p>
    </div>';
}
?>
    </div>

