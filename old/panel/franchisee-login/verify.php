<?php

require('connect.php');
?>
<br>
<br>
<br>

<?php

if(isset($_GET['email'])){
	$query=mysqli_query($connect,'SELECT * FROM franchisee_login WHERE f_user_email="'.$_GET['email'].'" AND f_user_active="NO" ');
		if(!mysqli_num_rows($query)==0){
			//login function 
			$row=mysqli_fetch_array($query);
			
			if($row['user_token']==$_GET['token'] ){
				$update=mysqli_query($connect,'UPDATE franchisee_login SET f_user_active="YES" WHERE f_user_email="'.$_GET['email'].'" ');
				
					$_SESSION['f_user_email']=$row['f_user_email'];
					$_SESSION['f_user_name']=$row['f_user_name'];
					$_SESSION['f_user_contact']=$row['f_user_contact'];
					echo '<div class="alert Success">Email Verified Successfully. Redirecting...</div>';
					echo '<meta http-equiv="refresh" content="2;URL=index.php">';
					exit();
			}else {
				$token=rand(1000000000,99999999999999999);
				echo '<div class="alert info">Email Expired! We have sent you a new link, click on that to verify your email.</div>';
				
				$update=mysqli_query($connect,'UPDATE franchisee_login SET f_user_token="'.$token.'" WHERE f_user_email="'.$_GET['email'].'" ');
				
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
$headers .= 'From: <omveersingh19970722@gmail.com>' . "\r\n";
$headers .= 'Cc: <omveersingh19970722@gmail.com>' . "\r\n";
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