<?php

require('connect.php');
require('header.php');

?>

<?php
$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" ');

if(mysqli_num_rows($query)==0){
	echo '<meta http-equiv="refresh" content="0;URL=index.php">';
}else {
	$row=mysqli_fetch_array($query);
}

?>

<div class="main3">
	<div class="navigator_up">
		<a href="select_theme.php"><div class="nav_cont " ><i class="fa fa-map"></i> Select Theme</div></a>
		<a href="create_card2.php"><div class="nav_cont"><i class="fa fa-bank"></i> Company Details</div></a>
		<a href="create_card3.php"><div class="nav_cont "><i class="fa fa-facebook"></i> Social Links</div></a>
		<a href="create_card4.php"><div class="nav_cont active"><i class="fa fa-rupee"></i> Payment Options</div></a>
		<a href="create_card5.php"><div class="nav_cont"><i class="fa fa-ticket"></i> Products & Services</div></a>
		<a href="create_card7.php"><div class="nav_cont"><i class="fa fa-archive"></i> E-commerce</div></a>
		<a href="create_card6.php"><div class="nav_cont"><i class="fa fa-image"></i> Image Gallery</div></a>
		<a href="preview_page.php"><div class="nav_cont"><i class="fa fa-laptop"></i> Preview Card</div></a>
	
	</div>
	
	<div class="btn_holder">
		<a href="create_card3.php"><div class="back_btn"><i class="fa fa-chevron-circle-left"></i> Back</div></a>
		<a href="create_card5.php"><div class="skip_btn">Skip <i class="fa fa-chevron-circle-right"></i></div></a>
	</div>
	<h1>Payment Options</h1>
	
	<form action="" method="POST" enctype="multipart/form-data">
	

<!-------------------form ----------------------->	
		<h3>Payment</h3>
		
		<div class="input_box"><p>PayTm Number (Optional) </p><input type="text" name="d_paytm" maxlength="20" placeholder="Paytm Number" value="<?php if(!empty($row['d_paytm'])){echo $row['d_paytm'];}?>" ></div>	
		<div class="input_box"><p>Google Pay (Optional) </p><input type="text" name="d_google_pay" maxlength="20" placeholder="Google Pay Number" value="<?php if(!empty($row['d_google_pay'])){echo $row['d_google_pay'];}?>" ></div>	
		<div class="input_box"><p>PhonePe (Optional) </p><input type="text" name="d_phone_pay" maxlength="20" placeholder="PhonePe Number" value="<?php if(!empty($row['d_phone_pay'])){echo $row['d_phone_pay'];}?>" ></div>
		
		<h3>Bank Account Details</h3>
		<div class="input_box"><p>Bank Name (Optional) </p><input type="text" name="d_bank_name" maxlength="100" placeholder="Bank Name. Ex. HDFC, SBI etc" value="<?php if(!empty($row['d_bank_name'])){echo $row['d_bank_name'];}?>" ></div>
		<div class="input_box"><p>Account Holder Name (Optional) </p><input type="text" name="d_ac_name" maxlength="100" placeholder="Account holder name" value="<?php if(!empty($row['d_ac_name'])){echo $row['d_ac_name'];}?>" ></div>
		<div class="input_box"><p>Bank Account Number (Optional) </p><input type="text" name="d_account_no" maxlength="100" placeholder="Account Number" value="<?php if(!empty($row['d_account_no'])){echo $row['d_account_no'];}?>" ></div>
		<div class="input_box"><p>Bank IFSC Code (Optional) </p><input type="text" name="d_ifsc" maxlength="100" placeholder="IFSC Code" value="<?php if(!empty($row['d_ifsc'])){echo $row['d_ifsc'];}?>" ></div>
		<div class="input_box"><p>Account Type (Optional) </p><select type="text" name="d_ac_type" maxlength="100"   ><option value="<?php if(!empty($row['d_ac_type'])){echo $row['d_ac_type'];}?>">-Select-</option><option>Savings Account</option><option>Current Account</option></select></div>
		
		<h3>Payment QR Code</h3>
		<div class="divider"><div class="num">Paytm QR Code</div>
		
		<img src="<?php if(!empty($row['d_qr_paytm'])){echo 'data:image/*;base64,'.base64_encode($row['d_qr_paytm']);}else {echo 'images/upload.png';} ?>" alt="Select image" id="showPreviewLogo10" onclick="clickFocus10()" >
		<div class="input_box">
		
		
		
		
			<script>
				function clickFocus10(){
					$('#clickMeImage10').click();
				}
				
				function readURL10(input){
					if(input.files && input.files[0]){
						var reader = new FileReader();
						reader.onload= function (a){
							$('#showPreviewLogo10').attr('src',a.target.result);
						};
						reader.readAsDataURL(input.files[0]);
					}
					
				}
			</script>
			<input type="file" name="d_qr_paytm" id="clickMeImage10" class="" onchange="readURL10(this);" accept="image/*"  >
			
		</div>	
	</div>	
		
		
		
		<div class="divider"><div class="num">Google Pay QR Code</div>
		
		<img src="<?php if(!empty($row['d_qr_google_pay'])){echo 'data:image/*;base64,'.base64_encode($row['d_qr_google_pay']);}else {echo 'images/upload.png';} ?>" alt="Select image" id="showPreviewLogo3" onclick="clickFocus3()" >
		<div class="input_box">
		
		
		
		
			<script>
				function clickFocus(){
					$('#clickMeImage3').click();
				}
				
				function readURL3(input){
					if(input.files && input.files[0]){
						var reader = new FileReader();
						reader.onload= function (a){
							$('#showPreviewLogo3').attr('src',a.target.result);
						};
						reader.readAsDataURL(input.files[0]);
					}
					
				}
			</script>
			<input type="file" name="d_qr_google_pay" id="clickMeImage3" class="" onchange="readURL3(this);" accept="image/*"  >
			
		</div>	
	</div>	
		
		
		
		<div class="divider"><div class="num">PhonePe QR Code</div>
		
		<img src="<?php if(!empty($row['d_qr_phone_pay'])){echo 'data:image/*;base64,'.base64_encode($row['d_qr_phone_pay']);}else {echo 'images/upload.png';} ?>" alt="Select image" id="showPreviewLogo2" onclick="clickFocus2()" >
		<div class="input_box">
		
		
		
		
			<script>
				function clickFocus2(){
					$('#clickMeImage2').click();
				}
				
				function readURL2(input){
					if(input.files && input.files[0]){
						var reader = new FileReader();
						reader.onload= function (a){
							$('#showPreviewLogo2').attr('src',a.target.result);
						};
						reader.readAsDataURL(input.files[0]);
					}
					
				}
			</script>
			<input type="file" name="d_qr_phone_pay" id="clickMeImage2" class="" onchange="readURL2(this);" accept="image/*"  >
			
		</div>	
	</div>	
		
		<br>
		
		
		
		<input type="submit" class="" name="process4" value="Next 5" id="block_loader">
	
<!-------------------form ending----------------------->
	</form>
	
	<?php
	if(isset($_POST['process4'])){
		
		$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'"');
		if(mysqli_num_rows($query)==1){
			
		// enter details in database
			// image upload
			
				if(!empty($_FILES['d_qr_paytm']['tmp_name'])){
					$d_qr_paytm=addslashes(file_get_contents($_FILES['d_qr_paytm']['tmp_name']));
					$update1=mysqli_query($connect,'UPDATE digi_card SET d_qr_paytm="'.$d_qr_paytm.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
				}
				if(!empty($_FILES['d_qr_google_pay']['tmp_name'])){
					$d_qr_google_pay=addslashes(file_get_contents($_FILES['d_qr_google_pay']['tmp_name']));
					$update1=mysqli_query($connect,'UPDATE digi_card SET d_qr_google_pay="'.$d_qr_google_pay.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
				}
				if(!empty($_FILES['d_qr_phone_pay']['tmp_name'])){
					$d_qr_phone_pay=addslashes(file_get_contents($_FILES['d_qr_phone_pay']['tmp_name']));
					$update1=mysqli_query($connect,'UPDATE digi_card SET d_qr_phone_pay="'.$d_qr_phone_pay.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
				}
			//image upload 
	
				
			$update=mysqli_query($connect,'UPDATE digi_card SET 
			
			d_paytm="'.$_POST['d_paytm'].'",
			d_google_pay="'.$_POST['d_google_pay'].'",
			d_phone_pay="'.$_POST['d_phone_pay'].'",
			d_account_no="'.$_POST['d_account_no'].'",
			d_ifsc="'.$_POST['d_ifsc'].'",
			d_ac_name="'.$_POST['d_ac_name'].'",
			d_bank_name="'.$_POST['d_bank_name'].'",
			d_ac_type="'.$_POST['d_ac_type'].'"
			
			WHERE id="'.$_SESSION['card_id_inprocess'].'"');
			
		// enter details in database ending
		
		if($update){
			echo '<a href="create_card5.php"><div class="alert info">Details Updated Wait...</div></a>';
			
			echo '<meta http-equiv="refresh" content="2;URL=create_card5.php">';
			echo '<style>  form {display:none;} </style>';
		}else {
			echo '<a href="create_card4.php"><div class="alert danger">Error! Try Again.</div></a>';
		}
			
		
		}else {
			
			echo '<a href="create_card.php"><div class="alert danger">Detail Not Available. Try Again Click here.</div></a>';
		}
		
	}
	?>

</div>


<footer class="">

<p>Copyright 2025 || <?php echo $_SERVER['HTTP_HOST']; ?></p>

<script data-ad-client="ca-pub-2577996436540735" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>