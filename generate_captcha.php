<?php
session_start();
header('Content-Type: image/png');

// Create image with larger dimensions
$width = 150;
$height = 50;
$image = imagecreatetruecolor($width, $height);

// Define colors
$bg_color = imagecolorallocate($image, 255, 255, 255); // White background
$text_color = imagecolorallocate($image, 51, 51, 51); // Dark gray text
$noise_color = imagecolorallocate($image, 100, 120, 180); // Blue noise

// Fill background
imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);

// Add random lines
for($i = 0; $i < 5; $i++) {
    imageline(
        $image,
        rand(0, $width), rand(0, $height),
        rand(0, $width), rand(0, $height),
        $noise_color
    );
}

// Add random dots
for($i = 0; $i < 50; $i++) {
    imagesetpixel($image, rand(0, $width), rand(0, $height), $noise_color);
}

// Generate random string
$captcha = substr(str_shuffle("23456789ABCDEFGHJKLMNPQRSTUVWXYZ"), 0, 6); // Removed confusing characters
$_SESSION['captcha'] = $captcha;

// Add text to image
$font_size = 24;
$angle = rand(-5, 5);
// Using built-in font since TTF might not be available
imagestring($image, 5, 30, 15, $captcha, $text_color);

// Output image
imagepng($image);
imagedestroy($image);
exit();
?>



