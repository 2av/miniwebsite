<?php
session_start();

// Include centralized configs
require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../app/config/payment.php');

// Check if user is logged in (franchisee)
if (!isset($_SESSION['franchisee_email']) && !isset($_SESSION['user_email'])) {
    header('Location: ../user/login');
    exit;
}

// Get email from session
$franchisee_email = $_SESSION['franchisee_email'] ?? $_SESSION['user_email'] ?? '';

// Initialize variables
$promo_discount = 0;
$promo_message = '';
$promo_applied = false;
$is_auto_applied = false;

// Initialize promo variables from session if they exist
if(isset($_SESSION['promo_code']) && isset($_SESSION['promo_discount'])) {
    $promo_applied = true;
    $promo_discount = $_SESSION['promo_discount'];
    $is_auto_applied = isset($_SESSION['auto_applied_promo']) && $_SESSION['auto_applied_promo'] === true;
    $promo_message = '<div class="promo-success">Promo code applied successfully! Discount: ₹' . $promo_discount . '</div>';
} else {
    $promo_applied = false;
    $promo_discount = 0;
    $promo_message = '';
    $is_auto_applied = false;
}

// Process form data if coming from wallet form
if ($_POST) {
    $recharge_amount = floatval($_POST['recharge_amount'] ?? 0);
    
    if ($recharge_amount < 350) {
        $recharge_amount = 350;
    }
    
    $_SESSION['recharge_amount'] = $recharge_amount;
    $_SESSION['user_name'] = $_POST['f_name'] . ' ' . $_POST['l_name'];
    $_SESSION['user_contact'] = $_POST['f_contact'] ?? '';
    $_SESSION['user_email'] = $franchisee_email;
    $_SESSION['service_type'] = 'wallet_recharge';
    $_SESSION['reference_number'] = 'WALLET'.rand(1000,9999).date('dmYHis');
    
    // For wallet recharge, no discount initially
    $_SESSION['original_amount'] = $recharge_amount;
    $_SESSION['discount_amount'] = 0;
    $_SESSION['subtotal_amount'] = $recharge_amount;
}

// Get or use session values
$original_amount = $_SESSION['original_amount'] ?? $_SESSION['recharge_amount'] ?? 350;
$discount_amount = $_SESSION['discount_amount'] ?? 0;
$subtotal = $_SESSION['subtotal_amount'] ?? $original_amount;

// Initialize reference number if not set
if (!isset($_SESSION['reference_number'])) {
    $_SESSION['reference_number'] = 'WALLET'.rand(1000,9999).date('dmYHis');
}

// Check if Razorpay SDK exists
$razorpay_path = __DIR__ . '/razorpay-php/Razorpay.php';

if (!file_exists($razorpay_path)) {
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px; font-family: Arial, sans-serif; text-align: center;">
        <h2>Payment SDK Error</h2>
        <p>The payment processing library is missing. Please contact the administrator.</p>
        <p><a href="../user/wallet" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Go Back</a></p>
    </div>';
    exit;
}

require_once($razorpay_path);
use Razorpay\Api\Api;

// Get Razorpay credentials from config
$keyId = 'rzp_live_xU57a1JhH7To1G';
$keySecret = 'VHJzQnCxqF5HTNsE3LUTZtnI';
$displayCurrency = 'INR';

// Create Razorpay API instance
$api = new Api($keyId, $keySecret);

// Calculate GST (18% for wallet recharge - no state distinction)
$cgst_amount = 0;
$sgst_amount = 0;
$igst_amount = round($subtotal * 0.18, 2);
$total_tax = $igst_amount;
$final_amount = $subtotal + $total_tax;

// Store in session
$_SESSION['amount'] = $final_amount;
$_SESSION['subtotal_amount'] = $subtotal;
$_SESSION['cgst_amount'] = $cgst_amount;
$_SESSION['sgst_amount'] = $sgst_amount;
$_SESSION['igst_amount'] = $igst_amount;
$_SESSION['final_total'] = $final_amount;
$_SESSION['original_amount'] = $original_amount;

// Update promo status from session
if (isset($_SESSION['promo_code']) && isset($_SESSION['promo_discount'])) {
    $promo_applied = true;
    $promo_discount = $_SESSION['promo_discount'];
    $is_auto_applied = isset($_SESSION['auto_applied_promo']) && $_SESSION['auto_applied_promo'] === true;
}

// Get franchisee details for pre-filling
$franchisee_query = mysqli_query($connect, 'SELECT * FROM franchisee_login WHERE f_user_email="' . mysqli_real_escape_string($connect, $franchisee_email) . '"');
$franchisee_data = mysqli_fetch_array($franchisee_query);
$franchisee_name = $franchisee_data['f_user_name'] ?? '';
$franchisee_contact = $franchisee_data['f_user_contact'] ?? '';

?>

<!DOCTYPE html>
<html>
<head>
    <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' />
    <title>Wallet Recharge Payment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        html {
            overflow-x: hidden;
            background: #f5f5f5;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .back-button {
            display: inline-flex;
            align-items: center;
            background: #002169;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,33,105,0.3);
            margin-bottom: 20px;
        }
        .back-button:hover {
            background: #001a4d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,33,105,0.4);
        }
        .billing-form input:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.3);
        }
        .promo-success {
            background: #d4edda;
            color: #155724;
            padding: 8px;
            border-radius: 4px;
            font-size: 13px;
            margin-top: 5px;
        }
        .promo-error {
            background: #f8d7da;
            color: #721c24;
            padding: 8px;
            border-radius: 4px;
            font-size: 13px;
            margin-top: 5px;
        }
    </style>
</head>
<body>

<div style="text-align: center; margin-bottom: 20px;">
    <a href="../user/wallet" class="back-button">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Back to Wallet
    </a>
</div>

<!-- Billing Details Section -->
<div style="max-width: 450px; margin: 0 auto; background: #002169; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    <h4 style="color: white; text-align: center; margin-bottom: 10px; font-size: 20px; font-weight: 600;">Wallet Recharge</h4>
    
    <!-- Yellow line below header -->
    <div style="width: 35%; height: 2px; background: #ffc107; margin: 0 auto 25px auto; border-radius: 1px;"></div>
    
    <!-- Billing Form -->
    <div class="billing-form">
        <!-- Recharge Amount -->
        <input type="number" id="recharge_amount" name="recharge_amount" placeholder="Recharge Amount" 
               value="<?php echo isset($_SESSION['original_amount']) ? htmlspecialchars($_SESSION['original_amount']) : '350'; ?>" 
               min="350" step="100" onkeyup="updateTaxOnInput()" onchange="updateTaxOnInput()"
               style="display: none; width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
        
        <input type="text" id="gst_name" name="gst_name" placeholder="Name" 
               value="<?php echo isset($_SESSION['billing_gst_name']) ? htmlspecialchars($_SESSION['billing_gst_name']) : htmlspecialchars($franchisee_name); ?>" 
               required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
        
        <input type="email" id="gst_email" name="gst_email" placeholder="Email Address" 
               value="<?php echo isset($_SESSION['billing_gst_email']) ? htmlspecialchars($_SESSION['billing_gst_email']) : htmlspecialchars($franchisee_email); ?>" 
               required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
        
        <input type="tel" id="gst_contact" name="gst_contact" placeholder="Contact Number" 
               value="<?php echo isset($_SESSION['billing_gst_contact']) ? htmlspecialchars($_SESSION['billing_gst_contact']) : htmlspecialchars($franchisee_contact); ?>" 
               required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
        
        <input type="text" id="gst_address" name="gst_address" placeholder="Address" 
               value="<?php echo isset($_SESSION['billing_gst_address']) ? htmlspecialchars($_SESSION['billing_gst_address']) : ''; ?>" 
               required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
        
        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
            <input type="text" id="gst_state" name="gst_state" placeholder="State" 
                   value="<?php echo isset($_SESSION['billing_gst_state']) ? htmlspecialchars($_SESSION['billing_gst_state']) : ''; ?>" 
                   required style="width: 50%; padding: 12px 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
            <input type="text" id="gst_city" name="gst_city" placeholder="City" 
                   value="<?php echo isset($_SESSION['billing_gst_city']) ? htmlspecialchars($_SESSION['billing_gst_city']) : ''; ?>" 
                   required style="width: 50%; padding: 12px 15px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
        </div>
        
        <input type="text" id="gst_pincode" name="gst_pincode" placeholder="Pin Code" 
               value="<?php echo isset($_SESSION['billing_gst_pincode']) ? htmlspecialchars($_SESSION['billing_gst_pincode']) : ''; ?>" 
               required style="width: 100%; padding: 12px 15px; margin-bottom: 25px; border: none; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
    </div>
    
    <!-- Price Breakdown -->
    <div class="calculation-display" style="margin: 20px 0; color: white; background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px;">
        <table style="width: 100%; color: white; font-size: 16px;">
            <tr>
                <td class="original-price" style="padding: 5px 0; text-align: left; width: 50%;"><strong>Recharge Amount:</strong></td>
                <td class="original-price" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($original_amount, 2); ?></strong></td>
            </tr>
            <tr>
                <td class="discount" style="padding: 5px 0; text-align: left; width: 50%;"><strong>Discount:</strong></td>
                <td class="discount" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($discount_amount, 2); ?></strong></td>
            </tr>
            <tr>
                <td class="subtotal" style="padding: 5px 0; text-align: left; width: 50%;"><strong>Sub Total:</strong></td>
                <td class="subtotal" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($subtotal, 2); ?></strong></td>
            </tr>
            <tr>
                <td class="igst" style="padding: 5px 0; text-align: left; width: 50%;"><strong>GST (18%):</strong></td>
                <td class="igst" style="text-align: right; padding: 5px 0; width: 50%;"><strong>₹ <?php echo number_format($igst_amount, 2); ?></strong></td>
            </tr>
            <tr>
                <td class="final-total" style="padding: 5px 0; border-top: 1px solid rgba(255,255,255,0.3); text-align: left;"><strong>Final Total:</strong></td>
                <td class="final-total" style="text-align: right; padding: 5px 0; border-top: 1px solid rgba(255,255,255,0.3);"><strong>₹ <?php echo number_format($final_amount, 2); ?></strong></td>
            </tr>
        </table>
    </div>
    
    <!-- Payment Button -->
    <button id="proceed-to-payment" style="width: 100%; background: #ffc107; color: #000; padding: 15px; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; transition: all 0.3s ease; margin-top: 10px;">
        PROCEED TO PAY
    </button>
</div>

<?php
// Create Razorpay Order
try {
    $orderData = [
        'receipt' => $_SESSION['reference_number'],
        'amount' => $final_amount * 100, // amount in paise
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
        "description" => "Wallet Recharge",
        "image" => "",
        "prefill" => [
            "name" => isset($_SESSION['user_name']) ? $_SESSION['user_name'] : $franchisee_name,
            "email" => $_SESSION['user_email'],
            "contact" => $_SESSION['user_contact'],
        ],
        "notes" => [
            "address" => 'Wallet Recharge',
            "merchant_order_id" => $_SESSION['reference_number'],
        ],
        "theme" => [
            "color" => "#002169"
        ],
        "order_id" => $razorpayOrderId,
    ];
    
    $json = json_encode($data);
?>

<!-- Hidden form for Razorpay -->
<form action="verify_wallet_recharge.php" method="POST" name="razorpayform" style="display:none;">
    <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
    <input type="hidden" name="razorpay_signature" id="razorpay_signature">
    <input type="hidden" name="razorpay_order_id" id="razorpay_order_id" value="<?php echo $razorpayOrderId; ?>">
    <input type="hidden" name="shopping_order_id" value="<?php echo $_SESSION['reference_number']; ?>">
</form>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var originalAmount = <?php echo isset($original_amount) ? $original_amount : 0; ?>;
    var currentDiscount = <?php echo isset($discount_amount) ? $discount_amount : 0; ?>;
    
    // Function to update tax calculation for wallet recharge (18% GST)
    function updateTaxCalculation(originalAmount, discountAmount) {
        var subtotal = originalAmount - discountAmount;
        
        // Wallet recharge always uses 18% GST (IGST)
        var igst = Math.round(subtotal * 0.18 * 100) / 100;
        var cgst = 0;
        var sgst = 0;
        
        var finalAmount = subtotal + igst;
        
        // Update display
        var igstElements = document.querySelectorAll('.igst');
        var finalTotalElements = document.querySelectorAll('.final-total');
        
        if (igstElements.length >= 1) {
            igstElements[0].innerHTML = '<strong>GST (18%):</strong>';
            if (igstElements.length >= 2) {
                igstElements[1].innerHTML = '<strong>₹ ' + igst.toFixed(2) + '</strong>';
            }
        }
        if (finalTotalElements.length >= 2) {
            finalTotalElements[1].innerHTML = '<strong>₹ ' + finalAmount.toFixed(2) + '</strong>';
        }
        
        // Update subtotal
        var subtotalElements = document.querySelectorAll('.subtotal');
        if (subtotalElements.length >= 2) {
            subtotalElements[1].innerHTML = '<strong>₹ ' + subtotal.toFixed(2) + '</strong>';
        }
        
        return finalAmount;
    }
    
    function updateTaxOnInput() {
        var rechargeAmountInput = document.getElementById('recharge_amount');
        var rechargeAmount = rechargeAmountInput ? parseFloat(rechargeAmountInput.value) || originalAmount : originalAmount;
        
        if (rechargeAmount < 350) {
            rechargeAmount = 350;
            if (rechargeAmountInput) rechargeAmountInput.value = 350;
        }
        
        var originalPriceElements = document.querySelectorAll('.original-price');
        if (originalPriceElements.length >= 2) {
            originalPriceElements[1].innerHTML = '<strong>₹ ' + rechargeAmount.toFixed(2) + '</strong>';
        }
        
        updateTaxCalculation(rechargeAmount, 0);
    }
    
    // Proceed to payment button
    var payBtn = document.getElementById('proceed-to-payment');
    if(payBtn) {
        payBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Validation
            var isValid = true;
            var errorMessage = "";
            
            if (!document.getElementById('gst_name').value.trim()) {
                isValid = false;
                errorMessage += "Name is required\n";
                document.getElementById('gst_name').style.border = "2px solid red";
            }
            if (!document.getElementById('gst_email').value.trim()) {
                isValid = false;
                errorMessage += "Email is required\n";
                document.getElementById('gst_email').style.border = "2px solid red";
            }
            if (!document.getElementById('gst_contact').value.trim()) {
                isValid = false;
                errorMessage += "Contact is required\n";
                document.getElementById('gst_contact').style.border = "2px solid red";
            }
            if (!document.getElementById('gst_address').value.trim()) {
                isValid = false;
                errorMessage += "Address is required\n";
                document.getElementById('gst_address').style.border = "2px solid red";
            }
            if (!document.getElementById('gst_state').value.trim()) {
                isValid = false;
                errorMessage += "State is required\n";
                document.getElementById('gst_state').style.border = "2px solid red";
            }
            if (!document.getElementById('gst_city').value.trim()) {
                isValid = false;
                errorMessage += "City is required\n";
                document.getElementById('gst_city').style.border = "2px solid red";
            }
            if (!document.getElementById('gst_pincode').value.trim()) {
                isValid = false;
                errorMessage += "Pin Code is required\n";
                document.getElementById('gst_pincode').style.border = "2px solid red";
            }
            
            var rechargeAmount = parseFloat(document.getElementById('recharge_amount').value) || 0;
            if (rechargeAmount < 350) {
                isValid = false;
                errorMessage += "Recharge amount must be at least ₹350\n";
                document.getElementById('recharge_amount').style.border = "2px solid red";
            }
            
            if (!isValid) {
                alert(errorMessage);
                return false;
            }
            
            // Save billing details first
            payBtn.disabled = true;
            payBtn.textContent = 'Processing...';
            
            var formData = new FormData();
            formData.append('recharge_amount', document.getElementById('recharge_amount').value);
            formData.append('gst_name', document.getElementById('gst_name').value);
            formData.append('gst_email', document.getElementById('gst_email').value);
            formData.append('gst_contact', document.getElementById('gst_contact').value);
            formData.append('gst_address', document.getElementById('gst_address').value);
            formData.append('gst_state', document.getElementById('gst_state').value);
            formData.append('gst_city', document.getElementById('gst_city').value);
            formData.append('gst_pincode', document.getElementById('gst_pincode').value);
            
            // Recalculate tax before saving
            var rechargeAmount = parseFloat(document.getElementById('recharge_amount').value) || originalAmount;
            var finalAmount = updateTaxCalculation(rechargeAmount, 0);
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'save_wallet_billing_details.php', true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            var finalAmount = response.final_amount || updateTaxCalculation(rechargeAmount, 0);
                            payBtn.textContent = 'Opening Payment...';
                            initializeRazorpayWithAmount(finalAmount);
                        } else {
                            alert('Error saving billing details: ' + response.message);
                            payBtn.disabled = false;
                            payBtn.textContent = 'PROCEED TO PAY';
                        }
                    } catch (e) {
                        alert('Error processing response');
                        payBtn.disabled = false;
                        payBtn.textContent = 'PROCEED TO PAY';
                    }
                } else {
                    alert('Error saving billing details');
                    payBtn.disabled = false;
                    payBtn.textContent = 'PROCEED TO PAY';
                }
            };
            xhr.send(formData);
        });
    }
    
    // Initialize Razorpay - create new order with updated amount
    function initializeRazorpayWithAmount(finalAmount) {
        var createOrderXhr = new XMLHttpRequest();
        createOrderXhr.open('POST', 'create_razorpay_order.php', true);
        createOrderXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        createOrderXhr.onload = function() {
            if (createOrderXhr.status === 200) {
                try {
                    var orderResponse = JSON.parse(createOrderXhr.responseText);
                    if (orderResponse.success) {
                        var options = {
                            key: "<?php echo $keyId; ?>",
                            amount: Math.round(orderResponse.amount * 100), // Use payment amount from response
                            name: "KIROVA SOLUTIONS LLP",
                            description: "Wallet Recharge",
                            image: "",
                            order_id: orderResponse.order_id,
                            handler: function (response) {
                                document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
                                document.getElementById('razorpay_signature').value = response.razorpay_signature;
                                document.getElementById('razorpay_order_id').value = orderResponse.order_id;
                                document.razorpayform.submit();
                            },
                            prefill: {
                                name: "<?php echo isset($_SESSION['user_name']) ? addslashes($_SESSION['user_name']) : ''; ?>",
                                email: "<?php echo isset($_SESSION['user_email']) ? addslashes($_SESSION['user_email']) : ''; ?>",
                                contact: "<?php echo isset($_SESSION['user_contact']) ? addslashes($_SESSION['user_contact']) : ''; ?>"
                            },
                            notes: {
                                address: "Wallet Recharge",
                                merchant_order_id: "<?php echo $_SESSION['reference_number']; ?>"
                            },
                            theme: {
                                color: "#002169"
                            },
                            modal: {
                                ondismiss: function() {
                                    var payBtn = document.getElementById('proceed-to-payment');
                                    if (payBtn) {
                                        payBtn.disabled = false;
                                        payBtn.textContent = 'PROCEED TO PAY';
                                    }
                                }
                            }
                        };
                        
                        var orderIdInput = document.getElementById('razorpay_order_id');
                        if (orderIdInput) {
                            orderIdInput.value = orderResponse.order_id;
                        } else {
                            var form = document.forms['razorpayform'];
                            if (form) {
                                var newInput = document.createElement('input');
                                newInput.type = 'hidden';
                                newInput.name = 'razorpay_order_id';
                                newInput.id = 'razorpay_order_id';
                                newInput.value = orderResponse.order_id;
                                form.appendChild(newInput);
                            }
                        }
                        
                        var rzp = new Razorpay(options);
                        rzp.on('payment.failed', function (response){
                            alert('Payment failed: ' + response.error.description);
                            var payBtn = document.getElementById('proceed-to-payment');
                            if (payBtn) {
                                payBtn.disabled = false;
                                payBtn.textContent = 'PROCEED TO PAY';
                            }
                        });
                        rzp.open();
                    } else {
                        alert('Error creating payment order: ' + orderResponse.message);
                        var payBtn = document.getElementById('proceed-to-payment');
                        if (payBtn) {
                            payBtn.disabled = false;
                            payBtn.textContent = 'PROCEED TO PAY';
                        }
                    }
                } catch (e) {
                    alert('Error processing order response');
                    var payBtn = document.getElementById('proceed-to-payment');
                    if (payBtn) {
                        payBtn.disabled = false;
                        payBtn.textContent = 'PROCEED TO PAY';
                    }
                }
            } else {
                alert('Error creating payment order');
                var payBtn = document.getElementById('proceed-to-payment');
                if (payBtn) {
                    payBtn.disabled = false;
                    payBtn.textContent = 'PROCEED TO PAY';
                }
            }
        };
        createOrderXhr.send('amount=' + finalAmount);
    }
});
</script>

<?php
} catch (Exception $e) {
    echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc; margin: 20px; border-radius: 5px;">
        <h2>Payment Error</h2>
        <p>Error creating payment order: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p><a href="../user/wallet" style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px;">Go Back</a></p>
    </div>';
}
?>

</body>
</html>
