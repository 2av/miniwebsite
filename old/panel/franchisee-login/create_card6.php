<?php

require('connect.php');
require('header.php');

?>

<?php
$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');
$query2=mysqli_query($connect,'SELECT * FROM digi_card3 WHERE id="'.$_SESSION['card_id_inprocess'].'" ');

if(mysqli_num_rows($query)==0){
	echo '<meta http-equiv="refresh" content="0;URL=index.php">';
}else {
	$row=mysqli_fetch_array($query);
					if(mysqli_num_rows($query2)>>0){
						$row2=mysqli_fetch_array($query2);
					
				}else {
					$insert_digi2=mysqli_query($connect,'INSERT INTO digi_card3 (id) VALUES ("'.$row['id'].'")');
					
					
				}
}


?>

<div class="main3">
	<div class="navigator_up">
		<a href="select_theme.php"><div class="nav_cont " ><i class="fa fa-map"></i> Select Theme</div></a>
		<a href="create_card2.php"><div class="nav_cont"><i class="fa fa-bank"></i> Company Details</div></a>
		<a href="create_card3.php"><div class="nav_cont "><i class="fa fa-facebook"></i> Social Links</div></a>
		<a href="create_card4.php"><div class="nav_cont"><i class="fa fa-rupee"></i> Payment Options</div></a>
		<a href="create_card5.php"><div class="nav_cont"><i class="fa fa-ticket"></i> Products & Services</div></a>
		<a href="create_card7.php"><div class="nav_cont"><i class="fa fa-archive"></i> E-commerce</div></a>
		<a href="create_card6.php"><div class="nav_cont active"><i class="fa fa-image"></i> Image Gallery</div></a>
		<a href="preview_page.php"><div class="nav_cont"><i class="fa fa-laptop"></i> Preview Card</div></a>
	
	</div>

	<div class="btn_holder">
		<a href="create_card5.php"><div class="back_btn"><i class="fa fa-chevron-circle-left"></i> Back</div></a>
		<a href="preview_page.php"><div class="skip_btn">Skip <i class="fa fa-chevron-circle-right"></i></div></a>
	</div>
	<h1>Image Gallery (Upload up to 10 Images)</h1>
	<p class="sug_alert">(Upload images with in 250 KB each image)</p>
	<div id="status_remove_img"></div>
	<form action="" method="POST" enctype="multipart/form-data">
	

<!-------------------form ----------------------->	
<!-------------------service 1 ----------------------->
<?php 
for ($m=1;$m <= 10; $m++){
	
	?>
	<div class="divider"><div class="num"><?php  echo "$m"; ?></div>
		
		<img src="<?php if(!empty($row2["d_gall_img$m"])){echo 'data:image/*;base64,'.base64_encode($row2["d_gall_img$m"]);}else {echo 'images/upload.png';} ?>" alt="Select image" alt="Select image" id="<?php  echo "showPreviewLogo$m"; ?>" onclick="<?php  echo "clickFocus($m)"; ?>" >
		<div class="input_box">
		
		
		
		
			<script>
			
				function clickFocus(vbl){
					$('#clickMeImage'+vbl).click();
				}
				
				function readURL<?php  echo "$m"; ?>(input){
					if(input.files && input.files[0]){
						var reader = new FileReader();
						reader.onload= function (a){
							$('#showPreviewLogo'+<?php  echo "$m"; ?>).attr('src',a.target.result);
						};
						reader.readAsDataURL(input.files[0]);
					}
					
				}
				
			
			</script>
			<input type="file" name="<?php  echo "d_gall_img$m"; ?>" id="<?php  echo "clickMeImage$m"; ?>" class="" onchange="<?php  echo "readURL$m(this);"; ?>" accept="image/*"  >
			
		</div>	
	</div>	
	
	<?php

// php incrementer form is ended
}

?>
	
	
<!-------------------service 2----------------------->
			
		
		
		
		<input type="submit" class="" name="process5" value="Complete & Preview" id="block_loader">
	
<!-------------------form ending----------------------->
	</form>
	
	
	
	<script>
	
							
							// if approved
								function removeData(id,numb){
										console.log(id,numb);
										$('#status_remove_img').css('color','blue');
									
										$.ajax({
											url:'js_request.php',
											method:'POST',
											data:{id_gal:id,d_gall_img:numb},
											dataType:'text',
											success:function(data){
												$('#status_remove_img').html(data);
												$('#showPreviewLogo'+numb).attr('src','images/upload.png');
											}
											
										});
										
									}
	
	</script>
	
	<?php
	if(isset($_POST['process5'])){
		
		$query=mysqli_query($connect,'SELECT * FROM digi_card3 WHERE id="'.$_SESSION['card_id_inprocess'].'" ');
		
		if(mysqli_num_rows($query) >> 0){
			
		// enter details in database
		
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
					
					// compress file function ended
		
		// image upload
		for($x=0; $x<=10; $x++) {
			if(!empty($_FILES["d_gall_img$x"]['tmp_name'])) {
				$source = $_FILES["d_gall_img$x"]['tmp_name'];
				$destination = $_FILES["d_gall_img$x"]['tmp_name'];
				
				// Check file size - limit to 250KB
				if($_FILES["d_gall_img$x"]['size'] <= 250000) {
					$quality = 65;
					
					// Call the function for compressing image
					$compressimage = compressImage($source, $destination, $quality);
					
					$d_gall_img = addslashes(file_get_contents($compressimage));
					$update1 = mysqli_query($connect, "UPDATE digi_card3 SET d_gall_img$x='".$d_gall_img."' WHERE id='".$_SESSION['card_id_inprocess']."' ");
				} else {
					echo '<div class="alert danger">File size for Gallery Image '.($x+1).' exceeds 250KB limit. Please resize your image.</div>';
				}
			}
		}
		
		echo '<a href=""><div class="next_btn">Re-Upload Images</div></a>';
		echo '<a href="preview_page.php"><div class="next_btn">Next to Preview</div></a>';
		echo '<meta http-equiv="refresh" content="500;URL=preview_page.php">';
		echo '<style>  form {display:none;} </style>';
		
		} else {
			echo '<a href="create_card.php"><div class="alert danger">Detail Not Available. Try Again Click here.</div></a>';
		}
		
	}
	?>

</div>


<footer class="">

<p>Copyright 2025 || <?php echo $_SERVER['HTTP_HOST']; ?></p>

<script data-ad-client="ca-pub-2577996436540735" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>
