<?php
/**
 * GD Library Check - Command Line Version
 * 
 * Run this from command line: php tests/check_gd_cli.php
 * Or from tests directory: php check_gd_cli.php
 */

// Include the check function
if(file_exists('../includes/file_validation.php')) {
    require_once '../includes/file_validation.php';
} elseif(file_exists('includes/file_validation.php')) {
    require_once 'includes/file_validation.php';
} else {
    // Fallback function
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

echo "========================================\n";
echo "GD Library Status Check\n";
echo "========================================\n\n";

$gdInfo = checkGDLibrary(true);

if($gdInfo['available']) {
    echo "Status: ✓ GD Library is AVAILABLE\n";
    echo "----------------------------------------\n";
    echo "Version: " . $gdInfo['version'] . "\n";
    echo "Extension Loaded: " . ($gdInfo['extension_loaded'] ? 'Yes' : 'No') . "\n";
    
    if(!empty($gdInfo['supported_formats'])) {
        echo "Supported Formats: " . implode(', ', $gdInfo['supported_formats']) . "\n";
    }
    
    echo "\nAvailable Functions (" . count($gdInfo['functions_available']) . "):\n";
    foreach($gdInfo['functions_available'] as $func => $desc) {
        echo "  ✓ $func - $desc\n";
    }
    
    // Test image creation
    echo "\nTest Image Creation: ";
    $testImage = @imagecreatetruecolor(100, 100);
    if($testImage) {
        echo "✓ Success\n";
        imagedestroy($testImage);
    } else {
        echo "✗ Failed\n";
    }
    
} else {
    echo "Status: ✗ GD Library is NOT AVAILABLE\n";
    echo "----------------------------------------\n";
    echo "Extension Loaded: " . ($gdInfo['extension_loaded'] ? 'Yes' : 'No') . "\n";
    echo "\nWarning: GD library is not installed or enabled.\n";
    echo "Image resizing and compression features will not work.\n\n";
    echo "To enable GD library:\n";
    echo "  - XAMPP: Uncomment 'extension=gd' in php.ini\n";
    echo "  - Linux: sudo apt-get install php-gd\n";
    echo "  - Windows: Enable extension in php.ini and restart server\n";
}

echo "\n========================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server API: " . php_sapi_name() . "\n";
echo "========================================\n";

