<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require('connect.php');
// Convert number to words
function numberToWords($number) {
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

    if ($number == 0) return "Zero";
    
    if ($number < 20) {
        return $ones[$number];
    }
    
    if ($number < 100) {
        return $tens[floor($number/10)] . " " . $ones[$number%10];
    }
    
    if ($number < 1000) {
        return $ones[floor($number/100)] . " " . $hundreds[0] . " " . numberToWords($number%100);
    }
    
    if ($number < 100000) {
        return numberToWords(floor($number/1000)) . " " . $hundreds[1] . " " . numberToWords($number%1000);
    }
    
    if ($number < 10000000) {
        return numberToWords(floor($number/100000)) . " " . $hundreds[2] . " " . numberToWords($number%100000);
    }
    
    return numberToWords(floor($number/10000000)) . " " . $hundreds[3] . " " . numberToWords($number%10000000);
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('No invoice ID provided');
}

$card_id = $_GET['id'];

// Get card details
$query = mysqli_query($connect, "SELECT * FROM digi_card WHERE id='$card_id'");

if (mysqli_num_rows($query) == 0) {
    die('Invoice not found');
}

$row = mysqli_fetch_array($query);

// Get user details
$user_query = mysqli_query($connect, 'SELECT * FROM customer_login WHERE user_email="' . $row['user_email'] . '"');
$user_data = mysqli_fetch_array($user_query);

// Set invoice details
$invoice_date = $row['d_payment_date'];
$invoice_number = 'KIR/00' . $card_id ;
$payment_amount = ($row['d_payment_amount'] < 200) ? 999 : $row['d_payment_amount'];

// Get GST number from database
$gst_number = isset($row['d_gst']) ? $row['d_gst'] : '';
$customer_state = isset($row['d_state']) ? strtolower(trim($row['d_state'])) : '';

// Get state code from GST if available
$customer_state_code = '';
if (!empty($gst_number) && strlen($gst_number) >= 2) {
    $customer_state_code = substr($gst_number, 0, 2);
}

// Determine if interstate
$is_interstate = false;
$company_state_code='06';
// Rule: GST filled
if (!empty($gst_number) && strlen($gst_number) === 15) {
    $is_interstate = ($customer_state_code !== $company_state_code);
} else {
    // GST not filled: use state field instead
    $is_interstate = ($customer_state !== 'haryana');
}

// GST Calculations
$sub_total = $payment_amount / 1.18;

if ($is_interstate) {
    // IGST (18%)
    $igst_amount = $sub_total * 0.18;
    $cgst_amount = 0;
    $sgst_amount = 0;
} else {
    // CGST + SGST (9% each)
    $cgst_amount = $sub_total * 0.09;
    $sgst_amount = $sub_total * 0.09;
    $igst_amount = 0;
}

// Format amounts for display
$sub_total_formatted = number_format($sub_total, 2);
$cgst_amount_formatted = number_format($cgst_amount, 2);
$sgst_amount_formatted = number_format($sgst_amount, 2);
$igst_amount_formatted = number_format($igst_amount, 2);
$total_amount_formatted = number_format($payment_amount, 2);

$amount_in_words = numberToWords($payment_amount) . " Rupees Only";
 
 

// Create PDF content
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice #' . $invoice_number . '</title>
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
            overflow-x: hidden; /* Prevent horizontal scroll */
            overflow-y: auto; /* Allow vertical scroll only if needed */
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
            margin-top: 20px;
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
            margin-top: 20px;
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
    </style>
    <script>
        function printInvoice() {
            window.print();
        }
        
        // Add event listener to ensure no scrollbars on load
        window.onload = function() {
            document.body.style.overflow = "hidden";
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
                    <td><strong>Date:</strong> ' . date('Y-m-d H:i:s', strtotime($invoice_date)) . '</td>
                    <td style="text-align: right;"><strong>Invoice No:</strong> ' . $invoice_number . '</td>
                   </tr>
            </table>
        </div>
        
        <div class="details-container">
            <div class="customer-details">
                <div class="details-title">CUSTOMER DETAILS</div>
                <div><strong>Billing Name:</strong> ' . (isset($row['d_gst_name']) ? $row['d_gst_name'] : $row['d_f_name'] . ' ' . $row['d_l_name']) . '</div>
                <div><strong>Address:</strong> ' . (isset($row['d_gst_address']) ? $row['d_gst_address'] : '') . '</div>
                <div><strong>Contact:</strong> ' . (isset($user_data['user_contact']) ? $user_data['user_contact'] : '') . '</div>
                <div><strong>GST No:</strong> ' . (!empty($gst_number) ? $gst_number : 'Not Provided') . '</div>
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
                    <th>SR No.</th>
                    <th>DESCRIPTION</th>
                    <th>HSN/SAC CODE</th>
                    <th>QTY.</th>
                    <th>PRICE</th>
                    <th>TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>Mini Website - 1 Year</td>
                    <td>998313</td>
                    <td>1</td>
                    <td>₹' . $sub_total_formatted . '</td>
                    <td>₹' . $sub_total_formatted . '</td>
                </tr>
                <tr>
                    <td colspan="5" style="text-align: right;"><strong>Sub Total:</strong></td>
                    <td>₹' . $sub_total_formatted . '</td>
                </tr>
                <tr>
                    <td colspan="5" style="text-align: right;"><strong>Total:</strong></td>
                    <td><strong>₹' . $total_amount_formatted . '</strong></td>
                </tr>
            </tbody>
        </table>
        
        <div class="terms">
            <p><strong>FOR KIROVA SOLUTIONS LLP</strong></p>
            <p>This is a computer generated invoice, hence<br>No signature is required.</p>
            <p>(Authorised Signatory)</p>
            
            <div class="bank-details">
                <p><strong>Amount (in words):</strong> ' . $amount_in_words . '</p>
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
                    <td>Sub Total:</td>
                    <td>₹' . $sub_total_formatted . '</td>
                </tr>';

// Conditionally show CGST+SGST or IGST based on interstate status
if ($is_interstate) {
    // Interstate - Show only IGST
    $html .= '
                <tr class="igst-row">
                    <td>IGST (18%):</td>
                    <td>₹' . $igst_amount_formatted . '</td>
                </tr>';
} else {
    // Intrastate - Show only CGST and SGST
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
                <tr class="total-row">
                    <td>Total:</td>
                    <td>₹' . $total_amount_formatted . '</td>
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

// Check if this is a direct view request (not download)
if (isset($_GET['view']) && $_GET['view'] == 'true') {
    echo $html;
    exit;
}

// Update mPDF configuration for A4 size
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
    
    try {
        // Create mPDF instance with A4 configuration
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'tempDir' => sys_get_temp_dir()
        ]);
        
        // Set document metadata
        $mpdf->SetTitle('Tax Invoice #' . $invoice_number);
        $mpdf->SetAuthor($_SERVER['HTTP_HOST']);
        $mpdf->SetCreator($_SERVER['HTTP_HOST']);
        
        // Write HTML to PDF
        $mpdf->WriteHTML($html);
        
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Tax_Invoice_' . $invoice_number . '.pdf"');
        header('Cache-Control: max-age=0');
        
        // Output PDF
        $mpdf->Output('Tax_Invoice_' . $invoice_number . '.pdf', 'D');
        exit;
    } catch (Exception $e) {
        // If mPDF fails, fall back to HTML with print button
        echo '<div style="color: red; padding: 20px; background: #ffeeee; border: 1px solid #ffcccc;">
            Error generating PDF: ' . $e->getMessage() . '
            <br><br>
            Displaying HTML version instead.
        </div>';
        echo $html;
    }
} else {
    // If mPDF is not available, use TCPDF as fallback
    if (file_exists('../vendor/tecnickcom/tcpdf/tcpdf.php')) {
        require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');
        
        // Create TCPDF instance
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($_SERVER['HTTP_HOST']);
        $pdf->SetTitle('Tax Invoice #' . $invoice_number);
        
        // Remove header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        
        // Add a page
        $pdf->AddPage();
        
        // Write HTML to PDF
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Output PDF
        $pdf->Output('Tax_Invoice_' . $invoice_number . '.pdf', 'D');
        exit;
    } else {
        // If no PDF library is available, fall back to HTML with print button
        header('Content-Type: text/html');
        echo $html;
    }
}
?>


