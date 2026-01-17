<?php
session_start();
require_once('../common/config.php');

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION['f_user_email'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
    exit;
}

$user_email = $_SESSION['f_user_email'];

// Check if file was uploaded
if(!isset($_FILES['profile_image']) || empty($_FILES['profile_image']['tmp_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'No image file selected. Please choose an image to upload.']);
    exit;
}

try {
    // Include file validation functions
    $file_validation_path = __DIR__ . '/../includes/file_validation.php';
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
    
    // Check file upload error
    if($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $_FILES['profile_image']['error']);
    }
    
    // Process image: auto crop to 1:1, resize to 600x600, compress to ~250KB
    $result = processImageUploadWithAutoCrop(
        $_FILES['profile_image'], 
        600,      // Target size: 600x600
        250000,   // Target file size: 250KB
        200000,   // Min file size: 200KB
        300000,   // Max file size: 300KB
        ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
        'jpeg',   // Output format
        null      // No specific destination, use temp file
    );
    
    if($result['status']) {
        // Create upload directory if it doesn't exist
        $upload_dir = __DIR__ . '/profile_images/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $unique_filename = uniqid() . '_' . time() . '.jpg';
        $upload_path = $upload_dir . $unique_filename;
        $db_path = 'profile_images/' . $unique_filename; // Store relative path in database
        
        // Save processed image to file
        if(file_put_contents($upload_path, $result['data'])) {
            // Get old profile image to delete (from franchisee_users table)
            $stmt = $connect->prepare("SELECT profile_image FROM franchisee_users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $user_email);
            $stmt->execute();
            $result_old = $stmt->get_result();
            $old_image = $result_old->fetch_assoc();
            $stmt->close();
            
            // Delete old image file if it exists
            if($old_image && !empty($old_image['profile_image'])) {
                // If image is stored as file path
                if(strpos($old_image['profile_image'], 'profile_images/') === 0) {
                    $old_file_path = __DIR__ . '/' . $old_image['profile_image'];
                    if(file_exists($old_file_path)) {
                        @unlink($old_file_path);
                    }
                }
            }
            
            // Update database with new image path (save to franchisee_users table)
            $stmt = $connect->prepare("UPDATE franchisee_users SET profile_image = ? WHERE email = ?");
            $stmt->bind_param("ss", $db_path, $user_email);
            
            if($stmt->execute()) {
                // Clean up temp file if it exists
                if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                    @unlink($result['file_path']);
                }
                
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Profile image updated successfully! Your new image will be visible immediately.',
                    'image_path' => $db_path
                ]);
            } else {
                // If database update fails, delete the uploaded file
                @unlink($upload_path);
                echo json_encode(['status' => 'error', 'message' => 'Failed to save image information. Please try again.']);
            }
            
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save processed image. Please try again.']);
        }
    } else {
        $errorMsg = isset($result['message']) ? strip_tags($result['message']) : 'Unknown error processing image';
        echo json_encode(['status' => 'error', 'message' => $errorMsg]);
    }
} catch(Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
} catch(Error $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Fatal error: ' . $e->getMessage()
    ]);
}
?>

