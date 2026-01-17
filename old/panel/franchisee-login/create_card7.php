<?php

require('connect.php');
require('header.php');

?>



<?php

$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');
$query2=mysqli_query($connect,'SELECT * FROM products WHERE id="'.$_SESSION['card_id_inprocess'].'" ');

if(mysqli_num_rows($query)==0){
	echo '<meta http-equiv="refresh" content="0;URL=index.php">';
}else {
	$row=mysqli_fetch_array($query);
					
					if(mysqli_num_rows($query2)>>0){
					$row2=mysqli_fetch_array($query2);
					
				
					
					
				}else {
					$insert_digi2=mysqli_query($connect,'INSERT INTO products (id) VALUES ("'.$_SESSION['card_id_inprocess'].'")');
					
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
		<a href="create_card7.php"><div class="nav_cont active"><i class="fa fa-archive"></i> Ecommerce</div></a>
		<a href="create_card6.php"><div class="nav_cont"><i class="fa fa-image"></i> Image Gallery</div></a>
		<a href="preview_page.php"><div class="nav_cont"><i class="fa fa-laptop"></i> Preview Card</div></a>
	
	</div>
	
	<div class="btn_holder">
		<a href="create_card4.php"><div class="back_btn"><i class="fa fa-chevron-circle-left"></i> Back</div></a>
		<a href="create_card6.php"><div class="skip_btn">Skip <i class="fa fa-chevron-circle-right"></i></div></a>
	</div>
	<h1>Ecommerce</h1>
	<p class="sug_alert">(Upload products which people can order online)</p>
	
	<div id="status_remove_img"></div>
	<form action="" method="POST" enctype="multipart/form-data">
	


<!-------------------order ----------------------->
<?php 
for ($m=1;$m <= 20; $m++){
	
	?>
	
	

	<div class="divider2"><div class="num"><?php  echo "$m"; ?></div>
		
		
		<img  src="<?php if(!empty($row2["pro_img$m"])){echo 'data:image/*;base64,'.base64_encode($row2["pro_img$m"]);}else {echo 'images/upload.png';} ?>" alt="Select image" id="<?php  echo "showPreviewLogo$m"; ?>" onclick="<?php  echo "clickFocus($m)"; ?>" >
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
		<input type="file" name="<?php  echo "pro_img$m"; ?>" id="<?php  echo "clickMeImage$m"; ?>"  onchange="<?php  echo "readURL$m(this);"; ?>" accept="image/*"  >
			
		</div>	
		<div class="input_box"><p>Product Name</p><input type="text" name="<?php  echo "pro_name$m"; ?>" maxlength="200" placeholder="Product Name" value="<?php if(!empty($row2["pro_name$m"])){echo $row2["pro_name$m"];}?>" ></div>
		
		<div class="input_box"><p>MRP</p><input type="number" name="<?php  echo "pro_mrp$m"; ?>" maxlength="200" max="500000" min="0" placeholder="MRP " value="<?php if(!empty($row2["pro_mrp$m"])){echo $row2["pro_mrp$m"];}?>" ></div>
		
		<div class="input_box"><p>Selling Price</p><input type="number" name="<?php  echo "pro_price$m"; ?>"  maxlength="200" max="500000" min="0" placeholder="Product Selling Price" value="<?php if(!empty($row2["pro_price$m"])){echo $row2["pro_price$m"];}?>" ></div>
		
		<div class="input_box"><p>Product Tax</p><input type="number" name="<?php  echo "pro_tax$m"; ?>"  maxlength="200" max="500000" min="0" placeholder="Tax Rate on product" value="<?php if(!empty($row2["pro_tax$m"])){echo $row2["pro_tax$m"];}?>" ></div>
		
		
	</div>
	
	
	<?php

// php incrementer form is ended
}

?>
<!-------------------service 1 ----------------------->
		
		
		
		<input type="submit" class="" name="product" value="Add Product" id="block_loader">
	
<!-------------------form ending----------------------->
	</form>
	
	
	
	
	<?php
	if(isset($_POST['product'])){
		
		$query=mysqli_query($connect,'SELECT * FROM products WHERE id="'.$_SESSION['card_id_inprocess'].'" ');
		if(mysqli_num_rows($query)==1){
		
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
				for($x=1;$x<=20;$x++){
				if(!empty($_FILES["pro_img$x"]['tmp_name'])) {
					$source = $_FILES["pro_img$x"]['tmp_name'];
					$destination = $_FILES["pro_img$x"]['tmp_name'];
					
					// Check file size - limit to 250KB
					if($_FILES["pro_img$x"]['size'] <= 250000) {
						$quality = 65;
						
						// Call the function for compressing image
						$compressimage = compressImage($source, $destination, $quality);
						
						$pro_img = addslashes(file_get_contents($compressimage));
						
						$update = mysqli_query($connect, "UPDATE products SET pro_img$x='".$pro_img."' WHERE id='".$_SESSION['card_id_inprocess']."' ");
					} else {
						echo '<div class="alert danger">File size for Product Image '.($x).' exceeds 250KB limit. Please resize your image.</div>';
					}
				}
		}
				
		//replace--------
		
	for($x=1;$x<=20;$x++){
				 $pro_name=str_replace(array('"',"'",'<','>'),array('\"',"\'",'\<','\>'),$_POST["pro_name$x"]);
				 $pro_tax=str_replace(array('"',"'",'<','>','%','e'),array('\"',"\'",'\<','\>','',''),$_POST["pro_tax$x"]);
				 $pro_mrp=str_replace(array('"',"'",'<','>','%','e'),array('\"',"\'",'\<','\>','',''),$_POST["pro_mrp$x"]);
				 $pro_price=str_replace(array('"',"'",'<','>','e'),array('\"',"\'",'\<','\>','',''),$_POST["pro_price$x"]);
				
				
				// update in products database
				$update=mysqli_query($connect,"UPDATE products SET 
						
						pro_name$x='$pro_name',
						pro_mrp$x='$pro_mrp',
						pro_price$x='$pro_price',
						pro_tax$x='$pro_tax',
						uploaded_date='$date'
						
						WHERE id='$_SESSION[card_id_inprocess]' ");
						
						
						if($update){
					
						
						echo '<style>  form {display:none;} </style>';
						
					}else {
						echo '<div class="alert danger">Error! Try Again.</div>';
					}
						
		}
		
		
						echo '<div class="alert success">Product Added</div>';
		echo '<a href=""><div class="next_btn">Re-Edit Products</div></a>';
		
			echo '<a href="create_card6.php"><div class="next_btn">Next</div></a>';
			echo '<meta http-equiv="refresh" content="500;URL=create_card6.php">';
		
			
				
				
			
			
		// enter details in database ending
		
		
			
		
		}else {
			
			echo '<a href="create_card.php"><div class="alert danger">Detail Not Available. Try Again Click here.</div></a>';
		}
		
	}
	?>

</div>


<footer class="">

<p>Copyright 2025 || <?php echo $_SERVER['HTTP_HOST']; ?></p>

<script data-ad-client="ca-pub-2577996436540735" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>
