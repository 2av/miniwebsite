<?php
require('connect.php');
?>
<div class="clip_path1"></div>
<div class="login">
	
	<form action="" method="post" autocomplete="off" id="login">
	<h1>Franchisee Login</h1>
	<p>Please login with your email id and password to create/View your digital visiting card</p>
		<input type="text" name="f_user_id" placeholder="Enter Email id or Mobile Number" autocomplete="off" required>
		<input type="password" name="f_user_password" placeholder="Password" autocomplete="off" required>
		<input type="submit" name="login_user" value="Login">
		<br>
	<a id="forgot_p">Forgot Password?</a>
	</form>
	
	
	<form action="" method="post" autocomplete="off" id="forgot_pass">
	<h1>Forgot Password?</h1>
	<p>Mention Email id, you will receive an email with password.</p>
		
		<input type="email" name="f_user_email" placeholder="Enter Email" autocomplete="off" required>
		
		
		<input type="submit" name="forgot_password" value="Send Password">
		<br>
		<br>
		<a id="login_en" >Go Back to Login</a>
		<br>
		<br>
	</form>


<script>

	$('#register_en').on('click',function(){
		$('#login').hide();
		$('#register').show();
		$('#forgot_pass').hide();
		
	})
	$('#login_en').on('click',function(){
		$('#register').hide();
		$('#forgot_pass').hide();
		$('#login').show();
	})
	$('#forgot_p').on('click',function(){
		$('#register').hide();
		$('#login').hide();
		$('#forgot_pass').show();
	})
	

</script>


<?php

	if(isset($_POST['login_user'])){
		$query=mysqli_query($connect,'SELECT * FROM franchisee_login WHERE f_user_email="'.$_POST['f_user_id'].'" OR f_user_contact="'.$_POST['f_user_id'].'" ');
		if(mysqli_num_rows($query)>0){
			//login function 
			$row=mysqli_fetch_array($query);
			
			if($row['f_user_password']==$_POST['f_user_password'] ){
				
				// form display none
					echo '<style> form {display:none;} </style>';
				if($row['f_user_active']=="YES"){
					$_SESSION['f_user_email']=$row['f_user_email'];
					$_SESSION['f_user_name']=$row['f_user_name'];
					$_SESSION['f_user_contact']=$row['f_user_contact'];
					echo '<div class="alert Success">Login Success, Redirecting...</div>';
					echo '<meta http-equiv="refresh" content="0;URL=index.php">';
					exit();}
				else {
					echo '<div class="alert info"><strong>Sorry!</strong> Your account is not Active/Verified. Please contact our Support for help.<br><a href="https://'.$_SERVER['HTTP_HOST'].'/index.php#contact"><b>Click here </b></a></div>';
				}
			}else {
				echo '<div class="alert info">Password Wrong! Try Again. If you forgot your password then <br><a href="https://'.$_SERVER['HTTP_HOST'].'/index.php#contact"><b>Click here </b></a></div>';
			}
			
		}else {
			echo '<div class="alert info" id="register_en">User Does Not Exists. Contact us on westandalone@gmail.com for new account request. <br><a href="https://'.$_SERVER['HTTP_HOST'].'/index.php#contact"><b>Click here </b></a></div>';
		}
	}
	

?>


<?php

if(isset($_POST['forgot_password'])){
	$query=mysqli_query($connect,'SELECT * FROM franchisee_login WHERE f_user_email="'.$_POST['f_user_email'].'" ');
	$row=mysqli_fetch_array($query);
		if(mysqli_num_rows($query)>>0){
			
			// email script				

				$to = $_POST['f_user_email'];
$subject = "Visiting Card Online Password for franchisee login";

 $message = '
Hi Dear,

Your Password is: '.$row['f_user_password'].'
to login on '.$_SERVER['HTTP_HOST'].'

Thanks


';

						$headers= 'From: <westandalone@gmail.com>';
						if(mail($to,$subject,$message,$headers)){
							echo '<div class="alert success" id="login_en">Password is sent to your email '.$_POST['f_user_email'].'. Check Junk or Spam folder also if not available in Inbox.</div>';
											
											
						}else {
							echo '<div class="alert danger">Error Email! try again</div>';
						}
			
		}else {echo '<div class="alert info" id="register_en">User Does Not Exists. Contact us on westandalone@gmail.com for new account request. <br><a href="https://'.$_SERVER['HTTP_HOST'].'/index.php#contact"><b>Click here </b></a></div>';}
}

?>

</div>

<footer class="">


</div>