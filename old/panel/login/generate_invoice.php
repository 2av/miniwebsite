<?php
require('connect.php');

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Invalid request. Card ID is required.');
}

// Get card details
$card_id = $_GET['id'];
$query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="' . $card_id . '"');

if (mysqli_num_rows($query) == 0) {
    die('Card not found.');
}

$row = mysqli_fetch_array($query);

// Get user details
$user_query = mysqli_query($connect, 'SELECT * FROM customer_login WHERE user_email="' . $row['user_email'] . '"');
$user_data = mysqli_fetch_array($user_query);

// Set invoice details
$invoice_number = 'INV-' . $card_id . '-' . date('Ymd');
$invoice_date = date('d-m-Y');
$payment_amount = ($row['d_payment_amount'] < 200) ? 199 : $row['d_payment_amount'];
$gst_rate = 18; // 18% GST
$gst_amount = ($payment_amount * $gst_rate) / 100;
$total_amount = $payment_amount + $gst_amount;

// Create PDF content
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice #' . $invoice_number . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            font-size: 16px;
            line-height: 24px;
        }
        .invoice-box table {
            width: 100%;
            line-height: inherit;
            text-align: left;
            border-collapse: collapse;
        }
        .invoice-box table td {
            padding: 5px;
            vertical-align: top;
        }
        .invoice-box table tr td:nth-child(2) {
            text-align: right;
        }
        .invoice-box table tr.top table td {
            padding-bottom: 20px;
        }
        .invoice-box table tr.top table td.title {
            font-size: 45px;
            line-height: 45px;
            color: #333;
        }
        .invoice-box table tr.information table td {
            padding-bottom: 40px;
        }
        .invoice-box table tr.heading td {
            background: #eee;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
        }
        .invoice-box table tr.details td {
            padding-bottom: 20px;
        }
        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
        }
        .invoice-box table tr.item.last td {
            border-bottom: none;
        }
        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #eee;
            font-weight: bold;
        }
        @media only screen and (max-width: 600px) {
            .invoice-box table tr.top table td {
                width: 100%;
                display: block;
                text-align: center;
            }
            .invoice-box table tr.information table td {
                width: 100%;
                display: block;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <table cellpadding="0" cellspacing="0">
            <tr class="top">
                <td colspan="2">
                    <table>
                        <tr>
                            <td class="title">
                                <img src="https://' . $_SERVER['HTTP_HOST'] . '/logo.png" style="width:100px; max-width:300px;">
                            </td>
                            <td>
                                Invoice #: ' . $invoice_number . '<br>
                                Created: ' . $invoice_date . '<br>
                                Due: ' . $invoice_date . '
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            
            <tr class="information">
                <td colspan="2">
                    <table>
                        <tr>
                            <td>
                                ' . $_SERVER['HTTP_HOST'] . '<br>
                                Digital Visiting Card<br>
                                support@' . $_SERVER['HTTP_HOST'] . '
                            </td>
                            <td>
                                ' . $row['d_comp_name'] . '<br>
                                ' . $row['d_f_name'] . ' ' . $row['d_l_name'] . '<br>
                                ' . $row['user_email'] . '
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            
            <tr class="heading">
                <td>Payment Method</td>
                <td>Status</td>
            </tr>
            
            <tr class="details">
                <td>Online Payment</td>
                <td>' . $row['d_payment_status'] . '</td>
            </tr>
            
            <tr class="heading">
                <td>Item</td>
                <td>Price</td>
            </tr>
            
            <tr class="item">
                <td>Mini Website - 1 Year</td>
                <td>₹' . number_format($payment_amount, 2) . '</td>
            </tr>
            
            <tr class="item">
                <td>GST (' . $gst_rate . '%)</td>
                <td>₹' . number_format($gst_amount, 2) . '</td>
            </tr>
            
            <tr class="total">
                <td></td>
                <td>Total: ₹' . number_format($total_amount, 2) . '</td>
            </tr>
        </table>
        <div style="margin-top: 50px; text-align: center; font-size: 12px; color: #555;">
            <p>This is a computer-generated invoice and does not require a signature.</p>
            <p>Thank you for your business!</p>
        </div>
    </div>
</body>
</html>
';

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Invoice_' . $invoice_number . '.pdf"');

// Check if mPDF is available, otherwise use basic HTML
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
    $mpdf = new \Mpdf\Mpdf();
    $mpdf->WriteHTML($html);
    $mpdf->Output('Invoice_' . $invoice_number . '.pdf', 'D');
} else {
    // Fallback to HTML if mPDF is not available
    header('Content-Type: text/html');
    echo $html;
}
?>
