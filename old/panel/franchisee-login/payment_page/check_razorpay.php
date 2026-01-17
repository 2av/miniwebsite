<?php
// This file checks if the Razorpay SDK is properly installed
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Razorpay SDK Check</h2>";

// Check if config.php exists
if (file_exists('config.php')) {
    echo "<p style='color:green'>✓ config.php exists</p>";
    require_once('config.php');
    
    echo "<p>Using Razorpay Key ID: " . substr($keyId, 0, 4) . "..." . substr($keyId, -4) . "</p>";
} else {
    echo "<p style='color:red'>✗ config.php does not exist</p>";
}

// Check if Razorpay.php exists
if (file_exists('razorpay-php/Razorpay.php')) {
    echo "<p style='color:green'>✓ razorpay-php/Razorpay.php exists</p>";
    
    try {
        require_once('razorpay-php/Razorpay.php');
        echo "<p style='color:green'>✓ Successfully included Razorpay.php</p>";
        
        // Check if Api class exists
        if (class_exists('Razorpay\Api\Api')) {
            echo "<p style='color:green'>✓ Razorpay\Api\Api class exists</p>";
            
            // Try to create an instance
            try {
                $api = new Razorpay\Api\Api($keyId, $keySecret);
                echo "<p style='color:green'>✓ Successfully created Razorpay API instance</p>";
                
                // Try to fetch a simple API endpoint
                try {
                    $result = $api->order->all(['count' => 1]);
                    echo "<p style='color:green'>✓ Successfully made API call</p>";
                } catch (Exception $e) {
                    echo "<p style='color:orange'>⚠ API call failed: " . $e->getMessage() . "</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color:red'>✗ Failed to create Razorpay API instance: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color:red'>✗ Razorpay\Api\Api class does not exist</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>✗ Failed to include Razorpay.php: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>✗ razorpay-php/Razorpay.php does not exist</p>";
    
    // Check if the razorpay-php directory exists
    if (is_dir('razorpay-php')) {
        echo "<p style='color:orange'>⚠ razorpay-php directory exists but Razorpay.php is missing</p>";
        
        // List files in the directory
        echo "<p>Files in razorpay-php directory:</p><ul>";
        $files = scandir('razorpay-php');
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                echo "<li>$file</li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>✗ razorpay-php directory does not exist</p>";
        echo "<p>You need to download the Razorpay PHP SDK from <a href='https://github.com/razorpay/razorpay-php/releases' target='_blank'>GitHub</a> and extract it to the razorpay-php directory.</p>";
    }
}

// Check if connect.php exists
if (file_exists('../connect.php')) {
    echo "<p style='color:green'>✓ ../connect.php exists</p>";
} else {
    echo "<p style='color:red'>✗ ../connect.php does not exist</p>";
}

// Check if the checkout directory exists
if (is_dir('checkout')) {
    echo "<p style='color:green'>✓ checkout directory exists</p>";
    
    // Check for automatic.php
    if (file_exists('checkout/automatic.php')) {
        echo "<p style='color:green'>✓ checkout/automatic.php exists</p>";
    } else {
        echo "<p style='color:orange'>⚠ checkout/automatic.php does not exist</p>";
    }
} else {
    echo "<p style='color:orange'>⚠ checkout directory does not exist</p>";
    echo "<p>Creating checkout directory...</p>";
    
    if (mkdir('checkout', 0755)) {
        echo "<p style='color:green'>✓ Successfully created checkout directory</p>";
    } else {
        echo "<p style='color:red'>✗ Failed to create checkout directory</p>";
    }
}

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Make sure all the red ✗ items above are fixed</li>";
echo "<li>If the Razorpay SDK is missing, download it from <a href='https://github.com/razorpay/razorpay-php/releases' target='_blank'>GitHub</a></li>";
echo "<li>Once everything is green, try the payment process again</li>";
echo "</ol>";

echo "<p><a href='pay.php' style='display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Try Payment Page</a></p>";
?>