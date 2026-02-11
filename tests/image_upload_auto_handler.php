<?php
/**
 * Automatic Image Upload Handler
 * Handles automatic 1:1 center crop, resize, and compression
 * Supports Intervention Image library with GD fallback
 */

header('Content-Type: application/json');

// Check library status
if (isset($_GET['action']) && $_GET['action'] === 'check_libraries') {
    $status = [
        'intervention_image' => false,
        'gd_library' => extension_loaded('gd'),
        'imagick' => extension_loaded('imagick'),
        'webp_support' => false
    ];
    
    // Check if Intervention Image is available
    try {
        if (class_exists('Intervention\Image\ImageManager') || 
            class_exists('Intervention\Image\ImageManagerStatic')) {
            $status['intervention_image'] = true;
        } elseif (file_exists(__DIR__ . '/../vendor/intervention/image/src/Intervention/Image/ImageManager.php')) {
            if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
                require_once __DIR__ . '/../vendor/autoload.php';
            }
            if (class_exists('Intervention\Image\ImageManager') || 
                class_exists('Intervention\Image\ImageManagerStatic')) {
                $status['intervention_image'] = true;
            }
        }
    } catch (Exception $e) {
        // Intervention Image not available
    }
    
    // Check WebP support
    if ($status['gd_library']) {
        $gdInfo = gd_info();
        $status['webp_support'] = isset($gdInfo['WebP Support']) && $gdInfo['WebP Support'];
    }
    
    echo json_encode($status);
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
$allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
$fileType = mime_content_type($file['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file type. Only PNG, JPG, JPEG, GIF, and WEBP are allowed.'
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
    // Get original image info
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception('Invalid image file.');
    }
    
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    $originalSize = $file['size'];
    
    // Target dimensions (600x600 is the sweet spot for mobile)
    $targetSize = 600; // Target size: 600x600
    $targetFileSize = 250 * 1024; // Target file size: 250KB
    $minFileSize = 200 * 1024; // Minimum file size: 200KB (allow some flexibility)
    $maxFileSize = 300 * 1024; // Maximum file size: 300KB (don't go too large)
    $currentTargetSize = $targetSize;
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/uploaded_images/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $filename = uniqid('auto_', true) . '_' . time() . '.jpg';
    $filepath = $uploadDir . $filename;
    
    // Try to use Intervention Image first
    $useIntervention = false;
    try {
        // Check if Intervention Image is available
        if (class_exists('Intervention\Image\ImageManager') || 
            class_exists('Intervention\Image\ImageManagerStatic')) {
            $useIntervention = true;
        } elseif (file_exists(__DIR__ . '/../vendor/intervention/image/src/Intervention/Image/ImageManager.php')) {
            if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
                require_once __DIR__ . '/../vendor/autoload.php';
            }
            if (class_exists('Intervention\Image\ImageManager') || 
                class_exists('Intervention\Image\ImageManagerStatic')) {
                $useIntervention = true;
            }
        }
    } catch (Exception $e) {
        // Intervention Image not available, will use GD
    }
    
    if ($useIntervention) {
        // Use Intervention Image (clean and simple)
        try {
            // Try different ways to load Intervention Image
            if (class_exists('Intervention\Image\ImageManagerStatic')) {
                $img = Intervention\Image\ImageManagerStatic::make($file['tmp_name']);
            } elseif (class_exists('Intervention\Image\ImageManager')) {
                $manager = new Intervention\Image\ImageManager(['driver' => 'gd']);
                $img = $manager->make($file['tmp_name']);
            } else {
                throw new Exception('Intervention Image class not found');
            }
            
            // Fit to 1:1 ratio (crops from center and resizes to target size - 600x600)
            $img->fit($targetSize, $targetSize);
            
            // Try to find the right quality to hit target file size (~250KB) at 600x600
            $quality = 75;
            $bestQuality = $quality;
            $bestSize = 0;
            $attempts = 0;
            $maxAttempts = 15; // Try quality levels from 60 to 100
            
            // Binary search approach: find quality that gives us closest to 250KB
            $lowQuality = 60;
            $highQuality = 100;
            
            while ($attempts < $maxAttempts) {
                $quality = round(($lowQuality + $highQuality) / 2);
                $img->save($filepath, $quality);
                clearstatcache(true, $filepath);
                $fileSize = filesize($filepath);
                
                if ($fileSize >= $minFileSize && $fileSize <= $maxFileSize) {
                    // Perfect! We're in the target range
                    $bestQuality = $quality;
                    $bestSize = $fileSize;
                    break;
                } elseif ($fileSize < $minFileSize) {
                    // Too small, increase quality
                    $lowQuality = $quality + 1;
                    $bestQuality = $quality;
                    $bestSize = $fileSize;
                } else {
                    // Too large, decrease quality
                    $highQuality = $quality - 1;
                    if ($bestSize == 0 || abs($fileSize - $targetFileSize) < abs($bestSize - $targetFileSize)) {
                        $bestQuality = $quality;
                        $bestSize = $fileSize;
                    }
                }
                
                $attempts++;
                
                if ($lowQuality > $highQuality) {
                    break;
                }
            }
            
            // Save with best quality found
            $img->save($filepath, $bestQuality);
            clearstatcache(true, $filepath);
            $fileSize = filesize($filepath);
            $quality = $bestQuality;
            
            // Only increase dimensions slightly if still too small (max 800x800)
            if ($fileSize < $minFileSize && $currentTargetSize < 800) {
                $currentTargetSize = 800; // Slight increase to 800x800 max
                $img->fit($currentTargetSize, $currentTargetSize);
                // Try quality around 85-90 for 800x800
                $img->save($filepath, 85);
                clearstatcache(true, $filepath);
                $fileSize = filesize($filepath);
                
                // Adjust quality if needed
                if ($fileSize < $minFileSize) {
                    $img->save($filepath, 90);
                    clearstatcache(true, $filepath);
                    $fileSize = filesize($filepath);
                    $quality = 90;
                } else {
                    $quality = 85;
                }
            }
            
            $finalWidth = $currentTargetSize;
            $finalHeight = $currentTargetSize;
            $format = 'JPEG';
            $qualityUsed = isset($quality) ? $quality : 100;
        } catch (Exception $e) {
            // If Intervention Image fails, fall back to GD
            $useIntervention = false;
        }
    }
    
    if (!$useIntervention) {
        // Fallback to GD library
        if (!extension_loaded('gd')) {
            throw new Exception('GD library is not available. Please enable GD extension in PHP.');
        }
        
        $mime = $imageInfo['mime'];
        
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
            case 'image/webp':
                // Check if WebP is supported
                if (!function_exists('imagecreatefromwebp')) {
                    throw new Exception('WebP support is not available in your PHP installation. Please enable WebP support in GD library.');
                }
                $sourceImage = imagecreatefromwebp($file['tmp_name']);
                break;
            default:
                throw new Exception('Unsupported image type: ' . $mime);
        }
        
        if (!$sourceImage) {
            throw new Exception('Failed to create image resource.');
        }
        
        // Calculate center crop dimensions (1:1 ratio)
        $minDimension = min($originalWidth, $originalHeight);
        
        // Calculate crop coordinates (center crop)
        $cropX = ($originalWidth - $minDimension) / 2;
        $cropY = ($originalHeight - $minDimension) / 2;
        
        // Create target image
        $targetImage = imagecreatetruecolor($currentTargetSize, $currentTargetSize);
        
        // Preserve transparency for PNG
        if ($mime == 'image/png') {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
            imagefilledrectangle($targetImage, 0, 0, $currentTargetSize, $currentTargetSize, $transparent);
        }
        
        // Crop and resize in one step
        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0, 0,                    // Destination x, y
            $cropX, $cropY,         // Source x, y (center crop)
            $currentTargetSize, $currentTargetSize, // Destination width, height
            $minDimension, $minDimension // Source width, height (square crop)
        );
        
        // Try to find the right quality to hit target file size (~250KB) at 600x600
        $quality = 75;
        $bestQuality = $quality;
        $bestSize = 0;
        $attempts = 0;
        $maxAttempts = 15;
        
        // Binary search approach: find quality that gives us closest to 250KB
        $lowQuality = 60;
        $highQuality = 100;
        
        while ($attempts < $maxAttempts) {
            $quality = round(($lowQuality + $highQuality) / 2);
            imagejpeg($targetImage, $filepath, $quality);
            clearstatcache(true, $filepath);
            $fileSize = filesize($filepath);
            
            if ($fileSize >= $minFileSize && $fileSize <= $maxFileSize) {
                // Perfect! We're in the target range
                $bestQuality = $quality;
                $bestSize = $fileSize;
                break;
            } elseif ($fileSize < $minFileSize) {
                // Too small, increase quality
                $lowQuality = $quality + 1;
                $bestQuality = $quality;
                $bestSize = $fileSize;
            } else {
                // Too large, decrease quality
                $highQuality = $quality - 1;
                if ($bestSize == 0 || abs($fileSize - $targetFileSize) < abs($bestSize - $targetFileSize)) {
                    $bestQuality = $quality;
                    $bestSize = $fileSize;
                }
            }
            
            $attempts++;
            
            if ($lowQuality > $highQuality) {
                break;
            }
        }
        
        // Save with best quality found
        imagejpeg($targetImage, $filepath, $bestQuality);
        clearstatcache(true, $filepath);
        $fileSize = filesize($filepath);
        $quality = $bestQuality;
        
        // Only increase dimensions slightly if still too small (max 800x800)
        if ($fileSize < $minFileSize && $currentTargetSize < 800) {
            // Clean up current image
            imagedestroy($targetImage);
            
            // Increase to 800x800 max
            $currentTargetSize = 800;
            $targetImage = imagecreatetruecolor($currentTargetSize, $currentTargetSize);
            
            // Preserve transparency for PNG
            if ($mime == 'image/png') {
                imagealphablending($targetImage, false);
                imagesavealpha($targetImage, true);
                $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
                imagefilledrectangle($targetImage, 0, 0, $currentTargetSize, $currentTargetSize, $transparent);
            }
            
            // Resize again with 800x800
            imagecopyresampled(
                $targetImage,
                $sourceImage,
                0, 0,
                $cropX, $cropY,
                $currentTargetSize, $currentTargetSize,
                $minDimension, $minDimension
            );
            
            // Try quality around 85-90 for 800x800
            imagejpeg($targetImage, $filepath, 85);
            clearstatcache(true, $filepath);
            $fileSize = filesize($filepath);
            
            // Adjust quality if needed
            if ($fileSize < $minFileSize) {
                imagejpeg($targetImage, $filepath, 90);
                clearstatcache(true, $filepath);
                $fileSize = filesize($filepath);
                $quality = 90;
            } else {
                $quality = 85;
            }
        }
        
        // Clear cache and ensure file is written before cleanup
        clearstatcache(true, $filepath);
        usleep(50000); // Small delay to ensure file is written
        
        // Clean up memory
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        
        $finalWidth = $currentTargetSize;
        $finalHeight = $currentTargetSize;
        $format = 'JPEG';
        
        // Store quality for response
        $qualityUsed = isset($quality) ? $quality : 100;
    }
    
    // Generate URL
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
               '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    $imageUrl = $baseUrl . '/uploaded_images/' . $filename;
    
    // Ensure quality variable is set
    if (!isset($qualityUsed)) {
        $qualityUsed = 100; // Default to maximum if not set
    }
    
    // Clear stat cache and get final file size (ensure we read the actual saved file)
    clearstatcache(true, $filepath);
    
    // Wait a moment to ensure file is fully written
    usleep(100000); // 0.1 second delay
    
    // Get final file size - read it fresh
    $finalSize = filesize($filepath);
    
    // Double-check: if file doesn't exist or size seems wrong, read again
    if (!file_exists($filepath) || $finalSize === false) {
        clearstatcache(true, $filepath);
        $finalSize = filesize($filepath);
    }
    
    // Final verification - ensure file exists and get accurate size
    if (!file_exists($filepath)) {
        throw new Exception('Processed image file was not created.');
    }
    
    // One more read with fresh cache clear
    clearstatcache(true, $filepath);
    $finalSize = filesize($filepath);
    
    // Verify the size is accurate (should be > 0)
    if ($finalSize === false || $finalSize <= 0) {
        throw new Exception('Unable to determine final file size.');
    }
    
    // Build message
    $message = 'Image processed successfully.';
    if ($finalSize >= $minFileSize) {
        $message .= ' Quality optimized to meet 250KB minimum requirement.';
    } else {
        $message .= ' Note: Image is smaller than 250KB (may be due to simple image content).';
    }
    
    // Return success response with accurate file size
    echo json_encode([
        'success' => true,
        'message' => $message,
        'image_url' => $imageUrl,
        'file_path' => $filepath,
        'file_size' => $finalSize, // This should now be accurate
        'original_size' => $originalSize,
        'dimensions' => [
            'width' => $finalWidth,
            'height' => $finalHeight
        ],
        'original_dimensions' => [
            'width' => $originalWidth,
            'height' => $originalHeight
        ],
        'format' => $format,
        'library_used' => $useIntervention ? 'Intervention Image' : 'GD Library',
        'quality_used' => $qualityUsed,
        'meets_minimum' => $finalSize >= $minFileSize,
        'file_size_kb' => round($finalSize / 1024, 2),
        'file_size_mb' => round($finalSize / (1024 * 1024), 2)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error processing image: ' . $e->getMessage()
    ]);
}
?>

