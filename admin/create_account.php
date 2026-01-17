<?php

require_once(__DIR__ . '/../app/config/database.php');
require('header.php');
require_once('includes/notification_helper.php');

?>


<div class="main3">
	<a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back </h3></a>
	<h1 class="close_form">Login ID & Password for User</h1>
	
	<form action="" method="POST" class="close_form" enctype="multipart/form-data">
		
		<h3></h3>
		<div class="input_box"><p>Login Email (Email ID)</p><input type="email" name="user_email" maxlength="199" placeholder="Enter Login Email for User" ></div>
		<div class="input_box"><p>Login Mobile *(Mobile Number)</p><input type="text" name="user_contact" maxlength="13" placeholder="Enter Mobile Number" required></div>
		<div class="input_box"><p>Login password *</p><input type="text" name="user_password" maxlength="199" placeholder="Enter Password" required></div>
			
		<input type="submit" class="" name="process1" value="Create ID & Password" id="block_loader">
	
	
	</form>
	




<?php
if(isset($_POST['process1'])){
	
				
		// Check in user_details table with role='CUSTOMER'
		$user_email = mysqli_real_escape_string($connect, $_POST['user_email']);
		$user_contact = mysqli_real_escape_string($connect, $_POST['user_contact']);
		$query=mysqli_query($connect,'SELECT * FROM user_details WHERE email="'.$user_email.'" AND phone="'.$user_contact.'" AND role="CUSTOMER"');
		if(mysqli_num_rows($query)>0){
			
			
			echo '<a href="create_card.php"><div class="alert info">Account already available.</div><div class="next_btn">Next</div></a>';
			$row=mysqli_fetch_array($query);
			$_SESSION['user_email']=$row['email'];
					$_SESSION['user_contact']=$row['phone'];
					
					
					echo '<style>  .close_form {display:none;} </style>';
				

		}else{

		
				// Insert into user_details table with role='CUSTOMER'
				$user_email_esc = mysqli_real_escape_string($connect, $_POST['user_email']);
				$user_password_esc = mysqli_real_escape_string($connect, $_POST['user_password']);
				$user_contact_esc = mysqli_real_escape_string($connect, $_POST['user_contact']);
				$ip = mysqli_real_escape_string($connect, $_SERVER['REMOTE_ADDR'] ?? '');
				// Use email as name if name is not provided
				$user_name_esc = mysqli_real_escape_string($connect, $_POST['user_email']);
				$insert=mysqli_query($connect,'INSERT INTO user_details (role, email, phone, name, password, ip, status) VALUES ("CUSTOMER", "'.$user_email_esc.'", "'.$user_contact_esc.'", "'.$user_name_esc.'", "'.$user_password_esc.'", "'.$ip.'", "ACTIVE")');
				if($insert){
					// Create notification for new account creation
					createNotification(
						'account_creation',
						'New Customer Account Created By Admin',
						'A new customer account has been created with email: ' . $_POST['user_email'],
						$_POST['user_email'],
						'customer',
						null,
						'medium'
					);
					
					echo '<a href="create_card.php"><div class="alert info">Account Created!.</div><div class="next_btn">Next</div></a>';
					echo '<style> form {display:none;} </style>';
					
					$_SESSION['user_email']=$_POST['user_email'];
					$_SESSION['user_contact']=$_POST['user_contact'];
				}
				
}

}
?>

</div>


<br /><br /><br /> <br /><br /><br /><br /><br /> <br /><br /><br /><br /><br />
 <footer class="footer-area"><center>
           <br />
                    <a href="index.html" class="footer-logo">
                        						<img src="../panel/images/f_logo.png" alt="Vcard" width="auto" height="50px">
						                    </a>
                    <p>&copy; Copyright 2025 - All Rights Reserved. Crafted With <?php echo $_SERVER['HTTP_HOST']; ?> for Someone Special ! </p> 
					<p><a target="_blank" href="https://support.ajooba.io">Support Forum</a> | <a target="_blank" href="https://support.ajooba.io/faq">Faq's</a> | <a target="_blank" href="https://support.ajooba.io/articles/category/digital-vcard">Knowlege Base</a> </p>
			
        </center></footer>


