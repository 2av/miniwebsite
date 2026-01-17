<?php
// This script handles QR code downloads and forces the browser to download the file

// Check if URL parameter is set
if (isset($_GET['url']) && !empty($_GET['url'])) {
    $url = urldecode($_GET['url']);
    $filename = isset($_GET['filename']) ? $_GET['filename'] : 'qr-code.png';

    // Validate URL (only allow QR code API URLs)
    if (strpos($url, 'api.qrserver.com') === false) {
        die('Invalid URL');
    }

    // Get the image content
    $imageContent = file_get_contents($url);

    if ($imageContent !== false) {
        // Clear any previous output that might prevent download
        ob_clean();

        // Set headers to force download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream'); // Force download
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . strlen($imageContent));

        // Disable output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Output the image content
        echo $imageContent;
        exit;
    } else {
        die('Failed to download QR code');
    }
} else {
    die('No URL specified');
}
?>
