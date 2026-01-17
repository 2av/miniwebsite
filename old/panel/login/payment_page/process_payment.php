<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if card ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
        <h2>Missing Card ID</h2>
        <p>The payment link is incomplete. Please use the complete payment link sent to you.</p>
        <p>If you received this link via email or message, please click the entire link.</p>
        <p>For assistance, contact support at: <a href="mailto:support@example.com">support@example.com</a></p>
        <p><a href="../index.php" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Return to Homepage</a></p>
    </div>';
    exit;
}

$keyId ='rzp_live_xU57a1JhH7To1G';
$keySecret = 'VHJzQnCxqF5HTNsE3LUTZtnI';
$displayCurrency = 'INR';

$razorpay_path = __DIR__ . '/razorpay-php/Razorpay.php';

if (file_exists($razorpay_path)) {
    require_once($razorpay_path);
} else {
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
        <h2>Payment Gateway Not Available</h2>
        <p>The payment gateway is currently not available. Please try again later.</p>
        <p>For assistance, contact support at: <a href="mailto:support@example.com">support@example.com</a></p>
    </div>';
    exit;
}

// Database connection
$db_host = "p004.bom1.mysecurecloudhost.com";
$db_user = "wwwmoody_miniweb_vcard";
$db_pass = "miniweb_vcard";
$db_name = "miniweb_vcard";

// Create database connection
try {
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($connect->connect_error) {
        die("Connection failed: " . $connect->connect_error);
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Get card details from database
$card_id = $_GET['id'];
$query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="' . $card_id . '" ');

if (mysqli_num_rows($query) == 1) {
    $row = mysqli_fetch_array($query);
    $status = $row['d_payment_status'];
    
    // Get customer contact
    $customer = mysqli_query($connect, 'SELECT user_contact FROM customer_login WHERE user_email="' . $row['user_email'] . '" ');
    $contactno = "";
    if ($customer && mysqli_num_rows($customer) > 0) {
        $row1 = mysqli_fetch_array($customer);
        $contactno = $row1['user_contact'];
    }
    
    // Set session variables
    $_SESSION['reference_number'] = rand(100, 9000) . date('dhsi');
    $_SESSION['user_name'] = $row['d_f_name'] . ' ' . $row['d_l_name'];
    $_SESSION['user_contact'] = $contactno;
    $_SESSION['amount'] = 1; // Set your amount here
    $_SESSION['id'] = $card_id;
    
    // Create Razorpay order
    try {
        $api = new \Razorpay\Api\Api($keyId, $keySecret);
        
        $orderData = [
            'receipt'         => ($contactno) . date('dhsi'),
            'amount'          => $_SESSION['amount'] * 100, // amount in paise
            'currency'        => 'INR',
            'payment_capture' => 1 // auto capture
        ];
        
        $razorpayOrder = $api->order->create($orderData);
        $razorpayOrderId = $razorpayOrder['id'];
        $_SESSION['razorpay_order_id'] = $razorpayOrderId;
        
        $data = [
            "key"               => $keyId,
            "amount"            => $orderData['amount'],
            "name"              => "KIROVA SOLUTIONS LLP",
            "description"       => "Payment",
            "image"             => "favicon.png",
            "prefill"           => [
                "name"          => $_SESSION['user_name'],
                "email"         => isset($row['user_email']) ? $row['user_email'] : '',
                "contact"       => $contactno,
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
        
        // Display payment form
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Payment</title>
            <link rel="stylesheet" href="css.css">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f5f5f5;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 50px auto;
                    padding: 20px;
                    background-color: #fff;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                h1 {
                    text-align: center;
                    color: #333;
                }
                .payment-details {
                    margin: 20px 0;
                }
                .payment-details div {
                    display: flex;
                    justify-content: space-between;
                    padding: 10px 0;
                    border-bottom: 1px solid #eee;
                }
                .payment-details div:last-child {
                    border-bottom: none;
                }
                .payment-button {
                    width: 100%;
                    padding: 15px;
                    background-color: #ff5476;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    font-size: 16px;
                    cursor: pointer;
                    margin-top: 20px;
                }
                .payment-button:hover {
                    background-color: #e04a6b;
                }
            </style>
            <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        </head>
        <body>
            <div class="container">
                <h1>Payment Details</h1>
                <div class="payment-details">
                    <div>
                        <span>Name:</span>
                        <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    </div>
                    <div>
                        <span>Amount:</span>
                        <span>₹<?php echo number_format($_SESSION['amount'], 2); ?></span>
                    </div>
                    <div>
                        <span>Reference Number:</span>
                        <span><?php echo $_SESSION['reference_number']; ?></span>
                    </div>
                </div>
                <button class="payment-button" onclick="payWithRazorpay()">Pay Now</button>
            </div>

            <script>
                function payWithRazorpay() {
                    var options = <?php echo $json; ?>;
                    var rzp = new Razorpay(options);
                    rzp.open();
                }
            </script>
        </body>
        </html>
        <?php
        
    } catch (Exception $e) {
        echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
            <h2>Payment Error</h2>
            <p>Error creating payment order: ' . htmlspecialchars($e->getMessage()) . '</p>
            <p>For assistance, contact support at: <a href="mailto:support@example.com">support@example.com</a></p>
        </div>';
    }
} else {
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
        <h2>Card Not Found</h2>
        <p>The requested card could not be found in our system.</p>
        <p>For assistance, contact support at: <a href="mailto:support@example.com">support@example.com</a></p>
    </div>';
}
?>