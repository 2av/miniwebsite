<?php
// Check if this is an AJAX request FIRST - before any output
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Start output buffering for AJAX requests immediately to prevent any output
if($is_ajax && isset($_POST['product'])) {
    ob_start();
    // Suppress warnings/notices for AJAX requests
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
}

// Handle card_number from URL, session, or cookie
// MUST be done before any output (before including header.php)
require_once(__DIR__ . '/../../app/config/database.php');

// Handle AJAX request to get product names by category
if($is_ajax && isset($_GET['action']) && $_GET['action'] == 'get_product_names') {
    header('Content-Type: application/json');
    
    if(isset($_GET['category_id'])) {
        $category_id = intval($_GET['category_id']);
        
        // Get product names for the selected product category
        $query = "SELECT id, category_name FROM product_categories 
                 WHERE parent_id = $category_id AND is_active = 1 AND category_type = 'product-name'
                 ORDER BY display_order, category_name ASC";
        
        $result = mysqli_query($connect, $query);
        $products = [];
        
        while($row = mysqli_fetch_assoc($result)) {
            $products[] = [
                'id' => $row['id'],
                'name' => htmlspecialchars($row['category_name'])
            ];
        }
        
        echo json_encode([
            'success' => true,
            'products' => $products
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Category ID not provided'
        ]);
    }
    exit;
}

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
        
        if(!function_exists('processImageUploadWithAutoCrop')) {
            throw new Exception('Image processing function not available');
        }
        
        // Check file upload error
        if($_FILES['product_image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $_FILES['product_image']['error']);
        }
        
        $result = processImageUploadWithAutoCrop(
            $_FILES['product_image'], 
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

// Fetch existing card data
$row = [];
$products_data = [];
$user_id = 0;

if(isset($_SESSION['card_id_inprocess']) && !empty($_SESSION['card_id_inprocess'])) {
    $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');
    if(mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_array($query);
        
        // Store selected business categories for use in product category binding
        $card_d_position_primary = isset($row['d_position_primary']) ? $row['d_position_primary'] : '';
        $card_d_position_secondary = isset($row['d_position_secondary']) ? $row['d_position_secondary'] : '';
        
        // Get user_id directly from user_details table (unified table for all users)
        $user_email_escaped = mysqli_real_escape_string($connect, $_SESSION['user_email']);
        $user_email_lower = strtolower(trim($user_email_escaped));
        $user_id = 0;
        
        $user_details_query = mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = '$user_email_lower' LIMIT 1");
        if($user_details_query && mysqli_num_rows($user_details_query) > 0) {
            $user_details_row = mysqli_fetch_array($user_details_query);
            $user_id = isset($user_details_row['id']) ? intval($user_details_row['id']) : 0;
        }
        
        // Get products from new table (card_product_pricing)
        $card_id = mysqli_real_escape_string($connect, $_SESSION['card_id_inprocess']);
        // Ensure category_source column exists (to distinguish system vs custom category - avoids ID collision)
        $col_check = @mysqli_query($connect, "SHOW COLUMNS FROM card_product_pricing LIKE 'category_source'");
        if(!$col_check || mysqli_num_rows($col_check) == 0) {
            @mysqli_query($connect, "ALTER TABLE card_product_pricing ADD category_source VARCHAR(10) DEFAULT 'system' AFTER product_category");
        }
        if($user_id > 0) {
            $products_query = mysqli_query($connect, "SELECT * FROM card_product_pricing WHERE card_id='$card_id' AND user_id=$user_id ORDER BY display_order ASC, id ASC");
            while($prod_row = mysqli_fetch_array($products_query)) {
                $products_data[] = $prod_row;
            }
        }
    } else {
        echo '<script>alert("Card ID not found or does not belong to your account."); window.location.href="business-name.php";</script>';
        exit;
    }
} else {
    echo '<script>alert("No card selected. Please select a card first."); window.location.href="business-name.php";</script>';
    exit;
}

// Handle form submission
$error_message = '';

if(isset($_POST['product'])){
    // Verify user_id is available
    if($user_id <= 0) {
        if($is_ajax) {
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['success' => false, 'message' => 'User not found. Please login again.']);
            exit;
        }
        $error_message = '<div class="alert alert-danger">User not found. Please login again.</div>';
    } else {
        
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
        }
        
        // Verify function is loaded (for debugging)
        if(!function_exists('processImageUploadWithAutoCrop') && !function_exists('compressImage')) {
            // Fallback compression function (only if neither function exists)
            function compressImage($source,$destination,$quality){
            // Check if GD library is available
            if(!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
                // GD library not available - return source file without compression
                return $source;
            }
            
            $imageInfo = @getimagesize($source);
            if($imageInfo === false) {
                return $source;
            }
            
            $mime = $imageInfo['mime'];
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
                // If image creation failed, return source without compression
                return $source;
            }
            
            // Compress and save
            @imagejpeg($image, $destination, $quality);
            @imagedestroy($image);
            return $destination;
        }
        }
        
        // Ensure product-pricing upload directory exists (store product images here)
        $productPricingUploadDirAbs = __DIR__ . '/../../assets/upload/websites/product-pricing/';
        if (!is_dir($productPricingUploadDirAbs)) {
            @mkdir($productPricingUploadDirAbs, 0775, true);
        }
        // Helper to save a product image binary blob to filesystem and return only the filename
        function saveProductPricingImageToFilesystem($binaryData, $uploadDirAbs, $cardId, $productName) {
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
        
        $card_id = mysqli_real_escape_string($connect, $_SESSION['card_id_inprocess']);
        $products_processed = false;
        
        // Process submitted products (can be from modal or form)
        // First, check for direct product_id (for AJAX updates from modal)
        $processed_product_ids = []; // Track which product IDs we've already processed
        
        // Process direct product_id first (for AJAX modal submissions)
        if(isset($_POST['product_id']) && !empty($_POST['product_id'])) {
            $direct_product_id = intval($_POST['product_id']);
            $product_name = '';
            $product_mrp = 0.00;
            $product_price = 0.00;
            $product_image = null;
            
            // Find which slot number this product_id is associated with
            $slot_found = null;
            for($x = 1; $x <= 60; $x++) {
                if(isset($_POST["pro_name$x"]) && !empty(trim($_POST["pro_name$x"]))) {
                    $slot_found = $x;
                    break;
                }
            }
            
            if($slot_found) {
                $product_name_raw = trim($_POST["pro_name$slot_found"]);
                $product_name_raw = function_exists('mb_substr') ? mb_substr($product_name_raw, 0, 30) : substr($product_name_raw, 0, 30);
                $product_name = mysqli_real_escape_string($connect, $product_name_raw);
                $product_category = null;
                $category_source = 'system';
                if(isset($_POST["pro_category$slot_found"]) && trim($_POST["pro_category$slot_found"]) !== '') {
                    $cat_val = trim($_POST["pro_category$slot_found"]);
                    if(preg_match('/^c_(\d+)$/', $cat_val, $m)) {
                        $product_category = intval($m[1]);
                        $category_source = 'custom';
                    } elseif(preg_match('/^s_(\d+)$/', $cat_val, $m)) {
                        $product_category = intval($m[1]);
                        $category_source = 'system';
                    } else {
                        $product_category = intval($cat_val);
                    }
                }
                
                // Get MRP and price
                if(isset($_POST["pro_mrp$slot_found"]) && !empty(trim($_POST["pro_mrp$slot_found"]))) {
                    $product_mrp = floatval(preg_replace('/[^0-9.]/', '', $_POST["pro_mrp$slot_found"]));
                }
                if(isset($_POST["pro_price$slot_found"]) && !empty(trim($_POST["pro_price$slot_found"]))) {
                    $product_price = floatval(preg_replace('/[^0-9.]/', '', $_POST["pro_price$slot_found"]));
                }
                
                // Get product description if provided (max 400 characters)
                $product_description = '';
                if(isset($_POST["pro_desc$slot_found"])) {
                    $product_description = trim($_POST["pro_desc$slot_found"]);
                    $product_description = mb_substr($product_description, 0, 400);
                    $product_description = mysqli_real_escape_string($connect, $product_description);
                }
                
                // Process image only when valid file attached (image optional - skip if none)
                if(!empty($_POST["processed_product_image_data$slot_found"])){
                    $binary_data = base64_decode($_POST["processed_product_image_data$slot_found"]);
                    if(!empty($binary_data) && strlen($binary_data) > 0) {
                        $product_image = saveProductPricingImageToFilesystem($binary_data, $productPricingUploadDirAbs, $card_id, $product_name);
                    }
                } elseif(isset($_FILES["pro_img$slot_found"]) && !empty($_FILES["pro_img$slot_found"]['tmp_name']) && $_FILES["pro_img$slot_found"]['error'] === UPLOAD_ERR_OK) {
                    if(function_exists('processImageUploadWithAutoCrop')) {
                        try {
                            $result = processImageUploadWithAutoCrop(
                                $_FILES["pro_img$slot_found"], 
                                600, 250000, 200000, 300000,
                                ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
                                'jpeg', null
                            );
                            if($result['status']) {
                                $product_image = saveProductPricingImageToFilesystem($result['data'], $productPricingUploadDirAbs, $card_id, $product_name);
                                if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                                    @unlink($result['file_path']);
                                }
                            }
                        } catch(Exception $e) {
                            // Image optional: continue without image, do not block save
                        }
                    }
                }
                
                // Update existing product
                $verify_query = mysqli_query($connect, "SELECT id FROM card_product_pricing WHERE id=$direct_product_id AND card_id='$card_id' AND user_id=$user_id");
                if(mysqli_num_rows($verify_query) > 0) {
                    $cat_source_esc = mysqli_real_escape_string($connect, $category_source);
                    if($product_image !== null) {
                        $product_image_escaped = mysqli_real_escape_string($connect, $product_image);
                        $product_category_value = ($product_category !== null && $product_category > 0) ? $product_category : 'NULL';
                        $update_query = "UPDATE card_product_pricing SET product_name='$product_name', product_category=$product_category_value, category_source='$cat_source_esc', product_description='$product_description', product_image='$product_image_escaped', mrp=$product_mrp, selling_price=$product_price WHERE id=$direct_product_id AND card_id='$card_id' AND user_id=$user_id";
                    } else {
                        $product_category_value = ($product_category !== null && $product_category > 0) ? $product_category : 'NULL';
                        $update_query = "UPDATE card_product_pricing SET product_name='$product_name', product_category=$product_category_value, category_source='$cat_source_esc', product_description='$product_description', mrp=$product_mrp, selling_price=$product_price WHERE id=$direct_product_id AND card_id='$card_id' AND user_id=$user_id";
                    }
                    $update_result = mysqli_query($connect, $update_query);
                    if(!$update_result) {
                        $error_message .= '<div class="alert alert-danger">Failed to update product. Error: ' . mysqli_error($connect) . '</div>';
                    }
                    $processed_product_ids[] = $direct_product_id;
                }
            }
        }
        
        // Now process regular form submissions (loop through slots)
        for($x = 1; $x <= 60; $x++) {
            $product_name = '';
            $product_id = null;
            $product_mrp = 0.00;
            $product_price = 0.00;
            $product_image = null;
            
            // Check if product name was submitted
            if(isset($_POST["pro_name$x"]) && !empty(trim($_POST["pro_name$x"]))) {
                $products_processed = true;
                $product_name_raw = trim($_POST["pro_name$x"]);
                $product_name_raw = function_exists('mb_substr') ? mb_substr($product_name_raw, 0, 30) : substr($product_name_raw, 0, 30);
                $product_name = mysqli_real_escape_string($connect, $product_name_raw);
                
                // Get MRP and price
                if(isset($_POST["pro_mrp$x"]) && !empty(trim($_POST["pro_mrp$x"]))) {
                    $product_mrp = floatval(preg_replace('/[^0-9.]/', '', $_POST["pro_mrp$x"]));
                }
                if(isset($_POST["pro_price$x"]) && !empty(trim($_POST["pro_price$x"]))) {
                    $product_price = floatval(preg_replace('/[^0-9.]/', '', $_POST["pro_price$x"]));
                }
                
                // Category for this slot (parse s_/c_ prefix: s_5=system id 5, c_123=custom id 123)
                $product_category = null;
                $category_source = 'system';
                if(isset($_POST["pro_category$x"]) && trim($_POST["pro_category$x"]) !== '') {
                    $cat_val = trim($_POST["pro_category$x"]);
                    if(preg_match('/^c_(\d+)$/', $cat_val, $m)) {
                        $product_category = intval($m[1]);
                        $category_source = 'custom';
                    } elseif(preg_match('/^s_(\d+)$/', $cat_val, $m)) {
                        $product_category = intval($m[1]);
                        $category_source = 'system';
                    } else {
                        $product_category = intval($cat_val);
                    }
                }
                
                // Get product description if provided (max 400 characters)
                $product_description = '';
                if(isset($_POST["pro_desc$x"])) {
                    $product_description = trim($_POST["pro_desc$x"]);
                    $product_description = mb_substr($product_description, 0, 400);
                    $product_description = mysqli_real_escape_string($connect, $product_description);
                }
                
                // Check if this is an update (product_id might be in hidden field)
                if(isset($_POST["product_id$x"]) && !empty($_POST["product_id$x"])) {
                    $product_id = intval($_POST["product_id$x"]);
                    // Skip if we already processed this product_id via direct product_id
                    if(in_array($product_id, $processed_product_ids)) {
                        continue;
                    }
                }
                
                // Process image only when valid file attached (image optional - skip if none)
                if(!empty($_POST["processed_product_image_data$x"])){
                    $binary_data = base64_decode($_POST["processed_product_image_data$x"]);
                    if(!empty($binary_data) && strlen($binary_data) > 0) {
                        $product_image = saveProductPricingImageToFilesystem($binary_data, $productPricingUploadDirAbs, $card_id, $product_name);
                    }
                } elseif(isset($_FILES["pro_img$x"]) && !empty($_FILES["pro_img$x"]['tmp_name']) && $_FILES["pro_img$x"]['error'] === UPLOAD_ERR_OK) {
                    if(function_exists('processImageUploadWithAutoCrop')) {
                        try {
                            $result = processImageUploadWithAutoCrop(
                                $_FILES["pro_img$x"], 
                                600, 250000, 200000, 300000,
                                ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
                                'jpeg', null
                            );
                            if($result['status']) {
                                $product_image = saveProductPricingImageToFilesystem($result['data'], $productPricingUploadDirAbs, $card_id, $product_name);
                                if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                                    @unlink($result['file_path']);
                                }
                            }
                        } catch(Exception $e) {
                            // Image optional: continue without image, do not block save
                        }
                    } else {
                        $filename = $_FILES["pro_img$x"]['name'];
                        $imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        $file_allow = array('png', 'jpeg', 'jpg', 'gif', 'webp');
                        if(in_array($imageFileType, $file_allow) && $_FILES["pro_img$x"]['size'] <= 250000) {
                            $source = $_FILES["pro_img$x"]['tmp_name'];
                            if(function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
                                $destination = $_FILES["pro_img$x"]['tmp_name'];
                                $compressimage = compressImage($source, $destination, 65);
                                $binary_data = file_get_contents($compressimage);
                                $product_image = saveProductPricingImageToFilesystem($binary_data, $productPricingUploadDirAbs, $card_id, $product_name);
                            } else {
                                $binary_data = file_get_contents($source);
                                $product_image = saveProductPricingImageToFilesystem($binary_data, $productPricingUploadDirAbs, $card_id, $product_name);
                            }
                        }
                    }
                }
                
                // Get next display_order
                $max_order_query = mysqli_query($connect, "SELECT MAX(display_order) as max_order FROM card_product_pricing WHERE card_id='$card_id' AND user_id=$user_id");
                $max_order_row = mysqli_fetch_array($max_order_query);
                $display_order = isset($max_order_row['max_order']) ? intval($max_order_row['max_order']) + 1 : $x;
                
                if($product_id && $product_id > 0) {
                    // Update existing product
                    $verify_query = mysqli_query($connect, "SELECT id FROM card_product_pricing WHERE id=$product_id AND card_id='$card_id' AND user_id=$user_id");
                    if(mysqli_num_rows($verify_query) == 0) {
                        $error_message .= '<div class="alert alert-danger">Product not found or access denied.</div>';
                        continue;
                    }
                    
                    $cat_source_esc = mysqli_real_escape_string($connect, $category_source);
                    if($product_image !== null) {
                        $product_image_escaped = mysqli_real_escape_string($connect, $product_image);
                        $product_category_value = ($product_category !== null && $product_category > 0) ? $product_category : 'NULL';
                        $update_query = "UPDATE card_product_pricing SET product_name='$product_name', product_category=$product_category_value, category_source='$cat_source_esc', product_description='$product_description', product_image='$product_image_escaped', mrp=$product_mrp, selling_price=$product_price WHERE id=$product_id AND card_id='$card_id' AND user_id=$user_id";
                    } else {
                        $product_category_value = ($product_category !== null && $product_category > 0) ? $product_category : 'NULL';
                        $update_query = "UPDATE card_product_pricing SET product_name='$product_name', product_category=$product_category_value, category_source='$cat_source_esc', product_description='$product_description', mrp=$product_mrp, selling_price=$product_price WHERE id=$product_id AND card_id='$card_id' AND user_id=$user_id";
                    }
                    $update_result = mysqli_query($connect, $update_query);
                    if(!$update_result) {
                        $error_message .= '<div class="alert alert-danger">Failed to update product. Error: ' . mysqli_error($connect) . '</div>';
                    }
                } else {
                    // Insert new product
                    if($user_id <= 0) {
                        $error_message .= '<div class="alert alert-danger">Invalid user ID. Please login again.</div>';
                        continue;
                    }
                    
                    $card_id_escaped = mysqli_real_escape_string($connect, $card_id);
                    $product_name_escaped = mysqli_real_escape_string($connect, $product_name);
                    $cat_source_esc = mysqli_real_escape_string($connect, $category_source);
                    $product_category_value = ($product_category !== null && $product_category > 0) ? $product_category : 'NULL';
                    if($product_image !== null) {
                        $product_image_escaped = mysqli_real_escape_string($connect, $product_image);
                        $insert_query = "INSERT INTO card_product_pricing (card_id, user_id, product_name, product_category, category_source, product_description, product_image, mrp, selling_price, display_order) VALUES ('$card_id_escaped', $user_id, '$product_name_escaped', $product_category_value, '$cat_source_esc', '$product_description', '$product_image_escaped', $product_mrp, $product_price, $display_order)";
                    } else {
                        $insert_query = "INSERT INTO card_product_pricing (card_id, user_id, product_name, product_category, category_source, product_description, mrp, selling_price, display_order) VALUES ('$card_id_escaped', $user_id, '$product_name_escaped', $product_category_value, '$cat_source_esc', '$product_description', $product_mrp, $product_price, $display_order)";
                    }
                    
                    $insert_result = mysqli_query($connect, $insert_query);
                    if(!$insert_result) {
                        $error_message .= '<div class="alert alert-danger">Failed to add product. Error: ' . mysqli_error($connect) . '</div>';
                    } else {
                        // Get the inserted product_id for AJAX response
                        $new_product_id = mysqli_insert_id($connect);
                        if($is_ajax && $new_product_id > 0) {
                            // Store product_id for response (only for single product insert via AJAX)
                            if(!isset($ajax_product_id)) {
                                $ajax_product_id = $new_product_id;
                            }
                        }
                    }
                }
            }
        }
        
        if(empty($error_message)) {
            if($is_ajax) {
                // Clear any output buffer and return JSON
                if(ob_get_level() > 0) {
                    ob_clean();
                }
                // Make sure no headers are sent
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                $response_data = ['success' => true, 'message' => 'Product saved successfully'];
                // Include product_id if we have it (for newly inserted products)
                if(isset($ajax_product_id) && $ajax_product_id > 0) {
                    $response_data['product_id'] = $ajax_product_id;
                }
                echo json_encode($response_data);
                if(ob_get_level() > 0) {
                    ob_end_flush();
                }
                exit;
            } else {
                // Regular form submission - save only (no redirect)
                $_SESSION['save_success'] = "Products Updated Successfully!";
                header('Location: products.php?card_number='.$_SESSION['card_id_inprocess']);
                exit;
            }
        } else {
            if($is_ajax) {
                if(ob_get_level() > 0) {
                    ob_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode(['success' => false, 'message' => $error_message]);
                if(ob_get_level() > 0) {
                    ob_end_flush();
                }
                exit;
            }
        }
    }
}

include '../includes/header.php';
// Include the common image upload/crop modal
require_once(__DIR__ . '/../../common/image_upload_crop_modal.php');
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
        <div class="main-top">
        <span class="heading">Products</span>
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
        <div class="card mb-4">
            <div class="card-body">
                <label class="heading">Products:</label>
                <p class="sub_title">You can add upto 60 products with pricing which you want to showcase on your Mini Website.</p>
                <p class="text-muted"><small>(Image Format: jpg, jpeg, png, gif, webp.)</small></p>
                <br>
                <div id="status_remove_img"></div>
                <button type="button" id="addProductBtn" class="btn btn-primary add_product" onclick="openProductModal()" <?php echo (count($products_data ?? []) >= 60) ? 'disabled' : ''; ?>><i class="fa fa-plus" aria-hidden="true"></i> <span>Add Product</span></button>

                <div class="Product-ServicesTable">
                    <table class="display table">
                        <thead class="bg-secondary">
                            <tr>
                                <th>Image Details</th>
                                <th>Product Category</th>
                                <th>Product Name</th>
                                <th>Product Description</th>
                                <th>MRP</th>
                                <th>Selling Price</th>
                                <th>Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $productCount = count($products_data);
                            if($productCount > 0):
                                foreach($products_data as $index => $prod): 
                                    $prod_id = intval($prod['id']);
                                    $prod_name_raw = !empty($prod['product_name']) ? $prod['product_name'] : '';
                                    $prod_name = $prod_name_raw !== '' ? htmlspecialchars($prod_name_raw) : 'No Name';
                                    
                                    // Get category name from ID - use category_source to avoid ID collision
                                    $prod_category_id = !empty($prod['product_category']) ? intval($prod['product_category']) : null;
                                    $prod_category = '';
                                    $prod_category_raw = '';
                                    $prod_category_source = !empty($prod['category_source']) ? trim($prod['category_source']) : 'system';
                                    $prod_user_id = !empty($prod['user_id']) ? intval($prod['user_id']) : $user_id;
                                    if($prod_category_id) {
                                        if($prod_category_source === 'custom') {
                                            $ucc_query = mysqli_query($connect, "SELECT category_name FROM user_custom_categories WHERE id = $prod_category_id AND user_id = $prod_user_id AND is_active = 1 LIMIT 1");
                                            if($ucc_query && mysqli_num_rows($ucc_query) > 0) {
                                                $ucc_row = mysqli_fetch_assoc($ucc_query);
                                                $prod_category_raw = $ucc_row['category_name'];
                                                $prod_category = htmlspecialchars($prod_category_raw);
                                            }
                                        } else {
                                            $cat_query = mysqli_query($connect, "SELECT category_name FROM product_categories WHERE id = $prod_category_id LIMIT 1");
                                            if($cat_query && mysqli_num_rows($cat_query) > 0) {
                                                $cat_row = mysqli_fetch_assoc($cat_query);
                                                $prod_category_raw = $cat_row['category_name'];
                                                $prod_category = htmlspecialchars($prod_category_raw);
                                            }
                                        }
                                    }
                                    
                                    $prod_description_raw = !empty($prod['product_description']) ? $prod['product_description'] : '';
                                    $prod_description = $prod_description_raw !== '' ? htmlspecialchars($prod_description_raw) : '';
                                    $prod_mrp = !empty($prod['mrp']) && $prod['mrp'] > 0 ? floatval($prod['mrp']) : 0;
                                    $prod_price = !empty($prod['selling_price']) && $prod['selling_price'] > 0 ? floatval($prod['selling_price']) : 0;
                            ?>
                                <tr data-product-id="<?php echo $prod_id; ?>" data-card-id="<?php echo $card_id;?>" data-product-name="<?php echo htmlspecialchars($prod_name_raw, ENT_QUOTES, 'UTF-8'); ?>" data-product-category="<?php echo intval($prod_category_id); ?>" data-product-category-source="<?php echo htmlspecialchars($prod_category_source, ENT_QUOTES, 'UTF-8'); ?>" data-product-mrp="<?php echo $prod_mrp; ?>" data-product-price="<?php echo $prod_price; ?>" data-product-desc="<?php echo htmlspecialchars($prod_description_raw, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td valign="middle">
                                        <?php if(!empty($prod['product_image'])): ?>
                                            <?php
                                            // Check if product_image is just a filename
                                            if(is_string($prod['product_image']) && strpos($prod['product_image'], '/') === false && strpos($prod['product_image'], '\\') === false && strpos($prod['product_image'], '.') !== false) {
                                                // It's just a filename - construct the full path
                                                $image_src = '../../assets/upload/websites/product-pricing/' . $prod['product_image'];
                                            } else {
                                                // It's binary data - convert to base64 (legacy support)
                                                $image_src = 'data:image/*;base64,' . base64_encode($prod['product_image']);
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($image_src); ?>" class="img-fluid" width="30px" alt="">
                                        <?php else: ?>
                                            <span class="text-muted">No Image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td valign="middle" class="product-table-cell-clip" title="<?php echo $prod_category_raw !== '' ? htmlspecialchars($prod_category_raw, ENT_QUOTES, 'UTF-8') : ''; ?>"><?php echo $prod_category ? $prod_category : '<span class="text-muted">-</span>'; ?></td>
                                    <td valign="middle" class="product-table-cell-clip" title="<?php echo $prod_name_raw !== '' ? htmlspecialchars($prod_name_raw, ENT_QUOTES, 'UTF-8') : ''; ?>"><?php echo $prod_name; ?></td>
                                    <td valign="middle" class="product-table-desc-cell" title="<?php echo $prod_description_raw !== '' ? htmlspecialchars($prod_description_raw, ENT_QUOTES, 'UTF-8') : ''; ?>"><?php echo $prod_description !== '' ? $prod_description : '<span class="text-muted">-</span>'; ?></td>
                                    <td valign="middle">
                                        <?php if($prod_mrp > 0): ?>
                                            <i class="fa fa-inr" aria-hidden="true"></i> <?php echo number_format($prod_mrp, 2); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td valign="middle">
                                        <?php if($prod_price > 0): ?>
                                            <i class="fa fa-inr" aria-hidden="true"></i> <?php echo number_format($prod_price, 2); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td valign="middle">
                                        <a class="edit" href="javascript:void(0);" onclick="editProductFromRow(this)" title="Edit"><i class="fa fa-edit" style="font-size:16px;color:#007bff;margin-right:8px;"></i></a>
                                        <a class="delet" href="javascript:void(0);" onclick="removeData(<?php echo $prod_id; ?>)" title="Delete"><i class="fa fa-trash" style="font-size:16px;color:#dc3545;"></i></a>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No products added yet. Click "Add Product" to add.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Hidden form for saving all products -->
                    <form id="productForm" action="" method="POST" enctype="multipart/form-data" style="display:none;">
                        <?php for($m = 1; $m <= 60; $m++): ?>
                            <input type="text" name="pro_name<?php echo $m; ?>" id="form_pro_name<?php echo $m; ?>" value="">
                            <input type="text" name="pro_category<?php echo $m; ?>" id="form_pro_category<?php echo $m; ?>" value="">
                            <input type="number" name="pro_mrp<?php echo $m; ?>" id="form_pro_mrp<?php echo $m; ?>" value="">
                            <input type="number" name="pro_price<?php echo $m; ?>" id="form_pro_price<?php echo $m; ?>" value="">
                            <input type="file" name="pro_img<?php echo $m; ?>" id="form_pro_img<?php echo $m; ?>">
                            <!-- Hidden field for processed image data -->
                            <input type="hidden" name="processed_product_image_data<?php echo $m; ?>" id="form_processed_image_data<?php echo $m; ?>" value="">
                            <!-- Hidden field for product_id (for updates) -->
                            <input type="hidden" name="product_id<?php echo $m; ?>" id="form_product_id<?php echo $m; ?>" value="">
                        <?php endfor; ?>
                    </form>

                   
                </div>
                <div class="Product-ServicesBtn" style="margin-top: 20px; width: 86%;">
                        <a href="services.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left">
                            <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                            <span>Back</span>
                        </a>
                        <button type="button" class="btn btn-primary align-center save_btn" onclick="saveProducts()">
                            <img src="../../assets/images/Save.png" class="img-fluid" width="35px" alt="">
                            <span>Save</span>
                        </button>
                        <a href="special-offers.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right">
                            <span>Next</span>
                            <span class="right_angle angle"><i class="fa fa-angle-right"></i></span>
                        </a>
                    </div>
            </div>
        </div>
    </div>
</main>

<!-- Product Modal -->
<div class="modal fade website-step-modal" id="productModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content website-step-modal-content">
            <button type="button" class="website-step-modal-close close" onclick="closeProductModal()" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <div class="modal-body">
                <form id="modalProductForm">
                    <input type="hidden" id="modal_product_id" value="">
                    <input type="hidden" id="modal_product_number" value="">
                    <div class="form-group">
                        <label>Image Details</label>
                        <div class="product-image-preview-modal" style="text-align: center; margin-bottom: 15px; min-height: 220px; display: flex; align-items: center; justify-content: center;">
                            <img id="modal_product_image_preview" 
                                 src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+" 
                                 alt="Product Image" 
                                 onclick="document.getElementById('modal_product_image').click()" 
                                 style="max-width: 200px; width: auto; max-height: 200px; height: auto; border: 2px dashed #ddd; border-radius: 8px; cursor: pointer; padding: 10px; object-fit: contain;">
                        </div>
                        <input type="file" id="modal_product_image" onchange="handleProductImageUpload(this);" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('modal_product_image').click()">Choose Image</button>
                    </div>
                    <div class="form-group">
                        <label for="modal_product_category">Product Category</label>
                        <small class="form-text text-muted d-block mb-1">Category and product name are each limited to 30 characters when saved.</small>
                        <div style="display: flex; gap: 10px;">
                            <select name="modal_product_category" id="modal_product_category" class="form-control" style="flex: 1;" onchange="loadProductNames(this.value)">
                                <option value="">Select Product Category</option>
                                <?php
                                // Bind product categories from BOTH selected business categories:
                                // primary + secondary (from company-details.php).
                                $selected_business_category_ids = [];
                                if(!empty($card_d_position_primary) && is_numeric($card_d_position_primary)) {
                                    $selected_business_category_ids[] = (int)$card_d_position_primary;
                                }
                                if(!empty($card_d_position_secondary) && is_numeric($card_d_position_secondary)) {
                                    $selected_business_category_ids[] = (int)$card_d_position_secondary;
                                }
                                $selected_business_category_ids = array_values(array_unique($selected_business_category_ids));

                                if(!empty($selected_business_category_ids)) {
                                    $parent_ids_sql = implode(',', $selected_business_category_ids);
                                    $child_cats_query = mysqli_query($connect, "
                                        SELECT id, category_name, display_order 
                                        FROM product_categories 
                                        WHERE parent_id IN ($parent_ids_sql)
                                        AND is_active = 1
                                        AND category_type = 'product-category'
                                        ORDER BY display_order ASC, category_name ASC
                                    ");
                                    
                                    while($cat = mysqli_fetch_assoc($child_cats_query)) {
                                        echo '<option value="s_' . intval($cat['id']) . '">' . htmlspecialchars($cat['category_name']) . '</option>';
                                    }
                                    
                                    // Get user ID for custom categories
                                    $user_email_escaped = mysqli_real_escape_string($connect, $_SESSION['user_email']);
                                    $user_query = mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = LOWER(TRIM('$user_email_escaped')) LIMIT 1");
                                    $user_id = 0;
                                    if($user_query && mysqli_num_rows($user_query) > 0) {
                                        $user_row = mysqli_fetch_assoc($user_query);
                                        $user_id = intval($user_row['id']);
                                    }
                                    
                                    // Get user custom product categories (use c_ prefix to avoid ID collision with product_categories)
                                    if($user_id > 0) {
                                        $custom_cats_query = mysqli_query($connect, "
                                            SELECT id, category_name FROM user_custom_categories
                                            WHERE user_id = $user_id AND category_type = 'product-category' AND is_active = 1
                                            ORDER BY created_at DESC
                                        ");
                                        
                                        if(mysqli_num_rows($custom_cats_query) > 0) {
                                            echo '<optgroup label="My Custom Categories">';
                                            while($custom_cat = mysqli_fetch_assoc($custom_cats_query)) {
                                                echo '<option value="c_' . intval($custom_cat['id']) . '">[Custom] ' . htmlspecialchars($custom_cat['category_name']) . '</option>';
                                            }
                                            echo '</optgroup>';
                                        }
                                    }
                                }
                                ?>
                            </select>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="openCustomProductCategoryModal()" style="min-width: 40px; padding: 0;" title="Add Custom Category">
                                <i class="fa fa-plus" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="modal_product_name">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_product_name" maxlength="30" required placeholder="Enter product name (max 30)">
                        <small class="form-text text-muted">Product name is required and limited to 30 characters.</small>
                    </div>
                    <div class="form-group">
                        <label for="modal_product_mrp">MRP</label>
                        <input type="number" class="form-control" id="modal_product_mrp" maxlength="200" max="500000" min="0" placeholder="Enter MRP">
                    </div>
                    <div class="form-group">
                        <label for="modal_product_price">Selling Price</label>
                        <input type="number" class="form-control" id="modal_product_price" maxlength="200" max="500000" min="0" placeholder="Enter Selling Price">
                    </div>
                    <div class="form-group">
                        <label for="modal_product_description">Product Description</label>
                        <textarea class="form-control" id="modal_product_description" maxlength="400" placeholder="Enter product description (max 400 characters)" rows="4" oninput="updateCharCount()" onpaste="updateCharCount()" onkeyup="updateCharCount()"></textarea>
                        <small class="form-text text-muted">
                            <strong id="char_counter_display">0/400</strong> characters
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeProductModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="productModalSubmitBtn" onclick="addProductToForm()">Add Product</button>
            </div>
        </div>
    </div>
</div>

<script>
var currentProductId = null;
var processedProductImageData = null;

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

// Product name is now a mandatory textbox (max 30 chars).
function loadProductNames() {}

var MAX_PRODUCTS = 60;
function updateAddProductButtonState() {
    var btn = document.getElementById('addProductBtn');
    if(!btn) return;
    var productCount = document.querySelectorAll('tr[data-product-id]').length;
    if(productCount >= MAX_PRODUCTS) {
        btn.disabled = true;
        btn.setAttribute('title', 'Maximum ' + MAX_PRODUCTS + ' products allowed. Delete one to add more.');
    } else {
        btn.disabled = false;
        btn.removeAttribute('title');
    }
}

function openProductModal() {
    var productCount = document.querySelectorAll('tr[data-product-id]').length;
    if(productCount >= MAX_PRODUCTS) {
        alert('You can add up to ' + MAX_PRODUCTS + ' products only. Please delete one before adding a new product.');
        return;
    }
    currentProductId = null;
    processedProductImageData = null;
    
    try {
        // Reset form fields (skip file input - clearing can throw in some browsers)
        var fieldIds = ['modal_product_id', 'modal_product_number', 'modal_product_category', 'modal_product_name', 'modal_product_mrp', 'modal_product_price', 'modal_product_description'];
        fieldIds.forEach(function(fieldId) {
            var field = document.getElementById(fieldId);
            if(field) field.value = '';
        });
        var fileInput = document.getElementById('modal_product_image');
        if(fileInput) {
            try { fileInput.value = ''; } catch(e) { /* file inputs may throw on reset */ }
        }

        // Reset image preview
        var imgPreview = document.getElementById('modal_product_image_preview');
        if(imgPreview) {
            imgPreview.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+';
        }

        var submitBtn = document.getElementById('productModalSubmitBtn');
        if(submitBtn) submitBtn.textContent = 'Add Product';

        if(typeof updateCharCount === 'function') updateCharCount();

        // Show modal
        var productModalEl = document.getElementById('productModal');
        if(!productModalEl) {
            console.error('Product modal element not found');
            return;
        }
        if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
            jQuery('#productModal').modal('show');
        } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(productModalEl, { backdrop: 'static', keyboard: false }).show();
        } else {
            productModalEl.style.display = 'block';
            productModalEl.classList.add('show');
            document.body.classList.add('modal-open');
            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.id = 'modalBackdrop';
            document.body.appendChild(backdrop);
        }
    } catch(e) {
        console.error('Error opening product modal:', e);
        alert('Could not open the form. Please refresh the page and try again.');
    }
}

function editProductFromRow(editLink) {
    var row = editLink.closest ? editLink.closest('tr') : editLink.parentElement;
    while (row && row.tagName !== 'TR') row = row.parentElement;
    if (!row) return;
    var productId = row.getAttribute('data-product-id') || row.dataset.productId;
    var productName = row.getAttribute('data-product-name') || row.dataset.productName || '';
    var productCategoryId = row.getAttribute('data-product-category') || row.dataset.productCategory || '';
    var productCategorySource = row.getAttribute('data-product-category-source') || row.dataset.productCategorySource || 'system';
    var mrp = row.getAttribute('data-product-mrp') || row.dataset.productMrp || '';
    var price = row.getAttribute('data-product-price') || row.dataset.productPrice || '';
    var productDescription = row.getAttribute('data-product-desc') || row.dataset.productDesc || '';
    editProduct(productId, productName, productCategoryId, productCategorySource, mrp, price, productDescription);
}

function editProduct(productId, productName, productCategoryId, productCategorySource, mrp, price, productDescription) {
    currentProductId = productId;
    processedProductImageData = null;
    
    // Set form values using vanilla JavaScript
    var modalProductIdField = document.getElementById('modal_product_id');
    var modalProductNumberField = document.getElementById('modal_product_number');
    var modalProductMrpField = document.getElementById('modal_product_mrp');
    var modalProductPriceField = document.getElementById('modal_product_price');
    var modalProductDescField = document.getElementById('modal_product_description');
    var modalProductCategoryField = document.getElementById('modal_product_category');
    var modalProductNameField = document.getElementById('modal_product_name');
    
    if(modalProductIdField) modalProductIdField.value = productId;
    if(modalProductNumberField) modalProductNumberField.value = productId;
    if(modalProductMrpField) modalProductMrpField.value = mrp || '';
    if(modalProductPriceField) modalProductPriceField.value = price || '';
    if(modalProductDescField) modalProductDescField.value = productDescription || '';
    
    if(modalProductNameField) modalProductNameField.value = productName || '';
    
    // Set category dropdown - use prefixed value (s_X or c_X) so correct option is selected
    var categoryValue = '';
    if(productCategoryId) {
        categoryValue = (productCategorySource === 'custom') ? ('c_' + productCategoryId) : ('s_' + productCategoryId);
    }
    if(modalProductCategoryField) {
        modalProductCategoryField.value = categoryValue;
    }
    
    // Get and set product image preview
    var row = document.querySelector('tr[data-product-id="' + productId + '"]');
    var imgPreview = document.getElementById('modal_product_image_preview');
    
    if(row && imgPreview) {
        var img = row.querySelector('img');
        if(img && img.src) {
            imgPreview.src = img.src;
        } else {
            imgPreview.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+';
        }
    }
    
    var submitBtn = document.getElementById('productModalSubmitBtn');
    if(submitBtn) {
        submitBtn.textContent = 'Update Product';
    }
    
    updateCharCount();
    
    // Show modal
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery('#productModal').modal('show');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modalElement = document.getElementById('productModal');
        bootstrap.Modal.getOrCreateInstance(modalElement, { backdrop: 'static', keyboard: false }).show();
    } else {
        document.getElementById('productModal').style.display = 'block';
        document.getElementById('productModal').classList.add('show');
        document.body.classList.add('modal-open');
    }
}

function handleProductImageUpload(input) {
    if(input.files && input.files[0]) {
        var file = input.files[0];
        var allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
        var maxSize = 10 * 1024 * 1024;
        if(allowedTypes.indexOf(file.type) === -1) {
            alert('Only JPG, PNG, GIF, and WEBP images are allowed.');
            input.value = '';
            processedProductImageData = null;
            return;
        }
        if(file.size > maxSize) {
            alert('Image size must be 10MB or less. The image will be automatically optimized to 250KB.');
            input.value = '';
            processedProductImageData = null;
            return;
        }
        if (typeof ImageCropUpload !== 'undefined') {
            window.productImageCropCallback = function(base64Data) {
                processedProductImageData = base64Data;
                var previewDataUri = 'data:image/jpeg;base64,' + base64Data;
                var imgPreview = document.getElementById('modal_product_image_preview');
                if(imgPreview) {
                    imgPreview.src = previewDataUri;
                    imgPreview.style.border = '2px solid #28a745';
                    imgPreview.style.borderRadius = '8px';
                    imgPreview.style.maxWidth = '200px';
                    imgPreview.style.width = 'auto';
                    imgPreview.style.maxHeight = '200px';
                    imgPreview.style.height = 'auto';
                    imgPreview.style.padding = '10px';
                }
            };
            ImageCropUpload.open(file, {
                method: 'base64',
                title: 'Adjust & Crop Product Image (1:1 square)',
                aspectRatio: 1,
                cropWidth: 600,
                cropHeight: 600,
                onSuccess: function(base64Data) {
                    if (window.productImageCropCallback) window.productImageCropCallback(base64Data);
                },
                onError: function(msg) {
                    alert(msg || 'Error processing image. Please try again.');
                    input.value = '';
                    processedProductImageData = null;
                }
            });
            input.value = '';
        } else {
            alert('Image crop tool not available. Please refresh the page.');
        }
    }
}

function addProductToForm() {
    var modalProductNameField = document.getElementById('modal_product_name');
    var productNameValue = modalProductNameField ? modalProductNameField.value.trim() : '';
    
    if(!productNameValue) {
        alert('Please enter product name.');
        return;
    }
    
    var productNameText = productNameValue;
    if(productNameText.length > 30) {
        productNameText = productNameText.substring(0, 30);
    }
    
    var modalProductIdField = document.getElementById('modal_product_id');
    var productId = modalProductIdField ? modalProductIdField.value : '';
    var isEdit = (productId && productId !== '');
    
    if(!isEdit) {
        var productCount = document.querySelectorAll('tr[data-product-id]').length;
        if(productCount >= MAX_PRODUCTS) {
            alert('You can add up to ' + MAX_PRODUCTS + ' products only. Please delete one before adding a new product.');
            return;
        }
    }
    
    var formData = new FormData();
    formData.append('product', '1');
    var tempSlot = '1';
    formData.append('pro_name' + tempSlot, productNameText);
    
    var modalProductCategoryField = document.getElementById('modal_product_category');
    var category = modalProductCategoryField ? modalProductCategoryField.value : '';
    if(category) formData.append('pro_category' + tempSlot, category);
    
    var modalProductMrpField = document.getElementById('modal_product_mrp');
    var mrp = modalProductMrpField ? modalProductMrpField.value : '';
    if(mrp) formData.append('pro_mrp' + tempSlot, mrp);
    
    var modalProductPriceField = document.getElementById('modal_product_price');
    var price = modalProductPriceField ? modalProductPriceField.value : '';
    if(price) formData.append('pro_price' + tempSlot, price);
    
    var modalProductDescField = document.getElementById('modal_product_description');
    var description = modalProductDescField ? modalProductDescField.value.trim() : '';
    if(description) {
        if(description.length > 400) description = description.substring(0, 400);
        formData.append('pro_desc' + tempSlot, description);
    }
    
    if(isEdit) {
        formData.append('product_id' + tempSlot, productId);
        formData.append('product_id', productId);
    }
    
    if(processedProductImageData) {
        formData.append('processed_product_image_data' + tempSlot, processedProductImageData);
    } else {
        var imageFileInput = document.getElementById('modal_product_image');
        if(imageFileInput && imageFileInput.files[0]) {
            formData.append('pro_img' + tempSlot, imageFileInput.files[0]);
        }
    }
    
    var statusElement = document.getElementById('status_remove_img');
    if(statusElement) {
        statusElement.innerHTML = '<div class="alert alert-info">Saving product...</div>';
    }
    
    var updateTableCallback = function() {
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(response => response.json())
        .then(data => {
            if(data && data.success) {
                var statusEl = document.getElementById('status_remove_img');
                if(statusEl) {
                    statusEl.innerHTML = '<div class="alert alert-success">' + (data.message || 'Product saved successfully!') + '</div>';
                }
                setTimeout(function(){ window.location.reload(); }, 800);
            } else {
                var statusEl = document.getElementById('status_remove_img');
                if(statusEl) {
                    statusEl.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Error saving product.') + '</div>';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            var statusEl = document.getElementById('status_remove_img');
            if(statusEl) {
                statusEl.innerHTML = '<div class="alert alert-danger">Error saving product. Please try again.</div>';
            }
        });
    };
    
    if(processedProductImageData) {
        updateTableCallback();
    } else {
        var imageFileInput = document.getElementById('modal_product_image');
        if(imageFileInput && imageFileInput.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) { updateTableCallback(); };
            reader.readAsDataURL(imageFileInput.files[0]);
        } else {
            updateTableCallback();
        }
    }
    closeProductModal();
}

function saveProducts() {
    var statusEl = document.getElementById('status_remove_img');
    if (statusEl) {
        statusEl.innerHTML = '<div class="alert alert-success">All products saved successfully!</div>';
        setTimeout(function() { statusEl.innerHTML = ''; }, 2000);
    }
}

function removeData(productId) {
    if(confirm('Are you sure you want to remove this product?')) {
        var statusElement = document.getElementById('status_remove_img');
        if(statusElement) {
            statusElement.style.color = 'blue';
        }
        
        fetch('../../admin/js_request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'product_id=' + encodeURIComponent(productId) + '&action=delete_product'
        })
        .then(response => response.text())
        .then(data => {
            var statusEl = document.getElementById('status_remove_img');
            if(statusEl) {
                statusEl.innerHTML = data;
            }
            
            if(data.includes('success')){
                var row = document.querySelector('tr[data-product-id="' + productId + '"]');
                if(row) {
                    row.remove();
                }
                
                var tableBody = document.querySelector('.Product-ServicesTable tbody');
                var hasProducts = tableBody ? tableBody.querySelector('tr[data-product-id]') : null;
                
                if(tableBody && !hasProducts) {
                    tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No products added yet. Click "Add Product" to add.</td></tr>';
                }
                
                updateAddProductButtonState();
                
                if(statusEl) {
                    statusEl.innerHTML = '<div class="alert alert-success">Product removed successfully!</div>';
                    setTimeout(function(){ statusEl.innerHTML = ''; }, 2000);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            var statusEl = document.getElementById('status_remove_img');
            if(statusEl) {
                statusEl.innerHTML = '<div class="alert alert-danger">Error deleting product. Please try again.</div>';
            }
        });
    }
}

function closeProductModal() {
    var productModalEl = document.getElementById('productModal');
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery('#productModal').modal('hide');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal && productModalEl) {
        var modal = bootstrap.Modal.getInstance(productModalEl);
        if(modal) modal.hide();
        else {
            productModalEl.classList.remove('show');
            productModalEl.style.display = 'none';
            productModalEl.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            document.body.style.paddingRight = '';
            document.querySelectorAll('.modal-backdrop').forEach(function(b) { b.remove(); });
        }
    } else if(productModalEl) {
        productModalEl.style.display = 'none';
        productModalEl.classList.remove('show');
        productModalEl.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        document.body.style.paddingRight = '';
        document.querySelectorAll('.modal-backdrop').forEach(function(b) { b.remove(); });
    }
    
    var modalProductIdField = document.getElementById('modal_product_id');
    var modalProductNumberField = document.getElementById('modal_product_number');
    
    if(modalProductIdField) modalProductIdField.value = '';
    if(modalProductNumberField) modalProductNumberField.value = '';
    
    processedProductImageData = null;
}

// ============= Custom Category Functions =============

function openCustomProductCategoryModal() {
    var nameField = document.getElementById('custom_product_category_name');
    if(nameField) nameField.value = '';
    
    var errorElement = document.getElementById('customProductCategoryError');
    var successElement = document.getElementById('customProductCategorySuccess');
    
    if(errorElement) errorElement.style.display = 'none';
    if(successElement) successElement.style.display = 'none';
    
    var modalElement = document.getElementById('customProductCategoryModal');
    
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery(modalElement).modal('show');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalElement, { backdrop: 'static', keyboard: false }).show();
    } else {
        modalElement.style.display = 'block';
        modalElement.classList.add('show');
        document.body.classList.add('modal-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        document.body.appendChild(backdrop);
    }
}

function closeCustomProductCategoryModal() {
    var modalElement = document.getElementById('customProductCategoryModal');
    
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery(modalElement).modal('hide');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modal = bootstrap.Modal.getInstance(modalElement);
        if(modal) modal.hide();
        else {
            modalElement.classList.remove('show');
            modalElement.style.display = 'none';
            modalElement.setAttribute('aria-hidden', 'true');
            var bds = document.querySelectorAll('.modal-backdrop');
            if (bds.length) bds[bds.length - 1].remove();
            if (!document.querySelector('.modal.show')) {
                document.body.classList.remove('modal-open');
                document.body.style.paddingRight = '';
            }
        }
    } else {
        modalElement.style.display = 'none';
        modalElement.classList.remove('show');
        var bds = document.querySelectorAll('.modal-backdrop');
        if (bds.length) bds[bds.length - 1].remove();
        if (!document.querySelector('.modal.show')) {
            document.body.classList.remove('modal-open');
        }
    }
}

/** Insert new custom category into product modal dropdown and select it (no page reload). */
function addCustomProductCategoryToSelect(categoryId, categoryName) {
    var sel = document.getElementById('modal_product_category');
    if (!sel || categoryId == null || categoryId === '') return;
    var val = 'c_' + String(parseInt(categoryId, 10));
    var displayLabel = '[Custom] ' + String(categoryName).replace(/^\[Custom\]\s*/i, '').trim();
    var i;
    for (i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === val) {
            sel.value = val;
            return;
        }
    }
    var optgroup = null;
    for (i = 0; i < sel.children.length; i++) {
        var node = sel.children[i];
        if (node.tagName === 'OPTGROUP' && node.label === 'My Custom Categories') {
            optgroup = node;
            break;
        }
    }
    if (!optgroup) {
        optgroup = document.createElement('optgroup');
        optgroup.label = 'My Custom Categories';
        sel.appendChild(optgroup);
    }
    var opt = document.createElement('option');
    opt.value = val;
    opt.textContent = displayLabel;
    optgroup.appendChild(opt);
    sel.value = val;
}

function saveCustomProductCategory() {
    var categoryName = document.getElementById('custom_product_category_name').value.trim();
    var errorElement = document.getElementById('customProductCategoryError');
    var successElement = document.getElementById('customProductCategorySuccess');
    
    if (!categoryName) {
        errorElement.textContent = 'Category name is required.';
        errorElement.style.display = 'block';
        return;
    }
    if (categoryName.length > 30) {
        errorElement.textContent = 'Category name must be 30 characters or less.';
        errorElement.style.display = 'block';
        return;
    }
    
    errorElement.style.display = 'none';
    successElement.style.display = 'none';
    successElement.textContent = 'Creating category...';
    successElement.style.display = 'block';
    
    var formData = new FormData();
    formData.append('category_name', categoryName);
    formData.append('category_type', 'product-category');
    
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
            successElement.textContent = 'Category added.';
            successElement.style.display = 'block';
            errorElement.style.display = 'none';
            addCustomProductCategoryToSelect(data.category_id, data.category_name || categoryName);
            var nameInput = document.getElementById('custom_product_category_name');
            if (nameInput) nameInput.value = '';
            setTimeout(function() {
                closeCustomProductCategoryModal();
                successElement.style.display = 'none';
            }, 500);
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
<style>
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
    .Product-ServicesBtn .btn{
        line-height:24px !important;
    }
    .Product-ServicesBtn button {
        padding: 7px !important;
        margin-top: 22px !important;
    }
    @media screen and (max-width: 768px) {
        .card-body {
            padding-bottom: 100px !important;
        }
        .Product-ServicesBtn{
            width: 80% !important;
            padding:0px !important;
            margin-top: 40px !important;
        }
        .save_btn{
            position: absolute;
            bottom: 150px;
            width: 145px !important;
            left: 96px;
            height: 36px;
        }
        .Copyright-left,
        .Copyright-right{
            padding:0px;
        }
    }
    .save_btn{
        width: 115px !important;
    }
    #imageCropModal{
        z-index: 10000 !important;
    }

    select.form-control {
        appearance: none !important;
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 10px center !important;
        background-size: 20px !important;
        padding-right: 40px !important;
        cursor: pointer;
        background-color: white !important;
        border: 1px solid #ced4da !important;
    }

    select.form-control:disabled {
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ccc' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") !important;
        background-color: #e9ecef !important;
    }

    #modal_product_category {
        appearance: none !important;
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 10px center !important;
        background-size: 20px !important;
        padding-right: 40px !important;
        background-color: white !important;
        border: 1px solid #ced4da !important;
        cursor: pointer !important;
    }

    #modal_product_category:disabled {
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ccc' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") !important;
        background-color: #e9ecef !important;
        color: #6c757d !important;
        cursor: not-allowed !important;
    }

    .add_product:disabled,
    .add_product[disabled] {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Single-line cells: long text does not stretch row layout */
    .Product-ServicesTable .display.table td.product-table-cell-clip,
    .Product-ServicesTable .display.table td.product-table-desc-cell {
        max-width: 12rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle !important;
    }
    .Product-ServicesTable .display.table td.product-table-desc-cell {
        max-width: 16rem;
    }
</style>

<!-- Custom Product Category Modal -->
<div class="modal fade website-step-modal" id="customProductCategoryModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog" role="document">
        <div class="modal-content website-step-modal-content">
            <button type="button" class="website-step-modal-close close" onclick="closeCustomProductCategoryModal()" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <div class="modal-body">
                <form id="customProductCategoryForm">
                    <div class="form-group">
                        <label for="custom_product_category_name">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="custom_product_category_name" placeholder="Enter category name (max 30)" maxlength="30" required>
                        <small class="form-text text-muted">Max 30 characters. This category will only be visible to you.</small>
                    </div>
                    <div id="customProductCategoryError" class="alert alert-danger" style="display: none;"></div>
                    <div id="customProductCategorySuccess" class="alert alert-success" style="display: none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCustomProductCategoryModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveCustomProductCategory()">Add Category</button>
            </div>
        </div>
    </div>
</div>


<?php include '../includes/footer.php'; ?>

