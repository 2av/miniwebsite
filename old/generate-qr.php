<?php
// This script generates and forces download of a QR code in one step

// Check if card_id parameter is set
if (isset($_GET['card_id']) && !empty($_GET['card_id'])) {
    $card_id = $_GET['card_id'];
    $filename = 'QR_Code_' . $card_id . '.png';

    // Create the QR code URL with clean URL format
    $qrCodeUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $card_id;
    $encodedUrl = urlencode($qrCodeUrl);

    // QR code API URL
    $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . $encodedUrl;

    // Get the image content
    $imageContent = file_get_contents($qrApiUrl);

    if ($imageContent !== false) {
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers to force download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . strlen($imageContent));

        // Output the image content
        echo $imageContent;
        exit;
    } else {
        die('Failed to generate QR code');
    }
} else {
    die('No card ID specified');
}
?>
