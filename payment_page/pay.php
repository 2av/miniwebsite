<?php
// Include centralized configs
require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../app/config/payment.php');
 

// Process form data if coming from franchise_agreement.php
if ($_POST) {
    $_SESSION['gst_number'] = $_POST['gst_number'] ?? '';
    $_SESSION['user_name'] = $_POST['name'] ?? '';
    $_SESSION['user_email'] = $_POST['email'] ?? '';
    $_SESSION['user_contact'] = $_POST['contact'] ?? '';
    $_SESSION['address'] = $_POST['address'] ?? '';
    $_SESSION['state'] = $_POST['state'] ?? '';
    $_SESSION['city'] = $_POST['city'] ?? '';
    $_SESSION['pincode'] = $_POST['pincode'] ?? '';
    $_SESSION['amount'] = $_POST['amount'] ?? 35400;
    $_SESSION['original_amount'] = $_POST['original_amount'] ?? 30000;
    $_SESSION['discount_amount'] = $_POST['discount_amount'] ?? 0;
    $_SESSION['subtotal_amount'] = $_POST['subtotal_amount'] ?? 30000;
    $_SESSION['cgst_amount'] = $_POST['cgst_amount'] ?? 2700;
    $_SESSION['sgst_amount'] = $_POST['sgst_amount'] ?? 2700;
    $_SESSION['igst_amount'] = $_POST['igst_amount'] ?? 0;
    $_SESSION['final_total'] = $_POST['final_total'] ?? 35400;
    $_SESSION['promo_code'] = $_POST['promo_code'] ?? '';
    $_SESSION['promo_discount'] = $_POST['promo_discount'] ?? 0;
    $_SESSION['service_type'] = $_POST['service_type'] ?? 'franchise_registration';
    $_SESSION['reference_number'] = 'FRAN'.rand(1000,9999).date('dmYHis');
    
    // Store franchise registration data
    $_SESSION['franchise_registration_data'] = array(
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'password' => $_POST['password'] ?? '123456',
        'contact' => $_POST['contact'],
        'address' => $_POST['address'],
        'state' => $_POST['state'],
        'city' => $_POST['city'],
        'pincode' => $_POST['pincode'],
        'gst_number' => $_POST['gst_number'] ?? '',
        'referral_code' => $_POST['referral_code'] ?? '',
        'referred_by' => $_POST['referred_by'] ?? ''
    );
}

// Ensure we have required session data
if (!isset($_SESSION['reference_number'])) {
    $_SESSION['reference_number'] = 'FRAN'.rand(1000,9999).date('dmYHis');
}

// Check if Razorpay SDK exists - use existing one from panel/login/payment_page
$razorpay_path = __DIR__ . '/../panel/login/payment_page/razorpay-php/Razorpay.php';

if (!file_exists($razorpay_path)) {
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
        <h2>Payment SDK Error</h2>
        <p>The payment processing library is missing. Please contact the administrator.</p>
        <p><a href="../franchise_agreement.php" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Go Back</a></p>
    </div>';
    exit;
}

require_once($razorpay_path);
use Razorpay\Api\Api;

// Use test credentials for development
$test_mode = false; // Set to false for production
 
    $keyId = 'rzp_live_xU57a1JhH7To1G';
    $keySecret = 'VHJzQnCxqF5HTNsE3LUTZtnI';
 

// Process form data
if ($_POST) {
    $_SESSION['gst_number'] = $_POST['gst_number'] ?? '';
    $_SESSION['user_name'] = $_POST['name'] ?? '';
    $_SESSION['user_email'] = $_POST['email'] ?? '';
    $_SESSION['user_contact'] = $_POST['contact'] ?? '';
    $_SESSION['address'] = $_POST['address'] ?? '';
    $_SESSION['state'] = $_POST['state'] ?? '';
    $_SESSION['city'] = $_POST['city'] ?? '';
    $_SESSION['pincode'] = $_POST['pincode'] ?? '';
    $_SESSION['amount'] = $_POST['amount'] ?? 35400;
    $_SESSION['original_amount'] = $_POST['original_amount'] ?? 30000;
    $_SESSION['discount_amount'] = $_POST['discount_amount'] ?? 0;
    $_SESSION['subtotal_amount'] = $_POST['subtotal_amount'] ?? 30000;
    $_SESSION['cgst_amount'] = $_POST['cgst_amount'] ?? 2700;
    $_SESSION['sgst_amount'] = $_POST['sgst_amount'] ?? 2700;
    $_SESSION['igst_amount'] = $_POST['igst_amount'] ?? 0;
    $_SESSION['final_total'] = $_POST['final_total'] ?? 35400;
    $_SESSION['promo_code'] = $_POST['promo_code'] ?? '';
    $_SESSION['promo_discount'] = $_POST['promo_discount'] ?? 0;
    $_SESSION['service_type'] = $_POST['service_type'] ?? 'franchise_registration';
    $_SESSION['reference_number'] = 'FRAN'.rand(1000,9999).date('dmYHis');
}

// Validate required fields
if (!isset($_SESSION['amount']) || !isset($_SESSION['user_name']) || !isset($_SESSION['user_email'])) {
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
        <h2>Error</h2>
        <p>Required information is missing. Please fill all required fields.</p>
        <p><a href="../franchise_agreement.php" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Go Back</a></p>
    </div>';
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' />
    <title>Franchise Registration Payment</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .payment-container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .detail-row:last-child { border-bottom: none; }
        .amount-box { background: #002169; color: white; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0; }
    </style>
</head>
<body>

<div class="payment-container">
    <div class="header">
        <h2>Franchise Registration Payment</h2>
        <p>Please verify your details before proceeding</p>
        <a href="../franchise_agreement.php" style="display: inline-block; padding: 8px 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; margin-bottom: 20px; font-size: 14px;">← Go Back</a>
    </div>
    
    <div class="detail-row"><strong>Order ID:</strong> <span><?php echo $_SESSION['reference_number']; ?></span></div>
    <div class="detail-row"><strong>Name:</strong> <span><?php echo $_SESSION['user_name']; ?></span></div>
    <div class="detail-row"><strong>Email:</strong> <span><?php echo $_SESSION['user_email']; ?></span></div>
    <div class="detail-row"><strong>Contact:</strong> <span><?php echo $_SESSION['user_contact']; ?></span></div>
    <div class="detail-row"><strong>Address:</strong> <span><?php echo $_SESSION['address']; ?></span></div>
    <div class="detail-row"><strong>City, State:</strong> <span><?php echo $_SESSION['city'] . ', ' . $_SESSION['state']; ?></span></div>
    <div class="detail-row"><strong>Pin Code:</strong> <span><?php echo $_SESSION['pincode']; ?></span></div>
    <?php if($_SESSION['gst_number']) { ?>
    <div class="detail-row"><strong>GST Number:</strong> <span><?php echo $_SESSION['gst_number']; ?></span></div>
    <?php } ?>
    
    <div class="amount-box">
        <strong>Total Amount: Rs <?php echo $_SESSION['amount']; ?>/-</strong>
    </div>

<?php
// Create Razorpay Order
try {
    $api = new Api($keyId, $keySecret);
    
    $orderData = [
        'receipt' => $_SESSION['reference_number'],
        'amount' => $_SESSION['amount'] * 100, // amount in paise
        'currency' => 'INR',
        'payment_capture' => 1
    ];
    
    $razorpayOrder = $api->order->create($orderData);
    $razorpayOrderId = $razorpayOrder['id'];
    $_SESSION['razorpay_order_id'] = $razorpayOrderId;
    
    $data = [
        "key" => $keyId,
        "amount" => $orderData['amount'],
        "name" => "KIROVA SOLUTIONS LLP",
        "description" => "Franchise Registration",
        "image" => "",
        "prefill" => [
            "name" => $_SESSION['user_name'],
            "email" => $_SESSION['user_email'],
            "contact" => $_SESSION['user_contact'],
        ],
        "notes" => [
            "address" => $_SESSION['address'],
            "merchant_order_id" => $_SESSION['reference_number'],
        ],
        "theme" => [
            "color" => "#002169"
        ],
        "order_id" => $razorpayOrderId,
    ];
    
    $json = json_encode($data);
?>

    <form action="verify.php" method="POST" name="razorpayform">
        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
        <input type="hidden" name="razorpay_signature" id="razorpay_signature">
        <input type="hidden" name="razorpay_order_id" value="<?php echo $razorpayOrderId; ?>">
    </form>
    
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var options = <?php echo $json; ?>;
        options.handler = function (response) {
            document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
            document.getElementById('razorpay_signature').value = response.razorpay_signature;
            document.razorpayform.submit();
        };
        
        var rzp = new Razorpay(options);
        rzp.open();
        
        rzp.on('payment.failed', function (response){
            alert('Payment failed: ' + response.error.description);
        });
    });
    </script>

<?php
} catch (Exception $e) {
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px;">
        <h2>Payment Error</h2>
        <p>Error creating payment order: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p><a href="../franchise_agreement.php" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Go Back</a></p>
    </div>';
}
?>
</div>

</body>
</html>









