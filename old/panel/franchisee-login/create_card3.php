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
		<a href="create_card2.php"><div class="nav_cont"><i class="fa fa-bank"></i> Company Details</div></a>
		<a href="create_card3.php"><div class="nav_cont active"><i class="fa fa-facebook"></i> Social Links</div></a>
		<a href="create_card4.php"><div class="nav_cont"><i class="fa fa-rupee"></i> Payment Options</div></a>
		<a href="create_card5.php"><div class="nav_cont"><i class="fa fa-ticket"></i> Products & Services</div></a>
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
		<div class="input_box"><p>Youtube Video Link (Optional)</p><input type="text" name="d_youtube1" maxlength="200" placeholder="1st Youtube Video Link "  value="<?php if(!empty($row['d_youtube1'])){echo $row['d_youtube1'];}?>" ></div>
		<div class="input_box"><p>Youtube Video Link 2(Optional)</p><input type="text" name="d_youtube2" maxlength="200" placeholder="2nd Youtube Video Link"  value="<?php if(!empty($row['d_youtube2'])){echo $row['d_youtube2'];}?>" ></div>
		<div class="input_box"><p>Youtube Video Link 3(Optional)</p><input type="text" name="d_youtube3" maxlength="200" placeholder="3rd Youtube Video Link"  value="<?php if(!empty($row['d_youtube3'])){echo $row['d_youtube3'];}?>" ></div>
		<div class="input_box"><p>Youtube Video Link 4(Optional)</p><input type="text" name="d_youtube4" maxlength="200" placeholder="4th Youtube Video Link"  value="<?php if(!empty($row['d_youtube4'])){echo $row['d_youtube4'];}?>" ></div>
		<div class="input_box"><p>Youtube Video Link 5(Optional)</p><input type="text" name="d_youtube5" maxlength="200" placeholder="5th Youtube Video Link"  value="<?php if(!empty($row['d_youtube5'])){echo $row['d_youtube5'];}?>" ></div>
		
		
		
		<input type="submit" class="" name="process3" value="Next 4" id="block_loader">
	
<!-------------------form ending----------------------->
	</form>
	
	<?php
	if(isset($_POST['process3'])){
		
		$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'"');
		if(mysqli_num_rows($query)==1){
			
		// enter details in database
				
			$update=mysqli_query($connect,'UPDATE digi_card SET 
			
			d_fb="'.$_POST['d_fb'].'",
			d_twitter="'.$_POST['d_twitter'].'",
			d_instagram="'.$_POST['d_instagram'].'",
			d_linkedin="'.$_POST['d_linkedin'].'",
			d_youtube="'.$_POST['d_youtube'].'",
			d_pinterest="'.$_POST['d_pinterest'].'",
			d_youtube1="'.$_POST['d_youtube1'].'",
			d_youtube2="'.$_POST['d_youtube2'].'",
			d_youtube3="'.$_POST['d_youtube3'].'",
			d_youtube4="'.$_POST['d_youtube4'].'",
			d_youtube5="'.$_POST['d_youtube5'].'"
			WHERE id="'.$_SESSION['card_id_inprocess'].'"');
			
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


<footer class="">

<p>Copyright 2025 || <?php echo $_SERVER['HTTP_HOST']; ?></p>

<script data-ad-client="ca-pub-2577996436540735" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>