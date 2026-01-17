<?php
// Start output buffering to prevent any output before JSON response
ob_start();

// Handle card_number from URL, session, or cookie
// MUST be done before any output (before including header.php)
require_once(__DIR__ . '/../../app/config/database.php');

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
    $row = mysqli_fetch_array($query);
}

// Get user_id - must be done before form processing
// Check both user_details (unified table) and customer_login (legacy table) for compatibility
if(!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    header('Location: ../../panel/login/login.php');
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
    echo '<script>alert("User not found. Please login again."); window.location.href="../../panel/login/login.php";</script>';
    exit;
}

// Get products data from card_products_services (new dynamic table)
$card_id = mysqli_real_escape_string($connect, $_SESSION['card_id_inprocess']);
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
        $product_id = null;
        
        // Check if product name was submitted
        if(isset($_POST["d_pro_name$x"]) && !empty(trim($_POST["d_pro_name$x"]))) {
            $products_processed = true;
            $product_name = mysqli_real_escape_string($connect, trim($_POST["d_pro_name$x"]));
            
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
            // Check if we have processed image data from AJAX (base64)
            if(!empty($_POST["processed_product_image_data$x"])){
                // Use the processed image data from AJAX
                $product_image = base64_decode($_POST["processed_product_image_data$x"]);
            } elseif(!empty($_FILES["d_pro_img$x"]['tmp_name'])){
                // Use the new automatic crop and resize function
                if(function_exists('processImageUploadWithAutoCrop')) {
                    $result = processImageUploadWithAutoCrop(
                        $_FILES["d_pro_img$x"], 
                        600,      // Target size: 600x600
                        250000,   // Target file size: 250KB
                        200000,   // Min file size: 200KB
                        300000,   // Max file size: 300KB
                        ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
                        'jpeg',
                        null
                    );
                    
                    if($result['status']) {
                        // Store binary image data directly (don't escape binary data)
                        $product_image = $result['data'];
                        // Clean up temp file
                        if($result['file_path'] && file_exists($result['file_path'])) {
                            @unlink($result['file_path']);
                        }
                    } else {
                        $error_message .= $result['message'];
                        $upload_success = false;
                    }
                } elseif(function_exists('processImageUploadWithCompression')) {
                    // Fallback to compression function
                    $result = processImageUploadWithCompression($_FILES["d_pro_img$x"], 65, 250000, ['png', 'jpeg', 'jpg']);
                    if($result['status']) {
                        $product_image = mysqli_real_escape_string($connect, $result['data']);
                    } else {
                        $error_message .= $result['message'];
                        $upload_success = false;
                    }
                } else {
                    // Final fallback
                    $filename = $_FILES["d_pro_img$x"]['name'];
                    $imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $file_allow = array('png', 'jpeg', 'jpg');
                    
                    if(in_array($imageFileType, $file_allow)) {
                        if($_FILES["d_pro_img$x"]['size'] <= 250000) {
                            $source = $_FILES["d_pro_img$x"]['tmp_name'];
                            $destination = $_FILES["d_pro_img$x"]['tmp_name'];
                            $quality = 65;
                            $compressimage = compressImage($source, $destination, $quality);
                            $product_image = addslashes(file_get_contents($compressimage));
                        } else {
                            $error_message .= '<div class="alert alert-danger">File size for Product Image '.$x.' exceeds 250KB limit.</div>';
                            $upload_success = false;
                        }
                    } else {
                        $error_message .= '<div class="alert alert-danger">Only PNG, JPG, JPEG files allowed for Product Image '.$x.'</div>';
                        $upload_success = false;
                    }
                }
            }
            
            // Get next display_order
            $max_order_query = mysqli_query($connect, "SELECT MAX(display_order) as max_order FROM card_products_services WHERE card_id='$card_id' AND user_id=$user_id");
            $max_order_row = mysqli_fetch_array($max_order_query);
            $display_order = isset($max_order_row['max_order']) ? intval($max_order_row['max_order']) + 1 : $x;
            
            if($product_id && $product_id > 0) {
                // Update existing product
                // Verify product exists and belongs to user
                $verify_query = mysqli_query($connect, "SELECT id FROM card_products_services WHERE id=$product_id AND card_id='$card_id' AND user_id=$user_id");
                if(mysqli_num_rows($verify_query) == 0) {
                    $upload_success = false;
                    $error_message .= 'Product not found or access denied. ';
                    continue;
                }
                
                if($product_image !== null) {
                    // Use addslashes for binary data instead of direct string interpolation
                    $product_image_escaped = addslashes($product_image);
                    $update_query = "UPDATE card_products_services SET product_name='$product_name', product_image='$product_image_escaped' WHERE id=$product_id AND card_id='$card_id' AND user_id=$user_id";
                } else {
                    $update_query = "UPDATE card_products_services SET product_name='$product_name' WHERE id=$product_id AND card_id='$card_id' AND user_id=$user_id";
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
                
                // Use the verified user_id for the insert
                // Try prepared statements first, fallback to regular query if needed
                $insert_success = false;
                $insert_error = '';
                
                // Attempt prepared statement (more secure)
                if($product_image !== null) {
                    // Prepare statement with image
                    $stmt = $connect->prepare("INSERT INTO card_products_services (card_id, user_id, product_name, product_image, display_order) VALUES (?, ?, ?, ?, ?)");
                    if($stmt) {
                        $null = null;
                        $stmt->bind_param("sisbi", $card_id, $user_id, $product_name, $null, $display_order);
                        $stmt->send_long_data(3, $product_image);
                        $insert_success = $stmt->execute();
                        if(!$insert_success) {
                            $insert_error = $stmt->error;
                        }
                        $stmt->close();
                    }
                } else {
                    // Prepare statement without image
                    $stmt = $connect->prepare("INSERT INTO card_products_services (card_id, user_id, product_name, display_order) VALUES (?, ?, ?, ?)");
                    if($stmt) {
                        $stmt->bind_param("sisi", $card_id, $user_id, $product_name, $display_order);
                        $insert_success = $stmt->execute();
                        if(!$insert_success) {
                            $insert_error = $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                
                // Fallback to regular query if prepared statement failed
                if(!$insert_success) {
                    // Escape values for regular query
                    $card_id_escaped = mysqli_real_escape_string($connect, $card_id);
                    $product_name_escaped = mysqli_real_escape_string($connect, $product_name);
                    
                    if($product_image !== null) {
                        // Use addslashes for binary data instead of mysqli_real_escape_string
                        $product_image_escaped = addslashes($product_image);
                        $insert_query = "INSERT INTO card_products_services (card_id, user_id, product_name, product_image, display_order) VALUES ('$card_id_escaped', $user_id, '$product_name_escaped', '$product_image_escaped', $display_order)";
                    } else {
                        $insert_query = "INSERT INTO card_products_services (card_id, user_id, product_name, display_order) VALUES ('$card_id_escaped', $user_id, '$product_name_escaped', $display_order)";
                    }
                    
                    $insert_result = mysqli_query($connect, $insert_query);
                    if($insert_result) {
                        $insert_success = true;
                    } else {
                        $insert_error = mysqli_error($connect);
                    }
                }
                
                if(!$insert_success) {
                    $upload_success = false;
                    $db_error = $insert_error;
                    
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
            $response = ['success' => true, 'message' => 'Product saved successfully'];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            
            // Flush and exit
            if(function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            exit;
        } else {
            // Regular form submission - save only (no redirect)
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            $_SESSION['save_success'] = "Products and Services Updated Successfully!";
            header('Location: product-and-services.php?card_number='.$_SESSION['card_id_inprocess']);
            exit;
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
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
        <div class="main-top">
        <span class="heading">Product & Service</span>
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
        <?php if(isset($error_message)): ?>
            <div><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div id="status_remove_img"></div>

        <div class="card mb-4">
            <div class="card-body">
                <label class="heading font-sm-22 font-sm-24">Product &amp; Services:</label>
                <p class="sub_title">You can add upto 10 products &amp; services which you want to showcase on your Mini Website.</p>
                <p class="text-muted"><small>(Image Format: jpg, jpeg, png, gif, webp.)</small></p>
                <br>
                <button type="button" class="btn btn-primary add_product" onclick="openProductModal()"><i class="fa fa-plus" aria-hidden="true"></i> <span>Add Product/Services</span></button>

                <form action="" method="POST" enctype="multipart/form-data" id="productForm" style="display:none;">
                    <!-- Hidden form fields for product data (will be populated dynamically) -->
                </form>

                <div class="Product-ServicesTable">
                    <table class="display table">
                        <thead class="bg-secondary">
                            <tr>
                                <th>Product/Service Name</th>
                                <th>Image Details</th>
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
                                    $product_name = htmlspecialchars($product['product_name']);
                                    $product_image = $product['product_image'];
                            ?>
                                <tr data-product-id="<?php echo $product_id; ?>" data-card-id="<?php echo $card_id; ?>">
                                    <td valign="middle"><?php echo !empty($product_name) ? $product_name : 'No Name'; ?></td>
                                    <td valign="middle">
                                        <?php if(!empty($product_image)): ?>
                                            <img src="data:image/*;base64,<?php echo base64_encode($product_image); ?>" class="img-fluid" width="60px" alt="">
                                        <?php else: ?>
                                            <span class="text-muted">No Image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td valign="middle">
                                        <a class="edit" href="javascript:void(0);" onclick="editProduct(<?php echo $product_id; ?>, '<?php echo htmlspecialchars($product_name, ENT_QUOTES); ?>', '<?php echo !empty($product_image) ? base64_encode($product_image) : ''; ?>')">
                                            <img src="../../../assets/images/edit1.png" alt="">
                                        </a>
                                        <a class="delet" href="javascript:void(0);" onclick="removeData(<?php echo $product_id; ?>)">
                                            <img src="../../../assets/images/delet.png" alt="">
                                        </a>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            endif;
                            
                            // Show empty message if no products
                            if($productCount == 0):
                            ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No products/services added yet. Click "Add Product/Services" to add.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                   
                </div>
                <div class="Product-ServicesBtn">
                        <a href="payment-details.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left">
                            <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                            <span>Back</span>
                        </a>
                        <button type="button" class="btn btn-primary align-center save_btn" onclick="saveProducts()">
                            <img src="../../../assets/images/Save.png" class="img-fluid" width="35px" alt=""> 
                            <span>Save</span>
                        </button>
                        <a href="product-pricing.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right">
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
                    <input type="hidden" id="modal_product_number" value="">
                    <div class="form-group">
                        <label for="modal_product_name">Product/Service Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_product_name" maxlength="200" placeholder="Enter Product/Service Name" required>
                    </div>
                    <div class="form-group">
                        <label>Product/Service Image</label>
                        <div class="product-image-preview-modal" style="text-align: center; margin-bottom: 15px;">
                            <img id="modal_product_image_preview" 
                                 src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+" 
                                 alt="Product Image" 
                                 onclick="document.getElementById('modal_product_image').click()" 
                                 style="max-width: 200px; width:200px; max-height: 200px; border: 2px dashed #ddd; border-radius: 8px; cursor: pointer; padding: 10px;">
                        </div>
                        <input type="file" id="modal_product_image" onchange="readModalImage(this);" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none;">
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
var productImageFiles = {};

// Open product modal
function openProductModal() {
    currentProductId = null;
    processedProductImageData = null; // Reset processed image data
    $('#modal_product_number').val('');
    $('#modal_product_name').val('');
    $('#modal_product_image').val('');
    $('#modal_product_image_preview').attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+');
    $('#productModalLabel').text('Add Product/Service');
    $('.modal-footer button:last').text('Add Product');
    
    // Try Bootstrap 4 method first
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        $('#productModal').modal('show');
    } 
    // Try Bootstrap 5 method
    else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modalElement = document.getElementById('productModal');
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
    }
    // Fallback: show directly
    else {
        document.getElementById('productModal').style.display = 'block';
        document.getElementById('productModal').classList.add('show');
        document.body.classList.add('modal-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'modalBackdrop';
        document.body.appendChild(backdrop);
    }
}

// Edit product
function editProduct(productId, productName, productImageBase64) {
    currentProductId = productId;
    processedProductImageData = null; // Reset processed image data
    $('#modal_product_number').val(productId);
    $('#modal_product_name').val(productName);
    
    // Load existing image if available
    if(productImageBase64 && productImageBase64 !== '') {
        $('#modal_product_image_preview').attr('src', 'data:image/*;base64,' + productImageBase64);
    } else {
        $('#modal_product_image_preview').attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+');
    }
    
    $('#productModalLabel').text('Edit Product/Service');
    $('.modal-footer button:last').text('Update Product');
    $('#productModal').modal('show');
}

// Store processed image data for form submission
var processedProductImageData = null;

// Read modal image
function readModalImage(input) {
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
        
        // Show loading indicator
        $('#modal_product_image_preview').attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5Qcm9jZXNzaW5nLi4uPC90ZXh0Pjwvc3ZnPg==');
        
        // Immediately process the image via AJAX
        var formData = new FormData();
        formData.append('product_image', file);
        formData.append('process_product_image_ajax', '1');
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if(response && response.success) {
                    // Show processed image (cropped to 1:1, optimized)
                    var processedImageSrc = 'data:image/jpeg;base64,' + response.image_data;
                    $('#modal_product_image_preview').attr('src', processedImageSrc);
                    
                    // Store processed image data for form submission
                    processedProductImageData = response.image_data;
                } else {
                    alert(response.message || 'Error processing image. Please try again.');
                    // Revert preview
                    $('#modal_product_image_preview').attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+');
                    $(input).val('');
                    processedProductImageData = null;
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                var errorMsg = 'Error processing image. Please try again.';
                try {
                    if(xhr.responseText) {
                        var errorResponse = JSON.parse(xhr.responseText);
                        if(errorResponse && errorResponse.message) {
                            errorMsg = errorResponse.message;
                        }
                    }
                } catch(e) {}
                alert(errorMsg);
                // Revert preview
                $('#modal_product_image_preview').attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+');
                $(input).val('');
                processedProductImageData = null;
            }
        });
    }
}

// Add product to form and save immediately
function addProductToForm() {
    var productName = $('#modal_product_name').val();
    if(!productName) {
        alert('Please enter product/service name.');
        return;
    }
    
    var productId = $('#modal_product_number').val();
    var isEdit = (productId && productId !== '');
    
    // Create FormData for AJAX submission
    var formData = new FormData();
    formData.append('process4', '1');
    
    // Use a temporary slot number for form submission (PHP will handle the actual insert/update)
    var tempSlot = '1'; // Use slot 1 for simplicity, PHP will handle ID logic
    formData.append('d_pro_name' + tempSlot, productName);
    
    // If editing, include product_id both ways for compatibility
    if(isEdit) {
        formData.append('product_id' + tempSlot, productId);
        formData.append('product_id', productId); // Direct parameter as fallback
    }
    
    // Handle image file - use processed image data if available
    if(processedProductImageData) {
        // Use processed image data from AJAX
        formData.append('processed_product_image_data' + tempSlot, processedProductImageData);
    } else {
        // Fallback to original file if no processed data
        var imageFile = document.getElementById('modal_product_image').files[0];
        if(imageFile) {
            formData.append('d_pro_img' + tempSlot, imageFile);
        }
    }
    
    // Show loading
    var loadingMsg = '<div class="alert alert-info">Saving product...</div>';
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
                    $('#status_remove_img').html('<div class="alert alert-success">' + (response.message || 'Product saved successfully!') + '</div>');
                    setTimeout(function() {
                        // Reload the page to show updated product list
                        window.location.reload();
                    }, 1000);
                } else {
                    $('#status_remove_img').html('<div class="alert alert-danger">' + (response.message || 'Error saving product.') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', {status: status, error: error, response: xhr.responseText, statusCode: xhr.status});
                
                // Try to parse as JSON first
                try {
                    var response = JSON.parse(xhr.responseText);
                    if(response && !response.success) {
                        $('#status_remove_img').html('<div class="alert alert-danger">' + (response.message || 'Error saving product.') + '</div>');
                        return;
                    }
                } catch(e) {
                    // Not JSON - show the actual response for debugging
                    var errorMsg = 'Error saving product. ';
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
        imagePreview = '<img src="data:image/jpeg;base64,' + processedProductImageData + '" class="img-fluid" width="100px" alt="">';
        updateTableCallback();
    } else {
        var imageFile = document.getElementById('modal_product_image').files[0];
        if(imageFile) {
            var reader = new FileReader();
            reader.onload = function(e) {
                imagePreview = '<img src="' + e.target.result + '" class="img-fluid" width="100px" alt="">';
                updateTableCallback();
            };
            reader.readAsDataURL(imageFile);
        } else {
            imagePreview = '<span class="text-muted">No Image</span>';
            updateTableCallback();
        }
    }
    
    // Close modal immediately
    closeProductModal();
}

// Save products - just show success message (no redirect)
function saveProducts() {
    // Show success message (products are already saved via AJAX when added)
    $('#status_remove_img').html('<div class="alert alert-success">All products saved successfully!</div>');
    setTimeout(function(){
        $('#status_remove_img').html('');
    }, 2000);
}

// Remove product
function removeData(productId) {
    if(confirm('Are you sure you want to remove this product/service?')) {
        $('#status_remove_img').css('color','blue');
        
        $.ajax({
            url: '../../panel/login/js_request.php',
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
                        tableBody.html('<tr><td colspan="3" class="text-center text-muted">No products/services added yet. Click "Add Product/Services" to add.</td></tr>');
                    }
                    
                    $('#status_remove_img').html('<div class="alert alert-success">Product removed successfully!</div>');
                    setTimeout(function(){
                        $('#status_remove_img').html('');
                    }, 2000);
                }
            },
            error: function(){
                $('#status_remove_img').html('<div class="alert alert-danger">Error deleting product. Please try again.</div>');
            }
        });
    }
}

// Close product modal
function closeProductModal() {
    // Try Bootstrap 4 method first
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        $('#productModal').modal('hide');
    } 
    // Try Bootstrap 5 method
    else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modalElement = document.getElementById('productModal');
        var modal = bootstrap.Modal.getInstance(modalElement);
        if(modal) {
            modal.hide();
        }
    }
    // Fallback: hide directly
    else {
        document.getElementById('productModal').style.display = 'none';
        document.getElementById('productModal').classList.remove('show');
        document.body.classList.remove('modal-open');
        var backdrop = document.getElementById('modalBackdrop');
        if(backdrop) {
            backdrop.remove();
        }
    }
    currentProductNumber = null;
}

// Reset modal when opened for new product
if(typeof jQuery !== 'undefined') {
    $('#productModal').on('show.bs.modal', function() {
        if(!currentProductNumber) {
            $('#modal_product_number').val('');
            $('#modal_product_name').val('');
            $('#modal_product_image').val('');
            $('#modal_product_image_preview').attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+');
            $('#productModalLabel').text('Add Product/Service');
            $('.modal-footer button:last').text('Add Product');
        }
    });

    $('#productModal').on('hidden.bs.modal', function() {
        currentProductNumber = null;
    });
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    var modal = document.getElementById('productModal');
    if(event.target === modal) {
        closeProductModal();
    }
});
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
.Product-ServicesTable table th,
.Product-ServicesTable table td{
    text-align:left !important;
    
}
.Product-ServicesTable table th:first-child,
.Product-ServicesTable table td:first-child{
    padding-left:40px;
    padding-right: 155px;
}
.Product-ServicesTable table th,
.Product-ServicesTable table td{
    width: 33%;
}
.Product-ServicesTable table th:nth-child(2),
.Product-ServicesTable table th:nth-child(3),
.Product-ServicesTable table td:nth-child(2),
.Product-ServicesTable table td:nth-child(3){
    text-align:center !important;
}
.Product-ServicesBtn button{
    display: flex !important;
    color: #fff !important;
    justify-content: center;
    align-items: center;
    gap: 10px;
}

.Product-ServicesBtn button .angle{
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
.Product-ServicesBtn button span:not(.angle){
    font-weight:500;
    font-size:22px;
}
.Product-ServicesBtn button span .fa-angle-left,
.Product-ServicesBtn button span .fa-angle-right{
    font-weight: bold;
    font-size: 16px !important;
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
.Product-ServicesBtn{
    margin-top:30px;
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
.Product-ServicesTable{
width: 100%;
overflow-x:auto;
}
.Product-ServicesTable table{
    width: 700px;
    white-space:nowrap;
}
.Product-ServicesTable table th:first-child, .Product-ServicesTable table td:first-child {
    padding-left: 10px;
    padding-top:10px;
    padding-bottom:10px;
    padding-right: 20px;
    font-weight:500 !important;
}
.Product-ServicesTable table th,
 .Product-ServicesTable table td {
    padding-left: 10px;
    padding-top:10px;
    padding-bottom:10px;
    padding-right: 20px;
    font-weight:500 !important;
}

.Product-ServicesBtn{
padding:0px;
}
.Product-ServicesBtn button span:not(.angle) {
    font-weight: 500;
    font-size: 15px;
}
.Dashboard .main-top {
        padding:0px;
    }
    .Dashboard .heading {
        font-size: 22px !important;
        
    }
    .card-body p {
        font-size: 16px;
        line-height: 20px;
    }
    .add_product i, .add_product span {
        font-size: 20px !important;
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
        width: 110px !important;
        left: 90px;
        height: 36px;
}
.Copyright-left,
.Copyright-right{
    padding:0px;
}
.Dashboard .main-top {
        justify-content: flex-start;
        margin-left: 2px;
        padding: 20px 0px;
        padding-bottom: 5px;
    }
    }
    .Product-ServicesTable table th,
 .Product-ServicesTable table td {
    
    font-weight:500 !important;
}

.add_product:hover{
background-color: #ffbe17a6;
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
    .Product-ServicesBtn button {
        padding: 7px !important;
        margin-top: 22px !important;
    }
    .card-body .heading{
        font-size:24px ;
        font-weight: 500;
    }
</style>

<?php include '../includes/footer.php'; ?>





