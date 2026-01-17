<?php
// Handle card_number from URL, session, or cookie
// MUST be done before any output (before including header.php)
require_once('../../common/config.php');

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
        
        // Process logo upload if file is selected
        // Check if we have processed image data from AJAX (base64)
        if(!empty($_POST['processed_logo_data'])){
            // Use the processed image data from AJAX
            $logoData = base64_decode($_POST['processed_logo_data']);
            $logo = addslashes($logoData);
            
            // Update database with processed image
            $updateLogo = mysqli_query($connect, 'UPDATE digi_card SET d_logo="'.$logo.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
            
            // Also save to file system
            $filename2 = '../../favicons/'.date('ymdsih').'_logo.jpg';
            if(file_put_contents($filename2, $logoData)) {
                $update = mysqli_query($connect, 'UPDATE digi_card SET d_logo_location="'.$filename2.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
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
                    $filename2 = '../../favicons/'.date('ymdsih').'_'.basename($_FILES['d_logo']['name']);
                    // Change extension to .jpg since output is always JPEG
                    $filename2 = preg_replace('/\.[^.]+$/', '.jpg', $filename2);
                    
                    // Copy the processed file to favicons directory
                    if($result['file_path'] && file_exists($result['file_path'])) {
                        if(copy($result['file_path'], $filename2)) {
                            $update = mysqli_query($connect, 'UPDATE digi_card SET d_logo_location="'.$filename2.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
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
                        
                        $filename2 = '../../favicons/'.date('ymdsih').$_FILES['d_logo']['name'];
                        if(copy($_FILES['d_logo']['tmp_name'], $filename2)) {
                            $update = mysqli_query($connect, 'UPDATE digi_card SET d_logo_location="'.$filename2.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
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
                            $filename2 = '../../favicons/'.date('ymdsih').$_FILES['d_logo']['name'];
                            
                            if(move_uploaded_file($compressimage, $filename2)) {
                                $update = mysqli_query($connect, 'UPDATE digi_card SET d_logo_location="'.$filename2.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
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
        
        // Escape special characters for certain fields
        $d_about_us = str_replace(array("'",'"',';','(',')','"','"',':','%','`','[',']'), array("\'",'\"','\;','\(','\)','\"','\"','\:','\%','\`','\[','\]'), $_POST['d_about_us']);
        $d_address = str_replace(array("'",'"',';','(',')','"','"',':','%','`','[',']'), array("\'",'\"','\;','\(','\)','\"','\"','\:','\%','\`','\[','\]'), $_POST['d_address']);
        $d_position = str_replace(array("'",'"',';','(',')','"','"',':','%','`','[',']'), array("\'",'\"','\;','\(','\)','\"','\"','\:','\%','\`','\[','\]'), $_POST['d_position']);
        $d_comp_est_date = str_replace(array("'",'"',';','(',')','"','"',':','%','`','[',']'), array("\'",'\"','\;','\(','\)','\"','\"','\:','\%','\`','\[','\]'), $_POST['d_comp_est_date']);
        
        $update = mysqli_query($connect, 'UPDATE digi_card SET 
        d_f_name="'.mysqli_real_escape_string($connect, $_POST['d_f_name']).'",
        d_l_name="'.mysqli_real_escape_string($connect, $_POST['d_l_name']).'",
        d_position="'.$d_position.'",
        d_contact="'.mysqli_real_escape_string($connect, $_POST['d_contact']).'",
        d_contact2="'.mysqli_real_escape_string($connect, $_POST['d_contact2']).'",
        d_whatsapp="'.mysqli_real_escape_string($connect, $_POST['d_whatsapp']).'",
        d_address="'.$d_address.'",
        d_email="'.mysqli_real_escape_string($connect, $_POST['d_email']).'",
        d_website="'.mysqli_real_escape_string($connect, $_POST['d_website']).'",
        d_location="'.mysqli_real_escape_string($connect, $_POST['d_location']).'",
        d_comp_est_date="'.$d_comp_est_date.'",
        d_about_us="'.$d_about_us.'"
        WHERE id="'.$_SESSION['card_id_inprocess'].'"');
        
        if($update){
            $_SESSION['save_success'] = "Details Updated Successfully!";
            header('Location: company-details.php?card_number='.$_SESSION['card_id_inprocess']);
            exit;
        } else {
            $_SESSION['save_error'] = "Error! Try Again.";
            header('Location: company-details.php?card_number='.$_SESSION['card_id_inprocess']);
            exit;
        }
    } else {
        $_SESSION['save_error'] = "Detail Not Available. Try Again.";
        header('Location: company-details.php?card_number='.$_SESSION['card_id_inprocess']);
        exit;
    }
}

include 'header.php';
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

        <div class="card mb-4">
            <div class="card-body">
                <label class="heading heading1">Company Details:</label>
                <form action="" method="POST" enctype="multipart/form-data" id="card_form">
                    <!-- Hidden field to store processed image data -->
                    <input type="hidden" name="processed_logo_data" id="processed_logo_data" value="">
                    <div class="upload-container">
                        <div class="logo-placeholder" id="logoPreview" onclick="clickFocus()">
                            <?php if(!empty($row['d_logo'])): ?>
                                <img id="showPreviewLogo" src="data:image/*;base64,<?php echo base64_encode($row['d_logo']); ?>" alt="Logo Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
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
                            <input type="file" name="d_logo" id="clickMeImage" onchange="readURL(this);" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none;">
                        </div>
                    </div>
                    <div class="Personal-Details">
                        <label class="heading">Personal Details:</label>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_f_name">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="d_f_name" id="d_f_name" maxlength="20" placeholder="Enter First Name" class="form-control" value="<?php echo !empty($row['d_f_name']) ? htmlspecialchars($row['d_f_name']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_l_name">Last Name (Optional)</label>
                                    <input type="text" name="d_l_name" id="d_l_name" maxlength="20" placeholder="Enter Last Name (Optional)" class="form-control" value="<?php echo !empty($row['d_l_name']) ? htmlspecialchars($row['d_l_name']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_position">Position/Business Category <span class="text-danger">*</span></label>
                                    <input type="text" name="d_position" id="d_position" maxlength="20" placeholder="Enter Position/Business Category (Ex. Manager etc.)" class="form-control" value="<?php echo !empty($row['d_position']) ? htmlspecialchars($row['d_position']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_contact">Phone No <span class="text-danger">*</span></label>
                                    <input type="text" name="d_contact" id="d_contact" maxlength="13" placeholder="Enter Phone Number" class="form-control" value="<?php echo !empty($row['d_contact']) ? htmlspecialchars($row['d_contact']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_contact2">Alternate Phone No (Optional)</label>
                                    <input type="text" name="d_contact2" id="d_contact2" maxlength="13" placeholder="Enter Alternate Phone Number (Optional)" class="form-control" value="<?php echo !empty($row['d_contact2']) ? htmlspecialchars($row['d_contact2']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_whatsapp">WhatsApp No <span class="text-danger">*</span></label>
                                    <input type="text" name="d_whatsapp" id="d_whatsapp" maxlength="13" placeholder="Enter WhatsApp Number" class="form-control" value="<?php echo !empty($row['d_whatsapp']) ? htmlspecialchars($row['d_whatsapp']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_email">Email Id <span class="text-danger">*</span></label>
                                    <input type="email" name="d_email" id="d_email" maxlength="100" placeholder="Enter Email Id" class="form-control" value="<?php echo !empty($row['d_email']) ? htmlspecialchars($row['d_email']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_website">Website (Optional)</label>
                                    <input type="text" name="d_website" id="d_website" maxlength="200" placeholder="Website (Optional)" class="form-control" value="<?php echo !empty($row['d_website']) ? htmlspecialchars($row['d_website']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_location">Location (Optional)</label>
                                    <input type="text" name="d_location" id="d_location" maxlength="999" placeholder="Your Business Location (Optional)" class="form-control" value="<?php echo !empty($row['d_location']) ? htmlspecialchars($row['d_location']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="d_comp_est_date">Company Est Date <span class="text-danger">*</span></label>
                                    <input type="text" name="d_comp_est_date" id="d_comp_est_date" maxlength="200" placeholder="When your comp. was started?" class="form-control" value="<?php echo !empty($row['d_comp_est_date']) ? htmlspecialchars($row['d_comp_est_date']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="form-group">
                                    <label for="d_address">Address <span class="text-danger">*</span></label>
                                    <textarea name="d_address" id="d_address" maxlength="500" placeholder="Full Address" class="form-control" required><?php echo !empty($row['d_address']) ? htmlspecialchars($row['d_address']) : ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="form-group">
                                    <label for="d_about_us">About Us <span class="text-danger">*</span></label>
                                    <textarea name="d_about_us" id="d_about_us" maxlength="1900" placeholder="About your company/business" class="form-control" required><?php echo !empty($row['d_about_us']) ? htmlspecialchars($row['d_about_us']) : ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="Product-ServicesBtn" style="margin-top: 20px;">
                            <a href="select-theme.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left">
                                <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                                <span>Back</span>
                            </a>
                            <button type="submit" name="process2" class="btn btn-primary align-center">
                                <img src="../../customer/assets/img/Save.png" class="img-fluid" width="35px" alt=""> 
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
// Preserve the current preview so we can revert on invalid selection
var originalLogoSrc = null;
$(document).ready(function(){
    originalLogoSrc = $('#showPreviewLogo').attr('src');
    if(!originalLogoSrc) {
        originalLogoSrc = '';
    }
    
    // Helper function to truncate file name
    function truncateFileName(fileName, maxLength) {
        if(fileName.length <= maxLength) {
            return fileName;
        }
        var ext = fileName.substring(fileName.lastIndexOf('.'));
        var nameWithoutExt = fileName.substring(0, fileName.lastIndexOf('.'));
        return nameWithoutExt.substring(0, maxLength - ext.length - 3) + '...' + ext;
    }
    
    // Handle file input change to show filename
    $('#clickMeImage').on('change', function(){
        if(this.files && this.files[0]) {
            var fileName = truncateFileName(this.files[0].name, 25);
            $('#fileName').text(fileName).attr('title', this.files[0].name);
        } else {
            $('#fileName').text('No File Chosen').removeAttr('title');
        }
    });
});

function clickFocus(){
    $('#clickMeImage').click();
}

// Store processed image data for form submission
var processedImageData = null;

function readURL(input){
    // Validate file before previewing
    if(input.files && input.files[0]){
        var file = input.files[0];
        var allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
        var maxSize = 10 * 1024 * 1024; // 10MB (will be auto-optimized to 250KB)
        
        // Type validation
        if(allowedTypes.indexOf(file.type) === -1){
            alert('Only JPG, PNG, GIF, and WEBP images are allowed.');
            // Revert preview and clear selection
            if(originalLogoSrc) {
                $('#showPreviewLogo').attr('src', originalLogoSrc).show();
            } else {
                $('#showPreviewLogo').hide();
                $('#logoPreview span').show();
            }
            $(input).val('');
            $('#fileName').text('No File Chosen').removeAttr('title');
            processedImageData = null;
            return;
        }
        
        // Size validation (10MB max - will be auto-optimized to 250KB)
        if(file.size > maxSize){
            alert('Image size must be 10MB or less. The image will be automatically optimized to 250KB.');
            // Revert preview and clear selection
            if(originalLogoSrc) {
                $('#showPreviewLogo').attr('src', originalLogoSrc).show();
            } else {
                $('#showPreviewLogo').hide();
                $('#logoPreview span').show();
            }
            $(input).val('');
            $('#fileName').text('No File Chosen').removeAttr('title');
            processedImageData = null;
            return;
        }
        
        // Show loading indicator
        $('#showPreviewLogo').hide();
        $('#logoPreview span').html('<i class="fa fa-spinner fa-spin"></i> Processing...').show();
        
        // Immediately process the image via AJAX (like test page)
        var formData = new FormData();
        formData.append('d_logo', file);
        formData.append('process_logo_ajax', '1');
        
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
                    $('#showPreviewLogo').attr('src', processedImageSrc).css({
                        'width': '100%',
                        'height': '100%',
                        'object-fit': 'cover',
                        'border-radius': '8px',
                        'display': 'block'
                    }).show();
                    $('#logoPreview span').hide();
                    
                    // Store processed image data for form submission
                    processedImageData = response.image_data;
                    $('#processed_logo_data').val(response.image_data);
                    
                    // Update original src
                    originalLogoSrc = processedImageSrc;
                    
                    // Truncate file name if too long
                    var fileName = file.name;
                    var maxLength = 25;
                    if(fileName.length > maxLength) {
                        var ext = fileName.substring(fileName.lastIndexOf('.'));
                        var nameWithoutExt = fileName.substring(0, fileName.lastIndexOf('.'));
                        fileName = nameWithoutExt.substring(0, maxLength - ext.length - 3) + '...' + ext;
                    }
                    $('#fileName').text(fileName).attr('title', file.name);
                } else {
                    // Error processing
                    alert(response.message || 'Error processing image. Please try again.');
                    // Revert preview
                    if(originalLogoSrc) {
                        $('#showPreviewLogo').attr('src', originalLogoSrc).show();
                    } else {
                        $('#showPreviewLogo').hide();
                        $('#logoPreview span').text('YOUR LOGO').show();
                    }
                    $(input).val('');
                    $('#fileName').text('No File Chosen').removeAttr('title');
                    processedImageData = null;
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    statusCode: xhr.status,
                    responseText: xhr.responseText ? xhr.responseText.substring(0, 500) : 'No response'
                });
                
                // Try to parse error response
                var errorMsg = 'Error processing image. Please try again.';
                try {
                    if(xhr.responseText) {
                        var errorResponse = JSON.parse(xhr.responseText);
                        if(errorResponse && errorResponse.message) {
                            errorMsg = errorResponse.message;
                        }
                    }
                } catch(e) {
                    // Not JSON, use default message
                }
                
                alert(errorMsg);
                // Revert preview
                if(originalLogoSrc) {
                    $('#showPreviewLogo').attr('src', originalLogoSrc).show();
                } else {
                    $('#showPreviewLogo').hide();
                    $('#logoPreview span').text('YOUR LOGO').show();
                }
                $(input).val('');
                $('#fileName').text('No File Chosen').removeAttr('title');
                processedImageData = null;
                $('#processed_logo_data').val('');
            }
        });
    }
}
</script>

<style>
        footer{
        margin-bottom:54px;
    }
    .Dashboard .heading{
        font-size:28px !important;
        
    }
    .upload-container .file-info{
        font-size: 21px;
    
    color: #3e3e3e;
    margin-bottom: 10px;
    text-align: center;
    padding-top: 10px;
    line-height: 26px;
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
        font-weight:500;
        font-size:22px;
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
        font-size:23px;
        font-weight:500;
    }
    .heading1{
        padding-left:50px;
        position:relative;
        margin-top:20px;
    }
    #fileName{
        padding-left: 20px;
        font-size: 20px !important;
        display: inline-block;
        max-width: 250px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle;
    }
    
    #fileName[title]:hover {
        cursor: help;
    }
    .savebutton{
        padding:5px 20px;
    }
    .logo-placeholder{
        width:200px;
        height:200px;
        border:2px solid darkgray;
        border-radius: 8px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    .logo-placeholder img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
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
        height:8vh;
        padding-right:5px;
    }
    .file-upload .choose-btn {
    font-size: 27px !important;
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
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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
    min-height: 55px !important;
    font-weight: 500;
    font-size: 20px !important;
    padding: 12px 15px !important;
}
.Personal-Details .form-group input[type="text"],
.Personal-Details .form-group input[type="email"] {
    min-height: 55px !important;
    font-size: 20px !important;
    padding: 12px 15px !important;
}
.Personal-Details .form-group textarea {
    min-height: 80px !important;
    font-size: 20px !important;
    padding: 12px 15px !important;
}
.Personal-Details .form-group label {
    margin-bottom: 8px !important;
    font-size: 22px !important;
    font-weight: 500;
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
        .card-body {
            padding: 20px 15px !important;
            padding-bottom: 100px !important;
        }
    }
</style>

<?php include '../footer.php'; ?>
