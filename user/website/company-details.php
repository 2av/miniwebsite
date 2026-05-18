<?php
// Handle card_number from URL, session, or cookie
// MUST be done before any output (before including header.php)
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/includes/product_categories_helper.php');

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

if (!function_exists('ensureDigiCardPreviousSlugMetaColumns')) {
    function ensureDigiCardPreviousSlugMetaColumn($connect, $column, $definition) {
        $column_esc = mysqli_real_escape_string($connect, $column);
        $res = @mysqli_query($connect, "SHOW COLUMNS FROM `digi_card_previous_slug` LIKE '$column_esc'");
        if (!$res || mysqli_num_rows($res) === 0) {
            @mysqli_query($connect, "ALTER TABLE `digi_card_previous_slug` ADD `$column_esc` $definition");
        }
    }
    function ensureDigiCardPreviousSlugMetaColumns($connect) {
        ensureDigiCardPreviousSlugMetaColumn($connect, 'd_business_profile_type', "VARCHAR(100) NOT NULL DEFAULT ''");
        ensureDigiCardPreviousSlugMetaColumn($connect, 'd_business_type', "VARCHAR(32) NOT NULL DEFAULT ''");
        ensureDigiCardPreviousSlugMetaColumn($connect, 'd_business_operation_area', "VARCHAR(32) NOT NULL DEFAULT ''");
        ensureDigiCardPreviousSlugMetaColumn($connect, 'd_business_operation_locations', 'TEXT NULL');
    }
}
ensureDigiCardPreviousSlugMetaColumns($connect);

// Read overflow/meta fields from digi_card_previous_slug when present (avoids wide digi_card row limit)
$metaQuery = @mysqli_query($connect, 'SELECT d_business_profile_type, d_business_type, d_business_operation_area, d_business_operation_locations FROM digi_card_previous_slug WHERE digi_card_id="'.intval($_SESSION['card_id_inprocess']).'" LIMIT 1');
if ($metaQuery && mysqli_num_rows($metaQuery) > 0) {
    $metaRow = mysqli_fetch_assoc($metaQuery);
    if (is_array($metaRow)) {
        $cardRow['d_business_profile_type'] = isset($metaRow['d_business_profile_type']) ? $metaRow['d_business_profile_type'] : '';
        $cardRow['d_business_type'] = isset($metaRow['d_business_type']) ? $metaRow['d_business_type'] : (isset($cardRow['d_business_type']) ? $cardRow['d_business_type'] : '');
        $cardRow['d_business_operation_area'] = isset($metaRow['d_business_operation_area']) ? $metaRow['d_business_operation_area'] : (isset($cardRow['d_business_operation_area']) ? $cardRow['d_business_operation_area'] : '');
        $cardRow['d_business_operation_locations'] = isset($metaRow['d_business_operation_locations']) ? $metaRow['d_business_operation_locations'] : (isset($cardRow['d_business_operation_locations']) ? $cardRow['d_business_operation_locations'] : '');
    }
}

$business_profile_type_options = getBusinessProfileTypeOptions($connect);

$company_details_user_id = 0;
if (!empty($_SESSION['user_email'])) {
    $user_email_escaped = mysqli_real_escape_string($connect, $_SESSION['user_email']);
    $user_query_cd = mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = LOWER(TRIM('$user_email_escaped')) LIMIT 1");
    if ($user_query_cd && mysqli_num_rows($user_query_cd) > 0) {
        $company_details_user_id = (int) mysqli_fetch_assoc($user_query_cd)['id'];
    }
}

$category_cascade_init = [
    'profile_type' => isset($cardRow['d_business_profile_type']) ? trim($cardRow['d_business_profile_type']) : '',
    'business_model' => isset($cardRow['d_business_type']) ? trim($cardRow['d_business_type']) : '',
    'primary_id' => !empty($cardRow['d_position_primary']) ? (int) $cardRow['d_position_primary'] : '',
    'secondary_id' => !empty($cardRow['d_position_secondary']) ? (int) $cardRow['d_position_secondary'] : '',
    'user_id' => $company_details_user_id,
];

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
            // Use the processed image data from crop (PNG for transparency, or JPEG)
            $logoData = base64_decode($_POST['processed_logo_data']);
            $logo = addslashes($logoData);
            $ext = (substr($logoData, 0, 8) === "\x89PNG\r\n\x1a\n") ? 'png' : 'jpg';
            
            // Update database with processed image
            $updateLogo = mysqli_query($connect, 'UPDATE digi_card SET d_logo="'.$logo.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
            
            // Also save to file system (preserve PNG for transparent logos)
            $fileNameOnly = date('ymdsih') . '_logo.' . $ext;
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
            // Process hero image: 1200x600 aspect crop
            if(function_exists('processHeroImageUpload')) {
                $result = processHeroImageUpload($_FILES['d_hero_image'], 1200, 600);
            } elseif(function_exists('processImageUploadWithAutoCrop')) {
                $result = processImageUploadWithAutoCrop(
                    $_FILES['d_hero_image'], 600, 500000, 300000, 600000,
                    ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'], 'jpeg', null
                );
            } else {
                $result = ['status' => false, 'message' => 'Image processing not available.'];
            }
            if($result['status']) {
                $heroFileNameOnly = date('ymdsih') . '_hero.jpg';
                $heroFilePathAbs = $companyDetailsUploadDirAbs . $heroFileNameOnly;
                if(empty($error_message) && file_put_contents($heroFilePathAbs, $result['data']) !== false) {
                    $update = mysqli_query($connect, 'UPDATE digi_card SET d_hero_image_location="'.mysqli_real_escape_string($connect, $heroFileNameOnly).'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                }
            } else {
                $error_message = '<div class="alert alert-danger">Error processing hero image: ' . (isset($result['message']) ? htmlspecialchars($result['message']) : 'Unknown error') . '</div>';
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
        function ensurePreviousSlugMetaColumns($connect){
            ensureDigiCardPreviousSlugMetaColumns($connect);
        }
        
        // Helper to convert position columns from VARCHAR to INT (for storing category IDs)
        function convertPositionColumnsToInt($connect, $table, $column) {
            $res = @mysqli_query($connect, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
            if($res && mysqli_num_rows($res) > 0) {
                $col_info = mysqli_fetch_assoc($res);
                // If column is VARCHAR and not empty, we need to convert it
                if(strpos($col_info['Type'], 'VARCHAR') !== false) {
                    // First, convert empty strings and NULL values to prevent conversion errors
                    @mysqli_query($connect, "UPDATE `{$table}` SET `{$column}` = NULL WHERE `{$column}` = '' OR `{$column}` IS NULL OR TRIM(`{$column}`) = ''");
                    // Now modify the column type to INT
                    @mysqli_query($connect, "ALTER TABLE `{$table}` MODIFY `{$column}` INT(11) DEFAULT NULL");
                }
            }
        }

        // Ensure required columns exist in digi_card (safe to call on every save)
        // d_position_primary and d_position_secondary now store category IDs (INT) instead of names
        convertPositionColumnsToInt($connect, 'digi_card', 'd_position_primary');
        convertPositionColumnsToInt($connect, 'digi_card', 'd_position_secondary');
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
        ensureColumnExists($connect, 'digi_card', 'd_business_hours', "TEXT NULL");
        ensureColumnExists($connect, 'digi_card', 'd_hero_image_location', "VARCHAR(500) DEFAULT ''");
        ensurePreviousSlugMetaColumns($connect);

        // Sanitize new fields (fall back to empty string when not provided) - now storing as ID
        $d_position_primary = (isset($_POST['d_position_primary']) && $_POST['d_position_primary'] !== '') ? intval($_POST['d_position_primary']) : null;
        $d_position_secondary = (isset($_POST['d_position_secondary']) && $_POST['d_position_secondary'] !== '') ? intval($_POST['d_position_secondary']) : null;
        $d_city = isset($_POST['d_city']) ? mysqli_real_escape_string($connect, $_POST['d_city']) : '';
        $d_state = isset($_POST['d_state']) ? mysqli_real_escape_string($connect, $_POST['d_state']) : '';
        $d_pincode = isset($_POST['d_pincode']) ? mysqli_real_escape_string($connect, $_POST['d_pincode']) : '';
        $d_country = isset($_POST['d_country']) ? mysqli_real_escape_string($connect, $_POST['d_country']) : '';
        $d_gst_number = isset($_POST['d_gst_number']) ? mysqli_real_escape_string($connect, $_POST['d_gst_number']) : '';

        $d_business_profile_type = isset($_POST['d_business_profile_type']) ? trim($_POST['d_business_profile_type']) : '';
        $allowed_profile_types = getBusinessProfileTypeOptions($connect);
        if ($d_business_profile_type !== '' && !empty($allowed_profile_types) && !in_array($d_business_profile_type, $allowed_profile_types, true)) {
            $d_business_profile_type = '';
        }

        $d_business_type = isset($_POST['d_business_type']) ? trim($_POST['d_business_type']) : '';
        $allowed_business_types = ['product', 'service', 'hybrid'];
        if ($d_business_type !== '' && !in_array($d_business_type, $allowed_business_types, true)) {
            $d_business_type = '';
        }
        $d_business_operation_area = isset($_POST['d_business_operation_area']) ? trim($_POST['d_business_operation_area']) : '';
        $allowed_operation_areas = ['local', 'pan_india', 'selected'];
        if ($d_business_operation_area !== '' && !in_array($d_business_operation_area, $allowed_operation_areas, true)) {
            $d_business_operation_area = '';
        }
        $d_business_operation_locations_raw = isset($_POST['d_business_operation_locations']) ? $_POST['d_business_operation_locations'] : '';
        $d_business_operation_locations = '';

        $form_validation_error = '';
        if ($d_business_operation_area === 'selected') {
            if (trim($d_business_operation_locations_raw) === '') {
                $form_validation_error = 'Please select at least one state when "Selected Cities / States" is chosen.';
            } else {
                $decoded_operation_locations = json_decode($d_business_operation_locations_raw, true);
                if (is_array($decoded_operation_locations) && isset($decoded_operation_locations['states']) && isset($decoded_operation_locations['citiesByState'])) {
                    $states = is_array($decoded_operation_locations['states']) ? $decoded_operation_locations['states'] : [];
                    $citiesByState = is_array($decoded_operation_locations['citiesByState']) ? $decoded_operation_locations['citiesByState'] : [];
                    $states_clean = [];
                    $state_lookup = [];
                    foreach ($states as $state_name) {
                        if (!is_string($state_name)) continue;
                        $state_name = trim($state_name);
                        if ($state_name === '') continue;
                        $state_key = strtolower($state_name);
                        if (!isset($state_lookup[$state_key])) {
                            $state_lookup[$state_key] = true;
                            $states_clean[] = $state_name;
                        }
                    }

                    if (count($states_clean) < 1) {
                        $form_validation_error = 'Please select at least one state.';
                    } elseif (count($states_clean) > 6) {
                        $form_validation_error = 'You can select up to 6 states only.';
                    } else {
                        $country_trim = isset($_POST['d_country']) ? trim($_POST['d_country']) : '';
                        if ($country_trim !== '' && strcasecmp($country_trim, 'India') === 0) {
                            $india_states_path = __DIR__ . '/../../includes/india_states_ut.php';
                            if (file_exists($india_states_path)) {
                                require_once $india_states_path;
                                if (function_exists('mw_india_state_names')) {
                                    $accepted_state_lookup = [];
                                    foreach (mw_india_state_names() as $state_name) {
                                        if (is_string($state_name) && trim($state_name) !== '') {
                                            $accepted_state_lookup[strtolower(trim($state_name))] = true;
                                        }
                                    }
                                    $accepted_state_lookup['orissa'] = true;
                                    $accepted_state_lookup['daman and diu'] = true;
                                    $accepted_state_lookup['dadra and nagar haveli'] = true;
                                    $accepted_state_lookup['jammu & kashmir'] = true;
                                    $accepted_state_lookup['pondicherry'] = true;
                                    foreach ($states_clean as $selected_state_name) {
                                        if (!isset($accepted_state_lookup[strtolower($selected_state_name)])) {
                                            $form_validation_error = 'Please choose valid Indian states/UTs from suggestions only.';
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $cities_by_state_clean = [];
                    $total_city_count = 0;
                    if ($form_validation_error === '') {
                        foreach ($citiesByState as $state_name => $city_list) {
                            if (!is_string($state_name) || !is_array($city_list)) continue;
                            if (!isset($state_lookup[strtolower(trim($state_name))])) continue;

                            $city_seen = [];
                            $clean_cities = [];
                            foreach ($city_list as $city_name) {
                                if (!is_string($city_name)) continue;
                                $city_name = trim($city_name);
                                if ($city_name === '') continue;
                                $city_key = strtolower($city_name);
                                if (!isset($city_seen[$city_key])) {
                                    $city_seen[$city_key] = true;
                                    $clean_cities[] = $city_name;
                                }
                            }

                            if (count($clean_cities) > 4) {
                                $form_validation_error = 'You can choose up to 4 cities per state.';
                                break;
                            }

                            $total_city_count += count($clean_cities);
                            $cities_by_state_clean[$state_name] = $clean_cities;
                        }
                    }

                    if ($form_validation_error === '' && $total_city_count > 24) {
                        $form_validation_error = 'You can choose up to 24 cities in total.';
                    }

                    if ($form_validation_error === '') {
                        $d_business_operation_locations = json_encode(
                            ['states' => $states_clean, 'citiesByState' => $cities_by_state_clean],
                            JSON_UNESCAPED_UNICODE
                        );
                    }
                } else {
                    // Backward compatibility for old free-text values.
                    $d_business_operation_locations = str_replace(
                        array("'", '"', ';', '(', ')', '"', '"', ':', '%', '`', '[', ']'),
                        array("\'", '\"', '\;', '\(', '\)', '\"', '\"', '\:', '\%', '\`', '\[', '\]'),
                        $d_business_operation_locations_raw
                    );
                    $country_trim = isset($_POST['d_country']) ? trim($_POST['d_country']) : '';
                    if ($country_trim !== '' && strcasecmp($country_trim, 'India') === 0) {
                        $india_states_path = __DIR__ . '/../../includes/india_states_ut.php';
                        if (file_exists($india_states_path)) {
                            require_once $india_states_path;
                            if (function_exists('mw_validate_operation_locations_for_india')) {
                                $loc_check = mw_validate_operation_locations_for_india($d_business_operation_locations_raw);
                                if (!$loc_check['ok']) {
                                    $form_validation_error = $loc_check['message'];
                                }
                            }
                        }
                    }
                }
            }
        }

        // Business hours: JSON v2 weekly schedule { version, schedule: { mon: {open, open_time, close_time}, ... } }
        $business_hours_obj = ['version' => 2, 'schedule' => []];
        $bh_day_keys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $bh_schedule_saved = false;
        if (!empty($_POST['business_hours_json'])) {
            $raw_bh = trim((string) $_POST['business_hours_json']);
            $decoded_bh = json_decode($raw_bh, true);
            if (is_array($decoded_bh) && isset($decoded_bh['schedule']) && is_array($decoded_bh['schedule'])) {
                $clean_bh = [];
                foreach ($bh_day_keys as $dk) {
                    $slot = (isset($decoded_bh['schedule'][$dk]) && is_array($decoded_bh['schedule'][$dk]))
                        ? $decoded_bh['schedule'][$dk] : [];
                    $open = !empty($slot['open']);
                    $ot = isset($slot['open_time']) ? trim((string) $slot['open_time']) : '';
                    $ct = isset($slot['close_time']) ? trim((string) $slot['close_time']) : '';
                    if ($ot !== '' && !preg_match('/^\d{2}:\d{2}$/', $ot)) {
                        $ot = '';
                    }
                    if ($ct !== '' && !preg_match('/^\d{2}:\d{2}$/', $ct)) {
                        $ct = '';
                    }
                    if ($open && ($ot === '' || $ct === '')) {
                        $open = false;
                    }
                    $clean_bh[$dk] = [
                        'open' => $open,
                        'open_time' => $ot,
                        'close_time' => $ct,
                    ];
                }
                $business_hours_obj = ['version' => 2, 'schedule' => $clean_bh];
                $bh_schedule_saved = true;
            }
        }
        if (!$bh_schedule_saved && !empty($cardRow['d_business_hours'])) {
            $prev_bh = json_decode($cardRow['d_business_hours'], true);
            if (is_array($prev_bh) && isset($prev_bh['version']) && (int) $prev_bh['version'] === 2 && !empty($prev_bh['schedule']) && is_array($prev_bh['schedule'])) {
                $business_hours_obj = $prev_bh;
            }
        }
        $d_business_hours = mysqli_real_escape_string($connect, json_encode($business_hours_obj));

        $d_business_profile_type_sql = mysqli_real_escape_string($connect, $d_business_profile_type);
        $d_business_type_sql = mysqli_real_escape_string($connect, $d_business_type);
        $d_business_operation_area_sql = mysqli_real_escape_string($connect, $d_business_operation_area);

        if ($form_validation_error !== '') {
            $error_message = '<div class="alert alert-danger">' . htmlspecialchars($form_validation_error) . '</div>';
            $update = false;
        } else {
            $d_position_primary_sql = ($d_position_primary === null) ? 'NULL' : (string)$d_position_primary;
            $d_position_secondary_sql = ($d_position_secondary === null) ? 'NULL' : (string)$d_position_secondary;
            $update = mysqli_query($connect, 'UPDATE digi_card SET 
        d_f_name="'.mysqli_real_escape_string($connect, $_POST['d_f_name']).'",
        d_l_name="'.mysqli_real_escape_string($connect, $_POST['d_l_name']).'",
        d_position_primary='.$d_position_primary_sql.',
        d_position_secondary='.$d_position_secondary_sql.',
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
        d_about_us="'.$d_about_us.'",
        d_business_hours="'.$d_business_hours.'"
        WHERE id="'.$_SESSION['card_id_inprocess'].'"');
        }
        
        if($update){
            // Save overflow/meta fields in digi_card_previous_slug table
            $cid = intval($_SESSION['card_id_inprocess']);
            $prevSlugRes = @mysqli_query($connect, 'SELECT previous_slug FROM digi_card_previous_slug WHERE digi_card_id="'.$cid.'" LIMIT 1');
            if ($prevSlugRes && mysqli_num_rows($prevSlugRes) > 0) {
                @mysqli_query($connect, 'UPDATE digi_card_previous_slug SET d_business_profile_type="'.$d_business_profile_type_sql.'", d_business_type="'.$d_business_type_sql.'", d_business_operation_area="'.$d_business_operation_area_sql.'", d_business_operation_locations="'.mysqli_real_escape_string($connect, $d_business_operation_locations).'" WHERE digi_card_id="'.$cid.'"');
            } else {
                // Keep unique key valid; placeholder uses current active slug when no previous slug exists yet
                $currSlug = isset($cardRow['card_id']) ? mysqli_real_escape_string($connect, (string) $cardRow['card_id']) : ('card-'.$cid);
                @mysqli_query($connect, 'INSERT INTO digi_card_previous_slug (digi_card_id, previous_slug, d_business_profile_type, d_business_type, d_business_operation_area, d_business_operation_locations) VALUES ("'.$cid.'", "'.$currSlug.'", "'.$d_business_profile_type_sql.'", "'.$d_business_type_sql.'", "'.$d_business_operation_area_sql.'", "'.mysqli_real_escape_string($connect, $d_business_operation_locations).'")');
            }
            $_SESSION['save_success'] = "Details Updated Successfully!";
            // Re-fetch updated record so fields show latest saved values
            $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');
            if($query && mysqli_num_rows($query) > 0){
                $cardRow = mysqli_fetch_array($query);
            }
            $metaQuery = @mysqli_query($connect, 'SELECT d_business_profile_type, d_business_type, d_business_operation_area, d_business_operation_locations FROM digi_card_previous_slug WHERE digi_card_id="'.intval($_SESSION['card_id_inprocess']).'" LIMIT 1');
            if ($metaQuery && mysqli_num_rows($metaQuery) > 0) {
                $metaRow = mysqli_fetch_assoc($metaQuery);
                if (is_array($metaRow)) {
                    $cardRow['d_business_profile_type'] = isset($metaRow['d_business_profile_type']) ? $metaRow['d_business_profile_type'] : '';
                    $cardRow['d_business_type'] = isset($metaRow['d_business_type']) ? $metaRow['d_business_type'] : '';
                    $cardRow['d_business_operation_area'] = isset($metaRow['d_business_operation_area']) ? $metaRow['d_business_operation_area'] : '';
                    $cardRow['d_business_operation_locations'] = isset($metaRow['d_business_operation_locations']) ? $metaRow['d_business_operation_locations'] : '';
                }
            }
            // Redirect if possible (prevents form resubmission on refresh)
            if (!headers_sent()) {
                header('Location: company-details.php?card_number='.$_SESSION['card_id_inprocess']);
                exit;
            }
        } elseif ($form_validation_error === '') {
            $_SESSION['save_error'] = "Error! Try Again. " . mysqli_error($connect);
            // If headers already sent, fall through and show page with existing $row
            if (!headers_sent()) {
                header('Location: company-details.php?card_number='.$_SESSION['card_id_inprocess']);
                exit;
            }
        }
        // Validation failure: $error_message is set; stay on page (no redirect)
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
                <label class="heading heading1">Company Visuals:</label>
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
                        <div class="file-info">Size: 1200×600px</div>
                        
                        <p class="addlogo">Add Hero Image (1200×600)</p>
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
                        <label class="heading heading2">Business Information:</label>
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
                                    <label for="d_position_primary">Business Category (Primary) <span class="text-danger">*</span></label>
                                    
                                    <div style="display: flex; gap: 10px;">
                                        <select name="d_position_primary" id="d_position_primary" class="form-control" style="flex: 1;" disabled required>
                                            <option value="">Select Business Profile Type first</option>
                                        </select>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="openCustomBusinessCategoryModal()" style="min-width: 40px; padding: 0;" title="Add Custom Category">
                                            <i class="fa fa-plus" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                            <div class="form-group">
                                    <label for="d_position_secondary">Business Category (Secondary)</label>
                                    
                                    <div style="display: flex; gap: 10px;">
                                        <select name="d_position_secondary" id="d_position_secondary" class="form-control" style="flex: 1;" disabled>
                                            <option value="">Select Business Profile Type first</option>
                                        </select>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="openCustomBusinessCategoryModal()" style="min-width: 40px; padding: 0;" title="Add Custom Category">
                                            <i class="fa fa-plus" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="d_product_categories_json" id="d_product_categories_json" value="">
                        <div id="product_category_cascade_panel" style="display:none;" aria-hidden="true"></div>
                        <div class="row" id="step_business_profile_model_row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_business_profile_type">Business Profile Type <span class="text-danger">*</span></label>
                                    <select name="d_business_profile_type" id="d_business_profile_type" class="form-control" required>
                                        <option value="" <?php echo empty($cardRow['d_business_profile_type']) ? 'selected' : ''; ?>>Choose which best describes your business?</option>
                                        <?php
                                        $saved_profile_type = isset($cardRow['d_business_profile_type']) ? trim($cardRow['d_business_profile_type']) : '';
                                        foreach ($business_profile_type_options as $profile_type):
                                            $sel = ($saved_profile_type !== '' && strcasecmp($saved_profile_type, $profile_type) === 0) ? ' selected' : '';
                                        ?>
                                        <option value="<?php echo htmlspecialchars($profile_type); ?>"<?php echo $sel; ?>><?php echo htmlspecialchars($profile_type); ?></option>
                                        <?php endforeach; ?>
                                        <?php if ($saved_profile_type !== '' && !in_array($saved_profile_type, $business_profile_type_options, true)): ?>
                                        <option value="<?php echo htmlspecialchars($saved_profile_type); ?>" selected><?php echo htmlspecialchars($saved_profile_type); ?> (saved)</option>
                                        <?php endif; ?>
                                    </select>
                                    
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_business_type">Business Model</label>
                                    <select name="d_business_type" id="d_business_type" class="form-control" disabled>
                                        <option value="">Choose What you offer?</option>
                                        <option value="product" <?php echo (isset($cardRow['d_business_type']) && $cardRow['d_business_type'] === 'product') ? 'selected' : ''; ?>>Product</option>
                                        <option value="service" <?php echo (isset($cardRow['d_business_type']) && $cardRow['d_business_type'] === 'service') ? 'selected' : ''; ?>>Service</option>
                                        <option value="hybrid" <?php echo (isset($cardRow['d_business_type']) && $cardRow['d_business_type'] === 'hybrid') ? 'selected' : ''; ?>>Hybrid (Product + Service)</option>
                                    </select>
                                    
                                </div>
                            </div>
                        </div>
                        <div class="row" id="step_business_operation_area_row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_business_operation_area">Business Operation Area</label>
                                    <select name="d_business_operation_area" id="d_business_operation_area" class="form-control">
                                        <option value="">-- Select Operation Area --</option>
                                        <option value="local" <?php echo (isset($cardRow['d_business_operation_area']) && $cardRow['d_business_operation_area'] === 'local') ? 'selected' : ''; ?>>Local Area (Within City)</option>
                                        <option value="pan_india" <?php echo (isset($cardRow['d_business_operation_area']) && $cardRow['d_business_operation_area'] === 'pan_india') ? 'selected' : ''; ?>>Across India (Pan India Service)</option>
                                        <option value="selected" <?php echo (isset($cardRow['d_business_operation_area']) && $cardRow['d_business_operation_area'] === 'selected') ? 'selected' : ''; ?>>Selected Cities / States</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row" id="business_operation_locations_row" style="<?php echo (isset($cardRow['d_business_operation_area']) && $cardRow['d_business_operation_area'] === 'selected') ? '' : 'display:none;'; ?>">
                            <div class="col-sm-12">
                                <div class="form-group">
                                    <label for="operation_states_tag_input">States you serve <span class="text-danger" id="business_operation_locations_required_mark" style="<?php echo (isset($cardRow['d_business_operation_area']) && $cardRow['d_business_operation_area'] === 'selected') ? '' : 'display:none;'; ?>">*</span></label>
                                    <div class="operation-locations-chips-field form-control" id="operation_states_chips_field">
                                        <div id="operation_states_chips" class="operation-locations-chips-inner"></div>
                                        <input type="text" id="operation_states_tag_input" class="operation-locations-tag-input" maxlength="200" placeholder="Type and select state(s)." autocomplete="off" aria-describedby="operation_locations_help">
                                    </div>
                                    <div id="operation-state-suggestions" class="operation-location-suggestions" style="display:none;"></div>
                                    <label for="operation_cities_tag_input" class="mt-2">Cities you serve</label>
                                    <div class="operation-locations-chips-field form-control" id="operation_cities_chips_field">
                                        <div id="operation_cities_chips" class="operation-locations-chips-inner"></div>
                                        <input type="text" id="operation_cities_tag_input" class="operation-locations-tag-input" maxlength="200" placeholder="Type and select cities from selected states." autocomplete="off" aria-describedby="operation_locations_help">
                                    </div>
                                    <div id="operation-city-suggestions" class="operation-location-suggestions" style="display:none;"></div>
                                    <input type="hidden" name="d_business_operation_locations" id="d_business_operation_locations" maxlength="2000" value="<?php echo isset($cardRow['d_business_operation_locations']) && $cardRow['d_business_operation_locations'] !== '' ? htmlspecialchars($cardRow['d_business_operation_locations']) : ''; ?>">
                                    <small id="operation_locations_help" class="form-text text-muted">Select up to 6 states/UTs of India. For each selected state, you can choose up to 4 cities (shown as <strong>City (State)</strong>) only from suggestions. Maximum cities allowed across all states is 24.</small>
                                    <div id="operation_locations_client_error" class="text-danger small mt-1" style="display:none;" role="alert"></div>
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
                                    <label for="d_country">Country <span class="text-danger">*</span></label>
                                    <select id="d_country" class="form-control" required data-saved="India" disabled>
                                        <option value="India" selected>India</option>
                                    </select>
                                    <input type="hidden" name="d_country" id="d_country_hidden" value="India">
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label for="d_state">State <span class="text-danger">*</span></label>
                                    <select name="d_state" id="d_state" class="form-control" required disabled data-saved="<?php echo !empty($cardRow['d_state']) ? htmlspecialchars($cardRow['d_state']) : ''; ?>">
                                        <option value="">Select State</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label for="d_city">City <span class="text-danger">*</span></label>
                                    <select name="d_city" id="d_city" class="form-control" required disabled data-saved="<?php echo !empty($cardRow['d_city']) ? htmlspecialchars($cardRow['d_city']) : ''; ?>">
                                        <option value="">Select City</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label for="d_pincode">Pincode <span class="text-danger">*</span></label>
                                    <input type="text" name="d_pincode" id="d_pincode" maxlength="200" placeholder="Enter Pincode" class="form-control" value="<?php echo !empty($cardRow['d_pincode']) ? htmlspecialchars($cardRow['d_pincode']) : ''; ?>" required>
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

                        <!-- Business Hours (weekly v2; stored as JSON) -->
                        <?php
                        $bh_day_order = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                        $bh_day_labels = [
                            'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
                            'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday',
                        ];
                        $bh_defaults = [
                            'mon' => ['open' => true, 'open_time' => '10:00', 'close_time' => '20:00'],
                            'tue' => ['open' => true, 'open_time' => '10:00', 'close_time' => '20:00'],
                            'wed' => ['open' => true, 'open_time' => '10:00', 'close_time' => '20:00'],
                            'thu' => ['open' => true, 'open_time' => '10:00', 'close_time' => '20:00'],
                            'fri' => ['open' => true, 'open_time' => '10:00', 'close_time' => '20:00'],
                            'sat' => ['open' => true, 'open_time' => '10:00', 'close_time' => '20:00'],
                            'sun' => ['open' => false, 'open_time' => '10:00', 'close_time' => '20:00'],
                        ];
                        $bh_schedule_merged = $bh_defaults;
                        $bh_raw_decoded = null;
                        if (!empty($cardRow['d_business_hours'])) {
                            $bh_raw_decoded = json_decode($cardRow['d_business_hours'], true);
                        }
                        if (is_array($bh_raw_decoded) && isset($bh_raw_decoded['version']) && (int) $bh_raw_decoded['version'] === 2 && !empty($bh_raw_decoded['schedule']) && is_array($bh_raw_decoded['schedule'])) {
                            foreach ($bh_day_order as $dk) {
                                if (isset($bh_raw_decoded['schedule'][$dk]) && is_array($bh_raw_decoded['schedule'][$dk])) {
                                    $s = $bh_raw_decoded['schedule'][$dk];
                                    $ot = isset($s['open_time']) ? trim((string) $s['open_time']) : $bh_defaults[$dk]['open_time'];
                                    $ct = isset($s['close_time']) ? trim((string) $s['close_time']) : $bh_defaults[$dk]['close_time'];
                                    if ($ot === '' || !preg_match('/^\d{2}:\d{2}$/', $ot)) {
                                        $ot = $bh_defaults[$dk]['open_time'];
                                    }
                                    if ($ct === '' || !preg_match('/^\d{2}:\d{2}$/', $ct)) {
                                        $ct = $bh_defaults[$dk]['close_time'];
                                    }
                                    $bh_schedule_merged[$dk] = [
                                        'open' => !empty($s['open']),
                                        'open_time' => $ot,
                                        'close_time' => $ct,
                                    ];
                                }
                            }
                        }
                        $bh_json_initial = json_encode(['version' => 2, 'schedule' => $bh_schedule_merged], JSON_UNESCAPED_UNICODE);
                        ?>
                        <div class="row mt-4">
                            <div class="col-sm-12">
                                <div class="bh-settings-card Personal-Details">
                                    <lable class="bh-settings-title mb-1">Business Hours Settings</lable>
                                    <h4 class="bh-settings-sub mb-4">Working Days &amp; Timings</h4>
                                    <div class="bh-table-head d-none d-md-grid">
                                        <span class="bh-th-day">Day</span>
                                        <span class="bh-th-open">Open</span>
                                        <span class="bh-th-close">Close</span>
                                    </div>
                                    <?php foreach ($bh_day_order as $dk):
                                        $bs = $bh_schedule_merged[$dk];
                                        $is_open = !empty($bs['open']);
                                        $ot_val = htmlspecialchars($bs['open_time'] ?? '10:00', ENT_QUOTES, 'UTF-8');
                                        $ct_val = htmlspecialchars($bs['close_time'] ?? '20:00', ENT_QUOTES, 'UTF-8');
                                        ?>
                                    <div class="bh-day-row" data-day="<?php echo htmlspecialchars($dk, ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="bh-cell bh-cell-day">
                                            <label class="bh-day-label mb-0">
                                                <input type="checkbox" class="bh-open-cb" <?php echo $is_open ? 'checked' : ''; ?> aria-label="<?php echo htmlspecialchars($bh_day_labels[$dk]); ?> open">
                                                <span class="bh-day-name"><?php echo htmlspecialchars($bh_day_labels[$dk]); ?></span>
                                            </label>
                                        </div>
                                        <div class="bh-cell bh-cell-open">
                                            <span class="d-md-none bh-mobile-col-label">Open</span>
                                            <div class="bh-time-field bh-when-open" <?php echo $is_open ? '' : 'style="display:none;"'; ?>>
                                                <span class="bh-time-icon" aria-hidden="true"><i class="fa fa-clock-o"></i></span>
                                                <input type="time" class="form-control bh-time-input bh-ot" value="<?php echo $ot_val; ?>" step="60" title="Open time">
                                            </div>
                                            <input type="text" class="form-control bh-closed-faux" value="Closed" readonly tabindex="-1" <?php echo $is_open ? 'style="display:none;"' : ''; ?> aria-hidden="<?php echo $is_open ? 'true' : 'false'; ?>">
                                        </div>
                                        <div class="bh-cell bh-cell-close">
                                            <span class="d-md-none bh-mobile-col-label">Close</span>
                                            <div class="bh-time-field bh-when-open" <?php echo $is_open ? '' : 'style="display:none;"'; ?>>
                                                <span class="bh-time-icon" aria-hidden="true"><i class="fa fa-clock-o"></i></span>
                                                <input type="time" class="form-control bh-time-input bh-ct" value="<?php echo $ct_val; ?>" step="60" title="Close time">
                                            </div>
                                            <input type="text" class="form-control bh-closed-faux" value="Closed" readonly tabindex="-1" <?php echo $is_open ? 'style="display:none;"' : ''; ?> aria-hidden="<?php echo $is_open ? 'true' : 'false'; ?>">
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <p class="bh-tz-note text-muted small mt-3 mb-4">Note: Time is set in your local time zone.</p>
                                    <div class="bh-quick-tips">
                                        <strong class="d-block mb-2">Quick Tips</strong>
                                        <ul class="bh-tip-list list-unstyled mb-0">
                                            <li><span class="bh-tip-icon"><i class="fa fa-check"></i></span> Set your correct working hours to avoid unnecessary calls.</li>
                                            <li><span class="bh-tip-icon"><i class="fa fa-check"></i></span> Timings will be shown to customers in your local time.</li>
                                            <li><span class="bh-tip-icon"><i class="fa fa-check"></i></span> Add special holidays to keep your customers informed.</li>
                                        </ul>
                                    </div>
                                    <input type="hidden" name="business_hours_json" id="business_hours_json" value="<?php echo htmlspecialchars($bh_json_initial, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="Product-ServicesBtn" style="margin-top: 20px; width: 86%;">
                            <a href="select-theme.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left">
                                <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                                <span>Back</span>
                            </a>
                            <button type="submit" name="process2" class="btn btn-primary align-center save_btn">
                                <img src="../../assets/images/Save.png" class="img-fluid" width="35px" alt=""> 
                                <span>Save</span>
                            </button>
                            <a href="services.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right">
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
                    outputFormat: 'png',
                    aspectRatio: 1,
                    cropWidth: 600,
                    cropHeight: 600,
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
                    title: 'Adjust & Crop Hero Image (1200×600)',
                    outputFormat: 'jpeg',
                    aspectRatio: 2,
                    cropWidth: 1200,
                    cropHeight: 600,
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

<script>
(function() {
    function bhRowFor(el) {
        return el && el.closest ? el.closest('.bh-day-row') : null;
    }
    function bhDayKey(row) {
        return row ? row.getAttribute('data-day') : '';
    }
    function bhUpdateRow(row) {
        if (!row) return;
        var open = row.querySelector('.bh-open-cb');
        var isOpen = open && open.checked;
        row.querySelectorAll('.bh-when-open').forEach(function(el) {
            el.style.display = isOpen ? '' : 'none';
        });
        row.querySelectorAll('.bh-closed-faux').forEach(function(el) {
            el.style.display = isOpen ? 'none' : '';
            el.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
        });
    }
    function syncBusinessHoursJson() {
        var schedule = {};
        document.querySelectorAll('.bh-day-row').forEach(function(row) {
            var dk = bhDayKey(row);
            if (!dk) return;
            var cb = row.querySelector('.bh-open-cb');
            var ot = row.querySelector('.bh-ot');
            var ct = row.querySelector('.bh-ct');
            schedule[dk] = {
                open: !!(cb && cb.checked),
                open_time: ot ? ot.value : '',
                close_time: ct ? ct.value : ''
            };
        });
        var hidden = document.getElementById('business_hours_json');
        if (hidden) {
            hidden.value = JSON.stringify({ version: 2, schedule: schedule });
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        var container = document.querySelector('.bh-settings-card');
        if (!container) return;
        document.querySelectorAll('.bh-day-row').forEach(function(row) { bhUpdateRow(row); });
        syncBusinessHoursJson();
        container.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('bh-open-cb')) {
                bhUpdateRow(bhRowFor(e.target));
                syncBusinessHoursJson();
            }
        });
        container.addEventListener('input', function(e) {
            if (e.target && (e.target.classList.contains('bh-ot') || e.target.classList.contains('bh-ct'))) {
                syncBusinessHoursJson();
            }
        });
        var form = document.getElementById('card_form');
        if (form) {
            form.addEventListener('submit', function() { syncBusinessHoursJson(); });
        }
    });
})();
</script>

<script>
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        var area = document.getElementById('d_business_operation_area');
        var row = document.getElementById('business_operation_locations_row');
        var ta = document.getElementById('d_business_operation_locations');
        var mark = document.getElementById('business_operation_locations_required_mark');
        function syncOperationLocationsRow() {
            if (!area || !row || !ta) return;
            if (area.value === 'selected') {
                row.style.display = '';
                ta.setAttribute('required', 'required');
                if (mark) mark.style.display = '';
            } else {
                row.style.display = 'none';
                ta.removeAttribute('required');
                if (mark) mark.style.display = 'none';
            }
        }
        if (area) {
            area.addEventListener('change', syncOperationLocationsRow);
            syncOperationLocationsRow();
        }
    });
})();
</script>
<script>
window.MW_CATEGORY_CASCADE_INIT = <?php echo json_encode($category_cascade_init, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script>
(function() {
    const AJAX_URL = '../../user/ajax/category_cascade.php';
    const INIT = window.MW_CATEGORY_CASCADE_INIT || {};

    const profileEl = document.getElementById('d_business_profile_type');
    const modelEl = document.getElementById('d_business_type');
    const primaryEl = document.getElementById('d_position_primary');
    const secondaryEl = document.getElementById('d_position_secondary');
    const productJsonEl = document.getElementById('d_product_categories_json');

    if (!profileEl || !modelEl || !primaryEl || !secondaryEl) return;

    // Step order: Profile Type → Business Model → Operation Area → States/Cities → Business Categories
    const profileModelRow = document.getElementById('step_business_profile_model_row')
        || profileEl.closest('.row');
    const categoriesRow = primaryEl.closest('.row');
    const locationsRow = document.getElementById('business_operation_locations_row');
    if (profileModelRow && categoriesRow && profileModelRow.parentNode) {
        const parent = categoriesRow.parentNode;
        parent.insertBefore(profileModelRow, categoriesRow);
        const opAreaRow = document.getElementById('step_business_operation_area_row')
            || document.getElementById('d_business_operation_area')?.closest('.row');
        if (opAreaRow) {
            parent.insertBefore(opAreaRow, categoriesRow);
        }
        if (locationsRow) {
            parent.insertBefore(locationsRow, categoriesRow);
        }
    }

    function resetSelect(sel, placeholder, disabled) {
        sel.innerHTML = '';
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = placeholder;
        sel.appendChild(opt);
        sel.disabled = !!disabled;
    }

    function fillBusinessSelect(sel, categories, selectedId, placeholder) {
        resetSelect(sel, placeholder, false);
        let currentGroup = null;
        let og = null;
        categories.forEach(function(c) {
            if (c.group && c.group !== currentGroup) {
                if (og) sel.appendChild(og);
                og = document.createElement('optgroup');
                og.label = c.group;
                currentGroup = c.group;
            }
            const opt = document.createElement('option');
            opt.value = String(c.id);
            opt.textContent = c.label;
            if (selectedId && String(selectedId) === String(c.id)) opt.selected = true;
            if (og) og.appendChild(opt);
            else sel.appendChild(opt);
        });
        if (og) sel.appendChild(og);
    }

    async function fetchBusinessCategories(profileType) {
        const url = AJAX_URL + '?action=business_categories&profile_type=' + encodeURIComponent(profileType);
        const res = await fetch(url);
        const data = await res.json();
        return data.success ? data.categories : [];
    }

    async function fetchProductCategories(primaryId, secondaryId) {
        const params = new URLSearchParams({ action: 'product_categories' });
        if (primaryId) params.set('primary_id', primaryId);
        if (secondaryId) params.set('secondary_id', secondaryId);
        const res = await fetch(AJAX_URL + '?' + params.toString());
        const data = await res.json();
        return data.success ? data.categories : [];
    }

    async function refreshProductCategories() {
        const primaryId = primaryEl.value;
        const secondaryId = secondaryEl.value;
        if (!primaryId && !secondaryId) {
            if (productJsonEl) productJsonEl.value = '';
            return;
        }
        const products = await fetchProductCategories(primaryId, secondaryId);
        if (productJsonEl) productJsonEl.value = JSON.stringify(products);
    }

    async function onProfileTypeChange() {
        const profileType = profileEl.value.trim();
        if (!profileType) {
            modelEl.disabled = true;
            resetSelect(primaryEl, 'Select Business Profile Type first', true);
            resetSelect(secondaryEl, 'Select Business Profile Type first', true);
            if (productJsonEl) productJsonEl.value = '';
            return;
        }
        modelEl.disabled = false;
        const categories = await fetchBusinessCategories(profileType);
        fillBusinessSelect(primaryEl, categories, '', '-- Select Primary Category --');
        fillBusinessSelect(secondaryEl, categories, '', '-- Select Secondary Category (Optional) --');
        if (productJsonEl) productJsonEl.value = '';
    }

    profileEl.addEventListener('change', onProfileTypeChange);
    primaryEl.addEventListener('change', refreshProductCategories);
    secondaryEl.addEventListener('change', refreshProductCategories);

    if (INIT.profile_type) {
        profileEl.value = INIT.profile_type;
        modelEl.disabled = false;
        fetchBusinessCategories(INIT.profile_type).then(function(categories) {
            fillBusinessSelect(primaryEl, categories, INIT.primary_id || '', '-- Select Primary Category --');
            fillBusinessSelect(secondaryEl, categories, INIT.secondary_id || '', '-- Select Secondary Category (Optional) --');
            refreshProductCategories();
        });
    }
})();
</script>
<?php
$mw_india_states_js = [];
$mw_isp_path = __DIR__ . '/../../includes/india_states_ut.php';
if (file_exists($mw_isp_path)) {
    require_once $mw_isp_path;
    if (function_exists('mw_india_state_names')) {
        $mw_india_states_js = mw_india_state_names();
    }
}
?>
<script>window.MW_INDIA_STATE_NAMES = <?php echo json_encode($mw_india_states_js, JSON_UNESCAPED_UNICODE); ?>;</script>
<script>
(function() {
    const API_BASE = 'https://countriesnow.space/api/v0.1/countries';
    let countriesData = [];
    let currentStates = [];
    let currentCities = [];
    let allIndiaCities = [];
    
    // Get saved values from database (if any)
    const savedCountry = 'India';
    const savedState = document.getElementById('d_state').getAttribute('data-saved') || '';
    const savedCity = document.getElementById('d_city').getAttribute('data-saved') || '';
    
    function showLoading(elementId) {
        document.getElementById(elementId).innerHTML = '<option value="">Loading...</option>';
        document.getElementById(elementId).disabled = true;
    }
    
    function showError(elementId) {
        document.getElementById(elementId).innerHTML = '<option value="">Error loading data</option>';
        document.getElementById(elementId).disabled = true;
    }
    
    async function fetchCountries() {
        try {
            const countrySelect = document.getElementById('d_country');
            countrySelect.innerHTML = '<option value="India" selected>India</option>';
            countrySelect.value = 'India';
            countrySelect.disabled = true;
            const countryHidden = document.getElementById('d_country_hidden');
            if (countryHidden) countryHidden.value = 'India';
            countriesData = [{ country: 'India' }];
            await fetchStates('India');
        } catch (error) {
            console.error('Error fetching countries:', error);
            const countrySelect = document.getElementById('d_country');
            countrySelect.innerHTML = '<option value="India" selected>India</option>';
            countrySelect.value = 'India';
            countrySelect.disabled = true;
            const countryHidden = document.getElementById('d_country_hidden');
            if (countryHidden) countryHidden.value = 'India';
            await fetchStates('India');
        }
    }
    
    async function fetchStates(country) {
        try {
            showLoading('d_state');
            currentStates = [];
            
            const response = await fetch(API_BASE + '/states', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ country: country })
            });
            
            const data = await response.json();
            
            if (data && data.data && Array.isArray(data.data.states) && data.data.states.length > 0) {
                const stateSelect = document.getElementById('d_state');
                stateSelect.innerHTML = '<option value="">Select State</option>';
                
                data.data.states.forEach(state => {
                    const option = document.createElement('option');
                    option.value = state.name;
                    option.textContent = state.name;
                    stateSelect.appendChild(option);
                });
                currentStates = data.data.states.map(function(state) { return state.name; });
                
                stateSelect.disabled = false;
                
                // Auto-select saved state if exists
                if (savedState) {
                    stateSelect.value = savedState;
                    await fetchCities(country, savedState);
                } else {
                    // Reset city dropdown when country changes
                    document.getElementById('d_city').innerHTML = '<option value="">Select City</option>';
                    document.getElementById('d_city').disabled = true;
                    currentCities = [];
                }
            } else {
                showError('d_state');
                currentStates = [];
            }
        } catch (error) {
            console.error('Error fetching states:', error);
            showError('d_state');
            currentStates = [];
        }
    }
    
    async function fetchCities(country, state) {
        try {
            showLoading('d_city');
            currentCities = [];
            
            const response = await fetch(API_BASE + '/state/cities', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    country: country,
                    state: state
                })
            });
            
            const data = await response.json();
            
            if (data && data.data && Array.isArray(data.data) && data.data.length > 0) {
                const citySelect = document.getElementById('d_city');
                citySelect.innerHTML = '<option value="">Select City</option>';
                
                data.data.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city;
                    option.textContent = city;
                    citySelect.appendChild(option);
                });
                currentCities = data.data.slice();
                
                citySelect.disabled = false;
                
                // Auto-select saved city if exists
                if (savedCity) {
                    citySelect.value = savedCity;
                }
            } else {
                showError('d_city');
                currentCities = [];
            }
        } catch (error) {
            console.error('Error fetching cities:', error);
            showError('d_city');
            currentCities = [];
        }
    }

    async function fetchAllIndiaCities() {
        try {
            const response = await fetch(API_BASE + '/cities', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ country: 'India' })
            });
            const data = await response.json();
            if (data && Array.isArray(data.data)) {
                allIndiaCities = data.data.map(function(item) {
                    if (typeof item === 'string') return item;
                    if (item && typeof item.city === 'string') return item.city;
                    if (item && typeof item.name === 'string') return item.name;
                    return '';
                }).filter(function(city) {
                    return city && city.trim() !== '';
                });
            } else {
                allIndiaCities = [];
            }
        } catch (error) {
            console.error('Error fetching all India cities:', error);
            allIndiaCities = [];
        }
    }

    const DEFAULT_INDIA_STATE_NAMES = [
        'Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat',
        'Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh',
        'Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan',
        'Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal',
        'Andaman and Nicobar Islands','Chandigarh','Dadra and Nagar Haveli and Daman and Diu',
        'Delhi','Jammu and Kashmir','Ladakh','Lakshadweep','Puducherry'
    ];

    function getIndiaStateNamesForSuggestions() {
        const fromWindow = Array.isArray(window.MW_INDIA_STATE_NAMES) ? window.MW_INDIA_STATE_NAMES : [];
        if (fromWindow.length) return fromWindow;
        return DEFAULT_INDIA_STATE_NAMES;
    }

    function buildIndiaOperationLookup(names) {
        const lookup = {};
        (names || []).forEach(function(n) {
            if (n) {
                lookup[String(n).toLowerCase()] = true;
            }
        });
        const aliases = {
            orissa: true,
            'daman and diu': true,
            'dadra and nagar haveli': true,
            'jammu & kashmir': true,
            pondicherry: true
        };
        Object.keys(aliases).forEach(function(k) {
            lookup[k] = true;
        });
        return lookup;
    }

    const indiaStateNames = getIndiaStateNamesForSuggestions();
    const indiaStateLookup = buildIndiaOperationLookup(indiaStateNames);

    function isIndiaSelected() {
        const c = document.getElementById('d_country');
        return c && c.value && String(c.value).toLowerCase() === 'india';
    }

    function validateOperationLocationToken(token) {
        const p = String(token || '').trim();
        if (!p) {
            return { ok: false, message: '' };
        }
        if (!isIndiaSelected()) {
            return { ok: true };
        }
        const lookup = indiaStateLookup;
        if (lookup[p.toLowerCase()]) {
            return { ok: true };
        }
        const m = p.match(/^(.+?)\s*[,\-]\s*(.+)$/);
        if (m) {
            const st = m[2].trim().toLowerCase();
            if (lookup[st]) {
                return { ok: true };
            }
            return {
                ok: false,
                message: 'Use a recognised Indian state or UT after the comma (e.g. Mumbai, Maharashtra), or enter a state/UT name alone.'
            };
        }
        return { ok: true };
    }

    function getOperationStateSuggestions(term, selectedStates) {
        const selectedLookup = {};
        (selectedStates || []).forEach(function(s) {
            selectedLookup[String(s || '').toLowerCase()] = true;
        });
        const q = (term || '').trim().toLowerCase();
        const merged = [];
        const seen = {};
        const source = isIndiaSelected() ? indiaStateNames : currentStates;
        source.forEach(function(s) {
            const value = String(s || '').trim();
            if (!value) return;
            const key = value.toLowerCase();
            if (selectedLookup[key] || seen[key]) return;
            seen[key] = true;
            merged.push({ label: value, value: value });
        });
        if (!q) return merged.slice(0, 15);
        return merged.filter(function(item) {
            return item.value.toLowerCase().indexOf(q) !== -1;
        }).slice(0, 15);
    }

    function initOperationLocationAutocomplete() {
        const hidden = document.getElementById('d_business_operation_locations');
        const stateInput = document.getElementById('operation_states_tag_input');
        const stateChips = document.getElementById('operation_states_chips');
        const stateField = document.getElementById('operation_states_chips_field');
        const stateBox = document.getElementById('operation-state-suggestions');
        const cityInput = document.getElementById('operation_cities_tag_input');
        const cityChips = document.getElementById('operation_cities_chips');
        const cityField = document.getElementById('operation_cities_chips_field');
        const cityBox = document.getElementById('operation-city-suggestions');
        const errEl = document.getElementById('operation_locations_client_error');
        if (!hidden || !stateInput || !stateChips || !stateBox || !cityInput || !cityChips || !cityBox) return;

        const MAX_STATES = 6;
        const MAX_CITIES_PER_STATE = 4;
        const MAX_TOTAL_CITIES = 24;
        const cityCacheByState = {};
        const cityFetchPromiseByState = {};
        let selectedStates = [];
        let citiesByState = {};

        function stateKey(v) {
            return String(v || '').trim().toLowerCase();
        }

        function cityKey(v) {
            return String(v || '').trim().toLowerCase();
        }

        function hideError() {
            if (!errEl) return;
            errEl.style.display = 'none';
            errEl.textContent = '';
        }

        function showError(msg) {
            if (!errEl) return;
            errEl.textContent = msg;
            errEl.style.display = '';
        }

        function hideStateBox() {
            stateBox.style.display = 'none';
            stateBox.innerHTML = '';
        }

        function hideCityBox() {
            cityBox.style.display = 'none';
            cityBox.innerHTML = '';
        }

        function totalCityCount() {
            let total = 0;
            Object.keys(citiesByState).forEach(function(st) {
                total += (citiesByState[st] || []).length;
            });
            return total;
        }

        function syncHidden() {
            hidden.value = JSON.stringify({
                states: selectedStates,
                citiesByState: citiesByState
            });
        }

        function parseLegacyValue(raw) {
            const outStates = [];
            const outCitiesByState = {};
            const tokenList = String(raw || '').split(/\s*,\s*/).map(function(t) { return t.trim(); }).filter(Boolean);
            for (let i = 0; i < tokenList.length; i++) {
                const token = tokenList[i];
                const tokenLower = token.toLowerCase();
                if (indiaStateLookup[tokenLower]) {
                    if (!outStates.some(function(x) { return stateKey(x) === tokenLower; })) {
                        outStates.push(token);
                    }
                    continue;
                }
                if (i + 1 < tokenList.length && indiaStateLookup[tokenList[i + 1].toLowerCase()]) {
                    const st = tokenList[i + 1];
                    if (!outStates.some(function(x) { return stateKey(x) === stateKey(st); })) outStates.push(st);
                    if (!outCitiesByState[st]) outCitiesByState[st] = [];
                    if (!outCitiesByState[st].some(function(c) { return cityKey(c) === cityKey(token); })) {
                        outCitiesByState[st].push(token);
                    }
                    i++;
                }
            }
            return { states: outStates, citiesByState: outCitiesByState };
        }

        function loadFromHidden() {
            const raw = String(hidden.value || '').trim();
            if (!raw) return { states: [], citiesByState: {} };
            try {
                const decoded = JSON.parse(raw);
                if (decoded && Array.isArray(decoded.states) && decoded.citiesByState && typeof decoded.citiesByState === 'object') {
                    return decoded;
                }
            } catch (e) {}
            return parseLegacyValue(raw);
        }

        function normalizeData(data) {
            const nextStates = [];
            const nextCitiesByState = {};
            const stateSeen = {};
            (data.states || []).forEach(function(st) {
                const stateName = String(st || '').trim();
                if (!stateName) return;
                const sk = stateKey(stateName);
                if (stateSeen[sk]) return;
                stateSeen[sk] = true;
                nextStates.push(stateName);
            });
            nextStates.slice(0, MAX_STATES).forEach(function(st) {
                const list = Array.isArray(data.citiesByState && data.citiesByState[st]) ? data.citiesByState[st] : [];
                const clean = [];
                const seen = {};
                list.forEach(function(city) {
                    const cityName = String(city || '').trim();
                    if (!cityName) return;
                    const ck = cityKey(cityName);
                    if (seen[ck]) return;
                    seen[ck] = true;
                    if (clean.length < MAX_CITIES_PER_STATE) clean.push(cityName);
                });
                nextCitiesByState[st] = clean;
            });
            selectedStates = nextStates.slice(0, MAX_STATES);
            citiesByState = nextCitiesByState;
            if (totalCityCount() > MAX_TOTAL_CITIES) {
                let remain = MAX_TOTAL_CITIES;
                selectedStates.forEach(function(st) {
                    const list = citiesByState[st] || [];
                    if (remain <= 0) {
                        citiesByState[st] = [];
                        return;
                    }
                    if (list.length > remain) {
                        citiesByState[st] = list.slice(0, remain);
                    }
                    remain -= (citiesByState[st] || []).length;
                });
            }
        }

        function renderStateChips() {
            stateChips.innerHTML = '';
            selectedStates.forEach(function(st) {
                const span = document.createElement('span');
                span.className = 'operation-location-chip';
                span.appendChild(document.createTextNode(st));
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'operation-location-chip-remove';
                btn.setAttribute('aria-label', 'Remove ' + st);
                btn.innerHTML = '\u00d7';
                btn.addEventListener('click', function() {
                    selectedStates = selectedStates.filter(function(x) { return stateKey(x) !== stateKey(st); });
                    delete citiesByState[st];
                    renderAll();
                    hideError();
                });
                span.appendChild(btn);
                stateChips.appendChild(span);
            });
        }

        function renderCityChips() {
            cityChips.innerHTML = '';
            selectedStates.forEach(function(st) {
                (citiesByState[st] || []).forEach(function(city) {
                    const span = document.createElement('span');
                    span.className = 'operation-location-chip';
                    span.appendChild(document.createTextNode(city + ' (' + st + ')'));
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'operation-location-chip-remove';
                    btn.setAttribute('aria-label', 'Remove ' + city + ' from ' + st);
                    btn.innerHTML = '\u00d7';
                    btn.addEventListener('click', function() {
                        citiesByState[st] = (citiesByState[st] || []).filter(function(c) { return cityKey(c) !== cityKey(city); });
                        renderAll();
                        hideError();
                    });
                    span.appendChild(btn);
                    cityChips.appendChild(span);
                });
            });
        }

        function renderAll() {
            renderStateChips();
            renderCityChips();
            syncHidden();
        }

        async function fetchOperationCitiesForState(stateName) {
            const st = String(stateName || '').trim();
            if (!st) return [];
            if (cityCacheByState[st]) return cityCacheByState[st];
            if (cityFetchPromiseByState[st]) return cityFetchPromiseByState[st];
            cityFetchPromiseByState[st] = (async function() {
                try {
                    const response = await fetch(API_BASE + '/state/cities', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ country: 'India', state: st })
                    });
                    const data = await response.json();
                    let list = [];
                    if (data && Array.isArray(data.data)) {
                        list = data.data.map(function(item) {
                            if (typeof item === 'string') return item.trim();
                            if (item && typeof item.city === 'string') return item.city.trim();
                            return '';
                        }).filter(Boolean);
                    }
                    cityCacheByState[st] = list;
                    return list;
                } catch (e) {
                    cityCacheByState[st] = [];
                    return [];
                } finally {
                    delete cityFetchPromiseByState[st];
                }
            })();
            return cityFetchPromiseByState[st];
        }

        async function prefetchSelectedStateCities() {
            if (!selectedStates.length) return;
            await Promise.all(selectedStates.map(function(st) {
                return fetchOperationCitiesForState(st);
            }));
        }

        function addState(stateName) {
            const st = String(stateName || '').trim();
            if (!st) return;
            if (selectedStates.some(function(x) { return stateKey(x) === stateKey(st); })) return;
            if (selectedStates.length >= MAX_STATES) {
                showError('You can choose up to 6 states only.');
                return;
            }
            selectedStates.push(st);
            if (!citiesByState[st]) citiesByState[st] = [];
            fetchOperationCitiesForState(st);
            stateInput.value = '';
            hideStateBox();
            renderAll();
            hideError();
        }

        function buildCityOptions(term) {
            const q = String(term || '').trim().toLowerCase();
            const byState = {};
            selectedStates.forEach(function(st) {
                const cityList = cityCacheByState[st] || [];
                const selectedForState = citiesByState[st] || [];
                if (selectedForState.length >= MAX_CITIES_PER_STATE) return;
                byState[st] = [];
                cityList.forEach(function(city) {
                    const cityName = String(city || '').trim();
                    if (!cityName) return;
                    const already = selectedForState.some(function(x) { return cityKey(x) === cityKey(cityName); });
                    if (already) return;
                    const label = cityName + ' (' + st + ')';
                    if (q && label.toLowerCase().indexOf(q) === -1) return;
                    byState[st].push({ state: st, city: cityName, label: label });
                });
            });
            const out = [];
            const MAX_CITY_SUGGESTIONS = 60;
            // Interleave state-wise so one big state does not hide others.
            let added = true;
            let idx = 0;
            while (added && out.length < MAX_CITY_SUGGESTIONS) {
                added = false;
                selectedStates.forEach(function(st) {
                    const list = byState[st] || [];
                    if (idx < list.length && out.length < MAX_CITY_SUGGESTIONS) {
                        out.push(list[idx]);
                        added = true;
                    }
                });
                idx++;
            }
            return out;
        }

        async function renderStateSuggestions() {
            const list = getOperationStateSuggestions(stateInput.value || '', selectedStates);
            if (!list.length) {
                hideStateBox();
                return;
            }
            stateBox.innerHTML = '';
            list.forEach(function(item) {
                const opt = document.createElement('button');
                opt.type = 'button';
                opt.className = 'operation-location-option';
                opt.textContent = item.label;
                opt.addEventListener('click', function() { addState(item.value); });
                stateBox.appendChild(opt);
            });
            stateBox.style.display = 'block';
        }

        async function renderCitySuggestions() {
            if (!selectedStates.length) {
                hideCityBox();
                return;
            }
            await prefetchSelectedStateCities();
            const list = buildCityOptions(cityInput.value || '');
            if (!list.length) {
                hideCityBox();
                return;
            }
            cityBox.innerHTML = '';
            list.forEach(function(item) {
                const opt = document.createElement('button');
                opt.type = 'button';
                opt.className = 'operation-location-option';
                opt.textContent = item.label;
                opt.addEventListener('click', function() {
                    if (totalCityCount() >= MAX_TOTAL_CITIES) {
                        showError('You can choose up to 24 cities in total.');
                        return;
                    }
                    const listForState = citiesByState[item.state] || [];
                    if (listForState.length >= MAX_CITIES_PER_STATE) {
                        showError('You can choose up to 4 cities per state.');
                        return;
                    }
                    listForState.push(item.city);
                    citiesByState[item.state] = listForState;
                    cityInput.value = '';
                    hideCityBox();
                    renderAll();
                    hideError();
                });
                cityBox.appendChild(opt);
            });
            cityBox.style.display = 'block';
        }

        const loadedData = loadFromHidden();
        normalizeData(loadedData);
        renderAll();
        prefetchSelectedStateCities();

        stateInput.addEventListener('input', renderStateSuggestions);
        stateInput.addEventListener('focus', renderStateSuggestions);
        stateInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') hideStateBox();
            if (e.key === 'Backspace' && stateInput.value === '' && selectedStates.length) {
                const removed = selectedStates.pop();
                delete citiesByState[removed];
                renderAll();
                hideError();
            }
        });

        cityInput.addEventListener('input', renderCitySuggestions);
        cityInput.addEventListener('focus', renderCitySuggestions);
        cityInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') hideCityBox();
        });

        document.addEventListener('click', function(e) {
            const inState = (stateField && stateField.contains(e.target)) || stateBox.contains(e.target);
            const inCity = (cityField && cityField.contains(e.target)) || cityBox.contains(e.target);
            if (!inState) hideStateBox();
            if (!inCity) hideCityBox();
        });

        const form = hidden.closest('form');
        if (form) {
            form.addEventListener('submit', function(ev) {
                const areaEl = document.getElementById('d_business_operation_area');
                if (!areaEl || areaEl.value !== 'selected') return;
                if (!selectedStates.length) {
                    showError('Please select at least one state.');
                    ev.preventDefault();
                    return;
                }
                if (selectedStates.length > MAX_STATES || totalCityCount() > MAX_TOTAL_CITIES) {
                    showError('Please review selected states/cities limits.');
                    ev.preventDefault();
                    return;
                }
                for (let i = 0; i < selectedStates.length; i++) {
                    const st = selectedStates[i];
                    if ((citiesByState[st] || []).length > MAX_CITIES_PER_STATE) {
                        showError('You can choose up to 4 cities per state.');
                        ev.preventDefault();
                        return;
                    }
                }
                syncHidden();
            });
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Fetch countries on page load
        fetchCountries();
        fetchAllIndiaCities();
        initOperationLocationAutocomplete();
        
        // Handle country selection change
        const countrySelect = document.getElementById('d_country');
        if (countrySelect) {
            countrySelect.addEventListener('change', function() {
                if (this.value) {
                    fetchStates(this.value);
                } else {
                    document.getElementById('d_state').innerHTML = '<option value="">Select State</option>';
                    document.getElementById('d_state').disabled = true;
                    document.getElementById('d_city').innerHTML = '<option value="">Select City</option>';
                    document.getElementById('d_city').disabled = true;
                    currentStates = [];
                    currentCities = [];
                }
            });
        }
        
        // Handle state selection change
        const stateSelect = document.getElementById('d_state');
        if (stateSelect) {
            stateSelect.addEventListener('change', function() {
                const country = document.getElementById('d_country').value;
                if (this.value && country) {
                    fetchCities(country, this.value);
                } else {
                    document.getElementById('d_city').innerHTML = '<option value="">Select City</option>';
                    document.getElementById('d_city').disabled = true;
                    currentCities = [];
                }
            });
        }
    });
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
    /* Hero image: 2:1 ratio (width twice height) */
    #heroImagePreview.logo-placeholder{
        width: 240px;
        height: 120px;
    }
    #heroImagePreview #showPreviewHero,
    #heroImagePreview img{
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 8px;
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
#heroImagePreview.logo-placeholder {
    width: 90%;
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
        padding: 0px 40px;
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
    .save_btn{
    width: 115px !important;
}

    select.form-control {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 20px;
        padding-right: 40px !important;
        cursor: pointer;
    }

    select.form-control:disabled {
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ccc' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    }

    /* Business hours: align delete control with inputs (no extra form-group offset) */
    .business-hours-row .form-group {
        margin-bottom: 0;
    }
    .business-hours-row .business-hours-fields.col {
        min-width: 0;
    }

    .operation-locations-chips-field {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        min-height: 38px;
        height: auto;
        padding-top: 6px;
        padding-bottom: 6px;
    }
    .operation-locations-chips-inner {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }
    .operation-locations-tag-input {
        flex: 1 1 140px;
        min-width: 140px;
        border: 0 !important;
        outline: none !important;
        box-shadow: none !important;
        padding: 4px 2px;
        margin: 0;
        background: transparent;
    }
    .operation-location-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 6px 4px 10px;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        border-radius: 6px;
        font-size: 14px;
        line-height: 1.2;
        max-width: 100%;
    }
    .operation-location-chip-remove {
        border: 0;
        background: transparent;
        color: #64748b;
        font-size: 18px;
        line-height: 1;
        padding: 0 2px;
        cursor: pointer;
        line-height: 1;
    }
    .operation-location-chip-remove:hover {
        color: #b91c1c;
    }
    .operation-location-suggestions {
        position: relative;
        z-index: 20;
        border: 1px solid #ddd;
        border-radius: 6px;
        margin-top: 6px;
        max-height: 220px;
        overflow-y: auto;
        background: #fff;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
    }
    .operation-location-option {
        display: block;
        width: 100%;
        border: 0;
        text-align: left;
        padding: 8px 12px;
        background: #fff;
        cursor: pointer;
        font-size: 14px;
    }
    .operation-location-option:hover {
        background: #f5f7ff;
    }
    /* Business Hours Settings (weekly grid) */
    .bh-settings-card {
        padding-left: 40px;
        padding-right: 40px;
        padding-bottom: 24px;
    }
    .bh-settings-title {
        font-size: 22px;
        font-weight: 700;
        color: #1a1a1a;
    }
    .bh-settings-sub {
        font-size: 18px;
        font-weight: 700;
        color: #1e3a5f;
    }
    .bh-table-head {
        display: none;
        grid-template-columns: minmax(140px, 1.2fr) 1fr 1fr;
        gap: 12px 16px;
        padding: 0 0 8px 0;
        font-size: 15px;
        font-weight: 700;
        color: #1e3a5f;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 8px;
        align-items: end;
    }
    .bh-day-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
        padding: 14px 0;
        border-bottom: 1px solid #eef1f5;
        align-items: center;
    }
    .bh-day-row:last-of-type {
        border-bottom: none;
    }
    @media (min-width: 768px) {
        .bh-table-head.d-md-grid {
            display: grid;
        }
        .bh-day-row {
            grid-template-columns: minmax(140px, 1.2fr) 1fr 1fr;
            gap: 12px 16px;
            padding: 12px 0;
        }
        .bh-mobile-col-label {
            display: none !important;
        }
    }
    .bh-day-label {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 500;
        color: #333;
    }
    .bh-open-cb {
        appearance: none;
        -webkit-appearance: none;
        width: 22px;
        height: 22px;
        border: 2px solid #cbd5e1;
        border-radius: 4px;
        margin: 0;
        flex-shrink: 0;
        cursor: pointer;
        position: relative;
        background: #fff;
    }
    .bh-open-cb:checked {
        background: #2563eb;
        border-color: #2563eb;
    }
    .bh-open-cb:checked::after {
        content: '';
        position: absolute;
        left: 6px;
        top: 2px;
        width: 5px;
        height: 10px;
        border: solid #fff;
        border-width: 0 2px 2px 0;
        transform: rotate(45deg);
    }
    .bh-open-cb:focus {
        outline: 2px solid rgba(37, 99, 235, 0.35);
        outline-offset: 2px;
    }
    .bh-cell-open .bh-time-field,
    .bh-cell-close .bh-time-field {
        position: relative;
        display: flex;
        align-items: center;
        width: 100%;
    }
    .bh-time-icon {
        position: absolute;
        right: 12px;
        z-index: 2;
        color: #64748b;
        pointer-events: none;
        font-size: 15px;
    }
    .bh-time-input {
        border-radius: 10px !important;
        border: 1px solid #d1d5db !important;
        min-height: 48px !important;
        padding-right: 40px !important;
        font-size: 15px !important;
        font-weight: 400;
    }
    .bh-closed-faux {
        border-radius: 10px !important;
        border: 1px solid #e5e7eb !important;
        background: #f3f4f6 !important;
        color: #6b7280 !important;
        min-height: 48px !important;
        font-size: 15px !important;
        cursor: default;
    }
    .bh-mobile-col-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .bh-tz-note {
        font-size: 13px;
    }
    .bh-quick-tips {
        background: #fdf6e3;
        border-radius: 12px;
        padding: 16px 18px;
        border: 1px solid #f5e6c8;
    }
    .bh-quick-tips strong {
        font-size: 16px;
        color: #1a1a1a;
    }
    .bh-tip-list li {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 6px 0;
        font-size: 14px;
        color: #374151;
        line-height: 1.45;
    }
    .bh-tip-icon {
        color: #2563eb;
        flex-shrink: 0;
        margin-top: 2px;
    }
    @media screen and (max-width: 767.98px) {
        .bh-settings-card {
            padding-left: 12px;
            padding-right: 12px;
        }
    }
</style>

<!-- Custom Business Category Modal -->
<div class="modal fade website-step-modal" id="customBusinessCategoryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content website-step-modal-content">
            <button type="button" class="website-step-modal-close close" onclick="closeCustomBusinessCategoryModal()" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <div class="modal-body">
                <form id="customBusinessCategoryForm">
                    <div class="form-group">
                        <label for="custom_business_category_name">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="custom_business_category_name" placeholder="Enter category name" maxlength="255" required>
                        <small class="form-text text-muted">This category will only be visible to you</small>
                    </div>
                    <div id="customBusinessCategoryError" class="alert alert-danger" style="display: none;"></div>
                    <div id="customBusinessCategorySuccess" class="alert alert-success" style="display: none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCustomBusinessCategoryModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveCustomBusinessCategory()">Add Category</button>
            </div>
        </div>
    </div>
</div>

<script>
function openCustomBusinessCategoryModal() {
    var nameField = document.getElementById('custom_business_category_name');
    if(nameField) nameField.value = '';
    
    var errorElement = document.getElementById('customBusinessCategoryError');
    var successElement = document.getElementById('customBusinessCategorySuccess');
    
    if(errorElement) errorElement.style.display = 'none';
    if(successElement) successElement.style.display = 'none';
    
    var modalElement = document.getElementById('customBusinessCategoryModal');
    
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery(modalElement).modal('show');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    } else {
        // Fallback: show modal manually
        modalElement.style.display = 'block';
        modalElement.classList.add('show');
        document.body.classList.add('modal-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        document.body.appendChild(backdrop);
    }
}

function closeCustomBusinessCategoryModal() {
    var modalElement = document.getElementById('customBusinessCategoryModal');
    
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery(modalElement).modal('hide');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modal = bootstrap.Modal.getInstance(modalElement);
        if(modal) modal.hide();
        else {
            modalElement.classList.remove('show');
            modalElement.style.display = 'none';
            modalElement.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            document.body.style.paddingRight = '';
            document.querySelectorAll('.modal-backdrop').forEach(function(b) { b.remove(); });
        }
    } else {
        modalElement.style.display = 'none';
        modalElement.classList.remove('show');
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop').forEach(function(b) { b.remove(); });
    }
}

function saveCustomBusinessCategory() {
    var categoryName = document.getElementById('custom_business_category_name').value.trim();
    var errorElement = document.getElementById('customBusinessCategoryError');
    var successElement = document.getElementById('customBusinessCategorySuccess');
    
    if (!categoryName) {
        errorElement.textContent = 'Category name is required.';
        errorElement.style.display = 'block';
        return;
    }
    
    errorElement.style.display = 'none';
    successElement.style.display = 'none';
    successElement.textContent = 'Creating category...';
    successElement.style.display = 'block';
    
    var formData = new FormData();
    formData.append('category_name', categoryName);
    formData.append('category_type', 'business-category');
    
    fetch('../../user/ajax/custom_categories.php?action=create', {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error, status = ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            successElement.textContent = data.message || 'Category created successfully!';
            successElement.style.display = 'block';
            errorElement.style.display = 'none';
            
            setTimeout(function() {
                closeCustomBusinessCategoryModal();
                window.location.reload();
            }, 1000);
        } else {
            errorElement.textContent = data.message || 'Error creating category.';
            errorElement.style.display = 'block';
            successElement.style.display = 'none';
            console.error('API Error:', data);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        errorElement.textContent = 'Network error. Please check your connection and try again.';
        errorElement.style.display = 'block';
        successElement.style.display = 'none';
    });
}
</script>

<?php include '../includes/footer.php'; ?>





