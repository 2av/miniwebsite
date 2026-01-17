<?php
// Database connection
if($_SERVER['HTTP_HOST']=="localhost"){
    $connect=mysqli_connect("localhost","onbvn_card","onbvn_card","onbvn_card") or die ('Database not available...');
} else {
    $connect=mysqli_connect("localhost","onbvn_card","onbvn_card","onbvn_card") or die ('Connection issue #567844 Error');
}

// Check if ID is provided and sanitize
if(!isset($_GET['id']) || empty($_GET['id'])) {
    die('No contact ID provided');
}

$card_id = mysqli_real_escape_string($connect, $_GET['id']);
$query = mysqli_query($connect, "SELECT * FROM digi_card WHERE id='$card_id'");

if(mysqli_num_rows($query) == 0) {
    die('Contact not found');
}

$row = mysqli_fetch_array($query);

// Create clean filename
$filename = preg_replace('/[^a-z0-9_\-\.]/i', '_', ($row['d_f_name'] ?? 'contact') . '_' . ($row['d_l_name'] ?? 'card')) . '.vcf';

// Set proper headers for vCard download
header('Content-Type: text/vcard; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

// Create vCard content
$vcard_content = "BEGIN:VCARD\r\n";
$vcard_content .= "VERSION:3.0\r\n";
$vcard_content .= "PRODID:-//MiniWebsite//" . $_SERVER['HTTP_HOST'] . "//EN\r\n";
$vcard_content .= "N:" . ($row['d_l_name'] ?? '') . ";" . ($row['d_f_name'] ?? '') . ";;;\r\n";
$vcard_content .= "FN:" . ($row['d_f_name'] ?? '') . " " . ($row['d_l_name'] ?? '') . "\r\n";
$vcard_content .= "ORG:" . ($row['d_comp_name'] ?? '') . "\r\n";
$vcard_content .= "TITLE:" . ($row['d_position'] ?? $row['d_comp_name']) . "\r\n";

if(!empty($row['d_email'])) {
    $vcard_content .= "EMAIL;TYPE=INTERNET,WORK,pref:" . $row['d_email'] . "\r\n";
}

if(!empty($row['d_contact'])) {
    $vcard_content .= "TEL;TYPE=WORK,voice,pref:" . $row['d_contact'] . "\r\n";
}
if(!empty($row['d_contact2'])) {
    $vcard_content .= "TEL;TYPE=CELL:" . $row['d_contact2'] . "\r\n";
}
if(!empty($row['d_whatsapp'])) {
    $vcard_content .= "TEL;TYPE=HOME:" . $row['d_whatsapp'] . "\r\n";
}

if(!empty($row['d_website'])) {
    $vcard_content .= "URL;TYPE=pref:https://" . $row['d_website'] . "\r\n";
}

$vcard_content .= "URL;TYPE=WORK:https://" . $_SERVER['HTTP_HOST'] . "/" . $row['card_id'] . "\r\n";

if(!empty($row['d_address'])) {
    $vcard_content .= "ADR;TYPE=WORK:;;" . str_replace("\r\n", " ", $row['d_address']) . ";;;;\r\n";
}

$vcard_content .= "END:VCARD\r\n";

// Set content length and output
header('Content-Length: ' . strlen($vcard_content));
echo $vcard_content;
exit;
?>
BEGIN:VCARD
VERSION:2.1
PRODID:-//MiniWebsite//<?php echo $_SERVER['HTTP_HOST']; ?>//EN
N:<?php echo $row['d_l_name']; ?>;<?php echo $row['d_f_name']; ?>;;;
FN:<?php echo $row['d_f_name'].$row['d_l_name']; ?> 
ORG:<?php echo $row['d_comp_name']; ?>;
TITLE:<?php echo $row['d_comp_name']; ?> 
EMAIL;type=INTERNET;type=WORK;type=pref:<?php echo $row['d_email']; ?> 
TEL;type=WORK;type=pref:<?php echo $row['d_contact']; ?> 
TEL;type=CELL:<?php echo $row['d_contact2']; ?> 
TEL;type=HOME:<?php echo $row['d_whatsapp']; ?> 
item3.URL;type=pref:http://<?php echo $row['d_website']; ?> 

END:VCARD

