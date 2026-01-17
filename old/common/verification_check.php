<?php
/**
 * Verification Check Functions
 * Used to check if franchisee documents are verified and restrict access accordingly
 */

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
    echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fa fa-exclamation-triangle"></i>
        <strong>Document Verification Required!</strong> 
        Please complete your document verification to access this feature. 
        <a href="../verification/" class="alert-link">Verify Documents</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

// Function to redirect to verification page
function redirectToVerification() {
    header('Location: ../verification/');
    exit();
}
?>
