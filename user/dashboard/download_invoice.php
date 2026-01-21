<?php
// Include centralized database config
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/role_helper.php');

// Check if franchisee is logged in
$current_role = get_current_user_role();
if($current_role !== 'FRANCHISEE') {
    header('Location: ' . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . '/login/franchisee.php');
    exit;
}

$franchisee_email = get_user_email();

// Get franchisee payment invoice details from invoice_details table
// Check both user_email and billing_email, and try multiple payment_type values for backward compatibility
$invoice_query = "SELECT * FROM invoice_details 
                  WHERE (user_email = ? OR billing_email = ?) 
                  AND (payment_type = 'Franchisee' OR payment_type = 'franchisee' OR payment_type LIKE '%Franchisee%' OR payment_type IS NULL)
                  ORDER BY created_at DESC LIMIT 1";

$stmt = $connect->prepare($invoice_query);
if (!$stmt) {
    error_log("Invoice query prepare failed: " . $connect->error);
    die("Database error. Please contact support.");
}
$stmt->bind_param("ss", $franchisee_email, $franchisee_email);
$stmt->execute();
$result = $stmt->get_result();

// Debug: Log query results
if (!$result) {
    error_log("Invoice query execution failed: " . $stmt->error);
}

$invoice_data = null;
if($result && $invoice_data = $result->fetch_assoc()) {
    // Invoice found
    error_log("Invoice found for franchisee: " . $franchisee_email . ", Invoice #: " . ($invoice_data['invoice_number'] ?? 'N/A') . ", Payment Type: " . ($invoice_data['payment_type'] ?? 'NULL'));
} else {
    // No invoice found - log for debugging
    error_log("No invoice found for franchisee email: " . $franchisee_email);
    // Try alternative query without payment_type restriction (check both emails)
    $alt_query = "SELECT * FROM invoice_details 
                  WHERE (user_email = ? OR billing_email = ?)
                  ORDER BY created_at DESC LIMIT 1";
    $alt_stmt = $connect->prepare($alt_query);
    if ($alt_stmt) {
        $alt_stmt->bind_param("ss", $franchisee_email, $franchisee_email);
        $alt_stmt->execute();
        $alt_result = $alt_stmt->get_result();
        if($alt_result && $alt_data = $alt_result->fetch_assoc()) {
            error_log("Found invoice with payment_type: " . ($alt_data['payment_type'] ?? 'NULL') . " for email: " . $franchisee_email . ", user_email: " . ($alt_data['user_email'] ?? 'NULL') . ", billing_email: " . ($alt_data['billing_email'] ?? 'NULL'));
            // Use this invoice if found
            $invoice_data = $alt_data;
        }
        $alt_stmt->close();
    }
}

if($invoice_data) {
    
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

    // Use pre-calculated values from database
    $invoice_number = $invoice_data['invoice_number'];
    $invoice_date = $invoice_data['invoice_date'];
    $billing_name = $invoice_data['billing_name'];
    $billing_address = $invoice_data['billing_address'];
    $billing_contact = $invoice_data['billing_contact'];
    $billing_gst = $invoice_data['billing_gst_number'];
    $service_description = $invoice_data['service_description'];
    $hsn_sac_code = $invoice_data['hsn_sac_code'];
    $quantity = $invoice_data['quantity'];
    $unit_price = $invoice_data['unit_price'];
    $total_price = $invoice_data['total_price'];
    $sub_total = $invoice_data['sub_total'];
    $igst_amount = $invoice_data['igst_amount'];
    $cgst_amount = $invoice_data['cgst_amount'];
    $sgst_amount = $invoice_data['sgst_amount'];
    $total_amount = $invoice_data['total_amount'];
    $promo_discount = $invoice_data['promo_discount'];
    $original_amount = $invoice_data['original_amount'];
    $discount_amount = $invoice_data['discount_amount'];

    // Format amounts for display
    $sub_total_formatted = number_format((float)$sub_total, 2);
    $total_amount_formatted = number_format((float)$total_amount, 2);
    $igst_amount_formatted = number_format((float)$igst_amount, 2);
    $cgst_amount_formatted = number_format((float)$cgst_amount, 2);
    $sgst_amount_formatted = number_format((float)$sgst_amount, 2);
    $unit_price_formatted = number_format((float)$unit_price, 2);
    $total_price_formatted = number_format((float)$total_price, 2);

    $amount_in_words = numberToWords($total_amount) . " Only";

    // Get base path for assets
    function get_assets_base_path() {
        $script_name = $_SERVER['SCRIPT_NAME'];
        $script_dir = dirname($script_name);
        $base = preg_replace('#/user(/.*)?$#', '', $script_dir);
        if ($base === '/' || $base === '') {
            return '';
        }
        return $base;
    }
    $assets_base = get_assets_base_path();

    // Create HTML content with same design as download_receipt.php
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Tax Invoice #' . $invoice_number . '</title>
        <style>
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
            
            table {
                table-layout: fixed;
                width: 100%;
            }
            
            img {
                max-width: 100%;
                height: auto;
            }
            
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
            
            .top-print-button {
                margin-bottom: 20px;
            }
            
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
                font-size: 12px;
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
                font-size: 12px;
            }
            .tax-summary .total-row {
                font-weight: bold;
            }
            .cgst-row, .sgst-row, .igst-row {
                background-color: transparent;
            }
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
        </style>
        <script>
            function printInvoice() {
                window.print();
            }
            
            window.onload = function() {
             //   document.body.style.overflow = "hidden";
            };
        </script>
    </head>
    <body>
        <button class="print-button top-print-button" onclick="printInvoice()">Print This Page</button>
        
        <div class="container">
          <div class="logo">
                <img src="' . $assets_base . '/assets/images/logo.png">
            </div>
            
            <div class="invoice-title">TAX INVOICE</div>
            <div class="header-info">
                <table>
                    <tr>
                        <td><strong>Date:</strong> ' . date('Y-m-d', strtotime($invoice_date)) . '</td>
                        <td style="text-align: right;"><strong>Invoice No:</strong> ' . $invoice_number . '</td>
                       </tr>
                </table>
            </div>
            
            <div class="details-container">
                <div class="customer-details">
                    <div class="details-title">CUSTOMER DETAILS</div>
                    <div><strong>Billing Name:</strong> ' . htmlspecialchars($billing_name) . '</div>
                    <div><strong>Address:</strong> ' . htmlspecialchars($billing_address) . '</div>
                    <div><strong>Contact:</strong> ' . htmlspecialchars($billing_contact) . '</div>
                    <div><strong>GST No:</strong> ' . (!empty($billing_gst) ? htmlspecialchars($billing_gst) : 'Not Provided') . '</div>
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
                        <td>' . htmlspecialchars($service_description) . '</td>
                        <td>' . htmlspecialchars($hsn_sac_code) . '</td>
                        <td>' . htmlspecialchars($quantity) . '</td>
                        <td>₹' . number_format((float)$original_amount, 2) . '</td>
                        <td>₹' . number_format((float)$original_amount, 2) . '</td>
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
                        <td>₹' . number_format((float)$original_amount, 2) . '</td>
                    </tr>
                    <tr>
                        <td>Discount :</td>
                        <td>₹' . number_format((float)$discount_amount, 2) . '</td>
                    </tr>
                    <tr>
                    <td>Sub Total :</td>
                    <td>₹' . number_format((float) $sub_total, 2) . '</td>
                </tr>';

// Show IGST if it exists, otherwise show CGST+SGST
if($igst_amount > 0) {
    $html .= '
                    <tr class="igst-row">
                        <td>IGST (18%):</td>
                        <td>₹' . $igst_amount_formatted . '</td>
                    </tr>';
} else {
    $html .= '
                    <tr class="cgst-row">
                        <td>CGST (9%):</td>
                        <td>₹' . $cgst_amount_formatted . '</td>
                    </tr>
                    <tr class="sgst-row">
                        <td>SGST (9%):</td>
                        <td>₹' . $sgst_amount_formatted . '</td>
                    </tr>';
}

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
        
        <button class="print-button" onclick="printInvoice()">Print This Page</button>
    </body>
    </html>';

// Check if this is a direct view request
if (isset($_GET['view']) && $_GET['view'] == 'true') {
    echo $html;
    exit;
}

// Generate PDF using mPDF (if available)
$vendor_path = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($vendor_path)) {
    require_once $vendor_path;
    
    try {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
        ]);
        
        $mpdf->SetTitle('Franchise Registration Invoice #' . $invoice_number);
        $mpdf->WriteHTML($html);
        $mpdf->Output('Franchise_Invoice_' . $invoice_number . '.pdf', 'D');
        exit;
    } catch (Exception $e) {
        echo '<div style="color: red; padding: 20px;">Error generating PDF: ' . $e->getMessage() . '</div>';
        echo $html;
    }
} else {
    // Fallback to HTML
    header('Content-Type: text/html');
    echo $html;
}

} else {
    // No invoice found
    $nav_base = get_assets_base_path() . '/user';
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Invoice Not Found</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .error { color: #d32f2f; font-size: 18px; margin-bottom: 20px; }
            .back-btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="error">No invoice found for your franchisee registration payment.</div>
        <p style="color: #666; font-size: 14px; margin: 10px 0;">If you have made a payment, please contact support.</p>
        <a href="' . $nav_base . '/dashboard" class="back-btn">Back to Dashboard</a>
    </body>
    </html>';
}
?>
