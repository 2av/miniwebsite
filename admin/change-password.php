<?php
require_once(__DIR__ . '/../app/config/database.php');
require('header.php');

$message = "";

if(isset($_POST['login_user'])){
    $new_password      = $_POST['new_password'] ?? '';
    $confirm_password  = $_POST['confirm_password'] ?? '';

    // Ensure admin is logged in and we know their email
    $admin_email = $_SESSION['admin_email'] ?? null;
    if (!$admin_email) {
        $message = '<div class="alert danger">You must be logged in as an admin to change the password.</div>';
    } elseif (strlen($new_password) < 6) {
        $message = '<div class="alert warning">New password must be at least 6 characters long.</div>';
    } elseif ($new_password !== $confirm_password) {
        $message = '<div class="alert warning">New passwords do not match.</div>';
    } else {
        $safe_email = mysqli_real_escape_string($connect, $admin_email);

        // Query user_details table for admin by email + role
        $query = mysqli_query($connect, "SELECT id FROM user_details WHERE email = '$safe_email' AND role='ADMIN' LIMIT 1");
        $row   = $query ? mysqli_fetch_assoc($query) : null;

        if ($row) {
            // Always store hashed; keep legacy column in sync
            $new_hash      = password_hash($new_password, PASSWORD_DEFAULT);
            $safe_new_hash = mysqli_real_escape_string($connect, $new_hash);

            $admin_id = (int)$row['id'];
            $update_query = mysqli_query(
                $connect,
                "UPDATE user_details 
                 SET password = '$safe_new_hash', password_hash = '$safe_new_hash'
                 WHERE id = '$admin_id' AND role='ADMIN'"
            );

            if ($update_query) {
                $message = '<div class="alert success">Password changed successfully.</div>';
            } else {
                $message = '<div class="alert danger">Error updating password. Please try again.</div>';
            }
        } else {
            $message = '<div class="alert danger">Admin account not found in user details.</div>';
        }
    }
}
?>


<div class="main3">
	<a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back </h3></a>
	<h1 class="close_form">Change Password</h1>

<div class="change-password-container">

    <?php echo $message; ?>

    <form action="" method="post" autocomplete="off" id="changepassword">
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" required>
        </div>

        <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required>
        </div>
		<input type="submit" name="login_user" value="Update Password">
       
    </form>
</div>
</div>



<style>


.change-password-container {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    width: 350px;
    text-align: center;
	margin:auto;
}



.form-group {
    margin-bottom: 15px;
    text-align: left;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
}

input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

button {
    background-color: #007bff;
    color: white;
    padding: 10px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    width: 100%;
}

button:hover {
    background-color: #0056b3;
}

.alert {
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.alert.success {
    background-color: #d4edda;
    color: #155724;
}

.alert.danger {
    background-color: #f8d7da;
    color: #721c24;
}

.alert.warning {
    background-color: #fff3cd;
    color: #856404;
}
	</style>

<footer class="footer-area">
   <center>
        <br />
        <a href="index.html" class="footer-logo">
            <img src="../panel/images/f_logo.png" alt="Vcard" width="auto" height="50px">
        </a>
        <p>&copy; Copyright 2025 - All Rights Reserved.</p> 
    </center>
</footer>



