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
                
                // Get MRP and price
                if(isset($_POST["pro_mrp$slot_found"]) && !empty(trim($_POST["pro_mrp$slot_found"]))) {
                    $product_mrp = floatval(preg_replace('/[^0-9.]/', '', $_POST["pro_mrp$slot_found"]));
                }
                if(isset($_POST["pro_price$slot_found"]) && !empty(trim($_POST["pro_price$slot_found"]))) {
                    $product_price = floatval(preg_replace('/[^0-9.]/', '', $_POST["pro_price$slot_found"]));
                }
                
                // Process image upload if provided
                if(!empty($_POST["processed_product_image_data$slot_found"])){
                    $product_image = base64_decode($_POST["processed_product_image_data$slot_found"]);
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
                                $product_image = $result['data'];
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
                        $product_image_escaped = addslashes($product_image);
                        $update_query = "UPDATE card_product_pricing SET product_name='$product_name', product_image='$product_image_escaped', mrp=$product_mrp, selling_price=$product_price WHERE id=$direct_product_id AND card_id='$card_id' AND user_id=$user_id";
                    } else {
                        $update_query = "UPDATE card_product_pricing SET product_name='$product_name', mrp=$product_mrp, selling_price=$product_price WHERE id=$direct_product_id AND card_id='$card_id' AND user_id=$user_id";
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
                    $product_image = base64_decode($_POST["processed_product_image_data$x"]);
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
                                $product_image = $result['data'];
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
                                    $product_image = file_get_contents($compressimage);
                                } else {
                                    $product_image = file_get_contents($source);
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
                        $product_image_escaped = addslashes($product_image);
                        $update_query = "UPDATE card_product_pricing SET product_name='$product_name', product_image='$product_image_escaped', mrp=$product_mrp, selling_price=$product_price WHERE id=$product_id AND card_id='$card_id' AND user_id=$user_id";
                    } else {
                        $update_query = "UPDATE card_product_pricing SET product_name='$product_name', mrp=$product_mrp, selling_price=$product_price WHERE id=$product_id AND card_id='$card_id' AND user_id=$user_id";
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
                    
                    if($product_image !== null) {
                        $product_image_escaped = addslashes($product_image);
                        $insert_query = "INSERT INTO card_product_pricing (card_id, user_id, product_name, product_image, mrp, selling_price, display_order) VALUES ('$card_id_escaped', $user_id, '$product_name_escaped', '$product_image_escaped', $product_mrp, $product_price, $display_order)";
                    } else {
                        $insert_query = "INSERT INTO card_product_pricing (card_id, user_id, product_name, mrp, selling_price, display_order) VALUES ('$card_id_escaped', $user_id, '$product_name_escaped', $product_mrp, $product_price, $display_order)";
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
                header('Location: product-pricing.php?card_number='.$_SESSION['card_id_inprocess']);
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
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
        <div class="main-top">
        <span class="heading">Product Pricing</span>
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
                <label class="heading">Product Pricing:</label>
                <p class="sub_title">You can add upto 60 products with pricing which you want to showcase on your Mini Website.</p>
                <p class="text-muted"><small>(Image Format: jpg, jpeg, png, gif, webp.)</small></p>
                <br>
                <div id="status_remove_img"></div>
                <button class="btn btn-primary add_product" onclick="openProductModal()"><i class="fa fa-plus" aria-hidden="true"></i> <span>Add Product/Services</span></button>

                <div class="Product-ServicesTable">
                    <table class="display table">
                        <thead class="bg-secondary">
                            <tr>
                                <th>Product Name</th>
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
                                    $prod_mrp = !empty($prod['mrp']) && $prod['mrp'] > 0 ? floatval($prod['mrp']) : 0;
                                    $prod_price = !empty($prod['selling_price']) && $prod['selling_price'] > 0 ? floatval($prod['selling_price']) : 0;
                            ?>
                                <tr data-product-id="<?php echo $prod_id; ?>" data-card-id="<?php echo $row['id']; ?>">
                                    <td valign="middle"><?php echo $prod_name; ?></td>
                                    <td valign="middle">
                                        <?php if(!empty($prod['product_image'])): ?>
                                            <img src="data:image/*;base64,<?php echo base64_encode($prod['product_image']); ?>" class="img-fluid" width="100px" alt="">
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
                                        <a class="edit" href="javascript:void(0);" onclick="editProduct(<?php echo $prod_id; ?>, '<?php echo htmlspecialchars($prod_name, ENT_QUOTES); ?>', '<?php echo $prod_mrp; ?>', '<?php echo $prod_price; ?>')">
                                            <img src="../../../assets/images/edit1.png" alt="">
                                        </a>
                                        <a class="delet" href="javascript:void(0);" onclick="removeData(<?php echo $row['id']; ?>, <?php echo $prod_id; ?>)">
                                            <img src="../../../assets/images/delet.png" alt="">
                                        </a>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No products added yet. Click "Add Product/Services" to add.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Hidden form for saving all products -->
                    <form id="productForm" action="" method="POST" enctype="multipart/form-data" style="display:none;">
                        <?php for($m = 1; $m <= 60; $m++): ?>
                            <input type="text" name="pro_name<?php echo $m; ?>" id="form_pro_name<?php echo $m; ?>" value="">
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
                        <a href="product-and-services.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left">
                            <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                            <span>Back</span>
                        </a>
                        <button class="btn btn-primary align-center save_btn" onclick="saveProducts()"><img src="../../../assets/images/Save.png" class="img-fluid" width="35px" alt=""> <span>Save</span></button>
                        <a href="image-gallery.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right">
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
                        <label for="modal_product_name">Product/Service Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_product_name" maxlength="200" placeholder="Enter Product/Service Name" required>
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
                        <label>Product/Service Image</label>
                        <div class="product-image-preview-modal" style="text-align: center; margin-bottom: 15px;">
                            <img id="modal_product_image_preview" 
                                 src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+" 
                                 alt="Product Image" 
                                 onclick="document.getElementById('modal_product_image').click()" 
                                 style="max-width: 200px; max-height: 200px; border: 2px dashed #ddd; border-radius: 8px; cursor: pointer; padding: 10px;">
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
var productImageFiles = {};
// Store processed image data for form submission
var processedProductImageData = null;

// Find next available product slot (for backward compatibility)
function findNextAvailableSlot() {
    // Count existing products and return next number
    var existingCount = $('tr[data-product-id]').length;
    if(existingCount >= 60) {
        alert('Maximum 60 products allowed. Please delete a product first.');
        return null;
    }
    return existingCount + 1;
}

// Open product modal
function openProductModal() {
    processedProductImageData = null; // Reset processed image data
    $('#modal_product_id').val('');
    $('#modal_product_number').val('');
    $('#modal_product_name').val('');
    $('#modal_product_mrp').val('');
    $('#modal_product_price').val('');
    $('#modal_product_image').val('');
    $('#modal_product_image_preview').attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+');
    $('#productModalLabel').text('Add Product/Service');
    $('.modal-footer button:last').text('Add Product');
    
    // Try to open modal using different methods
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        $('#productModal').modal('show');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modalElement = document.getElementById('productModal');
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        // Fallback: direct DOM manipulation
        document.getElementById('productModal').style.display = 'block';
        document.getElementById('productModal').classList.add('show');
        document.body.classList.add('modal-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'modalBackdrop';
        document.body.appendChild(backdrop);
    }
}

// Edit product (productNum is now product_id)
function editProduct(productId, productName, productMrp, productPrice) {
    processedProductImageData = null; // Reset processed image data
    
    // Get existing image BEFORE opening modal (to avoid timing issues)
    // Try multiple ways to find the row (by product_id, or by productNum if productId is actually a number)
    var existingRow = null;
    var existingImgSrc = null;
    
    // First try by data-product-id
    if(productId && productId !== '') {
        existingRow = $('tr[data-product-id="' + productId + '"]');
    }
    
    // If not found and productId looks like a number, it might be productNum
    if((!existingRow || existingRow.length === 0) && productId && !isNaN(productId)) {
        existingRow = $('tr[data-product-num="' + productId + '"]');
    }
    
    // If still not found, try to find by product name in the table
    if(!existingRow || existingRow.length === 0) {
        $('tbody tr').each(function() {
            var rowText = $(this).find('td:first').text().trim();
            if(rowText === productName) {
                existingRow = $(this);
                return false; // break loop
            }
        });
    }
    
    if(existingRow && existingRow.length > 0) {
        // Try to find image in 2nd column (Image Details column)
        var existingImg = existingRow.find('td:nth-child(2) img');
        if(existingImg.length === 0) {
            // Fallback: try any img in the row
            existingImg = existingRow.find('img');
        }
        
        if(existingImg.length > 0) {
            var imgSrc = existingImg.attr('src');
            // Check if it's a valid image (not SVG placeholder and not "No Image" text)
            if(imgSrc && imgSrc.startsWith('data:image') && !imgSrc.includes('svg+xml')) {
                existingImgSrc = imgSrc;
            } else if(imgSrc && imgSrc.startsWith('data:image/jpeg') || imgSrc.startsWith('data:image/png') || imgSrc.startsWith('data:image/*')) {
                existingImgSrc = imgSrc;
            }
        }
    }
    
    // Open modal first
    openProductModal();
    
    // Set values after modal is opened (use setTimeout to ensure modal is fully rendered)
    setTimeout(function() {
        $('#modal_product_id').val(productId);
        $('#modal_product_number').val(''); // Clear slot number for edit
        $('#modal_product_name').val(productName);
        $('#modal_product_mrp').val(productMrp);
        $('#modal_product_price').val(productPrice);
        
        // Set existing image if available
        if(existingImgSrc) {
            $('#modal_product_image_preview').attr('src', existingImgSrc);
        } else {
            $('#modal_product_image_preview').attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+');
        }
        
        $('#productModalLabel').text('Edit Product/Service');
        $('.modal-footer button:last').text('Update Product');
    }, 200); // Increased timeout to ensure modal is fully rendered
}

// Read modal image
function readModalImage(input) {
    if(input.files && input.files[0]) {
        var file = input.files[0];
        var allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
        var maxSize = 10 * 1024 * 1024; // 10MB (will be auto-optimized to 250KB)
        
        // Validate file type
        if(allowedTypes.indexOf(file.type) === -1) {
            alert('Only JPG, PNG, GIF, and WEBP images are allowed.');
            $(input).val('');
            processedProductImageData = null;
            return;
        }
        
        // Validate file size (10MB max - will be auto-optimized to 250KB)
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
    
    // Get product_id if editing
    var productId = $('#modal_product_id').val();
    
    var productNum = $('#modal_product_number').val();
    if(!productNum) {
        // If editing, try to find existing row's slot, otherwise find next available
        if(productId) {
            // Find existing row by product_id to get its slot number
            var existingRow = $('tr[data-product-id="' + productId + '"]');
            if(existingRow.length > 0) {
                productNum = existingRow.data('product-num') || 1;
            } else {
                productNum = 1; // Use slot 1 as placeholder for edit (PHP will identify by product_id)
            }
        } else {
            productNum = findNextAvailableSlot();
            if(!productNum) return;
        }
    }
    
    var productMrp = $('#modal_product_mrp').val() || '';
    var productPrice = $('#modal_product_price').val() || '';
    
    // Create FormData for AJAX submission
    var formData = new FormData();
    formData.append('product', '1');
    if(productId) {
        formData.append('product_id', productId);
        formData.append('product_id' + productNum, productId);
    }
    formData.append('pro_name' + productNum, productName);
    formData.append('pro_mrp' + productNum, productMrp);
    formData.append('pro_price' + productNum, productPrice);
    
    // Handle image file - use processed image data if available
    if(processedProductImageData) {
        // Use processed image data from AJAX
        formData.append('processed_product_image_data' + productNum, processedProductImageData);
    } else {
        // Fallback to original file if no processed data
        var imageFile = document.getElementById('modal_product_image').files[0];
        if(imageFile) {
            formData.append('pro_img' + productNum, imageFile);
        }
    }
    
    // Also update hidden form for later save
    $('#form_pro_name' + productNum).val(productName);
    $('#form_pro_mrp' + productNum).val(productMrp);
    $('#form_pro_price' + productNum).val(productPrice);
    // Set product_id in hidden form field if editing
    if(productId) {
        $('#form_product_id' + productNum).val(productId);
    }
    
    // Store processed image data in hidden form field if available
    if(processedProductImageData) {
        // Create hidden input for processed image data
        var hiddenInput = document.getElementById('form_processed_image_data' + productNum);
        if(!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'processed_product_image_data' + productNum;
            hiddenInput.id = 'form_processed_image_data' + productNum;
            document.getElementById('productForm').appendChild(hiddenInput);
        }
        hiddenInput.value = processedProductImageData;
    } else {
        var imageFile = document.getElementById('modal_product_image').files[0];
        if(imageFile) {
            var newInput = document.createElement('input');
            newInput.type = 'file';
            newInput.name = 'pro_img' + productNum;
            newInput.id = 'form_pro_img' + productNum;
            newInput.style.display = 'none';
            var dataTransfer = new DataTransfer();
            dataTransfer.items.add(imageFile);
            newInput.files = dataTransfer.files;
            var oldInput = document.getElementById('form_pro_img' + productNum);
            if(oldInput) oldInput.remove();
            document.getElementById('productForm').appendChild(newInput);
            productImageFiles[productNum] = imageFile;
        }
    }
    
    // Get cardId for use in AJAX callback
    var cardId = $('tbody tr[data-card-id]').first().data('card-id') || '';
    
    // Show loading
    var loadingMsg = '<div class="alert alert-info">Saving product...</div>';
    $('#status_remove_img').html(loadingMsg);
    
    // Get image preview first (before AJAX)
    var imagePreview = '';
    var updateTableCallback = function() {
        // Update table immediately (optimistic update)
        updateProductTable(productNum, productName, productMrp, productPrice, imagePreview, productId);
        
        // Submit via AJAX in background
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
                    // If we got a product_id back from server, update the row
                    if(response.product_id && !productId) {
                        // Update the row's data-product-id attribute
                        var updatedRow = $('tr[data-product-num="' + productNum + '"]');
                        if(updatedRow.length > 0) {
                            updatedRow.attr('data-product-id', response.product_id);
                            // Update edit and delete onclick to use the new product_id
                            var escapedProductName = productName.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                            updatedRow.find('.edit').attr('onclick', 'editProduct(' + response.product_id + ', \'' + escapedProductName + '\', \'' + productMrp + '\', \'' + productPrice + '\')');
                            updatedRow.find('.delet').attr('onclick', 'removeData(' + cardId + ', ' + response.product_id + ')');
                        }
                    }
                    
                    $('#status_remove_img').html('<div class="alert alert-success">' + (response.message || 'Product saved successfully!') + '</div>');
                    setTimeout(function() {
                        $('#status_remove_img').html('');
                    }, 2000);
                } else {
                    $('#status_remove_img').html('<div class="alert alert-danger">' + (response.message || 'Error saving product.') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', {
                    status: status, 
                    error: error, 
                    statusCode: xhr.status,
                    responseText: xhr.responseText.substring(0, 500) // First 500 chars
                });
                
                var errorMsg = 'Error saving product. ';
                
                // Check HTTP status code
                if(xhr.status === 0) {
                    errorMsg = 'Network error. Please check your connection.';
                } else if(xhr.status >= 500) {
                    errorMsg = 'Server error (HTTP ' + xhr.status + '). Please try again later.';
                } else if(xhr.status >= 400) {
                    errorMsg = 'Request error (HTTP ' + xhr.status + '). Please refresh and try again.';
                }
                
                // Try to parse JSON response
                try {
                    var response = JSON.parse(xhr.responseText);
                    if(response && !response.success) {
                        $('#status_remove_img').html('<div class="alert alert-danger">' + (response.message || 'Error saving product.') + '</div>');
                        return;
                    }
                } catch(e) {
                    // Not JSON - might be HTML error or PHP warning
                    if(xhr.responseText) {
                        // Check for specific error patterns
                        if(xhr.responseText.includes('Warning') || xhr.responseText.includes('Notice') || xhr.responseText.includes('Fatal error')) {
                            errorMsg += 'Server error detected. Check console for details.';
                        } else if(xhr.responseText.length > 0) {
                            errorMsg += 'Unexpected response from server.';
                        }
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
            // Check if there's an existing image (check by product_id if editing, otherwise by productNum)
            var existingRow = null;
            if(productId) {
                existingRow = $('tr[data-product-id="' + productId + '"]');
            } else {
                existingRow = $('tr[data-product-num="' + productNum + '"]');
            }
            if(existingRow.length) {
                var existingImg = existingRow.find('img');
                if(existingImg.length && existingImg.attr('src') && !existingImg.attr('src').includes('svg+xml')) {
                    imagePreview = existingImg[0].outerHTML;
                } else {
                    imagePreview = '<span class="text-muted">No Image</span>';
                }
            } else {
                imagePreview = '<span class="text-muted">No Image</span>';
            }
            updateTableCallback();
        }
    }
    
    // Close modal immediately
    closeProductModal();
}

// Update product table dynamically
function updateProductTable(productNum, productName, productMrp, productPrice, imagePreview, productId) {
    var tableBody = $('.Product-ServicesTable tbody');
    var cardId = $('tbody tr[data-card-id]').first().data('card-id') || '<?php echo isset($row["id"]) ? $row["id"] : ""; ?>';
    
    // Remove "No products" message if exists
    tableBody.find('td[colspan="5"]').closest('tr').remove();
    
    // Escape product name for JavaScript
    var escapedProductName = productName.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    
    // Format prices
    var mrpDisplay = productMrp ? '<i class="fa fa-inr" aria-hidden="true"></i> ' + parseFloat(productMrp).toFixed(2) : '<span class="text-muted">-</span>';
    var priceDisplay = productPrice ? '<i class="fa fa-inr" aria-hidden="true"></i> ' + parseFloat(productPrice).toFixed(2) : '<span class="text-muted">-</span>';
    
    // Check if row already exists (for edit) - check by product_id if provided, otherwise by productNum
    var existingRow = null;
    if(productId) {
        existingRow = tableBody.find('tr[data-product-id="' + productId + '"]');
    } else {
        existingRow = tableBody.find('tr[data-product-num="' + productNum + '"]');
    }
    
    if(existingRow.length > 0) {
        // Update existing row
        existingRow.find('td:first').text(productName);
        existingRow.find('td:nth-child(2)').html(imagePreview);
        existingRow.find('td:nth-child(3)').html(mrpDisplay);
        existingRow.find('td:nth-child(4)').html(priceDisplay);
        // Update edit onclick with product_id
        var editProductId = productId || productNum;
        existingRow.find('.edit').attr('onclick', 'editProduct(' + editProductId + ', \'' + escapedProductName + '\', \'' + productMrp + '\', \'' + productPrice + '\')');
        // Update delete onclick with product_id
        existingRow.find('.delet').attr('onclick', 'removeData(' + cardId + ', ' + editProductId + ')');
    } else {
        // Add new row
        var rowProductId = productId || '';
        var newRow = '<tr data-product-id="' + rowProductId + '" data-product-num="' + productNum + '" data-card-id="' + cardId + '">' +
            '<td valign="middle">' + productName + '</td>' +
            '<td valign="middle">' + imagePreview + '</td>' +
            '<td valign="middle">' + mrpDisplay + '</td>' +
            '<td valign="middle">' + priceDisplay + '</td>' +
            '<td valign="middle">' +
            '<a class="edit" href="javascript:void(0);" onclick="editProduct(' + (productId || productNum) + ', \'' + escapedProductName + '\', \'' + productMrp + '\', \'' + productPrice + '\')">' +
            '<img src="../../../assets/images/edit1.png" alt=""></a> ' +
            '<a class="delet" href="javascript:void(0);" onclick="removeData(' + cardId + ', ' + (productId || productNum) + ')">' +
            '<img src="../../../assets/images/delet.png" alt=""></a>' +
            '</td>' +
            '</tr>';
        tableBody.append(newRow);
    }
}

// Save products - just submit form (no redirect, PHP will handle success message)
function saveProducts() {
    document.getElementById('productForm').submit();
    // Note: PHP will save and show success message, no redirect
}

// Remove product (numb is now product_id)
function removeData(cardId, productId) {
    if(confirm('Are you sure you want to remove this product?')) {
        $('#status_remove_img').css('color','blue');
        
        $.ajax({
            url: '../../panel/login/js_request.php',
            method: 'POST',
            data: {action: 'delete_product_pricing', product_id: productId},
            dataType: 'text',
            success: function(data){
                $('#status_remove_img').html(data);
                if(data.includes('success')){
                    // Remove the row from table
                    $('tr[data-product-id="' + productId + '"]').remove();
                    
                    // Check if table is now empty
                    var tableBody = $('.Product-ServicesTable tbody');
                    if(tableBody.find('tr[data-product-id]').length === 0) {
                        tableBody.html('<tr><td colspan="5" class="text-center text-muted">No products added yet. Click "Add Product/Services" to add.</td></tr>');
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
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        $('#productModal').modal('hide');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modalElement = document.getElementById('productModal');
        var modal = bootstrap.Modal.getInstance(modalElement);
        if(modal) {
            modal.hide();
        } else {
            var newModal = new bootstrap.Modal(modalElement);
            newModal.hide();
        }
    } else {
        document.getElementById('productModal').style.display = 'none';
        document.getElementById('productModal').classList.remove('show');
        document.body.classList.remove('modal-open');
        var backdrop = document.getElementById('modalBackdrop');
        if(backdrop) backdrop.remove();
    }
}
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
    .card-body .heading{
        position: relative;
    }
    .card-body heading:after
    {
        content: '';
        width: 165px;
        height: 1px;
        background: #ffb300;
        position: absolute;
        left: 8px;
        bottom: 2px;
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
        font-size:22px !important;
    }
    .sub_title{
        font-size:20px;
        margin-bottom:0px !important;
    }
    .add_product i,.add_product span{
        font-size:22px !important;
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
    padding-right: 60px;
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
.Product-ServicesBtn{
    padding: 0px 40px;
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
        left: 98px;
        height: 36px;
}
.Copyright-left,
.Copyright-right{
    padding:0px;
}

.submitBtnSection{
    margin-top:20px;
}

.card-body heading:after {
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
    font-size: 16px !important;
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

.headingTop {
    font-size: 28px;
    padding: 0px;
    line-height:40px;
}
.Dashboard .heading {
        font-size: 22px !important;
        
    }
    .card-body   .heading:after {
        content: '';
        width: 120px;
      
    }
    .card-body p {
        font-size: 16px;
        line-height: 20px;
    }
    .add_product i, .add_product span {
        font-size: 20px !important;
    }
    }
    .Product-ServicesTable table th,
 .Product-ServicesTable table td {
    
    font-weight:500 !important;
}
.Product-ServicesTable table td:last-child img{
        width: 20px;
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





