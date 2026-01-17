<?php
require('config.php');
require('razorpay-php/Razorpay.php');

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

$success = true;
$error = "Payment Failed";

if (empty($_POST['razorpay_payment_id']) === false) {
    $api = new Api($keyId, $keySecret);

    try {
        $attributes = array(
            'razorpay_order_id' => $_SESSION['razorpay_order_id'],
            'razorpay_payment_id' => $_POST['razorpay_payment_id'],
            'razorpay_signature' => $_POST['razorpay_signature']
        );

        $api->utility->verifyPaymentSignature($attributes);
    } catch(SignatureVerificationError $e) {
        $success = false;
        $error = 'Razorpay Error : ' . $e->getMessage();
    }
}

if ($success === true) {
    // Payment successful, update your database here
    $html = "<p>Your payment was successful</p>
             <p>Payment ID: {$_POST['razorpay_payment_id']}</p>";
} else {
    $html = "<p>Your payment failed</p>
             <p>{$error}</p>";
}

echo $html;
?>
<br>
<br>
<br>

<?php

if(isset($_GET['email'])){
	// Query user_details table for franchisee
	$query=mysqli_query($connect,'SELECT * FROM user_details WHERE email="'.$_GET['email'].'" AND role="FRANCHISEE" AND status="INACTIVE"');
		if(mysqli_num_rows($query) > 0){
			//login function
			$row=mysqli_fetch_array($query);

			if($row['user_token']==$_GET['token'] ){
				// Update status in user_details table
				$update=mysqli_query($connect,'UPDATE user_details SET status="ACTIVE" WHERE email="'.$_GET['email'].'" AND role="FRANCHISEE"');

					// Map user_details fields to session variables
					$_SESSION['f_user_email']=$row['email'];
					$_SESSION['f_user_name']=$row['name'];
					$_SESSION['f_user_contact']=$row['phone'];
					echo '<div class="alert Success">Email Verified Successfully. Redirecting...</div>';
					echo '<meta http-equiv="refresh" content="2;URL=index.php">';
					exit();
			}else {
				$token=rand(1000000000,99999999999999999);
				echo '<div class="alert info">Email Expired! We have sent you a new link, click on that to verify your email.</div>';

				// Update user_token in user_details table
				$update=mysqli_query($connect,'UPDATE user_details SET user_token="'.$token.'" WHERE email="'.$_GET['email'].'" AND role="FRANCHISEE"');

// email script
// email script
// email script
// email script
// email script
// email script

				$to = $_GET['email'];
$subject = "DIGI CARD Email Varification Link";

 $message = '
Hi Dear,

Please click on this link to verify your email on DIGI CARD (Digital Visiting Card).<br><br><br>
<a href="https://'.$_SERVER['HTTP_HOST'].'/panel/login/verify.php?email='.$_GET['email'].'&token='.$token.'" style="background: #00a1ff;   color: white;   padding: 10px;">Click here to verify</a><br><br><br><br>


Thanks<br>
DIGI CARD Team

';

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <info@obp.org.in>' . "\r\n";
$headers .= 'Cc: <info@spireinfo.in>' . "\r\n";
if(mail($to,$subject,$message,$headers)){
	echo '<div class="alert success">Verification email sent again. Please click on that to verify your account and start using services.</div>';
}else {
	echo '<div class="alert danger">Error Email! try again</div>';
}
// email script end
// email script end
// email script end
// email script end
// email script end
// email script end
// email script end
			}

		}else {
			echo '<a href="login.php"><div class="alert danger">Email already verified! Back to login</div></a>';
		}
}
?>
