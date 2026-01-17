<?php
// Set PHP execution limits (upload_max_filesize and post_max_size must be set in php.ini)
@ini_set('max_execution_time', '300');
@ini_set('max_input_time', '300');
@ini_set('memory_limit', '128M');

require_once('connect.php');
include_once('header.php');

// Determine active kit tab
$allowed_kits = array('franchisee','sales','marketing','franchise_sales');
$current_kit = isset($_GET['kit']) && in_array(strtolower($_GET['kit']), $allowed_kits) ? strtolower($_GET['kit']) : 'franchisee';

function kitLabel($key) {
    if ($key === 'sales') return 'Sales Kit';
    if ($key === 'marketing') return 'Marketing Kit';
    if ($key === 'franchise_sales') return 'Franchise Sales Kit';
    return 'Franchisee Kit';
}

// Counts per kit for badges
$kit_counts = array('franchisee' => 0, 'sales' => 0, 'marketing' => 0, 'franchise_sales' => 0);
$count_res = mysqli_query($connect, "SELECT category, COUNT(*) AS c FROM franchisee_kit GROUP BY category");
if ($count_res) {
    while ($cr = mysqli_fetch_array($count_res)) {
        $cat = strtolower($cr['category']);
        if (isset($kit_counts[$cat])) { $kit_counts[$cat] = (int)$cr['c']; }
    }
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Function to convert PHP ini size string to bytes
function convertToBytes($val) {
    if (empty($val)) return 0;
    $val = trim($val);
    $len = strlen($val);
    if ($len == 0) return 0;
    $last = strtolower($val[$len - 1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add_image') {
            // Handle image upload
            if (isset($_FILES['kit_image']) && $_FILES['kit_image']['error'] == 0) {
                $upload_dir = '../franchisee/kit/uploads/';
                
                // Check if directory exists, create if not
                if (!file_exists($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $error_message = "Failed to create upload directory. Please check server permissions.";
                    }
                }
                
                // Check if directory is writable
                if (file_exists($upload_dir) && !is_writable($upload_dir)) {
                    $error_message = "Upload directory is not writable. Please set permissions to 755 or 777 on: " . $upload_dir;
                }
                
                if (!isset($error_message)) {
                    $file_extension = strtolower(pathinfo($_FILES['kit_image']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    $max_file_size = 10 * 1024 * 1024; // 10MB in bytes
                    
                    // Check file size
                    if ($_FILES['kit_image']['size'] > $max_file_size) {
                        $error_message = "Image size too large. Maximum allowed size is 10MB. Your file size: " . formatFileSize($_FILES['kit_image']['size']);
                    } elseif (in_array($file_extension, $allowed_extensions)) {
                        $new_filename = 'kit_image_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['kit_image']['tmp_name'], $upload_path)) {
                            $title = mysqli_real_escape_string($connect, isset($_POST['image_title']) ? $_POST['image_title'] : '');
                            $display_order = intval(isset($_POST['image_order']) ? $_POST['image_order'] : 0);
                            
                            // Category from hidden field or current tab
                            $kit_category = mysqli_real_escape_string($connect, isset($_POST['kit_category']) ? $_POST['kit_category'] : $current_kit);
                            $insert_query = "INSERT INTO franchisee_kit (type, title, file_path, display_order, status, category) 
                                           VALUES ('image', '$title', '$new_filename', $display_order, 'active', '$kit_category')";
                            
                            if (mysqli_query($connect, $insert_query)) {
                                $success_message = "Image uploaded successfully!";
                            } else {
                                $error_message = "Error saving image details: " . mysqli_error($connect);
                            }
                        } else {
                            $error_message = "Error uploading image file. Upload directory: " . $upload_dir . " (writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . ")";
                        }
                    } else {
                        $error_message = "Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.";
                    }
                }
            } else {
                $error_message = "Please select an image file.";
            }
        }
        
        elseif ($action == 'add_video') {
            // Handle video URL addition
            $video_url = mysqli_real_escape_string($connect, isset($_POST['video_url']) ? $_POST['video_url'] : '');
            $video_title = mysqli_real_escape_string($connect, isset($_POST['video_title']) ? $_POST['video_title'] : '');
            $display_order = intval(isset($_POST['video_order']) ? $_POST['video_order'] : 0);
            $kit_category = mysqli_real_escape_string($connect, isset($_POST['kit_category']) ? $_POST['kit_category'] : $current_kit);
            
            if (!empty($video_url)) {
                $insert_query = "INSERT INTO franchisee_kit (type, title, video_url, display_order, status, category) 
                               VALUES ('video', '$video_title', '$video_url', $display_order, 'active', '$kit_category')";
                
                if (mysqli_query($connect, $insert_query)) {
                    $success_message = "Video link added successfully!";
                } else {
                    $error_message = "Error saving video details: " . mysqli_error($connect);
                }
            } else {
                $error_message = "Please enter a video URL.";
            }
        }
        
        elseif ($action == 'add_file') {
            // Handle file upload
            if (isset($_FILES['file_upload'])) {
                $file_error = $_FILES['file_upload']['error'];
                
                // Check for upload errors
                if ($file_error == 0) {
                    $upload_dir = '../franchisee/kit/uploads/';
                    
                    // Check if directory exists, create if not
                    if (!file_exists($upload_dir)) {
                        if (!mkdir($upload_dir, 0755, true)) {
                            $error_message = "Failed to create upload directory. Please check server permissions.";
                        }
                    }
                    
                    // Check if directory is writable
                    if (file_exists($upload_dir) && !is_writable($upload_dir)) {
                        $error_message = "Upload directory is not writable. Please set permissions to 755 or 777 on: " . $upload_dir;
                    }
                    
                    if (!isset($error_message)) {
                        $file_extension = strtolower(pathinfo($_FILES['file_upload']['name'], PATHINFO_EXTENSION));
                        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', 'mp4', 'avi', 'mov', 'mp3', 'wav'];
                        $max_file_size = 10 * 1024 * 1024; // 10MB in bytes
                        
                        // Check file size
                        if ($_FILES['file_upload']['size'] > $max_file_size) {
                            $error_message = "File size too large. Maximum allowed size is 10MB. Your file size: " . formatFileSize($_FILES['file_upload']['size']);
                        } elseif (in_array($file_extension, $allowed_extensions)) {
                            $new_filename = 'kit_file_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                            $upload_path = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $upload_path)) {
                            $title = mysqli_real_escape_string($connect, isset($_POST['file_title']) ? $_POST['file_title'] : '');
                            $display_order = intval(isset($_POST['file_order']) ? $_POST['file_order'] : 0);
                            
                            $kit_category = mysqli_real_escape_string($connect, isset($_POST['kit_category']) ? $_POST['kit_category'] : $current_kit);
                            $insert_query = "INSERT INTO franchisee_kit (type, title, file_path, display_order, status, category) 
                                           VALUES ('file', '$title', '$new_filename', $display_order, 'active', '$kit_category')";
                            
                            if (mysqli_query($connect, $insert_query)) {
                                $success_message = "File uploaded successfully!";
                            } else {
                                $error_message = "Error saving file details: " . mysqli_error($connect);
                            }
                        } else {
                            $error_message = "Error uploading file. Please check file permissions. Upload directory: " . $upload_dir . " (writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . ")";
                        }
                    } else {
                        $error_message = "Invalid file type. Only PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR, MP4, AVI, MOV, MP3, WAV files are allowed. Your file: ." . $file_extension;
                    }
                }
                } else {
                    // Handle specific upload errors
                    switch ($file_error) {
                        case 1:
                            $error_message = "File too large. Please select a smaller file.";
                            break;
                        case 2:
                            $error_message = "File too large. Please select a smaller file.";
                            break;
                        case 3:
                            $error_message = "File upload was interrupted. Please try again.";
                            break;
                        case 4:
                            $error_message = "Please select a file.";
                            break;
                        case 6:
                            $error_message = "Server error. Please try again later.";
                            break;
                        case 7:
                            $error_message = "Server error. Please try again later.";
                            break;
                        case 8:
                            $error_message = "File upload stopped. Please try again.";
                            break;
                        default:
                            $error_message = "Upload error. Please try again.";
                    }
                }
            } else {
                $error_message = "Please select a file.";
            }
        }
        
        elseif ($action == 'delete_item') {
            $item_id = intval($_POST['item_id']);
            $item_type = mysqli_real_escape_string($connect, $_POST['item_type']);
            
            // Get file path if it's an image or file
            if ($item_type == 'image' || $item_type == 'file') {
                $get_file_query = "SELECT file_path FROM franchisee_kit WHERE id = $item_id";
                $file_result = mysqli_query($connect, $get_file_query);
                if ($file_result && $file_row = mysqli_fetch_array($file_result)) {
                    $file_path = '../franchisee/kit/uploads/' . $file_row['file_path'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            }
            
            $delete_query = "DELETE FROM franchisee_kit WHERE id = $item_id";
            if (mysqli_query($connect, $delete_query)) {
                $success_message = "Item deleted successfully!";
            } else {
                $error_message = "Error deleting item: " . mysqli_error($connect);
            }
        }
        
        elseif ($action == 'update_status') {
            $item_id = intval($_POST['item_id']);
            $new_status = mysqli_real_escape_string($connect, $_POST['new_status']);
            
            $update_query = "UPDATE franchisee_kit SET status = '$new_status' WHERE id = $item_id";
            if (mysqli_query($connect, $update_query)) {
                $success_message = "Status updated successfully!";
            } else {
                $error_message = "Error updating status: " . mysqli_error($connect);
            }
        }
        
        elseif ($action == 'edit_image') {
            $item_id = intval($_POST['item_id']);
            $title = mysqli_real_escape_string($connect, isset($_POST['edit_image_title']) ? $_POST['edit_image_title'] : '');
            $display_order = intval(isset($_POST['edit_image_order']) ? $_POST['edit_image_order'] : 0);
            $status = mysqli_real_escape_string($connect, isset($_POST['edit_image_status']) ? $_POST['edit_image_status'] : 'active');
            
            // Handle new image upload if provided
            $image_update = '';
            if (isset($_FILES['edit_kit_image']) && $_FILES['edit_kit_image']['error'] == 0) {
                $upload_dir = '../franchisee/kit/uploads/';
                $file_extension = strtolower(pathinfo($_FILES['edit_kit_image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    $max_file_size = 10 * 1024 * 1024; // 10MB in bytes
                
                // Check file size
                if ($_FILES['edit_kit_image']['size'] > $max_file_size) {
                    $error_message = "Image size too large. Maximum allowed size is 10MB. Your file size: " . formatFileSize($_FILES['edit_kit_image']['size']);
                    goto edit_end;
                } elseif (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = 'kit_image_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['edit_kit_image']['tmp_name'], $upload_path)) {
                        // Delete old image file
                        $old_file_query = "SELECT file_path FROM franchisee_kit WHERE id = $item_id";
                        $old_file_result = mysqli_query($connect, $old_file_query);
                        if ($old_file_result && $old_file_row = mysqli_fetch_array($old_file_result)) {
                            $old_file_path = $upload_dir . $old_file_row['file_path'];
                            if (file_exists($old_file_path)) {
                                unlink($old_file_path);
                            }
                        }
                        $image_update = ", file_path = '$new_filename'";
                    } else {
                        $error_message = "Error uploading new image file.";
                        goto edit_end;
                    }
                } else {
                    $error_message = "Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.";
                    goto edit_end;
                }
            }
            
            $update_query = "UPDATE franchisee_kit SET title = '$title', display_order = $display_order, status = '$status'$image_update WHERE id = $item_id";
            if (mysqli_query($connect, $update_query)) {
                $success_message = "Image updated successfully!";
            } else {
                $error_message = "Error updating image: " . mysqli_error($connect);
            }
            edit_end:
        }
        
        elseif ($action == 'edit_video') {
            $item_id = intval($_POST['item_id']);
            $title = mysqli_real_escape_string($connect, isset($_POST['edit_video_title']) ? $_POST['edit_video_title'] : '');
            $video_url = mysqli_real_escape_string($connect, isset($_POST['edit_video_url']) ? $_POST['edit_video_url'] : '');
            $display_order = intval(isset($_POST['edit_video_order']) ? $_POST['edit_video_order'] : 0);
            $status = mysqli_real_escape_string($connect, isset($_POST['edit_video_status']) ? $_POST['edit_video_status'] : 'active');
            
            if (!empty($video_url)) {
                $update_query = "UPDATE franchisee_kit SET title = '$title', video_url = '$video_url', display_order = $display_order, status = '$status' WHERE id = $item_id";
                if (mysqli_query($connect, $update_query)) {
                    $success_message = "Video updated successfully!";
                } else {
                    $error_message = "Error updating video: " . mysqli_error($connect);
                }
            } else {
                $error_message = "Please enter a video URL.";
            }
        }
        
        elseif ($action == 'edit_file') {
            $item_id = intval($_POST['item_id']);
            $title = mysqli_real_escape_string($connect, isset($_POST['edit_file_title']) ? $_POST['edit_file_title'] : '');
            $display_order = intval(isset($_POST['edit_file_order']) ? $_POST['edit_file_order'] : 0);
            $status = mysqli_real_escape_string($connect, isset($_POST['edit_file_status']) ? $_POST['edit_file_status'] : 'active');
            
            // Handle new file upload if provided
            $file_update = '';
            if (isset($_FILES['edit_file_upload']) && $_FILES['edit_file_upload']['error'] == 0) {
                $upload_dir = '../franchisee/kit/uploads/';
                $file_extension = strtolower(pathinfo($_FILES['edit_file_upload']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', 'mp4', 'avi', 'mov', 'mp3', 'wav'];
                
                    $max_file_size = 10 * 1024 * 1024; // 10MB in bytes
                
                // Check file size
                if ($_FILES['edit_file_upload']['size'] > $max_file_size) {
                    $error_message = "File size too large. Maximum allowed size is 10MB. Your file size: " . formatFileSize($_FILES['edit_file_upload']['size']);
                    goto edit_file_end;
                } elseif (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = 'kit_file_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['edit_file_upload']['tmp_name'], $upload_path)) {
                        // Delete old file
                        $old_file_query = "SELECT file_path FROM franchisee_kit WHERE id = $item_id";
                        $old_file_result = mysqli_query($connect, $old_file_query);
                        if ($old_file_result && $old_file_row = mysqli_fetch_array($old_file_result)) {
                            $old_file_path = $upload_dir . $old_file_row['file_path'];
                            if (file_exists($old_file_path)) {
                                unlink($old_file_path);
                            }
                        }
                        $file_update = ", file_path = '$new_filename'";
                    } else {
                        $error_message = "Error uploading new file.";
                        goto edit_file_end;
                    }
                } else {
                    $error_message = "Invalid file type. Only PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR, MP4, AVI, MOV, MP3, WAV files are allowed.";
                    goto edit_file_end;
                }
            }
            
            $update_query = "UPDATE franchisee_kit SET title = '$title', display_order = $display_order, status = '$status'$file_update WHERE id = $item_id";
            if (mysqli_query($connect, $update_query)) {
                $success_message = "File updated successfully!";
            } else {
                $error_message = "Error updating file: " . mysqli_error($connect);
            }
            edit_file_end:
        }
    }
}

// Get all kit items for current category
$kit_items_query = "SELECT * FROM franchisee_kit WHERE category='" . mysqli_real_escape_string($connect, $current_kit) . "' ORDER BY display_order ASC, created_at DESC";
$kit_items_result = mysqli_query($connect, $kit_items_query);
$kit_items = [];
if ($kit_items_result) {
    while ($row = mysqli_fetch_assoc($kit_items_result)) {
        $kit_items[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Kit Management - Admin Panel</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 20px;
        }
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            overflow: hidden;
        }
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            border: none;
        }
        .btn-custom-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-custom-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .btn-custom-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-custom-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
        .btn-custom-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-custom-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.4);
            color: white;
        }
        .kit-item-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        .kit-item-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .kit-image {
            height: 200px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
            width: 100%;
        }
        .kit-video-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .kit-video-card:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f2ff 0%, #e8ecff 100%);
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .status-inactive {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .btn-action {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .btn-action:hover {
            transform: translateY(-1px);
        }
        .modal-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .modal-header-custom .btn-close {
            filter: invert(1);
        }
        .form-control-custom {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control-custom:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .stats-overview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .stat-item {
            text-align: center;
            padding: 20px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="d-flex align-items-center mb-2">
                        <a href="index.php" class="btn btn-outline-secondary me-3">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                        <h2 class="mb-0"><i class="fas fa-toolbox me-2"></i>Kit Management</h2>
                    </div>
                    <p class="text-muted mb-0">Manage promotional materials and resources for franchisees</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-custom-primary" data-bs-toggle="modal" data-bs-target="#addfileModal">
                        <i class="fas fa-plus me-2"></i>Add File
                    </button>
                    <button type="button" class="btn btn-custom-warning" data-bs-toggle="modal" data-bs-target="#addImageModal">
                        <i class="fas fa-plus me-2"></i>Add Images
                    </button>
                    <button type="button" class="btn btn-custom-success" data-bs-toggle="modal" data-bs-target="#addVideoModal">
                        <i class="fas fa-video me-2"></i>Add Videos
                    </button>
                </div>
            </div>
        </div>

        <!-- Kit Tabs -->
        <ul class="nav nav-tabs content-tabs" role="tablist" style="margin-top:-10px;gap:8px;border-bottom:none;">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $current_kit=='franchisee' ? 'active' : ''; ?>" href="?kit=franchisee" style="border:1px solid #e9ecef;border-bottom-width:3px;border-radius:10px;padding:10px 16px;">
                    <i class="fas fa-toolbox me-2"></i><?php echo kitLabel('franchisee'); ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo $kit_counts['franchisee']; ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $current_kit=='sales' ? 'active' : ''; ?>" href="?kit=sales" style="border:1px solid #e9ecef;border-bottom-width:3px;border-radius:10px;padding:10px 16px;">
                    <i class="fas fa-briefcase me-2"></i><?php echo kitLabel('sales'); ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo $kit_counts['sales']; ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $current_kit=='marketing' ? 'active' : ''; ?>" href="?kit=marketing" style="border:1px solid #e9ecef;border-bottom-width:3px;border-radius:10px;padding:10px 16px;">
                    <i class="fas fa-bullhorn me-2"></i><?php echo kitLabel('marketing'); ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo $kit_counts['marketing']; ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $current_kit=='franchise_sales' ? 'active' : ''; ?>" href="?kit=franchise_sales" style="border:1px solid #e9ecef;border-bottom-width:3px;border-radius:10px;padding:10px 16px;">
                    <i class="fas fa-handshake me-2"></i><?php echo kitLabel('franchise_sales'); ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo $kit_counts['franchise_sales']; ?></span>
                </a>
            </li>
        </ul>
        <style>
            .content-tabs .nav-link.active{
                background: #ffffff;
                border-color: #667eea !important;
                color: #667eea !important;
                box-shadow: 0 3px 10px rgba(102,126,234,0.15);
            }
            .content-tabs .nav-link{
                color: #495057;
                font-weight: 600;
                background:#fff;
            }
        </style>

        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="row">
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($kit_items, function($item) { return $item['type'] == 'image'; })); ?></div>
                        <div class="stat-label">Images</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($kit_items, function($item) { return $item['type'] == 'video'; })); ?></div>
                        <div class="stat-label">Videos</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($kit_items, function($item) { return $item['type'] == 'file'; })); ?></div>
                        <div class="stat-label">Files</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($kit_items, function($item) { return $item['status'] == 'active'; })); ?></div>
                        <div class="stat-label">Active Items</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($kit_items); ?></div>
                        <div class="stat-label">Total Items</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Server Configuration Check -->
        <?php if (isset($error_message) && (strpos($error_message, 'too large') !== false || strpos($error_message, 'Upload error') !== false || strpos($error_message, 'permissions') !== false || strpos($error_message, 'writable') !== false)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong><i class="fas fa-info-circle me-2"></i>Server Configuration Diagnostics:</strong><br>
            <div class="row mt-2">
                <div class="col-md-6">
                    <strong>PHP Upload Limits:</strong><br>
                    Upload max size: <strong><?php echo ini_get('upload_max_filesize'); ?></strong><br>
                    Post max size: <strong><?php echo ini_get('post_max_size'); ?></strong><br>
                    Memory limit: <strong><?php echo ini_get('memory_limit'); ?></strong><br>
                </div>
                <div class="col-md-6">
                    <strong>Upload Directory Status:</strong><br>
                    Directory: <code><?php echo realpath('../franchisee/kit/uploads/') ?: '../franchisee/kit/uploads/'; ?></code><br>
                    Exists: <strong><?php echo file_exists('../franchisee/kit/uploads/') ? 'Yes' : 'No'; ?></strong><br>
                    Writable: <strong><?php echo is_writable('../franchisee/kit/uploads/') ? 'Yes' : 'No'; ?></strong><br>
                    <?php if (file_exists('../franchisee/kit/uploads/')): ?>
                    Permissions: <strong><?php echo substr(sprintf('%o', fileperms('../franchisee/kit/uploads/')), -4); ?></strong><br>
                    <?php endif; ?>
                </div>
            </div>
            <small class="mt-2 d-block">
                <?php 
                $upload_max = convertToBytes(ini_get('upload_max_filesize'));
                $post_max = convertToBytes(ini_get('post_max_size'));
                $required_upload = 10 * 1024 * 1024; // 10MB
                $required_post = 12 * 1024 * 1024; // 12MB
                $upload_dir = '../franchisee/kit/uploads/';
                $has_php_issue = ($upload_max < $required_upload || $post_max < $required_post);
                $has_permission_issue = (file_exists($upload_dir) && !is_writable($upload_dir));
                
                if ($has_php_issue || $has_permission_issue): ?>
                    <strong>Action Required for Live Server:</strong><br>
                    <?php if ($has_php_issue): ?>
                    <strong>1. PHP Configuration:</strong> Contact your hosting provider or edit <code>php.ini</code> to set:<br>
                    <code>upload_max_filesize = 10M</code><br>
                    <code>post_max_size = 12M</code><br>
                    <em>Note: If using cPanel, you can also create/update <code>.user.ini</code> file in your root directory with these values.</em><br><br>
                    <?php endif; ?>
                    <?php if ($has_permission_issue): ?>
                    <strong>2. File Permissions:</strong> Set permissions on upload directory via FTP/cPanel File Manager:<br>
                    <code>chmod 755</code> or <code>chmod 777</code> on <code>franchisee/kit/uploads/</code><br>
                    <em>In cPanel: Right-click folder → Change Permissions → Set to 755 (or 777 if 755 doesn't work)</em><br><br>
                    <?php endif; ?>
                    <?php if (!$has_php_issue && !$has_permission_issue): ?>
                    Your server configuration should allow 10MB uploads. If errors persist, check Apache error logs or contact your hosting provider.
                    <?php endif; ?>
                <?php else: ?>
                    Your server configuration appears correct. If you're still getting errors, check Apache/PHP error logs.
                <?php endif; ?>
            </small>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Images Section -->
        <div class="content-card">
            <div class="card-header-custom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-images me-2"></i>Promotional Images</h5>
                    <span class="badge bg-light text-dark"><?php echo count(array_filter($kit_items, function($item) { return $item['type'] == 'image'; })); ?> items</span>
                </div>
            </div>
            <div class="card-body p-4">
                <?php 
                $image_items = array_filter($kit_items, function($item) { return $item['type'] == 'image'; });
                if (!empty($image_items)): ?>
                    <div class="row g-4">
                        <?php foreach ($image_items as $item): ?>
                            <div class="col-md-4 col-lg-3">
                                <div class="kit-item-card">
                                    <img src="../franchisee/kit/uploads/<?php echo htmlspecialchars($item['file_path']); ?>" 
                                         class="kit-image" 
                                         alt="<?php echo htmlspecialchars($item['title'] ?: 'Untitled'); ?>">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-2"><?php echo htmlspecialchars($item['title'] ?: 'Untitled Image'); ?></h6>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <small class="text-muted">Order: <?php echo $item['display_order']; ?></small>
                                            <span class="status-badge <?php echo $item['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo ucfirst($item['status']); ?>
                                            </span>
                                        </div>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-outline-primary btn-action" 
                                                    onclick="editItem(<?php echo $item['id']; ?>, 'image')" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-<?php echo $item['status'] == 'active' ? 'warning' : 'success'; ?> btn-action" 
                                                    onclick="toggleStatus(<?php echo $item['id']; ?>, '<?php echo $item['status']; ?>')" 
                                                    title="<?php echo $item['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-<?php echo $item['status'] == 'active' ? 'eye-slash' : 'eye'; ?>"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-action" 
                                                    onclick="deleteItem(<?php echo $item['id']; ?>, 'image')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-images"></i>
                        <h5>No Images Added Yet</h5>
                        <p>Start building your franchisee kit by adding promotional images</p>
                        <button type="button" class="btn btn-custom-warning" data-bs-toggle="modal" data-bs-target="#addImageModal">
                            <i class="fas fa-plus me-2"></i>Add Your First Image
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Videos Section -->
        <div class="content-card">
            <div class="card-header-custom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-video me-2"></i>Video Resources</h5>
                    <span class="badge bg-light text-dark"><?php echo count(array_filter($kit_items, function($item) { return $item['type'] == 'video'; })); ?> items</span>
                </div>
            </div>
            <div class="card-body p-4">
                <?php 
                $video_items = array_filter($kit_items, function($item) { return $item['type'] == 'video'; });
                if (!empty($video_items)): ?>
                    <div class="row g-4">
                        <?php foreach ($video_items as $item): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="kit-video-card">
                                    <div class="mb-3">
                                        <i class="fas fa-play-circle" style="font-size: 3rem; color: #667eea;"></i>
                                    </div>
                                    <h6 class="card-title mb-2"><?php echo htmlspecialchars($item['title'] ?: 'Untitled Video'); ?></h6>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <small class="text-muted">Order: <?php echo $item['display_order']; ?></small>
                                        <span class="status-badge <?php echo $item['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo ucfirst($item['status']); ?>
                                        </span>
                                    </div>
                                    <p class="card-text mb-3">
                                        <small class="text-break"><?php echo htmlspecialchars($item['video_url']); ?></small>
                                    </p>
                                    <div class="mb-3">
                                        <a href="<?php echo htmlspecialchars($item['video_url']); ?>" 
                                           target="_blank" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-external-link-alt me-1"></i>Open in YouTube
                                        </a>
                                    </div>
                                    <div class="action-buttons">
                                        <button type="button" class="btn btn-outline-primary btn-action" 
                                                onclick="editItem(<?php echo $item['id']; ?>, 'video')" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-<?php echo $item['status'] == 'active' ? 'warning' : 'success'; ?> btn-action" 
                                                onclick="toggleStatus(<?php echo $item['id']; ?>, '<?php echo $item['status']; ?>')" 
                                                title="<?php echo $item['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fas fa-<?php echo $item['status'] == 'active' ? 'eye-slash' : 'eye'; ?>"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-action" 
                                                onclick="deleteItem(<?php echo $item['id']; ?>, 'video')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-video"></i>
                        <h5>No Videos Added Yet</h5>
                        <p>Add YouTube video links to help franchisees with training and marketing</p>
                        <button type="button" class="btn btn-custom-success" data-bs-toggle="modal" data-bs-target="#addVideoModal">
                            <i class="fas fa-plus me-2"></i>Add Your First Video
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Files Section -->
        <div class="content-card">
            <div class="card-header-custom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file me-2"></i>File Resources</h5>
                    <span class="badge bg-light text-dark"><?php echo count(array_filter($kit_items, function($item) { return $item['type'] == 'file'; })); ?> items</span>
                </div>
            </div>
            <div class="card-body p-4">
                <?php 
                $file_items = array_filter($kit_items, function($item) { return $item['type'] == 'file'; });
                if (!empty($file_items)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>File Name</th>
                                    <th>Title</th>
                                    <th>File Type</th>
                                    <th>Size</th>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th>Upload Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($file_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-file me-2 text-primary"></i>
                                                <span><?php echo htmlspecialchars($item['file_path']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['title'] ?: 'Untitled File'); ?></td>
                                        <td>
                                            <?php 
                                            $file_extension = strtoupper(pathinfo($item['file_path'], PATHINFO_EXTENSION));
                                            echo $file_extension;
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $file_path = '../franchisee/kit/uploads/' . $item['file_path'];
                                            if (file_exists($file_path)) {
                                                $file_size = filesize($file_path);
                                                echo formatFileSize($file_size);
                                            } else {
                                                echo '<span class="text-muted">Unknown</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $item['display_order']; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $item['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo ucfirst($item['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php 
                                            $date_field = isset($item['created_at']) ? $item['created_at'] : (isset($item['uploaded_date']) ? $item['uploaded_date'] : 'now');
                                            echo date('d-m-Y H:i', strtotime($date_field)); 
                                        ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="../franchisee/kit/uploads/<?php echo htmlspecialchars($item['file_path']); ?>" 
                                                   target="_blank" 
                                                   class="btn btn-outline-info btn-action" 
                                                   title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-primary btn-action" 
                                                        onclick="editItem(<?php echo $item['id']; ?>, 'file')" 
                                                        title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-<?php echo $item['status'] == 'active' ? 'warning' : 'success'; ?> btn-action" 
                                                        onclick="toggleStatus(<?php echo $item['id']; ?>, '<?php echo $item['status']; ?>')" 
                                                        title="<?php echo $item['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $item['status'] == 'active' ? 'eye-slash' : 'eye'; ?>"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-action" 
                                                        onclick="deleteItem(<?php echo $item['id']; ?>, 'file')" 
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file"></i>
                        <h5>No Files Added Yet</h5>
                        <p>Upload documents, presentations, and other resources for franchisees</p>
                        <button type="button" class="btn btn-custom-primary" data-bs-toggle="modal" data-bs-target="#addfileModal">
                            <i class="fas fa-plus me-2"></i>Add Your First File
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Image Modal -->
    <div class="modal fade" id="addImageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-image me-2"></i>Add New Promotional Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add_image">
                        <input type="hidden" name="kit_category" value="<?php echo htmlspecialchars($current_kit); ?>">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="image_title" class="form-label">Image Title</label>
                                    <input type="text" class="form-control form-control-custom" id="image_title" name="image_title" 
                                           placeholder="Enter a descriptive title for the image">
                                </div>
                                <div class="mb-3">
                                    <label for="kit_image" class="form-label">Select Image File</label>
                                    <input type="file" class="form-control form-control-custom" id="kit_image" name="kit_image" 
                                           accept="image/*" required>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Supported formats: JPG, JPEG, PNG, GIF (Max size: 10MB)
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="image_order" class="form-label">Display Order</label>
                                    <input type="number" class="form-control form-control-custom" id="image_order" name="image_order" 
                                           value="0" min="0" placeholder="0">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Lower numbers appear first
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div id="imagePreview" class="mt-3" style="display: none;">
                                        <img id="previewImg" src="" alt="Preview" class="img-fluid rounded" style="max-height: 200px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-warning">
                            <i class="fas fa-upload me-2"></i>Upload Image
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Video Modal -->
    <div class="modal fade" id="addVideoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-video me-2"></i>Add New Video Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add_video">
                        <input type="hidden" name="kit_category" value="<?php echo htmlspecialchars($current_kit); ?>">
                        <div class="mb-3">
                            <label for="video_title" class="form-label">Video Title</label>
                            <input type="text" class="form-control form-control-custom" id="video_title" name="video_title" 
                                   placeholder="Enter a descriptive title for the video">
                        </div>
                        <div class="mb-3">
                            <label for="video_url" class="form-label">YouTube Video URL</label>
                            <input type="url" class="form-control form-control-custom" id="video_url" name="video_url" 
                                   placeholder="https://www.youtube.com/watch?v=..." required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Paste the full YouTube video URL
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="video_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control form-control-custom" id="video_order" name="video_order" 
                                   value="0" min="0" placeholder="0">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Lower numbers appear first
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-success">
                            <i class="fas fa-plus me-2"></i>Add Video
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add File Modal -->
    <div class="modal fade" id="addfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-file me-2"></i>Add New File Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add_file">
                        <input type="hidden" name="kit_category" value="<?php echo htmlspecialchars($current_kit); ?>">
                        <div class="mb-3">
                            <label for="file_title" class="form-label">File Title</label>
                            <input type="text" class="form-control form-control-custom" id="file_title" name="file_title" 
                                   placeholder="Enter a descriptive title for the file" required>
                        </div>
                        <div class="mb-3">
                            <label for="file_upload" class="form-label">Select File</label>
                            <input type="file" class="form-control form-control-custom" id="file_upload" name="file_upload" 
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.mp4,.avi,.mov,.mp3,.wav" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Supported formats: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR, MP4, AVI, MOV, MP3, WAV (Max size: 10MB)
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="file_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control form-control-custom" id="file_order" name="file_order" 
                                   value="0" min="0" placeholder="0">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Lower numbers appear first
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-primary">
                            <i class="fas fa-plus me-2"></i>Add File
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Image Modal -->
    <div class="modal fade" id="editImageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="edit_image">
                        <input type="hidden" name="item_id" id="edit_image_id">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="edit_image_title" class="form-label">Image Title</label>
                                    <input type="text" class="form-control form-control-custom" id="edit_image_title" name="edit_image_title" 
                                           placeholder="Enter a descriptive title for the image" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_kit_image" class="form-label">Update Image (Optional)</label>
                                    <input type="file" class="form-control form-control-custom" id="edit_kit_image" name="edit_kit_image" 
                                           accept="image/*">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Leave empty to keep the current image. Supported formats: JPG, JPEG, PNG, GIF (Max size: 10MB)
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_image_order" class="form-label">Display Order</label>
                                    <input type="number" class="form-control form-control-custom" id="edit_image_order" name="edit_image_order" 
                                           min="0" placeholder="0" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_image_status" class="form-label">Status</label>
                                    <select class="form-control form-control-custom" id="edit_image_status" name="edit_image_status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="text-center">
                                    <div id="editImagePreview" class="mt-3">
                                        <img id="editPreviewImg" src="" alt="Current Image" class="img-fluid rounded" style="max-height: 200px;">
                                        <p class="text-muted mt-2">Current Image</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-primary">
                            <i class="fas fa-save me-2"></i>Update Image
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Video Modal -->
    <div class="modal fade" id="editVideoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="edit_video">
                        <input type="hidden" name="item_id" id="edit_video_id">
                        <div class="mb-3">
                            <label for="edit_video_title" class="form-label">Video Title</label>
                            <input type="text" class="form-control form-control-custom" id="edit_video_title" name="edit_video_title" 
                                   placeholder="Enter a descriptive title for the video" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_video_url" class="form-label">YouTube Video URL</label>
                            <input type="url" class="form-control form-control-custom" id="edit_video_url" name="edit_video_url" 
                                   placeholder="https://www.youtube.com/watch?v=..." required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Paste the full YouTube video URL
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_video_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control form-control-custom" id="edit_video_order" name="edit_video_order" 
                                   min="0" placeholder="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_video_status" class="form-label">Status</label>
                            <select class="form-control form-control-custom" id="edit_video_status" name="edit_video_status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-primary">
                            <i class="fas fa-save me-2"></i>Update Video
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit File Modal -->
    <div class="modal fade" id="editFileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="edit_file">
                        <input type="hidden" name="item_id" id="edit_file_id">
                        <div class="mb-3">
                            <label for="edit_file_title" class="form-label">File Title</label>
                            <input type="text" class="form-control form-control-custom" id="edit_file_title" name="edit_file_title" 
                                   placeholder="Enter a descriptive title for the file" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_file_upload" class="form-label">Update File (Optional)</label>
                            <input type="file" class="form-control form-control-custom" id="edit_file_upload" name="edit_file_upload" 
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.mp4,.avi,.mov,.mp3,.wav">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Leave empty to keep the current file. Supported formats: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR, MP4, AVI, MOV, MP3, WAV (Max size: 10MB)
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_file_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control form-control-custom" id="edit_file_order" name="edit_file_order" 
                                   min="0" placeholder="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_file_status" class="form-label">Status</label>
                            <select class="form-control form-control-custom" id="edit_file_status" name="edit_file_status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-primary">
                            <i class="fas fa-save me-2"></i>Update File
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" name="item_id" id="delete_item_id">
                        <input type="hidden" name="item_type" id="delete_item_type">
                        <div class="text-center">
                            <i class="fas fa-trash-alt text-danger" style="font-size: 3rem; margin-bottom: 20px;"></i>
                            <h5>Are you sure?</h5>
                            <p class="text-muted">This action cannot be undone. The item will be permanently deleted.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Image preview functionality
        document.getElementById('kit_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });

        // Delete item functionality
        function deleteItem(itemId, itemType) {
            document.getElementById('delete_item_id').value = itemId;
            document.getElementById('delete_item_type').value = itemType;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Edit item functionality
        function editItem(itemId, itemType) {
            itemId = parseInt(itemId); // Ensure itemId is an integer
            
            if (itemType === 'image') {
                // Fetch image data and populate edit modal
                if (fetchImageData(itemId)) {
                    document.getElementById('edit_image_id').value = itemId;
                    new bootstrap.Modal(document.getElementById('editImageModal')).show();
                } else {
                    alert('Error: Could not find image data. Please refresh the page and try again.');
                }
            } else if (itemType === 'video') {
                // Fetch video data and populate edit modal
                if (fetchVideoData(itemId)) {
                    document.getElementById('edit_video_id').value = itemId;
                    new bootstrap.Modal(document.getElementById('editVideoModal')).show();
                } else {
                    alert('Error: Could not find video data. Please refresh the page and try again.');
                }
            } else if (itemType === 'file') {
                // Fetch file data and populate edit modal
                if (fetchFileData(itemId)) {
                    document.getElementById('edit_file_id').value = itemId;
                    new bootstrap.Modal(document.getElementById('editFileModal')).show();
                } else {
                    alert('Error: Could not find file data. Please refresh the page and try again.');
                }
            }
        }

        // Store kit items data globally
        const kitItemsData = {
            images: <?php 
                $image_items = array_values(array_filter($kit_items, function($item) { return $item['type'] == 'image'; }));
                echo json_encode($image_items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            ?>,
            videos: <?php 
                $video_items = array_values(array_filter($kit_items, function($item) { return $item['type'] == 'video'; }));
                echo json_encode($video_items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            ?>,
            files: <?php 
                $file_items = array_values(array_filter($kit_items, function($item) { return $item['type'] == 'file'; }));
                echo json_encode($file_items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            ?>
        };

        // Fetch image data for editing
        function fetchImageData(itemId) {
            try {
                itemId = parseInt(itemId);
                const item = kitItemsData.images.find(img => parseInt(img.id) === itemId);
                
                if (item) {
                    const titleEl = document.getElementById('edit_image_title');
                    const orderEl = document.getElementById('edit_image_order');
                    const statusEl = document.getElementById('edit_image_status');
                    const previewImg = document.getElementById('editPreviewImg');
                    
                    if (titleEl) titleEl.value = item.title || '';
                    if (orderEl) orderEl.value = item.display_order || 0;
                    if (statusEl) statusEl.value = item.status || 'active';
                    
                    // Set current image preview
                    if (previewImg && item.file_path) {
                        previewImg.src = '../franchisee/kit/uploads/' + item.file_path;
                        previewImg.style.display = 'block';
                    }
                    return true;
                }
                console.error('Image item not found. ItemId:', itemId, 'Available items:', kitItemsData.images);
                return false;
            } catch (error) {
                console.error('Error fetching image data:', error);
                return false;
            }
        }

        // Fetch video data for editing
        function fetchVideoData(itemId) {
            try {
                itemId = parseInt(itemId);
                const item = kitItemsData.videos.find(vid => parseInt(vid.id) === itemId);
                
                if (item) {
                    const titleEl = document.getElementById('edit_video_title');
                    const urlEl = document.getElementById('edit_video_url');
                    const orderEl = document.getElementById('edit_video_order');
                    const statusEl = document.getElementById('edit_video_status');
                    
                    if (titleEl) titleEl.value = item.title || '';
                    if (urlEl) urlEl.value = item.video_url || '';
                    if (orderEl) orderEl.value = item.display_order || 0;
                    if (statusEl) statusEl.value = item.status || 'active';
                    return true;
                }
                console.error('Video item not found. ItemId:', itemId, 'Available items:', kitItemsData.videos);
                return false;
            } catch (error) {
                console.error('Error fetching video data:', error);
                return false;
            }
        }

        // Fetch file data for editing
        function fetchFileData(itemId) {
            try {
                itemId = parseInt(itemId);
                const item = kitItemsData.files.find(file => parseInt(file.id) === itemId);
                
                if (item) {
                    const titleEl = document.getElementById('edit_file_title');
                    const orderEl = document.getElementById('edit_file_order');
                    const statusEl = document.getElementById('edit_file_status');
                    
                    if (titleEl) titleEl.value = item.title || '';
                    if (orderEl) orderEl.value = item.display_order || 0;
                    if (statusEl) statusEl.value = item.status || 'active';
                    return true;
                }
                console.error('File item not found. ItemId:', itemId, 'Available items:', kitItemsData.files);
                return false;
            } catch (error) {
                console.error('Error fetching file data:', error);
                return false;
            }
        }

        // Toggle item status
        function toggleStatus(itemId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = currentStatus === 'active' ? 'deactivate' : 'activate';
            
            if (confirm(`Are you sure you want to ${action} this item?`)) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="item_id" value="${itemId}">
                    <input type="hidden" name="new_status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>

</body>
</html>
