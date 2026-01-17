<?php
require_once(__DIR__ . '/../common/config.php');

// Check if team member is already logged in (using old session names)
if (!empty($_SESSION['user_email']) && !empty($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    header('Location: dashboard/');
    exit;
}

$login_messages = [];

if (isset($_GET['session']) && $_GET['session'] === 'expired') {
    $login_messages[] = ['type' => 'info', 'message' => 'Your session has expired. Please log in again.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $login_messages[] = ['type' => 'danger', 'message' => 'Please enter a valid email address.'];
    }
    if ($password === '') {
        $login_messages[] = ['type' => 'danger', 'message' => 'Please enter your password.'];
    }

    if (empty($login_messages)) {
        // Authenticate against team_members table
        $stmt = $connect->prepare('SELECT id, member_name, member_email, password_hash, status, referral_code FROM team_members WHERE member_email = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $member = $result->fetch_assoc();
            $stmt->close();

            if ($member) {
                // Verify password using password_hash field
                if (password_verify($password, $member['password_hash'])) {
                    // Check if account is active
                    if ($member['status'] !== 'ACTIVE') {
                        $login_messages[] = ['type' => 'info', 'message' => 'This team member account is currently inactive. Please contact the administrator.'];
                    } else {
                        // Generate referral code if it doesn't exist
                        $referralCode = $member['referral_code'] ?? '';
                        if (empty($referralCode)) {
                            $referralCode = strtoupper(substr(md5($member['member_email'] . time()), 0, 8));
                            $updateRefStmt = $connect->prepare('UPDATE team_members SET referral_code = ? WHERE id = ?');
                            if ($updateRefStmt) {
                                $updateRefStmt->bind_param('si', $referralCode, $member['id']);
                                $updateRefStmt->execute();
                                $updateRefStmt->close();
                            }
                        }
                        
                        // Set session variables using old names
                        $_SESSION['user_email'] = $member['member_email'];
                        $_SESSION['user_name'] = $member['member_name'];
                        $_SESSION['user_referral_code'] = $referralCode;
                        $_SESSION['is_logged_in'] = true;
                        $_SESSION['login_time'] = time();
                        $_SESSION['team_member_id'] = $member['id']; // Keep team_member_id for reference

                        // Update last login timestamp
                        $updateStmt = $connect->prepare('UPDATE team_members SET last_login_at = NOW() WHERE id = ?');
                        if ($updateStmt) {
                            $updateStmt->bind_param('i', $member['id']);
                            $updateStmt->execute();
                            $updateStmt->close();
                        }

                        // Redirect to dashboard on success
                        header('Location: dashboard/');
                        exit;
                    }
                } else {
                    // Wrong password - stay on login page (no redirect)
                    $login_messages[] = ['type' => 'danger', 'message' => 'Invalid login credentials. Please try again.'];
                }
            } else {
                // User not found - stay on login page (no redirect)
                $login_messages[] = ['type' => 'danger', 'message' => 'Invalid login credentials. Please try again.'];
            }
        } else {
            error_log('Team login prepare failed: ' . $connect->error);
            $login_messages[] = ['type' => 'danger', 'message' => 'Unable to process login right now. Please try again later.'];
        }
    }
}

$login_error = '';
if (!empty($login_messages)) {
    foreach ($login_messages as $alert) {
        $message = htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8');
        $type = htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8');
        $login_error .= '<div class="alert ' . $type . ' error-msg">' . $message . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"/>
    <title>Team Login</title>
    <link rel="apple-touch-icon" sizes="180x180" href="../panel/v1/assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../panel/v1/assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="../panel/v1/assets/images/favicon.ico">
    <link rel="manifest" href="../panel/v1/assets/images/favicon.ico">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Baloo+Bhai+2:wght@400..800&family=Baloo+Bhaina+2:wght@400..800&family=Barlow:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="../panel/v1/assets/css/font-awesome.css">
    <link rel="stylesheet" href="../panel/v1/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../panel/v1/assets/css/animate.css">
    <link rel="stylesheet" href="../panel/v1/assets/css/layout.css">
    <link rel="stylesheet" href="../panel/v1/assets/css/responsive.css">
    <link rel="stylesheet" href="../panel/login/css.css">
    <link rel="stylesheet" href="../panel/login/mobile_css.css">
    <link rel="stylesheet" href="../panel/login/login-styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../panel/v1/assets/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="login-wrap">

    <div class="login-container">
        <h2 class="heading"><a href="../"><i class="fa fa-angle-left" aria-hidden="true"></i></a>Team Login</h2>
        <p class="text-white">Please enter your team credentials</p>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" autocomplete="off" id="team-login">
            <div class="mb-4">
                <input type="email" class="form-control" name="email" id="email" placeholder="Enter Team Email*" required>
            </div>
            <div class="mb-5 position-relative">
                <div class="input-group mb-2">
                    <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
                    <div class="input-group-prepend">
                        <div class="input-group-text password-toggle" id="password-toggle" onclick="showpassword()" title="Show/Hide Password">
                            <i class="fa fa-eye eye-closed" aria-hidden="true"></i>
                            <i class="fa fa-eye-slash eye-open" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
            </div>
            <input type="submit" value="LOG IN" class="btn btn-login">
        </form>
        <?php echo $login_error; ?>
         
    </div>
</div>

<script>
    function showpassword(){
        const passwordField = document.getElementById("password");
        const passwordToggle = document.getElementById("password-toggle");

        if(passwordField.type === "password") {
            passwordField.type = "text";
            passwordToggle.classList.add("visible");
        } else {
            passwordField.type = "password";
            passwordToggle.classList.remove("visible");
        }
    }
</script>
</body>
</html>
