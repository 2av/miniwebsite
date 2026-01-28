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
            $is_locked = ($row['status'] == 'submitted' || $row['status'] == 'approved');
            // Allow resubmission if rejected
            if($row['status'] == 'rejected') {
                $is_locked = false;
            }
        }
        $stmt->close();
    } catch(Exception $e) {
        error_log("Error fetching verification data: " . $e->getMessage());
    }
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_documents'])) {
    $pan_card_file = $_FILES['pan_card_document'];
    $aadhaar_front_file = $_FILES['aadhaar_front_document'];
    $aadhaar_back_file = $_FILES['aadhaar_back_document'];
    $upload_success = true;
    $error_message = '';
    
    // Validate files
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    $max_size = 200 * 1024; // 200KB
    
    // Check PAN Card file
    if($pan_card_file['error'] == 0) {
        if(!in_array($pan_card_file['type'], $allowed_types)) {
            $error_message = "PAN Card file must be JPG or PNG format.";
            $upload_success = false;
        } elseif($pan_card_file['size'] > $max_size) {
            $error_message = "PAN Card file size must be less than 200KB.";
            $upload_success = false;
        }
    }
    
    // Check Aadhaar Front file
    if($aadhaar_front_file['error'] == 0) {
        if(!in_array($aadhaar_front_file['type'], $allowed_types)) {
            $error_message = "Aadhaar Front file must be JPG or PNG format.";
            $upload_success = false;
        } elseif($aadhaar_front_file['size'] > $max_size) {
            $error_message = "Aadhaar Front file size must be less than 200KB.";
            $upload_success = false;
        }
    }
    
    // Check Aadhaar Back file
    if($aadhaar_back_file['error'] == 0) {
        if(!in_array($aadhaar_back_file['type'], $allowed_types)) {
            $error_message = "Aadhaar Back file must be JPG or PNG format.";
            $upload_success = false;
        } elseif($aadhaar_back_file['size'] > $max_size) {
            $error_message = "Aadhaar Back file size must be less than 200KB.";
            $upload_success = false;
        }
    }
    
    if($upload_success) {
        // Store verification uploads in a single shared folder:
        // <project_root>/assets/upload/verification/
        $upload_dir = __DIR__ . '/../../assets/upload/verification/';
        if(!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $pan_card_filename = '';
        $aadhaar_front_filename = '';
        $aadhaar_back_filename = '';
        
        // Upload PAN Card file
        if($pan_card_file['error'] == 0) {
            $pan_card_filename = 'pan_' . time() . '_' . $_SESSION['f_user_email'] . '.' . pathinfo($pan_card_file['name'], PATHINFO_EXTENSION);
            if(!move_uploaded_file($pan_card_file['tmp_name'], $upload_dir . $pan_card_filename)) {
                $error_message = "Failed to upload PAN Card file.";
                $upload_success = false;
            }
        }
        
        // Upload Aadhaar Front file
        if($aadhaar_front_file['error'] == 0) {
            $aadhaar_front_filename = 'aadhaar_front_' . time() . '_' . $_SESSION['f_user_email'] . '.' . pathinfo($aadhaar_front_file['name'], PATHINFO_EXTENSION);
            if(!move_uploaded_file($aadhaar_front_file['tmp_name'], $upload_dir . $aadhaar_front_filename)) {
                $error_message = "Failed to upload Aadhaar Front file.";
                $upload_success = false;
            }
        }
        
        // Upload Aadhaar Back file
        if($aadhaar_back_file['error'] == 0) {
            $aadhaar_back_filename = 'aadhaar_back_' . time() . '_' . $_SESSION['f_user_email'] . '.' . pathinfo($aadhaar_back_file['name'], PATHINFO_EXTENSION);
            if(!move_uploaded_file($aadhaar_back_file['tmp_name'], $upload_dir . $aadhaar_back_filename)) {
                $error_message = "Failed to upload Aadhaar Back file.";
                $upload_success = false;
            }
        }
        
        if($upload_success) {
            try {
                // Insert or update verification record
                $stmt = $connect->prepare("INSERT INTO franchisee_verification (user_email, pan_card_document, aadhaar_front_document, aadhaar_back_document, status, submitted_at) VALUES (?, ?, ?, ?, 'submitted', NOW()) ON DUPLICATE KEY UPDATE pan_card_document = VALUES(pan_card_document), aadhaar_front_document = VALUES(aadhaar_front_document), aadhaar_back_document = VALUES(aadhaar_back_document), status = 'submitted', submitted_at = NOW()");
                $stmt->bind_param("ssss", $_SESSION['f_user_email'], $pan_card_filename, $aadhaar_front_filename, $aadhaar_back_filename);
                $stmt->execute();
                $stmt->close();
                
                // Create admin notification for new document submission
                try {
                    require_once('../../admin/includes/notification_helper.php');
                    
                    $notification_title = "New Franchisee Documents Submitted";
                    $notification_message = "Franchisee " . $_SESSION['f_user_email'] . " has submitted new documents for verification.";
                    
                    createNotification(
                        'verification',
                        $notification_title,
                        $notification_message,
                        $_SESSION['f_user_email'],
                        'franchisee',
                        null,
                        'high'
                    );
                } catch(Exception $e) {
                    // Log notification error but don't fail the main operation
                    error_log("Error creating notification: " . $e->getMessage());
                }
                
                // Update local variables
                $pan_card_document = $pan_card_filename;
                $aadhaar_front_document = $aadhaar_front_filename;
                $aadhaar_back_document = $aadhaar_back_filename;
                $verification_status = 'submitted';
                $is_locked = true;
                
                $success_message = "Documents submitted successfully! Our team will verify them within 48 hours.";
            } catch(Exception $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>
<main>
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
                    
                    <?php if(isset($error_message) && !empty($error_message)): ?>
                        <div class=""><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>
                    
                    <?php if(isset($success_message) && !empty($success_message)): ?>
                        <div class=""><?php echo htmlspecialchars($success_message); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" id="verificationForm">
                        <div class="row">
                            <!-- PAN Card Upload -->
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                       
                                         <h4 class="card-title">PAN CARD <span class="text-danger">*</span></h4>
                                         <?php if(!$is_locked): ?>
                                         <div class="mb-3">
                                             <small class="text-muted d-block">File Size - Not more than 200KB</small>
                                             <small class="text-muted d-block">File Supported - .png, .jpg</small>
                                         </div>
                                         <?php endif; ?>
                                        
                                        <?php if(!$is_locked): ?>
                                        <div class="mb-3">
                                            <input type="file" class="form-control-file" name="pan_card_document" accept=".jpg,.jpeg,.png">
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($pan_card_document)): ?>
                                                                                           <div class="uploaded-file">
                                                  <?php if(!$is_locked): ?>
                                                  <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded mb-2">
                                                      <span class="text-truncate"><?php echo htmlspecialchars($pan_card_document); ?></span>
                                                      <button type="button" class="btn btn-sm btn-outline-danger remove-file" data-file="pan_card">×</button>
                                                  </div>
                                                  <?php endif; ?>
                                                 <!-- Image Preview -->
                                                 <div class="image-preview-container">
                                                     <img src="<?php echo htmlspecialchars($assets_base . '/assets/upload/verification/' . $pan_card_document); ?>" 
                                                          alt="PAN Card" 
                                                          class="img-fluid rounded border" 
                                                          style="max-height: 200px; width: 100%; object-fit: contain;"
                                                          onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                     <div class="text-center p-3 bg-light border rounded" style="display: none;">
                                                         <i class="fa fa-image text-muted" style="font-size: 2rem;"></i>
                                                         <p class="text-muted mt-2">Image not available</p>
                                                     </div>
                                                 </div>
                                             </div>
                                         <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Aadhaar Front Upload -->
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                         
                                        <h4 class="card-title">AADHAAR FRONT <span class="text-danger">*</span></h4>
                                        <?php if(!$is_locked): ?>
                                        <div class="mb-3">
                                            <small class="text-muted d-block">File Size - Not more than 200KB</small>
                                            <small class="text-muted d-block">File Supported - .png, .jpg</small>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(!$is_locked): ?>
                                        <div class="mb-3">
                                            <input type="file" class="form-control-file" name="aadhaar_front_document" accept=".jpg,.jpeg,.png">
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($aadhaar_front_document)): ?>
                                        <div class="uploaded-file">
                                            <?php if(!$is_locked): ?>
                                            <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded mb-2">
                                                <span class="text-truncate"><?php echo htmlspecialchars($aadhaar_front_document); ?></span>
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-file" data-file="aadhaar_front">×</button>
                                            </div>
                                            <?php endif; ?>
                                            <!-- Image Preview -->
                                            <div class="image-preview-container">
                                                <img src="<?php echo htmlspecialchars($assets_base . '/assets/upload/verification/' . $aadhaar_front_document); ?>" 
                                                     alt="Aadhaar Front" 
                                                     class="img-fluid rounded border" 
                                                     style="max-height: 200px; width: 100%; object-fit: contain;"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                <div class="text-center p-3 bg-light border rounded" style="display: none;">
                                                    <i class="fa fa-image text-muted" style="font-size: 2rem;"></i>
                                                    <p class="text-muted mt-2">Image not available</p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Aadhaar Back Upload -->
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                         
                                        <h4 class="card-title">AADHAAR BACK <span class="text-danger">*</span></h4>
                                        <?php if(!$is_locked): ?>
                                        <div class="mb-3">
                                            <small class="text-muted d-block">File Size - Not more than 200KB</small>
                                            <small class="text-muted d-block">File Supported - .png, .jpg</small>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(!$is_locked): ?>
                                        <div class="mb-3">
                                            <input type="file" class="form-control-file" name="aadhaar_back_document" accept=".jpg,.jpeg,.png">
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($aadhaar_back_document)): ?>
                                        <div class="uploaded-file">
                                            <?php if(!$is_locked): ?>
                                            <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded mb-2">
                                                <span class="text-truncate"><?php echo htmlspecialchars($aadhaar_back_document); ?></span>
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-file" data-file="aadhaar_back">×</button>
                                            </div>
                                            <?php endif; ?>
                                            <!-- Image Preview -->
                                            <div class="image-preview-container">
                                                <img src="<?php echo htmlspecialchars($assets_base . '/assets/upload/verification/' . $aadhaar_back_document); ?>" 
                                                     alt="Aadhaar Back" 
                                                     class="img-fluid rounded border" 
                                                     style="max-height: 200px; width: 100%; object-fit: contain;"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                <div class="text-center p-3 bg-light border rounded" style="display: none;">
                                                    <i class="fa fa-image text-muted" style="font-size: 2rem;"></i>
                                                    <p class="text-muted mt-2">Image not available</p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
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
.card-title{
    font-size:20px;
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
$(document).ready(function() {
    // File input change handler
    $('input[type="file"]').on('change', function() {
        var file = this.files[0];
        var maxSize = 200 * 1024; // 200KB
        var inputName = $(this).attr('name');
        var cardContainer = $(this).closest('.card-body');
        
        if(file) {
            if(file.size > maxSize) {
                alert('File size must be less than 200KB');
                this.value = '';
                return;
            }
            
            var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if(!allowedTypes.includes(file.type)) {
                alert('Only JPG and PNG files are allowed');
                this.value = '';
                return;
            }
            
            // Create image preview
            var reader = new FileReader();
            reader.onload = function(e) {
                var imagePreview = cardContainer.find('.image-preview-container');
                if(imagePreview.length === 0) {
                    // Create new preview container
                    var previewHtml = '<div class="image-preview-container mt-2">' +
                        '<img src="' + e.target.result + '" class="img-fluid rounded border" style="max-height: 200px; width: 100%; object-fit: contain;">' +
                        '</div>';
                    cardContainer.find('.uploaded-file').remove();
                    cardContainer.append('<div class="uploaded-file">' + previewHtml + '</div>');
                } else {
                    // Update existing preview
                    imagePreview.find('img').attr('src', e.target.result);
                }
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Remove file handler
    $('.remove-file').on('click', function() {
        var fileType = $(this).data('file');
        var input = $('input[name="' + fileType + '_document"]');
        input.val('');
        $(this).closest('.uploaded-file').remove();
    });
    
    // Form validation
    $('#verificationForm').on('submit', function(e) {
        var panCardFile = $('input[name="pan_card_document"]')[0].files[0];
        var aadhaarFrontFile = $('input[name="aadhaar_front_document"]')[0].files[0];
        var aadhaarBackFile = $('input[name="aadhaar_back_document"]')[0].files[0];
        
        // All three files are mandatory
        if(!panCardFile) {
            alert('Please upload PAN Card document. All documents are mandatory.');
            e.preventDefault();
            return false;
        }
        
        if(!aadhaarFrontFile) {
            alert('Please upload Aadhaar Front document. All documents are mandatory.');
            e.preventDefault();
            return false;
        }
        
        if(!aadhaarBackFile) {
            alert('Please upload Aadhaar Back document. All documents are mandatory.');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
     
     // Image click handler for modal
     $(document).on('click', '.image-preview-container img', function() {
         var imageSrc = $(this).attr('src');
         var imageAlt = $(this).attr('alt');
         
         $('#modalImage').attr('src', imageSrc);
         $('#modalImage').attr('alt', imageAlt);
         $('#imageModalLabel').text(imageAlt);
         
         var modal = new bootstrap.Modal(document.getElementById('imageModal'));
         modal.show();
     });
 });
</script>

<?php include_once('../footer.php'); ?>



