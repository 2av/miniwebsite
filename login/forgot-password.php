<?php
// Common password reset page for CUSTOMER, FRANCHISEE, TEAM
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/password_helper.php';

// Try to load PHPMailer + email config (best-effort, will fall back to mail())
$hasMailer = false;
$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../panel/vendor/autoload.php',
    __DIR__ . '/../panel/login/vendor/autoload.php',
];
foreach ($autoloadCandidates as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $hasMailer = true;
        break;
    }
}
require_once __DIR__ . '/../app/config/email.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure password_resets table exists
$createSql = "
CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    role ENUM('CUSTOMER','FRANCHISEE','TEAM','ADMIN') NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    used_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_role (email, role),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$connect->query($createSql);

function fp_send_otp_email(string $to, string $name, string $otp, string $roleLabel): bool {
    global $hasMailer;

    $safeName = $name !== '' ? $name : 'User';
    $subject  = "Password Reset OTP for {$roleLabel} Login";
    $body     = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <p style="font-size: 16px; color: #333;">Hi <strong>' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
            <p style="font-size: 16px; color: #333;">
                We received a request to reset the password for your <strong>' . htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') . '</strong> account.
            </p>
            <p style="font-size: 16px; color: #333;">
                Please use the following One-Time Password (OTP) to reset your password. This OTP is valid for 10 minutes.
            </p>
            <p style="font-size: 28px; font-weight: bold; letter-spacing: 4px; color: #1a73e8; text-align: center; margin: 25px 0;">'
                . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') .
            '</p>
            <p style="font-size: 14px; color: #666;">
                If you did not request this change, you can safely ignore this email.
            </p>
            <p style="font-size: 14px; color: #666; margin-top: 25px;">
                Regards,<br>
                <strong>MiniWebsite Support</strong>
            </p>
        </div>';

    if ($hasMailer) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = SMTP_AUTH;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];
            $mail->setFrom(SMTP_USERNAME, 'MiniWebsite Support');
            $mail->addAddress($to, $safeName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $body));
            return $mail->send();
        } catch (Exception $e) {
            error_log('Forgot password OTP mail failed: ' . $e->getMessage());
        }
    }

    // Fallback: basic mail()
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: MiniWebsite Support <' . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'miniwebsite.in')) . ">\r\n";
    return @mail($to, $subject, $body, $headers);
}

// Helper for base path (used for links back to site)
function fp_get_base_path() {
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $script_dir  = dirname($script_name);
    $base        = str_replace('/login', '', $script_dir);
    return $base === '/' ? '' : $base;
}
$base_path = fp_get_base_path();

// Map roles to labels and login URLs
$roleLabels = [
    'CUSTOMER'   => 'Customer',
    'FRANCHISEE' => 'Franchisee',
    'TEAM'       => 'Team',
];
$loginUrls = [
    'CUSTOMER'   => $base_path . '/login/customer.php',
    'FRANCHISEE' => $base_path . '/login/franchisee.php',
    'TEAM'       => $base_path . '/login/team.php',
];

$currentRole = strtoupper(trim($_GET['role'] ?? ''));
if (!isset($roleLabels[$currentRole])) {
    $currentRole = 'CUSTOMER';
}

$step = 'request'; // request or verify
$messages = [];
$prefillIdentifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_otp') {
        $step         = 'request';
        $identifier   = trim($_POST['identifier'] ?? '');
        $prefillIdentifier = $identifier;

        if ($identifier === '') {
            $messages[] = ['type' => 'danger', 'text' => 'Please enter your Email ID or Mobile Number.'];
        } elseif (!filter_var($identifier, FILTER_VALIDATE_EMAIL) && !preg_match('/^[0-9]{10}$/', $identifier)) {
            $messages[] = ['type' => 'danger', 'text' => 'Enter a valid Email ID or 10-digit Mobile Number.'];
        } else {
            // Look up user in user_details (auto-detect role from email/phone - emails are unique)
            $stmt = $connect->prepare('SELECT id, email, phone, name, role FROM user_details WHERE email = ? OR phone = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('ss', $identifier, $identifier);
                $stmt->execute();
                $result = $stmt->get_result();
                $user   = $result ? $result->fetch_assoc() : null;
                $stmt->close();

                if ($user) {
                    $targetEmail = $user['email'] ?? '';
                    $targetName  = $user['name'] ?? '';
                    $detectedRole = strtoupper(trim($user['role'] ?? ''));

                    // Only allow CUSTOMER, FRANCHISEE, TEAM roles
                    if (!isset($roleLabels[$detectedRole])) {
                        $messages[] = ['type' => 'danger', 'text' => 'Password reset is not available for this account type.'];
                    } elseif ($targetEmail !== '') {
                        // Generate 6-digit OTP and store hash
                        $otp      = (string)random_int(100000, 999999);
                        $otpHash  = password_hash($otp, PASSWORD_DEFAULT);

                        // Invalidate previous unused tokens for this email/role
                        $upd = $connect->prepare('UPDATE password_resets SET used = 1, used_at = NOW() WHERE email = ? AND role = ? AND used = 0');
                        if ($upd) {
                            $upd->bind_param('ss', $targetEmail, $detectedRole);
                            $upd->execute();
                            $upd->close();
                        }

                        // Insert new reset record
                        $insert = $connect->prepare('INSERT INTO password_resets (email, role, otp_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
                        if ($insert) {
                            $insert->bind_param('sss', $targetEmail, $detectedRole, $otpHash);
                            $insert->execute();
                            $insert->close();
                        }

                        // Send OTP email
                        fp_send_otp_email($targetEmail, $targetName, $otp, $roleLabels[$detectedRole]);
                        
                        // Store detected role for verify step
                        $currentRole = $detectedRole;
                    }
                }

                // Always show generic message (do not reveal if user exists)
                $messages[] = ['type' => 'success', 'text' => 'If an account exists for the provided details, an OTP has been sent to the registered email address.'];
                $step = 'verify';
            } else {
                $messages[] = ['type' => 'danger', 'text' => 'Unable to process your request right now. Please try again later.'];
            }
        }
    } elseif ($action === 'verify_otp') {
        $step       = 'verify';
        $identifier = trim($_POST['identifier'] ?? '');
        $otp        = trim($_POST['otp'] ?? '');
        $newPw      = (string)($_POST['new_password'] ?? '');
        $confPw     = (string)($_POST['confirm_password'] ?? '');
        $prefillIdentifier = $identifier;

        if ($identifier === '' || $otp === '' || $newPw === '' || $confPw === '') {
            $messages[] = ['type' => 'danger', 'text' => 'All fields are required.'];
        } elseif ($newPw !== $confPw) {
            $messages[] = ['type' => 'danger', 'text' => 'New password and confirm password do not match.'];
        } elseif (strlen($newPw) < 6) {
            $messages[] = ['type' => 'danger', 'text' => 'Password must be at least 6 characters long.'];
        } else {
            // Auto-detect role from identifier (emails are unique)
            $stmt = $connect->prepare('SELECT id, email, role FROM user_details WHERE email = ? OR phone = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('ss', $identifier, $identifier);
                $stmt->execute();
                $result = $stmt->get_result();
                $user   = $result ? $result->fetch_assoc() : null;
                $stmt->close();

                if ($user && !empty($user['email'])) {
                    $email = $user['email'];
                    $detectedRole = strtoupper(trim($user['role'] ?? ''));

                    // Only allow CUSTOMER, FRANCHISEE, TEAM roles
                    if (!isset($roleLabels[$detectedRole])) {
                        $messages[] = ['type' => 'danger', 'text' => 'Password reset is not available for this account type.'];
                    } else {
                        // Find latest non-used, non-expired reset entry
                        $rs = $connect->prepare('SELECT id, otp_hash, expires_at FROM password_resets WHERE email = ? AND role = ? AND used = 0 AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
                        if ($rs) {
                            $rs->bind_param('ss', $email, $detectedRole);
                            $rs->execute();
                            $res = $rs->get_result();
                            $resetRow = $res ? $res->fetch_assoc() : null;
                            $rs->close();

                            if ($resetRow && !empty($resetRow['otp_hash']) && password_verify($otp, $resetRow['otp_hash'])) {
                                // Mark token as used
                                $upd = $connect->prepare('UPDATE password_resets SET used = 1, used_at = NOW() WHERE id = ?');
                                if ($upd) {
                                    $id = (int)$resetRow['id'];
                                    $upd->bind_param('i', $id);
                                    $upd->execute();
                                    $upd->close();
                                }

                                // Update user password (hash stored in both columns)
                                $hash = mw_hash_password($newPw);
                                $upUser = $connect->prepare('UPDATE user_details SET password = ?, password_hash = ?, updated_at = NOW() WHERE id = ? AND role = ?');
                                if ($upUser) {
                                    $uid = (int)$user['id'];
                                    $upUser->bind_param('ssis', $hash, $hash, $uid, $detectedRole);
                                    $upUser->execute();
                                    $upUser->close();

                                    $messages[] = ['type' => 'success', 'text' => 'Password updated successfully. You can now log in with your new password.'];
                                    $step = 'done';
                                    $currentRole = $detectedRole;
                                } else {
                                    $messages[] = ['type' => 'danger', 'text' => 'Failed to update password. Please try again.'];
                                }
                            } else {
                                $messages[] = ['type' => 'danger', 'text' => 'Invalid or expired OTP. Please request a new one.'];
                            }
                        } else {
                            $messages[] = ['type' => 'danger', 'text' => 'Unable to verify OTP right now. Please try again later.'];
                        }
                    }
                } else {
                    $messages[] = ['type' => 'danger', 'text' => 'Invalid account details. Please check your Email/Mobile.'];
                }
            } else {
                $messages[] = ['type' => 'danger', 'text' => 'Unable to verify OTP right now. Please try again later.'];
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon.ico">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Baloo+Bhai+2:wght@400..800&family=Baloo+Bhaina+2:wght@400..800&family=Barlow:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="../assets/css/font-awesome.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/layout.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <!-- Keep visual style consistent with existing login pages -->
    <style>
        .heading {
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .heading a {
            color: #fff;
        }
        .alert {
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 13px;
        }
        .alert.success { background: #d4edda; color:#155724; }
        .alert.info    { background: #d1ecf1; color:#0c5460; }
        .alert.danger  { background: #f8d7da; color:#721c24; }
        .role-badge {
            font-size: 11px;
            background: rgba(255,255,255,0.1);
            padding: 4px 8px;
            border-radius: 999px;
            margin-left: 4px;
        }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-container">
        <h2 class="heading">
            <a href="<?php echo htmlspecialchars($base_path, ENT_QUOTES, 'UTF-8'); ?>/">
                <i class="fa fa-angle-left" aria-hidden="true"></i>
            </a>
            Forgot Password
        </h2>
        <p class="text-white mb-2" style="font-size: 14px;">
            Reset your password using an OTP sent to your registered email.
        </p>

        <?php foreach ($messages as $m): ?>
            <div class="alert <?php echo htmlspecialchars($m['type'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($m['text'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endforeach; ?>

        <?php if ($step === 'done'): ?>
            <?php if (isset($loginUrls[$currentRole])): ?>
                <p class="mt-3" style="font-size: 14px; color: #fff;">
                    Go to
                    <a href="<?php echo htmlspecialchars($loginUrls[$currentRole], ENT_QUOTES, 'UTF-8'); ?>" class="text-warning">
                        <?php echo htmlspecialchars($roleLabels[$currentRole], ENT_QUOTES, 'UTF-8'); ?> Login
                    </a>
                </p>
            <?php endif; ?>
        <?php else: ?>
            <form action="" method="post" autocomplete="off" class="mt-3">
                <input type="hidden" name="action" value="<?php echo $step === 'verify' ? 'verify_otp' : 'request_otp'; ?>">
                <div class="mb-3">
                    <label class="form-label text-white" style="font-size: 13px;">Registered Email ID or Mobile Number</label>
                    <input
                        type="text"
                        class="form-control"
                        name="identifier"
                        value="<?php echo htmlspecialchars($prefillIdentifier, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Enter your Email ID or Mobile Number"
                        required
                    >
                </div>

                <?php if ($step === 'verify'): ?>
                    <input type="hidden" name="identifier" value="<?php echo htmlspecialchars($prefillIdentifier, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mb-3">
                        <label class="form-label text-white" style="font-size: 13px;">OTP</label>
                        <input
                            type="text"
                            class="form-control"
                            name="otp"
                            placeholder="Enter 6-digit OTP sent to your email"
                            required
                        >
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-white" style="font-size: 13px;">New Password</label>
                        <input
                            type="password"
                            class="form-control"
                            name="new_password"
                            placeholder="Enter new password (min 6 characters)"
                            required
                        >
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-white" style="font-size: 13px;">Confirm New Password</label>
                        <input
                            type="password"
                            class="form-control"
                            name="confirm_password"
                            placeholder="Confirm new password"
                            required
                        >
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-login w-100">
                    <?php if ($step === 'verify'): ?>
                        Verify OTP &amp; Reset Password
                    <?php else: ?>
                        Send OTP
                    <?php endif; ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="../assets/js/jquery.slim.min.js"></script>
<script>
  if (!window.jQuery) {
    document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
  }
</script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
  if (!window.bootstrap && !(window.jQuery && window.jQuery.fn && window.jQuery.fn.dropdown)) {
    document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"><\/script>');
  }
</script>
</body>
</html>

