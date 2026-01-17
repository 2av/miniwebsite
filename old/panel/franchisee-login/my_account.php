<?php

require('connect.php');
require('header.php');

?>


<div class="container2">

	<h1>Account Manager</h1>

	<a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back </h3></a>	
<?php
	
	
			 
	$query=mysqli_query($connect,'SELECT * FROM franchisee_login WHERE  f_user_email="'.$_SESSION['f_user_email'].'"');
	
	if(mysqli_num_rows($query) >> 0){}else {
		echo '<a href="logout.php"><div class="alert danger">Your Session is Expired! Click here to re-login .</div></a>';
		
	}
	
	$row=mysqli_fetch_array($query);
		
		

		
?>
<form  method="POST" enctype="multipart/form-data"  class="my_account_form">
		
		<img class="profile_image" src="<?php if(!empty($row['f_user_image'])){echo 'data:image/*;base64,'.base64_encode($row['f_user_image']);}else {echo 'images/upload.png';} ?>" alt="Select image" id="showPreviewLogo" onclick="clickFocus()" ><br>
		
	
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
			
		
			<input type="file" name="f_user_image" id="clickMeImage" onchange="readURL(this);" accept="image/*"  >
			
		</div>
		<br>
		<center>
			
		<div class="input_area"><p>Your Login Email</p>
		<input type="readonly"  value="<?php echo $row['f_user_email'];  ?>"  name="update_details" readonly>
		</div>
		<div class="input_area"><p>Your Contact</p>
		<input type="readonly" value="<?php echo $row['f_user_contact'];  ?>" readonly>
		</div>
		
		<br>
		<div class="input_area"><p>New Password (Set a new password)</p>
		<input type="password"  name="f_user_password" id="myPassword"  value="<?php echo $row['f_user_password'];  ?>" placeholder="Enter new password..." required><span class="show_pass_alert"><input type="checkbox" onclick="showPass()"> Show Password</span>
		
		
		<script>
		// show password 
function showPass() {
  var x = document.getElementById("myPassword");
  if (x.type === "password") {
    x.type = "text";
  } else {
    x.type = "password";
  }
}
</script>

		</div>
		
		<h3>Payment Details</h3>
		<div class="input_area"><p>Google Pay Number</p>
		<input type="" name="f_user_google_pay" value="<?php echo $row['f_user_google_pay'];  ?>" placeholder="Enter your Google Pay Number" >
		</div>
		<div class="input_area"><p>Paytm Number</p>
		<input type="" name="f_user_paytm" value="<?php echo $row['f_user_paytm'];  ?>" placeholder="Enter Paytm Number" >
		</div>
		<div class="input_area" style="display:none"><p>Razorpay Secreat</p>
		<input type="" name="f_user_rz_pay" value="<?php echo $row['f_user_rz_pay'];  ?>" placeholder="Enter Razorpay Secreat id" >
		</div>
		<div class="input_area" style="display:none"><p>Razorpay Key ID</p>
		<input type="" name="f_user_rz_pay2" value="<?php echo $row['f_user_rz_pay2'];  ?>" placeholder="Enter Razorpay Key id" >
		</div>
		
		<div class="btn_payment" id="update_details">Update Details</div>
		
			<h3>API Details</h3>
			
			
		<br>
		
		
		<div class="input_area"><p>API URL</p>
		<input   value="<?php echo "https://$_SERVER[HTTP_HOST]/api/api.php?ref_id=Ref_id&token=tokenNumber";  ?>" placeholder="Enter Razorpay Key id" readonly>
		</div>
		<div class="input_area"><p>Your API Ref_id</p>
		<input   value="<?php echo $row['id'];  ?>" readonly>
		</div>
		<div class="input_area"><p>Your API Token</p>
		<input   value="<?php echo md5($row['f_user_password']);  ?>" readonly>
		</div>
		

		
		
		
	</form>






<?php

if(isset($_POST['update_details']))	{
	// image upload start
	if(!empty($_FILES['f_user_image']['tmp_name'])) {
		$filename = $_FILES['f_user_image']['name'];
		$imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$file_allow = array('png', 'jpeg', 'jpg', 'gif');
		$filesize = $_FILES['f_user_image']['size'];
		
		// Image type check if correct
		if(in_array($imageFileType, $file_allow)) {
			// Image size check not more than 250KB
			if($filesize <= 250000) {
				$profile_image = addslashes(file_get_contents($_FILES['f_user_image']['tmp_name']));
				$updateLogo = mysqli_query($connect, 'UPDATE franchisee_login SET f_user_image="'.$profile_image.'" WHERE f_user_email="'.$_SESSION['f_user_email'].'"');
			} else {
				echo '<div class="alert danger">File Size More than 250KB. Please resize it and then upload.</div>';
			}
		} else {
			echo '<div class="alert danger">Only PNG, JPG, JPEG or GIF files allowed</div>';
		}
	}
	
	// image upload ended
	
								$f_user_rz_pay=str_replace(array("'",'"',';','(',')','“','”'," ","<",">"),array("\'",'\"','\;','\(','\)','\“','\”',"","",""),$_POST['f_user_rz_pay']);
								$f_user_rz_pay2=str_replace(array("'",'"',';','(',')','“','”'," ","<",">"),array("\'",'\"','\;','\(','\)','\“','\”',"","",""),$_POST['f_user_rz_pay2']);
								
								
								
				// image upload
			$update=mysqli_query($connect,'UPDATE franchisee_login SET 
			
			f_user_google_pay="'.$_POST['f_user_google_pay'].'",
			f_user_paytm="'.$_POST['f_user_paytm'].'",
			f_user_password="'.$_POST['f_user_password'].'",
			f_user_rz_pay="'.$f_user_rz_pay.'",
			f_user_rz_pay2="'.$f_user_rz_pay2.'" 
			WHERE f_user_email="'.$_SESSION['f_user_email'].'"');
			
		// enter details in database ending
		
		if($update){
			echo '<a href="my_account.php"><div class="alert info">Details Updated Wait...</div></a>';
			echo '<meta http-equiv="refresh" content="1;URL=my_account.php">';
			echo '<style>  form {display:none;} </style>';
		}else {
			echo '<a href="my_account.php"><div class="alert danger">Error! Try Again.</div></a>';
		}
		
}else {
	
	
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


	

</script>

<footer class="">

<p>Copyright 2025 || Website developed & Maintained by WeStandOut SERVICES</p>

<script data-ad-client="ca-pub-2577996436540735" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>
