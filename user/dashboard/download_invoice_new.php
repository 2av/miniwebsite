<?php
// Include centralized database config
require_once(__DIR__ . '/../../app/config/database.php');

// Check if user is logged in
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Allow admin bypass via temporary session
$isAdminBypass = false;
if (isset($_GET['admin']) && $_GET['admin'] == '1') {
    if (isset($_SESSION['invoice_admin_bypass']['allowed']) && $_SESSION['invoice_admin_bypass']['allowed'] === true) {
        if (time() <= ($_SESSION['invoice_admin_bypass']['expires_at'] ?? 0)) {
            $isAdminBypass = true;
        } else {
            unset($_SESSION['invoice_admin_bypass']);
        }
    }
}

if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    if (!$isAdminBypass) {
        echo '<div class="alert alert-danger">Please login to download invoice.</div>';
        exit;
    }
}

// Check if invoice_id is provided (support both 'invoice_id' and 'id' parameters)
$invoice_id = '';
if (isset($_GET['invoice_id']) && !empty($_GET['invoice_id'])) {
    $invoice_id = $_GET['invoice_id'];
} elseif (isset($_GET['id']) && !empty($_GET['id'])) {
    $invoice_id = $_GET['id'];
} else {
    echo '<div class="alert alert-danger">Invalid request. Invoice ID is required.</div>';
    exit;
}

$invoice_id = mysqli_real_escape_string($connect, $invoice_id);
$user_email = $_SESSION['user_email'] ?? '';

// Try to get invoice details from invoice_details table (new flow)
if ($isAdminBypass) {
    $invoice_query = mysqli_query($connect, "SELECT id.*, dc.d_comp_name, dc.d_f_name, dc.d_l_name 
                                            FROM invoice_details id 
                                            LEFT JOIN digi_card dc ON id.card_id = dc.id 
                                            WHERE id.id = '$invoice_id'");
} else {
    $invoice_query = mysqli_query($connect, "SELECT id.*, dc.d_comp_name, dc.d_f_name, dc.d_l_name 
                                            FROM invoice_details id 
                                            LEFT JOIN digi_card dc ON id.card_id = dc.id 
                                            WHERE id.id = '$invoice_id' AND dc.user_email = '$user_email'");
}

if ($invoice_query && mysqli_num_rows($invoice_query) > 0) {
    // Modern invoices stored in invoice_details
    $invoice = mysqli_fetch_array($invoice_query);
} else {
    // Legacy fallback: treat ID as digi_card ID (old behaviour) and build invoice data from digi_card + customer_login
    $card_id = $invoice_id;
    
    // Ensure card belongs to this user (unless admin bypass)
    $card_email_condition = $isAdminBypass ? "" : " AND user_email = '$user_email'";
    $card_query = mysqli_query($connect, "SELECT * FROM digi_card WHERE id = '$card_id' $card_email_condition");
    
    if (!$card_query || mysqli_num_rows($card_query) == 0) {
        echo '<div class="alert alert-danger">Invoice not found or access denied.</div>';
        exit;
    }
    
    $card = mysqli_fetch_array($card_query);
    
    // Get basic customer details from legacy customer_login table
    $legacy_user = [
        'user_name'    => '',
        'user_contact' => ''
    ];
    if (!empty($card['user_email'])) {
        $legacy_user_query = mysqli_query(
            $connect,
            'SELECT user_name, user_contact FROM customer_login WHERE user_email="' . mysqli_real_escape_string($connect, $card['user_email']) . '" LIMIT 1'
        );
        if ($legacy_user_query && mysqli_num_rows($legacy_user_query) > 0) {
            $legacy_user = mysqli_fetch_array($legacy_user_query);
        }
    }
    
    // Legacy invoice fields (mirrors old download_invoice.php behaviour)
    $invoice_date    = $card['d_payment_date'] ?? date('Y-m-d');
    $invoice_number  = 'KIR/00' . $card_id;
    $payment_amount  = (isset($card['d_payment_amount']) && $card['d_payment_amount'] < 200)
        ? 999
        : (float)($card['d_payment_amount'] ?? 0);
    
    $gst_number      = $card['d_gst']   ?? '';
    $customer_state  = strtolower(trim($card['d_state'] ?? ''));
    
    // Determine intra/inter state for GST split (copy of old logic)
    $customer_state_code = '';
    if (!empty($gst_number) && strlen($gst_number) >= 2) {
        $customer_state_code = substr($gst_number, 0, 2);
    }
    
    $company_state_code = '06'; // Haryana
    if (!empty($gst_number) && strlen($gst_number) === 15) {
        $is_interstate = ($customer_state_code !== $company_state_code);
    } else {
        $is_interstate = ($customer_state !== 'haryana');
    }
    
    // Compute GST components from total (same as old file)
    $sub_total = $payment_amount / 1.18;
    if ($is_interstate) {
        $igst_amount = $sub_total * 0.18;
        $cgst_amount = 0;
        $sgst_amount = 0;
    } else {
        $cgst_amount = $sub_total * 0.09;
        $sgst_amount = $sub_total * 0.09;
        $igst_amount = 0;
    }
    
    // Build a synthetic $invoice array compatible with the new template
    $billing_name = !empty($card['d_gst_name'])
        ? $card['d_gst_name']
        : trim(($card['d_f_name'] ?? '') . ' ' . ($card['d_l_name'] ?? ''));
    
    $billing_address = $card['d_gst_address'] ?? '';
    $billing_contact = $legacy_user['user_contact'] ?? '';
    
    $invoice = [
        'invoice_number'      => $invoice_number,
        'invoice_date'        => $invoice_date,
        'billing_name'        => $billing_name,
        'billing_address'     => $billing_address,
        'billing_contact'     => $billing_contact,
        'billing_gst_number'  => $gst_number,
        'service_description' => 'Mini Website - 1 Year',
        'hsn_sac_code'        => '998313',
        'quantity'            => 1,
        'original_amount'     => $payment_amount,
        'promo_discount'      => 0,
        'sub_total'           => $sub_total,
        'igst_amount'         => $igst_amount,
        'cgst_amount'         => $cgst_amount,
        'sgst_amount'         => $sgst_amount,
        'total_amount'        => $payment_amount,
    ];
}

// Convert number to words function with Rupees/Paise support (Indian system)
function numberToWords($number) {
    if (!is_numeric($number)) {
        $number = floatval($number);
    }

    if ($number < 0) {
        return "Negative " . numberToWords(abs($number));
    }

    $parts = explode('.', number_format($number, 2, '.', ''));
    $integerPart = (int)$parts[0];
    $decimalPart = isset($parts[1]) ? (int)$parts[1] : 0;

    $ones = array(
        0 => "", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four", 5 => "Five",
        6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine", 10 => "Ten",
        11 => "Eleven", 12 => "Twelve", 13 => "Thirteen", 14 => "Fourteen",
        15 => "Fifteen", 16 => "Sixteen", 17 => "Seventeen", 18 => "Eighteen",
        19 => "Nineteen"
    );
    $tens = array(
        0 => "", 1 => "", 2 => "Twenty", 3 => "Thirty", 4 => "Forty", 5 => "Fifty",
        6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety"
    );
    $hundreds = array(
        "Hundred", "Thousand", "Lakh", "Crore"
    );

    function convertIntegerToWords($num, $ones, $tens, $hundreds) {
        if ($num == 0) return "Zero";

        if ($num < 20) {
            return $ones[$num];
        }

        if ($num < 100) {
            return trim($tens[floor($num/10)] . ( ($num%10) ? " " . $ones[$num%10] : "" ));
        }

        if ($num < 1000) {
            $remainder = $num % 100;
            return trim($ones[floor($num/100)] . " " . $hundreds[0] . ( $remainder ? " " . convertIntegerToWords($remainder, $ones, $tens, $hundreds) : "" ));
        }

        if ($num < 100000) {
            $remainder = $num % 1000;
            return trim(convertIntegerToWords(floor($num/1000), $ones, $tens, $hundreds) . " " . $hundreds[1] . ( $remainder ? " " . convertIntegerToWords($remainder, $ones, $tens, $hundreds) : "" ));
        }

        if ($num < 10000000) {
            $remainder = $num % 100000;
            return trim(convertIntegerToWords(floor($num/100000), $ones, $tens, $hundreds) . " " . $hundreds[2] . ( $remainder ? " " . convertIntegerToWords($remainder, $ones, $tens, $hundreds) : "" ));
        }

        $remainder = $num % 10000000;
        return trim(convertIntegerToWords(floor($num/10000000), $ones, $tens, $hundreds) . " " . $hundreds[3] . ( $remainder ? " " . convertIntegerToWords($remainder, $ones, $tens, $hundreds) : "" ));
    }

    $rupeesWords = convertIntegerToWords($integerPart, $ones, $tens, $hundreds);
    $rupeeLabel = ($integerPart == 1) ? 'Rupee' : 'Rupees';
    $result = $rupeesWords . ' ' . $rupeeLabel;

    if ($decimalPart > 0) {
        $paiseWords = convertIntegerToWords($decimalPart, $ones, $tens, $hundreds);
        $result .= ' and ' . $paiseWords . ' Paise';
    }

    return trim($result);
}

// Format amounts for display
$sub_total_formatted = number_format((float)$invoice['sub_total'], 2);
$total_amount_formatted = number_format((float)$invoice['total_amount'], 2);
$igst_amount_formatted = number_format((float)$invoice['igst_amount'], 2);
$cgst_amount_formatted = number_format((float)($invoice['cgst_amount'] ?? 0), 2);
$sgst_amount_formatted = number_format((float)($invoice['sgst_amount'] ?? 0), 2);

$amount_in_words = numberToWords($invoice['total_amount']) . " Only";

// Create HTML content
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice #' . $invoice['invoice_number'] . '</title>
    <style>
        /* Reset and base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            
           
            font-family: Arial, sans-serif;
            color: #333;
            font-size: 12px;
        }
        
        @page {
            size: A4;
            margin: 20mm 15mm 20mm 15mm;
        }
        
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Table styles with fixed layout to prevent overflow */
        table {
            table-layout: fixed;
            width: 100%;
        }
        
        /* Make sure images don\'t cause overflow */
        img {
            max-width: 100%;
            height: auto;
        }
        
        /* Print button styles */
        .print-button {
            display: block;
            margin: 10px auto;
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        .print-button:hover {
            background-color: #45a049;
        }
        
        /* Top button specific styles */
        .top-print-button {
            margin-bottom: 20px;
        }
        
        /* Hide print buttons when printing */
        @media print {
            .print-button {
                display: none;
            }
        }
        .logo {
            text-align: left;
            margin-bottom: 15px;
        }
        .logo img {
            max-width: 150px;
            height: auto;
        }
        .invoice-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin: 15px 0;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        .header-info {
            margin-bottom: 15px;
            width: 100%;
        }
        .header-info table {
            width: 100%;
        }
        .header-info td {
            padding: 5px;
            font-size: 12px;
        }
        .details-container {
            width: 100%;
            display: table;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .customer-details, .company-details {
            border: 1px solid #ccc;
            padding: 10px;
            vertical-align: top;
            display: table-cell;
            width: 50%;
            font-size: 12px;
        }
        .details-title {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 12px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .items-table th, .items-table td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: center;
            font-size: 12px; /* Consistent font size */
        }
        .items-table th {
            background-color: #ffbf00;
            color: #000;
            font-size: 12px;
            font-weight: bold;
        }
        .amount-in-words {
            margin-bottom: 15px;
            font-style: italic;
            font-size: 12px;
        }
        .terms {
             
            font-size: 12px;
            float: left;
            width: 60%;
        }
        .signature {
            text-align: right;
            margin-top: 30px;
            font-size: 12px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #777;
        }
        .tax-summary {
            width: 40%;
            float: right;
            margin-top: -5px;
        }
        .tax-summary table {
            width: 100%;
            border-collapse: collapse;
        }
        .tax-summary td {
            padding: 3px;
            text-align: right;
            font-size: 12px; /* Consistent font size */
        }
        .tax-summary .total-row {
            font-weight: bold;
        }
        .cgst-row, .sgst-row, .igst-row {
            background-color: transparent;
        }
        /* Center and bold the thank you message */
        .thank-you-section {
            text-align: center;
            margin: 20px 0;
            clear: both;
            width: 100%;
            padding-top: 20px;
        }
        
        .thank-you-section p {
            font-weight: bold;
            font-size: 14px;
            margin: 5px 0;
        }
            .bank-details {
                margin-top: 20px;
            }
    </style>
    <script>
        function printInvoice() {
            window.print();
        }
        
        // Add event listener to ensure no scrollbars on load
        window.onload = function() {
         //   document.body.style.overflow = "hidden";
        };
    </script>
</head>
<body>
    <!-- Top print button -->
    <button class="print-button top-print-button" onclick="printInvoice()">Print This Page</button>
    
    <div class="container">
      <div class="logo">
            <img src="../../assets/images/logo.png">
        </div>
        
        <div class="invoice-title">TAX INVOICE</div>
        <div class="header-info">
            <table>
                <tr>
                    <td><strong>Date:</strong> ' . date('Y-m-d', strtotime($invoice['invoice_date'])) . '</td>
                    <td style="text-align: right;"><strong>Invoice No:</strong> ' . $invoice['invoice_number'] . '</td>
                   </tr>
            </table>
        </div>
        
        <div class="details-container">
            <div class="customer-details">
                <div class="details-title">CUSTOMER DETAILS</div>
                <div><strong>Billing Name:</strong> ' . htmlspecialchars($invoice['billing_name']) . '</div>
                <div><strong>Address:</strong> ' . htmlspecialchars($invoice['billing_address']) . '</div>
                <div><strong>Contact:</strong> ' . htmlspecialchars($invoice['billing_contact']) . '</div>
                <div><strong>GST No:</strong> ' . (!empty($invoice['billing_gst_number']) ? htmlspecialchars($invoice['billing_gst_number']) : 'Not Provided') . '</div>
            </div>
            
            <div class="company-details">
                <div class="details-title">COMPANY DETAILS</div>
                <div>KIROVA SOLUTIONS LLP</div>
                <div><strong>Address:</strong> plot no 535, 1st floor, block b, near madrasi mandir, sec 23, sanjay colony, Faridabad Sector 22, sector 23 police station, Faridabad, Faridabad- 121005, Haryana, India</div>
                <div><strong>Contact:</strong> +91 9429693061</div>
                <div><strong>Email Id:</strong> support@miniwebsite.in</div>
                <div><strong>PAN No:</strong> ABDFK4023D</div>
                <div><strong>GST No:</strong> 06ABDFK4023D1ZW</div>
            </div>
        </div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%;">SR No.</th>
                    <th>DESCRIPTION</th>
                    <th style="width: 20%;">HSN/SAC CODE</th>
                    <th style="width: 10%;">QTY.</th>
                    <th style="width: 10%;">PRICE</th>
                    <th style="width: 10%;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>' . htmlspecialchars($invoice['service_description']) . '</td>
                    <td>' . htmlspecialchars($invoice['hsn_sac_code']) . '</td>
                    <td>' . htmlspecialchars($invoice['quantity']) . '</td>
                    <td>₹' . number_format((float)$invoice['original_amount'], 2) . '</td>
                    <td>₹' . number_format((float)$invoice['original_amount'], 2) . '</td>
                </tr>
            </tbody>
        </table>
        
        <div class="terms">
            <p><strong>FOR KIROVA SOLUTIONS LLP</strong></p><br/><br/>
            <p style="font-size: 10px;">This is a computer generated invoice, hence<br>No signature is required.</p>
            <br/><br/>
            
            <p>(Authorised Signatory)</p>
            <br/><br/>
            <p><strong>Amount (in words):</strong> ' . $amount_in_words . '</p>
            
            <div class="bank-details" style="margin-top: 20px;">
                
                <p><strong>COMPANY BANK DETAILS:</strong></p>
                <table class="bank-table">
                    <tr>
                        <td class="bank-label">A/C Name</td>
                        <td class="bank-value">: KIROVA SOLUTIONS LLP</td>
                    </tr>
                    <tr>
                        <td class="bank-label">Bank Name</td>
                        <td class="bank-value">: HDFC BANK</td>
                    </tr>
                    <tr>
                        <td class="bank-label">Account No.</td>
                        <td class="bank-value">: 50200109163384</td>
                    </tr>
                    <tr>
                        <td class="bank-label">IFSC CODE</td>
                        <td class="bank-value">: HDFC0000279</td>
                    </tr>
                    <tr>
                        <td class="bank-label">Account type</td>
                        <td class="bank-value">: CURRENT ACCOUNT</td>
                    </tr>
                </table>
               
            </div>
            
        </div>
        
        <div class="tax-summary">
            <table>
             <tr>
                    <td>Original Amount:</td>
                    <td>₹' . number_format((float)$invoice['original_amount'], 2) . '</td>
                </tr>
                <tr>
                    <td>Discount :</td>
                    <td>₹' . number_format((float)$invoice['promo_discount'], 2) . '</td>
                </tr>
                <tr>
                <td>Sub Total :</td>
                <td>₹' . number_format((float) $invoice['sub_total'], 2) . '</td>
            </tr>';

// Show IGST if it exists, otherwise show CGST+SGST
 
    
        $html .= '
                <tr class="cgst-row">
                    <td>CGST (9%):</td>
                    <td>₹' . $cgst_amount_formatted . '</td>
                </tr>';
    

        $html .= '
                <tr class="sgst-row">
                    <td>SGST (9%):</td>
                    <td>₹' . $sgst_amount_formatted . '</td>
                </tr>';
    
                $html .= '
                <tr class="igst-row">
                    <td>IGST (18%):</td>
                    <td>₹' . $igst_amount_formatted . '</td>
                </tr>';

$html .= '
                <tr class="total-row" style="border-top: 1px solid #000; border-bottom: 1px solid #000;">
                    <td><strong>Total:</strong></td>
                    <td><strong>₹' . $total_amount_formatted . '</strong></td>
                </tr>
            </table>
        </div>
        
        <div style="clear: both;"></div>
        
        <div class="thank-you-section">
            <p><b>Thank you for your business</b></p>
            <p><b>www.miniwebsite.in</b></p>
        </div>
    </div>
    
    <!-- Bottom print button (keeping both for convenience) -->
    <button class="print-button" onclick="printInvoice()">Print This Page</button>
</body>
</html>';

// Output the HTML
echo $html;
?>


