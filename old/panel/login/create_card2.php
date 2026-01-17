<?php

require('connect.php');
require('header.php');

?>


<?php
$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');

if(mysqli_num_rows($query)==0){
	echo '<meta http-equiv="refresh" content="0;URL=index.php">';
}else {
	$row=mysqli_fetch_array($query);
}

?>

<div class="main3">
	<div class="navigator_up">
		<a href="select_theme.php"><div class="nav_cont " ><i class="fa fa-map"></i> Select Theme</div></a>
		<a href="create_card2.php"><div class="nav_cont active"><i class="fa fa-bank"></i> Company Details</div></a>
		<a href="create_card3.php"><div class="nav_cont"><i class="fa fa-facebook"></i> Social Links</div></a>
		<a href="create_card4.php"><div class="nav_cont"><i class="fa fa-rupee"></i> Payment Options</div></a>
		<a href="create_card5.php"><div class="nav_cont"><i class="fa fa-ticket"></i> Products & Services</div></a>
		<a href="create_card7.php"><div class="nav_cont"><i class="fa fa-archive"></i> E-commerce</div></a>
		<a href="create_card6.php"><div class="nav_cont"><i class="fa fa-image"></i> Image Gallery</div></a>
		<a href="preview_page.php"><div class="nav_cont"><i class="fa fa-laptop"></i> Preview Card</div></a>
	
	</div>
	
	<div class="btn_holder">
		<a href="select_theme.php"><div class="back_btn"><i class="fa fa-chevron-circle-left"></i> Back</div></a>
		<a href="create_card3.php"><div class="skip_btn">Skip <i class="fa fa-chevron-circle-right"></i></div></a>
	</div>
	<h1>Company Details</h1>
	
	<form id="card_form"  action="" method="POST" enctype="multipart/form-data" >
	

<!-------------------form ----------------------->	
		<img src="<?php if(!empty($row['d_logo'])){echo 'data:image/*;base64,'.base64_encode($row['d_logo']);}else {echo 'images/logo.png';} ?>" alt="Select image" id="showPreviewLogo" onclick="clickFocus()" >
		<div class="input_box"><p>Company Logo (Required)* 250KB max size </p>
		
		
		
			<script>
				// Preserve the current preview so we can revert on invalid selection
				var originalLogoSrc = null;
				$(document).ready(function(){
					originalLogoSrc = $('#showPreviewLogo').attr('src');
				});
				
				function clickFocus(){
					$('#clickMeImage').click();
				}
				
				function readURL(input){
					// Validate file before previewing
					if(input.files && input.files[0]){
						var file = input.files[0];
						var allowedTypes = ['image/jpeg','image/png'];
						var maxSize = 250 * 1024; // 250KB
						
						// Type validation
						if(allowedTypes.indexOf(file.type) === -1){
							alert('Only JPG and PNG images are allowed.');
							// Revert preview and clear selection
							$('#showPreviewLogo').attr('src', originalLogoSrc);
							$(input).val('');
							return;
						}
						
						// Size validation
						if(file.size > maxSize){
							alert('Image size must be 250KB or less.');
							// Revert preview and clear selection
							$('#showPreviewLogo').attr('src', originalLogoSrc);
							$(input).val('');
							return;
						}
						
						// Passed validation → preview the image
						var reader = new FileReader();
						reader.onload = function(a){
							$('#showPreviewLogo').attr('src', a.target.result);
							// Update the original src to the newly valid image
							originalLogoSrc = a.target.result;
						};
						reader.readAsDataURL(file);
					}
				}
			</script>
			<input type="file" name="d_logo" id="clickMeImage" onchange="readURL(this);" accept=".jpg,.jpeg,.png">
			
		</div>	
		
		<h3>Personal Details</h3>
		<div class="input_box"><p>First Name *</p><input type="text" name="d_f_name" maxlength="20" placeholder="Enter First Name" value="<?php if(!empty($row['d_f_name'])){echo $row['d_f_name'];}?>" required></div>
		
		<div class="input_box"><p>Last Name (Optional)</p><input type="text" name="d_l_name" maxlength="20" placeholder="Enter Last Name  (Optional)" value="<?php if(!empty($row['d_l_name'])){echo $row['d_l_name'];}?>"></div>
		
		<div class="input_box"><p>Position/Business Category * </p><input type="text" name="d_position" maxlength="20" placeholder="Enter Position/Business Category (Ex. Manager etc.)" value="<?php if(!empty($row['d_position'])){echo $row['d_position'];}?>" required></div>
		
		<div class="input_box"><p>Phone No * </p><input type="text" name="d_contact" maxlength="13" placeholder="Enter Phone Number" value="<?php if(!empty($row['d_contact'])){echo $row['d_contact'];}?>" required></div>
		
		<div class="input_box"><p>Alternet Phone No (Optional)</p><input type="text" name="d_contact2" maxlength="13" placeholder="Enter Alternet Phone Number  (Optional)" value="<?php if(!empty($row['d_contact2'])){echo $row['d_contact2'];}?>" ></div>
		
		<div class="input_box"><p>WhatsApp No * </p><input type="text" name="d_whatsapp" maxlength="13" placeholder="Enter WhatsApp Number"  value="<?php if(!empty($row['d_whatsapp'])){echo $row['d_whatsapp'];}?>" required></div>
		
		<div class="input_box"><p>Address * </p><textarea type="text" name="d_address" maxlength="500" placeholder="Full Address"  required><?php if(!empty($row['d_address'])){echo $row['d_address'];}?></textarea></div>
		
		<div class="input_box"><p>Email Id * </p><input type="email" name="d_email" maxlength="100" placeholder="Email Address" value="<?php if(!empty($row['d_email'])){echo $row['d_email'];}?>" required></div>
		<div class="input_box"><p>Website (Optional) </p><input type="text" name="d_website" maxlength="200" placeholder="Website (Optional)" value="<?php if(!empty($row['d_website'])){echo $row['d_website'];}?>" ></div>
		<div class="input_box"><p>Location (Optional) </p><input type="text" name="d_location" maxlength="999" placeholder="Your Business Location (Optional)" value="<?php if(!empty($row['d_location'])){echo $row['d_location'];}?>" ></div>
		<div class="input_box"><p>Company Est Date *</p><input type="text" name="d_comp_est_date" maxlength="200" placeholder="When your comp. was started?" value="<?php if(!empty($row['d_comp_est_date'])){echo $row['d_comp_est_date'];}?>" required></div>
		
		<div class="input_box"><p>About Us * </p><textarea type="text" name="d_about_us" maxlength="1900" placeholder="About your company/business"  required><?php if(!empty($row['d_about_us'])){echo $row['d_about_us'];}?></textarea></div>
			
		<input type="submit" class="" name="process2" value="Next 3" id="block_loader">
	
<!-------------------form ending----------------------->
	</form>
	
	<?php
	if(isset($_POST['process2'])){
		
		$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'"');
		if(mysqli_num_rows($query)==1){
			
			// Include file validation functions
			if(file_exists('../../includes/file_validation.php')) {
				require_once '../../includes/file_validation.php';
			} elseif(file_exists('../includes/file_validation.php')) {
				require_once '../includes/file_validation.php';
			} elseif(file_exists('includes/file_validation.php')) {
				require_once 'includes/file_validation.php';
			} else {
				// Fallback to original code if file_validation.php doesn't exist
				// compress file function creation 
				function compressImage($source,$destination,$quality){
					$imageInfo=getimagesize($source);
					
					$mime=$imageInfo['mime'];
					
					switch($mime){
						case 'image/jpeg':
						$image=imagecreatefromjpeg($source);
						break;
						case 'image/png':
						$image=imagecreatefrompng($source);
						break;
						case 'image/gif':
						$image=imagecreatefromgif($source);
						break;
						default:
						$image-imagecreatefromjpeg($source);
					}
					imagejpeg($image,$destination,$quality);
					
					return $destination;
				}
			}
			
			// Process logo upload if file is selected
			if(!empty($_FILES['d_logo']['tmp_name'])){
				// Use the validation function if available
				if(function_exists('processImageUploadWithCompression')) {
					$result = processImageUploadWithCompression($_FILES['d_logo'], 55, 250000, ['png', 'jpeg', 'jpg']);
					
					if($result['status']) {
						// Image processed successfully
						$logo = $result['data'];
						$updateLogo = mysqli_query($connect, 'UPDATE digi_card SET d_logo="'.$logo.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
						
						// Also save to file system if needed
						$filename2 = '../favicons/'.date('ymdsih').$_FILES['d_logo']['name'];
						if(copy($_FILES['d_logo']['tmp_name'], $filename2)) {
							$update = mysqli_query($connect, 'UPDATE digi_card SET d_logo_location="'.$filename2.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
						}
					} else {
						// Display error message
						echo $result['message'];
					}
				} else {
					// Fallback to original code
					$filename = $_FILES['d_logo']['name'];
					$imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
					$file_allow = array('png', 'jpeg', 'jpg');
					
					if(in_array($imageFileType, $file_allow)) {
						// Check file size - limit to 250KB
						if($_FILES['d_logo']['size'] <= 250000) {
							$source = $_FILES["d_logo"]['tmp_name'];
							$destination = $_FILES["d_logo"]['tmp_name'];
							$quality = 55;
							
							// Call the function for compressing image
							$compressimage = compressImage($source, $destination, $quality);
							
							$logo = addslashes(file_get_contents($compressimage));
							$filename2 = '../favicons/'.date('ymdsih').$_FILES['d_logo']['name'];
							
							if(move_uploaded_file($compressimage, $filename2)) {
								$update = mysqli_query($connect, 'UPDATE digi_card SET d_logo_location="'.$filename2.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
							} else {
								echo '<div class="alert danger">Image Not uploaded</div>';
							}
							
							$updateLogo = mysqli_query($connect, 'UPDATE digi_card SET d_logo="'.$logo.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
						} else {
							echo '<div class="alert danger">File size exceeds 250KB limit. Please resize your image.</div>';
						}
					} else {
						echo '<div class="alert danger">Only PNG, JPG, JPEG files allowed</div>';
					}
				}
			}
			
			// image upload
			
			// replace 
			
								 
			$d_about_us=str_replace(array("'",'"',';','(',')','“','”',':','%','`','[',']'),array("\'",'\"','\;','\(','\)','\“','\”','\:','\%','\`','\[','\]'),$_POST['d_about_us']);
			
			$d_address=str_replace(array("'",'"',';','(',')','“','”',':','%','`','[',']'),array("\'",'\"','\;','\(','\)','\“','\”','\:','\%','\`','\[','\]'),$_POST['d_address']);
			
			$d_position=str_replace(array("'",'"',';','(',')','“','”',':','%','`','[',']'),array("\'",'\"','\;','\(','\)','\“','\”','\:','\%','\`','\[','\]'),$_POST['d_position']);
			$d_comp_est_date=str_replace(array("'",'"',';','(',')','“','”',':','%','`','[',']'),array("\'",'\"','\;','\(','\)','\“','\”','\:','\%','\`','\[','\]'),$_POST['d_comp_est_date']);
		$update=mysqli_query($connect,'UPDATE digi_card SET 
		
		d_f_name="'.$_POST['d_f_name'].'",
		d_l_name="'.$_POST['d_l_name'].'",
		d_position="'.$d_position.'",
		d_contact="'.$_POST['d_contact'].'",
		d_contact2="'.$_POST['d_contact2'].'",
		d_whatsapp="'.$_POST['d_whatsapp'].'",
		d_address="'.$d_address.'",
		d_email="'.$_POST['d_email'].'",
		d_address="'.$_POST['d_address'].'",
		d_website="'.$_POST['d_website'].'",
		d_location="'.$_POST['d_location'].'",
		d_comp_est_date="'.$d_comp_est_date.'",
		d_about_us="'.$d_about_us.'"
		WHERE id="'.$_SESSION['card_id_inprocess'].'"');
		
		
		// enter details in database ending
		
		if($update){
			echo '<a href="create_card3.php"><div class="alert info">Details Updated Wait...</div></a>';
			echo '<meta http-equiv="refresh" content="0;URL=create_card3.php">';
			echo '<style>  form {display:none;} </style>';
		}else {
			echo '<a href="create_card2.php"><div class="alert danger">Error! Try Again.</div></a>';
		}
			
		
		}else {
			
			echo '<a href="create_card.php"><div class="alert danger">Detail Not Available. Try Again Click here.</div></a>';
		}
		
	}
	?>

</div>


<footer class="">

<p>Copyright 2025 || <?php echo $_SERVER['HTTP_HOST']; ?></p>

<script data-ad-client="ca-pub-8647574284151945" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>
