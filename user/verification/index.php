<?php
// Start session and include database connection first
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/access_control.php');

// Check page access - redirects to dashboard if unauthorized
require_page_access('/verification');

// Now include the header
include_once('../includes/header.php');

// Get user's verification status and documents
$verification_status = 'pending';
$pan_card_document = '';
$aadhaar_front_document = '';
$aadhaar_back_document = '';
$is_locked = false;
$admin_remarks = '';
$reviewed_at = '';
$reviewed_by = '';

if(isset($_SESSION['f_user_email'])) {
    try {
        $stmt = $connect->prepare("SELECT * FROM franchisee_verification WHERE user_email = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("s", $_SESSION['f_user_email']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result && $row = $result->fetch_assoc()) {
            $verification_status = $row['status'];
            $pan_card_document = $row['pan_card_document'] ?? '';
            $aadhaar_front_document = $row['aadhaar_front_document'] ?? '';
            $aadhaar_back_document = $row['aadhaar_back_document'] ?? '';
            $admin_remarks = $row['admin_remarks'] ?? '';
            $reviewed_at = $row['reviewed_at'] ?? '';
            $reviewed_by = $row['reviewed_by'] ?? '';
            // Lock only when approved, or when submitted with all three documents (so user can still add missing docs)
            $all_docs = !empty($pan_card_document) && !empty($aadhaar_front_document) && !empty($aadhaar_back_document);
            $is_locked = ($row['status'] == 'approved') || ($row['status'] == 'submitted' && $all_docs);
            if($row['status'] == 'rejected') {
                $is_locked = false;
            }
        }
        $stmt->close();
    } catch(Exception $e) {
        error_log("Error fetching verification data: " . $e->getMessage());
    }
}

// Include file validation functions early
$file_validation_path = __DIR__ . '/../../includes/file_validation.php';
if(file_exists($file_validation_path)) {
    require_once $file_validation_path;
} elseif(file_exists('../../includes/file_validation.php')) {
    require_once '../../includes/file_validation.php';
}

// Handle AJAX image processing - same as company-details.php
if(isset($_POST['process_pan_ajax']) && !empty($_FILES['pan_card_document']['tmp_name'])){
    ob_start();
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    try {
        if(!function_exists('processImageUploadWithAutoCrop')) {
            throw new Exception('Image processing function not available');
        }
        
        $result = processImageUploadWithAutoCrop(
            $_FILES['pan_card_document'], 
            600,
            250000,
            200000,
            300000,
            ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
            'jpeg',
            null
        );
        
        if($result['status']) {
            $base64Image = base64_encode($result['data']);
            
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => true,
                'image_data' => $base64Image,
                'message' => 'Document processed successfully'
            ]);
            
            if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                @unlink($result['file_path']);
            }
        } else {
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => false,
                'message' => isset($result['message']) ? $result['message'] : 'Error processing image'
            ]);
        }
    } catch(Exception $e) {
        while(ob_get_level() > 0) {
            ob_end_clean();
        }
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    exit;
}

if(isset($_POST['process_aadhaar_front_ajax']) && !empty($_FILES['aadhaar_front_document']['tmp_name'])){
    ob_start();
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    try {
        if(!function_exists('processImageUploadWithAutoCrop')) {
            throw new Exception('Image processing function not available');
        }
        
        $result = processImageUploadWithAutoCrop(
            $_FILES['aadhaar_front_document'], 
            600,
            250000,
            200000,
            300000,
            ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
            'jpeg',
            null
        );
        
        if($result['status']) {
            $base64Image = base64_encode($result['data']);
            
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => true,
                'image_data' => $base64Image,
                'message' => 'Document processed successfully'
            ]);
            
            if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                @unlink($result['file_path']);
            }
        } else {
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => false,
                'message' => isset($result['message']) ? $result['message'] : 'Error processing image'
            ]);
        }
    } catch(Exception $e) {
        while(ob_get_level() > 0) {
            ob_end_clean();
        }
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    exit;
}

if(isset($_POST['process_aadhaar_back_ajax']) && !empty($_FILES['aadhaar_back_document']['tmp_name'])){
    ob_start();
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    try {
        if(!function_exists('processImageUploadWithAutoCrop')) {
            throw new Exception('Image processing function not available');
        }
        
        $result = processImageUploadWithAutoCrop(
            $_FILES['aadhaar_back_document'], 
            600,
            250000,
            200000,
            300000,
            ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
            'jpeg',
            null
        );
        
        if($result['status']) {
            $base64Image = base64_encode($result['data']);
            
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => true,
                'image_data' => $base64Image,
                'message' => 'Document processed successfully'
            ]);
            
            if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                @unlink($result['file_path']);
            }
        } else {
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => false,
                'message' => isset($result['message']) ? $result['message'] : 'Error processing image'
            ]);
        }
    } catch(Exception $e) {
        while(ob_get_level() > 0) {
            ob_end_clean();
        }
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    exit;
}

// Handle form submission - save images to folder and store only filename in DB
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_documents'])){
    $upload_dir = __DIR__ . '/../../assets/upload/verification/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0775, true);
    }
    
    $existing_pan = $pan_card_document;
    $existing_aadhaar_f = $aadhaar_front_document;
    $existing_aadhaar_b = $aadhaar_back_document;
    
    $pan_card_filename = '';
    $aadhaar_front_filename = '';
    $aadhaar_back_filename = '';
    
    // Process PAN Card
    if(!empty($_POST['processed_pan_data'])){
        $panData = base64_decode($_POST['processed_pan_data']);
        $fileNameOnly = date('ymdsih') . '_pan.jpg';
        $filePathAbs = $upload_dir . $fileNameOnly;
        if(file_put_contents($filePathAbs, $panData) !== false) {
            $pan_card_filename = $fileNameOnly;
        }
    }
    
    // Process Aadhaar Front
    if(!empty($_POST['processed_aadhaar_front_data'])){
        $aadhaarFrontData = base64_decode($_POST['processed_aadhaar_front_data']);
        $fileNameOnly = date('ymdsih') . '_aadhaar_front.jpg';
        $filePathAbs = $upload_dir . $fileNameOnly;
        if(file_put_contents($filePathAbs, $aadhaarFrontData) !== false) {
            $aadhaar_front_filename = $fileNameOnly;
        }
    }
    
    // Process Aadhaar Back
    if(!empty($_POST['processed_aadhaar_back_data'])){
        $aadhaarBackData = base64_decode($_POST['processed_aadhaar_back_data']);
        $fileNameOnly = date('ymdsih') . '_aadhaar_back.jpg';
        $filePathAbs = $upload_dir . $fileNameOnly;
        if(file_put_contents($filePathAbs, $aadhaarBackData) !== false) {
            $aadhaar_back_filename = $fileNameOnly;
        }
    }
    
    // Save to database - only filename, not full path
    $pan_final = !empty($pan_card_filename) ? $pan_card_filename : $existing_pan;
    $aadhaar_f_final = !empty($aadhaar_front_filename) ? $aadhaar_front_filename : $existing_aadhaar_f;
    $aadhaar_b_final = !empty($aadhaar_back_filename) ? $aadhaar_back_filename : $existing_aadhaar_b;
    $all_three_present = (!empty($pan_final) && !empty($aadhaar_f_final) && !empty($aadhaar_b_final));
    
    try {
        // Check if we already have a row for this user
        $stmt = $connect->prepare("SELECT id FROM franchisee_verification WHERE user_email = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("s", $_SESSION['f_user_email']);
        $stmt->execute();
        $res = $stmt->get_result();
        $row_exists = ($res && $res->num_rows > 0);
        $stmt->close();
        
        if ($row_exists) {
            if ($all_three_present) {
                $stmt = $connect->prepare("UPDATE franchisee_verification SET pan_card_document = ?, aadhaar_front_document = ?, aadhaar_back_document = ?, status = 'submitted', submitted_at = NOW() WHERE user_email = ?");
                $stmt->bind_param("ssss", $pan_final, $aadhaar_f_final, $aadhaar_b_final, $_SESSION['f_user_email']);
            } else {
                $stmt = $connect->prepare("UPDATE franchisee_verification SET pan_card_document = ?, aadhaar_front_document = ?, aadhaar_back_document = ? WHERE user_email = ?");
                $stmt->bind_param("ssss", $pan_final, $aadhaar_f_final, $aadhaar_b_final, $_SESSION['f_user_email']);
            }
            $stmt->execute();
            $stmt->close();
        } else {
            $status = $all_three_present ? 'submitted' : 'submitted';
            $stmt = $connect->prepare("INSERT INTO franchisee_verification (user_email, pan_card_document, aadhaar_front_document, aadhaar_back_document, status, submitted_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssss", $_SESSION['f_user_email'], $pan_final, $aadhaar_f_final, $aadhaar_b_final, $status);
            $stmt->execute();
            $stmt->close();
        }
        
        if ($all_three_present) {
            try {
                require_once('../../admin/includes/notification_helper.php');
                createNotification('verification', "New Franchisee Documents Submitted", "Franchisee " . $_SESSION['f_user_email'] . " has submitted new documents for verification.", $_SESSION['f_user_email'], 'franchisee', null, 'high');
            } catch(Exception $e) {
                error_log("Error creating notification: " . $e->getMessage());
            }
        }
        
        // Update local variables
        $pan_card_document = $pan_final;
        $aadhaar_front_document = $aadhaar_f_final;
        $aadhaar_back_document = $aadhaar_b_final;
        $verification_status = $all_three_present ? 'submitted' : 'submitted';
        $is_locked = ($verification_status == 'approved') || ($verification_status == 'submitted' && $all_three_present);
        
        $success_message = $all_three_present
            ? "Documents submitted successfully! Our team will verify them within 48 hours."
            : "Document(s) saved. Please upload the remaining document(s) and submit when all three are ready.";
    } catch(Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}
?>
<main class="Dashboard">
<div class="container-fluid customer_content_area">

    
    <div class="main-top">
    <span class="heading">Franchise Verification</span>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                      <li class="breadcrumb-item"><a href="#">Mini Website </a></li>
                      <li class="breadcrumb-item active" aria-current="page">Verification</li>
                    </ol>
                </nav>                              
            </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
               
                <div class="card-body">
                 
                    <h4 class="mb-0 heading">Document Verification</h4>
            
                    <p class="text-muted mb-4 page_title_description">Upload your PAN Card and Aadhaar Card (Front & Back) for verification. <strong class="text-danger">All documents are mandatory.</strong></p>
                    
                    <form method="POST" enctype="multipart/form-data" id="verificationForm">
                        <div class="row">
                            <!-- PAN Card Upload -->
                            <div class="col-md-4 mb-4">
                                <input type="hidden" name="processed_pan_data" id="processed_pan_data" value="">
                                <div class="upload-container">
                                    <div class="document-placeholder" id="panPreview" onclick="clickFocusPan()">
                                        <?php if(!empty($pan_card_document)): ?>
                                            <span style="display:none;">PAN CARD</span>
                                            <img id="showPreviewPan" src="<?php echo htmlspecialchars($assets_base . '/assets/upload/verification/' . $pan_card_document); ?>" alt="PAN Card Preview">
                                        <?php else: ?>
                                            <span>PAN CARD</span>
                                            <img id="showPreviewPan" style="display:none;" alt="PAN Card Preview">
                                        <?php endif; ?>
                                    </div>
                                    <div class="file-info">File Supported - .png, .jpg, .jpeg, .gif, .webp</div>
                                    
                                    <p class="addlogo">Add PAN Card</p>
                                    <div class="file-upload">
                                        <label for="panCardInput" class="choose-btn">Choose File</label>
                                        <input type="file" name="pan_card_document" id="panCardInput" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" style="display:none;">
                                    </div>
                                    
                                    <?php if(!empty($pan_card_document) && !$is_locked): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-2 w-100 remove-file" data-file="pan_card">
                                        <i class="fa fa-trash"></i> Remove
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Aadhaar Front Upload -->
                            <div class="col-md-4 mb-4">
                                <input type="hidden" name="processed_aadhaar_front_data" id="processed_aadhaar_front_data" value="">
                                <div class="upload-container">
                                    <div class="document-placeholder" id="aadhaarFrontPreview" onclick="clickFocusAadhaarFront()">
                                        <?php if(!empty($aadhaar_front_document)): ?>
                                            <span style="display:none;">AADHAAR FRONT</span>
                                            <img id="showPreviewAadhaarFront" src="<?php echo htmlspecialchars($assets_base . '/assets/upload/verification/' . $aadhaar_front_document); ?>" alt="Aadhaar Front Preview">
                                        <?php else: ?>
                                            <span>AADHAAR FRONT</span>
                                            <img id="showPreviewAadhaarFront" style="display:none;" alt="Aadhaar Front Preview">
                                        <?php endif; ?>
                                    </div>
                                    <div class="file-info">File Supported - .png, .jpg, .jpeg, .gif, .webp</div>
                                    
                                    <p class="addlogo">Add Aadhaar Front</p>
                                    <div class="file-upload">
                                        <label for="aadhaarFrontInput" class="choose-btn">Choose File</label>
                                        <input type="file" name="aadhaar_front_document" id="aadhaarFrontInput" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" style="display:none;">
                                    </div>
                                    
                                    <?php if(!empty($aadhaar_front_document) && !$is_locked): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-2 w-100 remove-file" data-file="aadhaar_front">
                                        <i class="fa fa-trash"></i> Remove
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Aadhaar Back Upload -->
                            <div class="col-md-4 mb-4">
                                <input type="hidden" name="processed_aadhaar_back_data" id="processed_aadhaar_back_data" value="">
                                <div class="upload-container">
                                    <div class="document-placeholder" id="aadhaarBackPreview" onclick="clickFocusAadhaarBack()">
                                        <?php if(!empty($aadhaar_back_document)): ?>
                                            <span style="display:none;">AADHAAR BACK</span>
                                            <img id="showPreviewAadhaarBack" src="<?php echo htmlspecialchars($assets_base . '/assets/upload/verification/' . $aadhaar_back_document); ?>" alt="Aadhaar Back Preview">
                                        <?php else: ?>
                                            <span>AADHAAR BACK</span>
                                            <img id="showPreviewAadhaarBack" style="display:none;" alt="Aadhaar Back Preview">
                                        <?php endif; ?>
                                    </div>
                                    <div class="file-info">File Supported - .png, .jpg, .jpeg, .gif, .webp</div>
                                    
                                    <p class="addlogo">Add Aadhaar Back</p>
                                    <div class="file-upload">
                                        <label for="aadhaarBackInput" class="choose-btn">Choose File</label>
                                        <input type="file" name="aadhaar_back_document" id="aadhaarBackInput" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" style="display:none;">
                                    </div>
                                    
                                    <?php if(!empty($aadhaar_back_document) && !$is_locked): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-2 w-100 remove-file" data-file="aadhaar_back">
                                        <i class="fa fa-trash"></i> Remove
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                                                 <?php if(!$is_locked): ?>
                         <div class="text-center">
                             <button type="submit" name="submit_documents" class="btn btn-warning btn-lg px-5">
                                 SUBMIT
                             </button>
                         </div>
                         <?php endif; ?>
                    </form>
                    
                    <!-- Verification Status Display -->
                    <?php if($verification_status != 'pending'): ?>
                        <div class="mt-4">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="mb-0 verification_title">
                                        <i class="fa fa-info-circle"></i> Verification Status
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <strong>Status:</strong>
                                                <?php if($verification_status == 'submitted'): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fa fa-clock"></i> Under Review
                                                    </span>
                                                <?php elseif($verification_status == 'approved'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fa fa-check-circle"></i> Approved
                                                    </span>
                                                <?php elseif($verification_status == 'rejected'): ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fa fa-times-circle"></i> Rejected
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if($reviewed_at): ?>
                                                <div class="mb-3">
                                                    <strong>Reviewed On:</strong>
                                                    <span class="text-muted">
                                                        <?php echo date('d M Y, h:i A', strtotime($reviewed_at)); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if($reviewed_by): ?>
                                                <div class="mb-3">
                                                    <strong>Reviewed By:</strong>
                                                    <span class="text-muted"><?php echo htmlspecialchars($reviewed_by); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                                                                 <div class="col-md-6">
                                             <?php if($admin_remarks): ?>
                                                 <div class="mb-3">
                                                     <strong>Admin Remarks:</strong>
                                                     <div class="mt-2 p-3 bg-light border rounded">
                                                         <i class="fa fa-comment"></i>
                                                         <?php echo nl2br(htmlspecialchars($admin_remarks)); ?>
                                                     </div>
                                                 </div>
                                             <?php endif; ?>
                                         </div>
                                     </div>
                                     
                                     <?php if($verification_status == 'submitted'): ?>
                                         <div class="mt-3 p-3 bg-warning text-dark border rounded">
                                             <i class="fa fa-clock"></i>
                                             <strong>Thanks for sharing the Documents. Our team will verify it and confirm within 48 hrs.</strong>
                                         </div>
                                     <?php elseif($verification_status == 'approved'): ?>
                                         <div class="mt-3 p-3 bg-success text-white border rounded approved_text">
                                             <i class="fa fa-check-circle"></i>
                                             <strong>Congratulations! Your documents have been approved successfully.</strong>
                                         </div>
                                     <?php elseif($verification_status == 'rejected'): ?>
                                         <div class="mt-3 p-3 bg-danger text-white border rounded">
                                             <i class="fa fa-exclamation-triangle"></i>
                                             <strong>Your documents have been rejected. Please upload new documents.</strong>
                                             
                                         </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                                         <?php endif; ?>
                 </div>
             </div>
         </div>
     </div>
 </div>
                                     </main>

<!-- Toast container for upload feedback -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
    <div id="verificationToast" class="toast border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header text-white">
            <strong class="me-auto toast-title">Message</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body text-white" id="verificationToastMessage"></div>
    </div>
</div>

 <!-- Image Modal for Full Size View -->
 <div class="modal fade" id="imageModal" tabindex="-1" role="dialog" aria-labelledby="imageModalLabel" aria-hidden="true">
     <div class="modal-dialog modal-lg" role="document">
         <div class="modal-content">
             <div class="modal-header">
                 <h5 class="modal-title" id="imageModalLabel">Document Preview</h5>
                 <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
             </div>
             <div class="modal-body text-center">
                 <img id="modalImage" src="" alt="Document" class="img-fluid" style="max-height: 70vh;">
             </div>
         </div>
     </div>
 </div>

 <style>
.card {
    border: 1px solid #dee2e6;
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.uploaded-file {
    margin-top: 10px;
}

.uploaded-file .text-truncate {
    max-width: 200px;
}

.btn-warning {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #212529;
    font-weight: bold;
}
.verification_title{
    font-size:18px;
}
.btn-warning:hover {
    background-color: #e0a800;
    border-color: #d39e00;
    color: #212529;
}

.btn-warning:disabled {
    background-color: #6c757d;
    border-color: #6c757d;
    color: #fff;
}

.form-control-file {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
}

.form-control-file:hover {
    border-color: #ffc107;
}

.form-control-file:disabled {
    background-color: #f8f9fa;
    opacity: 0.6;
}
 
     .customer_content_area{
    padding: 0px 40px;
    margin-top: 33px;
    
}
 .breadcrumb {
    margin-bottom: 0px;
}
/* Status display styling */
.badge {
    font-size: 0.9em;
    padding: 8px 12px;
}

.badge i {
    margin-right: 5px;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}
.heading{
    font-size:22px;
}
.card-header h5 {
    color: #495057;
    font-weight: 600;
}

.card-header i {
    color: #007bff;
    margin-right: 8px;
}

/* Image preview styling */
.image-preview-container {
    margin-top: 10px;
}

.image-preview-container img {
    transition: all 0.3s ease;
    cursor: pointer;
}

.image-preview-container img:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.uploaded-file {
    margin-top: 15px;
}

.uploaded-file .text-truncate {
    max-width: 200px;
}
.verification-preview-wrap {
    min-height: 0;
}
.verification-preview-wrap .image-preview-container img {
    max-height: 200px;
    width: 100%;
    object-fit: contain;
}
.card-title{
    font-size:20px;
}
.document-placeholder {
    width: 200px;
    height: 200px;
    border: 2px solid darkgray;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    margin: 0 auto;
}

.document-placeholder span {
    font-weight: 500;
    font-size: 18px;
    line-height: 33px;
    text-align: center;
    color: #666;
}

.document-placeholder img {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 8px;
}

.upload-container {
    background: white;
    padding: 20px;
}

.upload-container .file-info {
    font-size: 14px;    
    color: #666;
    margin-bottom: 10px;
    text-align: center;
    padding-top: 10px;
    line-height: 20px;
}

.file-upload {
    display: none;
}

.choose-btn {
    display: inline-block;
    padding: 10px 20px;
    background-color: #007bff;
    color: white;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.choose-btn:hover {
    background-color: #0056b3;
    text-decoration: none;
    color: white;
}

.choose-btn i {
    margin-right: 8px;
}

.addlogo {
    font-size: 18px !important;
    margin-bottom: 10px;
    margin-top: 15px;
    text-align: center;
}
@media screen and (max-width: 768px) {
        .sb-topnav .navbar-brand img {
        max-height: 60px;
    }
    .FranchiseeDashboard-head .row-items-3 {
    justify-content: space-between;
    align-items: center;
}
.FranchiseeDashboard-head .card {
        width: 26rem !important;
    }
    .card-body {
    padding: 20px !important;
    padding-bottom: 100px !important;
}
.FranchiseeDashboard-head .card {
    
    padding: 10px 15px;
    font-weight: 600;
    margin: 30px auto;
}
 .FranchiseeDashboard-head .card {
    
    margin: 10px 0px !important;
    gap:3px;
}
.FranchiseeDashboard-head .card .img img {
        min-width: 53px;
        max-width: 50px;
    }
    .FranchiseeDashboard-head .card .content {
        
        padding-top: 0px;
    }
     .main-top {
        justify-content: flex-start;
        margin-left: 2px;
        padding: 20px 0px;
        padding-bottom: 0px;
    }
     .customer_content_area {
        padding: 0px 20px !important;
        margin-top: 33px;
    }
    .Copyright-left,
.Copyright-right{
    padding:0px;
}
    }
</style>

<script>
(function() {
    var documentUploadInited = false;
    
    function initDocumentUpload() {
        if (documentUploadInited || typeof window.jQuery === 'undefined' || typeof window.ImageCropUpload === 'undefined') return documentUploadInited;
        documentUploadInited = true;
        var $ = window.jQuery;
        
        $(document).ready(function(){
            // PAN Card upload handler
            $('#panCardInput').off('change.panUpload').on('change.panUpload', function(){
                if (!this.files || !this.files[0]) return;
                var file = this.files[0];
                
                ImageCropUpload.open(file, {
                    method: 'base64',
                    hiddenField: '#processed_pan_data',
                    previewSelector: '#panPreview',
                    imgIdToUpdate: '#showPreviewPan',
                    spanSelector: '#panPreview span',
                    title: 'Adjust & Crop PAN Card',
                    onSuccess: function() {
                        // Get base64 data from hidden field
                        var base64Data = $('#processed_pan_data').val();
                        if(base64Data) {
                            $('#showPreviewPan').attr('src', 'data:image/jpeg;base64,' + base64Data).show();
                            $('#panPreview span').hide();
                        }
                    },
                    onError: function(msg) { alert(msg); }
                });
                $(this).val('');
            });
            
            // Aadhaar Front upload handler
            $('#aadhaarFrontInput').off('change.aadhaarFrontUpload').on('change.aadhaarFrontUpload', function(){
                if (!this.files || !this.files[0]) return;
                var file = this.files[0];
                
                ImageCropUpload.open(file, {
                    method: 'base64',
                    hiddenField: '#processed_aadhaar_front_data',
                    previewSelector: '#aadhaarFrontPreview',
                    imgIdToUpdate: '#showPreviewAadhaarFront',
                    spanSelector: '#aadhaarFrontPreview span',
                    title: 'Adjust & Crop Aadhaar Front',
                    onSuccess: function() {
                        // Get base64 data from hidden field
                        var base64Data = $('#processed_aadhaar_front_data').val();
                        if(base64Data) {
                            $('#showPreviewAadhaarFront').attr('src', 'data:image/jpeg;base64,' + base64Data).show();
                            $('#aadhaarFrontPreview span').hide();
                        }
                    },
                    onError: function(msg) { alert(msg); }
                });
                $(this).val('');
            });
            
            // Aadhaar Back upload handler
            $('#aadhaarBackInput').off('change.aadhaarBackUpload').on('change.aadhaarBackUpload', function(){
                if (!this.files || !this.files[0]) return;
                var file = this.files[0];
                
                ImageCropUpload.open(file, {
                    method: 'base64',
                    hiddenField: '#processed_aadhaar_back_data',
                    previewSelector: '#aadhaarBackPreview',
                    imgIdToUpdate: '#showPreviewAadhaarBack',
                    spanSelector: '#aadhaarBackPreview span',
                    title: 'Adjust & Crop Aadhaar Back',
                    onSuccess: function() {
                        // Get base64 data from hidden field
                        var base64Data = $('#processed_aadhaar_back_data').val();
                        if(base64Data) {
                            $('#showPreviewAadhaarBack').attr('src', 'data:image/jpeg;base64,' + base64Data).show();
                            $('#aadhaarBackPreview span').hide();
                        }
                    },
                    onError: function(msg) { alert(msg); }
                });
                $(this).val('');
            });
            
            // Remove file handler
            $(document).on('click', '.remove-file', function() {
                var fileType = $(this).data('file');
                
                if(fileType === 'pan_card') {
                    $('#processed_pan_data').val('');
                    $('#showPreviewPan').hide();
                    $('#panPreview span').show();
                } else if(fileType === 'aadhaar_front') {
                    $('#processed_aadhaar_front_data').val('');
                    $('#showPreviewAadhaarFront').hide();
                    $('#aadhaarFrontPreview span').show();
                } else if(fileType === 'aadhaar_back') {
                    $('#processed_aadhaar_back_data').val('');
                    $('#showPreviewAadhaarBack').hide();
                    $('#aadhaarBackPreview span').show();
                }
            });
        });
        
        return true;
    }
    
    if (!initDocumentUpload()) {
        var check = setInterval(function() { if (initDocumentUpload()) clearInterval(check); }, 100);
    }
    
    window.clickFocusPan = function() { if (window.jQuery) window.jQuery('#panCardInput').click(); };
    window.clickFocusAadhaarFront = function() { if (window.jQuery) window.jQuery('#aadhaarFrontInput').click(); };
    window.clickFocusAadhaarBack = function() { if (window.jQuery) window.jQuery('#aadhaarBackInput').click(); };
})();

$(document).ready(function() {
    <?php if(isset($success_message) && !empty($success_message)): ?>
    (function() {
        var toastEl = document.getElementById('verificationToast');
        if (toastEl && typeof bootstrap !== 'undefined') {
            toastEl.classList.add('bg-success');
            toastEl.querySelector('.toast-title').textContent = 'Success';
            toastEl.querySelector('#verificationToastMessage').textContent = <?php echo json_encode($success_message); ?>;
            var toast = new bootstrap.Toast(toastEl, { delay: 5000 });
            toast.show();
        }
    })();
    <?php endif; ?>
    <?php if(isset($error_message) && !empty($error_message)): ?>
    (function() {
        var toastEl = document.getElementById('verificationToast');
        if (toastEl && typeof bootstrap !== 'undefined') {
            toastEl.classList.add('bg-danger');
            toastEl.querySelector('.toast-title').textContent = 'Error';
            toastEl.querySelector('#verificationToastMessage').textContent = <?php echo json_encode($error_message); ?>;
            var toast = new bootstrap.Toast(toastEl, { delay: 6000 });
            toast.show();
        }
    })();
    <?php endif; ?>
});
</script>

<?php include_once(__DIR__ . '/../includes/footer.php'); ?>



