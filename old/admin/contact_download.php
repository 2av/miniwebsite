<?php
require('../config.php');
$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_GET['id'].'" ');

$row=mysqli_fetch_array($query);

header('Content-Type: text/vcard');  
header('Content-Disposition: attachment; filename="' . $row['d_f_name'] . '_' . $row['d_l_name'] . '.vcf"');  

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

$vcard_content .= "END:VCARD\r\n";

// Output the vCard content
echo $vcard_content;
exit;
?>
