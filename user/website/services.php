<?php
// Start output buffering to prevent any output before JSON response
ob_start();

// Handle card_number from URL, session, or cookie
// MUST be done before any output (before including header.php)
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/includes/product_categories_helper.php');

// Handle AJAX image processing FIRST - before any other output
// This must be at the very top to prevent any output before JSON response
if(isset($_POST['process_product_image_ajax']) && !empty($_FILES['product_image']['tmp_name'])){
    // Suppress any output and set JSON header
    while(ob_get_level() > 0) {
        ob_end_clean();
    }
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
        
        if(!function_exists('processHeroImageUpload') && !function_exists('processImageUploadWithAutoCrop')) {
            throw new Exception('Image processing function not available');
        }
        
        // Check file upload error
        if($_FILES['product_image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $_FILES['product_image']['error']);
        }
        
        // Service images: 4:3 ratio (width to height)
        if(function_exists('processHeroImageUpload')) {
            $result = processHeroImageUpload($_FILES['product_image'], 1200, 900);
        } else {
            $result = processImageUploadWithAutoCrop($_FILES['product_image'], 600, 250000, 200000, 300000, ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'], 'jpeg', null);
        }
        
        if($result['status']) {
            // Return processed image as base64 for immediate preview
            $base64Image = base64_encode($result['data']);
            
            echo json_encode([
                'success' => true,
                'image_data' => $base64Image,
                'dimensions' => isset($result['dimensions']) ? $result['dimensions'] : ['width' => 1200, 'height' => 900],
                'file_size' => isset($result['file_size']) ? $result['file_size'] : 0,
                'message' => 'Image processed successfully'
            ]);
            
            // Clean up temp file
            if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                @unlink($result['file_path']);
            }
        } else {
            $errorMsg = isset($result['message']) ? strip_tags($result['message']) : 'Unknown error processing image';
            echo json_encode([
                'success' => false,
                'message' => $errorMsg
            ]);
        }
    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    } catch(Error $e) {
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
    // Use dedicated variable to avoid collisions with included files (e.g. header.php)
    $cardRow = mysqli_fetch_array($query);
}

// Same as Products: child product categories come from selected business categories on the card
$card_d_position_primary = isset($cardRow['d_position_primary']) ? $cardRow['d_position_primary'] : '';
$card_d_position_secondary = isset($cardRow['d_position_secondary']) ? $cardRow['d_position_secondary'] : '';

// Get user_id - must be done before form processing
// Check both user_details (unified table) and customer_login (legacy table) for compatibility
if(!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    header('Location: ../../login/customer.php');
    exit;
}

$user_email = trim($_SESSION['user_email']);
$user_email = mysqli_real_escape_string($connect, $user_email);

// Query to get user_id - ensure connection is valid
if(!$connect || ($connect instanceof mysqli && $connect->connect_error)) {
    die('Database connection error. Please refresh and try again.');
}

$user_id = 0;

// Use case-insensitive comparison for email (trim and lowercase)
$user_email_lower = strtolower(trim($user_email));

// Get user_id directly from user_details table (unified table for all users)
$user_details_query = mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = '$user_email_lower' LIMIT 1");
if($user_details_query && mysqli_num_rows($user_details_query) > 0) {
    $user_details_row = mysqli_fetch_array($user_details_query);
    $user_id = isset($user_details_row['id']) ? intval($user_details_row['id']) : 0;
}
if($user_id == 0) {
    // If this is an AJAX request, return JSON error
    if(isset($_POST['process4']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        while(ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => 'User not found. Please login again.']);
        exit;
    }
    echo '<script>alert("User not found. Please login again."); window.location.href="../../login/customer.php";</script>';
    exit;
}

// Get products data from card_products_services (new dynamic table)
$card_id = mysqli_real_escape_string($connect, $_SESSION['card_id_inprocess']);

// Optional columns (same pattern as Products pricing): category for services list + MiniWebsite
@$colSvcCat = mysqli_query($connect, "SHOW COLUMNS FROM card_products_services LIKE 'product_category'");
if (!$colSvcCat || mysqli_num_rows($colSvcCat) == 0) {
    @mysqli_query($connect, "ALTER TABLE card_products_services ADD COLUMN product_category INT(11) NULL DEFAULT NULL AFTER product_name");
}
@$colSvcCs = mysqli_query($connect, "SHOW COLUMNS FROM card_products_services LIKE 'category_source'");
if (!$colSvcCs || mysqli_num_rows($colSvcCs) == 0) {
    @mysqli_query($connect, "ALTER TABLE card_products_services ADD COLUMN category_source VARCHAR(10) DEFAULT 'system' AFTER product_category");
}

// Clean up any blank entries (created before validation fix) for this card
mysqli_query($connect, "DELETE FROM card_products_services WHERE card_id='$card_id' AND user_id=$user_id AND TRIM(COALESCE(product_name,'')) = ''");

$products_query = mysqli_query($connect, "SELECT * FROM card_products_services WHERE card_id='$card_id' AND user_id=$user_id ORDER BY display_order ASC, id ASC");
$products = [];
while($prod_row = mysqli_fetch_array($products_query)) {
    $products[] = $prod_row;
}

// Handle form submission
if(isset($_POST['process4'])){
    // Check if this is an AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // For AJAX requests, ensure clean output
    if($is_ajax) {
        // Suppress any warnings/notices that might break JSON
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
        ini_set('display_errors', 0);
        
        // Set error handler to catch fatal errors
        register_shutdown_function(function() {
            $error = error_get_last();
            if($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                while(ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode([
                    'success' => false, 
                    'message' => 'Server error: ' . $error['message'] . ' in ' . basename($error['file']) . ' on line ' . $error['line']
                ]);
                exit;
            }
        });
    }
    
    // Verify database connection exists
    if(!isset($connect) || !$connect) {
        if($is_ajax) {
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'message' => 'Database connection error. Please refresh and try again.']);
            exit;
        }
    }
    
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
        // Fallback compression function
        function compressImage($source,$destination,$quality){
            if(!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
                return $source;
            }
            $imageInfo = @getimagesize($source);
            if($imageInfo === false) return $source;
            $mime = $imageInfo['mime'];
            $image = false;
            switch($mime){
                case 'image/jpeg':
                    if(function_exists('imagecreatefromjpeg')) $image = @imagecreatefromjpeg($source);
                    break;
                case 'image/png':
                    if(function_exists('imagecreatefrompng')) $image = @imagecreatefrompng($source);
                    break;
                case 'image/gif':
                    if(function_exists('imagecreatefromgif')) $image = @imagecreatefromgif($source);
                    break;
                default:
                    if(function_exists('imagecreatefromjpeg')) $image = @imagecreatefromjpeg($source);
            }
            if($image !== false) {
                @imagejpeg($image, $destination, $quality);
                @imagedestroy($image);
            }
            return $destination;
        }
    }

    // Ensure product-and-services upload directory exists (store product images here)
    $productServicesUploadDirAbs = __DIR__ . '/../../assets/upload/websites/product-and-services/';
    if (!is_dir($productServicesUploadDirAbs)) {
        @mkdir($productServicesUploadDirAbs, 0775, true);
    }
    // Helper to save a product image binary blob to filesystem and return only the filename
    function saveProductImageToFilesystem($binaryData, $uploadDirAbs, $cardId, $productName) {
        if (empty($binaryData) || empty($uploadDirAbs) || !is_dir($uploadDirAbs)) {
            return null;
        }
        $safeProductName = preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($productName, 0, 50));
        $fileName = $cardId . '_' . $safeProductName . '_' . date('ymdsih') . '.jpg';
        $filePath = $uploadDirAbs . $fileName;
        if(@file_put_contents($filePath, $binaryData)) {
            // Return only filename for database storage
            return $fileName;
        }
        return null;
    }
    
    $upload_success = true;
    $error_message = '';
    $products_processed = false;
    
    try {
        // Verify session variables exist
        if(!isset($_SESSION['card_id_inprocess']) || empty($_SESSION['card_id_inprocess'])) {
            throw new Exception('Card ID not found in session. Please refresh and try again.');
        }
        
        if(!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
            throw new Exception('User email not found in session. Please login again.');
        }
        
        $card_id = mysqli_real_escape_string($connect, $_SESSION['card_id_inprocess']);
        
        // Verify user_id is valid (should already be set above, but double-check)
        // Re-fetch user_id to ensure it's available in this scope
        $user_email_check = trim($_SESSION['user_email']);
        $user_email_check = mysqli_real_escape_string($connect, $user_email_check);
        $user_email_check_lower = strtolower(trim($user_email_check));
        // Use case-insensitive comparison
        $user_query_check = mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = '$user_email_check_lower' LIMIT 1");
        if(!$user_query_check) {
            throw new Exception('Database error while verifying user: ' . mysqli_error($connect));
        }
        $user_row_check = mysqli_fetch_array($user_query_check);
        $user_id_check = isset($user_row_check['id']) ? intval($user_row_check['id']) : 0;
        
        if($user_id_check <= 0) {
            throw new Exception('User account not found. Email: ' . htmlspecialchars($user_email_check) . '. Please login again.');
        }
        
        // Verify the user_id exists in user_details table (double verification)
        $verify_user_query = mysqli_query($connect, "SELECT id FROM user_details WHERE id=$user_id_check LIMIT 1");
        if(!$verify_user_query) {
            throw new Exception('Database error: ' . mysqli_error($connect));
        }
        if(mysqli_num_rows($verify_user_query) == 0) {
            throw new Exception('User ID ' . $user_id_check . ' not found in user_details table. Please contact support.');
        }
        
        // Use the verified user_id
        $user_id = $user_id_check;
    
        // Process submitted products (can be from modal or form)
        // Check for product data in POST (format: d_pro_name1, d_pro_name2, etc. or product_id)
        // First check for direct product_id (for AJAX edits)
        $direct_product_id = null;
        if(isset($_POST['product_id']) && !empty($_POST['product_id'])) {
            $direct_product_id = intval($_POST['product_id']);
        }
    
        for($x = 1; $x <= 10; $x++) {
        $product_name = '';
        $product_description = '';
        $product_id = null;
        
        // Check if product name was submitted (mandatory - reject empty, whitespace-only, or invisible chars)
        $product_name_raw = isset($_POST["d_pro_name$x"]) ? trim($_POST["d_pro_name$x"]) : '';
        if($product_name_raw !== '' && preg_match('/\S/', $product_name_raw)) {
            $products_processed = true;
            $product_name = mysqli_real_escape_string($connect, $product_name_raw);
            
            // Get product description if provided (max 400 characters)
            if(isset($_POST["d_pro_desc$x"])) {
                $product_description = trim($_POST["d_pro_desc$x"]);
                $product_description = mb_substr($product_description, 0, 400);
                $product_description = mysqli_real_escape_string($connect, $product_description);
            }

            // Category (optional; same s_/c_ keys as Products)
            $pro_cat_raw = isset($_POST["pro_category$x"]) ? trim($_POST["pro_category$x"]) : '';
            $product_category = null;
            $category_source = 'system';
            if ($pro_cat_raw !== '') {
                if (strpos($pro_cat_raw, 'c_') === 0) {
                    $product_category = intval(substr($pro_cat_raw, 2));
                    $category_source = 'custom';
                } elseif (strpos($pro_cat_raw, 's_') === 0) {
                    $product_category = intval(substr($pro_cat_raw, 2));
                    $category_source = 'system';
                }
            }
            $product_category_sql = ($product_category !== null && $product_category > 0) ? (string) intval($product_category) : 'NULL';
            $cat_source_esc = mysqli_real_escape_string($connect, $category_source);
            
            // Check if this is an update (product_id might be in hidden field)
            // Priority: product_id$x > direct product_id
            if(isset($_POST["product_id$x"]) && !empty($_POST["product_id$x"])) {
                $product_id = intval($_POST["product_id$x"]);
            } elseif($direct_product_id && $direct_product_id > 0) {
                // Direct product_id parameter (from modal edit) - use it for the first product found
                $product_id = $direct_product_id;
                $direct_product_id = null; // Use it only once
            }
            
            // Process image upload if provided
            $product_image = null;
            
            // DEBUG: Log what we're receiving
            error_log("DEBUG: Processing image for product $x: $product_name");
            error_log("  processed_product_image_data$x exists: " . (isset($_POST["processed_product_image_data$x"]) ? "YES (len=" . strlen($_POST["processed_product_image_data$x"]) . ")" : "NO"));
            error_log("  d_pro_img$x exists: " . (isset($_FILES["d_pro_img$x"]) ? "YES" : "NO"));
            
            // Process image only when a valid file is attached (image is optional - skip if none)
            $has_valid_image = false;
            if(!empty($_POST["processed_product_image_data$x"])){
                $binary_data = base64_decode($_POST["processed_product_image_data$x"]);
                if(!empty($binary_data) && strlen($binary_data) > 0) {
                    $product_image = saveProductImageToFilesystem($binary_data, $productServicesUploadDirAbs, $card_id, $product_name);
                    $has_valid_image = ($product_image !== null);
                }
            } elseif(isset($_FILES["d_pro_img$x"]) && !empty($_FILES["d_pro_img$x"]['tmp_name']) && $_FILES["d_pro_img$x"]['error'] === UPLOAD_ERR_OK){
                // Only process when file was successfully uploaded (avoids "No file uploaded" error)
                $result = null;
                if(function_exists('processHeroImageUpload')) {
                    $result = processHeroImageUpload($_FILES["d_pro_img$x"], 1200, 900);
                } elseif(function_exists('processImageUploadWithAutoCrop')) {
                    $result = processImageUploadWithAutoCrop($_FILES["d_pro_img$x"], 600, 250000, 200000, 300000, ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'], 'jpeg', null);
                } elseif(function_exists('processImageUploadWithCompression')) {
                    $result = processImageUploadWithCompression($_FILES["d_pro_img$x"], 65, 250000, ['png', 'jpeg', 'jpg']);
                }
                if($result && $result['status']) {
                    $product_image = saveProductImageToFilesystem($result['data'], $productServicesUploadDirAbs, $card_id, $product_name);
                    $has_valid_image = ($product_image !== null);
                    if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                        @unlink($result['file_path']);
                    }
                }
                // Image optional: if processing failed, continue without image (do not block save)
            } elseif(isset($_FILES["d_pro_img$x"]) && !empty($_FILES["d_pro_img$x"]['tmp_name']) && $_FILES["d_pro_img$x"]['error'] === UPLOAD_ERR_OK) {
                $filename = $_FILES["d_pro_img$x"]['name'];
                $imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $file_allow = array('png', 'jpeg', 'jpg');
                if(in_array($imageFileType, $file_allow) && $_FILES["d_pro_img$x"]['size'] <= 250000) {
                    $source = $_FILES["d_pro_img$x"]['tmp_name'];
                    $destination = $_FILES["d_pro_img$x"]['tmp_name'];
                    $compressimage = compressImage($source, $destination, 65);
                    $binary = file_get_contents($compressimage);
                    $product_image = saveProductImageToFilesystem($binary, $productServicesUploadDirAbs, $card_id, $product_name);
                    $has_valid_image = ($product_image !== null);
                }
            }
            
            // Get next display_order
            $max_order_query = mysqli_query($connect, "SELECT MAX(display_order) as max_order FROM card_products_services WHERE card_id='$card_id' AND user_id=$user_id");
            $max_order_row = mysqli_fetch_array($max_order_query);
            $display_order = isset($max_order_row['max_order']) ? intval($max_order_row['max_order']) + 1 : $x;
            
            if($product_id && $product_id > 0) {
                // Update existing product
                error_log("  Updating product ID: $product_id");
                // Verify product exists and belongs to user
                $verify_query = mysqli_query($connect, "SELECT id FROM card_products_services WHERE id=$product_id AND card_id='$card_id' AND user_id=$user_id");
                if(mysqli_num_rows($verify_query) == 0) {
                    $upload_success = false;
                    $error_message .= 'Product not found or access denied. ';
                    continue;
                }
                
                if($product_image !== null) {
                    // Escape the file path string for database
                    error_log("  Storing filename in DB: " . $product_image);
                    $product_image_escaped = mysqli_real_escape_string($connect, $product_image);
                    $update_query = "UPDATE card_products_services SET product_name='$product_name', product_description='$product_description', product_image='$product_image_escaped', product_category=$product_category_sql, category_source='$cat_source_esc' WHERE id=$product_id AND card_id='$card_id' AND user_id=$user_id";
                } else {
                    error_log("  No image to update");
                    $update_query = "UPDATE card_products_services SET product_name='$product_name', product_description='$product_description', product_category=$product_category_sql, category_source='$cat_source_esc' WHERE id=$product_id AND card_id='$card_id' AND user_id=$user_id";
                }
                $update_result = mysqli_query($connect, $update_query);
                if(!$update_result) {
                    $upload_success = false;
                    $db_error = mysqli_error($connect);
                    
                    // Check for foreign key constraint error
                    if(strpos($db_error, 'foreign key constraint') !== false || strpos($db_error, 'FOREIGN KEY') !== false) {
                        $error_message .= 'Database error: User account not found. Please refresh the page and try again. If the problem persists, please contact support. ';
                    } else {
                        $error_message .= 'Failed to update product. Error: ' . $db_error . '. ';
                    }
                }
            } else {
                // Insert new product
                error_log("  Inserting NEW product: $product_name");
                error_log("  Product image value: " . ($product_image ? "YES (filename: $product_image)" : "NO"));
                // CRITICAL: Re-verify user_id exists right before insert to prevent foreign key errors
                // This is the most important check - ensure user_id is valid and exists
                if($user_id <= 0) {
                    $upload_success = false;
                    $error_message .= 'Invalid user ID. Please login again. ';
                    continue;
                }
                
                // Final verification: Check user exists in user_details with exact ID match
                $final_verify = mysqli_query($connect, "SELECT id, email FROM user_details WHERE id = $user_id LIMIT 1");
                if(!$final_verify) {
                    $upload_success = false;
                    $error_message .= 'Database error while verifying user account: ' . mysqli_error($connect) . '. ';
                    continue;
                }
                
                $final_verify_row = mysqli_fetch_array($final_verify);
                if(!$final_verify_row || empty($final_verify_row['id']) || intval($final_verify_row['id']) != $user_id) {
                    $upload_success = false;
                    $error_message .= 'User account (ID: ' . $user_id . ') not found in database. Please login again. ';
                    continue;
                }
                
                // Ensure user_id is an integer (no decimals, no strings)
                $user_id = intval($final_verify_row['id']);
                
                // Insert with optional category columns (aligned with Products)
                $insert_success = false;
                $insert_error = '';
                $card_id_escaped = mysqli_real_escape_string($connect, $card_id);
                // $product_name / $product_description already escaped for SQL above
                if($product_image !== null) {
                    $product_image_escaped = mysqli_real_escape_string($connect, $product_image);
                    $insert_query = "INSERT INTO card_products_services (card_id, user_id, product_name, product_category, category_source, product_description, product_image, display_order) VALUES ('$card_id_escaped', $user_id, '$product_name', $product_category_sql, '$cat_source_esc', '$product_description', '$product_image_escaped', $display_order)";
                } else {
                    $insert_query = "INSERT INTO card_products_services (card_id, user_id, product_name, product_category, category_source, product_description, display_order) VALUES ('$card_id_escaped', $user_id, '$product_name', $product_category_sql, '$cat_source_esc', '$product_description', $display_order)";
                }
                $insert_result = mysqli_query($connect, $insert_query);
                if($insert_result) {
                    $insert_success = true;
                } else {
                    $insert_error = mysqli_error($connect);
                }
                
                if(!$insert_success) {
                    $upload_success = false;
                    $db_error = isset($insert_error) ? $insert_error : 'Unknown database error';
                    
                    // Check for foreign key constraint error
                    if(strpos($db_error, 'foreign key constraint') !== false || strpos($db_error, 'FOREIGN KEY') !== false) {
                        // Final diagnostic check
                        $debug_check = mysqli_query($connect, "SELECT id, email FROM user_details WHERE id = $user_id LIMIT 1");
                        $debug_row = mysqli_fetch_array($debug_check);
                        if(!$debug_row || empty($debug_row['id'])) {
                            $error_message .= 'Database error: User ID ' . $user_id . ' does not exist in user_details table. Please refresh the page and login again. ';
                        } else {
                            // User exists but foreign key still fails - database schema issue
                            $error_message .= 'Database error: Foreign key constraint failed. Verified User ID: ' . $user_id . ', Email: ' . htmlspecialchars($debug_row['email']) . '. This indicates a database schema mismatch. Please contact support. ';
                        }
                    } else {
                        $error_message .= 'Failed to insert product. Error: ' . $db_error . '. ';
                    }
                }
            }
            }
        }
    } catch(Exception $e) {
        $upload_success = false;
        $error_message = $e->getMessage();
    } catch(Error $e) {
        $upload_success = false;
        $error_message = 'Fatal error: ' . $e->getMessage();
    }
    
    // If no products were processed, it might be an error
    if(!$products_processed && $is_ajax) {
        while(ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => 'No product data received. Please enter a product name.']);
        exit;
    }
    
    if($upload_success && empty($error_message)){
        if($is_ajax) {
            // Return JSON for AJAX requests
            // Clean all output buffers
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Set proper headers
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-cache, must-revalidate');
            }
            
            // Output JSON
            $response = ['success' => true, 'message' => 'Services saved successfully'];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            
            // Flush and exit
            if(function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            exit;
        } else {
            // Regular form submission - save and reload to show updated data
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            $_SESSION['save_success'] = "Services Updated Successfully!";
            // Re-fetch products to ensure latest data is shown
            $card_id_refresh = mysqli_real_escape_string($connect, $_SESSION['card_id_inprocess']);
            $products_refresh_query = mysqli_query($connect, "SELECT * FROM card_products_services WHERE card_id='$card_id_refresh' AND user_id=$user_id ORDER BY display_order ASC, id ASC");
            // Just redirect - the page will reload with fresh data from the database query at the top
            if (!headers_sent()) {
                header('Location: services.php?card_number='.$_SESSION['card_id_inprocess']);
                exit;
            }
        }
    } else {
        if($is_ajax) {
            // Clean all output buffers
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Set proper headers
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-cache, must-revalidate');
            }
            
            // Clean error message and output JSON
            $error_msg = !empty($error_message) ? strip_tags($error_message) : 'Error! Try Again.';
            // Remove any HTML tags and clean up
            $error_msg = preg_replace('/<[^>]*>/', '', $error_msg);
            $error_msg = trim($error_msg);
            
            $response = ['success' => false, 'message' => $error_msg];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            
            // Flush and exit
            if(function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            exit;
        } else {
            $error_message = !empty($error_message) ? $error_message : '<div class="alert alert-danger">Error! Try Again.</div>';
        }
    }
}

// If we get here, it's a regular page load (not form submission)
// Include header (output buffer is already started at the top)
include '../includes/header.php';

// Image crop modal is included globally from user/includes/header.php
require_once(__DIR__ . '/../../common/mw_modal.php');
?>

<!-- Phase B · Step 12 — services.php page chrome uses .mw-* design system.
     Services table, hidden #productForm, add/edit #productModal, all data-attributes preserved.
     JS hooks intact: openProductModal(), editProductFromRow(), removeData(), saveProducts().
     Add/Edit #productModal + delete confirm use common/mw_modal.php (image crop via MwModal globally). -->
<main class="Dashboard mw-page">
    <div class="container-fluid customer_content_area mw-container">
        <div class="main-top mw-page-header">
            <h1 class="heading mw-page-title">Services</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mw-breadcrumb">
                    <li class="breadcrumb-item mw-breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item mw-breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
                </ol>
            </nav>
        </div>

        <?php if(isset($_SESSION['save_success'])): ?>
            <div class="alert alert-dismissible fade show mw-alert mw-alert-success" role="alert">
                <i class="fa fa-check-circle mw-alert-icon" aria-hidden="true"></i>
                <div class="mw-alert-body"><?php echo $_SESSION['save_success']; unset($_SESSION['save_success']); ?></div>
                <button type="button" class="close mw-alert-close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>
        <?php if(isset($error_message)): ?>
            <div class="alert mw-alert mw-alert-danger" role="alert">
                <i class="fa fa-exclamation-circle mw-alert-icon" aria-hidden="true"></i>
                <div class="mw-alert-body"><?php echo $error_message; ?></div>
            </div>
        <?php endif; ?>

        <div id="status_remove_img"></div>

        <div class="card mb-4 mw-card">
            <div class="card-body mw-card-body">
                <div class="sv-section-head">
                    <h2 class="heading font-sm-22 font-sm-24 mw-section-title sv-section-heading">Services</h2>
                    <p class="sub_title mw-helper-text">
                        <i class="fa fa-info-circle" aria-hidden="true"></i>
                        <span>You can add up to <strong style="color:var(--mw-color-text);font-weight:600">10 services</strong> to showcase on your Mini Website. <span class="text-muted">Image formats: jpg, jpeg, png, gif, webp.</span></span>
                    </p>
                </div>

                <div class="sv-toolbar">
                    <button type="button" id="addServiceBtn" class="btn btn-primary add_product mw-btn mw-btn-save" onclick="openProductModal()" <?php echo (count($products ?? []) >= 10) ? 'disabled' : ''; ?>>
                        <i class="fa fa-plus" aria-hidden="true"></i>
                        <span>Add Service</span>
                    </button>
                 </div>

                <form action="" method="POST" enctype="multipart/form-data" id="productForm" style="display:none;">
                    <!-- Hidden form fields for product data (will be populated dynamically) -->
                </form>

                <div class="Product-ServicesTable mw-table-scroll">
                    <table class="display table">
                        <thead class="mw-table-header">
                            <tr>
                                <th>Service Image</th>
                                <th>Category</th>
                                <th>Service Name</th>
                                <th>Service Description</th>
                                <th>Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $productCount = 0;
                            if(!empty($products)):
                                foreach($products as $index => $product):
                                    $productCount++;
                                    $product_id = intval($product['id']);
                                    $product_name_raw = isset($product['product_name']) ? $product['product_name'] : '';
                                    $product_name = htmlspecialchars($product_name_raw);
                                    $product_description = isset($product['product_description']) ? htmlspecialchars($product['product_description']) : '';
                                    $product_image = $product['product_image'];
                                    $prod_category_id = !empty($product['product_category']) ? intval($product['product_category']) : 0;
                                    $prod_category_source = !empty($product['category_source']) ? trim($product['category_source']) : 'system';
                                    $category_select_val = '';
                                    if ($prod_category_id > 0) {
                                        $category_select_val = ($prod_category_source === 'custom' ? 'c_' : 's_') . $prod_category_id;
                                    }
                                    $prod_category_display = '-';
                                    if ($prod_category_id > 0) {
                                        $cat_label = getStoredProductCategoryLabel($connect, $prod_category_id, $prod_category_source, $user_id);
                                        if ($cat_label !== '') {
                                            $prod_category_display = htmlspecialchars($cat_label);
                                        }
                                    }
                            ?>
                                <tr data-product-id="<?php echo $product_id; ?>" data-card-id="<?php echo $card_id; ?>" data-product-name="<?php echo htmlspecialchars($product_name_raw, ENT_QUOTES, 'UTF-8'); ?>" data-product-category-val="<?php echo htmlspecialchars($category_select_val, ENT_QUOTES, 'UTF-8'); ?>" data-product-desc="<?php echo htmlspecialchars(isset($product['product_description']) ? $product['product_description'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                                <td valign="middle">
                                        <?php if(!empty($product_image)): ?>
                                            <?php
                                            // Check if product_image is just a filename or contains path separators
                                            if(is_string($product_image) && (strpos($product_image, '/') !== false || strpos($product_image, '\\') !== false)) {
                                                // Legacy: It's a full file path - use as is
                                                $image_src = '../../' . $product_image;
                                            } elseif(is_string($product_image) && strlen($product_image) > 0 && strpos($product_image, '.') !== false) {
                                                // It's just a filename - construct the full path
                                                $image_src = '../../assets/upload/websites/product-and-services/' . $product_image;
                                            } else {
                                                // It's binary data - convert to base64
                                                $image_src = 'data:image/*;base64,' . base64_encode($product_image);
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($image_src); ?>" class="img-fluid" width="30px" alt="">
                                        <?php else: ?>
                                            <span class="text-muted">No Image</span>
                                        <?php endif; ?>
                                    </td>
                                <td valign="middle"><?php echo $prod_category_display !== '-' ? $prod_category_display : '<span class="text-muted">-</span>'; ?></td>
                                <td valign="middle"><?php echo !empty($product_name) ? $product_name : 'No Name'; ?></td>
                                    <td valign="middle">
                                        <?php if(!empty($product_description)): ?>
                                            <span class="text-truncate" title="<?php echo $product_description; ?>">
                                                <?php echo strlen($product_description) > 50 ? substr($product_description, 0, 50) . '...' : $product_description; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No Description</span>
                                        <?php endif; ?>
                                    </td>
                                   
                                    <td valign="middle">
                                        <a class="edit" href="javascript:void(0);" onclick="editProductFromRow(this)" title="Edit"><i class="fa fa-edit" style="font-size:16px;color:#007bff;margin-right:8px;"></i></a>
                                        <a class="delet" href="javascript:void(0);" onclick="removeData(<?php echo $product_id; ?>)" title="Delete"><i class="fa fa-trash" style="font-size:16px;color:#dc3545;"></i></a>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            endif;
                            
                            // Show empty message if no products
                            if($productCount == 0):
                            ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No services added yet. Click "Add Service" to add.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                   
                </div>
                <div class="Product-ServicesBtn mw-btn-row">
                    <a href="company-details.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left mw-btn mw-btn-back">
                        <span class="left_angle angle mw-btn-angle"><i class="fa fa-angle-left" aria-hidden="true"></i></span>
                        <span>Back</span>
                    </a>
                    <button type="button" class="btn btn-primary align-center save_btn mw-btn mw-btn-save" onclick="saveProducts()">
                        <img src="../../assets/images/Save.png" alt="" style="width:1.25rem;height:1.25rem;flex-shrink:0;">
                        <span>Save</span>
                    </button>
                    <a href="special-offers.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right mw-btn mw-btn-next">
                        <span>Next</span>
                        <span class="right_angle angle mw-btn-angle"><i class="fa fa-angle-right" aria-hidden="true"></i></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
ob_start();
?>
                <form id="modalProductForm" class="mw-form sv-product-modal-form">
                    <input type="hidden" id="modal_product_number" value="">
                    <div class="form-group">
                        <label>Service Image</label>
                        <div class="product-image-preview-modal" style="max-width: 280px; margin: 0 auto 15px; aspect-ratio: 4/3; overflow: hidden; border: 2px dashed #ddd; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; background: #f5f5f5;" onclick="document.getElementById('modal_product_image').click()">
                            <img id="modal_product_image_preview" 
                                 src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjgwIiBoZWlnaHQ9IjIxMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjgwIiBoZWlnaHQ9IjIxMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+" 
                                 alt="Product Image" 
                                 style="width: 100%; height: 100%; object-fit: cover; display: block;">
                        </div>
                        <input type="file" id="modal_product_image" onchange="handleProductImageUpload(this);" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('modal_product_image').click()">Choose Image</button>
                    </div>
                    <div class="form-group">
                        <label for="modal_service_name_select">Service name <span class="text-danger">*</span></label>
                        <small class="form-text text-muted d-block mb-1">Same category list as Products. Selected category name will be saved as service name.</small>
                        <select class="form-control" id="modal_service_name_select" name="modal_service_name_select">
                            <option value="">Select service name</option>
                            <?php
                            // Same product category source as products.php (flat + legacy via helper).
                            $selected_business_category_ids = [];
                            if (!empty($card_d_position_primary) && is_numeric($card_d_position_primary)) {
                                $selected_business_category_ids[] = (int) $card_d_position_primary;
                            }
                            if (!empty($card_d_position_secondary) && is_numeric($card_d_position_secondary)) {
                                $selected_business_category_ids[] = (int) $card_d_position_secondary;
                            }
                            $selected_business_category_ids = array_values(array_unique($selected_business_category_ids));

                            if (empty($selected_business_category_ids)) {
                                echo '<option value="" disabled>Set Business Categories on Company Details first</option>';
                            } else {
                                $service_cat_options = getProductCategoriesForBusinessIds($connect, $selected_business_category_ids);
                                if (empty($service_cat_options)) {
                                    echo '<option value="" disabled>No service categories for your business categories</option>';
                                } else {
                                    foreach ($service_cat_options as $cat) {
                                        $cat_name = trim((string) ($cat['label'] ?? ''));
                                        if ($cat_name === '') {
                                            continue;
                                        }
                                        $pl = ['c' => 's_' . (int) $cat['id'], 'n' => $cat_name];
                                        $jsonv = json_encode($pl, JSON_UNESCAPED_UNICODE);
                                        echo '<option value="' . htmlspecialchars($jsonv, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($cat_name) . '</option>';
                                    }
                                }

                                if ($user_id > 0) {
                                    $custom_cats_query = mysqli_query($connect, "
                                        SELECT id, category_name FROM user_custom_categories
                                        WHERE user_id = $user_id AND category_type = 'product-category' AND is_active = 1
                                        ORDER BY created_at DESC
                                    ");

                                    if ($custom_cats_query && mysqli_num_rows($custom_cats_query) > 0) {
                                        echo '<optgroup label="My Custom Categories">';
                                        while ($custom_cat = mysqli_fetch_assoc($custom_cats_query)) {
                                            $cat_name = trim((string) ($custom_cat['category_name'] ?? ''));
                                            if ($cat_name === '') {
                                                continue;
                                            }
                                            $pl = ['c' => 'c_' . (int) $custom_cat['id'], 'n' => $cat_name];
                                            $jsonv = json_encode($pl, JSON_UNESCAPED_UNICODE);
                                            echo '<option value="' . htmlspecialchars($jsonv, ENT_QUOTES, 'UTF-8') . '">[Custom] ' . htmlspecialchars($cat_name) . '</option>';
                                        }
                                        echo '</optgroup>';
                                    }
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modal_product_description">Service Description</label>
                        <textarea class="form-control" id="modal_product_description" maxlength="400" placeholder="Enter service description (max 400 characters)" rows="4" oninput="updateCharCount()" onpaste="updateCharCount()" onkeyup="updateCharCount()"></textarea>
                        <small class="form-text text-muted">
                            <strong id="char_counter_display">0/400</strong> characters
                        </small>
                    </div>
                </form>
<?php
$product_modal_body = ob_get_clean();

mw_modal_render([
    'id'       => 'productModal',
    'size'     => 'lg',
    'title'    => 'Add Service',
    'subtitle' => 'Upload image and select a service name',
    'icon'     => 'fa-cogs',
    'body'     => $product_modal_body,
    'footer'   => mw_modal_footer([
        ['label' => 'Cancel', 'class' => 'mw-btn mw-btn-cancel', 'attrs' => 'type="button" data-mw-modal-close'],
        ['label' => 'Add Service', 'class' => 'mw-btn mw-btn-save', 'attrs' => 'type="button" id="productModalSaveBtn"'],
    ]),
    'hidden'   => true,
]);
?>

<script>
var currentProductId = null;
var productImageFiles = {};

// Update character count display (max 400 characters) - updates live while typing
function updateCharCount() {
    var textarea = document.getElementById('modal_product_description');
    var counter = document.getElementById('char_counter_display');
    
    if(textarea && counter) {
        var count = textarea.value.length;
        counter.textContent = count + '/400';
        counter.style.color = count > 400 ? '#dc3545' : '';
    }
}

// Bind character count - inline oninput/onpaste/onkeyup on textarea for reliable live updates
// Also bind via jQuery when DOM ready (fallback)
$(document).ready(function() {
    $('#modal_product_description').on('input paste keyup', updateCharCount);
    var saveBtn = document.getElementById('productModalSaveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', addProductToForm);
    }
});

var PRODUCT_MODAL_PLACEHOLDER_IMG = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjgwIiBoZWlnaHQ9IjIxMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjgwIiBoZWlnaHQ9IjIxMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+';

function setProductModalMode(isEdit) {
    var titleEl = document.getElementById('productModalTitle');
    var saveBtn = document.getElementById('productModalSaveBtn');
    if (titleEl) {
        titleEl.textContent = isEdit ? 'Edit Service' : 'Add Service';
    }
    if (saveBtn) {
        saveBtn.textContent = isEdit ? 'Update Service' : 'Add Service';
    }
}

function showProductModal() {
    if (window.MwModal && typeof window.MwModal.open === 'function') {
        window.MwModal.open('productModal');
    }
}

function resetProductModalForm() {
    $('#modal_product_number').val('');
    $('#modal_service_name_select').val('');
    $('#modal_product_description').val('');
    $('#modal_product_image').val('');
    $('#modal_product_image_preview').attr('src', PRODUCT_MODAL_PLACEHOLDER_IMG);
    $('.product-image-preview-modal').css('border', '2px dashed #ddd');
    updateCharCount();
}

// Open product modal
function openProductModal() {
    var serviceCount = $('tr[data-product-id]').length;
    if(serviceCount >= MAX_SERVICES) {
        alert('You can add up to ' + MAX_SERVICES + ' services only. Please delete one before adding a new service.');
        return;
    }
    currentProductId = null;
    processedProductImageData = null;
    resetProductModalForm();
    setProductModalMode(false);
    showProductModal();
}

// Edit product from row (reads data attributes + img src to avoid HTML attribute size limits)
function editProductFromRow(editLink) {
    var row = $(editLink).closest('tr');
    var productId = row.data('product-id');
    var productName = row.data('product-name') || '';
    var productDescription = row.data('product-desc') || '';
    var productImageData = '';
    var categoryVal = row.attr('data-product-category-val') || '';
    var img = row.find('td:first img');
    if (img.length && img.attr('src')) {
        var src = img.attr('src');
        if (src.indexOf('data:image') === 0 && src.indexOf('base64,') !== -1) {
            productImageData = src.split('base64,')[1] || '';
        } else if (src.indexOf('product-and-services/') !== -1) {
            productImageData = 'filename:' + src.split('product-and-services/')[1];
        } else if (src.indexOf('../../') === 0) {
            productImageData = 'filepath:' + src.replace('../../', '');
        }
    }
    editProduct(productId, productName, productImageData, productDescription, categoryVal);
}

// Edit product
function editProduct(productId, productName, productImageData, productDescription, categoryVal) {
    currentProductId = productId;
    processedProductImageData = null; // Reset processed image data
    $('#modal_product_number').val(productId);
    $('#modal_product_description').val(productDescription || '');
    
    // Load existing image if available
    if(productImageData && productImageData !== '') {
        // Check if it's a filename (starts with 'filename:'), file path (starts with 'filepath:') or base64 data
        if(productImageData.indexOf('filename:') === 0) {
            // It's just a filename - construct the full path
            var fileName = productImageData.substring(9); // Remove 'filename:' prefix
            $('#modal_product_image_preview').attr('src', '../../assets/upload/websites/product-and-services/' + fileName);
        } else if(productImageData.indexOf('filepath:') === 0) {
            // Legacy: It's a full file path
            var filePath = productImageData.substring(9); // Remove 'filepath:' prefix
            $('#modal_product_image_preview').attr('src', '../../' + filePath);
        } else {
            // It's base64 data
            $('#modal_product_image_preview').attr('src', 'data:image/*;base64,' + productImageData);
        }
    } else {
        $('#modal_product_image_preview').attr('src', PRODUCT_MODAL_PLACEHOLDER_IMG);
        $('.product-image-preview-modal').css('border', '2px dashed #ddd');
    }

    setProductModalMode(true);
    selectServiceOptionByCategoryAndName(categoryVal || '', productName || '');
    updateCharCount();
    showProductModal();
}

// Store processed image data for form submission
var processedProductImageData = null;

// Handle product image upload - use common ImageCropUpload modal
function handleProductImageUpload(input) {
    if(input.files && input.files[0]) {
        var file = input.files[0];
        var allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
        var maxSize = 10 * 1024 * 1024; // 10MB (will be auto-optimized to 250KB)
        
        if(allowedTypes.indexOf(file.type) === -1) {
            alert('Only JPG, PNG, GIF, and WEBP images are allowed.');
            $(input).val('');
            processedProductImageData = null;
            return;
        }
        
        if(file.size > maxSize) {
            alert('Image size must be 10MB or less. The image will be automatically optimized to 250KB.');
            $(input).val('');
            processedProductImageData = null;
            return;
        }
        
        // Truncate file name if too long
        var fileName = file.name;
        var maxLength = 25;
        if(fileName.length > maxLength) {
            var ext = fileName.substring(fileName.lastIndexOf('.'));
            var nameWithoutExt = fileName.substring(0, fileName.lastIndexOf('.'));
            fileName = nameWithoutExt.substring(0, maxLength - ext.length - 3) + '...' + ext;
        }
        
        // Use common ImageCropUpload modal (from common/image_upload_crop_modal.php)
        if (typeof ImageCropUpload !== 'undefined') {
            // Set up the success callback before opening the modal
            window.productImageCropCallback = function(base64Data) {
                // base64Data from ImageCropUpload is already clean (no data URI prefix)
                // Store it for form submission
                processedProductImageData = base64Data;
                
                // Build the data URI for preview display
                var previewDataUri = 'data:image/jpeg;base64,' + base64Data;
                console.log('Image crop successful, stored base64 data');
                
                // Update preview image with the cropped image
                $('#modal_product_image_preview').attr('src', previewDataUri);
                
                // Add visual feedback with green border (4:3 ratio display)
                $('#modal_product_image_preview').css({
                    'width': '100%',
                    'height': '100%',
                    'object-fit': 'cover',
                    'display': 'block'
                });
                $('#modal_product_image_preview').closest('.product-image-preview-modal').css('border', '2px solid #28a745');
            };
            
            ImageCropUpload.open(file, {
                method: 'base64',
                hiddenField: null,  // We'll handle the base64 data in onSuccess
                previewSelector: null,  // Don't auto-update preview
                spanSelector: null,
                title: 'Adjust & Crop Service Image (4:3 ratio)',
                aspectRatio: 4/3,
                cropWidth: 1200,
                cropHeight: 900,
                onSuccess: function(base64Data) {
                    // Call our custom callback
                    if (window.productImageCropCallback) {
                        window.productImageCropCallback(base64Data);
                    }
                },
                onError: function(msg) {
                    var errMsg = msg || 'Error processing image. Please try again.';
                    if (window.MwModal && window.MwModal.alert) {
                        window.MwModal.alert({ title: 'Service Image', message: errMsg });
                    } else {
                        alert(errMsg);
                    }
                    $(input).val('');
                    processedProductImageData = null;
                }
            });
            $(input).val('');
        } else {
            alert('Image crop tool not available. Please refresh the page.');
        }
    }
}

// Add product to form and save immediately (with double-submit prevention)
var addProductSubmitting = false;
var MAX_SERVICES = 10;

function updateAddButtonState() {
    var btn = document.getElementById('addServiceBtn');
    if(!btn) return;
    var serviceCount = $('tr[data-product-id]').length;
    if(serviceCount >= MAX_SERVICES) {
        btn.disabled = true;
        btn.setAttribute('title', 'Maximum ' + MAX_SERVICES + ' services allowed. Delete one to add more.');
    } else {
        btn.disabled = false;
        btn.removeAttribute('title');
    }
}

/** Match saved category + name to grouped dropdown; if missing (legacy row), append a temporary option. */
function selectServiceOptionByCategoryAndName(catVal, productName) {
    var sel = document.getElementById('modal_service_name_select');
    if (!sel) return;
    var pm = String(productName || '').trim();
    var cv = String(catVal || '').trim();
    sel.querySelectorAll('option[data-legacy-svc="1"]').forEach(function(o) { o.remove(); });
    sel.value = '';
    if (!pm) return;
    for (var i = 0; i < sel.options.length; i++) {
        var o = sel.options[i];
        if (!o.value) continue;
        try {
            var p = JSON.parse(o.value);
            if (p && p.c === cv && p.n === pm) {
                sel.selectedIndex = i;
                return;
            }
        } catch (e1) {}
    }
    try {
        var inj = document.createElement('option');
        inj.value = JSON.stringify({ c: cv, n: pm });
        inj.textContent = pm ? ('Saved — ' + pm) : pm;
        inj.setAttribute('data-legacy-svc', '1');
        sel.appendChild(inj);
        sel.value = inj.value;
    } catch (e2) {}
}

function addProductToForm() {
    if(addProductSubmitting) {
        return; // Prevent double-click / multiple submissions
    }
    var nameSel = document.getElementById('modal_service_name_select');
    var rawVal = nameSel ? String(nameSel.value || '').trim() : '';
    if (!rawVal) {
        alert('Please select a service name from the list (grouped by category).');
        return;
    }
    var payload = null;
    try {
        payload = JSON.parse(rawVal);
    } catch (e) {
        alert('Invalid service selection. Please pick again.');
        return;
    }
    var productName = payload && payload.n ? String(payload.n).trim() : '';
    var catVal = payload && payload.c ? String(payload.c).trim() : '';
    if (!productName || !/\S/.test(productName)) {
        alert('Please select a valid service name.');
        return;
    }
    if (productName.length > 200) productName = productName.substring(0, 200);
    var productId = $('#modal_product_number').val();
    var isEdit = (productId && productId !== '');
    // Limit to 10 services when adding new (not when editing)
    if(!isEdit) {
        var serviceCount = $('tr[data-product-id]').length;
        if(serviceCount >= MAX_SERVICES) {
            alert('You can add up to ' + MAX_SERVICES + ' services only. Please delete one before adding a new service.');
            return;
        }
    }
    addProductSubmitting = true;
    
    var productDescription = $('#modal_product_description').val().trim();
    if(productDescription.length > 400) {
        productDescription = productDescription.substring(0, 400);
    }
    
    // Create FormData for AJAX submission
    var formData = new FormData();
    formData.append('process4', '1');
    
    // Use a temporary slot number for form submission (PHP will handle the actual insert/update)
    var tempSlot = '1'; // Use slot 1 for simplicity, PHP will handle ID logic
    formData.append('d_pro_name' + tempSlot, productName);
    formData.append('d_pro_desc' + tempSlot, productDescription);
    if (catVal) formData.append('pro_category' + tempSlot, catVal);
    
    // If editing, include product_id both ways for compatibility
    if(isEdit) {
        formData.append('product_id' + tempSlot, productId);
        formData.append('product_id', productId); // Direct parameter as fallback
    }
    
    // Handle image file - use processed image data if available
    if(processedProductImageData) {
        // Use processed image data from AJAX (already clean base64 from ImageCropUpload)
        console.log('Submitting processed image, length: ' + processedProductImageData.length);
        formData.append('processed_product_image_data' + tempSlot, processedProductImageData);
    } else {
        // Fallback to original file if no processed data
        var imageFile = document.getElementById('modal_product_image').files[0];
        if(imageFile) {
            formData.append('d_pro_img' + tempSlot, imageFile);
        }
    }
    
    // Show loading
    var loadingMsg = '<div class="alert alert-info">Saving service...</div>';
    $('#status_remove_img').html(loadingMsg);
    
    // Get image preview first (before AJAX)
    var imagePreview = '';
    var updateTableCallback = function() {
        // Submit via AJAX
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            dataType: 'json',
            success: function(response) {
                if(response && response.success) {
                    // Reload page to show updated data (or refresh table via AJAX)
                    $('#status_remove_img').html('<div class="alert alert-success">' + (response.message || 'Service saved successfully!') + '</div>');
                    updateAddButtonState();
                    setTimeout(function() {
                        // Reload the page to show updated product list
                        window.location.reload();
                    }, 1000);
                } else {
                    addProductSubmitting = false;
                    $('#status_remove_img').html('<div class="alert alert-danger">' + (response.message || 'Error saving service.') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                addProductSubmitting = false;
                console.log('AJAX Error:', {status: status, error: error, response: xhr.responseText, statusCode: xhr.status});
                
                // Try to parse as JSON first
                try {
                    var response = JSON.parse(xhr.responseText);
                    if(response && !response.success) {
                        $('#status_remove_img').html('<div class="alert alert-danger">' + (response.message || 'Error saving service.') + '</div>');
                        return;
                    }
                } catch(e) {
                    // Not JSON - show the actual response for debugging
                    var errorMsg = 'Error saving service. ';
                    if(xhr.responseText) {
                        // Try to extract error message from HTML response
                        var textResponse = xhr.responseText.substring(0, 200);
                        errorMsg += 'Response: ' + textResponse;
                    } else {
                        errorMsg += 'Status: ' + status + ', Error: ' + error;
                    }
                    $('#status_remove_img').html('<div class="alert alert-danger">' + errorMsg + '</div>');
                }
            }
        });
    };
    
    // Use processed image if available, otherwise use file preview
    if(processedProductImageData) {
        // processedProductImageData is already clean base64 from ImageCropUpload
        imagePreview = '<img src="data:image/jpeg;base64,' + processedProductImageData + '" class="img-fluid" width="100px" alt="">';
        updateTableCallback();
    } else {
        var imageFile = document.getElementById('modal_product_image').files[0];
        if(imageFile) {
            var reader = new FileReader();
            reader.onload = function(e) {
                // For unprocessed files, show a preview
                imagePreview = '<img src="' + e.target.result + '" class="img-fluid" width="100px" alt="">';
                updateTableCallback();
            };
            reader.readAsDataURL(imageFile);
        } else {
            // No new image selected - use existing image from modal preview
            var previewSrc = $('#modal_product_image_preview').attr('src');
            if(previewSrc && previewSrc !== '') {
                imagePreview = '<img src="' + previewSrc + '" class="img-fluid" width="100px" alt="">';
            } else {
                imagePreview = '<span class="text-muted">No Image</span>';
            }
            updateTableCallback();
        }
    }
    
    // Close modal immediately
    closeProductModal();
}

// Save products - just show success message (no redirect)
function saveProducts() {
    // Show success message (services are already saved via AJAX when added)
    $('#status_remove_img').html('<div class="alert alert-success">All services saved successfully!</div>');
    setTimeout(function(){
        $('#status_remove_img').html('');
    }, 2000);
}

// Remove product
function removeData(productId) {
    function doDelete() {
        $('#status_remove_img').css('color','blue');
        
        $.ajax({
            url: '../../admin/js_request.php',
            method: 'POST',
            data: {product_id: productId, action: 'delete_product'},
            dataType: 'text',
            success: function(data){
                $('#status_remove_img').html(data);
                if(data.includes('success')){
                    // Remove the row from table
                    $('tr[data-product-id="' + productId + '"]').remove();
                    
                    // Check if table is now empty
                    var tableBody = $('.Product-ServicesTable tbody');
                    if(tableBody.find('tr[data-product-id]').length === 0) {
                        tableBody.html('<tr><td colspan="5" class="text-center text-muted">No services added yet. Click "Add Service" to add.</td></tr>');
                    }
                    
                    // Re-enable Add button when under 10 services
                    updateAddButtonState();
                    
                    $('#status_remove_img').html('<div class="alert alert-success">Service removed successfully!</div>');
                    setTimeout(function(){
                        $('#status_remove_img').html('');
                    }, 2000);
                }
            },
            error: function(){
                $('#status_remove_img').html('<div class="alert alert-danger">Error deleting service. Please try again.</div>');
            }
        });
    }
    if (window.MwModal && typeof window.MwModal.confirm === 'function') {
        window.MwModal.confirm({
            title: 'Remove service?',
            message: 'Are you sure you want to remove this service?',
            confirmText: 'Remove',
            cancelText: 'Cancel',
            confirmClass: 'mw-btn mw-btn-danger',
            onConfirm: doDelete
        });
    } else if (confirm('Are you sure you want to remove this service?')) {
        doDelete();
    }
}

// Close product modal
function closeProductModal() {
    if (window.MwModal && typeof window.MwModal.close === 'function') {
        window.MwModal.close('productModal');
    }
    currentProductId = null;
}

(function() {
    var modalEl = document.getElementById('productModal');
    if (!modalEl) return;
    modalEl.addEventListener('mw-modal:closed', function() {
        currentProductId = null;
    });
})();
</script>

<style>
        footer{
        margin-bottom:54px;
    }
    .savebutton span{
        font-size:26px;
    }
    .savebutton{
        display: flex !important;
    
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    }
    .headingTop{
        font-size:28px;
        font-weight:500px;
        line-height:67px;
        padding-left:5px;
    }
    .heading{
        position: relative;
    }
  .card-body  .heading:after
    {
        content: '';
        width: 170px;
        height: 1px;
        background: #ffb300;
        position: absolute;
        left: 8px;
        bottom: 0px;
    }
    .Product-ServicesTable table td:last-child img{
        width: 20px;
    }
    .card-body{
        padding:50px !important;
        padding-top:30px !important;
    }
    .Dashboard .heading{
        font-size:24px !important;
    }
    .add_product{
        border-radius: 4px;
        display: flex !important;
        justify-content: center;
        align-items: center;
        gap: 10px;
        padding:10px;
    }
    .sub_title{
        font-size:20px;
        margin-bottom:5px;
    }
    small{
        font-size:16px;
    }
    .add_product i,.add_product span{
        font-size:19px !important;
    }
    .Product-ServicesTable {
    margin: 20px auto;
}
/* Table header/cell styles: design system in user/includes/header.php */
.Product-ServicesTable .text-truncate {
    display: inline-block;
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
@media screen and (max-width: 768px) {
        .card-body form {
    padding: 0px 15px;
}
.card-body {
    padding: 25px !important;
    padding-bottom: 100px !important;
}
.font-sm-22{
    font-size: 22px !important;
}

.submitBtnSection{
    margin-top:20px;
}
.card-body .heading {
    font-size: 22px !important;
    font-weight: 500;
}
.card-bodyheading:after {
    content: '';
    width: 164px;
    height: 2px;
    background: #ffb300;
    position: absolute;
    left: 8px;
    bottom: 0px;
}
.card-body p{
    font-size: 15px;
    line-height: 20px;
}
.add_product i, .add_product span {
    font-size: 22px !important;
}
/* Mobile table scroll + .main-top: design system in user/includes/header.php */

    .card-body p {
        font-size: 16px;
        line-height: 20px;
    }
    .add_product i, .add_product span {
        font-size: 20px !important;
    }

.Copyright-left,
.Copyright-right{
    padding:0px;
}
    }
.add_product:hover{
background-color: #ffbe17a6;
}
.add_product:disabled,
.add_product[disabled]{
    opacity: 0.6;
    cursor: not-allowed;
}


.Product-ServicesBtn{
        padding:0 40px;
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
    .Product-ServicesBtn:not(.mw-btn-row) button {
        padding: 7px !important;
    }
    .card-body .heading{
        font-size:24px ;
        font-weight: 500;
    }

    #imageCropModal{
        z-index: 10000 !important;
    }

</style>

<!-- Phase B · Step 12 — design-system chrome overrides for services.
     Sit AFTER the page-local <style> so they win the cascade for chrome elements. -->
<style>
    /* Section heading — promote .heading to design-system style */
    main.Dashboard .heading.mw-section-title.sv-section-heading {
        font-size: var(--mw-font-section-title);
        line-height: 1.3;
        color: var(--mw-color-text);
        font-weight: 600;
        margin: 0 0 0.5rem;
        display: inline-block;
        position: relative;
        padding-bottom: 0.5rem;
        background: transparent;
    }
    @media (min-width: 768px) {
        main.Dashboard .heading.mw-section-title.sv-section-heading { font-size: var(--mw-font-section-title-lg); }
    }
    main.Dashboard .heading.mw-section-title.sv-section-heading::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: 0;
        width: 3rem;
        height: 2px;
        background: var(--mw-color-primary);
        border-radius: 9999px;
    }
    main.Dashboard .sv-section-head { margin-bottom: 1.25rem; }

    /* Toolbar (Add Service + count pill) */
    main.Dashboard .sv-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin: 0.5rem 0 1.25rem; }
    main.Dashboard .sv-toolbar .add_product.mw-btn.mw-btn-save { margin: 0 !important; }
    main.Dashboard .sv-toolbar .sv-count-pill { padding: 0.375rem 0.75rem; }

    /* Add/Edit service modal (MwModal) */
    #productModal .sv-product-modal-form .product-image-preview-modal {
        max-width: 280px;
        margin: 0 auto 15px;
        aspect-ratio: 4 / 3;
        overflow: hidden;
        border: 2px dashed var(--mw-color-border, #e2e8f0);
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
    }
    #productModal .sv-product-modal-form #modal_service_name_select {
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238a94a6' stroke-width='2'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.65rem center;
        background-size: 1rem;
        padding-right: 2.25rem;
    }

    /* Button row — neutralize legacy rules; layout from .mw-btn-row in header.php */
    main.Dashboard .Product-ServicesBtn.mw-btn-row {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.75rem !important;
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin-top: 1.5rem !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        position: relative !important;
        box-sizing: border-box !important;
    }
    main.Dashboard .Product-ServicesBtn.mw-btn-row .mw-btn,
    main.Dashboard .Product-ServicesBtn.mw-btn-row .save_btn {
        position: static !important;
        bottom: auto !important;
        left: auto !important;
        right: auto !important;
        width: auto !important;
        max-width: none !important;
        min-width: 0 !important;
        height: auto !important;
        min-height: 3rem !important;
        margin: 0 !important;
        margin-top: 0 !important;
        padding: var(--mw-btn-padding-y) var(--mw-btn-padding-x) !important;
        flex: 0 0 auto !important;
        order: unset !important;
    }
    main.Dashboard .Product-ServicesBtn.mw-btn-row .align-center img {
        width: 1.25rem !important;
        height: 1.25rem !important;
    }
    @media screen and (max-width: 767.98px) {
        main.Dashboard .card-body.mw-card-body {
            padding-bottom: 1.5rem !important;
        }
        main.Dashboard .Product-ServicesBtn.mw-btn-row {
            flex-wrap: wrap !important;
            justify-content: stretch !important;
        }
        main.Dashboard .Product-ServicesBtn.mw-btn-row .mw-btn-save {
            order: 1 !important;
            flex: 1 1 100% !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        main.Dashboard .Product-ServicesBtn.mw-btn-row .mw-btn-back {
            order: 2 !important;
            flex: 1 1 calc(50% - 0.375rem) !important;
            max-width: calc(50% - 0.375rem) !important;
        }
        main.Dashboard .Product-ServicesBtn.mw-btn-row .mw-btn-next {
            order: 3 !important;
            flex: 1 1 calc(50% - 0.375rem) !important;
            max-width: calc(50% - 0.375rem) !important;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>

