<?php
require('connect.php');

if(isset($_POST['reset_password'])){
    $email = mysqli_real_escape_string($connect, $_POST['email']);
    $new_password = mysqli_real_escape_string($connect, $_POST['new_password']);

    $update = mysqli_query($connect, "UPDATE customer_login SET user_password='$new_password', reset_otp=NULL, reset_otp_expiry=NULL WHERE user_email='$email'");

    if($update){
        echo '<div class="alert success">Password Reset Successfully! Redirecting to Login...</div>';
        echo '<meta http-equiv="refresh" content="2;URL=login.php">';
    } else {
        echo '<div class="alert danger">Error updating password! Try again.</div>';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>

<h1>Reset Your Password</h1>
<form action="" method="post">
    <input type="hidden" name="email" value="<?php echo $_GET['email']; ?>" required>
    <input type="password" name="new_password" placeholder="Enter New Password" required>
    <input type="submit" name="reset_password" value="Reset Password">
</form>

</body>
</html>
