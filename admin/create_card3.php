<?php

require_once(__DIR__ . '/../app/config/database.php');
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
		<a href="select_theme.php"><div class="nav_cont  " ><i class="fa fa-map"></i> Select Theme</div></a>
		<a href="create_card2.php"><div class="nav_cont "><i class="fa fa-bank"></i> Company Details</div></a>
		<a href="create_card3.php"><div class="nav_cont active"><i class="fa fa-facebook"></i> Social Links</div></a>
		<a href="create_card4.php"><div class="nav_cont"><i class="fa fa-rupee"></i> Payment Options</div></a>
		<a href="create_card5.php"><div class="nav_cont "><i class="fa fa-ticket"></i> Products & Services</div></a>
		<a href="create_card7.php"><div class="nav_cont"><i class="fa fa-archive"></i> E-commerce</div></a>
		<a href="create_card6.php"><div class="nav_cont"><i class="fa fa-image"></i> Image Gallery</div></a>
		<a href="preview_page.php"><div class="nav_cont"><i class="fa fa-laptop"></i> Preview Card</div></a>
	
	</div>
	
	<div class="btn_holder">
		<a href="create_card2.php"><div class="back_btn"><i class="fa fa-chevron-circle-left"></i> Back</div></a>
		<a href="create_card4.php"><div class="skip_btn">Skip <i class="fa fa-chevron-circle-right"></i></div></a>
	</div>
	<h1>Social Links</h1>
	
	<form action="" method="POST" enctype="multipart/form-data">
	

<!-------------------form ----------------------->	
		<h3>Social Media Links</h3>
		<div class="input_box"><p>Facebook Link(Optional)</p><input type="text" name="d_fb" maxlength="200" placeholder="facebook Link" value="<?php if(!empty($row['d_fb'])){echo $row['d_fb'];}?>" ></div>
		
		<div class="input_box"><p>X Link(Optional)</p><input type="text" name="d_twitter" maxlength="200" placeholder="Twitter Link " value="<?php if(!empty($row['d_twitter'])){echo $row['d_twitter'];}?>"></div>
		
		<div class="input_box"><p>Instagram Link(Optional) </p><input type="text" name="d_instagram" maxlength="200" placeholder="Instagram Link" value="<?php if(!empty($row['d_instagram'])){echo $row['d_instagram'];}?>" ></div>
		
		<div class="input_box"><p>LinkedIn Link(Optional)</p><input type="text" name="d_linkedin" maxlength="200" placeholder="Linked in Link" value="<?php if(!empty($row['d_linkedin'])){echo $row['d_linkedin'];}?>" ></div>
		
		<div class="input_box"><p>Youtube Link(Optional)</p><input type="text" name="d_youtube" maxlength="200" placeholder="Youtube Page Link" value="<?php if(!empty($row['d_youtube'])){echo $row['d_youtube'];}?>" ></div>
		
		<div class="input_box"><p>Pinterest Link(Optional)</p><input type="text" name="d_pinterest" maxlength="200" placeholder="Pinterest Link"  value="<?php if(!empty($row['d_pinterest'])){echo $row['d_pinterest'];}?>" ></div>
		
		<h3>Youtube Video Links</h3>
		<?php
		// Generate 20 youtube input boxes for admin create flow
		for ($i = 1; $i <= 20; $i++) {
			$field = 'd_youtube' . $i;
			$val = !empty($row[$field]) ? $row[$field] : '';
		?>
		<div class="input_box"><p>Youtube Video Link <?php echo $i; ?> (Optional)</p><input type="text" name="<?php echo $field; ?>" maxlength="200" placeholder="<?php echo $i; ?>th Youtube Video Link"  value="<?php echo $val; ?>" ></div>
		<?php } ?>
		
		
		
		<input type="submit" class="" name="process3" value="Next 4" id="block_loader">
	
<!-------------------form ending----------------------->
	</form>
	
	<?php
	if(isset($_POST['process3'])){
		
		$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'"');
		if(mysqli_num_rows($query)==1){
			
		// enter details in database
				
			// Build update SQL dynamically for youtube fields + other social fields
			$updates = array();
			$updates[] = 'd_fb="'.mysqli_real_escape_string($connect, $_POST['d_fb']).'"';
			$updates[] = 'd_twitter="'.mysqli_real_escape_string($connect, $_POST['d_twitter']).'"';
			$updates[] = 'd_instagram="'.mysqli_real_escape_string($connect, $_POST['d_instagram']).'"';
			$updates[] = 'd_linkedin="'.mysqli_real_escape_string($connect, $_POST['d_linkedin']).'"';
			$updates[] = 'd_youtube="'.mysqli_real_escape_string($connect, $_POST['d_youtube']).'"';
			$updates[] = 'd_pinterest="'.mysqli_real_escape_string($connect, $_POST['d_pinterest']).'"';
			for ($i = 1; $i <= 20; $i++) {
				$field = 'd_youtube' . $i;
				$value = isset($_POST[$field]) ? mysqli_real_escape_string($connect, $_POST[$field]) : '';
				$updates[] = $field . '="' . $value . '"';
			}
			$update_sql = 'UPDATE digi_card SET ' . implode(', ', $updates) . ' WHERE id="'.$_SESSION['card_id_inprocess'].'"';
			$update = mysqli_query($connect, $update_sql);
			
		// enter details in database ending
		
		if($update){
			echo '<a href="create_card4.php"><div class="alert info">Details Updated Wait...</div></a>';
			echo '<meta http-equiv="refresh" content="0;URL=create_card4.php">';
			echo '<style>  form {display:none;} </style>';
		}else {
			echo '<a href="create_card3.php"><div class="alert danger">Error! Try Again.</div></a>';
		}
			
		
		}else {
			
			echo '<a href="create_card.php"><div class="alert danger">Detail Not Available. Try Again Click here.</div></a>';
		}
		
	}
	?>

</div>


 <footer class="footer-area"><center>
           <br />
                    <a href="index.html" class="footer-logo">
                        						<img src="../panel/images/f_logo.png" alt="Vcard" width="auto" height="50px">
						                    </a>
                    <p>&copy; Copyright 2025 - All Rights Reserved. Crafted With <?php echo $_SERVER['HTTP_HOST']; ?> for Someone Special ! </p> 
					<p><a target="_blank" href="https://support.ajooba.io">Support Forum</a> | <a target="_blank" href="https://support.ajooba.io/faq">Faq's</a> | <a target="_blank" href="https://support.ajooba.io/articles/category/digital-vcard">Knowlege Base</a> </p>
			
        </center></footer>


