<?php
/**
 * GD Library Enable Instructions for XAMPP
 * 
 * This script helps you find the correct php.ini file and shows instructions
 */

// Find php.ini location
$phpIniPath = php_ini_loaded_file();
$phpIniScanned = php_ini_scanned_files();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enable GD Library - XAMPP Instructions</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 30px auto;
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
            color: #dc3545;
            border-bottom: 3px solid #dc3545;
            padding-bottom: 10px;
        }
        h2 {
            color: #495057;
            margin-top: 30px;
        }
        .step {
            background: #f8f9fa;
            padding: 20px;
            margin: 15px 0;
            border-left: 4px solid #007bff;
            border-radius: 4px;
        }
        .step-number {
            background: #007bff;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }
        .code-block code {
            color: #f8f8f2;
        }
        .highlight {
            background: #fff3cd;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }
        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .path-info {
            background: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            word-break: break-all;
            margin: 10px 0;
        }
        ul {
            line-height: 1.8;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Enable GD Library in XAMPP</h1>
        
        <div class="info-box">
            <strong>Current Status:</strong> GD Library is <strong>NOT ENABLED</strong><br>
            <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?><br>
            <strong>Server API:</strong> <?php echo php_sapi_name(); ?>
        </div>

        <h2>📍 Step 1: Locate php.ini File</h2>
        
        <div class="step">
            <span class="step-number">1</span>
            <strong>Find your php.ini file location:</strong>
            <div class="path-info">
                <strong>Loaded php.ini:</strong> <?php echo $phpIniPath ? htmlspecialchars($phpIniPath) : 'Not found'; ?>
            </div>
            
            <?php if($phpIniPath): ?>
                <div class="success-box">
                    ✓ php.ini file found! You can edit it directly.
                </div>
            <?php else: ?>
                <div class="warning-box">
                    ⚠ php.ini file not found. Common locations in XAMPP:
                    <ul>
                        <li><code>C:\xampp\php\php.ini</code></li>
                        <li><code>C:\xampp1\php\php.ini</code></li>
                        <li><code>C:\Program Files\xampp\php\php.ini</code></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <h2>✏️ Step 2: Edit php.ini File</h2>
        
        <div class="step">
            <span class="step-number">2</span>
            <strong>Open php.ini in a text editor (Notepad++, VS Code, or Notepad)</strong>
            <ul>
                <li>Right-click on php.ini file</li>
                <li>Select "Open with" → Choose a text editor</li>
                <li><strong>Important:</strong> You may need to run the editor as Administrator</li>
            </ul>
        </div>

        <div class="step">
            <span class="step-number">3</span>
            <strong>Search for GD extension</strong>
            <ul>
                <li>Press <kbd>Ctrl + F</kbd> to open search</li>
                <li>Search for: <code>extension=gd</code></li>
                <li>You'll find a line that looks like: <code>;extension=gd</code></li>
            </ul>
        </div>

        <div class="step">
            <span class="step-number">4</span>
            <strong>Enable GD extension</strong>
            <p>Find this line (it will have a semicolon <span class="highlight">;</span> at the beginning):</p>
            <div class="code-block">
;extension=gd
            </div>
            <p>Remove the semicolon to uncomment it:</p>
            <div class="code-block">
extension=gd
            </div>
            <div class="warning-box">
                <strong>Note:</strong> Make sure there's NO semicolon (<code>;</code>) at the beginning of the line!
            </div>
        </div>

        <h2>🔄 Step 3: Restart Apache</h2>
        
        <div class="step">
            <span class="step-number">5</span>
            <strong>Restart Apache in XAMPP Control Panel</strong>
            <ol>
                <li>Open <strong>XAMPP Control Panel</strong></li>
                <li>Click <strong>Stop</strong> button next to Apache</li>
                <li>Wait a few seconds</li>
                <li>Click <strong>Start</strong> button next to Apache</li>
                <li>Make sure Apache status shows as "Running" (green)</li>
            </ol>
        </div>

        <h2>✅ Step 4: Verify GD is Enabled</h2>
        
        <div class="step">
            <span class="step-number">6</span>
            <strong>Check if GD is now enabled</strong>
            <p>After restarting Apache, refresh this page or check:</p>
            <ul>
                <li><a href="check_gd.php" class="btn">Check GD Status</a></li>
                <li>Or run: <code>php tests/check_gd_cli.php</code> from command line</li>
            </ul>
        </div>

        <h2>📝 Alternative: Quick Edit Method</h2>
        
        <div class="info-box">
            <strong>Quick Method (if you know the php.ini path):</strong>
            <ol>
                <li>Open Command Prompt as Administrator</li>
                <li>Navigate to XAMPP php folder: <code>cd C:\xampp1\php</code></li>
                <li>Run: <code>notepad php.ini</code></li>
                <li>Search for <code>;extension=gd</code></li>
                <li>Remove the semicolon</li>
                <li>Save and close</li>
                <li>Restart Apache in XAMPP Control Panel</li>
            </ol>
        </div>

        <h2>🔍 Troubleshooting</h2>
        
        <div class="step">
            <strong>If GD still doesn't work after enabling:</strong>
            <ul>
                <li><strong>Check if php.ini is the correct one:</strong> The path shown above should match your XAMPP installation</li>
                <li><strong>Verify the line is uncommented:</strong> Make sure there's NO semicolon before <code>extension=gd</code></li>
                <li><strong>Check for multiple php.ini files:</strong> Sometimes there are multiple php.ini files. Make sure you edit the one that's actually being used</li>
                <li><strong>Restart Apache properly:</strong> Stop and Start Apache (don't just restart)</li>
                <li><strong>Check Apache error log:</strong> Look in <code>C:\xampp1\apache\logs\error.log</code> for any errors</li>
                <li><strong>Verify PHP version:</strong> Make sure the GD extension file exists: <code>C:\xampp1\php\ext\php_gd2.dll</code></li>
            </ul>
        </div>

        <div class="step">
            <strong>Check if GD DLL file exists:</strong>
            <div class="code-block">
C:\xampp1\php\ext\php_gd2.dll
            </div>
            <p>If this file doesn't exist, you may need to download it or reinstall XAMPP.</p>
        </div>

        <h2>📋 Summary</h2>
        <div class="success-box">
            <strong>Quick Checklist:</strong>
            <ol>
                <li>✓ Find php.ini file (shown above)</li>
                <li>□ Open php.ini in text editor</li>
                <li>□ Find <code>;extension=gd</code></li>
                <li>□ Remove semicolon: <code>extension=gd</code></li>
                <li>□ Save php.ini file</li>
                <li>□ Restart Apache in XAMPP Control Panel</li>
                <li>□ Verify GD is enabled (check status page)</li>
            </ol>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="check_gd.php" class="btn">Check GD Status Now</a>
            <a href="check_gd.php" class="btn" style="background: #28a745;">Refresh After Enabling</a>
        </div>
    </div>
</body>
</html>

