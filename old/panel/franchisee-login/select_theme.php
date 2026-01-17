<?php

require('connect.php');
require('header.php');

?>

<div class="main3">
<?php

if(isset($_GET['card_number'])){
		$_SESSION['card_id_inprocess']=$_GET['card_number'];
		$_SESSION['user_email']=$_GET['user_email'];
	}else {
		
	}

$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'" AND f_user_email="'.$_SESSION['f_user_email'].'" ');



if(mysqli_num_rows($query)==0){
	echo '<meta http-equiv="refresh" content="200;URL=index.php">';
}else {
	$row=mysqli_fetch_array($query);
}

?>

	<div class="btn_holder">
	<div class="navigator_up">
		<a href="select_theme.php"><div class="nav_cont active" ><i class="fa fa-map"></i> Select Theme</div></a>
		<a href="create_card2.php"><div class="nav_cont"><i class="fa fa-bank"></i> Company Details</div></a>
		<a href="create_card3.php"><div class="nav_cont "><i class="fa fa-facebook"></i> Social Links</div></a>
		<a href="create_card4.php"><div class="nav_cont"><i class="fa fa-rupee"></i> Payment Options</div></a>
		<a href="create_card5.php"><div class="nav_cont"><i class="fa fa-ticket"></i> Products & Services</div></a>
		<a href="create_card7.php"><div class="nav_cont"><i class="fa fa-archive"></i> E-commerce</div></a>
		<a href="create_card6.php"><div class="nav_cont"><i class="fa fa-image"></i> Image Gallery</div></a>
		<a href="preview_page.php"><div class="nav_cont"><i class="fa fa-laptop"></i> Preview Card</div></a>
	
	</div>
	
	
		<a href="create_card.php"><div class="back_btn"><i class="fa fa-chevron-circle-left"></i> Back</div></a>
		<a href="create_card2.php"><div class="skip_btn">Skip <i class="fa fa-chevron-circle-right"></i></div></a>
	</div>
	<h1>Select the desine of your card.</h1>
	<center>
	<div class="theme"><?php if($row['d_css']=='card_css8.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css8.css"><img src="../images/template8.png"></a>
	</div>	
	
	<div class="theme "><?php if($row['d_css']=='card_css10.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css10.css"><img src="../images/template10.png"></a>
	</div>
	<div class="theme "><?php if($row['d_css']=='card_css9.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css9.css"><img src="../images/template9.png"></a>
	</div>
	<div class="theme "><?php if($row['d_css']=='card_css1.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css1.css"><img src="../images/template.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css2.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css2.css"><img src="../images/template1.png"></a>
	</div>
	
	<div class="theme"><?php if($row['d_css']=='card_css3.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css3.css"><img src="../images/template2.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css4.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css4.css"><img src="../images/template3.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css5.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css5.css"><img src="../images/template4.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css6.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css6.css"><img src="../images/template5.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css7.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css7.css"><img src="../images/template7.png"></a>
	</div>
		<div class="theme"><?php if($row['d_css']=='card_css11.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css11.css"><img src="../images/template11.png"></a>
	</div>
		<div class="theme"><?php if($row['d_css']=='card_css12.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css12.css"><img src="../images/template12.png"></a>
	</div>
		<div class="theme"><?php if($row['d_css']=='card_css13.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css13.css"><img src="../images/template13.png"></a>
	</div>
		<div class="theme"><?php if($row['d_css']=='card_css14.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css14.css"><img src="../images/template14.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css15.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css15.css"><img src="../images/template15.png"></a>
	</div>
	
			<div class="theme"><?php if($row['d_css']=='card_css16.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css16.css"><img src="../images/template16.png"></a>
	</div>
		<div class="theme"><?php if($row['d_css']=='card_css17.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css17.css"><img src="../images/template17.png"></a>
	</div>
		<div class="theme"><?php if($row['d_css']=='card_css18.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css18.css"><img src="../images/template18.png"></a>
	</div>
		<div class="theme"><?php if($row['d_css']=='card_css19.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css19.css"><img src="../images/template19.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css20.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css20.css"><img src="../images/template20.png"></a>
	</div>
	
	<div class="theme"><?php if($row['d_css']=='card_css21.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css21.css"><img src="../images/template21.png"></a>
	</div>
		<div class="theme"><?php if($row['d_css']=='card_css22.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css22.css"><img src="../images/template22.png"></a>
	</div>
		<div class="theme"><?php if($row['d_css']=='card_css23.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css23.css"><img src="../images/template23.png"></a>
	</div>
		<div class="theme"><?php if($row['d_css']=='card_css24.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css24.css"><img src="../images/template24.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css25.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css25.css"><img src="../images/template25.png"></a>
	</div>
	
		<div class="theme"><?php if($row['d_css']=='card_css26.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css26.css"><img src="../images/template26.png"></a>
	</div>
		<div class="theme"><?php if($row['d_css']=='card_css27.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css27.css"><img src="../images/template27.png"></a>
	</div>
		<div class="theme"><?php if($row['d_css']=='card_css28.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css28.css"><img src="../images/template28.png"></a>
	</div>
		<div class="theme"><?php if($row['d_css']=='card_css29.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css29.css"><img src="../images/template29.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css30.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css30.css"><img src="../images/template30.png"></a>
	</div>
		<div class="theme"><?php if($row['d_css']=='card_css31.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css31.css"><img src="../images/template31.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css32.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css32.css"><img src="../images/template32.png"></a>
	</div>
	</center>




<?php
if(isset($_GET['d_css'])){
				
$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'"');
		if(mysqli_num_rows($query)==1){
			
		// enter details in database
			
			$update=mysqli_query($connect,'UPDATE digi_card SET 
			
			d_css="'.$_GET['d_css'].'"
			
			WHERE id="'.$_SESSION['card_id_inprocess'].'"');
			
		// enter details in database ending
		
		if($update){
			echo '<a href="create_card2.php"><input type="submit" class="" name="process2" value="Next 3" id="block_loader">';
			echo '<div class="alert info">Theme Saved. Wait...</div></a>';
			echo '<meta http-equiv="refresh" content="1;URL=create_card2.php">';
			echo '<style>  form {display:none;} </style>';
		}else {
			echo '<a href="select_theme.php"><div class="alert danger">Error! Try Again.</div></a>';
		}
				
		}
	}else {
		
		
	}
	

?>

</div>



 <!-- Start Footer Area -->
 <footer class="footer-area">
     <br /><br /><br /><br /><br /><br />
     <center>
           
                    <a href="index.html" class="footer-logo">
                        						<img src="/panel/images/logo.png" alt="Vcard" width="auto" height="50px">
						                    </a>
                    <p>&copy; Copyright 2025 - All Rights Reserved. Crafted With <?php echo $_SERVER['HTTP_HOST']; ?> for Someone Special ! </p> 
					<p><a target="_blank" href="https://support.ajooba.io">Support Forum</a> | <a target="_blank" href="https://support.ajooba.io/faq">Faq's</a> | <a target="_blank" href="https://support.ajooba.io/articles/category/digital-vcard">Knowlege Base</a> </p>
			
        </center></footer>
	<!-- End Footer Area -->