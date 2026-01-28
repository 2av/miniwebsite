<?php
/**
 * Verification Check Functions
 * Used to check if franchisee documents are verified and restrict access accordingly
 */

// Include database connection
require_once(__DIR__ . '/../config/database.php');

// Resolve base path of the app (works whether deployed at /, /miniwebsite, or any subfolder)
if (!function_exists('verification_get_base_path')) {
    function verification_get_base_path() {
        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
        $scriptDir  = rtrim(dirname($scriptPath), '/');

        // Break into segments and strip everything after the first context segment
        $segments = array_values(array_filter(explode('/', $scriptDir), 'strlen'));
        $contexts = ['user', 'admin', 'login'];
        $cutIndex = null;

        foreach ($segments as $idx => $seg) {
            if (in_array(strtolower($seg), $contexts, true)) {
                $cutIndex = $idx;
                break;
            }
        }

        if ($cutIndex !== null) {
            $baseSegments = array_slice($segments, 0, $cutIndex);
        } else {
            $baseSegments = $segments;
        }

        if (empty($baseSegments)) {
            return '';
        }

        return '/' . implode('/', $baseSegments);
    }
}

// Function to check if franchisee documents are verified
function isFranchiseeVerified($user_email) {
    global $connect;
    
    if(empty($user_email)) {
        return false;
    }
    
    try {
        $stmt = $connect->prepare("SELECT status FROM franchisee_verification WHERE user_email = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("s", $user_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result && $row = $result->fetch_assoc()) {
            return $row['status'] === 'approved';
        }
        
        $stmt->close();
        return false; // No verification record found
    } catch(Exception $e) {
        error_log("Error checking franchisee verification: " . $e->getMessage());
        return false;
    }
}

// Function to get verification status
function getVerificationStatus($user_email) {
    global $connect;
    
    if(empty($user_email)) {
        return 'pending';
    }
    
    try {
        $stmt = $connect->prepare("SELECT status FROM franchisee_verification WHERE user_email = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("s", $user_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result && $row = $result->fetch_assoc()) {
            return $row['status'];
        }
        
        $stmt->close();
        return 'pending'; // No verification record found
    } catch(Exception $e) {
        error_log("Error getting verification status: " . $e->getMessage());
        return 'pending';
    }
}

// Function to show verification warning
function showVerificationWarning() {
    $basePath = verification_get_base_path();
    $verificationUrl = $basePath . '/user/verification/';
    echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fa fa-exclamation-triangle"></i>
        <strong>Document Verification Required!</strong> 
        Please complete your document verification to access this feature. 
        <a href="'.htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8').'" class="alert-link">Verify Documents</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

// Function to redirect to verification page
function redirectToVerification() {
    $basePath = verification_get_base_path();
    $verificationUrl = $basePath . '/user/verification/';
    header('Location: ' . $verificationUrl);
    exit();
}
?>
