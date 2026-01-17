<?php
require('connect.php');
require('header.php');

$message = "";

if(isset($_POST['login_user'])){
    $current_password =  $_POST['current_password'];
    $new_password =$_POST['new_password'];
    $confirm_password =  $_POST['confirm_password'];

	
    // Fetch admin's current password from user_details table
    $admin_id = $_SESSION['admin_id'];

	   
    // Query user_details table for admin
    $query = mysqli_query($connect, "SELECT password FROM user_details WHERE id = '$admin_id' AND role='ADMIN'");
    $row = mysqli_fetch_assoc($query);
	echo $row['password'];   
    if ($row && $current_password == $row['password']) {
        if ($new_password === $confirm_password) {
//$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            // Update password in user_details table
            $update_query = mysqli_query($connect, "UPDATE user_details SET password = '$new_password' WHERE id = '$admin_id' AND role='ADMIN'");

            if ($update_query) {
                $message = '<div class="alert success">Password changed successfully.</div>';
            } else {
                $message = '<div class="alert danger">Error updating password.</div>';
            }
        } else {
            $message = '<div class="alert warning">New passwords do not match.</div>';
        }
    } else {
        $message = '<div class="alert danger">Incorrect current password.</div>';
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
            <label>Current Password</label>
            <input type="password" name="current_password" required>
        </div>

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
