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

/**
 * True when franchise registration (agreement) payment succeeded for this email.
 * Uses franchise_payments when present, else invoice_details (Franchisee registration rows).
 */
function isFranchiseeRegistrationAgreementPaid($user_email) {
    global $connect;

    if (empty($user_email) || !($connect instanceof mysqli)) {
        return false;
    }

    $email = trim((string) $user_email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    try {
        $tbl = @mysqli_query($connect, "SHOW TABLES LIKE 'franchise_payments'");
        if ($tbl) {
            $has_fp = mysqli_num_rows($tbl) > 0;
            mysqli_free_result($tbl);
        } else {
            $has_fp = false;
        }
        if ($has_fp) {
            $stmt = $connect->prepare('SELECT 1 FROM franchise_payments WHERE franchise_email = ? AND LOWER(TRIM(payment_status)) = ? LIMIT 1');
            if ($stmt) {
                $ok = 'success';
                $stmt->bind_param('ss', $email, $ok);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $stmt->close();
                    return true;
                }
                $stmt->close();
            }
        }

        $stmt = $connect->prepare("SELECT 1 FROM invoice_details WHERE (user_email = ? OR billing_email = ?) AND LOWER(TRIM(payment_status)) IN ('success', 'paid') AND (service_name = 'Franchisee Registration Fees' OR payment_type = 'Franchisee' OR reference_number LIKE 'FRAN%') LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ss', $email, $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $stmt->close();
                return true;
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('isFranchiseeRegistrationAgreementPaid: ' . $e->getMessage());
    }

    return false;
}

/**
 * Redirect franchisee to agreement payment page until registration is paid.
 */
function redirectFranchiseeToAgreementUntilPaid($franchise_email) {
    if (empty(trim((string) $franchise_email))) {
        return;
    }
    if (isFranchiseeRegistrationAgreementPaid($franchise_email)) {
        return;
    }
    $basePath = verification_get_base_path();
    $q = rawurlencode(trim((string) $franchise_email));
    header('Location: ' . $basePath . '/franchise_agreement.php?email=' . $q . '&unlock=1');
    exit();
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
