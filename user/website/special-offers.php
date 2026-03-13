<?php
// Check if this is an AJAX request FIRST - before any output
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Start output buffering for AJAX requests immediately to prevent any output
if($is_ajax && isset($_POST['offer'])) {
    ob_start();
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
}

// Handle card_number from URL, session, or cookie
// MUST be done before any output (before including header.php)
require_once(__DIR__ . '/../../app/config/database.php');

// Handle AJAX image processing FIRST - before any other output
if(isset($_POST['process_product_image_ajax']) && !empty($_FILES['product_image']['tmp_name'])){
    while(ob_get_level() > 0) {
        ob_end_clean();
    }
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    try {
        $file_validation_path = __DIR__ . '/../../includes/file_validation.php';
        if(file_exists($file_validation_path)) {
            require_once $file_validation_path;
        } elseif(file_exists('../../includes/file_validation.php')) {
            require_once '../../includes/file_validation.php';
        } else {
            throw new Exception('File validation library not found');
        }
        
        if(!function_exists('processImageUploadWithAutoCrop')) {
            throw new Exception('Image processing function not available');
        }
        
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
            $base64Image = base64_encode($result['data']);
            
            echo json_encode([
                'success' => true,
                'image_data' => $base64Image,
                'dimensions' => isset($result['dimensions']) ? $result['dimensions'] : ['width' => 600, 'height' => 600],
                'file_size' => isset($result['file_size']) ? $result['file_size'] : 0,
                'message' => 'Image processed successfully'
            ]);
            
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
    setcookie('card_id_inprocess', $card_number, time() + (86400 * 1), '/');
} elseif(isset($_COOKIE['card_id_inprocess']) && !empty($_COOKIE['card_id_inprocess'])) {
    $_SESSION['card_id_inprocess'] = $_COOKIE['card_id_inprocess'];
}

// Fetch existing card data
$row = [];
$offers_data = [];
$user_id = 0;

if(isset($_SESSION['card_id_inprocess']) && !empty($_SESSION['card_id_inprocess'])) {
    $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');
    if(mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_array($query);
        
        $user_email_escaped = mysqli_real_escape_string($connect, $_SESSION['user_email']);
        $user_email_lower = strtolower(trim($user_email_escaped));
        $user_id = 0;
        
        $user_details_query = mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = '$user_email_lower' LIMIT 1");
        if($user_details_query && mysqli_num_rows($user_details_query) > 0) {
            $user_details_row = mysqli_fetch_array($user_details_query);
            $user_id = isset($user_details_row['id']) ? intval($user_details_row['id']) : 0;
        }
        
        // Get special offers from new table (card_special_offers)
        $card_id = mysqli_real_escape_string($connect, $_SESSION['card_id_inprocess']);
        if($user_id > 0) {
            $offers_query = mysqli_query($connect, "SELECT * FROM card_special_offers WHERE card_id='$card_id' AND user_id=$user_id ORDER BY display_order ASC, id ASC");
            while($offer_row = mysqli_fetch_array($offers_query)) {
                $offers_data[] = $offer_row;
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

if(isset($_POST['offer'])){
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
        
        $file_validation_path = __DIR__ . '/../../includes/file_validation.php';
        if(file_exists($file_validation_path)) {
            require_once $file_validation_path;
        } elseif(file_exists('../../includes/file_validation.php')) {
            require_once '../../includes/file_validation.php';
        }
        
        if(!function_exists('processImageUploadWithAutoCrop') && !function_exists('compressImage')) {
            function compressImage($source,$destination,$quality){
            if(!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
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
                return $source;
            }
            
            @imagejpeg($image, $destination, $quality);
            @imagedestroy($image);
            return $destination;
        }
        }
        
        $offerUploadDirAbs = __DIR__ . '/../../assets/upload/websites/special-offers/';
        if (!is_dir($offerUploadDirAbs)) {
            @mkdir($offerUploadDirAbs, 0775, true);
        }
        
        function saveOfferImageToFilesystem($binaryData, $uploadDirAbs, $cardId, $offerTitle) {
            if (empty($binaryData) || empty($uploadDirAbs) || !is_dir($uploadDirAbs)) {
                return null;
            }
            $safeOfferTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($offerTitle, 0, 50));
            $fileName = $cardId . '_' . $safeOfferTitle . '_' . date('ymdsih') . '.jpg';
            $filePath = $uploadDirAbs . $fileName;
            if(@file_put_contents($filePath, $binaryData)) {
                return $fileName;
            }
            return null;
        }
        
        $card_id = mysqli_real_escape_string($connect, $_SESSION['card_id_inprocess']);
        $offers_processed = false;
        
        $processed_offer_ids = [];
        
        if(isset($_POST['offer_id']) && !empty($_POST['offer_id'])) {
            $direct_offer_id = intval($_POST['offer_id']);
            $offer_title = '';
            $offer_discount = 0;
            $offer_image = null;
            
            $slot_found = null;
            for($x = 1; $x <= 5; $x++) {
                if(isset($_POST["offer_title$x"]) && !empty(trim($_POST["offer_title$x"]))) {
                    $slot_found = $x;
                    break;
                }
            }
            
            if($slot_found) {
                $offer_title = mysqli_real_escape_string($connect, trim($_POST["offer_title$slot_found"]));
                
                if(isset($_POST["offer_discount$slot_found"]) && !empty(trim($_POST["offer_discount$slot_found"]))) {
                    $offer_discount = intval(preg_replace('/[^0-9]/', '', $_POST["offer_discount$slot_found"]));
                }
                
                $offer_description = '';
                if(isset($_POST["offer_desc$slot_found"])) {
                    $offer_description = mysqli_real_escape_string($connect, trim($_POST["offer_desc$slot_found"]));
                    if(strlen($offer_description) > 500) {
                        $offer_description = substr($offer_description, 0, 500);
                    }
                }
                
                if(!empty($_POST["processed_offer_image_data$slot_found"])){
                    $binary_data = base64_decode($_POST["processed_offer_image_data$slot_found"]);
                    $offer_image = saveOfferImageToFilesystem($binary_data, $offerUploadDirAbs, $card_id, $offer_title);
                } elseif(!empty($_FILES["offer_img$slot_found"]['tmp_name'])) {
                    if(function_exists('processImageUploadWithAutoCrop')) {
                        try {
                            $result = processImageUploadWithAutoCrop(
                                $_FILES["offer_img$slot_found"], 
                                600, 250000, 200000, 300000,
                                ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
                                'jpeg', null
                            );
                            if($result['status']) {
                                $offer_image = saveOfferImageToFilesystem($result['data'], $offerUploadDirAbs, $card_id, $offer_title);
                                if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                                    @unlink($result['file_path']);
                                }
                            }
                        } catch(Exception $e) {
                            $error_message .= '<div class="alert alert-danger">Error processing image: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    }
                }
                
                $verify_query = mysqli_query($connect, "SELECT id FROM card_special_offers WHERE id=$direct_offer_id AND card_id='$card_id' AND user_id=$user_id");
                if(mysqli_num_rows($verify_query) > 0) {
                    if($offer_image !== null) {
                        $offer_image_escaped = mysqli_real_escape_string($connect, $offer_image);
                        $update_query = "UPDATE card_special_offers SET offer_title='$offer_title', offer_description='$offer_description', offer_image='$offer_image_escaped', discount_percentage=$offer_discount WHERE id=$direct_offer_id AND card_id='$card_id' AND user_id=$user_id";
                    } else {
                        $update_query = "UPDATE card_special_offers SET offer_title='$offer_title', offer_description='$offer_description', discount_percentage=$offer_discount WHERE id=$direct_offer_id AND card_id='$card_id' AND user_id=$user_id";
                    }
                    $update_result = mysqli_query($connect, $update_query);
                    if(!$update_result) {
                        $error_message .= '<div class="alert alert-danger">Failed to update offer. Error: ' . mysqli_error($connect) . '</div>';
                    }
                    $processed_offer_ids[] = $direct_offer_id;
                }
            }
        }
        
        for($x = 1; $x <= 5; $x++) {
            $offer_title = '';
            $offer_id = null;
            $offer_discount = 0;
            $offer_image = null;
            $offer_badge = '';
            $offer_start_date = '';
            $offer_end_date = '';
            $offer_start_time = '';
            $offer_end_time = '';
            $offer_status = 'Active';
            
            if(isset($_POST["offer_title$x"]) && !empty(trim($_POST["offer_title$x"]))) {
                $offers_processed = true;
                $offer_title = mysqli_real_escape_string($connect, trim($_POST["offer_title$x"]));
                
                if(isset($_POST["offer_discount$x"]) && !empty(trim($_POST["offer_discount$x"]))) {
                    $offer_discount = intval(preg_replace('/[^0-9]/', '', $_POST["offer_discount$x"]));
                }
                
                $offer_description = '';
                if(isset($_POST["offer_desc$x"])) {
                    $offer_description = mysqli_real_escape_string($connect, trim($_POST["offer_desc$x"]));
                    if(strlen($offer_description) > 500) {
                        $offer_description = substr($offer_description, 0, 500);
                    }
                }
                
                if(isset($_POST["offer_badge$x"]) && !empty(trim($_POST["offer_badge$x"]))) {
                    $offer_badge = mysqli_real_escape_string($connect, trim($_POST["offer_badge$x"]));
                }
                
                if(isset($_POST["offer_start_date$x"]) && !empty($_POST["offer_start_date$x"])) {
                    $offer_start_date = mysqli_real_escape_string($connect, $_POST["offer_start_date$x"]);
                }
                
                if(isset($_POST["offer_end_date$x"]) && !empty($_POST["offer_end_date$x"])) {
                    $offer_end_date = mysqli_real_escape_string($connect, $_POST["offer_end_date$x"]);
                }
                
                if(isset($_POST["offer_start_time$x"]) && !empty($_POST["offer_start_time$x"])) {
                    $offer_start_time = mysqli_real_escape_string($connect, $_POST["offer_start_time$x"]);
                }
                
                if(isset($_POST["offer_end_time$x"]) && !empty($_POST["offer_end_time$x"])) {
                    $offer_end_time = mysqli_real_escape_string($connect, $_POST["offer_end_time$x"]);
                }
                
                if(isset($_POST["offer_status$x"]) && !empty($_POST["offer_status$x"])) {
                    $offer_status = mysqli_real_escape_string($connect, $_POST["offer_status$x"]);
                }
                
                if(isset($_POST["offer_id$x"]) && !empty($_POST["offer_id$x"])) {
                    $offer_id = intval($_POST["offer_id$x"]);
                    if(in_array($offer_id, $processed_offer_ids)) {
                        continue;
                    }
                }
                
                if(!empty($_POST["processed_offer_image_data$x"])){
                    $binary_data = base64_decode($_POST["processed_offer_image_data$x"]);
                    $offer_image = saveOfferImageToFilesystem($binary_data, $offerUploadDirAbs, $card_id, $offer_title);
                } elseif(!empty($_FILES["offer_img$x"]['tmp_name'])) {
                    if(function_exists('processImageUploadWithAutoCrop')) {
                        try {
                            $result = processImageUploadWithAutoCrop(
                                $_FILES["offer_img$x"], 
                                600,      
                                250000,   
                                200000,   
                                300000,   
                                ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
                                'jpeg',
                                null
                            );
                            
                            if($result['status']) {
                                $offer_image = saveOfferImageToFilesystem($result['data'], $offerUploadDirAbs, $card_id, $offer_title);
                                if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                                    @unlink($result['file_path']);
                                }
                            } else {
                                $error_message .= isset($result['message']) ? $result['message'] : '<div class="alert alert-danger">Error processing image for offer '.$x.'.</div>';
                            }
                        } catch(Exception $e) {
                            $error_message .= '<div class="alert alert-danger">Error processing image for offer '.$x.': ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                    } else {
                        $filename = $_FILES["offer_img$x"]['name'];
                        $imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        $file_allow = array('png', 'jpeg', 'jpg', 'gif', 'webp');
                        
                        if(in_array($imageFileType, $file_allow)) {
                            if($_FILES["offer_img$x"]['size'] <= 250000) {
                                $source = $_FILES["offer_img$x"]['tmp_name'];
                                if(function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
                                    $destination = $_FILES["offer_img$x"]['tmp_name'];
                                    $quality = 65;
                                    $compressimage = compressImage($source, $destination, $quality);
                                    $binary_data = file_get_contents($compressimage);
                                    $offer_image = saveOfferImageToFilesystem($binary_data, $offerUploadDirAbs, $card_id, $offer_title);
                                } else {
                                    $binary_data = file_get_contents($source);
                                    $offer_image = saveOfferImageToFilesystem($binary_data, $offerUploadDirAbs, $card_id, $offer_title);
                                }
                            } else {
                                $error_message .= '<div class="alert alert-danger">File size for Offer Image '.$x.' exceeds 250KB limit.</div>';
                            }
                        } else {
                            $error_message .= '<div class="alert alert-danger">Only PNG, JPG, JPEG, GIF, WEBP files allowed for Offer Image '.$x.'</div>';
                        }
                    }
                }
                
                $max_order_query = mysqli_query($connect, "SELECT MAX(display_order) as max_order FROM card_special_offers WHERE card_id='$card_id' AND user_id=$user_id");
                $max_order_row = mysqli_fetch_array($max_order_query);
                $display_order = isset($max_order_row['max_order']) ? intval($max_order_row['max_order']) + 1 : $x;
                
                if($offer_id && $offer_id > 0) {
                    $verify_query = mysqli_query($connect, "SELECT id FROM card_special_offers WHERE id=$offer_id AND card_id='$card_id' AND user_id=$user_id");
                    if(mysqli_num_rows($verify_query) == 0) {
                        $error_message .= '<div class="alert alert-danger">Offer not found or access denied.</div>';
                        continue;
                    }
                    
                    if($offer_image !== null) {
                        $offer_image_escaped = mysqli_real_escape_string($connect, $offer_image);
                        $update_query = "UPDATE card_special_offers SET offer_title='$offer_title', offer_description='$offer_description', offer_image='$offer_image_escaped', badge='$offer_badge', discount_percentage=$offer_discount, start_date='$offer_start_date', end_date='$offer_end_date', start_time='$offer_start_time', end_time='$offer_end_time', status='$offer_status' WHERE id=$offer_id AND card_id='$card_id' AND user_id=$user_id";
                    } else {
                        $update_query = "UPDATE card_special_offers SET offer_title='$offer_title', offer_description='$offer_description', badge='$offer_badge', discount_percentage=$offer_discount, start_date='$offer_start_date', end_date='$offer_end_date', start_time='$offer_start_time', end_time='$offer_end_time', status='$offer_status' WHERE id=$offer_id AND card_id='$card_id' AND user_id=$user_id";
                    }
                    $update_result = mysqli_query($connect, $update_query);
                    if(!$update_result) {
                        $error_message .= '<div class="alert alert-danger">Failed to update offer. Error: ' . mysqli_error($connect) . '</div>';
                    }
                } else {
                    if($user_id <= 0) {
                        $error_message .= '<div class="alert alert-danger">Invalid user ID. Please login again.</div>';
                        continue;
                    }
                    
                    $card_id_escaped = mysqli_real_escape_string($connect, $card_id);
                    $offer_title_escaped = mysqli_real_escape_string($connect, $offer_title);
                    
                    if($offer_image !== null) {
                        $offer_image_escaped = mysqli_real_escape_string($connect, $offer_image);
                        $insert_query = "INSERT INTO card_special_offers (card_id, user_id, offer_title, offer_description, offer_image, badge, discount_percentage, start_date, end_date, start_time, end_time, status, display_order) VALUES ('$card_id_escaped', $user_id, '$offer_title_escaped', '$offer_description', '$offer_image_escaped', '$offer_badge', $offer_discount, '$offer_start_date', '$offer_end_date', '$offer_start_time', '$offer_end_time', '$offer_status', $display_order)";
                    } else {
                        $insert_query = "INSERT INTO card_special_offers (card_id, user_id, offer_title, offer_description, badge, discount_percentage, start_date, end_date, start_time, end_time, status, display_order) VALUES ('$card_id_escaped', $user_id, '$offer_title_escaped', '$offer_description', '$offer_badge', $offer_discount, '$offer_start_date', '$offer_end_date', '$offer_start_time', '$offer_end_time', '$offer_status', $display_order)";
                    }
                    
                    $insert_result = mysqli_query($connect, $insert_query);
                    if(!$insert_result) {
                        $error_message .= '<div class="alert alert-danger">Failed to add offer. Error: ' . mysqli_error($connect) . '</div>';
                    } else {
                        $new_offer_id = mysqli_insert_id($connect);
                        if($is_ajax && $new_offer_id > 0) {
                            if(!isset($ajax_offer_id)) {
                                $ajax_offer_id = $new_offer_id;
                            }
                        }
                    }
                }
            }
        }
        
        if(empty($error_message)) {
            if($is_ajax) {
                if(ob_get_level() > 0) {
                    ob_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                $response_data = ['success' => true, 'message' => 'Offer saved successfully'];
                if(isset($ajax_offer_id) && $ajax_offer_id > 0) {
                    $response_data['offer_id'] = $ajax_offer_id;
                }
                echo json_encode($response_data);
                if(ob_get_level() > 0) {
                    ob_end_flush();
                }
                exit;
            } else {
                $_SESSION['save_success'] = "Offers Updated Successfully!";
                header('Location: special-offers.php?card_number='.$_SESSION['card_id_inprocess']);
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
require_once(__DIR__ . '/../../common/image_upload_crop_modal.php');
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
        <div class="main-top">
        <span class="heading">Special Offers</span>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Special Offers</li>
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
                <label class="heading">Special Offers:</label>
                <p class="sub_title">You can add upto 5 special offers with discount percentages to showcase on your Mini Website.</p>
                <p class="text-muted"><small>(Image Format: jpg, jpeg, png, gif, webp.)</small></p>
                <br>
                <div id="status_remove_img"></div>
                <button class="btn btn-primary add_product" onclick="openOfferModal()"><i class="fa fa-plus" aria-hidden="true"></i> <span>Add Offer</span></button>

                <div class="Product-ServicesTable">
                    <table class="display table">
                        <thead class="bg-secondary">
                            <tr>
                                <th>Image</th>
                                <th>Badge</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Start/End Date</th>
                                <th>Start/End Time</th>
                                <th>Offer Status</th>
                                <th>Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $offerCount = count($offers_data);
                            if($offerCount > 0):
                                foreach($offers_data as $index => $offer): 
                                    $offer_id = intval($offer['id']);
                                    $offer_title = !empty($offer['offer_title']) ? htmlspecialchars($offer['offer_title']) : 'No Title';
                                    $offer_description = !empty($offer['offer_description']) ? htmlspecialchars($offer['offer_description']) : '';
                                    $offer_discount = !empty($offer['discount_percentage']) ? intval($offer['discount_percentage']) : 0;
                                    $offer_badge = !empty($offer['badge']) ? htmlspecialchars($offer['badge']) : '-';
                                    $start_date = !empty($offer['start_date']) ? $offer['start_date'] : '-';
                                    $end_date = !empty($offer['end_date']) ? $offer['end_date'] : '-';
                                    $start_time = !empty($offer['start_time']) ? $offer['start_time'] : '-';
                                    $end_time = !empty($offer['end_time']) ? $offer['end_time'] : '-';
                                    $offer_status = !empty($offer['status']) ? htmlspecialchars($offer['status']) : 'Active';
                            ?>
                                <tr data-offer-id="<?php echo $offer_id; ?>" data-card-id="<?php echo $card_id;?>">
                                    <td valign="middle">
                                        <?php if(!empty($offer['offer_image'])): ?>
                                            <?php
                                            if(is_string($offer['offer_image']) && strpos($offer['offer_image'], '/') === false && strpos($offer['offer_image'], '\\') === false && strpos($offer['offer_image'], '.') !== false) {
                                                $image_src = '../../assets/upload/websites/special-offers/' . $offer['offer_image'];
                                            } else {
                                                $image_src = 'data:image/*;base64,' . base64_encode($offer['offer_image']);
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($image_src); ?>" class="img-fluid" width="80px" alt="">
                                        <?php else: ?>
                                            <span class="text-muted">No Image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td valign="middle">
                                        <?php if($offer_badge !== '-'): ?>
                                            <span class="badge badge-info"><?php echo $offer_badge; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td valign="middle"><strong><?php echo $offer_title; ?></strong></td>
                                    <td valign="middle"><?php echo !empty($offer_description) ? substr($offer_description, 0, 40) . (strlen($offer_description) > 40 ? '...' : '') : '<span class="text-muted">-</span>'; ?></td>
                                    <td valign="middle">
                                        <small>
                                            <?php 
                                            $date_range = '';
                                            if($start_date !== '-' || $end_date !== '-') {
                                                $date_range = ($start_date !== '-' ? $start_date : '...') . ' to ' . ($end_date !== '-' ? $end_date : '...');
                                                echo $date_range;
                                            } else {
                                                echo '<span class="text-muted">-</span>';
                                            }
                                            ?>
                                        </small>
                                    </td>
                                    <td valign="middle">
                                        <small>
                                            <?php 
                                            $time_range = '';
                                            if($start_time !== '-' || $end_time !== '-') {
                                                $time_range = ($start_time !== '-' ? substr($start_time, 0, 5) : '...') . ' to ' . ($end_time !== '-' ? substr($end_time, 0, 5) : '...');
                                                echo $time_range;
                                            } else {
                                                echo '<span class="text-muted">-</span>';
                                            }
                                            ?>
                                        </small>
                                    </td>
                                    <td valign="middle">
                                        <?php 
                                        if($offer_status === 'Active') {
                                            echo '<span class="badge badge-success">Active</span>';
                                        } elseif($offer_status === 'Inactive') {
                                            echo '<span class="badge badge-secondary">Inactive</span>';
                                        } else {
                                            echo '<span class="badge badge-warning">' . $offer_status . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td valign="middle">
                                        <a class="edit" href="javascript:void(0);" onclick="editOffer(<?php echo $offer_id; ?>, '<?php echo htmlspecialchars($offer_title, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($offer_badge, ENT_QUOTES); ?>', '<?php echo $offer_discount; ?>', '<?php echo htmlspecialchars($offer_description, ENT_QUOTES); ?>', '<?php echo $start_date; ?>', '<?php echo $end_date; ?>', '<?php echo $start_time; ?>', '<?php echo $end_time; ?>', '<?php echo $offer_status; ?>')"><img src="../../assets/images/edit1.png" alt=""></a>
                                        <a class="delet" href="javascript:void(0);" onclick="removeOffer(<?php echo $offer_id; ?>)"><img src="../../assets/images/delet.png" alt=""></a>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No special offers added yet. Click "Add Offer" to add.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Hidden form for saving all offers -->
                    <form id="offerForm" action="" method="POST" enctype="multipart/form-data" style="display:none;">
                        <?php for($m = 1; $m <= 5; $m++): ?>
                            <input type="text" name="offer_title<?php echo $m; ?>" id="form_offer_title<?php echo $m; ?>" value="">
                            <input type="text" name="offer_badge<?php echo $m; ?>" id="form_offer_badge<?php echo $m; ?>" value="">
                            <input type="number" name="offer_discount<?php echo $m; ?>" id="form_offer_discount<?php echo $m; ?>" value="">
                            <input type="text" name="offer_desc<?php echo $m; ?>" id="form_offer_desc<?php echo $m; ?>" value="">
                            <input type="date" name="offer_start_date<?php echo $m; ?>" id="form_offer_start_date<?php echo $m; ?>" value="">
                            <input type="date" name="offer_end_date<?php echo $m; ?>" id="form_offer_end_date<?php echo $m; ?>" value="">
                            <input type="time" name="offer_start_time<?php echo $m; ?>" id="form_offer_start_time<?php echo $m; ?>" value="">
                            <input type="time" name="offer_end_time<?php echo $m; ?>" id="form_offer_end_time<?php echo $m; ?>" value="">
                            <input type="text" name="offer_status<?php echo $m; ?>" id="form_offer_status<?php echo $m; ?>" value="">
                            <input type="file" name="offer_img<?php echo $m; ?>" id="form_offer_img<?php echo $m; ?>">
                            <input type="hidden" name="processed_offer_image_data<?php echo $m; ?>" id="form_processed_image_data<?php echo $m; ?>" value="">
                            <input type="hidden" name="offer_id<?php echo $m; ?>" id="form_offer_id<?php echo $m; ?>" value="">
                        <?php endfor; ?>
                    </form>
                   
                </div>
                <div class="Product-ServicesBtn">
                        <a href="product-pricing.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left">
                            <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                            <span>Back</span>
                        </a>
                        <button class="btn btn-primary align-center save_btn" onclick="saveOffers()"><img src="../../assets/images/Save.png" class="img-fluid" width="35px" alt=""> <span>Save</span></button>
                        <a href="image-gallery.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right">
                            <span>Next</span>
                            <span class="right_angle angle"><i class="fa fa-angle-right"></i></span>
                        </a>
                    </div>
            </div>
        </div>
    </div>
</main>

<!-- Offer Modal -->
<div class="modal fade" id="offerModal" tabindex="-1" role="dialog" aria-labelledby="offerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="offerModalLabel">Add Special Offer</h5>
                <button type="button" class="close" onclick="closeOfferModal()" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 600px; overflow-y: auto;">
                <form id="modalOfferForm">
                    <input type="hidden" id="modal_offer_id" value="">
                   
                    <div class="form-group">
                        <label for="modal_offer_title">Offer Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_offer_title" placeholder="e.g., Flat 25% OFF" maxlength="255" required>
                    </div>

                    <div class="form-group">
                        <label for="modal_offer_badge">Badge <span class="text-muted">(Optional)</span></label>
                        <input type="text" class="form-control" id="modal_offer_badge" placeholder="e.g., Hot Deal, Limited Time" maxlength="100">
                    </div>

                    <div class="form-group">
                        <label for="modal_offer_discount">Discount Percentage <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="modal_offer_discount" placeholder="Enter discount %" min="0" max="100" required>
                            <div class="input-group-append">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="modal_offer_description">Offer Description <span class="text-muted">(Optional)</span></label>
                        <textarea class="form-control" id="modal_offer_description" maxlength="500" placeholder="Enter offer description details" rows="3"></textarea>
                        <small class="form-text text-muted">
                            <strong id="char_counter_display">0/500</strong> characters
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_offer_start_date">Start Date <span class="text-muted">(Optional)</span></label>
                                <input type="date" class="form-control" id="modal_offer_start_date">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_offer_end_date">End Date <span class="text-muted">(Optional)</span></label>
                                <input type="date" class="form-control" id="modal_offer_end_date">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_offer_start_time">Start Time <span class="text-muted">(Optional)</span></label>
                                <input type="time" class="form-control" id="modal_offer_start_time">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_offer_end_time">End Time <span class="text-muted">(Optional)</span></label>
                                <input type="time" class="form-control" id="modal_offer_end_time">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="modal_offer_status">Offer Status</label>
                        <select class="form-control" id="modal_offer_status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Offer Image (Banner) <span class="text-muted">(Optional)</span></label>
                        <div class="offer-image-preview-modal" style="text-align: center; margin-bottom: 15px; min-height: 220px; display: flex; align-items: center; justify-content: center;">
                            <img id="modal_offer_image_preview" 
                                 src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+" 
                                 alt="Offer Image" 
                                 onclick="document.getElementById('modal_offer_image').click()" 
                                 style="max-width: 200px; width: auto; max-height: 200px; height: auto; border: 2px dashed #ddd; border-radius: 8px; cursor: pointer; padding: 10px; object-fit: contain;">
                        </div>
                        <input type="file" id="modal_offer_image" onchange="handleOfferImageUpload(this);" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('modal_offer_image').click()">Choose Image</button>
                        <small class="form-text text-muted">File Supported - .png, .jpg, .jpeg, .gif, .webp</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeOfferModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addOfferToForm()">Add Offer</button>
            </div>
        </div>
    </div>
</div>

<style>
    #imageCropModal {
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
</style>

<script>
var currentOfferId = null;
var processedOfferImageData = null;

function updateCharCount() {
    var textarea = document.getElementById('modal_offer_description');
    var counter = document.getElementById('char_counter_display');
    
    if(textarea && counter) {
        var length = textarea.value.length;
        counter.textContent = length + '/500';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var textarea = document.getElementById('modal_offer_description');
    if(textarea) {
        textarea.addEventListener('input', updateCharCount);
        textarea.addEventListener('keyup', updateCharCount);
        textarea.addEventListener('change', updateCharCount);
    }
});

function openOfferModal() {
    currentOfferId = null;
    processedOfferImageData = null;
    
    var fields = [
        'modal_offer_id',
        'modal_offer_title',
        'modal_offer_badge',
        'modal_offer_discount',
        'modal_offer_description',
        'modal_offer_start_date',
        'modal_offer_end_date',
        'modal_offer_start_time',
        'modal_offer_end_time',
        'modal_offer_status',
        'modal_offer_image'
    ];
    
    fields.forEach(function(fieldId) {
        var field = document.getElementById(fieldId);
        if(field) {
            if(fieldId === 'modal_offer_status') {
                field.value = 'Active';
            } else {
                field.value = '';
            }
        }
    });
    
    var imgPreview = document.getElementById('modal_offer_image_preview');
    if(imgPreview) {
        imgPreview.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+';
    }
    
    var modalLabel = document.getElementById('offerModalLabel');
    if(modalLabel) {
        modalLabel.textContent = 'Add Special Offer';
    }
    
    var submitBtn = document.querySelector('#offerModal .modal-footer button:last-child');
    if(submitBtn) {
        submitBtn.textContent = 'Add Offer';
    }
    
    updateCharCount();
    
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery('#offerModal').modal('show');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modalElement = document.getElementById('offerModal');
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        document.getElementById('offerModal').style.display = 'block';
        document.getElementById('offerModal').classList.add('show');
        document.body.classList.add('modal-open');
    }
}

function editOffer(offerId, offerTitle, offerBadge, offerDiscount, offerDescription, startDate, endDate, startTime, endTime, offerStatus) {
    currentOfferId = offerId;
    processedOfferImageData = null;
    
    var modalOfferIdField = document.getElementById('modal_offer_id');
    var modalOfferTitleField = document.getElementById('modal_offer_title');
    var modalOfferBadgeField = document.getElementById('modal_offer_badge');
    var modalOfferDiscountField = document.getElementById('modal_offer_discount');
    var modalOfferDescField = document.getElementById('modal_offer_description');
    var modalOfferStartDateField = document.getElementById('modal_offer_start_date');
    var modalOfferEndDateField = document.getElementById('modal_offer_end_date');
    var modalOfferStartTimeField = document.getElementById('modal_offer_start_time');
    var modalOfferEndTimeField = document.getElementById('modal_offer_end_time');
    var modalOfferStatusField = document.getElementById('modal_offer_status');
    
    if(modalOfferIdField) modalOfferIdField.value = offerId;
    if(modalOfferTitleField) modalOfferTitleField.value = offerTitle || '';
    if(modalOfferBadgeField) modalOfferBadgeField.value = (offerBadge && offerBadge !== '-') ? offerBadge : '';
    if(modalOfferDiscountField) modalOfferDiscountField.value = offerDiscount || '';
    if(modalOfferDescField) modalOfferDescField.value = offerDescription || '';
    if(modalOfferStartDateField) modalOfferStartDateField.value = (startDate && startDate !== '-') ? startDate : '';
    if(modalOfferEndDateField) modalOfferEndDateField.value = (endDate && endDate !== '-') ? endDate : '';
    if(modalOfferStartTimeField) modalOfferStartTimeField.value = (startTime && startTime !== '-') ? startTime : '';
    if(modalOfferEndTimeField) modalOfferEndTimeField.value = (endTime && endTime !== '-') ? endTime : '';
    if(modalOfferStatusField) modalOfferStatusField.value = offerStatus || 'Active';
    
    var row = document.querySelector('tr[data-offer-id="' + offerId + '"]');
    var imgPreview = document.getElementById('modal_offer_image_preview');
    
    if(row && imgPreview) {
        var img = row.querySelector('img');
        if(img && img.src) {
            imgPreview.src = img.src;
        } else {
            imgPreview.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+';
        }
    }
    
    var modalLabel = document.getElementById('offerModalLabel');
    if(modalLabel) {
        modalLabel.textContent = 'Edit Special Offer';
    }
    
    var submitBtn = document.querySelector('#offerModal .modal-footer button:last-child');
    if(submitBtn) {
        submitBtn.textContent = 'Update Offer';
    }
    
    updateCharCount();
    
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery('#offerModal').modal('show');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modalElement = document.getElementById('offerModal');
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        document.getElementById('offerModal').style.display = 'block';
        document.getElementById('offerModal').classList.add('show');
        document.body.classList.add('modal-open');
    }
}

function handleOfferImageUpload(input) {
    if(input.files && input.files[0]) {
        var file = input.files[0];
        var allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
        var maxSize = 10 * 1024 * 1024;
        
        if(allowedTypes.indexOf(file.type) === -1) {
            alert('Only JPG, PNG, GIF, and WEBP images are allowed.');
            input.value = '';
            processedOfferImageData = null;
            return;
        }
        
        if(file.size > maxSize) {
            alert('Image size must be 10MB or less. The image will be automatically optimized to 250KB.');
            input.value = '';
            processedOfferImageData = null;
            return;
        }
        
        if (typeof ImageCropUpload !== 'undefined') {
            window.offerImageCropCallback = function(base64Data) {
                processedOfferImageData = base64Data;
                var previewDataUri = 'data:image/jpeg;base64,' + base64Data;
                var imgPreview = document.getElementById('modal_offer_image_preview');
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
                // Ensure modal remains open after crop
                var modalElement = document.getElementById('offerModal');
                if(modalElement && typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                    jQuery(modalElement).modal('show');
                }
            };
            
            ImageCropUpload.open(file, {
                method: 'base64',
                title: 'Adjust & Crop Offer Banner',
                onSuccess: function(base64Data) {
                    if (window.offerImageCropCallback) window.offerImageCropCallback(base64Data);
                },
                onError: function(msg) {
                    alert(msg || 'Error processing image. Please try again.');
                    input.value = '';
                    processedOfferImageData = null;
                    // Ensure modal remains open on error
                    var modalElement = document.getElementById('offerModal');
                    if(modalElement && typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                        jQuery(modalElement).modal('show');
                    }
                }
            });
            input.value = '';
        } else {
            alert('Image crop tool not available. Please refresh the page.');
        }
    }
}

function addOfferToForm() {
    var modalOfferTitleField = document.getElementById('modal_offer_title');
    var offerTitleValue = modalOfferTitleField ? modalOfferTitleField.value : '';
    
    if(!offerTitleValue) {
        alert('Please enter offer title.');
        return;
    }
    
    // Check if limit of 5 offers is reached (only for new offers, not edits)
    var modalOfferIdField = document.getElementById('modal_offer_id');
    var offerId = modalOfferIdField ? modalOfferIdField.value : '';
    var isEdit = (offerId && offerId !== '');
    
    if(!isEdit) {
        var offerRows = document.querySelectorAll('tr[data-offer-id]');
        if(offerRows.length >= 5) {
            alert('You can only add up to 5 special offers. Please delete one before adding a new offer.');
            return;
        }
    }
    
    // Get all date and time fields
    var modalOfferStartDateField = document.getElementById('modal_offer_start_date');
    var startDate = modalOfferStartDateField ? modalOfferStartDateField.value : '';
    
    var modalOfferEndDateField = document.getElementById('modal_offer_end_date');
    var endDate = modalOfferEndDateField ? modalOfferEndDateField.value : '';
    
    var modalOfferStartTimeField = document.getElementById('modal_offer_start_time');
    var startTime = modalOfferStartTimeField ? modalOfferStartTimeField.value : '';
    
    var modalOfferEndTimeField = document.getElementById('modal_offer_end_time');
    var endTime = modalOfferEndTimeField ? modalOfferEndTimeField.value : '';
    
    // Validation: If start date is provided, end date should also be provided
    if(startDate && !endDate) {
        alert('Please enter end date when start date is provided.');
        return;
    }
    
    // Validation: If end date is provided, start date should also be provided
    if(endDate && !startDate) {
        alert('Please enter start date when end date is provided.');
        return;
    }
    
    // Validation: End date should be greater than or equal to start date
    if(startDate && endDate) {
        var startDateObj = new Date(startDate);
        var endDateObj = new Date(endDate);
        if(endDateObj < startDateObj) {
            alert('End date must be greater than or equal to start date.');
            return;
        }
    }
    
    // Validation: If start time is provided, end time should also be provided
    if(startTime && !endTime) {
        alert('Please enter end time when start time is provided.');
        return;
    }
    
    // Validation: If end time is provided, start time should also be provided
    if(endTime && !startTime) {
        alert('Please enter start time when end time is provided.');
        return;
    }
    
    // Validation: If both start and end times are provided on same day, end time should be after start time
    if(startTime && endTime && startDate && endDate && startDate === endDate) {
        var startTimeObj = new Date('2000-01-01 ' + startTime);
        var endTimeObj = new Date('2000-01-01 ' + endTime);
        if(endTimeObj <= startTimeObj) {
            alert('End time must be after start time on the same day.');
            return;
        }
    }
    
    var formData = new FormData();
    formData.append('offer', '1');
    var tempSlot = '1';
    formData.append('offer_title' + tempSlot, offerTitleValue);
    
    var modalOfferBadgeField = document.getElementById('modal_offer_badge');
    var badge = modalOfferBadgeField ? modalOfferBadgeField.value : '';
    if(badge) formData.append('offer_badge' + tempSlot, badge);
    
    var modalOfferDiscountField = document.getElementById('modal_offer_discount');
    var discount = modalOfferDiscountField ? modalOfferDiscountField.value : '';
    if(discount) formData.append('offer_discount' + tempSlot, discount);
    
    var modalOfferDescField = document.getElementById('modal_offer_description');
    var description = modalOfferDescField ? modalOfferDescField.value : '';
    if(description) formData.append('offer_desc' + tempSlot, description);
    
    if(startDate) formData.append('offer_start_date' + tempSlot, startDate);
    if(endDate) formData.append('offer_end_date' + tempSlot, endDate);
    if(startTime) formData.append('offer_start_time' + tempSlot, startTime);
    if(endTime) formData.append('offer_end_time' + tempSlot, endTime);
    
    var modalOfferStatusField = document.getElementById('modal_offer_status');
    var status = modalOfferStatusField ? modalOfferStatusField.value : 'Active';
    if(status) formData.append('offer_status' + tempSlot, status);
    
    if(isEdit) {
        formData.append('offer_id' + tempSlot, offerId);
        formData.append('offer_id', offerId);
    }
    
    if(processedOfferImageData) {
        formData.append('processed_offer_image_data' + tempSlot, processedOfferImageData);
    } else {
        var imageFileInput = document.getElementById('modal_offer_image');
        if(imageFileInput && imageFileInput.files[0]) {
            formData.append('offer_img' + tempSlot, imageFileInput.files[0]);
        }
    }
    
    var statusElement = document.getElementById('status_remove_img');
    if(statusElement) {
        statusElement.innerHTML = '<div class="alert alert-info">Saving offer...</div>';
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
                    statusEl.innerHTML = '<div class="alert alert-success">' + (data.message || 'Offer saved successfully!') + '</div>';
                }
                setTimeout(function(){ window.location.reload(); }, 800);
            } else {
                var statusEl = document.getElementById('status_remove_img');
                if(statusEl) {
                    statusEl.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Error saving offer.') + '</div>';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            var statusEl = document.getElementById('status_remove_img');
            if(statusEl) {
                statusEl.innerHTML = '<div class="alert alert-danger">Error saving offer. Please try again.</div>';
            }
        });
    };
    
    if(processedOfferImageData) {
        updateTableCallback();
    } else {
        var imageFileInput = document.getElementById('modal_offer_image');
        if(imageFileInput && imageFileInput.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) { updateTableCallback(); };
            reader.readAsDataURL(imageFileInput.files[0]);
        } else {
            updateTableCallback();
        }
    }
    closeOfferModal();
}

function saveOffers() {
    $('#status_remove_img').html('<div class="alert alert-success">All offers saved successfully!</div>');
    setTimeout(function(){ $('#status_remove_img').html(''); }, 2000);
}

function removeOffer(offerId) {
    if(confirm('Are you sure you want to remove this offer?')) {
        var statusElement = document.getElementById('status_remove_img');
        if(statusElement) {
            statusElement.style.color = 'blue';
        }
        
        fetch('../../admin/js_request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'offer_id=' + encodeURIComponent(offerId) + '&action=delete_offer'
        })
        .then(response => response.text())
        .then(data => {
            var statusEl = document.getElementById('status_remove_img');
            if(statusEl) {
                statusEl.innerHTML = data;
            }
            
            if(data.includes('success')){
                var row = document.querySelector('tr[data-offer-id="' + offerId + '"]');
                if(row) {
                    row.remove();
                }
                
                var tableBody = document.querySelector('.Product-ServicesTable tbody');
                var hasOffers = tableBody ? tableBody.querySelector('tr[data-offer-id]') : null;
                
                if(tableBody && !hasOffers) {
                    tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No special offers added yet. Click "Add Offer" to add.</td></tr>';
                }
                
                if(statusEl) {
                    statusEl.innerHTML = '<div class="alert alert-success">Offer removed successfully!</div>';
                    setTimeout(function(){ statusEl.innerHTML = ''; }, 2000);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            var statusEl = document.getElementById('status_remove_img');
            if(statusEl) {
                statusEl.innerHTML = '<div class="alert alert-danger">Error deleting offer. Please try again.</div>';
            }
        });
    }
}

function closeOfferModal() {
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery('#offerModal').modal('hide');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modalElement = document.getElementById('offerModal');
        var modal = bootstrap.Modal.getInstance(modalElement);
        if(modal) modal.hide();
    } else {
        document.getElementById('offerModal').style.display = 'none';
        document.getElementById('offerModal').classList.remove('show');
        document.body.classList.remove('modal-open');
        var backdrop = document.getElementById('modalBackdrop');
        if(backdrop) backdrop.remove();
    }
    
    var modalOfferIdField = document.getElementById('modal_offer_id');
    if(modalOfferIdField) modalOfferIdField.value = '';
    
    processedOfferImageData = null;
}

document.addEventListener('click', function(event) {
    var modal = document.getElementById('offerModal');
    if(event.target === modal) closeOfferModal();
});
</script>

<?php include '../includes/footer.php'; ?>
