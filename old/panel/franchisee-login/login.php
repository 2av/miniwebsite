<title>Franchisee Login</title>
<?php
require('login-connect.php');

if (isset($_SESSION['sender_token'])) {
    $sender_token = $_SESSION['sender_token'];
} else {
    $sender_token = '';
}

$login_error = '';

// Check if redirected due to session expiration
if (isset($_GET['session']) && $_GET['session'] === 'expired') {
    $login_error = '<div class="alert info error-msg">Your session has expired. Please log in again.</div>';
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_user'])) {
    if (!empty($_POST['user_id']) && !empty($_POST['user_password'])) {
        $user_id = trim($_POST['user_id']);
        $user_password = trim($_POST['user_password']);

        // Using Prepared Statement to prevent SQL Injection
        $stmt = $connect->prepare("SELECT * FROM franchisee_login WHERE f_user_email = ? OR f_user_contact = ?");
        $stmt->bind_param("ss", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();

            // Direct password comparison without hashing
            if ($user_password == $row['f_user_password']) {
                if ($row['f_user_active'] == "YES") {
                    // Store user data in session with proper sanitization
                    $_SESSION['f_user_email'] = htmlspecialchars($row['f_user_email']);
                    $_SESSION['f_user_name'] = htmlspecialchars($row['f_user_name'] ?? '');
                    $_SESSION['f_user_contact'] = htmlspecialchars($row['f_user_contact'] ?? '');

                    // Set a session marker to track login status
                    $_SESSION['f_is_logged_in'] = true;
                    $_SESSION['f_login_time'] = time();

                    echo '<div class="alert Success">Login Successful, Redirecting...</div>';
                    echo '<meta http-equiv="refresh" content="1;URL=../../franchisee/dashboard/">';
                    exit();
                } else {
                    $login_error = '<div class="alert info error-msg"><strong>Sorry!</strong> Your account is not Active/Verified. Please contact our support.<br><a href="/index.php#contact"><b>Click here </b></a></div>';
                }
            } else {
                $login_error = '<div class="alert info error-msg">Incorrect Password! Try Again. <br><a href="/forgot-password.php"><b>Reset Password</b></a></div>';
            }
        } else {
            $login_error = '<div class="alert info error-msg">Invalid user id or password.</div>';
        }

        $stmt->close();
    } else {
        $login_error = '<div class="alert info error-msg">All fields are required.</div>';
    }
}
?>

<!-- Include custom styles for password toggle -->
<link rel="stylesheet" href="login-styles.css">

<div class="login-wrap">
    <div class="login-container">
        <h2 class="heading"><a href="https://miniwebsite.in"><i class="fa fa-angle-left" aria-hidden="true"></i></a>Franchisee Login</h2>
        <p class="text-white">Please enter your details</p>

        <form action="" method="post" autocomplete="off" id="login">
            <div class="mb-4">
                <input type="text" class="form-control" name="user_id" id="user_id" placeholder="Enter Email ID or Mobile Number*" required>
            </div>
            <div class="mb-5 position-relative">
                <div class="input-group mb-2">
                    <input type="password" class="form-control" name="user_password" id="user_password" placeholder="Password" required>
                    <div class="input-group-prepend">
                        <div class="input-group-text password-toggle" id="password-toggle" onclick="togglePassword()" title="Show/Hide Password">
                            <i class="fa fa-eye eye-closed" aria-hidden="true" ></i>
                            <i class="fa fa-eye-slash eye-open" aria-hidden="true" ></i>
                        </div>
                    </div>
                </div>
                <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
            </div>
            <input type="submit" name="login_user" value="LOG IN" class="btn btn-login">
        </form>

        <?php echo $login_error; // Display error messages ?>
    </div>
</div>

<script>
function togglePassword() {
    const passwordField = document.getElementById("user_password");
    const passwordToggle = document.getElementById("password-toggle");

    if(passwordField.type === "password") {
        // Show password
        passwordField.type = "text";
        passwordToggle.classList.add("visible");
    } else {
        // Hide password
        passwordField.type = "password";
        passwordToggle.classList.remove("visible");
    }
}
</script>
