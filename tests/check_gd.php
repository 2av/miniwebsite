<?php
/**
 * GD Library Check Script
 * 
 * This script checks if GD library is available and displays detailed information
 * Access this file via browser to see GD status
 */

// Include the check function
if(file_exists('../includes/file_validation.php')) {
    require_once '../includes/file_validation.php';
} elseif(file_exists('includes/file_validation.php')) {
    require_once 'includes/file_validation.php';
} else {
    // Fallback function if file_validation.php is not available
    function checkGDLibrary($detailed = false) {
        $gdInfo = [
            'available' => false,
            'extension_loaded' => false,
            'functions_available' => [],
            'supported_formats' => [],
            'version' => null,
            'info' => []
        ];
        
        $gdInfo['extension_loaded'] = extension_loaded('gd');
        
        if($gdInfo['extension_loaded']) {
            $gdInfo['available'] = true;
            
            if(function_exists('gd_info')) {
                $gdInfo['info'] = gd_info();
                $gdInfo['version'] = isset($gdInfo['info']['GD Version']) ? $gdInfo['info']['GD Version'] : 'Unknown';
            }
            
            $essentialFunctions = [
                'imagecreatefromjpeg' => 'JPEG Support',
                'imagecreatefrompng' => 'PNG Support',
                'imagecreatefromgif' => 'GIF Support',
                'imagejpeg' => 'JPEG Output',
                'imagepng' => 'PNG Output',
                'imagegif' => 'GIF Output',
                'imagecreatetruecolor' => 'True Color Support',
                'imagecopyresampled' => 'Image Resizing'
            ];
            
            foreach($essentialFunctions as $func => $description) {
                if(function_exists($func)) {
                    $gdInfo['functions_available'][$func] = $description;
                }
            }
            
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
        
        return $detailed ? $gdInfo : $gdInfo['available'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GD Library Check</title>
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
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .status {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-weight: bold;
            font-size: 18px;
        }
        .available {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .not-available {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info-section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .info-section h2 {
            color: #495057;
            margin-top: 0;
            font-size: 20px;
        }
        .info-item {
            margin: 10px 0;
            padding: 8px;
            background: white;
            border-left: 4px solid #007bff;
            padding-left: 15px;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
        }
        .info-value {
            color: #28a745;
        }
        .function-list {
            list-style: none;
            padding: 0;
        }
        .function-list li {
            padding: 5px 10px;
            margin: 5px 0;
            background: white;
            border-left: 3px solid #28a745;
            padding-left: 15px;
        }
        .format-badge {
            display: inline-block;
            padding: 5px 12px;
            margin: 3px;
            background-color: #007bff;
            color: white;
            border-radius: 15px;
            font-size: 14px;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>GD Library Status Check</h1>
        
        <?php
        $gdInfo = checkGDLibrary(true);
        
        if($gdInfo['available']) {
            echo '<div class="status available">✓ GD Library is AVAILABLE</div>';
        } else {
            echo '<div class="status not-available">✗ GD Library is NOT AVAILABLE</div>';
            echo '<div class="warning">';
            echo '<strong>Warning:</strong> GD library is not installed or enabled. Image resizing and compression features will not work.';
            echo '<br><br>';
            echo '<div style="text-align: center; margin: 20px 0;">';
            echo '<a href="enable_gd_instructions.php" style="display: inline-block; padding: 15px 30px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">';
            echo '📖 Click Here for Step-by-Step Enable Instructions';
            echo '</a>';
            echo '</div>';
            echo '<br>Quick steps:';
            echo '<ul>';
            echo '<li>For XAMPP: Uncomment <code>extension=gd</code> in php.ini</li>';
            echo '<li>For Linux: Install php-gd package: <code>sudo apt-get install php-gd</code></li>';
            echo '<li>For Windows: Enable extension in php.ini and restart web server</li>';
            echo '</ul>';
            echo '</div>';
        }
        ?>
        
        <?php if(!$gdInfo['available']): ?>
        <div style="margin: 20px 0; text-align: center;">
            <a href="enable_gd_instructions.php" style="display: inline-block; padding: 15px 30px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                📖 Click Here for Step-by-Step Enable Instructions
            </a>
        </div>
        <?php endif; ?>
        
        <?php if($gdInfo['available']): ?>
        <div class="info-section">
            <h2>GD Library Information</h2>
            
            <div class="info-item">
                <span class="info-label">Version:</span>
                <span class="info-value"><?php echo htmlspecialchars($gdInfo['version']); ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Extension Loaded:</span>
                <span class="info-value"><?php echo $gdInfo['extension_loaded'] ? 'Yes' : 'No'; ?></span>
            </div>
            
            <?php if(!empty($gdInfo['supported_formats'])): ?>
            <div class="info-item">
                <span class="info-label">Supported Formats:</span><br>
                <?php foreach($gdInfo['supported_formats'] as $format): ?>
                    <span class="format-badge"><?php echo htmlspecialchars($format); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if(!empty($gdInfo['functions_available'])): ?>
            <div class="info-item">
                <span class="info-label">Available Functions (<?php echo count($gdInfo['functions_available']); ?>):</span>
                <ul class="function-list">
                    <?php foreach($gdInfo['functions_available'] as $func => $desc): ?>
                        <li><code><?php echo htmlspecialchars($func); ?></code> - <?php echo htmlspecialchars($desc); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if(!empty($gdInfo['info'])): ?>
            <div class="info-item">
                <span class="info-label">Detailed GD Info:</span>
                <pre style="background: white; padding: 10px; border-radius: 3px; overflow-x: auto;"><?php print_r($gdInfo['info']); ?></pre>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="info-section">
            <h2>Quick Test</h2>
            <?php
            if($gdInfo['available']) {
                // Try to create a test image
                try {
                    $testImage = @imagecreatetruecolor(100, 100);
                    if($testImage) {
                        echo '<div class="info-item">';
                        echo '<span class="info-label">Test Image Creation:</span> ';
                        echo '<span class="info-value">✓ Success</span>';
                        echo '</div>';
                        imagedestroy($testImage);
                    } else {
                        echo '<div class="info-item">';
                        echo '<span class="info-label">Test Image Creation:</span> ';
                        echo '<span class="info-value" style="color: #dc3545;">✗ Failed</span>';
                        echo '</div>';
                    }
                } catch(Exception $e) {
                    echo '<div class="info-item">';
                    echo '<span class="info-label">Test Image Creation:</span> ';
                    echo '<span class="info-value" style="color: #dc3545;">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
                    echo '</div>';
                }
            } else {
                echo '<div class="info-item">';
                echo '<span class="info-label">Test Image Creation:</span> ';
                echo '<span class="info-value" style="color: #dc3545;">✗ Cannot test - GD not available</span>';
                echo '</div>';
            }
            ?>
        </div>
        
        <div class="info-section">
            <h2>PHP Information</h2>
            <div class="info-item">
                <span class="info-label">PHP Version:</span>
                <span class="info-value"><?php echo PHP_VERSION; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Server API:</span>
                <span class="info-value"><?php echo php_sapi_name(); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">php.ini Location:</span>
                <span class="info-value" style="font-family: monospace; font-size: 12px; word-break: break-all;"><?php echo php_ini_loaded_file() ? htmlspecialchars(php_ini_loaded_file()) : 'Not found'; ?></span>
            </div>
        </div>
        
        <?php if($gdInfo['available']): ?>
        <div style="text-align: center; margin: 30px 0;">
            <a href="test_image_resize.php" style="display: inline-block; padding: 15px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                🧪 Test Image Auto-Resize Feature
            </a>
        </div>
        <?php else: ?>
        <div style="text-align: center; margin: 30px 0;">
            <a href="enable_gd_instructions.php" style="display: inline-block; padding: 15px 30px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                📖 View Detailed Enable Instructions
            </a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>

