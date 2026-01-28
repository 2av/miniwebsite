<?php
/**
 * File Validation Helper Functions
 * 
 * This file contains functions to validate file uploads with size and type restrictions
 */

/**
 * Check if GD library is available and get detailed information
 * 
 * @param bool $detailed If true, returns detailed information about GD capabilities
 * @return array|bool Returns array with detailed info if $detailed is true, otherwise returns boolean
 */
function checkGDLibrary($detailed = false) {
    $gdInfo = [
        'available' => false,
        'extension_loaded' => false,
        'functions_available' => [],
        'supported_formats' => [],
        'version' => null,
        'info' => []
    ];
    
    // Check if extension is loaded
    $gdInfo['extension_loaded'] = extension_loaded('gd');
    
    if($gdInfo['extension_loaded']) {
        $gdInfo['available'] = true;
        
        // Get GD info
        if(function_exists('gd_info')) {
            $gdInfo['info'] = gd_info();
            $gdInfo['version'] = isset($gdInfo['info']['GD Version']) ? $gdInfo['info']['GD Version'] : 'Unknown';
        }
        
        // Check for essential functions
        $essentialFunctions = [
            'imagecreatefromjpeg' => 'JPEG Support',
            'imagecreatefrompng' => 'PNG Support',
            'imagecreatefromgif' => 'GIF Support',
            'imagejpeg' => 'JPEG Output',
            'imagepng' => 'PNG Output',
            'imagegif' => 'GIF Output',
            'imagecreatetruecolor' => 'True Color Support',
            'imagecopyresampled' => 'Image Resizing',
            'imagesx' => 'Image Dimensions',
            'imagesy' => 'Image Dimensions'
        ];
        
        foreach($essentialFunctions as $func => $description) {
            if(function_exists($func)) {
                $gdInfo['functions_available'][$func] = $description;
            }
        }
        
        // Determine supported formats
        if(function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
            $gdInfo['supported_formats'][] = 'JPEG';
        }
        if(function_exists('imagecreatefrompng') && function_exists('imagepng')) {
            $gdInfo['supported_formats'][] = 'PNG';
        }
        if(function_exists('imagecreatefromgif') && function_exists('imagegif')) {
            $gdInfo['supported_formats'][] = 'GIF';
        }
        if(function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
            $gdInfo['supported_formats'][] = 'WebP';
        }
    }
    
    // Return simple boolean or detailed array
    if($detailed) {
        return $gdInfo;
    }
    
    return $gdInfo['available'];
}

/**
 * Validates if a file is within size limit and of allowed type
 * 
 * @param array $file The $_FILES array element
 * @param int $maxSize Maximum file size in bytes (default 250KB)
 * @param array $allowedTypes Array of allowed file extensions
 * @return array Result with status and message
 */
function validateFile($file, $maxSize = 250000, $allowedTypes = ['png', 'jpeg', 'jpg', 'gif']) {
    $result = [
        'status' => false,
        'message' => ''
    ];
    
    // Check if file is uploaded
    if(empty($file['tmp_name']) || $file['error'] != 0) {
        $result['message'] = 'No file uploaded or upload error occurred';
        return $result;
    }
    
    // Check file type
    $filename = $file['name'];
    $imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if(!in_array($imageFileType, $allowedTypes)) {
        $result['message'] = '<div class="alert danger">Only ' . implode(', ', $allowedTypes) . ' files allowed</div>';
        return $result;
    }
    
    // Check file size
    if($file['size'] > $maxSize) {
        $result['message'] = '<div class="alert danger">File size exceeds ' . ($maxSize/1000) . 'KB limit. Please resize your image.</div>';
        return $result;
    }
    
    // All validations passed
    $result['status'] = true;
    return $result;
}

/**
 * Validates if a file is an image and of allowed type
 * 
 * @param array $file The $_FILES array element
 * @param int $maxSize Maximum file size in bytes (default 250KB)
 * @return array Result with status and message
 */
function validateImageFile($file, $maxSize = 250000) {
    // Only allow image file types
    $allowedTypes = ['jpg', 'jpeg', 'png'];
    
    $result = validateFile($file, $maxSize, $allowedTypes);
    
    // Additional check for image files
    if($result['status']) {
        // Verify it's actually an image
        $check = getimagesize($file['tmp_name']);
        if($check === false) {
            $result['status'] = false;
            $result['message'] = '<div class="alert danger">File is not a valid image</div>';
        }
    }
    
    return $result;
}

/**
 * Process image upload with validation
 * 
 * @param array $file The $_FILES array element
 * @param string $destination Destination path (optional)
 * @param int $maxSize Maximum file size in bytes (default 250KB)
 * @return array Result with status, message and file path
 */
function processImageUpload($file, $destination = null, $maxSize = 250000) {
    $result = [
        'status' => false,
        'message' => '',
        'path' => '',
        'data' => null
    ];
    
    // Validate file
    $validation = validateImageFile($file, $maxSize);
    if(!$validation['status']) {
        $result['message'] = $validation['message'];
        return $result;
    }
    
    try {
        // If destination is provided, move the file
        if($destination) {
            if(move_uploaded_file($file['tmp_name'], $destination)) {
                $result['status'] = true;
                $result['path'] = $destination;
            } else {
                $result['message'] = '<div class="alert danger">Failed to move uploaded file</div>';
                return $result;
            }
        }
        
        // Read the image data
        if(file_exists($file['tmp_name'])) {
            $imageData = addslashes(file_get_contents($file['tmp_name']));
            $result['status'] = true;
            $result['data'] = $imageData;
        } else if(!$destination) {
            $result['message'] = '<div class="alert danger">Image file not found</div>';
        }
    } catch (Exception $e) {
        $result['message'] = '<div class="alert danger">Error processing image: ' . $e->getMessage() . '</div>';
    }
    
    return $result;
}

/**
 * Compresses an image file
 * 
 * @param string $source Source file path
 * @param string $destination Destination file path
 * @param int $quality Compression quality (0-100)
 * @return string Path to compressed image
 */
function compressImage($source, $destination, $quality) {
    // Check if GD extension is available
    if(!extension_loaded('gd')) {
        // If GD is not available, just copy the file without compression
        if($source !== $destination) {
            if(!copy($source, $destination)) {
                throw new Exception("Failed to copy image file");
            }
        }
        return $destination;
    }
    
    // Check if file exists
    if(!file_exists($source)) {
        throw new Exception("Source file does not exist");
    }
    
    // Get image info
    $imageInfo = getimagesize($source);
    if($imageInfo === false) {
        throw new Exception("Invalid image file");
    }
    
    $mime = $imageInfo['mime'];
    
    // Create image resource based on mime type
    switch($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($source);
            break;
        default:
            throw new Exception("Unsupported image type: $mime");
    }
    
    // Check if image creation was successful
    if(!$image) {
        throw new Exception("Failed to create image resource");
    }
    
    // Preserve transparency for PNG images
    if($mime == 'image/png') {
        imageAlphaBlending($image, true);
        imageSaveAlpha($image, true);
    }
    
    // Create a temporary file for the compressed image
    $tempFile = tempnam(sys_get_temp_dir(), 'img_');
    
    // Compress and save the image
    $success = false;
    switch($mime) {
        case 'image/jpeg':
            $success = imagejpeg($image, $tempFile, $quality);
            break;
        case 'image/png':
            // PNG uses a different quality scale (0-9)
            $pngQuality = floor((100 - $quality) / 11.111);
            $success = imagepng($image, $tempFile, $pngQuality);
            break;
        case 'image/gif':
            $success = imagegif($image, $tempFile);
            break;
    }
    
    // Free memory
    imagedestroy($image);
    
    if(!$success) {
        unlink($tempFile);
        throw new Exception("Failed to save compressed image");
    }
    
    // Copy the temp file to the destination
    if($tempFile !== $destination) {
        copy($tempFile, $destination);
        unlink($tempFile);
    }
    
    return $destination;
}

/**
 * Process image upload with validation and compression
 * 
 * @param array $file The $_FILES array element
 * @param int $quality Compression quality (0-100)
 * @param int $maxSize Maximum file size in bytes (default 250KB)
 * @param array $allowedTypes Array of allowed file extensions
 * @return array Result with status, message and processed image data
 */
function processImageUploadWithCompression($file, $quality = 65, $maxSize = 250000, $allowedTypes = ['png', 'jpeg', 'jpg']) {
    $result = [
        'status' => false,
        'message' => '',
        'data' => null
    ];
    
    // Validate file
    $validation = validateFile($file, $maxSize, $allowedTypes);
    if(!$validation['status']) {
        $result['message'] = $validation['message'];
        return $result;
    }
    
    try {
        // Check if GD extension is available
        if(!extension_loaded('gd')) {
            // If GD is not available, process without compression
            $imageData = addslashes(file_get_contents($file['tmp_name']));
            $result['status'] = true;
            $result['data'] = $imageData;
            $result['message'] = '<div class="alert info">Image uploaded successfully (compression disabled - GD extension not available).</div>';
        } else {
            // Create a temporary file for compression
            $source = $file['tmp_name'];
            $destination = $file['tmp_name'];
            
            // Compress image
            $compressedImage = compressImage($source, $destination, $quality);
            
            // Read the compressed image data
            if(file_exists($compressedImage)) {
                $imageData = addslashes(file_get_contents($compressedImage));
                $result['status'] = true;
                $result['data'] = $imageData;
                $result['message'] = '<div class="alert info">Image uploaded and compressed successfully.</div>';
            } else {
                throw new Exception("Compressed image file not found");
            }
        }
    } catch (Exception $e) {
        $result['message'] = '<div class="alert danger">Error processing image: ' . $e->getMessage() . '</div>';
    }
    
    return $result;
}

/**
 * Resize and compress image to meet target file size
 * Automatically resizes dimensions and adjusts quality until file size is under target
 * This function ensures images are automatically resized to meet size requirements
 * 
 * @param string $source Source file path
 * @param string $destination Destination file path
 * @param int $maxSize Maximum file size in bytes (default 250000 = 250KB)
 * @param int $maxWidth Maximum width in pixels (default 2000)
 * @param int $maxHeight Maximum height in pixels (default 2000)
 * @return string Path to processed image
 * @throws Exception If image processing fails
 */
function resizeAndCompressImage($source, $destination, $maxSize = 250000, $maxWidth = 2000, $maxHeight = 2000) {
    // Check if GD library is available
    if(!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
        // GD library not available - return source file without processing
        if($source !== $destination && file_exists($source)) {
            copy($source, $destination);
        }
        return $destination;
    }
    
    $imageInfo = @getimagesize($source);
    if($imageInfo === false) {
        if($source !== $destination && file_exists($source)) {
            copy($source, $destination);
        }
        return $destination;
    }
    
    $mime = $imageInfo['mime'];
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    
    // Create image resource based on mime type
    $image = false;
    switch($mime){
        case 'image/jpeg':
            if(function_exists('imagecreatefromjpeg')) {
                $image = @imagecreatefromjpeg($source);
            }
            break;
        case 'image/png':
            if(function_exists('imagecreatefrompng')) {
                $image = @imagecreatefrompng($source);
            }
            break;
        case 'image/gif':
            if(function_exists('imagecreatefromgif')) {
                $image = @imagecreatefromgif($source);
            }
            break;
        default:
            if(function_exists('imagecreatefromjpeg')) {
                $image = @imagecreatefromjpeg($source);
            }
    }
    
    if($image === false) {
        if($source !== $destination && file_exists($source)) {
            copy($source, $destination);
        }
        return $destination;
    }
    
    // Preserve transparency for PNG images
    $isPng = ($mime == 'image/png');
    if($isPng) {
        imageAlphaBlending($image, false);
        imageSaveAlpha($image, true);
    }
    
    // Store original image for potential further resizing
    $originalImage = $image;
    
    // Calculate initial dimensions (maintain aspect ratio)
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight, 1);
    $newWidth = (int)($originalWidth * $ratio);
    $newHeight = (int)($originalHeight * $ratio);
    
    // If image is already small enough, just resize if needed
    if($originalWidth <= $maxWidth && $originalHeight <= $maxHeight && filesize($source) <= $maxSize) {
        // Still compress to optimize
        $newWidth = $originalWidth;
        $newHeight = $originalHeight;
    }
    
    // Create resized image
    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG
    if($isPng) {
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
        imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize image with high quality
    imagecopyresampled($resizedImage, $originalImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
    
    // Create temporary file for testing
    $tempFile = tempnam(sys_get_temp_dir(), 'img_resize_');
    
    // Try different quality levels until we meet the size requirement
    $quality = 85; // Start with high quality
    $minQuality = 30; // Minimum quality threshold
    $step = 5; // Quality reduction step
    
    $bestFile = null;
    $bestSize = PHP_INT_MAX;
    
    while($quality >= $minQuality) {
        // Save with current quality
        $success = false;
        if($isPng) {
            // PNG uses compression level 0-9 (0 = no compression, 9 = max compression)
            $pngQuality = (int)((100 - $quality) / 11.111);
            $success = @imagepng($resizedImage, $tempFile, $pngQuality);
        } else {
            // JPEG
            $success = @imagejpeg($resizedImage, $tempFile, $quality);
        }
        
        if($success && file_exists($tempFile)) {
            $fileSize = filesize($tempFile);
            
            if($fileSize <= $maxSize) {
                // Found acceptable size
                $bestFile = $tempFile;
                break;
            } else if($fileSize < $bestSize) {
                // Keep track of best attempt so far
                $bestSize = $fileSize;
                $bestFile = $tempFile;
            }
        }
        
        // Reduce quality for next iteration
        $quality -= $step;
    }
    
    // If still too large, try reducing dimensions further (use original image for better quality)
    if($bestFile && file_exists($bestFile) && filesize($bestFile) > $maxSize) {
        $currentWidth = $newWidth;
        $currentHeight = $newHeight;
        $dimensionStep = 0.85; // Reduce by 15% each iteration
        $maxIterations = 10; // Prevent infinite loops
        $iteration = 0;
        
        while($currentWidth > 100 && $currentHeight > 100 && $iteration < $maxIterations) {
            $iteration++;
            $currentWidth = (int)($currentWidth * $dimensionStep);
            $currentHeight = (int)($currentHeight * $dimensionStep);
            
            // Create new temp file for this iteration
            $iterationTempFile = tempnam(sys_get_temp_dir(), 'img_resize_iter_');
            
            // Create new resized image from original (better quality than resizing from resized)
            $smallerImage = imagecreatetruecolor($currentWidth, $currentHeight);
            
            if($isPng) {
                imagealphablending($smallerImage, false);
                imagesavealpha($smallerImage, true);
                $transparent = imagecolorallocatealpha($smallerImage, 255, 255, 255, 127);
                imagefilledrectangle($smallerImage, 0, 0, $currentWidth, $currentHeight, $transparent);
            }
            
            // Resize from original image for better quality
            imagecopyresampled($smallerImage, $originalImage, 0, 0, 0, 0, $currentWidth, $currentHeight, $originalWidth, $originalHeight);
            
            // Try with moderate quality
            $finalQuality = 70;
            $success = false;
            if($isPng) {
                $pngQuality = (int)((100 - $finalQuality) / 11.111);
                $success = @imagepng($smallerImage, $iterationTempFile, $pngQuality);
            } else {
                $success = @imagejpeg($smallerImage, $iterationTempFile, $finalQuality);
            }
            
            imagedestroy($smallerImage);
            
            if($success && file_exists($iterationTempFile)) {
                $iterationSize = filesize($iterationTempFile);
                
                if($iterationSize <= $maxSize) {
                    // Found acceptable size - clean up previous best file
                    if($bestFile && file_exists($bestFile) && $bestFile !== $iterationTempFile) {
                        @unlink($bestFile);
                    }
                    $bestFile = $iterationTempFile;
                    break;
                } else if($iterationSize < $bestSize) {
                    // Update best file if this is smaller
                    if($bestFile && file_exists($bestFile) && $bestFile !== $iterationTempFile) {
                        @unlink($bestFile);
                    }
                    $bestFile = $iterationTempFile;
                    $bestSize = $iterationSize;
                } else {
                    // This attempt is worse, clean it up
                    @unlink($iterationTempFile);
                }
            } else if(file_exists($iterationTempFile)) {
                @unlink($iterationTempFile);
            }
        }
    }
    
    // Clean up original image resource
    imagedestroy($originalImage);
    
    // Copy to destination
    if($bestFile && file_exists($bestFile)) {
        if($bestFile !== $destination) {
            copy($bestFile, $destination);
            unlink($bestFile);
        }
    } else {
        // Fallback: save with minimum settings
        if($isPng) {
            @imagepng($resizedImage, $destination, 9);
        } else {
            @imagejpeg($resizedImage, $destination, 50);
        }
    }
    
    imagedestroy($resizedImage);
    
    return $destination;
}

/**
 * Process image upload with automatic resizing and compression
 * Automatically resizes images to meet size requirements - no manual resizing needed
 * 
 * @param array $file The $_FILES array element
 * @param int $maxSize Maximum file size in bytes (default 250000 = 250KB)
 * @param int $maxWidth Maximum width in pixels (default 2000)
 * @param int $maxHeight Maximum height in pixels (default 2000)
 * @param array $allowedTypes Array of allowed file extensions (default: jpg, jpeg, png)
 * @return array Result with status, message and processed image data
 */
function processImageUploadWithAutoResize($file, $maxSize = 250000, $maxWidth = 2000, $maxHeight = 2000, $allowedTypes = ['jpg', 'jpeg', 'png']) {
    $result = [
        'status' => false,
        'message' => '',
        'data' => null
    ];
    
    // Check if file is uploaded
    if(empty($file['tmp_name']) || $file['error'] != 0) {
        $result['message'] = '<div class="alert alert-danger">No file uploaded or upload error occurred</div>';
        return $result;
    }
    
    // Validate file type
    $imageFileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if(!in_array($imageFileType, $allowedTypes)) {
        $result['message'] = '<div class="alert alert-danger">Only ' . implode(', ', $allowedTypes) . ' files are allowed</div>';
        return $result;
    }
    
    // Verify it's actually an image
    $check = @getimagesize($file['tmp_name']);
    if($check === false) {
        $result['message'] = '<div class="alert alert-danger">File is not a valid image</div>';
        return $result;
    }
    
    try {
        // Check if GD extension is available
        if(!extension_loaded('gd')) {
            // If GD is not available, check size and use original
            if($file['size'] > $maxSize) {
                $result['message'] = '<div class="alert alert-danger">File size exceeds ' . ($maxSize/1000) . 'KB limit. GD library not available for automatic resizing.</div>';
                return $result;
            }
            $imageData = addslashes(file_get_contents($file['tmp_name']));
            $result['status'] = true;
            $result['data'] = $imageData;
            $result['message'] = '<div class="alert alert-info">Image uploaded successfully (auto-resize disabled - GD extension not available).</div>';
        } else {
            // Get original file size for logging
            $originalSize = $file['size'];
            $originalSizeKB = round($originalSize / 1024, 2);
            
            // Create temporary file for processed image
            $processedFile = tempnam(sys_get_temp_dir(), 'auto_resize_');
            
            // Automatically resize and compress image to meet size requirement
            $processedImage = resizeAndCompressImage($file['tmp_name'], $processedFile, $maxSize, $maxWidth, $maxHeight);
            
            // Check final file size
            if(file_exists($processedImage)) {
                $finalSize = filesize($processedImage);
                $finalSizeKB = round($finalSize / 1024, 2);
                $sizeReduction = $originalSize > 0 ? round((($originalSize - $finalSize) / $originalSize) * 100, 2) : 0;
                
                // Console log the size information
                $logMessage = "Image Auto-Resize: Original: {$originalSizeKB}KB ({$originalSize} bytes) -> Processed: {$finalSizeKB}KB ({$finalSize} bytes) | Reduction: {$sizeReduction}% | Target: " . round($maxSize/1024, 2) . "KB";
                error_log($logMessage);
                
                // Also add to result for potential JavaScript console output
                $result['original_size'] = $originalSize;
                $result['original_size_kb'] = $originalSizeKB;
                $result['processed_size'] = $finalSize;
                $result['processed_size_kb'] = $finalSizeKB;
                $result['size_reduction'] = $sizeReduction;
                
                // If still too large after processing, reject it
                if($finalSize > $maxSize) {
                    $result['message'] = '<div class="alert alert-danger">Unable to reduce file size below ' . ($maxSize/1000) . 'KB. Please use a smaller image.</div>';
                    @unlink($processedImage);
                    return $result;
                }
                
                // Read processed image data
                $imageData = addslashes(file_get_contents($processedImage));
                $result['status'] = true;
                $result['data'] = $imageData;
                $result['message'] = '<div class="alert alert-success">Image uploaded and automatically resized successfully.</div>';
                
                // Clean up temp file
                @unlink($processedImage);
            } else {
                throw new Exception("Processed image file not found");
            }
        }
    } catch (Exception $e) {
        $result['message'] = '<div class="alert alert-danger">Error processing image: ' . $e->getMessage() . '</div>';
    }
    
    return $result;
}

/**
 * Process image upload with automatic 1:1 crop, resize, and compression
 * Automatically crops image to 1:1 ratio (center crop), resizes to target dimensions,
 * and compresses to meet target file size requirements
 * 
 * @param array $file The $_FILES array element
 * @param int $targetSize Target square dimensions (default 600x600)
 * @param int $targetFileSize Target file size in bytes (default 250KB)
 * @param int $minFileSize Minimum file size in bytes (default 200KB)
 * @param int $maxFileSize Maximum file size in bytes (default 300KB)
 * @param array $allowedTypes Array of allowed MIME types (default: png, jpeg, jpg, gif, webp)
 * @param string $outputFormat Output format: 'jpeg', 'png', or 'original' (default: 'jpeg')
 * @param string $destination Optional destination path for saved file
 * @return array Result with status, message, data (base64 encoded), file_path, dimensions, and file_size
 */
function processImageUploadWithAutoCrop($file, $targetSize = 600, $targetFileSize = 250000, $minFileSize = 200000, $maxFileSize = 300000, $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'], $outputFormat = 'jpeg', $destination = null) {
    $result = [
        'status' => false,
        'message' => '',
        'data' => null,
        'file_path' => null,
        'dimensions' => ['width' => 0, 'height' => 0],
        'file_size' => 0,
        'original_size' => 0,
        'format' => 'JPEG'
    ];
    
    // Check if file is uploaded
    if(empty($file['tmp_name']) || $file['error'] != UPLOAD_ERR_OK) {
        $result['message'] = '<div class="alert alert-danger">No file uploaded or upload error occurred.</div>';
        return $result;
    }
    
    // Validate file type
    $fileType = mime_content_type($file['tmp_name']);
    if(!in_array($fileType, $allowedTypes)) {
        $result['message'] = '<div class="alert alert-danger">Invalid file type. Only PNG, JPG, JPEG, GIF, and WEBP are allowed.</div>';
        return $result;
    }
    
    // Validate file size (10MB max for upload)
    $maxUploadSize = 10 * 1024 * 1024; // 10MB
    if($file['size'] > $maxUploadSize) {
        $result['message'] = '<div class="alert alert-danger">File size exceeds 10MB limit.</div>';
        return $result;
    }
    
    try {
        // Get original image info
        $imageInfo = getimagesize($file['tmp_name']);
        if($imageInfo === false) {
            throw new Exception('Invalid image file.');
        }
        
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $originalSize = $file['size'];
        $result['original_size'] = $originalSize;
        
        $currentTargetSize = $targetSize;
        $mime = $imageInfo['mime'];
        
        // Create temporary file for processed image
        if($destination === null) {
            $tempFile = tempnam(sys_get_temp_dir(), 'auto_crop_');
            $filepath = $tempFile . '.jpg';
        } else {
            $filepath = $destination;
        }
        
        // Try to use Intervention Image first
        $useIntervention = false;
        try {
            // Check if already loaded
            if(class_exists('Intervention\Image\ImageManager') || class_exists('Intervention\Image\ImageManagerStatic')) {
                $useIntervention = true;
            } 
            // Check in old/vendor folder (outside vendor folder as requested)
            elseif(file_exists(__DIR__ . '/../../old/vendor/intervention/image/src/Intervention/Image/ImageManager.php')) {
                if(file_exists(__DIR__ . '/../../old/vendor/autoload.php')) {
                    require_once __DIR__ . '/../../old/vendor/autoload.php';
                }
                if(class_exists('Intervention\Image\ImageManager') || class_exists('Intervention\Image\ImageManagerStatic')) {
                    $useIntervention = true;
                }
            }
            // Also check in root vendor folder if it exists
            elseif(file_exists(__DIR__ . '/../../vendor/intervention/image/src/Intervention/Image/ImageManager.php')) {
                if(file_exists(__DIR__ . '/../../vendor/autoload.php')) {
                    require_once __DIR__ . '/../../vendor/autoload.php';
                }
                if(class_exists('Intervention\Image\ImageManager') || class_exists('Intervention\Image\ImageManagerStatic')) {
                    $useIntervention = true;
                }
            }
        } catch(Exception $e) {
            // Intervention Image not available, will use GD
        }
        
        if($useIntervention) {
            // Use Intervention Image
            try {
                if(class_exists('Intervention\Image\ImageManagerStatic')) {
                    $img = Intervention\Image\ImageManagerStatic::make($file['tmp_name']);
                } elseif(class_exists('Intervention\Image\ImageManager')) {
                    $manager = new Intervention\Image\ImageManager(['driver' => 'gd']);
                    $img = $manager->make($file['tmp_name']);
                } else {
                    throw new Exception('Intervention Image class not found');
                }
                
                // Fit to 1:1 ratio (crops from center and resizes to target size)
                $img->fit($targetSize, $targetSize);
                
                // Try to find the right quality to hit target file size
                $quality = 75;
                $bestQuality = $quality;
                $bestSize = 0;
                $attempts = 0;
                $maxAttempts = 15;
                
                $lowQuality = 60;
                $highQuality = 100;
                
                while($attempts < $maxAttempts) {
                    $quality = round(($lowQuality + $highQuality) / 2);
                    $img->save($filepath, $quality);
                    clearstatcache(true, $filepath);
                    $fileSize = filesize($filepath);
                    
                    if($fileSize >= $minFileSize && $fileSize <= $maxFileSize) {
                        $bestQuality = $quality;
                        $bestSize = $fileSize;
                        break;
                    } elseif($fileSize < $minFileSize) {
                        $lowQuality = $quality + 1;
                        $bestQuality = $quality;
                        $bestSize = $fileSize;
                    } else {
                        $highQuality = $quality - 1;
                        if($bestSize == 0 || abs($fileSize - $targetFileSize) < abs($bestSize - $targetFileSize)) {
                            $bestQuality = $quality;
                            $bestSize = $fileSize;
                        }
                    }
                    
                    $attempts++;
                    if($lowQuality > $highQuality) {
                        break;
                    }
                }
                
                // Save with best quality found
                $img->save($filepath, $bestQuality);
                clearstatcache(true, $filepath);
                $fileSize = filesize($filepath);
                
                // Only increase dimensions slightly if still too small (max 800x800)
                if($fileSize < $minFileSize && $currentTargetSize < 800) {
                    $currentTargetSize = 800;
                    $img->fit($currentTargetSize, $currentTargetSize);
                    $img->save($filepath, 85);
                    clearstatcache(true, $filepath);
                    $fileSize = filesize($filepath);
                    
                    if($fileSize < $minFileSize) {
                        $img->save($filepath, 90);
                        clearstatcache(true, $filepath);
                        $fileSize = filesize($filepath);
                    }
                }
                
                $finalWidth = $currentTargetSize;
                $finalHeight = $currentTargetSize;
                $format = 'JPEG';
            } catch(Exception $e) {
                // If Intervention Image fails, fall back to GD
                $useIntervention = false;
            }
        }
        
        if(!$useIntervention) {
            // Fallback to GD library
            if(!extension_loaded('gd')) {
                throw new Exception('GD library is not available. Please enable GD extension in PHP.');
            }
            
            // Create image resource based on mime type
            $sourceImage = null;
            switch($mime) {
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
                    if(!function_exists('imagecreatefromwebp')) {
                        throw new Exception('WebP support is not available in your PHP installation.');
                    }
                    $sourceImage = imagecreatefromwebp($file['tmp_name']);
                    break;
                default:
                    throw new Exception('Unsupported image type: ' . $mime);
            }
            
            if(!$sourceImage) {
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
            if($mime == 'image/png') {
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
            
            // Try to find the right quality to hit target file size
            $quality = 75;
            $bestQuality = $quality;
            $bestSize = 0;
            $attempts = 0;
            $maxAttempts = 15;
            
            $lowQuality = 60;
            $highQuality = 100;
            
            while($attempts < $maxAttempts) {
                $quality = round(($lowQuality + $highQuality) / 2);
                imagejpeg($targetImage, $filepath, $quality);
                clearstatcache(true, $filepath);
                $fileSize = filesize($filepath);
                
                if($fileSize >= $minFileSize && $fileSize <= $maxFileSize) {
                    $bestQuality = $quality;
                    $bestSize = $fileSize;
                    break;
                } elseif($fileSize < $minFileSize) {
                    $lowQuality = $quality + 1;
                    $bestQuality = $quality;
                    $bestSize = $fileSize;
                } else {
                    $highQuality = $quality - 1;
                    if($bestSize == 0 || abs($fileSize - $targetFileSize) < abs($bestSize - $targetFileSize)) {
                        $bestQuality = $quality;
                        $bestSize = $fileSize;
                    }
                }
                
                $attempts++;
                if($lowQuality > $highQuality) {
                    break;
                }
            }
            
            // Save with best quality found
            imagejpeg($targetImage, $filepath, $bestQuality);
            clearstatcache(true, $filepath);
            $fileSize = filesize($filepath);
            
            // Only increase dimensions slightly if still too small (max 800x800)
            if($fileSize < $minFileSize && $currentTargetSize < 800) {
                imagedestroy($targetImage);
                
                $currentTargetSize = 800;
                $targetImage = imagecreatetruecolor($currentTargetSize, $currentTargetSize);
                
                if($mime == 'image/png') {
                    imagealphablending($targetImage, false);
                    imagesavealpha($targetImage, true);
                    $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
                    imagefilledrectangle($targetImage, 0, 0, $currentTargetSize, $currentTargetSize, $transparent);
                }
                
                imagecopyresampled(
                    $targetImage,
                    $sourceImage,
                    0, 0,
                    $cropX, $cropY,
                    $currentTargetSize, $currentTargetSize,
                    $minDimension, $minDimension
                );
                
                imagejpeg($targetImage, $filepath, 85);
                clearstatcache(true, $filepath);
                $fileSize = filesize($filepath);
                
                if($fileSize < $minFileSize) {
                    imagejpeg($targetImage, $filepath, 90);
                    clearstatcache(true, $filepath);
                    $fileSize = filesize($filepath);
                }
            }
            
            // Clean up memory
            imagedestroy($sourceImage);
            imagedestroy($targetImage);
            
            $finalWidth = $currentTargetSize;
            $finalHeight = $currentTargetSize;
            $format = 'JPEG';
        }
        
        // Wait a moment to ensure file is fully written
        clearstatcache(true, $filepath);
        usleep(100000); // 0.1 second delay
        
        // Get final file size
        $finalSize = filesize($filepath);
        
        if(!file_exists($filepath) || $finalSize === false || $finalSize <= 0) {
            throw new Exception('Processed image file was not created or is invalid.');
        }
        
        // Read processed image data
        $imageData = file_get_contents($filepath);
        
        // Set result data
        $result['status'] = true;
        $result['data'] = $imageData; // Binary data (can be used with addslashes() if needed)
        $result['file_path'] = $filepath;
        $result['dimensions'] = ['width' => $finalWidth, 'height' => $finalHeight];
        $result['file_size'] = $finalSize;
        $result['format'] = $format;
        $result['message'] = '<div class="alert alert-success">Image processed successfully. Cropped to 1:1 ratio, resized to ' . $finalWidth . 'x' . $finalHeight . ' pixels, and optimized.</div>';
        
    } catch(Exception $e) {
        $result['message'] = '<div class="alert alert-danger">Error processing image: ' . $e->getMessage() . '</div>';
    }
    
    return $result;
}
?>

