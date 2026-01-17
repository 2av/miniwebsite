<?php
require('connect.php');
require('header.php');

// Process form submission
if(isset($_POST['process4'])){
    $query=mysqli_query($connect,'SELECT * FROM digi_card2 WHERE id="'.$_SESSION['card_id_inprocess'].'" ');
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
            // Compress file function creation 
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
                        $image=imagecreatefromjpeg($source);
                }
                imagejpeg($image,$destination,$quality);
                
                return $destination;
            }
        }
        
        // Process product names and images
        $upload_success = true;
        $error_message = '';
        
        // Update product names
        for($x=1; $x<=10; $x++) {
            if(isset($_POST["d_pro_name$x"])) {
                $pro_name = mysqli_real_escape_string($connect, $_POST["d_pro_name$x"]);
                $update_name = mysqli_query($connect, "UPDATE digi_card2 SET d_pro_name$x='$pro_name' WHERE id='".$_SESSION['card_id_inprocess']."'");
                
                if(!$update_name) {
                    $upload_success = false;
                    $error_message .= 'Failed to update product name ' . $x . '. ';
                }
            }
            
            // image upload
            if(!empty($_FILES["d_pro_img$x"]['tmp_name'])){
                // Use the validation function if available
                if(function_exists('processImageUploadWithCompression')) {
                    $result = processImageUploadWithCompression($_FILES["d_pro_img$x"], 65, 250000, ['png', 'jpeg', 'jpg']);
                    
                    if($result['status']) {
                        // Image processed successfully
                        $d_pro_img = $result['data'];
                        $update1 = mysqli_query($connect, "UPDATE digi_card2 SET d_pro_img$x='".$d_pro_img."' WHERE id='".$_SESSION['card_id_inprocess']."' ");
                    } else {
                        // Display error message
                        echo $result['message'];
                    }
                } else {
                    // Fallback to original code
                    $filename = $_FILES["d_pro_img$x"]['name'];
                    $imageFileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $file_allow = array('png', 'jpeg', 'jpg');
                    
                    if(in_array($imageFileType, $file_allow)) {
                        // Check file size - limit to 250KB
                        if($_FILES["d_pro_img$x"]['size'] <= 250000) {
                            $source = $_FILES["d_pro_img$x"]['tmp_name'];
                            $destination = $_FILES["d_pro_img$x"]['tmp_name'];
                            $quality = 65;
                            
                            // Call the function for compressing image
                            $compressimage = compressImage($source, $destination, $quality);
                            
                            $d_pro_img = addslashes(file_get_contents($compressimage));
                            $update1 = mysqli_query($connect, "UPDATE digi_card2 SET d_pro_img$x='".$d_pro_img."' WHERE id='".$_SESSION['card_id_inprocess']."' ");
                        } else {
                            echo '<div class="alert danger">File size for Product Image '.($x+1).' exceeds 250KB limit. Please resize your image.</div>';
                        }
                    } else {
                        echo '<div class="alert danger">Only PNG, JPG, JPEG files allowed for Product Image '.($x+1).'</div>';
                    }
                }
            }
        }
        
        // Store result in session
        $_SESSION['product_result'] = [
            'success' => $upload_success,
            'message' => $error_message
        ];
        
        // Redirect to prevent form resubmission
        header('Location: create_card5.php?status=completed');
        exit;
    } else {
        $_SESSION['product_result'] = [
            'success' => false,
            'message' => 'Detail Not Available. Try Again.'
        ];
        
        // Redirect to prevent form resubmission
        header('Location: create_card5.php?status=error');
        exit;
    }
}

// Get card data
$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');
$query2=mysqli_query($connect,'SELECT * FROM digi_card2 WHERE id="'.$_SESSION['card_id_inprocess'].'" ');

if(mysqli_num_rows($query)==0){
    echo '<meta http-equiv="refresh" content="0;URL=index.php">';
} else {
    $row=mysqli_fetch_array($query);
    if(mysqli_num_rows($query2)>>0){
        $row2=mysqli_fetch_array($query2);
    } else {
        $insert_digi2=mysqli_query($connect,'INSERT INTO digi_card2 (id) VALUES ("'.$row['id'].'")');
    }
}
?>

<div class="main3">
<div class="main3">
	<div class="navigator_up">
		<a href="select_theme.php"><div class="nav_cont " ><i class="fa fa-map"></i> Select Theme</div></a>
		<a href="create_card2.php"><div class="nav_cont"><i class="fa fa-bank"></i> Company Details</div></a>
		<a href="create_card3.php"><div class="nav_cont "><i class="fa fa-facebook"></i> Social Links</div></a>
		<a href="create_card4.php"><div class="nav_cont"><i class="fa fa-rupee"></i> Payment Options</div></a>
		<a href="create_card5.php"><div class="nav_cont active"><i class="fa fa-ticket"></i> Products & Services</div></a>
		<a href="create_card7.php"><div class="nav_cont"><i class="fa fa-archive"></i> E-commerce</div></a>
		<a href="create_card6.php"><div class="nav_cont"><i class="fa fa-image"></i> Image Gallery</div></a>
		<a href="preview_page.php"><div class="nav_cont"><i class="fa fa-laptop"></i> Preview Card</div></a>
	
	</div>
	
	<div class="btn_holder">
		<a href="create_card4.php"><div class="back_btn"><i class="fa fa-chevron-circle-left"></i> Back</div></a>
		<a href="create_card7.php"><div class="skip_btn">Skip <i class="fa fa-chevron-circle-right"></i></div></a>
	</div>
	<h1>Products & Services</h1>
	<p class="sug_alert">(Image Formate: jpg, jpeg, png.  Max image size: 250kb each)</p>
	
	<div id="status_remove_img"></div>
	
	<?php
	// Display result messages if available
	if(isset($_GET['status']) && isset($_SESSION['product_result'])) {
		$result = $_SESSION['product_result'];
		
		if($_GET['status'] == 'completed') {
			if($result['success']) {
				echo '<div class="alert success">Products and services saved successfully!</div>';
				echo '<a href="create_card5.php"><div class="next_btn">Edit Products</div></a>';
				echo '<a href="create_card7.php"><div class="next_btn">Next to E-commerce</div></a>';
				// Auto-redirect to create_card7.php after 3 seconds
				echo '<meta http-equiv="refresh" content="3;URL=create_card7.php">';
			} else {
				echo '<div class="alert warning">Save completed with some issues: ' . $result['message'] . '</div>';
			}
		} else if($_GET['status'] == 'error') {
			echo '<div class="alert danger">' . $result['message'] . ' <a href="create_card.php">Click here</a> to try again.</div>';
		}
		
		// Clear the session result to prevent showing it again on refresh
		unset($_SESSION['product_result']);
	}
	
	// Don't show the form if save was successful
	if(!(isset($_GET['status']) && $_GET['status'] == 'completed')):
	?>
	
	<form action="" method="POST" enctype="multipart/form-data">
	
	<?php 
	for ($m=1; $m <= 10; $m++){
	?>
		<div class="divider"><div class="num"><?php echo "$m"; ?></div>
			<?php if(!empty($row2["d_pro_img$m"]) || !empty($row2["d_pro_name$m"])): ?>
			<div class="delImg" onclick="removeData(<?php echo $row2['id']; ?>,<?php echo $m; ?>)"><i class="fa fa-trash-o"></i></div>
			<?php endif; ?>
			<div class="input_box"><p><?php echo "$m"; ?>th Product & Service</p>
				<input type="text" name="<?php echo "d_pro_name$m"; ?>" maxlength="200" 
					   placeholder="Product/Service Name" 
					   value="<?php if(!empty($row2["d_pro_name$m"])){echo htmlspecialchars($row2["d_pro_name$m"]);}?>" >
			</div>
			<img src="<?php if(!empty($row2["d_pro_img$m"])){echo 'data:image/*;base64,'.base64_encode($row2["d_pro_img$m"]);}else {echo 'images/upload.png';} ?>" 
				 alt="Select image" id="<?php echo "showPreviewLogo$m"; ?>" 
				 onclick="<?php echo "clickFocus($m)"; ?>" >
			<div class="input_box">
				<script>
					function clickFocus(vbl){
						$('#clickMeImage'+vbl).click();
					}
					
					function readURL<?php echo "$m"; ?>(input){
						if(input.files && input.files[0]){
							var reader = new FileReader();
							reader.onload = function (a){
								$('#showPreviewLogo'+<?php echo "$m"; ?>).attr('src',a.target.result);
							};
							reader.readAsDataURL(input.files[0]);
						}
					}
				</script>
				<input type="file" name="<?php echo "d_pro_img$m"; ?>" 
					   id="<?php echo "clickMeImage$m"; ?>" class="" 
					   onchange="<?php echo "readURL$m(this);"; ?>" accept=".jpg,.jpeg,.png">
			</div>	
		</div>
	<?php
	}
	?>
		
		<input type="submit" class="" name="process4" value="Next to E-commerce" id="block_loader">
	</form>
	
	<?php endif; ?>
	
	<script>
		function removeData(id, numb){
			if(confirm('Are you sure you want to remove this product/service?')) {
				console.log(id, numb);
				$('#status_remove_img').css('color','blue');
				
				$.ajax({
					url: 'js_request.php',
					method: 'POST',
					data: {id: id, d_pro_img: numb, d_pro_name: numb},
					dataType: 'text',
					success: function(data){
						$('#status_remove_img').html(data);
						$('#showPreviewLogo'+numb).attr('src','images/upload.png');
						// Clear the input field as well
						$('input[name="d_pro_name'+numb+'"]').val('');
						// Scroll to the status message
						$('html, body').animate({
							scrollTop: $('#status_remove_img').offset().top - 100
						}, 500);
					}
				});
			}
		}
		
		// Prevent form resubmission when page is refreshed
		if (window.history.replaceState) {
			window.history.replaceState(null, null, window.location.href);
		}
	</script>
</div>


<footer class="">

<p>Copyright 2025 || <?php echo $_SERVER['HTTP_HOST']; ?></p>

<script data-ad-client="ca-pub-8647574284151945" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>
