<?php
// Handle card_number from URL, session, or cookie
// MUST be done before any output (before including header.php)
require_once(__DIR__ . '/../../app/config/database.php');

// Handle AJAX image processing FIRST - before any other output
// This must be at the very top to prevent any output before JSON response
if(isset($_POST['process_logo_ajax']) && !empty($_FILES['d_logo']['tmp_name'])){
    // Suppress any output and set JSON header
    ob_start();
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    try {
        // Include file validation functions
        $file_validation_path = __DIR__ . '/../../includes/file_validation.php';
        if(file_exists($file_validation_path)) {
            require_once $file_validation_path;
        } elseif(file_exists('../../includes/file_validation.php')) {
            require_once '../../includes/file_validation.php';
        } elseif(file_exists('../includes/file_validation.php')) {
            require_once '../includes/file_validation.php';
        } elseif(file_exists('includes/file_validation.php')) {
            require_once 'includes/file_validation.php';
        } else {
            throw new Exception('File validation library not found');
        }
        
        if(!function_exists('processImageUploadWithAutoCrop')) {
            throw new Exception('Image processing function not available');
        }
        
        // Check file upload error
        if($_FILES['d_logo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $_FILES['d_logo']['error']);
        }
        
        $result = processImageUploadWithAutoCrop(
            $_FILES['d_logo'], 
            600,      // Target size: 600x600
            250000,   // Target file size: 250KB
            200000,   // Min file size: 200KB
            300000,   // Max file size: 300KB
            ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
            'jpeg',
            null
        );
        
        if($result['status']) {
            // Return processed image as base64 for immediate preview
            $base64Image = base64_encode($result['data']);
            
            // Clear any output buffer
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => true,
                'image_data' => $base64Image,
                'dimensions' => isset($result['dimensions']) ? $result['dimensions'] : ['width' => 600, 'height' => 600],
                'file_size' => isset($result['file_size']) ? $result['file_size'] : 0,
                'message' => 'Image processed successfully'
            ]);
            
            // Clean up temp file
            if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                @unlink($result['file_path']);
            }
        } else {
            // Clear any output buffer
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            
            $errorMsg = isset($result['message']) ? strip_tags($result['message']) : 'Unknown error processing image';
            echo json_encode([
                'success' => false,
                'message' => $errorMsg
            ]);
        }
    } catch(Exception $e) {
        // Clear any output buffer
        while(ob_get_level() > 0) {
            ob_end_clean();
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    } catch(Error $e) {
        // Clear any output buffer
        while(ob_get_level() > 0) {
            ob_end_clean();
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $e->getMessage()
        ]);
    }
    
    exit;
}

// Handle AJAX hero image processing FIRST - before any other output
// This must be at the very top to prevent any output before JSON response
if(isset($_POST['process_hero_image_ajax']) && !empty($_FILES['d_hero_image']['tmp_name'])){
    // Suppress any output and set JSON header
    ob_start();
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    try {
        // Include file validation functions
        $file_validation_path = __DIR__ . '/../../includes/file_validation.php';
        if(file_exists($file_validation_path)) {
            require_once $file_validation_path;
        } elseif(file_exists('../../includes/file_validation.php')) {
            require_once '../../includes/file_validation.php';
        } elseif(file_exists('../includes/file_validation.php')) {
            require_once '../includes/file_validation.php';
        } elseif(file_exists('includes/file_validation.php')) {
            require_once 'includes/file_validation.php';
        } else {
            throw new Exception('File validation library not found');
        }
        
        if(!function_exists('processImageUploadWithAutoCrop')) {
            throw new Exception('Image processing function not available');
        }
        
        // Check file upload error
        if($_FILES['d_hero_image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $_FILES['d_hero_image']['error']);
        }
        
        $result = processImageUploadWithAutoCrop(
            $_FILES['d_hero_image'], 
            1200,     // Target size: 1200x400 (wider aspect ratio for hero)
            500000,   // Target file size: 500KB
            300000,   // Min file size: 300KB
            600000,   // Max file size: 600KB
            ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
            'jpeg',
            null
        );
        
        if($result['status']) {
            // Return processed image as base64 for immediate preview
            $base64Image = base64_encode($result['data']);
            
            // Clear any output buffer
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => true,
                'image_data' => $base64Image,
                'dimensions' => isset($result['dimensions']) ? $result['dimensions'] : ['width' => 1200, 'height' => 400],
                'file_size' => isset($result['file_size']) ? $result['file_size'] : 0,
                'message' => 'Hero image processed successfully'
            ]);
            
            // Clean up temp file
            if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                @unlink($result['file_path']);
            }
        } else {
            // Clear any output buffer
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            
            $errorMsg = isset($result['message']) ? strip_tags($result['message']) : 'Unknown error processing image';
            echo json_encode([
                'success' => false,
                'message' => $errorMsg
            ]);
        }
    } catch(Exception $e) {
        // Clear any output buffer
        while(ob_get_level() > 0) {
            ob_end_clean();
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    } catch(Error $e) {
        // Clear any output buffer
        while(ob_get_level() > 0) {
            ob_end_clean();
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $e->getMessage()
        ]);
    }
    
    exit;
}

if(isset($_GET['card_number']) && !empty($_GET['card_number'])){
    $card_number = mysqli_real_escape_string($connect, $_GET['card_number']);
    $_SESSION['card_id_inprocess'] = $card_number;
    // Store in cookie for 24 hours
    setcookie('card_id_inprocess', $card_number, time() + (86400 * 1), '/');
} elseif(isset($_COOKIE['card_id_inprocess']) && !empty($_COOKIE['card_id_inprocess'])) {
    // If card_number not in URL but exists in cookie, restore to session
    $_SESSION['card_id_inprocess'] = $_COOKIE['card_id_inprocess'];
}

// Get current card data
if(!isset($_SESSION['card_id_inprocess']) || empty($_SESSION['card_id_inprocess'])) {
    header('Location: business-name.php');
    exit;
}

$query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');

if(mysqli_num_rows($query) == 0){
    echo '<script>alert("Card id does not match with your email account"); window.location.href="business-name.php";</script>';
    exit;
} else {
    // Use a dedicated variable to avoid collisions with included files (e.g. header.php)
    $cardRow = mysqli_fetch_array($query);
}

// Handle form submission
if(isset($_POST['process2'])){
    $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'"');
    if(mysqli_num_rows($query) == 1){
        
        // Include file validation functions - use absolute path from config
        $file_validation_path = __DIR__ . '/../../includes/file_validation.php';
        if(file_exists($file_validation_path)) {
            require_once $file_validation_path;
        } elseif(file_exists('../../includes/file_validation.php')) {
            require_once '../../includes/file_validation.php';
        } elseif(file_exists('../includes/file_validation.php')) {
            require_once '../includes/file_validation.php';
        } elseif(file_exists('includes/file_validation.php')) {
            require_once 'includes/file_validation.php';
        } else {
            // Fallback to original code if file_validation.php doesn't exist
            function compressImage($source,$destination,$quality){
                $imageInfo=getimagesize($source);
                $mime=$imageInfo['mime'];
                switch($mime){
                    case 'image/jpeg':
                    $image=imagecreatefromjpeg($source);
                    break;
                    case 'image/png':
                    $image=imagecreatefrompng($source);
                    break;
                    case 'image/gif':
                    $image=imagecreatefromgif($source);
                    break;
                    default:
                    $image=imagecreatefromjpeg($source);
                }
                imagejpeg($image,$destination,$quality);
                return $destination;
            }
        }

        // Ensure company-details upload directory exists (store logo file here)
        $companyDetailsUploadDirAbs = __DIR__ . '/../../assets/upload/websites/company_details/';
        if (!is_dir($companyDetailsUploadDirAbs)) {
            if (!@mkdir($companyDetailsUploadDirAbs, 0775, true) && !is_dir($companyDetailsUploadDirAbs)) {
                $error_message = '<div class="alert alert-danger">Upload directory not available. Please create: assets/upload/websites/company_details</div>';
            }
        }
        
        // Process logo upload if file is selected
        // Check if we have processed image data from AJAX (base64)
        if(!empty($_POST['processed_logo_data'])){
            // Use the processed image data from AJAX
            $logoData = base64_decode($_POST['processed_logo_data']);
            $logo = addslashes($logoData);
            
            // Update database with processed image
            $updateLogo = mysqli_query($connect, 'UPDATE digi_card SET d_logo="'.$logo.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
            
            // Also save to file system
            $fileNameOnly = date('ymdsih') . '_logo.jpg';
            $filePathAbs = $companyDetailsUploadDirAbs . $fileNameOnly;
            if(empty($error_message) && file_put_contents($filePathAbs, $logoData) !== false) {
                // Save ONLY the filename in DB
                $update = mysqli_query($connect, 'UPDATE digi_card SET d_logo_location="'.$fileNameOnly.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
            }
        } elseif(!empty($_FILES['d_logo']['tmp_name'])){
            // Use the new automatic crop and resize function
            if(function_exists('processImageUploadWithAutoCrop')) {
                // Process image: auto crop to 1:1, resize to 600x600, compress to ~250KB
                $result = processImageUploadWithAutoCrop(
                    $_FILES['d_logo'], 
                    600,      // Target size: 600x600
                    250000,   // Target file size: 250KB
                    200000,   // Min file size: 200KB
                    300000,   // Max file size: 300KB
                    ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'], // Allowed types
                    'jpeg',   // Output format
                    null      // No specific destination, use temp file
                );
                
                if($result['status']) {
                    // Image processed successfully - get binary data
                    $logo = addslashes($result['data']);
                    
                    // Update database with processed image
                    $updateLogo = mysqli_query($connect, 'UPDATE digi_card SET d_logo="'.$logo.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                    
                    // Also save to file system with original filename
                    $safeOriginal = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['d_logo']['name']));
                    $fileNameOnly = date('ymdsih') . '_' . $safeOriginal;
                    // Change extension to .jpg since output is always JPEG
                    $fileNameOnly = preg_replace('/\.[^.]+$/', '.jpg', $fileNameOnly);
                    $filePathAbs = $companyDetailsUploadDirAbs . $fileNameOnly;
                    
                    // Copy the processed file to company_details upload directory
                    if(empty($error_message) && $result['file_path'] && file_exists($result['file_path'])) {
                        if(copy($result['file_path'], $filePathAbs)) {
                            // Save ONLY the filename in DB
                            $update = mysqli_query($connect, 'UPDATE digi_card SET d_logo_location="'.$fileNameOnly.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                        }
                        // Clean up temp file
                        @unlink($result['file_path']);
                    }
                } else {
                    // Display error message
                    $error_message = $result['message'];
                }
            } else {
                // Fallback to compression function if auto crop is not available
                if(function_exists('processImageUploadWithCompression')) {
                    $result = processImageUploadWithCompression($_FILES['d_logo'], 55, 250000, ['png', 'jpeg', 'jpg']);
                    
                    if($result['status']) {
                        $logo = $result['data'];
                        $updateLogo = mysqli_query($connect, 'UPDATE digi_card SET d_logo="'.$logo.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                        
                        $safeOriginal = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['d_logo']['name']));
                        $fileNameOnly = date('ymdsih') . '_' . $safeOriginal;
                        $filePathAbs = $companyDetailsUploadDirAbs . $fileNameOnly;
                        if(empty($error_message) && copy($_FILES['d_logo']['tmp_name'], $filePathAbs)) {
                            // Save ONLY the filename in DB
                            $update = mysqli_query($connect, 'UPDATE digi_card SET d_logo_location="'.$fileNameOnly.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                        }
                    } else {
                        $error_message = $result['message'];
                    }
                } else {
                    // Final fallback to original code
                    $filename = $_FILES['d_logo']['name'];
                    $imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $file_allow = array('png', 'jpeg', 'jpg');
                    
                    if(in_array($imageFileType, $file_allow)) {
                        if($_FILES['d_logo']['size'] <= 250000) {
                            $source = $_FILES["d_logo"]['tmp_name'];
                            $destination = $_FILES["d_logo"]['tmp_name'];
                            $quality = 55;
                            
                            $compressimage = compressImage($source, $destination, $quality);
                            $logo = addslashes(file_get_contents($compressimage));
                            $safeOriginal = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['d_logo']['name']));
                            $fileNameOnly = date('ymdsih') . '_' . $safeOriginal;
                            $filePathAbs = $companyDetailsUploadDirAbs . $fileNameOnly;
                            
                            if(empty($error_message) && move_uploaded_file($compressimage, $filePathAbs)) {
                                // Save ONLY the filename in DB
                                $update = mysqli_query($connect, 'UPDATE digi_card SET d_logo_location="'.$fileNameOnly.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                            } else {
                                $error_message = '<div class="alert alert-danger">Image Not uploaded</div>';
                            }
                            
                            $updateLogo = mysqli_query($connect, 'UPDATE digi_card SET d_logo="'.$logo.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                        } else {
                            $error_message = '<div class="alert alert-danger">File size exceeds 250KB limit. Please resize your image.</div>';
                        }
                    } else {
                        $error_message = '<div class="alert alert-danger">Only PNG, JPG, JPEG files allowed</div>';
                    }
                }
            }
        }
        
        // Process hero image upload if file is selected
        // Check if we have processed image data from AJAX (base64)
        if(!empty($_POST['processed_hero_image_data'])){
            // Use the processed image data from AJAX
            $heroImageData = base64_decode($_POST['processed_hero_image_data']);
            
            // Save to file system only
            $heroFileNameOnly = date('ymdsih') . '_hero.jpg';
            $heroFilePathAbs = $companyDetailsUploadDirAbs . $heroFileNameOnly;
            if(empty($error_message) && file_put_contents($heroFilePathAbs, $heroImageData) !== false) {
                // Save ONLY the filename in DB
                $update = mysqli_query($connect, 'UPDATE digi_card SET d_hero_image_location="'.$heroFileNameOnly.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
            }
        } elseif(!empty($_FILES['d_hero_image']['tmp_name'])){
            // Use the new automatic crop and resize function
            if(function_exists('processImageUploadWithAutoCrop')) {
                // Process image: auto crop, resize to 1200x400, compress
                $result = processImageUploadWithAutoCrop(
                    $_FILES['d_hero_image'], 
                    1200,     // Target size: 1200x400
                    500000,   // Target file size: 500KB
                    300000,   // Min file size: 300KB
                    600000,   // Max file size: 600KB
                    ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'], // Allowed types
                    'jpeg',   // Output format
                    null      // No specific destination, use temp file
                );
                
                if($result['status']) {
                    // Image processed successfully - save to file system only
                    $safeOriginal = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['d_hero_image']['name']));
                    $heroFileNameOnly = date('ymdsih') . '_' . $safeOriginal;
                    $heroFilePathAbs = $companyDetailsUploadDirAbs . $heroFileNameOnly;
                    if(empty($error_message) && file_put_contents($heroFilePathAbs, $result['data']) !== false) {
                        // Save ONLY the filename in DB (no binary data)
                        $update = mysqli_query($connect, 'UPDATE digi_card SET d_hero_image_location="'.$heroFileNameOnly.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                    }
                } else {
                    $error_message = '<div class="alert alert-danger">Error processing hero image: ' . (isset($result['message']) ? $result['message'] : 'Unknown error') . '</div>';
                }
            }
        }
        
        // Escape special characters for certain fields
        $d_about_us = str_replace(array("'",'"',';','(',')','"','"',':','%','`','[',']'), array("\'",'\"','\;','\(','\)','\"','\"','\:','\%','\`','\[','\]'), $_POST['d_about_us']);
        $d_address = str_replace(array("'",'"',';','(',')','"','"',':','%','`','[',']'), array("\'",'\"','\;','\(','\)','\"','\"','\:','\%','\`','\[','\]'), $_POST['d_address']);
        $d_address2 = str_replace(array("'",'"',';','(',')','"','"',':','%','`','[',']'), array("\'",'\"','\;','\(','\)','\"','\"','\:','\%','\`','\[','\]'), isset($_POST['d_address2']) ? $_POST['d_address2'] : '');
        $d_comp_est_date = str_replace(array("'",'"',';','(',')','"','"',':','%','`','[',']'), array("\'",'\"','\;','\(','\)','\"','\"','\:','\%','\`','\[','\]'), $_POST['d_comp_est_date']);

        // Helper to add missing columns (no-op if exists)
        function ensureColumnExists($connect, $table, $column, $definition){
            $res = @mysqli_query($connect, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
            if(!$res || mysqli_num_rows($res) == 0){
                @mysqli_query($connect, "ALTER TABLE `{$table}` ADD `{$column}` {$definition}");
            }
        }

        // Ensure required columns exist in digi_card (safe to call on every save)
        ensureColumnExists($connect, 'digi_card', 'd_position_primary', "VARCHAR(200) DEFAULT ''");
        ensureColumnExists($connect, 'digi_card', 'd_position_secondary', "VARCHAR(200) DEFAULT ''");
        ensureColumnExists($connect, 'digi_card', 'd_contact', "VARCHAR(50) DEFAULT ''");
        ensureColumnExists($connect, 'digi_card', 'd_contact2', "VARCHAR(50) DEFAULT ''");
        ensureColumnExists($connect, 'digi_card', 'd_whatsapp', "VARCHAR(50) DEFAULT ''");
        ensureColumnExists($connect, 'digi_card', 'd_gst_number', "VARCHAR(100) DEFAULT ''");
        ensureColumnExists($connect, 'digi_card', 'd_comp_est_date', "DATE NULL");
        ensureColumnExists($connect, 'digi_card', 'd_website', "VARCHAR(255) DEFAULT ''");
        ensureColumnExists($connect, 'digi_card', 'd_address2', "VARCHAR(500) DEFAULT ''");
        ensureColumnExists($connect, 'digi_card', 'd_city', "VARCHAR(200) DEFAULT ''");
        ensureColumnExists($connect, 'digi_card', 'd_state', "VARCHAR(200) DEFAULT ''");
        ensureColumnExists($connect, 'digi_card', 'd_pincode', "VARCHAR(50) DEFAULT ''");
        ensureColumnExists($connect, 'digi_card', 'd_country', "VARCHAR(200) DEFAULT ''");

        // Sanitize new fields (fall back to empty string when not provided)
        $d_position_primary = isset($_POST['d_position_primary']) ? mysqli_real_escape_string($connect, $_POST['d_position_primary']) : '';
        $d_position_secondary = isset($_POST['d_position_secondary']) ? mysqli_real_escape_string($connect, $_POST['d_position_secondary']) : '';
        $d_city = isset($_POST['d_city']) ? mysqli_real_escape_string($connect, $_POST['d_city']) : '';
        $d_state = isset($_POST['d_state']) ? mysqli_real_escape_string($connect, $_POST['d_state']) : '';
        $d_pincode = isset($_POST['d_pincode']) ? mysqli_real_escape_string($connect, $_POST['d_pincode']) : '';
        $d_country = isset($_POST['d_country']) ? mysqli_real_escape_string($connect, $_POST['d_country']) : '';
        $d_gst_number = isset($_POST['d_gst_number']) ? mysqli_real_escape_string($connect, $_POST['d_gst_number']) : '';

        $update = mysqli_query($connect, 'UPDATE digi_card SET 
        d_f_name="'.mysqli_real_escape_string($connect, $_POST['d_f_name']).'",
        d_l_name="'.mysqli_real_escape_string($connect, $_POST['d_l_name']).'",
        d_position_primary="'.$d_position_primary.'",
        d_position_secondary="'.$d_position_secondary.'",
        d_position="'.(isset($_POST['d_position']) ? mysqli_real_escape_string($connect, $_POST['d_position']) : $d_position_primary).'",
        d_contact="'.mysqli_real_escape_string($connect, $_POST['d_contact']).'",
        d_contact2="'.mysqli_real_escape_string($connect, $_POST['d_contact2']).'",
        d_whatsapp="'.mysqli_real_escape_string($connect, $_POST['d_whatsapp']).'",
        d_gst_number="'.$d_gst_number.'",
        d_address="'.$d_address.'",
        d_address2="'.$d_address2.'",
        d_city="'.$d_city.'",
        d_state="'.$d_state.'",
        d_pincode="'.$d_pincode.'",
        d_country="'.$d_country.'",
        d_email="'.mysqli_real_escape_string($connect, $_POST['d_email']).'",
        d_website="'.mysqli_real_escape_string($connect, $_POST['d_website']).'",
        d_location="'.mysqli_real_escape_string($connect, $_POST['d_location']).'",
        d_comp_est_date="'.mysqli_real_escape_string($connect, $d_comp_est_date).'",
        d_about_us="'.$d_about_us.'"
        WHERE id="'.$_SESSION['card_id_inprocess'].'"');
        
        if($update){
            $_SESSION['save_success'] = "Details Updated Successfully!";
            // Re-fetch updated record so fields show latest saved values
            $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');
            if($query && mysqli_num_rows($query) > 0){
                $cardRow = mysqli_fetch_array($query);
            }
            // Redirect if possible (prevents form resubmission on refresh)
            if (!headers_sent()) {
                header('Location: company-details.php?card_number='.$_SESSION['card_id_inprocess']);
                exit;
            }
        } else {
            $_SESSION['save_error'] = "Error! Try Again.";
            // If headers already sent, fall through and show page with existing $row
            if (!headers_sent()) {
                header('Location: company-details.php?card_number='.$_SESSION['card_id_inprocess']);
                exit;
            }
        }
    } else {
        $_SESSION['save_error'] = "Detail Not Available. Try Again.";
        header('Location: company-details.php?card_number='.$_SESSION['card_id_inprocess']);
        exit;
    }
}

include '../includes/header.php';
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
    
        <div class="main-top">
        <span class="heading">Company Details</span>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
                </ol>
            </nav>
        </div>
        
        <?php if(isset($_SESSION['save_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['save_success']; unset($_SESSION['save_success']); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['save_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['save_error']; unset($_SESSION['save_error']); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <label class="heading heading1">Company Details:</label>
                <form action="" method="POST" enctype="multipart/form-data" id="card_form">
                    <!-- Hidden field to store processed image data -->
                     <div class="row">
                        <div class="col-sm-6">
                        <input type="hidden" name="processed_logo_data" id="processed_logo_data" value="">
                        <div class="upload-container">
                        <div class="logo-placeholder" id="logoPreview" onclick="clickFocusLogo()">
                            <?php if(!empty($cardRow['d_logo'])): ?>
                                <img id="showPreviewLogo" src="data:image/*;base64,<?php echo base64_encode($cardRow['d_logo']); ?>" alt="Logo Preview">
                            <?php else: ?>
                                <span>YOUR LOGO</span>
                                <img id="showPreviewLogo" style="display:none;">
                            <?php endif; ?>
                        </div>
                        <div class="file-info">File Supported - .png, .jpg, .jpeg, .gif, .webp</div>
                        
                        <p class="addlogo">Add your Logo</p>
                        <div class="file-upload">
                            <div id="fileContainer">
                                <span id="fileName">No File Chosen</span>
                            </div>                                        
                            <label for="clickMeImage" class="choose-btn">Choose File</label>
                            <input type="file" name="d_logo" id="clickMeImage" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" style="display:none;">
                        </div>
                        </div>
                        </div>

                        <div class="col-sm-6">
                        <input type="hidden" name="processed_hero_image_data" id="processed_hero_image_data" value="">
                        <div class="upload-container">
                        <div class="logo-placeholder" id="heroImagePreview" onclick="clickFocusHero()">
                            <?php if(!empty($cardRow['d_hero_image_location'])): ?>
                                <img id="showPreviewHero" src="../../assets/upload/websites/company_details/<?php echo htmlspecialchars($cardRow['d_hero_image_location']); ?>" alt="Hero Image Preview">
                            <?php else: ?>
                                <span>YOUR HERO IMAGE</span>
                                <img id="showPreviewHero" style="display:none;">
                            <?php endif; ?>
                        </div>
                        <div class="file-info">File Supported - .png, .jpg, .jpeg, .gif, .webp</div>
                        
                        <p class="addlogo">Add Hero Image</p>
                        <div class="file-upload">
                            <div id="fileContainerHero">
                                <span id="fileNameHero">No File Chosen</span>
                            </div>                                        
                            <label for="clickMeImageHero" class="choose-btn">Choose File</label>
                            <input type="file" name="d_hero_image" id="clickMeImageHero" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" style="display:none;">
                        </div>
                        </div>
                        </div>
                    </div>
                   
                    <div class="Personal-Details">
                        <label class="heading heading2">Personal Details:</label>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_f_name">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="d_f_name" id="d_f_name" maxlength="20" placeholder="Enter First Name" class="form-control" value="<?php echo !empty($cardRow['d_f_name']) ? htmlspecialchars($cardRow['d_f_name']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_l_name">Last Name (Optional)</label>
                                    <input type="text" name="d_l_name" id="d_l_name" maxlength="20" placeholder="Enter Last Name (Optional)" class="form-control" value="<?php echo !empty($cardRow['d_l_name']) ? htmlspecialchars($cardRow['d_l_name']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_f_name">Business Category(Primary) <span class="text-danger">*</span></label>
                                    <select name="d_position_primary" id="d_position_primary" class="form-control">
                                        <option value="">Select Position</option>
                                        <option value="Manager">Manager</option>
                                        <option value="Director">Director</option>
                                        <option value="CEO">CEO</option>
                                        <option value="CTO">CTO</option>
                                        <option value="CFO">CFO</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6">
                            <div class="form-group">
                                    <label for="d_f_name">Business Category(secondary)</label>
                                    <select name="d_position_secondary" id="d_position_secondary" class="form-control">
                                        <option value="">Select Business Category(secondary)</option>   
                                        <option value="Agriculture">Agriculture</option>
                                        <option value="Automotive">Automotive</option>
                                        <option value="Banking">Banking</option>
                                        <option value="Beauty & Spa">Beauty & Spa</option>
                                        <option value="Beverages">Beverages</option>
                                        <option value="Clothing">Clothing</option>
                                        <option value="Construction">Construction</option>
                                        <option value="Education">Education</option>
                                        <option value="Entertainment">Entertainment</option>
                                        <option value="Food & Drink">Food & Drink</option>
                                        <option value="Health & Fitness">Health & Fitness</option>
                                        <option value="Home & Garden">Home & Garden</option>
                                        <option value="Jewelry">Jewelry</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">                           
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_contact">Phone No <span class="text-danger">*</span></label>
                                    <input type="text" name="d_contact" id="d_contact" maxlength="13" placeholder="Enter Phone Number" class="form-control" value="<?php echo !empty($cardRow['d_contact']) ? htmlspecialchars($cardRow['d_contact']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_whatsapp">WhatsApp No <span class="text-danger">*</span></label>
                                    <input type="text" name="d_whatsapp" id="d_whatsapp" maxlength="13" placeholder="Enter WhatsApp Number" class="form-control" value="<?php echo !empty($cardRow['d_whatsapp']) ? htmlspecialchars($cardRow['d_whatsapp']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_contact2">Alternate Phone No (Optional)</label>
                                    <input type="text" name="d_contact2" id="d_contact2" maxlength="13" placeholder="Enter Alternate Phone Number (Optional)" class="form-control" value="<?php echo !empty($cardRow['d_contact2']) ? htmlspecialchars($cardRow['d_contact2']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_email">Email Id <span class="text-danger">*</span></label>
                                    <input type="email" name="d_email" id="d_email" maxlength="100" placeholder="Enter Email Id" class="form-control" value="<?php echo !empty($cardRow['d_email']) ? htmlspecialchars($cardRow['d_email']) : ''; ?>" required>
                                </div>
                            </div>
                            
                        </div>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_contact2">GST Identification Number</label>
                                    <input type="text" name="d_gst_number" id="d_gst_number" maxlength="13" placeholder="Enter GST Identification Number" class="form-control" value="<?php echo !empty($cardRow['d_gst_number']) ? htmlspecialchars($cardRow['d_gst_number']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_email">Company Establishment Date <span class="text-danger">*</span></label>
                                    <input type="date" name="d_comp_est_date" id="d_comp_est_date" maxlength="200" placeholder="Enter Company Establishment Date" class="form-control" value="<?php echo !empty($cardRow['d_comp_est_date']) ? htmlspecialchars($cardRow['d_comp_est_date']) : ''; ?>" required>
                                </div>
                            </div>
                            
                        </div>
                        <div class="row">
                            
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_website">Website Link</label>
                                    <input type="text" name="d_website" id="d_website" maxlength="200" placeholder="Website (Optional)" class="form-control" value="<?php echo !empty($cardRow['d_website']) ? htmlspecialchars($cardRow['d_website']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_location">Add Google Map Link For Direction</label>
                                    <input type="text" name="d_location" id="d_location" maxlength="999" placeholder="Your Business Location (Optional)" class="form-control" value="<?php echo !empty($cardRow['d_location']) ? htmlspecialchars($cardRow['d_location']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                       
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_address">Address Line 1 <span class="text-danger">*</span></label>
                                    <input type="text" name="d_address" id="d_address" maxlength="500" placeholder="Full Address" class="form-control" required value="<?php echo !empty($cardRow['d_address']) ? htmlspecialchars($cardRow['d_address']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_address">Area/Locality<span class="text-danger">*</span></label>
                                    <input type="text" name="d_address2" id="d_address2" maxlength="500" placeholder="Full Address" class="form-control" required value="<?php echo !empty($cardRow['d_address2']) ? htmlspecialchars($cardRow['d_address2']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label for="d_city">City <span class="text-danger">*</span></label>
                                    <input type="text" name="d_city" id="d_city" maxlength="200" placeholder="Enter City" class="form-control" value="<?php echo !empty($cardRow['d_city']) ? htmlspecialchars($cardRow['d_city']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label for="d_state">State <span class="text-danger">*</span></label>
                                    <input type="text" name="d_state" id="d_state" maxlength="200" placeholder="Enter State" class="form-control" value="<?php echo !empty($cardRow['d_state']) ? htmlspecialchars($cardRow['d_state']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label for="d_pincode">Pincode <span class="text-danger">*</span></label>
                                    <input type="text" name="d_pincode" id="d_pincode" maxlength="200" placeholder="Enter Pincode" class="form-control" value="<?php echo !empty($cardRow['d_pincode']) ? htmlspecialchars($cardRow['d_pincode']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label for="d_country">Country <span class="text-danger">*</span></label>
                                    <input type="text" name="d_country" id="d_country" maxlength="200" placeholder="Enter Country" class="form-control" value="<?php echo !empty($cardRow['d_country']) ? htmlspecialchars($cardRow['d_country']) : ''; ?>" required>
                                </div>
                            </div>                            
                        </div>
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="form-group">
                                    <label for="d_about_us">About Us <span class="text-danger">*</span></label>
                                    <textarea name="d_about_us" id="d_about_us" maxlength="1900" placeholder="About your company/business" class="form-control" required><?php echo !empty($cardRow['d_about_us']) ? htmlspecialchars($cardRow['d_about_us']) : ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="Product-ServicesBtn" style="margin-top: 20px;">
                            <a href="select-theme.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left">
                                <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                                <span>Back</span>
                            </a>
                            <button type="submit" name="process2" class="btn btn-primary align-center save_btn">
                                <img src="../../assets/images/Save.png" class="img-fluid" width="35px" alt=""> 
                                <span>Save</span>
                            </button>
                            <a href="social-links.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right">
                                <span>Next</span>
                                <span class="right_angle angle"><i class="fa fa-angle-right"></i></span>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
(function() {
    var logoUploadInited = false;
    function truncateFileName(fileName, maxLength) {
        if (fileName.length <= maxLength) return fileName;
        var ext = fileName.substring(fileName.lastIndexOf('.'));
        var nameWithoutExt = fileName.substring(0, fileName.lastIndexOf('.'));
        return nameWithoutExt.substring(0, maxLength - ext.length - 3) + '...' + ext;
    }
    function initLogoUpload() {
        if (logoUploadInited || typeof window.jQuery === 'undefined' || typeof window.ImageCropUpload === 'undefined') return logoUploadInited;
        logoUploadInited = true;
        var $ = window.jQuery;
        $(document).ready(function(){
            $('#clickMeImage').off('change.logoUpload').on('change.logoUpload', function(){
                if (!this.files || !this.files[0]) return;
                var file = this.files[0];
                $('#fileName').text(truncateFileName(file.name, 25)).attr('title', file.name);
                ImageCropUpload.open(file, {
                    method: 'base64',
                    hiddenField: '#processed_logo_data',
                    previewSelector: '#showPreviewLogo',
                    spanSelector: '#logoPreview span',
                    title: 'Adjust & Crop Logo',
                    onSuccess: function() {},
                    onError: function(msg) { alert(msg); }
                });
                $(this).val('');
            });
            
            // Hero Image Upload Handler
            $('#clickMeImageHero').off('change.heroUpload').on('change.heroUpload', function(){
                if (!this.files || !this.files[0]) return;
                var file = this.files[0];
                $('#fileNameHero').text(truncateFileName(file.name, 25)).attr('title', file.name);
                ImageCropUpload.open(file, {
                    method: 'base64',
                    hiddenField: '#processed_hero_image_data',
                    previewSelector: '#showPreviewHero',
                    spanSelector: '#heroImagePreview span',
                    title: 'Adjust & Crop Hero Image',
                    onSuccess: function() {},
                    onError: function(msg) { alert(msg); }
                });
                $(this).val('');
            });
        });
        return true;
    }
    if (!initLogoUpload()) {
        var check = setInterval(function() { if (initLogoUpload()) clearInterval(check); }, 100);
    }
    window.clickFocusLogo = function() { if (window.jQuery) window.jQuery('#clickMeImage').click(); };
    window.clickFocusHero = function() { if (window.jQuery) window.jQuery('#clickMeImageHero').click(); };
})();
</script>

<style>
        footer{
        margin-bottom:54px;
    }
    
    .upload-container .file-info{
        font-size: 20px;    
    color: #3e3e3e;
    margin-bottom: 10px;
    text-align: center;
    padding-top: 10px;
    line-height: 26px;
    }
    .heading1{
        font-size: 24px !important;
    }
    .heading2{
margin-left: 0px !important;
font-size: 24px !important;
    }
    .file-upload{
        padding:0px;
        padding-left:10px;
    }
    .choose-btn{
        padding:0px 15px;
        margin-bottom:0px;
        font-size:34px;
    }
    .savebutton span{
        font-size:27px;
    }
    .savebutton{
        display: flex !important;
    margin: auto !important;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    }
    .addlogo{
        font-size: 24px !important;
        margin-bottom: 5px;
    }
    .Personal-Details{
        padding: 0px 40px;
    }
    .Personal-Details .heading{
        position:relative;
        margin-bottom:20px;
        
    }
    .upload-container {
    background: white;
    padding: 20px 40px;
    
}
.heading2{
    left:3px;
}
    .Personal-Details .heading:after
    {
        content: '';
    width: 153px;
    height: 2px;
    background: #ffb300;
    position: absolute;
    left: 3px;
    bottom: 0px;
    }
    .heading1:after{
        content: '';
        width: 165px;
    height: 2px;
    background: #ffb300;
    position: absolute;
    left: 57px;
    bottom: 1px;
    }
    .Personal-Details .form-group .form-control{
        min-height:55px !important;
        font-weight:100;
        font-size:16px;
    }
    .Personal-Details .form-group .form-control:focus{
        outline:none;
        box-shadow:none;
    }
    .Personal-Details .form-group .form-control:placeholder{
        font-weight:normal;
    }
    .Personal-Details .form-group label{
        margin-bottom:0px;
        font-size:22px;
        font-weight:100;
    }
    .heading1{
        padding-left:50px;
        position:relative;
        margin-top:15px;
    }
    #fileName{
        padding-left: 20px;
        font-size: 16px !important;
    }
    .savebutton{
        padding:5px 20px;
    }
    .logo-placeholder{
        width:200px;
        height:200px;
        border:2px solid darkgray;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    #showPreviewLogo{
        max-width: 100%;
        max-height: 100%;
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 8px;
    }
    #logoPreview span{
        font-weight: 500;
    font-size: 30px;
    line-height: 33px
    }
    .upload-container .addlogo{
        margin-left:10px;
        margin-top:10px;
    }
    .file-upload{
        height:7vh;
        padding-right:5px;
    }
    .file-upload .choose-btn {
    font-size: 22px !important;
    font-weight: 500;
    padding: 5px 25px;
}
.Personal-Details label{
    margin-left:5px;
}

.Personal-Details input{
    font-size:21px;
}
.Personal-Details textarea{
    height:100px;
}
@media screen and (max-width: 768px) {
    .heading.heading1{
padding-left:0px;
margin-top:0px;
    }
    .heading1:after{
        left:5px;
    }
    .upload-container{
padding:40px 0px;
padding-bottom:0px;
    }
    .upload-container .file-info {
    font-size: 17px;
    color: #3e3e3e;
    margin-bottom: 10px;
    text-align: center;
    padding-top: 10px;
    line-height: 21px;
}
.file-upload {
    height: 6vh;
    padding-right: 5px;
}
.file-upload {
    height: 6vh;
    padding-right: 5px;
}
.file-upload .choose-btn {
    font-size: 14px !important;
    font-weight: 500;
    padding: 5px 10px;
}
#fileName {
    padding-left: 5px;
    font-size: 18px !important;
}
.upload-container .addlogo {
    margin-left: 10px;
    margin-top: 30px;
    font-size: 22px !important;
}
.Personal-Details {
    padding: 0px 0px;
}
.Personal-Details .form-group .form-control {
    min-height: 50px !important;
    font-weight: 100;
    font-size: 18px;
}
.Personal-Details .form-group label {
    margin-bottom: 0px;
    font-size: 21px;
    font-weight: 100;
}
.Dashboard .heading {
    font-size: 22px !important;
}
.heading1:after {
    content: '';
    width: 135px;
    
}
.logo-placeholder {
    width: 130px;
    height: 125px;
    border: 2px solid darkgray;
    text-align: center;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
#logoPreview span {
    font-weight: 500;
    font-size: 26px;
    line-height: 33px;
}
.upload-container {
        padding-top: 20px;
    }
    .upload-container .file-info {
        font-size: 16px;
        
    }
    #fileName {
        padding-left: 5px;
        font-size: 16px !important;
    }
    .Personal-Details .form-group .form-control {
        
        font-size: 16px;
    }
    .Personal-Details .form-group label {
        margin-bottom: 0px;
        font-size: 20px;
        font-weight: 100;
    }
    .Copyright-left,
.Copyright-right{
    padding:0px;
}
.Product-ServicesBtn{
    width: 75% !important;
    padding:0px !important;
            margin-top: 40px !important;
            margin:auto;
}
.save_btn{
    position: absolute;
        bottom: 150px;
        width: 138px !important;
        left: 87px;
        height: 36px;
}
}

.Product-ServicesBtn{
        
        display: flex;
        justify-content: space-between;
        margin-top: 30px;
    }
    .Product-ServicesBtn button,
    .Product-ServicesBtn a{
        display: flex !important;
        color: #fff !important;
        justify-content: center;
        align-items: center;
        gap: 10px;
        text-decoration: none;
    }
    .Product-ServicesBtn button .angle,
    .Product-ServicesBtn a .angle{
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #fff !important;
        color:#000;
        font-weight:bold;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .Product-ServicesBtn button span:not(.angle),
    .Product-ServicesBtn a span:not(.angle){
        font-weight:500;
        font-size:16px;
    }
    .Product-ServicesBtn .align-center{
        padding: 4px 10px;
    }
    .Product-ServicesBtn .align-center img{
        width: 23px;
    }
    .Product-ServicesBtn .align-center span{
        color:#000;
    }

    .Product-ServicesBtn  .btn{
        line-height:24px !important;
    }
    .Product-ServicesBtn button {
        padding: 7px !important;
        margin-top: 22px !important;
    }
</style>

<?php include '../includes/footer.php'; ?>





