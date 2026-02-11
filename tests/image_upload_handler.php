<?php
/**
 * Image Upload Handler
 * Handles image upload, cropping, and optimization to 512x512
 */

header('Content-Type: application/json');

// Check if GD extension is available
if (!extension_loaded('gd')) {
    echo json_encode([
        'success' => false,
        'message' => 'GD library is not available. Please enable GD extension in PHP.'
    ]);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'No image file uploaded or upload error occurred.'
    ]);
    exit;
}

$file = $_FILES['image'];

// Validate file type
$allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];
$fileType = mime_content_type($file['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file type. Only PNG, JPG, JPEG, and GIF are allowed.'
    ]);
    exit;
}

// Validate file size (10MB max)
$maxSize = 10 * 1024 * 1024; // 10MB
if ($file['size'] > $maxSize) {
    echo json_encode([
        'success' => false,
        'message' => 'File size exceeds 10MB limit.'
    ]);
    exit;
}

try {
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/uploaded_images/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Get image info
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception('Invalid image file.');
    }
    
    $mime = $imageInfo['mime'];
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    
    // Create image resource based on mime type
    $sourceImage = null;
    switch ($mime) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($file['tmp_name']);
            break;
        default:
            throw new Exception('Unsupported image type: ' . $mime);
    }
    
    if (!$sourceImage) {
        throw new Exception('Failed to create image resource.');
    }
    
    // Create optimized image (512x512)
    $targetWidth = 512;
    $targetHeight = 512;
    
    // Create true color image
    $optimizedImage = imagecreatetruecolor($targetWidth, $targetHeight);
    
    // Preserve transparency for PNG and GIF
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($optimizedImage, false);
        imagesavealpha($optimizedImage, true);
        $transparent = imagecolorallocatealpha($optimizedImage, 255, 255, 255, 127);
        imagefilledrectangle($optimizedImage, 0, 0, $targetWidth, $targetHeight, $transparent);
    }
    
    // Resize image with high quality
    imagecopyresampled(
        $optimizedImage,
        $sourceImage,
        0, 0, 0, 0,
        $targetWidth,
        $targetHeight,
        $originalWidth,
        $originalHeight
    );
    
    // Generate unique filename
    $filename = uniqid('img_', true) . '_' . time() . '.png';
    $filepath = $uploadDir . $filename;
    
    // Save optimized image as PNG (best quality and supports transparency)
    $quality = 9; // PNG compression level (0-9, 9 = maximum compression)
    if (!imagepng($optimizedImage, $filepath, $quality)) {
        throw new Exception('Failed to save optimized image.');
    }
    
    // Get file size
    $fileSize = filesize($filepath);
    
    // Clean up memory
    imagedestroy($sourceImage);
    imagedestroy($optimizedImage);
    
    // Generate URL
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
               '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    $imageUrl = $baseUrl . '/uploaded_images/' . $filename;
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded and optimized successfully.',
        'image_url' => $imageUrl,
        'file_path' => $filepath,
        'file_size' => $fileSize,
        'dimensions' => [
            'width' => $targetWidth,
            'height' => $targetHeight
        ],
        'original_dimensions' => [
            'width' => $originalWidth,
            'height' => $originalHeight
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error processing image: ' . $e->getMessage()
    ]);
}
?>

