<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Log session data for debugging
error_log("Joining Deal Verify.php - Session data: " . print_r($_SESSION, true));
error_log("Joining Deal Verify.php - POST data: " . print_r($_POST, true));

require_once(__DIR__ . '/../panel/login/payment_page/razorpay-php/Razorpay.php');

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

// Database connection
$db_host = "p004.bom1.mysecurecloudhost.com";
$db_user = "wwwmoody_miniweb_vcard";
$db_pass = "miniweb_vcard";
$db_name = "miniweb_vcard";

try {
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    // Check connection
    if ($connect->connect_error) {
        throw new Exception("Connection failed: " . $connect->connect_error);
    }
    
    // Set charset to utf8
    $connect->set_charset("utf8");
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection error: " . $e->getMessage());
}

// Razorpay credentials
$keyId = 'rzp_live_xU57a1JhH7To1G';
$keySecret = 'VHJzQnCxqF5HTNsE3LUTZtnI';

$success = true;
$error = "Payment Failed";

// Normal payment verification
if (empty($_POST['razorpay_payment_id'])) {
    $success = false;
    $error = "Payment information missing. Please try again.";
} else {
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
    $date = date('Y-m-d H:i:s');
    
    // Check if required session variables exist
    if (!isset($_SESSION['joining_deal_payment_data'])) {
        error_log("ERROR: joining_deal_payment_data not found in session");
        die("Payment verification failed: Missing payment data. Please try again.");
    }
    
    if (!isset($_SESSION['reference_number'])) {
        error_log("ERROR: reference_number not found in session");
        die("Payment verification failed: Missing reference number. Please try again.");
    }
    
    // Process joining deal payment after successful payment
    if (isset($_SESSION['joining_deal_payment_data'])) {
        $payment_data = $_SESSION['joining_deal_payment_data'];
        
        // Debug log
        error_log("Processing joining deal payment for: " . $payment_data['email']);
        
        // Get the mapping ID and joining deal ID
        $mapping_id = $payment_data['mapping_id'];
        $joining_deal_id = $payment_data['joining_deal_id'];
        
        if (empty($mapping_id) || empty($joining_deal_id)) {
            error_log("ERROR: Missing mapping_id or joining_deal_id");
            die("Payment verification failed: Missing deal information. Please try again.");
        }
        
        // Update the joining deal mapping with payment details
        $update_query = "UPDATE user_joining_deals_mapping 
                         SET payment_status = 'PAID', 
                             payment_date = NOW(), 
                             amount_paid = ?, 
                             transaction_id = ?,
                             updated_at = NOW()
                         WHERE id = ?";
        
        $stmt = $connect->prepare($update_query);
        $amount_paid = $_SESSION['amount'];
        $transaction_id = $_POST['razorpay_payment_id'];
        $stmt->bind_param("dsi", $amount_paid, $transaction_id, $mapping_id);
        
        if ($stmt->execute()) {
            // Log the successful payment
            error_log("Joining deal payment successful: User: " . $payment_data['email'] . ", Amount: $amount_paid, Transaction: $transaction_id");
            
            // Create invoice entry in invoice_details table (using same structure as verify.php)
            // Get the next KIR invoice number
            $last_invoice_query = mysqli_query($connect, "SELECT invoice_number FROM invoice_details WHERE invoice_number LIKE 'KIR/%' ORDER BY CAST(SUBSTRING(invoice_number, 5) AS UNSIGNED) DESC LIMIT 1");
            $next_number = 1;
            if ($last_invoice_query && mysqli_num_rows($last_invoice_query) > 0) {
                $last_invoice = mysqli_fetch_array($last_invoice_query);
                $last_number = intval(substr($last_invoice['invoice_number'], 5));
                $next_number = $last_number + 1;
            }
            $invoice_number = 'KIR/' . str_pad($next_number, 5, '0', STR_PAD_LEFT);
            $invoice_date = date('Y-m-d');
            $current_timestamp = date('Y-m-d H:i:s');
            
            // Calculate GST amounts (assuming 18% total GST split as CGST + SGST)
            $sub_total = $amount_paid / 1.18; // Remove GST to get base amount
            $cgst_amount = $sub_total * 0.09; // 9% CGST
            $sgst_amount = $sub_total * 0.09; // 9% SGST
            $igst_amount = 0; // No IGST for intrastate
            $total_amount = $amount_paid;
            $gst_percentage = 18; // Total GST percentage
            
            $hsn_sac_code = '998314'; // Default for digital services
            
            // Get the joining deal name from user_joining_deals_mapping
            $deal_name_query = mysqli_query($connect, "SELECT jd.deal_name FROM user_joining_deals_mapping ujdm JOIN joining_deals jd ON ujdm.joining_deal_id = jd.id WHERE ujdm.id = " . intval($mapping_id));
            $deal_name = 'Joining Deal Payment'; // Default fallback
            if ($deal_name_query && mysqli_num_rows($deal_name_query) > 0) {
                $deal_data = mysqli_fetch_array($deal_name_query);
                $deal_name = $deal_data['deal_name'];
            }
            $service_name = 'Franchisee Distributer (' . $deal_name . ')';
            
            $invoice_query = "INSERT INTO invoice_details (
                invoice_number, invoice_date, card_id, user_email, user_name, user_contact,
                billing_name, billing_email, billing_contact, billing_address, billing_state, 
                billing_city, billing_pincode, billing_gst_number, original_amount, discount_amount, 
                final_amount, promo_code, promo_discount, razorpay_order_id, razorpay_payment_id, 
                razorpay_signature, payment_status, payment_date, service_name, service_description,
                hsn_sac_code, quantity, unit_price, total_price, sub_total, igst_percentage, 
                igst_amount, cgst_amount, sgst_amount, total_amount, payment_type, reference_number, created_at, updated_at
            ) VALUES (
                '" . mysqli_real_escape_string($connect, $invoice_number) . "',
                '" . mysqli_real_escape_string($connect, $invoice_date) . "',
                '0', /* No card ID for joining deal */
                '" . mysqli_real_escape_string($connect, $payment_data['email']) . "',
                '" . mysqli_real_escape_string($connect, $payment_data['name']) . "',
                '" . mysqli_real_escape_string($connect, $payment_data['contact']) . "',
                '" . mysqli_real_escape_string($connect, $payment_data['name']) . "',
                '" . mysqli_real_escape_string($connect, $payment_data['email']) . "',
                '" . mysqli_real_escape_string($connect, $payment_data['contact']) . "',
                '" . mysqli_real_escape_string($connect, $payment_data['address']) . "',
                '" . mysqli_real_escape_string($connect, $payment_data['state']) . "',
                '" . mysqli_real_escape_string($connect, $payment_data['city']) . "',
                '" . mysqli_real_escape_string($connect, $payment_data['pincode']) . "',
                '" . mysqli_real_escape_string($connect, $payment_data['gst_number']) . "',
                '" . mysqli_real_escape_string($connect, $sub_total) . "',
                '0', /* No discount for joining deals */
                '" . mysqli_real_escape_string($connect, $total_amount) . "',
                '', /* No promo code */
                '0', /* No promo discount */
                '" . mysqli_real_escape_string($connect, $_SESSION['razorpay_order_id']) . "',
                '" . mysqli_real_escape_string($connect, $transaction_id) . "',
                '" . mysqli_real_escape_string($connect, $_POST['razorpay_signature'] ?? '') . "',
                'Success',
                '" . mysqli_real_escape_string($connect, $date) . "',
                '" . mysqli_real_escape_string($connect, $service_name) . "',
                '" . mysqli_real_escape_string($connect, $service_name) . "',
                '" . mysqli_real_escape_string($connect, $hsn_sac_code) . "',
                '1',
                '" . mysqli_real_escape_string($connect, $total_amount) . "',
                '" . mysqli_real_escape_string($connect, $total_amount) . "',
                '" . mysqli_real_escape_string($connect, $sub_total) . "',
                '" . mysqli_real_escape_string($connect, $gst_percentage) . "',
                '" . mysqli_real_escape_string($connect, $igst_amount) . "',
                '" . mysqli_real_escape_string($connect, $cgst_amount) . "',
                '" . mysqli_real_escape_string($connect, $sgst_amount) . "',
                '" . mysqli_real_escape_string($connect, $total_amount) . "',
                'Franchisee',
                '" . mysqli_real_escape_string($connect, $_SESSION['reference_number']) . "',
                '" . mysqli_real_escape_string($connect, $current_timestamp) . "',
                '" . mysqli_real_escape_string($connect, $current_timestamp) . "'
            )";
            
            $invoice_stmt = $connect->prepare($invoice_query);
            
            if ($invoice_stmt->execute()) {
                $invoice_id = $connect->insert_id;
                error_log("Invoice created with ID: $invoice_id for user: " . $payment_data['email']);
                
                // Update start_date, expiry_date, and invoice_id in user_joining_deals_mapping
                $date_update_query = "UPDATE user_joining_deals_mapping 
                                     SET start_date = NOW(),
                                         expiry_date = DATE_ADD(NOW(), INTERVAL 1 YEAR),
                                         invoice_id = ?,
                                         updated_at = NOW()
                                     WHERE id = ?";
                
                $date_stmt = $connect->prepare($date_update_query);
                $date_stmt->bind_param("ii", $invoice_id, $mapping_id);
                
                if ($date_stmt->execute()) {
                    error_log("Dates updated: start_date = NOW(), expiry_date = NOW() + 1 YEAR for user: " . $payment_data['email']);
                    
                    // Ensure mapped deals (MiniWebsite and Franchise) are replaced after successful payment
                    // Fetch configured mapped deal IDs for this joining deal
                    $mapped_deals_sql = "SELECT mw_deal_id, franchise_deal_id FROM joining_deals WHERE id = " . intval($joining_deal_id) . " LIMIT 1";
                    $mapped_deals_rs = mysqli_query($connect, $mapped_deals_sql);
                    if ($mapped_deals_rs && mysqli_num_rows($mapped_deals_rs) > 0) {
                        $mapped_deals = mysqli_fetch_array($mapped_deals_rs);
                        $customer_email = mysqli_real_escape_string($connect, $payment_data['email']);
                        $created_by = mysqli_real_escape_string($connect, 'system');
                        
                        // Process deferred mappings from notes (for upgrade cases with payment)
                        // Format in notes: "remove_mw:ID,remove_fr:ID,map_mw:ID,map_fr:ID"
                        $mapping_notes_query = mysqli_query($connect, "SELECT notes FROM user_joining_deals_mapping WHERE id = " . intval($mapping_id) . " LIMIT 1");
                        $deferred_removals = [];
                        $deferred_mappings = [];
                        
                        if ($mapping_notes_query && mysqli_num_rows($mapping_notes_query) > 0) {
                            $notes_data = mysqli_fetch_array($mapping_notes_query);
                            if (!empty($notes_data['notes'])) {
                                // Extract deferred actions from notes
                                if (preg_match_all("/(remove_mw|remove_fr|map_mw|map_fr):(\d+)/", $notes_data['notes'], $matches, PREG_SET_ORDER)) {
                                    foreach ($matches as $match) {
                                        $action = $match[1];
                                        $deal_id = intval($match[2]);
                                        
                                        if (strpos($action, 'remove_') === 0) {
                                            $deferred_removals[$action] = $deal_id;
                                        } else {
                                            $deferred_mappings[$action] = $deal_id;
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Step 1: Remove old deals first (if specified in deferred notes)
                        if (!empty($deferred_removals)) {
                            // Remove old MW deal
                            if (isset($deferred_removals['remove_mw'])) {
                                $old_mw_id = $deferred_removals['remove_mw'];
                                $delete_old_mw = mysqli_query($connect, "DELETE FROM deal_customer_mapping 
                                    WHERE customer_email='".$customer_email."' AND deal_id=".$old_mw_id);
                                if(!$delete_old_mw) {
                                    error_log("Warning: Failed to remove old MW deal mapping after payment: " . mysqli_error($connect));
                                } else {
                                    error_log("Removed old MW deal ID ".$old_mw_id." for user: ".$customer_email);
                                }
                            }
                            
                            // Remove old Franchise deal
                            if (isset($deferred_removals['remove_fr'])) {
                                $old_fr_id = $deferred_removals['remove_fr'];
                                $delete_old_fr = mysqli_query($connect, "DELETE FROM deal_customer_mapping 
                                    WHERE customer_email='".$customer_email."' AND deal_id=".$old_fr_id);
                                if(!$delete_old_fr) {
                                    error_log("Warning: Failed to remove old Franchise deal mapping after payment: " . mysqli_error($connect));
                                } else {
                                    error_log("Removed old Franchise deal ID ".$old_fr_id." for user: ".$customer_email);
                                }
                            }
                        }
                        
                        // Step 2: Remove any existing MW and Franchise mappings (clean replacement)
                        // This ensures complete replacement even if deferred notes weren't set
                        // Remove existing MiniWebsite deal mappings
                        $remove_mw_query = mysqli_query($connect, "DELETE dcm FROM deal_customer_mapping dcm 
                            INNER JOIN deals d ON dcm.deal_id = d.id 
                            WHERE dcm.customer_email='".$customer_email."' AND d.plan_type='MiniWebsite'");
                        if(!$remove_mw_query) {
                            error_log("Warning: Failed to remove existing MW mappings after payment: " . mysqli_error($connect));
                        }
                        
                        // Remove existing Franchise deal mappings
                        $remove_fr_query = mysqli_query($connect, "DELETE dcm FROM deal_customer_mapping dcm 
                            INNER JOIN deals d ON dcm.deal_id = d.id 
                            WHERE dcm.customer_email='".$customer_email."' AND d.plan_type='Franchise'");
                        if(!$remove_fr_query) {
                            error_log("Warning: Failed to remove existing Franchise mappings after payment: " . mysqli_error($connect));
                        }

                        // Step 3: Map new deals (use deferred mappings if available, otherwise use joining deal config)
                        $mw_deal_id = null;
                        $fr_deal_id = null;
                        
                        // Check deferred mappings first
                        if (isset($deferred_mappings['map_mw'])) {
                            $mw_deal_id = $deferred_mappings['map_mw'];
                        } elseif (!empty($mapped_deals['mw_deal_id']) && intval($mapped_deals['mw_deal_id']) > 0) {
                            $mw_deal_id = intval($mapped_deals['mw_deal_id']);
                        }
                        
                        if (isset($deferred_mappings['map_fr'])) {
                            $fr_deal_id = $deferred_mappings['map_fr'];
                        } elseif (!empty($mapped_deals['franchise_deal_id']) && intval($mapped_deals['franchise_deal_id']) > 0) {
                            $fr_deal_id = intval($mapped_deals['franchise_deal_id']);
                        }
                        
                        // Map new MiniWebsite deal
                        if ($mw_deal_id !== null && $mw_deal_id > 0) {
                            $ins_q = mysqli_query($connect, "INSERT INTO deal_customer_mapping (customer_email, deal_id, created_by, created_date) VALUES ('".$customer_email."', ".$mw_deal_id.", '".$created_by."', NOW())");
                            if(!$ins_q){ 
                                error_log("Failed to auto-map MW deal after payment: ".mysqli_error($connect)); 
                            } else {
                                error_log("Successfully mapped MW deal ID ".$mw_deal_id." for user: ".$customer_email);
                            }
                        }

                        // Map new Franchise deal
                        if ($fr_deal_id !== null && $fr_deal_id > 0) {
                            $ins_q2 = mysqli_query($connect, "INSERT INTO deal_customer_mapping (customer_email, deal_id, created_by, created_date) VALUES ('".$customer_email."', ".$fr_deal_id.", '".$created_by."', NOW())");
                            if(!$ins_q2){ 
                                error_log("Failed to auto-map Franchise deal after payment: ".mysqli_error($connect)); 
                            } else {
                                error_log("Successfully mapped Franchise deal ID ".$fr_deal_id." for user: ".$customer_email);
                            }
                        }
                    }
                    
                    // Send success response
                    echo '<div class="payment_confirmation">Your Payment Successful. Please wait we are redirecting...</div>';
                    
                    // Redirect to download receipt page
                    $payment_id = $_POST['razorpay_payment_id'];
                    echo '<meta http-equiv="refresh" content="3;URL=download_receipt.php?ref=' . $_SESSION['reference_number'] . '&payment_id=' . $payment_id . '">';
                } else {
                    throw new Exception("Failed to update dates: " . $date_stmt->error);
                }
                
                $date_stmt->close();
            } else {
                throw new Exception("Failed to create invoice: " . $invoice_stmt->error);
            }
            
            $invoice_stmt->close();
        } else {
            throw new Exception("Failed to update payment status: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Clear payment data
        unset($_SESSION['joining_deal_payment_data']);
    }
    
} else {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Payment Failed</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
            .payment_error { border: 1px solid #f44336; width: 80%; max-width: 500px; padding: 30px; font-size: 16px; background: #ffebee; color: #c62828; font-family: sans-serif; margin: 50px auto; text-align: center; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .btn1 { display: inline-block; padding: 12px 25px; background: #f44336; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="payment_error">
            <h2>Payment Failed</h2>
            <p><?php echo $error; ?></p>
            <a href="../franchisee-distributer-agreement.php" class="btn1">Try Again</a>
        </div>
        <script>
            setTimeout(function() {
                window.location.href = '../franchisee-distributer-agreement.php';
            }, 5000);
        </script>
    </body>
    </html>
    <?php
}
?>
