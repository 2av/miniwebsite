<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
if(file_exists('connect.php')) {
    require('connect.php');
} else {
    // Fallback connection if connect.php doesn't exist
    if($_SERVER['HTTP_HOST'] == "test.miniwebsite.in") {
        $connect = mysqli_connect("localhost", "miniweb_vcard_test", "miniweb_vcard_test", "miniweb_vcard_test") or die('Database not available...');
    } elseif($_SERVER['HTTP_HOST'] == "localhost") {
        $connect = mysqli_connect("localhost", "root", "", "digicard") or die('Database not available...');
    } else {
        // Use the correct database credentials for your server
        $connect = mysqli_connect("localhost", "miniweb_vcard", "miniweb_vcard", "miniweb_vcard") or die('Connection issue #567844 Error');
    }
}

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    die('No contact ID provided');
}

// Sanitize the ID parameter to prevent SQL injection
$card_id = mysqli_real_escape_string($connect, $_GET['id']);

// Get card details
$query = mysqli_query($connect, "SELECT * FROM digi_card WHERE id='$card_id'");

if(mysqli_num_rows($query) == 0) {
    die('Contact not found');
}

$row = mysqli_fetch_array($query);

// Set headers for vCard download
header('Content-Type: text/vcard');
header('Content-Disposition: attachment; filename="' . ($row['d_f_name'] ?? 'contact') . '_' . ($row['d_l_name'] ?? 'card') . '.vcf"');

// Create vCard content
$vcard_content = "BEGIN:VCARD\r\n";
$vcard_content .= "VERSION:3.0\r\n";
$vcard_content .= "PRODID:-//MiniWebsite//" . $_SERVER['HTTP_HOST'] . "//EN\r\n";
$vcard_content .= "N:" . ($row['d_l_name'] ?? '') . ";" . ($row['d_f_name'] ?? '') . ";;;\r\n";
$vcard_content .= "FN:" . ($row['d_f_name'] ?? '') . " " . ($row['d_l_name'] ?? '') . "\r\n";
$vcard_content .= "ORG:" . ($row['d_comp_name'] ?? '') . "\r\n";
$vcard_content .= "TITLE:" . ($row['d_position'] ?? $row['d_comp_name']) . "\r\n";

// Add email if available
if(!empty($row['d_email'])) {
    $vcard_content .= "EMAIL;TYPE=INTERNET,WORK,pref:" . $row['d_email'] . "\r\n";
}

// Add phone numbers if available
if(!empty($row['d_contact'])) {
    $vcard_content .= "TEL;TYPE=WORK,voice,pref:" . $row['d_contact'] . "\r\n";
}
if(!empty($row['d_contact2'])) {
    $vcard_content .= "TEL;TYPE=CELL:" . $row['d_contact2'] . "\r\n";
}
if(!empty($row['d_whatsapp'])) {
    $vcard_content .= "TEL;TYPE=HOME:" . $row['d_whatsapp'] . "\r\n";
}

// Add website if available
if(!empty($row['d_website'])) {
    $vcard_content .= "URL;TYPE=pref:https://" . $row['d_website'] . "\r\n";
}

// Add card URL
$vcard_content .= "URL;TYPE=WORK:https://" . $_SERVER['HTTP_HOST'] . "/" . $row['card_id'] . "\r\n";

// Add address if available
if(!empty($row['d_address'])) {
    $vcard_content .= "ADR;TYPE=WORK:;;" . str_replace("\r\n", " ", $row['d_address']) . ";;;;\r\n";
}

// Add photo if available
if(!empty($row['d_logo'])) {
    $photo = base64_encode($row['d_logo']);
    $vcard_content .= "PHOTO;ENCODING=b;TYPE=JPEG:" . $photo . "\r\n";
}

$vcard_content .= "END:VCARD\r\n";

// Output the vCard content
echo $vcard_content;
exit;

