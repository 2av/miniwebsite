<?php

require_once(__DIR__ . '/../app/config/database.php');
require('header.php');

?>


<div class="container2">

	<h1>Account Manager</h1>

	<a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back </h3></a>	
<?php
	
	
			 
	// Query user_details table for admin
	$query=mysqli_query($connect,'SELECT * FROM user_details WHERE email="'.$_SESSION['admin_email'].'" AND role="ADMIN"');
	
	if(mysqli_num_rows($query) > 0){
		$row=mysqli_fetch_array($query);
		// Map user_details fields to old field names for compatibility
		$row['admin_email'] = $row['email'] ?? '';
		$row['admin_contact'] = $row['phone'] ?? '';
		$row['admin_image'] = $row['image'] ?? '';
		$row['admin_google_pay'] = $row['google_pay'] ?? '';
		$row['admin_paytm'] = $row['paytm'] ?? '';
		$row['admin_rz_pay'] = $row['rz_pay'] ?? '';
		$row['admin_rz_pay2'] = $row['rz_pay2'] ?? '';
	} else {
		echo '<a href="logout.php"><div class="alert danger">Your Session is Expired! Click here to re-login .</div></a>';
		$row = array(); // Initialize empty array to prevent errors
	}
		
		

		
?>
<form  method="POST" enctype="multipart/form-data"  class="my_account_form">
		
		<img class="profile_image" src="<?php if(!empty($row['admin_image'])){echo 'data:image/*;base64,'.base64_encode($row['admin_image']);}else {echo 'images/upload.png';} ?>" alt="Select image" id="showPreviewLogo" onclick="clickFocus()" ><br>
		<div class="input_area"><p>Company Logo (Required)* 100x100 to 400x400 PX</p>
		
		
		
			<script>
				function clickFocus(){
					$('#clickMeImage').click();
				}
				
				function readURL(input){
					if(input.files && input.files[0]){
						var reader = new FileReader();
						reader.onload= function (a){
							$('#showPreviewLogo').attr('src',a.target.result);
						};
						reader.readAsDataURL(input.files[0]);
					}
					
				}
			</script>
			<input type="file" name="admin_image" id="clickMeImage" onchange="readURL(this);" accept="image/*"  >
			
		</div>
		<br>
		
		<div class="input_area"><p>Your Login Email</p>
		<input type="readonly"  value="<?php echo $row['admin_email'];  ?>"  name="update_details" readonly>
		</div>
		<div class="input_area"><p>Your Contact</p>
		<input type="readonly" value="<?php echo $row['admin_contact'];  ?>" readonly>
		</div>
		
		<div class="input_area"><p>Google Pay Number</p>
		<input type="" name="admin_google_pay" value="<?php echo $row['admin_google_pay'];  ?>" placeholder="Enter your Google Pay Number" >
		</div>
		<div class="input_area"><p>Paytm Number</p>
		<input type="" name="admin_paytm" value="<?php echo $row['admin_paytm'];  ?>" placeholder="Enter Paytm Number" >
		</div>
		<div class="input_area"><p>Razorpay Key ID</p>
		<input type="" name="admin_rz_pay" value="<?php echo $row['admin_rz_pay'];  ?>" placeholder="Enter Razorpay Secreat id" >
		</div>
		<div class="input_area"><p>Razorpay Secreat</p>
		<input type="" name="admin_rz_pay2" value="<?php echo $row['admin_rz_pay2'];  ?>" placeholder="Enter Razorpay Key id" >
		</div>
		
		<div class="btn_payment" id="update_details">Update Details</div>
		
		
	</form>





<?php

if(isset($_POST['update_details']))	{
	// image upload start
	if(!empty($_FILES['admin_image']['tmp_name'])) {
		$filename = $_FILES['admin_image']['name'];
		$imageFileType = strtolower(pathinfo($filename,PATHINFO_EXTENSION));
		$file_allow = array('png','jpeg','jpg','gif');
		$filesize = $_FILES['admin_image']['size'];
		
		// Add file size validation for admin profile image
		if($filesize > 250000) {
			echo '<div class="alert danger">File Size More then 250KB! Please resize it and then upload.</div>';
			return;
		}
		
		// image type check if correct
		if(in_array($imageFileType,$file_allow)) {
			$profile_image = addslashes(file_get_contents($_FILES['admin_image']['tmp_name']));
			// Update in user_details table
			$updateLogo = mysqli_query($connect,'UPDATE user_details SET image="'.$profile_image.'" WHERE email="'.$_SESSION['admin_email'].'" AND role="ADMIN"');
		} else {
			echo '<div class="alert danger">Only PNG,JPG,JPEG or GIF files allowed</div>';
			return;
		}
	}
	
	// image upload ended
	
	$admin_rz_pay = str_replace(array("'",'"',';','(',')','"','"'," ","<",">"),array("\'",'\"','\;','\(','\)','\"','\"',"","",""),$_POST['admin_rz_pay']);
	$admin_rz_pay2 = str_replace(array("'",'"',';','(',')','"','"'," ","<",">"),array("\'",'\"','\;','\(','\)','\"','\"',"","",""),$_POST['admin_rz_pay2']);
	
	// Update in user_details table
	$update = mysqli_query($connect,'UPDATE user_details SET 
	google_pay="'.$_POST['admin_google_pay'].'",
	paytm="'.$_POST['admin_paytm'].'",
	rz_pay="'.$admin_rz_pay.'",
	rz_pay2="'.$admin_rz_pay2.'" 
	WHERE email="'.$_SESSION['admin_email'].'" AND role="ADMIN"');
	
	// enter details in database ending
	
	if($update) {
		echo '<a href="my_account.php"><div class="alert info">Details Updated Wait...</div></a>';
		echo '<meta http-equiv="refresh" content="1;URL=my_account.php">';
		echo '<style>  form {display:none;} </style>';
	} else {
		echo '<a href="my_account.php"><div class="alert danger">Error! Try Again.</div></a>';
	}
	
} else {
	
}
		

?>


</div>

<script>
// function for submitting this data
$(document).ready(function(){
	$('#update_details').on('click',function(e){
		$('.my_account_form').submit();
	});
})


	

</script> <footer class="footer-area"><center>
           <br />
                    <a href="index.html" class="footer-logo">
                        						<img src="../panel/images/f_logo.png" alt="Vcard" width="auto" height="50px">
						                    </a>
                    <p>&copy; Copyright 2025 - All Rights Reserved. Crafted With <?php echo $_SERVER['HTTP_HOST']; ?> for Someone Special ! </p> 
					<p><a target="_blank" href="https://support.ajooba.io">Support Forum</a> | <a target="_blank" href="https://support.ajooba.io/faq">Faq's</a> | <a target="_blank" href="https://support.ajooba.io/articles/category/digital-vcard">Knowlege Base</a> </p>
			
        </center></footer>



