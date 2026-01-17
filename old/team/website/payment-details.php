<?php
// Handle card_number from URL, session, or cookie
// MUST be done before any output (before including header.php)
require_once('../../common/config.php');

// Handle AJAX image processing FIRST - before any other output
// This must be at the very top to prevent any output before JSON response
if(isset($_POST['process_qr_ajax']) && !empty($_FILES['qr_image']['tmp_name'])){
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
        if($_FILES['qr_image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $_FILES['qr_image']['error']);
        }
        
        $result = processImageUploadWithAutoCrop(
            $_FILES['qr_image'], 
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

// Handle form submission
if(isset($_POST['process4'])){
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
        }
        
        // Process Paytm QR code upload
        // Check if we have processed image data from AJAX (base64)
        if(!empty($_POST['processed_qr_paytm_data'])){
            // Use the processed image data from AJAX
            $qrData = base64_decode($_POST['processed_qr_paytm_data']);
            $d_qr_paytm = addslashes($qrData);
            $update1 = mysqli_query($connect, 'UPDATE digi_card SET d_qr_paytm="'.$d_qr_paytm.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
        } elseif(!empty($_FILES['d_qr_paytm']['tmp_name'])){
            // Use the new automatic crop and resize function
            if(function_exists('processImageUploadWithAutoCrop')) {
                $result = processImageUploadWithAutoCrop(
                    $_FILES['d_qr_paytm'], 
                    600,      // Target size: 600x600
                    250000,   // Target file size: 250KB
                    200000,   // Min file size: 200KB
                    300000,   // Max file size: 300KB
                    ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
                    'jpeg',
                    null
                );
                
                if($result['status']) {
                    $d_qr_paytm = addslashes($result['data']);
                    $update1 = mysqli_query($connect, 'UPDATE digi_card SET d_qr_paytm="'.$d_qr_paytm.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                    // Clean up temp file
                    if($result['file_path'] && file_exists($result['file_path'])) {
                        @unlink($result['file_path']);
                    }
                } else {
                    $error_message = $result['message'];
                }
            } elseif(function_exists('validateImageFile')) {
                // Fallback to validation function
                $validation = validateImageFile($_FILES['d_qr_paytm'], 250000);
                if($validation['status']) {
                    $d_qr_paytm = addslashes(file_get_contents($_FILES['d_qr_paytm']['tmp_name']));
                    $update1 = mysqli_query($connect, 'UPDATE digi_card SET d_qr_paytm="'.$d_qr_paytm.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                } else {
                    $error_message = $validation['message'];
                }
            } else {
                // Final fallback
                $filename = $_FILES['d_qr_paytm']['name'];
                $imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $file_allow = array('png', 'jpeg', 'jpg');
                if(in_array($imageFileType, $file_allow) && $_FILES['d_qr_paytm']['size'] <= 250000) {
                    $d_qr_paytm = addslashes(file_get_contents($_FILES['d_qr_paytm']['tmp_name']));
                    $update1 = mysqli_query($connect, 'UPDATE digi_card SET d_qr_paytm="'.$d_qr_paytm.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                } else {
                    $error_message = '<div class="alert alert-danger">Paytm QR: Only PNG, JPG, JPEG files allowed (max 250KB)</div>';
                }
            }
        }
        
        // Process Google Pay QR code upload
        // Check if we have processed image data from AJAX (base64)
        if(!empty($_POST['processed_qr_google_pay_data'])){
            // Use the processed image data from AJAX
            $qrData = base64_decode($_POST['processed_qr_google_pay_data']);
            $d_qr_google_pay = addslashes($qrData);
            $update1 = mysqli_query($connect, 'UPDATE digi_card SET d_qr_google_pay="'.$d_qr_google_pay.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
        } elseif(!empty($_FILES['d_qr_google_pay']['tmp_name'])){
            // Use the new automatic crop and resize function
            if(function_exists('processImageUploadWithAutoCrop')) {
                $result = processImageUploadWithAutoCrop(
                    $_FILES['d_qr_google_pay'], 
                    600,      // Target size: 600x600
                    250000,   // Target file size: 250KB
                    200000,   // Min file size: 200KB
                    300000,   // Max file size: 300KB
                    ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
                    'jpeg',
                    null
                );
                
                if($result['status']) {
                    $d_qr_google_pay = addslashes($result['data']);
                    $update1 = mysqli_query($connect, 'UPDATE digi_card SET d_qr_google_pay="'.$d_qr_google_pay.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                    // Clean up temp file
                    if($result['file_path'] && file_exists($result['file_path'])) {
                        @unlink($result['file_path']);
                    }
                } else {
                    $error_message = $result['message'];
                }
            } elseif(function_exists('validateImageFile')) {
                // Fallback to validation function
                $validation = validateImageFile($_FILES['d_qr_google_pay'], 250000);
                if($validation['status']) {
                    $d_qr_google_pay = addslashes(file_get_contents($_FILES['d_qr_google_pay']['tmp_name']));
                    $update1 = mysqli_query($connect, 'UPDATE digi_card SET d_qr_google_pay="'.$d_qr_google_pay.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                } else {
                    $error_message = $validation['message'];
                }
            } else {
                // Final fallback
                $filename = $_FILES['d_qr_google_pay']['name'];
                $imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $file_allow = array('png', 'jpeg', 'jpg');
                if(in_array($imageFileType, $file_allow) && $_FILES['d_qr_google_pay']['size'] <= 250000) {
                    $d_qr_google_pay = addslashes(file_get_contents($_FILES['d_qr_google_pay']['tmp_name']));
                    $update1 = mysqli_query($connect, 'UPDATE digi_card SET d_qr_google_pay="'.$d_qr_google_pay.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                } else {
                    $error_message = '<div class="alert alert-danger">Google Pay QR: Only PNG, JPG, JPEG files allowed (max 250KB)</div>';
                }
            }
        }
        
        // Process PhonePe QR code upload
        // Check if we have processed image data from AJAX (base64)
        if(!empty($_POST['processed_qr_phone_pay_data'])){
            // Use the processed image data from AJAX
            $qrData = base64_decode($_POST['processed_qr_phone_pay_data']);
            $d_qr_phone_pay = addslashes($qrData);
            $update1 = mysqli_query($connect, 'UPDATE digi_card SET d_qr_phone_pay="'.$d_qr_phone_pay.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
        } elseif(!empty($_FILES['d_qr_phone_pay']['tmp_name'])){
            // Use the new automatic crop and resize function
            if(function_exists('processImageUploadWithAutoCrop')) {
                $result = processImageUploadWithAutoCrop(
                    $_FILES['d_qr_phone_pay'], 
                    600,      // Target size: 600x600
                    250000,   // Target file size: 250KB
                    200000,   // Min file size: 200KB
                    300000,   // Max file size: 300KB
                    ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
                    'jpeg',
                    null
                );
                
                if($result['status']) {
                    $d_qr_phone_pay = addslashes($result['data']);
                    $update1 = mysqli_query($connect, 'UPDATE digi_card SET d_qr_phone_pay="'.$d_qr_phone_pay.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                    // Clean up temp file
                    if($result['file_path'] && file_exists($result['file_path'])) {
                        @unlink($result['file_path']);
                    }
                } else {
                    $error_message = $result['message'];
                }
            } elseif(function_exists('validateImageFile')) {
                // Fallback to validation function
                $validation = validateImageFile($_FILES['d_qr_phone_pay'], 250000);
                if($validation['status']) {
                    $d_qr_phone_pay = addslashes(file_get_contents($_FILES['d_qr_phone_pay']['tmp_name']));
                    $update1 = mysqli_query($connect, 'UPDATE digi_card SET d_qr_phone_pay="'.$d_qr_phone_pay.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                } else {
                    $error_message = $validation['message'];
                }
            } else {
                // Final fallback
                $filename = $_FILES['d_qr_phone_pay']['name'];
                $imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $file_allow = array('png', 'jpeg', 'jpg');
                if(in_array($imageFileType, $file_allow) && $_FILES['d_qr_phone_pay']['size'] <= 250000) {
                    $d_qr_phone_pay = addslashes(file_get_contents($_FILES['d_qr_phone_pay']['tmp_name']));
                    $update1 = mysqli_query($connect, 'UPDATE digi_card SET d_qr_phone_pay="'.$d_qr_phone_pay.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
                } else {
                    $error_message = '<div class="alert alert-danger">PhonePe QR: Only PNG, JPG, JPEG files allowed (max 250KB)</div>';
                }
            }
        }
        
        // Update payment details
        $update = mysqli_query($connect, 'UPDATE digi_card SET 
        d_paytm="'.mysqli_real_escape_string($connect, $_POST['d_paytm']).'",
        d_google_pay="'.mysqli_real_escape_string($connect, $_POST['d_google_pay']).'",
        d_phone_pay="'.mysqli_real_escape_string($connect, $_POST['d_phone_pay']).'",
        d_account_no="'.mysqli_real_escape_string($connect, $_POST['d_account_no']).'",
        d_ifsc="'.mysqli_real_escape_string($connect, $_POST['d_ifsc']).'",
        d_ac_name="'.mysqli_real_escape_string($connect, $_POST['d_ac_name']).'",
        d_bank_name="'.mysqli_real_escape_string($connect, $_POST['d_bank_name']).'",
        d_ac_type="'.mysqli_real_escape_string($connect, $_POST['d_ac_type']).'"
        WHERE id="'.$_SESSION['card_id_inprocess'].'"');
        
        if($update){
            $_SESSION['save_success'] = "Payment Details Updated Successfully!";
            header('Location: payment-details.php?card_number='.$_SESSION['card_id_inprocess']);
            exit;
        } else {
            $_SESSION['save_error'] = "Error! Try Again.";
            header('Location: payment-details.php?card_number='.$_SESSION['card_id_inprocess']);
            exit;
        }
    } else {
        $_SESSION['save_error'] = "Detail Not Available. Try Again.";
        header('Location: payment-details.php?card_number='.$_SESSION['card_id_inprocess']);
        exit;
    }
}

include 'header.php';
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
    
        <div class="main-top">
        <span class="headingTop">Payment Details</span>
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
        
        <div id="status_remove_img"></div>

        <div class="card mb-4">
            <div class="card-body">
                <label class="heading">Payment Through UPI/QR Scanner</label>
                <small>You can add multiple QR codes as well as Bank Details For your customers so that you can easily receive payments.</small>
                <form action="" method="POST" enctype="multipart/form-data" id="card_form">
                    <!-- Hidden fields to store processed image data -->
                    <input type="hidden" name="processed_qr_paytm_data" id="processed_qr_paytm_data" value="">
                    <input type="hidden" name="processed_qr_google_pay_data" id="processed_qr_google_pay_data" value="">
                    <input type="hidden" name="processed_qr_phone_pay_data" id="processed_qr_phone_pay_data" value="">
                    <div class="paysection">
                        <!-- GPay Section -->
                        <div class="card">
                            <div class="logo-placeholder" id="gpayPreview" onclick="clickFocus3()">
                                <?php if(!empty($row['d_qr_google_pay'])): ?>
                                    <img id="showPreviewLogo3" src="data:image/*;base64,<?php echo base64_encode($row['d_qr_google_pay']); ?>" alt="Google Pay QR" style="max-width: 100%; max-height: 200px; border-radius: 8px;">
                                    <?php if(!empty($row['d_qr_google_pay'])): ?>
                                        <div class="delImg" onclick="event.stopPropagation(); removeData(<?php echo $row['id']; ?>,2)" title="Delete QR Code">
                                            <i class="fa fa-times"></i>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <img id="showPreviewLogo3" src="../../customer/assets/img/Gpay.png" class="img-fluid" style="display:none;">
                                    <small>ADD QR CODE</small>
                                <?php endif; ?>
                            </div>
                            <div class="file-info">File Supported - .png, .jpg, .jpeg, .gif, .webp      </div>
                            <div class="file-upload">
                                <span id="gpayFileName">No File Chosen</span>
                                <label for="clickMeImage3" class="choose-btn">Choose File</label>
                                <input type="file" name="d_qr_google_pay" id="clickMeImage3" onchange="readURL3(this);" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none;">
                            </div>
                            <div class="form-group">
                                <label for="d_google_pay" class="title">UPI Number (Gpay) (Optional)</label>
                                <input type="text" name="d_google_pay" id="d_google_pay" maxlength="20" class="form-control" placeholder="Enter Gpay Number" value="<?php echo !empty($row['d_google_pay']) ? htmlspecialchars($row['d_google_pay']) : ''; ?>">
                            </div>
                        </div>

                        <!-- Paytm Section -->
                        <div class="card">
                            <div class="logo-placeholder" id="paytmPreview" onclick="clickFocus10()">
                                <?php if(!empty($row['d_qr_paytm'])): ?>
                                    <img id="showPreviewLogo10" src="data:image/*;base64,<?php echo base64_encode($row['d_qr_paytm']); ?>" alt="Paytm QR" style="max-width: 100%; max-height: 200px; border-radius: 8px;">
                                    <?php if(!empty($row['d_qr_paytm'])): ?>
                                        <div class="delImg" onclick="event.stopPropagation(); removeData(<?php echo $row['id']; ?>,1)" title="Delete QR Code">
                                            <i class="fa fa-times"></i>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <img id="showPreviewLogo10" src="../../customer/assets/img/paytm.png" class="img-fluid" style="display:none;">
                                    <small>ADD QR CODE</small>
                                <?php endif; ?>
                            </div>
                            <div class="file-info">File Supported - .png, .jpg, .jpeg, .gif, .webp</div>
                            <div class="file-upload">
                                <span id="paytmFileName">No File Chosen</span>
                                <label for="clickMeImage10" class="choose-btn">Choose File</label>
                                <input type="file" name="d_qr_paytm" id="clickMeImage10" onchange="readURL10(this);" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none;">
                            </div>
                            <div class="form-group">
                                <label for="d_paytm" class="title">UPI Number (Paytm) (Optional)</label>
                                <input type="text" name="d_paytm" id="d_paytm" maxlength="20" class="form-control" placeholder="Enter Paytm Number" value="<?php echo !empty($row['d_paytm']) ? htmlspecialchars($row['d_paytm']) : ''; ?>">
                            </div>
                        </div>

                        <!-- PhonePe Section -->
                        <div class="card">
                            <div class="logo-placeholder" id="phonepePreview" onclick="clickFocus2()">
                                <?php if(!empty($row['d_qr_phone_pay'])): ?>
                                    <img id="showPreviewLogo2" src="data:image/*;base64,<?php echo base64_encode($row['d_qr_phone_pay']); ?>" alt="PhonePe QR" style="max-width: 100%; max-height: 200px; border-radius: 8px;">
                                    <?php if(!empty($row['d_qr_phone_pay'])): ?>
                                        <div class="delImg" onclick="event.stopPropagation(); removeData(<?php echo $row['id']; ?>,3)" title="Delete QR Code">
                                            <i class="fa fa-times"></i>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <img id="showPreviewLogo2" src="../../customer/assets/img/phonepe.png" class="img-fluid" style="display:none;">
                                    <small>ADD QR CODE</small>
                                <?php endif; ?>
                            </div>
                            <div class="file-info">File Supported - .png, .jpg, .jpeg, .gif, .webp</div>
                            <div class="file-upload">
                                <span id="phonepeFileName">No File Chosen</span>
                                <label for="clickMeImage2" class="choose-btn">Choose File</label>
                                <input type="file" name="d_qr_phone_pay" id="clickMeImage2" onchange="readURL2(this);" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none;">
                            </div>
                            <div class="form-group">
                                <label for="d_phone_pay" class="title">UPI Number (PhonePe) (Optional)</label>
                                <input type="text" name="d_phone_pay" id="d_phone_pay" maxlength="20" class="form-control" placeholder="Enter PhonePe Number" value="<?php echo !empty($row['d_phone_pay']) ? htmlspecialchars($row['d_phone_pay']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="BankDetails">
                        <label class="heading1">Bank Account Details:</label>
                        <div class="form-group">
                            <label for="d_bank_name">Bank Name (Optional)</label>
                            <input type="text" name="d_bank_name" id="d_bank_name" maxlength="100" class="form-control" placeholder="Enter Bank Name" value="<?php echo !empty($row['d_bank_name']) ? htmlspecialchars($row['d_bank_name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="d_ac_name">Account Holder Name (Optional)</label>
                            <input type="text" name="d_ac_name" id="d_ac_name" maxlength="100" class="form-control" placeholder="Account Holder Name" value="<?php echo !empty($row['d_ac_name']) ? htmlspecialchars($row['d_ac_name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="d_account_no">Bank Account Number (Optional)</label>
                            <input type="text" name="d_account_no" id="d_account_no" maxlength="100" class="form-control" placeholder="Enter Your Bank Account Number" value="<?php echo !empty($row['d_account_no']) ? htmlspecialchars($row['d_account_no']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="d_ifsc">Bank IFSC Code (Optional)</label>
                            <input type="text" name="d_ifsc" id="d_ifsc" maxlength="100" class="form-control" placeholder="Enter IFSC Code" value="<?php echo !empty($row['d_ifsc']) ? htmlspecialchars($row['d_ifsc']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="BankDetails">
                        <label class="heading2">GST Identification Number:</label>
                        <div class="form-group">
                            <label for="d_ac_type">GST Number (Optional)</label>
                            <input type="text" name="d_ac_type" id="d_ac_type" maxlength="100" class="form-control" placeholder="Enter GST Number" value="<?php echo !empty($row['d_ac_type']) ? htmlspecialchars($row['d_ac_type']) : ''; ?>">
                        </div>
                    </div>
                    <div class="Product-ServicesBtn" style="margin-top: 20px;">
                        <a href="social-links.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left">
                            <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                            <span>Back</span>
                        </a>
                        <button type="submit" name="process4" class="btn btn-primary align-center">
                            <img src="../../customer/assets/img/Save.png" class="img-fluid" width="35px" alt=""> 
                            <span>Save</span>
                        </button>
                        <a href="product-and-services.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right">
                            <span>Next</span>
                            <span class="right_angle angle"><i class="fa fa-angle-right"></i></span>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
// QR Code Preview Functions
function clickFocus10(){
    $('#clickMeImage10').click();
}

function readURL10(input){
    if(input.files && input.files[0]){
        var file = input.files[0];
        var allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
        var maxSize = 10 * 1024 * 1024; // 10MB (will be auto-optimized to 250KB)
        
        if(allowedTypes.indexOf(file.type) === -1){
            alert('Only JPG, PNG, GIF, and WEBP images are allowed.');
            $(input).val('');
            $('#paytmFileName').text('No File Chosen');
            $('#processed_qr_paytm_data').val('');
            return;
        }
        
        if(file.size > maxSize){
            alert('Image size must be 10MB or less. The image will be automatically optimized to 250KB.');
            $(input).val('');
            $('#paytmFileName').text('No File Chosen');
            $('#processed_qr_paytm_data').val('');
            return;
        }
        
        // Show loading indicator
        $('#showPreviewLogo10').hide();
        $('#paytmPreview small').html('<i class="fa fa-spinner fa-spin"></i> Processing...').show();
        
        // Immediately process the image via AJAX
        var formData = new FormData();
        formData.append('qr_image', file);
        formData.append('process_qr_ajax', '1');
        
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
                    $('#showPreviewLogo10').attr('src', processedImageSrc).show();
                    $('#paytmPreview small').hide();
                    
                    // Store processed image data for form submission
                    $('#processed_qr_paytm_data').val(response.image_data);
                    
                    // Truncate file name if too long
                    var fileName = file.name;
                    var maxLength = 25;
                    if(fileName.length > maxLength) {
                        var ext = fileName.substring(fileName.lastIndexOf('.'));
                        var nameWithoutExt = fileName.substring(0, fileName.lastIndexOf('.'));
                        fileName = nameWithoutExt.substring(0, maxLength - ext.length - 3) + '...' + ext;
                    }
                    $('#paytmFileName').text(fileName).attr('title', file.name);
                } else {
                    alert(response.message || 'Error processing image. Please try again.');
                    // Revert preview
                    $('#showPreviewLogo10').attr('src', '../../customer/assets/img/paytm.png').hide();
                    $('#paytmPreview small').text('ADD QR CODE').show();
                    $(input).val('');
                    $('#paytmFileName').text('No File Chosen');
                    $('#processed_qr_paytm_data').val('');
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
                $('#showPreviewLogo10').attr('src', '../assets/img/paytm.png').hide();
                $('#paytmPreview small').text('ADD QR CODE').show();
                $(input).val('');
                $('#paytmFileName').text('No File Chosen');
                $('#processed_qr_paytm_data').val('');
            }
        });
    }
}

function clickFocus3(){
    $('#clickMeImage3').click();
}

function readURL3(input){
    if(input.files && input.files[0]){
        var file = input.files[0];
        var allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
        var maxSize = 10 * 1024 * 1024; // 10MB (will be auto-optimized to 250KB)
        
        if(allowedTypes.indexOf(file.type) === -1){
            alert('Only JPG, PNG, GIF, and WEBP images are allowed.');
            $(input).val('');
            $('#gpayFileName').text('No File Chosen');
            $('#processed_qr_google_pay_data').val('');
            return;
        }
        
        if(file.size > maxSize){
            alert('Image size must be 10MB or less. The image will be automatically optimized to 250KB.');
            $(input).val('');
            $('#gpayFileName').text('No File Chosen');
            $('#processed_qr_google_pay_data').val('');
            return;
        }
        
        // Show loading indicator
        $('#showPreviewLogo3').hide();
        $('#gpayPreview small').html('<i class="fa fa-spinner fa-spin"></i> Processing...').show();
        
        // Immediately process the image via AJAX
        var formData = new FormData();
        formData.append('qr_image', file);
        formData.append('process_qr_ajax', '1');
        
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
                    $('#showPreviewLogo3').attr('src', processedImageSrc).show();
                    $('#gpayPreview small').hide();
                    
                    // Store processed image data for form submission
                    $('#processed_qr_google_pay_data').val(response.image_data);
                    
                    // Truncate file name if too long
                    var fileName = file.name;
                    var maxLength = 25;
                    if(fileName.length > maxLength) {
                        var ext = fileName.substring(fileName.lastIndexOf('.'));
                        var nameWithoutExt = fileName.substring(0, fileName.lastIndexOf('.'));
                        fileName = nameWithoutExt.substring(0, maxLength - ext.length - 3) + '...' + ext;
                    }
                    $('#gpayFileName').text(fileName).attr('title', file.name);
                } else {
                    alert(response.message || 'Error processing image. Please try again.');
                    // Revert preview
                    $('#showPreviewLogo3').attr('src', '../../customer/assets/img/Gpay.png').hide();
                    $('#gpayPreview small').text('ADD QR CODE').show();
                    $(input).val('');
                    $('#gpayFileName').text('No File Chosen');
                    $('#processed_qr_google_pay_data').val('');
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
                $('#showPreviewLogo3').attr('src', '../assets/img/Gpay.png').hide();
                $('#gpayPreview small').text('ADD QR CODE').show();
                $(input).val('');
                $('#gpayFileName').text('No File Chosen');
                $('#processed_qr_google_pay_data').val('');
            }
        });
    }
}

function clickFocus2(){
    $('#clickMeImage2').click();
}

function readURL2(input){
    if(input.files && input.files[0]){
        var file = input.files[0];
        var allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
        var maxSize = 10 * 1024 * 1024; // 10MB (will be auto-optimized to 250KB)
        
        if(allowedTypes.indexOf(file.type) === -1){
            alert('Only JPG, PNG, GIF, and WEBP images are allowed.');
            $(input).val('');
            $('#phonepeFileName').text('No File Chosen');
            $('#processed_qr_phone_pay_data').val('');
            return;
        }
        
        if(file.size > maxSize){
            alert('Image size must be 10MB or less. The image will be automatically optimized to 250KB.');
            $(input).val('');
            $('#phonepeFileName').text('No File Chosen');
            $('#processed_qr_phone_pay_data').val('');
            return;
        }
        
        // Show loading indicator
        $('#showPreviewLogo2').hide();
        $('#phonepePreview small').html('<i class="fa fa-spinner fa-spin"></i> Processing...').show();
        
        // Immediately process the image via AJAX
        var formData = new FormData();
        formData.append('qr_image', file);
        formData.append('process_qr_ajax', '1');
        
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
                    $('#showPreviewLogo2').attr('src', processedImageSrc).show();
                    $('#phonepePreview small').hide();
                    
                    // Store processed image data for form submission
                    $('#processed_qr_phone_pay_data').val(response.image_data);
                    
                    // Truncate file name if too long
                    var fileName = file.name;
                    var maxLength = 25;
                    if(fileName.length > maxLength) {
                        var ext = fileName.substring(fileName.lastIndexOf('.'));
                        var nameWithoutExt = fileName.substring(0, fileName.lastIndexOf('.'));
                        fileName = nameWithoutExt.substring(0, maxLength - ext.length - 3) + '...' + ext;
                    }
                    $('#phonepeFileName').text(fileName).attr('title', file.name);
                } else {
                    alert(response.message || 'Error processing image. Please try again.');
                    // Revert preview
                    $('#showPreviewLogo2').attr('src', '../../customer/assets/img/phonepe.png').hide();
                    $('#phonepePreview small').text('ADD QR CODE').show();
                    $(input).val('');
                    $('#phonepeFileName').text('No File Chosen');
                    $('#processed_qr_phone_pay_data').val('');
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
                $('#showPreviewLogo2').attr('src', '../assets/img/phonepe.png').hide();
                $('#phonepePreview small').text('ADD QR CODE').show();
                $(input).val('');
                $('#phonepeFileName').text('No File Chosen');
                $('#processed_qr_phone_pay_data').val('');
            }
        });
    }
}

// Handle file input change to show filename
$(document).ready(function(){
    // Helper function to truncate file name
    function truncateFileName(fileName, maxLength) {
        if(fileName.length <= maxLength) {
            return fileName;
        }
        var ext = fileName.substring(fileName.lastIndexOf('.'));
        var nameWithoutExt = fileName.substring(0, fileName.lastIndexOf('.'));
        return nameWithoutExt.substring(0, maxLength - ext.length - 3) + '...' + ext;
    }
    
    $('#clickMeImage10').on('change', function(){
        if(this.files && this.files[0]) {
            var fileName = truncateFileName(this.files[0].name, 25);
            $('#paytmFileName').text(fileName).attr('title', this.files[0].name);
        } else {
            $('#paytmFileName').text('No File Chosen').removeAttr('title');
        }
    });
    
    $('#clickMeImage3').on('change', function(){
        if(this.files && this.files[0]) {
            var fileName = truncateFileName(this.files[0].name, 25);
            $('#gpayFileName').text(fileName).attr('title', this.files[0].name);
        } else {
            $('#gpayFileName').text('No File Chosen').removeAttr('title');
        }
    });
    
    $('#clickMeImage2').on('change', function(){
        if(this.files && this.files[0]) {
            var fileName = truncateFileName(this.files[0].name, 25);
            $('#phonepeFileName').text(fileName).attr('title', this.files[0].name);
        } else {
            $('#phonepeFileName').text('No File Chosen').removeAttr('title');
        }
    });
});

// QR Code Deletion Function
function removeData(qr_id, qr_num){
    if(!confirm('Are you sure you want to delete this QR code?')){
        return;
    }
    
    $('#status_remove_img').css('color','blue');
    
    $.ajax({
        url:'../../panel/login/js_request.php',
        method:'POST',
        data:{qr_id:qr_id, qr_num:qr_num},
        dataType:'text',
        success:function(data){
            $('#status_remove_img').html(data);
            // Update the image source to default after successful deletion
            if(data.includes('success')){
                if(qr_num == 1) {
                    $('#showPreviewLogo10').attr('src', '../../customer/assets/img/paytm.png').hide();
                    $('#paytmPreview small').show();
                } else if(qr_num == 2) {
                    $('#showPreviewLogo3').attr('src', '../../customer/assets/img/Gpay.png').hide();
                    $('#gpayPreview small').show();
                } else if(qr_num == 3) {
                    $('#showPreviewLogo2').attr('src', '../../customer/assets/img/phonepe.png').hide();
                    $('#phonepePreview small').show();
                }
                setTimeout(function(){
                    location.reload();
                }, 1000);
            }
        },
        error: function(){
            $('#status_remove_img').html('<div class="alert alert-danger">Error deleting QR code. Please try again.</div>');
        }
    });
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
    margin: auto !important;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    }
    .Dashboard .heading{
        font-size:28px !important;
        margin-bottom:5px;
        margin-left: 27px;
        position:relative;
    }
    .Dashboard small{
        display:block;
        margin-bottom:20px;
        font-size:14px;
        margin-left: 27px;
        margin-top:10px;
    }
    .headingTop{
        font-size:28px;
        font-weight:500px;
        line-height:67px;
        padding-left:5px;
    }
.paysection{
    gap:34px;
}
     .heading:after
    {
        content: '';
        width: 390px;
        height: 1px;
        background: #ffb300;
        position: absolute;
        left: 8px;
        bottom: 0px;
    }
    .heading1{
        position:relative;
        margin-top:20px !important;
        margin-bottom:20px !important;
        font-size:28px !important;
    }
    .BankDetails{
        padding:25px;
    }
    .BankDetails label:not(.heading1){
        font-size:23px;
        margin:0px;
    }
    .BankDetails label:not(.heading2){
        font-size:23px;
        margin:0px;
    }
    .BankDetails input{
        height:45px;
    }
    .heading1:after
    {
        content: '';
        width: 216px;
        height: 1px;
        background: #ffb300;
        position: absolute;
        left: 8px;
        bottom: 0px;
    }
    .heading2{
        position: relative;
        margin-bottom:20px !important;
        font-size:28px !important;
    }
    .heading2:after
    {
        content: '';
        width: 240px;
        height: 1px;
        background: #ffb300;
        position: absolute;
        left: 8px;
        bottom: 0px;
    }
    .paysection .choose-btn{
        font-size: 17px !important;
        margin-bottom: 0px;
    }
    .paysection .choose-btn:hover{
        font-size: 17px;
        margin-bottom: 0px;
    }
    .paysection .file-upload{
        padding:2px;
        padding-left:10px;
    }
    .paysection .file-upload span{
        font-size:16px;
        display: inline-block;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle;
    }
    .paysection .file-info {
        font-size: 14px;
        color: #555;
        margin-bottom: 10px;
        line-height: 20px;
   }

    .paysection .title{
        font-size: 19px !important;
        font-weight: 500 !important;
        margin-bottom: 5px;
    }

.paysection .card .form-group input{
    height:45px;

}
.BankDetails input:focus{
    outline:none;
    box-shadow:none;
    
}
.paysection .card{
    width:348px;
}
.text-center button{
        width:120px !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center;
        gap:10px !important;
        margin: auto !important;
    }
    @media screen and (max-width: 768px) {
        .card-body form {
    padding: 0px 15px;
}
.card-body {
    padding: 20px !important;
    padding-bottom: 100px !important;
}
.Dashboard .heading {
    font-size: 22px !important;
    margin-bottom: 5px;
    margin-left:0px; 
    position: relative;
}
.submitBtnSection{
        margin-top:45px;
    }
    .heading:after {
    content: '';
    width: 195px;
    height: 1px;
    background: #ffb300;
    position: absolute;
    left: 8px;
    bottom: 0px;
}
.Dashboard small {
    display: block;
    margin-bottom: 20px;
    font-size: 13px;
    margin-left: 5px;
    margin-top: 10px;
}
.BankDetails {
    padding: 7px;
}
.heading1 {
    position: relative;
    margin-top: 40px !important;
    margin-bottom: 20px !important;
    font-size: 26px !important;
}
.BankDetails label:not(.heading2) {
    font-size: 21px;
    margin: 0px;
}
.BankDetails input {
    height: 45px;
    font-size:2rem;
}
.paysection .form-control{
    font-size:2rem;
}
.paysection .choose-btn {
    font-size: 14px !important;
    margin-bottom: 0px;
}
.heading2 {
    position: relative;
    margin-bottom: 20px !important;
    font-size: 26px !important;
    margin-top: 20px !important;
}
.heading2:after {
    content: '';
    width: 152px;
    
}
.text-center button {
    width: 140px !important;
    display: flex !important
;
    justify-content: center !important;
    align-items: center;
    gap: 10px !important;
    margin: auto !important;
    padding:0px;
}
.submitBtnSection{
    margin-top:20px;
}
    }

    
    .Product-ServicesBtn{
        padding: 0px 28px;
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
    
    @media screen and (max-width: 768px) {
        .Product-ServicesBtn {
            padding: 0px 10px !important;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px !important;
            width: 100% !important;
        }
        .Product-ServicesBtn button,
        .Product-ServicesBtn a {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 12px 20px !important;
            font-size: 16px !important;
            justify-content: center;
        }
        .Product-ServicesBtn .align-left,
        .Product-ServicesBtn .align-center,
        .Product-ServicesBtn .align-right {
            width: 100% !important;
        }
        .Product-ServicesBtn button span:not(.angle),
        .Product-ServicesBtn a span:not(.angle) {
            font-size: 16px !important;
        }
        .Product-ServicesBtn .align-center img {
            width: 20px;
        }
    }
    
    /* Remove Icon Styling */
    .logo-placeholder {
        position: relative;
    }
    
    .delImg {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 32px;
        height: 32px;
        background: rgba(220, 53, 69, 0.9);
        color: white;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        z-index: 10;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }
    
    .delImg:hover {
        background: rgba(220, 53, 69, 1);
        transform: scale(1.1);
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
    }
    
    .delImg i {
        font-size: 14px;
        font-weight: bold;
    }
    
    /* File name tooltip on hover */
    .paysection .file-upload span[title]:hover {
        cursor: help;
    }
</style>

<?php include '../footer.php'; ?>
