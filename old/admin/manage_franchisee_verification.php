<?php
include_once('header.php');

// Include PHPMailer and email configuration
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../common/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to send verification email using PHPMailer
function sendVerificationEmail($user_email, $action, $remarks = '') {
    // Get franchisee name from database
    global $connect;
    $franchisee_name = 'Franchisee';
    try {
        // Query user_details table for franchisee
        $stmt = $connect->prepare("SELECT name FROM user_details WHERE email = ? AND role='FRANCHISEE'");
        $stmt->bind_param("s", $user_email);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result && $row = $result->fetch_assoc()) {
            $franchisee_name = $row['name'] ?: 'Franchisee';
        }
        $stmt->close();
    } catch(Exception $e) {
        // Use default name if query fails
    }
    
    $subject = "MiniWebsite.in – Document verification";
    
    if($action == 'approve') {
        // Approved email template
        $message = "Hi " . $franchisee_name . ",<br><br>";
        $message .= "Thank you for registering as a franchisee with MiniWebsite.in.<br><br>";
        $message .= "Congratulation! The verification documents are approved by Miniwebsite Team.<br>";
        $message .= "You can access your Franchisee Kit from your Dashboard and start your business immediately.<br><br>";
        $message .= "If you have any questions or need assistance, feel free to reach out to our support team.<br><br>";
        $message .= "Best regards,<br>";
        $message .= "Team MiniWebsite.in<br>";
        $message .= "www.miniwebsite.in";
    } else {
        // Rejected email template
        $message = "Hi " . $franchisee_name . ",<br><br>";
        $message .= "Thank you for registering as a franchisee with MiniWebsite.in.<br><br>";
        $message .= "The documents uploaded for verification is not approved by Miniwebsite Team.<br><br>";
        $message .= "Please check the reason:<br>";
        $message .= (!empty($remarks) ? $remarks : "Please upload clear and valid documents.") . "<br><br>";
        $message .= "If you have any questions or need assistance, feel free to reach out to our support team.<br><br>";
        $message .= "Best regards,<br>";
        $message .= "Team MiniWebsite.in<br>";
        $message .= "www.miniwebsite.in";
    }
    
    try {
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = SMTP_AUTH;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Additional SMTP settings for better compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(SMTP_USERNAME, 'MiniWebsite Support');
        $mail->addAddress($user_email, $franchisee_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));
        
        // Send the email
        $mail_sent = $mail->send();
        
        // Log email sending attempt
        error_log("Verification email sent to: $user_email, Action: $action, Status: " . ($mail_sent ? 'Success' : 'Failed'));
        
        return $mail_sent;
    } catch (Exception $e) {
        error_log("Verification email failed: " . $e->getMessage());
        return false;
    }
}

// Handle admin actions
if(isset($_POST['action']) && isset($_POST['user_email'])) {
    $user_email = $_POST['user_email'];
    $action = $_POST['action'];
    $remarks = isset($_POST['remarks']) ? $_POST['remarks'] : '';
    
    if($action == 'approve' || $action == 'reject') {
        $status = ($action == 'approve') ? 'approved' : 'rejected';
        
        try {
            $stmt = $connect->prepare("UPDATE franchisee_verification SET status = ?, admin_remarks = ?, reviewed_at = NOW(), reviewed_by = ? WHERE user_email = ?");
            $admin_email = $_SESSION['admin_email'] ?? 'admin';
            $stmt->bind_param("ssss", $status, $remarks, $admin_email, $user_email);
            $stmt->execute();
            $stmt->close();
            
            // Send email notification
            sendVerificationEmail($user_email, $action, $remarks);
            
            $success_message = "Verification status updated successfully!";
        } catch(Exception $e) {
            $error_message = "Error updating status: " . $e->getMessage();
        }
    }
}

// Get all verification requests
$verifications = [];
try {
    $query = "SELECT v.*, f.name as franchisee_name, f.phone as franchisee_phone 
              FROM franchisee_verification v 
              LEFT JOIN franchisee_users f ON v.user_email = f.email 
              ORDER BY v.submitted_at DESC";
    $result = $connect->query($query);
    
    if($result) {
        while($row = $result->fetch_assoc()) {
            $verifications[] = $row;
        }
    }
} catch(Exception $e) {
    $error_message = "Error fetching verifications: " . $e->getMessage();
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Manage Franchisee Verification</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Franchisee Verification</li>
    </ol>
    
    <?php if(isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if(isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Document Verification Requests</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="verificationTable">
                    <thead>
                        <tr>
                            <th>Franchisee</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Documents</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($verifications as $verification): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($verification['franchisee_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($verification['user_email']); ?></td>
                                <td><?php echo htmlspecialchars($verification['franchisee_phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if(!empty($verification['gpay_document'])): ?>
                                        <a href="../franchisee/verification/uploads/<?php echo $verification['gpay_document']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">GPay</a>
                                    <?php endif; ?>
                                    <?php if(!empty($verification['paytm_document'])): ?>
                                        <a href="../franchisee/verification/uploads/<?php echo $verification['paytm_document']; ?>" target="_blank" class="btn btn-sm btn-outline-success">Paytm</a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    switch($verification['status']) {
                                        case 'pending':
                                            $status_class = 'badge bg-secondary';
                                            $status_text = 'Pending';
                                            break;
                                        case 'submitted':
                                            $status_class = 'badge bg-warning';
                                            $status_text = 'Submitted';
                                            break;
                                        case 'approved':
                                            $status_class = 'badge bg-success';
                                            $status_text = 'Approved';
                                            break;
                                        case 'rejected':
                                            $status_class = 'badge bg-danger';
                                            $status_text = 'Rejected';
                                            break;
                                    }
                                    ?>
                                    <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td>
                                    <?php if($verification['submitted_at']): ?>
                                        <?php echo date('d M Y, h:i A', strtotime($verification['submitted_at'])); ?>
                                    <?php else: ?>
                                        Not submitted
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($verification['status'] == 'submitted'): ?>
                                        <button type="button" class="btn btn-sm btn-success" onclick="approveVerification('<?php echo $verification['user_email']; ?>')">
                                            <i class="fa fa-check"></i> Approve
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="rejectVerification('<?php echo $verification['user_email']; ?>')">
                                            <i class="fa fa-times"></i> Reject
                                        </button>
                                    <?php elseif($verification['status'] == 'approved'): ?>
                                        <span class="text-success"><i class="fa fa-check-circle"></i> Approved</span>
                                        <?php if($verification['reviewed_at']): ?>
                                            <br><small class="text-muted"><?php echo date('d M Y', strtotime($verification['reviewed_at'])); ?></small>
                                        <?php endif; ?>
                                    <?php elseif($verification['status'] == 'rejected'): ?>
                                        <span class="text-danger"><i class="fa fa-times-circle"></i> Rejected</span>
                                        <?php if($verification['reviewed_at']): ?>
                                            <br><small class="text-muted"><?php echo date('d M Y', strtotime($verification['reviewed_at'])); ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Verification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="user_email" id="approveUserEmail">
                    <p>Are you sure you want to approve this verification request?</p>
                    <div class="mb-3">
                        <label for="approveRemarks" class="form-label">Remarks (Optional)</label>
                        <textarea class="form-control" name="remarks" id="approveRemarks" rows="3" placeholder="Add any remarks..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Verification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="user_email" id="rejectUserEmail">
                    <p>Are you sure you want to reject this verification request?</p>
                    <div class="mb-3">
                        <label for="rejectRemarks" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" name="remarks" id="rejectRemarks" rows="3" placeholder="Please provide a reason for rejection..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveVerification(userEmail) {
    document.getElementById('approveUserEmail').value = userEmail;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function rejectVerification(userEmail) {
    document.getElementById('rejectUserEmail').value = userEmail;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

$(document).ready(function() {
    $('#verificationTable').DataTable({
        order: [[5, 'desc']], // Sort by submitted date descending
        pageLength: 25,
        responsive: true
    });
});
</script>

<?php include_once('footer.php'); ?>
