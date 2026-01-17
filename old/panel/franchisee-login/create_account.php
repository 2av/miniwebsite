<?php

require('connect.php');
require('header.php');

?>


<div class="main3">
	<a href="../../franchisee/dashboard"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back </h3></a>
	<h1 class="close_form">Login ID & Password for User</h1>
	
	<form action="" method="POST" class="close_form" enctype="multipart/form-data">
		
		<h3></h3>
		<div class="input_box"><p>Login Email (Email ID)</p><input type="email" name="user_email" maxlength="199" placeholder="Enter Login Email for User" required></div>
		<div class="input_box"><p>Login Mobile *(Mobile Number)</p><input type="text" name="user_contact" maxlength="13" placeholder="Enter Mobile Number" required></div>
		<div class="input_box"><p>Login password *</p><input type="text" name="user_password" maxlength="199" placeholder="Enter Password" required></div>
			
		<input type="submit" class="" name="process1" value="Create ID & Password" id="block_loader">
	
	
	</form>
	




<?php
if(isset($_POST['process1'])){
	
				
		$query=mysqli_query($connect,'SELECT * FROM customer_login WHERE user_email="'.$_POST['user_email'].'" AND user_contact="'.$_POST['user_contact'].'" ');
		if(mysqli_num_rows($query)>>0){
			
			
			echo '<a href="create_card.php"><div class="alert info">Account already available.</div><div class="next_btn">Next</div></a>';
			$row=mysqli_fetch_array($query);
			 $_SESSION['user_email']=$row['user_email'];
				 	$_SESSION['user_contact']=$row['user_contact'];
					
					
					echo '<style>  .close_form {display:none;} </style>';
				

		}else{

		
				$insert=mysqli_query($connect,'INSERT INTO customer_login (user_email,user_password,user_contact,user_active) VALUES ("'.$_POST['user_email'].'","'.$_POST['user_password'].'","'.$_POST['user_contact'].'","YES")');
				if($insert){
                    // Also add to unified user_details as CUSTOMER
                    $legacy_id = mysqli_insert_id($connect);
                    $ip = mysqli_real_escape_string($connect, $_SERVER['REMOTE_ADDR'] ?? '');
                    $email = mysqli_real_escape_string($connect, $_POST['user_email']);
                    $phone = mysqli_real_escape_string($connect, $_POST['user_contact']);
                    $password = mysqli_real_escape_string($connect, $_POST['user_password']);
                    mysqli_query($connect, "
                        INSERT IGNORE INTO user_details
                            (role, email, phone, name, password, ip, status, created_at, legacy_customer_id)
                        VALUES
                            ('CUSTOMER', '$email', '$phone', '$email', '$password', '$ip', 'ACTIVE', NOW(), ".(int)$legacy_id.")
                    ");
					echo '<a href="create_card.php"><div class="alert info">Account Created!.</div><div class="next_btn">Next</div></a>';
					echo '<style> form {display:none;} </style>';
					
				 	$_SESSION['user_email']=$_POST['user_email'];
			 	$_SESSION['user_contact']=$_POST['user_contact'];
				}
				
}

}
?>

</div>




<footer class="">

<p>Copyright 2025 || <?php echo $_SERVER['HTTP_HOST']; ?></p>

<script data-ad-client="ca-pub-2577996436540735" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>