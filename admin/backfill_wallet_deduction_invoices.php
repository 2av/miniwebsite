<?php
require_once __DIR__ . '/../app/config/database.php';

// Optional filter: ?email=test@example.com
$email_filter = isset($_GET['email']) ? trim((string)$_GET['email']) : '';
$where_email = '';
if ($email_filter !== '') {
    $where_email = " AND w.f_user_email='" . mysqli_real_escape_string($connect, $email_filter) . "'";
}

// Wallet deductions that do not yet have a corresponding invoice
$query = "
    SELECT w.id, w.f_user_email, w.w_order_id, w.w_withdraw, w.uploaded_date
    FROM wallet w
    LEFT JOIN invoice_details i ON i.reference_number = CONCAT('WALLET-', w.id)
    WHERE w.w_withdraw < 0
      AND i.id IS NULL
      $where_email
    ORDER BY w.id ASC
";

$result = mysqli_query($connect, $query);
if (!$result) {
    die('Backfill query failed: ' . mysqli_error($connect));
}

$created = 0;
$skipped = 0;
$errors = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $wallet_id = (int)($row['id'] ?? 0);
    $franchisee_email = (string)($row['f_user_email'] ?? '');
    $card_id = is_numeric($row['w_order_id'] ?? null) ? (int)$row['w_order_id'] : 0;
    $withdraw_amount = (float)($row['w_withdraw'] ?? 0);
    $final_total = round(abs($withdraw_amount), 2);

    if ($wallet_id < 1 || $final_total <= 0 || $franchisee_email === '') {
        $skipped++;
        continue;
    }

    $reference_number = 'WALLET-' . $wallet_id;

    // Safety check (idempotent)
    $exists_q = mysqli_query(
        $connect,
        "SELECT id FROM invoice_details WHERE reference_number='" . mysqli_real_escape_string($connect, $reference_number) . "' LIMIT 1"
    );
    if ($exists_q && mysqli_num_rows($exists_q) > 0) {
        $skipped++;
        continue;
    }

    // Derive base and GST from tax-inclusive final amount (18% IGST)
    $original_amount = round($final_total / 1.18, 2);
    $discount_amount = 0.00;
    $subtotal_amount = $original_amount;
    $igst_amount = round($final_total - $original_amount, 2);
    $cgst_amount = 0.00;
    $sgst_amount = 0.00;
    $gst_percentage = 18;

    // Try to get customer info from card first
    $customer_email = '';
    $customer_name = '';
    $customer_contact = '';
    if ($card_id > 0) {
        $card_q = mysqli_query(
            $connect,
            "SELECT user_email FROM digi_card WHERE id='" . mysqli_real_escape_string($connect, (string)$card_id) . "' LIMIT 1"
        );
        if ($card_q && mysqli_num_rows($card_q) > 0) {
            $card_row = mysqli_fetch_assoc($card_q);
            $customer_email = trim((string)($card_row['user_email'] ?? ''));
        }
    }

    if ($customer_email !== '') {
        $safe_customer_email = mysqli_real_escape_string($connect, strtolower(trim($customer_email)));
        $ud_q = mysqli_query(
            $connect,
            "SELECT name, phone FROM user_details WHERE LOWER(TRIM(email))='$safe_customer_email' LIMIT 1"
        );
        if ($ud_q && mysqli_num_rows($ud_q) > 0) {
            $ud_row = mysqli_fetch_assoc($ud_q);
            $customer_name = (string)($ud_row['name'] ?? '');
            $customer_contact = (string)($ud_row['phone'] ?? '');
        }
    }

    // Fallback to franchisee identity when customer is not traceable
    if ($customer_email === '') {
        $customer_email = $franchisee_email;
    }
    if ($customer_name === '') {
        $customer_name = 'Wallet Customer';
    }

    // Build next invoice number
    $last_invoice_query = mysqli_query(
        $connect,
        "SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '/', -1) AS UNSIGNED)) as last_number
         FROM invoice_details WHERE invoice_number LIKE 'KIR/%'"
    );
    $last_invoice_result = $last_invoice_query ? mysqli_fetch_assoc($last_invoice_query) : null;
    $next_number = (int)($last_invoice_result['last_number'] ?? 0) + 1;
    $invoice_number = 'KIR/' . str_pad((string)$next_number, 5, '0', STR_PAD_LEFT);
    $invoice_date = date('Y-m-d', strtotime((string)$row['uploaded_date']));
    $current_timestamp = date('Y-m-d H:i:s');

    $invoice_insert_query = "INSERT INTO invoice_details (
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
        '" . mysqli_real_escape_string($connect, (string)$card_id) . "',
        '" . mysqli_real_escape_string($connect, $customer_email) . "',
        '" . mysqli_real_escape_string($connect, $customer_name) . "',
        '" . mysqli_real_escape_string($connect, $customer_contact) . "',
        '" . mysqli_real_escape_string($connect, $customer_name) . "',
        '" . mysqli_real_escape_string($connect, $customer_email) . "',
        '" . mysqli_real_escape_string($connect, $customer_contact) . "',
        '',
        '',
        '',
        '',
        '',
        '" . mysqli_real_escape_string($connect, (string)$original_amount) . "',
        '" . mysqli_real_escape_string($connect, (string)$discount_amount) . "',
        '" . mysqli_real_escape_string($connect, (string)$final_total) . "',
        '',
        '0',
        '',
        '',
        '',
        'Success',
        '" . mysqli_real_escape_string($connect, $current_timestamp) . "',
        'Mini Website Creation',
        'Mini Website Creation (Wallet)',
        '998314',
        '1',
        '" . mysqli_real_escape_string($connect, (string)$final_total) . "',
        '" . mysqli_real_escape_string($connect, (string)$final_total) . "',
        '" . mysqli_real_escape_string($connect, (string)$subtotal_amount) . "',
        '" . mysqli_real_escape_string($connect, (string)$gst_percentage) . "',
        '" . mysqli_real_escape_string($connect, (string)$igst_amount) . "',
        '" . mysqli_real_escape_string($connect, (string)$cgst_amount) . "',
        '" . mysqli_real_escape_string($connect, (string)$sgst_amount) . "',
        '" . mysqli_real_escape_string($connect, (string)$final_total) . "',
        'Wallet',
        '" . mysqli_real_escape_string($connect, $reference_number) . "',
        '" . mysqli_real_escape_string($connect, $current_timestamp) . "',
        '" . mysqli_real_escape_string($connect, $current_timestamp) . "'
    )";

    $insert_ok = mysqli_query($connect, $invoice_insert_query);
    if ($insert_ok) {
        $created++;
    } else {
        $errors++;
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "Wallet invoice backfill complete\n";
echo "Created: $created\n";
echo "Skipped: $skipped\n";
echo "Errors: $errors\n";
if ($email_filter !== '') {
    echo "Filtered email: $email_filter\n";
}

