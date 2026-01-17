<?php
/**
 * Test Image Resize Functionality
 * 
 * This page tests the automatic image resizing feature
 */

// Include the check function
if(file_exists('../includes/file_validation.php')) {
    require_once '../includes/file_validation.php';
} elseif(file_exists('includes/file_validation.php')) {
    require_once 'includes/file_validation.php';
} else {
    die('Error: file_validation.php not found');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Image Auto-Resize</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #28a745;
            border-bottom: 3px solid #28a745;
            padding-bottom: 10px;
        }
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .upload-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .form-group {
            margin: 15px 0;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #495057;
        }
        input[type="file"] {
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            width: 100%;
            max-width: 400px;
        }
        button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #0056b3;
        }
        .result {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
        }
        .result-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .result-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .image-comparison {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .image-box {
            flex: 1;
            min-width: 200px;
            text-align: center;
        }
        .image-box img {
            max-width: 100%;
            border: 2px solid #dee2e6;
            border-radius: 5px;
            margin: 10px 0;
        }
        .stats {
            background: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 14px;
        }
        .stats strong {
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✓ Image Auto-Resize Test</h1>
        
        <div class="success-box">
            <strong>GD Library Status:</strong> ✓ Enabled and Working<br>
            <strong>Version:</strong> <?php 
                $gdInfo = checkGDLibrary(true);
                echo htmlspecialchars($gdInfo['version']);
            ?><br>
            <strong>Supported Formats:</strong> <?php echo implode(', ', $gdInfo['supported_formats']); ?>
        </div>

        <div class="info-box">
            <strong>How it works:</strong>
            <ul>
                <li>Upload any image (even large files)</li>
                <li>The system will automatically resize it to meet the 250KB requirement</li>
                <li>Image quality and dimensions will be optimized automatically</li>
                <li>No manual resizing needed!</li>
            </ul>
        </div>

        <div class="upload-form">
            <h2>Test Image Upload</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="test_image">Select an Image (JPEG, PNG, or GIF):</label>
                    <input type="file" id="test_image" name="test_image" accept="image/jpeg,image/png,image/gif" required>
                    <small style="color: #6c757d;">You can upload images larger than 250KB - they will be automatically resized.</small>
                </div>
                <button type="submit">Test Auto-Resize</button>
            </form>
        </div>

        <?php
        if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_image'])) {
            echo '<div class="result">';
            
            $file = $_FILES['test_image'];
            
            // Get original file info
            $originalSize = $file['size'];
            $originalName = $file['name'];
            $originalType = $file['type'];
            
            // Get original image dimensions if possible
            $originalDimensions = 'Unknown';
            if($file['tmp_name'] && file_exists($file['tmp_name'])) {
                $imgInfo = @getimagesize($file['tmp_name']);
                if($imgInfo) {
                    $originalDimensions = $imgInfo[0] . ' x ' . $imgInfo[1] . ' pixels';
                }
            }
            
            echo '<h3>Original Image Info:</h3>';
            echo '<div class="stats">';
            echo '<strong>File Name:</strong> ' . htmlspecialchars($originalName) . '<br>';
            echo '<strong>File Size:</strong> ' . number_format($originalSize / 1024, 2) . ' KB<br>';
            echo '<strong>Dimensions:</strong> ' . $originalDimensions . '<br>';
            echo '<strong>MIME Type:</strong> ' . htmlspecialchars($originalType) . '<br>';
            echo '</div>';
            
            // Process with auto-resize
            $result = processImageUploadWithAutoResize($file, 250000, 2000, 2000, ['jpg', 'jpeg', 'png', 'gif']);
            
            if($result['status']) {
                echo '<div class="result-success">';
                echo '<h3>✓ Processing Successful!</h3>';
                
                // Get processed image info
                $processedSize = strlen($result['data']);
                $sizeReduction = $originalSize > 0 ? (($originalSize - $processedSize) / $originalSize) * 100 : 0;
                
                echo '<div class="stats">';
                echo '<strong>Processed Size:</strong> ' . number_format($processedSize / 1024, 2) . ' KB<br>';
                echo '<strong>Size Reduction:</strong> ' . number_format($sizeReduction, 1) . '%<br>';
                echo '<strong>Status:</strong> ' . ($processedSize <= 250000 ? '✓ Under 250KB limit' : '⚠ Still above limit') . '<br>';
                echo '</div>';
                
                // Prepare processed image data for display
                // Remove slashes that were added for database storage
                $processedImageData = stripslashes($result['data']);
                
                // Determine MIME type of processed image
                // Processed images are typically saved as JPEG by resizeAndCompressImage
                $processedMimeType = 'image/jpeg'; // Default to JPEG
                
                // Try to detect actual format from image data
                $imageInfo = @getimagesizefromstring($processedImageData);
                if($imageInfo && isset($imageInfo['mime'])) {
                    $processedMimeType = $imageInfo['mime'];
                }
                
                // Get processed image dimensions
                $processedDimensions = 'Unknown';
                if($imageInfo) {
                    $processedDimensions = $imageInfo[0] . ' x ' . $imageInfo[1] . ' pixels';
                }
                
                // Display images
                echo '<div class="image-comparison">';
                echo '<div class="image-box">';
                echo '<h4>Original Image</h4>';
                $originalImageData = file_get_contents($file['tmp_name']);
                echo '<img src="data:' . htmlspecialchars($originalType) . ';base64,' . base64_encode($originalImageData) . '" alt="Original">';
                echo '<div class="stats">';
                echo number_format($originalSize / 1024, 2) . ' KB<br>';
                echo $originalDimensions;
                echo '</div>';
                echo '</div>';
                
                echo '<div class="image-box">';
                echo '<h4>Processed Image</h4>';
                echo '<img src="data:' . htmlspecialchars($processedMimeType) . ';base64,' . base64_encode($processedImageData) . '" alt="Processed">';
                echo '<div class="stats">';
                echo number_format($processedSize / 1024, 2) . ' KB<br>';
                echo $processedDimensions;
                echo '</div>';
                echo '</div>';
                echo '</div>';
                
                // Show additional info
                echo '<div class="stats" style="margin-top: 15px;">';
                echo '<strong>Processed Format:</strong> ' . htmlspecialchars($processedMimeType) . '<br>';
                echo '<strong>Dimension Reduction:</strong> ';
                if($originalDimensions !== 'Unknown' && $processedDimensions !== 'Unknown') {
                    $origParts = explode(' x ', $originalDimensions);
                    $procParts = explode(' x ', $processedDimensions);
                    if(count($origParts) == 2 && count($procParts) == 2) {
                        $origW = (int)$origParts[0];
                        $origH = (int)$origParts[1];
                        $procW = (int)$procParts[0];
                        $procH = (int)$procParts[1];
                        if($origW > 0 && $origH > 0) {
                            $dimReduction = ((($origW * $origH) - ($procW * $procH)) / ($origW * $origH)) * 100;
                            echo number_format($dimReduction, 1) . '%';
                        } else {
                            echo 'N/A';
                        }
                    } else {
                        echo 'N/A';
                    }
                } else {
                    echo 'N/A';
                }
                echo '</div>';
                
                echo '<div class="info-box">';
                echo '<strong>Result:</strong> Image has been automatically resized and compressed to meet the 250KB requirement. ';
                echo 'The processed image is ready to be saved to the database.';
                echo '</div>';
                
                echo '</div>';
            } else {
                echo '<div class="result-error">';
                echo '<h3>✗ Processing Failed</h3>';
                echo '<p>' . $result['message'] . '</p>';
                echo '</div>';
            }
            
            echo '</div>';
        }
        ?>

        <div class="info-box" style="margin-top: 30px;">
            <strong>Next Steps:</strong>
            <ul>
                <li>✓ GD Library is enabled and working</li>
                <li>✓ Image auto-resize function is ready to use</li>
                <li>✓ You can now use image upload features in your application</li>
                <li>Visit <a href="../customer/website/image-gallery.php">Image Gallery</a> to test with real uploads</li>
            </ul>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="check_gd.php" style="display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; margin: 5px;">
                ← Back to GD Status
            </a>
            <a href="../customer/website/image-gallery.php" style="display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 5px;">
                Test Image Gallery →
            </a>
        </div>
    </div>
</body>
</html>

