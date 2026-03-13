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
        
        // Store d_position_primary in a separate variable for use in modals
        $card_d_position_primary = isset($row['d_position_primary']) ? $row['d_position_primary'] : '';
        
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
                $product_name = mysqli_real_escape_string($connect, trim($_POST["pro_name$slot_found"]));
                $product_category = '';
                if(isset($_POST["pro_category$slot_found"]) && !empty(trim($_POST["pro_category$slot_found"]))) {
                    $product_category = intval($_POST["product_category$slot_found"]);
                }
                
                // Get MRP and price
                if(isset($_POST["pro_mrp$slot_found"]) && !empty(trim($_POST["pro_mrp$slot_found"]))) {
                    $product_mrp = floatval(preg_replace('/[^0-9.]/', '', $_POST["pro_mrp$slot_found"]));
                }
                if(isset($_POST["pro_price$slot_found"]) && !empty(trim($_POST["pro_price$slot_found"]))) {
                    $product_price = floatval(preg_replace('/[^0-9.]/', '', $_POST["pro_price$slot_found"]));
                }
                
                // Get product description if provided
                $product_description = '';
                if(isset($_POST["pro_desc$slot_found"])) {
                    $product_description = mysqli_real_escape_string($connect, trim($_POST["pro_desc$slot_found"]));
                    // Enforce max character limit (500 characters)
                    if(strlen($product_description) > 500) {
                        $product_description = substr($product_description, 0, 500);
                    }
                }
                
                // Process image upload if provided
                if(!empty($_POST["processed_product_image_data$slot_found"])){
                    $binary_data = base64_decode($_POST["processed_product_image_data$slot_found"]);
                    // Save to filesystem and get the filename
                    $product_image = saveProductPricingImageToFilesystem($binary_data, $productPricingUploadDirAbs, $card_id, $product_name);
                } elseif(!empty($_FILES["pro_img$slot_found"]['tmp_name'])) {
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
                            $error_message .= '<div class="alert alert-danger">Error processing image: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    }
                }
                
                // Update existing product
                $verify_query = mysqli_query($connect, "SELECT id FROM card_product_pricing WHERE id=$direct_product_id AND card_id='$card_id' AND user_id=$user_id");
                if(mysqli_num_rows($verify_query) > 0) {
                    if($product_image !== null) {
                        $product_image_escaped = mysqli_real_escape_string($connect, $product_image);
                        $product_category_value = (!empty($product_category)) ? $product_category : 'NULL';
                        $update_query = "UPDATE card_product_pricing SET product_name='$product_name', product_category=$product_category_value, product_description='$product_description', product_image='$product_image_escaped', mrp=$product_mrp, selling_price=$product_price WHERE id=$direct_product_id AND card_id='$card_id' AND user_id=$user_id";
                    } else {
                        $product_category_value = (!empty($product_category)) ? $product_category : 'NULL';
                        $update_query = "UPDATE card_product_pricing SET product_name='$product_name', product_category=$product_category_value, product_description='$product_description', mrp=$product_mrp, selling_price=$product_price WHERE id=$direct_product_id AND card_id='$card_id' AND user_id=$user_id";
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
                $product_name = mysqli_real_escape_string($connect, trim($_POST["pro_name$x"]));
                
                // Get MRP and price
                if(isset($_POST["pro_mrp$x"]) && !empty(trim($_POST["pro_mrp$x"]))) {
                    $product_mrp = floatval(preg_replace('/[^0-9.]/', '', $_POST["pro_mrp$x"]));
                }
                if(isset($_POST["pro_price$x"]) && !empty(trim($_POST["pro_price$x"]))) {
                    $product_price = floatval(preg_replace('/[^0-9.]/', '', $_POST["pro_price$x"]));
                }
                
                // Category for this slot (now storing as ID)
                $product_category = '';
                if(isset($_POST["pro_category$x"]) && !empty(trim($_POST["pro_category$x"]))) {
                    $product_category = intval($_POST["pro_category$x"]);
                }
                
                // Get product description if provided
                $product_description = '';
                if(isset($_POST["pro_desc$x"])) {
                    $product_description = mysqli_real_escape_string($connect, trim($_POST["pro_desc$x"]));
                    // Enforce max character limit (500 characters)
                    if(strlen($product_description) > 500) {
                        $product_description = substr($product_description, 0, 500);
                    }
                }
                
                // Check if this is an update (product_id might be in hidden field)
                if(isset($_POST["product_id$x"]) && !empty($_POST["product_id$x"])) {
                    $product_id = intval($_POST["product_id$x"]);
                    // Skip if we already processed this product_id via direct product_id
                    if(in_array($product_id, $processed_product_ids)) {
                        continue;
                    }
                }
                
                // Process image upload if provided
                // Check if we have processed image data from AJAX (base64)
                if(!empty($_POST["processed_product_image_data$x"])){
                    $binary_data = base64_decode($_POST["processed_product_image_data$x"]);
                    $product_image = saveProductPricingImageToFilesystem($binary_data, $productPricingUploadDirAbs, $card_id, $product_name);
                } elseif(!empty($_FILES["pro_img$x"]['tmp_name'])) {
                    // Use the new automatic crop and resize function
                    if(function_exists('processImageUploadWithAutoCrop')) {
                        try {
                            $result = processImageUploadWithAutoCrop(
                                $_FILES["pro_img$x"], 
                                600,      // Target size: 600x600
                                250000,   // Target file size: 250KB
                                200000,   // Min file size: 200KB
                                300000,   // Max file size: 300KB
                                ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
                                'jpeg',
                                null
                            );
                            
                            if($result['status']) {
                                $product_image = saveProductPricingImageToFilesystem($result['data'], $productPricingUploadDirAbs, $card_id, $product_name);
                                // Clean up temp file
                                if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                                    @unlink($result['file_path']);
                                }
                            } else {
                                $error_message .= isset($result['message']) ? $result['message'] : '<div class="alert alert-danger">Error processing image for product '.$x.'.</div>';
                            }
                        } catch(Exception $e) {
                            $error_message .= '<div class="alert alert-danger">Error processing image for product '.$x.': ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    } else {
                        // Fallback compression
                        $filename = $_FILES["pro_img$x"]['name'];
                        $imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        $file_allow = array('png', 'jpeg', 'jpg', 'gif', 'webp');
                        
                        if(in_array($imageFileType, $file_allow)) {
                            if($_FILES["pro_img$x"]['size'] <= 250000) {
                                $source = $_FILES["pro_img$x"]['tmp_name'];
                                if(function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
                                    $destination = $_FILES["pro_img$x"]['tmp_name'];
                                    $quality = 65;
                                    $compressimage = compressImage($source, $destination, $quality);
                                    $binary_data = file_get_contents($compressimage);
                                    $product_image = saveProductPricingImageToFilesystem($binary_data, $productPricingUploadDirAbs, $card_id, $product_name);
                                } else {
                                    $binary_data = file_get_contents($source);
                                    $product_image = saveProductPricingImageToFilesystem($binary_data, $productPricingUploadDirAbs, $card_id, $product_name);
                                }
                            } else {
                                $error_message .= '<div class="alert alert-danger">File size for Product Image '.$x.' exceeds 250KB limit.</div>';
                            }
                        } else {
                            $error_message .= '<div class="alert alert-danger">Only PNG, JPG, JPEG, GIF, WEBP files allowed for Product Image '.$x.'</div>';
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
                    
                    if($product_image !== null) {
                        $product_image_escaped = mysqli_real_escape_string($connect, $product_image);
                        $product_category_value = (!empty($product_category)) ? $product_category : 'NULL';
                        $update_query = "UPDATE card_product_pricing SET product_name='$product_name', product_category=$product_category_value, product_description='$product_description', product_image='$product_image_escaped', mrp=$product_mrp, selling_price=$product_price WHERE id=$product_id AND card_id='$card_id' AND user_id=$user_id";
                    } else {
                        $product_category_value = (!empty($product_category)) ? $product_category : 'NULL';
                        $update_query = "UPDATE card_product_pricing SET product_name='$product_name', product_category=$product_category_value, product_description='$product_description', mrp=$product_mrp, selling_price=$product_price WHERE id=$product_id AND card_id='$card_id' AND user_id=$user_id";
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
                    
                    $product_category_value = (!empty($product_category)) ? $product_category : 'NULL';
                    if($product_image !== null) {
                        $product_image_escaped = mysqli_real_escape_string($connect, $product_image);
                        $insert_query = "INSERT INTO card_product_pricing (card_id, user_id, product_name, product_category, product_description, product_image, mrp, selling_price, display_order) VALUES ('$card_id_escaped', $user_id, '$product_name_escaped', $product_category_value, '$product_description', '$product_image_escaped', $product_mrp, $product_price, $display_order)";
                    } else {
                        $insert_query = "INSERT INTO card_product_pricing (card_id, user_id, product_name, product_category, product_description, mrp, selling_price, display_order) VALUES ('$card_id_escaped', $user_id, '$product_name_escaped', $product_category_value, '$product_description', $product_mrp, $product_price, $display_order)";
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
                <button class="btn btn-primary add_product" onclick="openProductModal()"><i class="fa fa-plus" aria-hidden="true"></i> <span>Add Product</span></button>

                <div class="Product-ServicesTable">
                    <table class="display table">
                        <thead class="bg-secondary">
                            <tr>
                                <th>Product Category</th>
                                <th>Product Name</th>
                                <th>Product Description</th>
                                <th>Image Details</th>
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
                                    $prod_name = !empty($prod['product_name']) ? htmlspecialchars($prod['product_name']) : 'No Name';
                                    
                                    // Get category name from ID
                                    $prod_category_id = !empty($prod['product_category']) ? intval($prod['product_category']) : null;
                                    $prod_category = '';
                                    if($prod_category_id) {
                                        $cat_query = mysqli_query($connect, "SELECT category_name FROM product_categories WHERE id = $prod_category_id LIMIT 1");
                                        if($cat_query && mysqli_num_rows($cat_query) > 0) {
                                            $cat_row = mysqli_fetch_assoc($cat_query);
                                            $prod_category = htmlspecialchars($cat_row['category_name']);
                                        }
                                    }
                                    
                                    $prod_description = !empty($prod['product_description']) ? htmlspecialchars($prod['product_description']) : '';
                                    $prod_mrp = !empty($prod['mrp']) && $prod['mrp'] > 0 ? floatval($prod['mrp']) : 0;
                                    $prod_price = !empty($prod['selling_price']) && $prod['selling_price'] > 0 ? floatval($prod['selling_price']) : 0;
                            ?>
                                <tr data-product-id="<?php echo $prod_id; ?>" data-card-id="<?php echo $card_id;?>">
                                    
                                    <td valign="middle"><?php echo $prod_category ? $prod_category : '<span class="text-muted">-</span>'; ?></td>
                                    <td valign="middle"><?php echo $prod_name; ?></td>
                                    <td valign="middle"><?php echo !empty($prod_description) ? $prod_description : '<span class="text-muted">-</span>'; ?></td>
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
                                            <img src="<?php echo htmlspecialchars($image_src); ?>" class="img-fluid" width="100px" alt="">
                                        <?php else: ?>
                                            <span class="text-muted">No Image</span>
                                        <?php endif; ?>
                                    </td>
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
                                        <a class="edit" href="javascript:void(0);" onclick="editProduct(<?php echo $prod_id; ?>, '<?php echo htmlspecialchars($prod_name, ENT_QUOTES); ?>', '<?php echo intval($prod_category_id); ?>', '<?php echo $prod_mrp; ?>', '<?php echo $prod_price; ?>', '<?php echo htmlspecialchars($prod_description, ENT_QUOTES); ?>')"><img src="../../assets/images/edit1.png" alt=""></a>
                                        <a class="delet" href="javascript:void(0);" onclick="removeData(<?php echo $prod_id; ?>)"><img src="../../assets/images/delet.png" alt=""></a>
                                            
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No products added yet. Click "Add Product" to add.</td>
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
                <div class="Product-ServicesBtn">
                        <a href="services.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left">
                            <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                            <span>Back</span>
                        </a>
                        <button class="btn btn-primary align-center save_btn" onclick="saveProducts()"><img src="../../assets/images/Save.png" class="img-fluid" width="35px" alt=""> <span>Save</span></button>
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
<div class="modal fade" id="productModal" tabindex="-1" role="dialog" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalLabel">Add/Edit Product/Service</h5>
                <button type="button" class="close" onclick="closeProductModal()" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="modalProductForm">
                    <input type="hidden" id="modal_product_id" value="">
                    <input type="hidden" id="modal_product_number" value="">
                   
                    <div class="form-group">
                        <label for="modal_product_category">Product Category</label>
                        <div style="display: flex; gap: 10px;">
                            <select name="modal_product_category" id="modal_product_category" class="form-control" style="flex: 1;">
                                <option value="">Select Product Category</option>
                                <?php
                                // Fetch child categories based on d_position_primary from digi_card
                                if(!empty($card_d_position_primary)) {
                                    // d_position_primary now stores the category ID directly
                                   
                                    $parent_query = mysqli_query($connect, "
                                        SELECT id, category_name FROM product_categories 
                                        WHERE parent_id = $card_d_position_primary
                                    ");
                                     
                                    if(!$parent_query) {
                                        echo '<script>alert("SQL Error: " + "' . mysqli_error($connect) . '");</script>';
                                    } else if(mysqli_num_rows($parent_query) > 0) {
                                        $parent_row = mysqli_fetch_assoc($parent_query);
                                       
                                    }
                                }
                                
                                // Fetch product categories from the selected business category
                                if($card_d_position_primary !== null) {
                                    $child_cats_query = mysqli_query($connect, "
                                        SELECT id, category_name, display_order 
                                        FROM product_categories 
                                        WHERE parent_id = $card_d_position_primary 
                                        AND is_active = 1
                                        AND category_type = 'product-category'
                                        ORDER BY display_order ASC
                                    ");
                                    
                                    while($cat = mysqli_fetch_assoc($child_cats_query)) {
                                        echo '<option value="' . intval($cat['id']) . '">' . htmlspecialchars($cat['category_name']) . '</option>';
                                    }
                                    
                                    // Get user ID for custom categories
                                    $user_email_escaped = mysqli_real_escape_string($connect, $_SESSION['user_email']);
                                    $user_query = mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = LOWER(TRIM('$user_email_escaped')) LIMIT 1");
                                    $user_id = 0;
                                    if($user_query && mysqli_num_rows($user_query) > 0) {
                                        $user_row = mysqli_fetch_assoc($user_query);
                                        $user_id = intval($user_row['id']);
                                    }
                                    
                                    // Get user custom product categories
                                    if($user_id > 0) {
                                        $custom_cats_query = mysqli_query($connect, "
                                            SELECT id, category_name FROM user_custom_categories
                                            WHERE user_id = $user_id AND category_type = 'product-category' AND is_active = 1
                                            ORDER BY created_at DESC
                                        ");
                                        
                                        if(mysqli_num_rows($custom_cats_query) > 0) {
                                            echo '<optgroup label="My Custom Categories">';
                                            while($custom_cat = mysqli_fetch_assoc($custom_cats_query)) {
                                                echo '<option value="' . intval($custom_cat['id']) . '">[Custom] ' . htmlspecialchars($custom_cat['category_name']) . '</option>';
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
                        <div style="display: flex; gap: 10px;">
                            <select class="form-control" id="modal_product_name" required style="flex: 1;">
                                <option value="">-- Select Product Name --</option>
                            </select>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="openCustomProductNameModal()" style="min-width: 40px; padding: 0;" title="Add Custom Product Name">
                                <i class="fa fa-plus" aria-hidden="true"></i>
                            </button>
                        </div>
                        <small class="form-text text-muted">Product names are populated based on selected product category</small>
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
                        <textarea class="form-control" id="modal_product_description" maxlength="500" placeholder="Enter product description (max 500 characters)" rows="4"></textarea>
                        <small class="form-text text-muted">
                            <strong id="char_counter_display">0/500</strong> characters
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Product Image</label>
                        <div class="product-image-preview-modal" style="text-align: center; margin-bottom: 15px; min-height: 220px; display: flex; align-items: center; justify-content: center;">
                            <img id="modal_product_image_preview" 
                                 src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+" 
                                 alt="Product Image" 
                                 onclick="document.getElementById('modal_product_image').click()" 
                                 style="max-width: 200px; width: auto; max-height: 200px; height: auto; border: 2px dashed #ddd; border-radius: 8px; cursor: pointer; padding: 10px; object-fit: contain;">
                        </div>
                        <input type="file" id="modal_product_image" onchange="handleProductImageUpload(this);" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('modal_product_image').click()">Choose Image</button>
                        <small class="form-text text-muted">File Supported - .png, .jpg, .jpeg, .gif, .webp</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeProductModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addProductToForm()">Add Product</button>
            </div>
        </div>
    </div>
</div>

<script>
var currentProductId = null;
var processedProductImageData = null;

// Update character count display
function updateCharCount() {
    var textarea = document.getElementById('modal_product_description');
    var counter = document.getElementById('char_counter_display');
    
    if(textarea && counter) {
        var length = textarea.value.length;
        counter.textContent = length + '/500';
    }
}

// Add native event listeners for character count
document.addEventListener('DOMContentLoaded', function() {
    var textarea = document.getElementById('modal_product_description');
    if(textarea) {
        textarea.addEventListener('input', updateCharCount);
        textarea.addEventListener('keyup', updateCharCount);
        textarea.addEventListener('change', updateCharCount);
    }
});

// Load product names when category is selected
document.addEventListener('DOMContentLoaded', function() {
    var categorySelect = document.getElementById('modal_product_category');
    if(categorySelect) {
        categorySelect.addEventListener('change', function() {
            loadProductNames(this.value);
        });
    }
});

function loadProductNames(categoryId) {
    var productSelect = document.getElementById('modal_product_name');
    
    if(!categoryId) {
        // Clear product names if no category selected
        productSelect.innerHTML = '<option value="">-- Select Product Name --</option>';
        productSelect.disabled = true;
        return;
    }
    
    // Disable the select and show loading state
    productSelect.disabled = true;
    productSelect.innerHTML = '<option value="">Loading products...</option>';
    
    // Fetch product names via AJAX
    fetch('?action=get_product_names&category_id=' + encodeURIComponent(categoryId), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        productSelect.innerHTML = '<option value="">-- Select Product Name --</option>';
        
        if(data.success && data.products.length > 0) {
            data.products.forEach(product => {
                var option = document.createElement('option');
                option.value = product.id;
                option.textContent = product.name;
                productSelect.appendChild(option);
            });
        }
        
        // Load custom product names
        fetch('../../user/ajax/custom_categories.php?action=get_custom&type=product-name', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(customData => {
            if(customData.success && customData.categories.length > 0) {
                var optgroup = document.createElement('optgroup');
                optgroup.label = 'My Custom Product Names';
                
                customData.categories.forEach(category => {
                    var option = document.createElement('option');
                    option.value = category.id;
                    option.textContent = '[Custom] ' + category.name;
                    optgroup.appendChild(option);
                });
                
                productSelect.appendChild(optgroup);
            }
            
            // Check if we have any products
            if((data.success && data.products.length > 0) || (customData.success && customData.categories.length > 0)) {
                productSelect.disabled = false;
            } else {
                var option = document.createElement('option');
                option.disabled = true;
                option.textContent = 'No products available for this category';
                productSelect.appendChild(option);
                productSelect.disabled = true;
            }
        })
        .catch(error => {
            console.error('Error loading custom products:', error);
            // Still enable if we have system products
            if(data.success && data.products.length > 0) {
                productSelect.disabled = false;
            } else {
                productSelect.disabled = true;
            }
        });
    })
    .catch(error => {
        console.error('Error loading products:', error);
        productSelect.innerHTML = '<option value="">-- Select Product Name --</option>';
        var option = document.createElement('option');
        option.disabled = true;
        option.textContent = 'Error loading products';
        productSelect.appendChild(option);
        productSelect.disabled = true;
    });
}

function openProductModal() {
    currentProductId = null;
    processedProductImageData = null;
    
    // Reset form fields using vanilla JavaScript
    var fields = [
        'modal_product_id',
        'modal_product_number',
        'modal_product_name',
        'modal_product_category',
        'modal_product_mrp',
        'modal_product_price',
        'modal_product_description',
        'modal_product_image'
    ];
    
    fields.forEach(function(fieldId) {
        var field = document.getElementById(fieldId);
        if(field) {
            field.value = '';
        }
    });
    
    // Reset image preview
    var imgPreview = document.getElementById('modal_product_image_preview');
    if(imgPreview) {
        imgPreview.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+';
    }
    
    // Update modal title and button text
    var modalLabel = document.getElementById('productModalLabel');
    if(modalLabel) {
        modalLabel.textContent = 'Add Product';
    }
    
    var submitBtn = document.querySelector('.modal-footer button:last-child');
    if(submitBtn) {
        submitBtn.textContent = 'Add Product';
    }
    
    updateCharCount();
    
    // Show modal
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery('#productModal').modal('show');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modalElement = document.getElementById('productModal');
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        document.getElementById('productModal').style.display = 'block';
        document.getElementById('productModal').classList.add('show');
        document.body.classList.add('modal-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'modalBackdrop';
        document.body.appendChild(backdrop);
    }
}

function editProduct(productId, productName, productCategoryId, mrp, price, productDescription) {
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
    
    // Clear the product name dropdown first
    if(modalProductNameField) {
        modalProductNameField.innerHTML = '<option value="">-- Select Product Name --</option>';
        modalProductNameField.disabled = true;
    }
    
    // Set category and load product names
    if(modalProductCategoryField) {
        modalProductCategoryField.value = productCategoryId || '';
    }
    
    if(productCategoryId) {
        loadProductNames(productCategoryId);
        // Set the product name after options have loaded (300ms to be safe)
        setTimeout(function() {
            if(modalProductNameField) {
                modalProductNameField.value = productName || '';
            }
        }, 300);
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
    
    // Update modal title and button text
    var modalLabel = document.getElementById('productModalLabel');
    if(modalLabel) {
        modalLabel.textContent = 'Edit Product/Service';
    }
    
    var submitBtn = document.querySelector('.modal-footer button:last-child');
    if(submitBtn) {
        submitBtn.textContent = 'Update Product';
    }
    
    updateCharCount();
    
    // Show modal
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery('#productModal').modal('show');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modalElement = document.getElementById('productModal');
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
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
                title: 'Adjust & Crop Product Image',
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
    var productNameValue = modalProductNameField ? modalProductNameField.value : '';
    
    if(!productNameValue) {
        alert('Please enter product name.');
        return;
    }
    
    // Get the product name text (not just the ID)
    var productNameText = productNameValue;
    if(modalProductNameField && modalProductNameField.selectedIndex > -1) {
        var selectedOption = modalProductNameField.options[modalProductNameField.selectedIndex];
        if(selectedOption) {
            productNameText = selectedOption.text;
            // Remove [Custom] prefix if present for storage
            productNameText = productNameText.replace('[Custom] ', '').trim();
        }
    }
    
    var modalProductIdField = document.getElementById('modal_product_id');
    var productId = modalProductIdField ? modalProductIdField.value : '';
    var isEdit = (productId && productId !== '');
    
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
    var description = modalProductDescField ? modalProductDescField.value : '';
    if(description) formData.append('pro_desc' + tempSlot, description);
    
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
    $('#status_remove_img').html('<div class="alert alert-success">All products saved successfully!</div>');
    setTimeout(function(){ $('#status_remove_img').html(''); }, 2000);
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
                    tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No products added yet. Click "Add Product" to add.</td></tr>';
                }
                
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
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery('#productModal').modal('hide');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modalElement = document.getElementById('productModal');
        var modal = bootstrap.Modal.getInstance(modalElement);
        if(modal) modal.hide();
    } else {
        document.getElementById('productModal').style.display = 'none';
        document.getElementById('productModal').classList.remove('show');
        document.body.classList.remove('modal-open');
        var backdrop = document.getElementById('modalBackdrop');
        if(backdrop) backdrop.remove();
    }
    
    var modalProductIdField = document.getElementById('modal_product_id');
    var modalProductNumberField = document.getElementById('modal_product_number');
    
    if(modalProductIdField) modalProductIdField.value = '';
    if(modalProductNumberField) modalProductNumberField.value = '';
    
    processedProductImageData = null;
}

document.addEventListener('click', function(event) {
    var modal = document.getElementById('productModal');
    if(event.target === modal) closeProductModal();
});

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
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
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

function closeCustomProductCategoryModal() {
    var modalElement = document.getElementById('customProductCategoryModal');
    
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery(modalElement).modal('hide');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modal = bootstrap.Modal.getInstance(modalElement);
        if(modal) modal.hide();
    } else {
        // Fallback: hide modal manually
        modalElement.style.display = 'none';
        modalElement.classList.remove('show');
        document.body.classList.remove('modal-open');
        var backdrop = document.querySelector('.modal-backdrop');
        if(backdrop) backdrop.remove();
    }
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
            successElement.textContent = 'Category created! Reloading...';
            successElement.style.display = 'block';
            errorElement.style.display = 'none';
            
            setTimeout(function() {
                closeCustomProductCategoryModal();
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

function openCustomProductNameModal() {
    var nameField = document.getElementById('custom_product_name');
    if(nameField) nameField.value = '';
    
    var errorElement = document.getElementById('customProductNameError');
    var successElement = document.getElementById('customProductNameSuccess');
    
    if(errorElement) errorElement.style.display = 'none';
    if(successElement) successElement.style.display = 'none';
    
    var modalElement = document.getElementById('customProductNameModal');
    
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery(modalElement).modal('show');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
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

function closeCustomProductNameModal() {
    var modalElement = document.getElementById('customProductNameModal');
    
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery(modalElement).modal('hide');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modal = bootstrap.Modal.getInstance(modalElement);
        if(modal) modal.hide();
    } else {
        // Fallback: hide modal manually
        modalElement.style.display = 'none';
        modalElement.classList.remove('show');
        document.body.classList.remove('modal-open');
        var backdrop = document.querySelector('.modal-backdrop');
        if(backdrop) backdrop.remove();
    }
}

function saveCustomProductName() {
    var productName = document.getElementById('custom_product_name').value.trim();
    var errorElement = document.getElementById('customProductNameError');
    var successElement = document.getElementById('customProductNameSuccess');
    
    if (!productName) {
        errorElement.textContent = 'Product name is required.';
        errorElement.style.display = 'block';
        return;
    }
    
    errorElement.style.display = 'none';
    successElement.style.display = 'none';
    successElement.textContent = 'Creating product name...';
    successElement.style.display = 'block';
    
    var formData = new FormData();
    formData.append('category_name', productName);
    formData.append('category_type', 'product-name');
    
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
            successElement.textContent = 'Product name added! Adding to dropdown...';
            successElement.style.display = 'block';
            errorElement.style.display = 'none';
            
            // Add to the product name dropdown
            var productNameSelect = document.getElementById('modal_product_name');
            var option = document.createElement('option');
            option.value = data.category_id;
            option.textContent = productName;
            productNameSelect.appendChild(option);
            productNameSelect.value = data.category_id;
            
            setTimeout(function() {
                closeCustomProductNameModal();
            }, 500);
        } else {
            errorElement.textContent = data.message || 'Error creating product name.';
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

    #modal_product_category,
    #modal_product_name {
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

    #modal_product_category:disabled,
    #modal_product_name:disabled {
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ccc' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") !important;
        background-color: #e9ecef !important;
        color: #6c757d !important;
        cursor: not-allowed !important;
    }
</style>

<!-- Custom Product Category Modal -->
<div class="modal fade" id="customProductCategoryModal" tabindex="-1" role="dialog" aria-labelledby="customProductCategoryLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customProductCategoryLabel">Add Custom Product Category</h5>
                <button type="button" class="close" onclick="closeCustomProductCategoryModal()" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="customProductCategoryForm">
                    <div class="form-group">
                        <label for="custom_product_category_name">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="custom_product_category_name" placeholder="Enter category name" maxlength="255" required>
                        <small class="form-text text-muted">This category will only be visible to you</small>
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

<!-- Custom Product Name Modal -->
<div class="modal fade" id="customProductNameModal" tabindex="-1" role="dialog" aria-labelledby="customProductNameLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customProductNameLabel">Add Custom Product Name</h5>
                <button type="button" class="close" onclick="closeCustomProductNameModal()" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="customProductNameForm">
                    <div class="form-group">
                        <label for="custom_product_name">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="custom_product_name" placeholder="Enter product name" maxlength="255" required>
                        <small class="form-text text-muted">This product name will only be visible to you</small>
                    </div>
                    <div id="customProductNameError" class="alert alert-danger" style="display: none;"></div>
                    <div id="customProductNameSuccess" class="alert alert-success" style="display: none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCustomProductNameModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveCustomProductName()">Add Product Name</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

