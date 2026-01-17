<?php
require('login-connect.php');

if(isset($_SESSION['sender_token'])){
	$sender_token=$_SESSION['sender_token'];
}else {
	$sender_token='';
}

?>
<div class="login-wrap">
        <div class="login-container">
            <h2 class="heading"><a href="login.php"><i class="fa fa-angle-left" aria-hidden="true"></i></a> Verify your Email</h2>
            <p class="text-white">A verification code has been sent to your email. Enter it below.</p>

            <div class="otp-input">
                <input type="text" maxlength="1" value="2">
                <input type="text" maxlength="1" value="9">
                <input type="text" maxlength="1" value="9">
                <input type="text" maxlength="1" value="3">
            </div>

            <button class="btn btn-verify">Verify Code</button>
            <div class="Reset-foot">
                <p class="mt-3">Didn't receive mail? <span class="resend-code">Resend Code</span></p>
            </div>
            <p class="error-msg">The code entered is wrong.</p>
        </div>
    </div>