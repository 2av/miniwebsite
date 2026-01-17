<?php

require_once(__DIR__ . '/../app/config/database.php');
require('header.php');

?>

<div class="main3">
<?php

if(isset($_GET['card_number'])){
		$_SESSION['card_id_inprocess']=$_GET['card_number'];
		$_SESSION['user_email']=$_GET['user_email'];
	}else {
		
	}

$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'"  ');

if(mysqli_num_rows($query)==0){
	echo '<meta http-equiv="refresh" content="2;URL=index.php">';
}else {
	$row=mysqli_fetch_array($query);
}

?>

<div class="navigator_up">
		<a href="select_theme.php"><div class="nav_cont active " ><i class="fa fa-map"></i> Select Theme</div></a>
		<a href="create_card2.php"><div class="nav_cont"><i class="fa fa-bank"></i> Company Details</div></a>
		<a href="create_card3.php"><div class="nav_cont "><i class="fa fa-facebook"></i> Social Links</div></a>
		<a href="create_card4.php"><div class="nav_cont"><i class="fa fa-rupee"></i> Payment Options</div></a>
		<a href="create_card5.php"><div class="nav_cont "><i class="fa fa-ticket"></i> Products & Services</div></a>
		<a href="create_card7.php"><div class="nav_cont"><i class="fa fa-archive"></i> E-commerce</div></a>
		<a href="create_card6.php"><div class="nav_cont"><i class="fa fa-image"></i> Image Gallery</div></a>
		<a href="preview_page.php"><div class="nav_cont"><i class="fa fa-laptop"></i> Preview Card</div></a>
	
	</div>
	<div class="btn_holder">
		<a href="create_card.php"><div class="back_btn"><i class="fa fa-chevron-circle-left"></i> Back</div></a>
		<a href="create_card2.php"><div class="skip_btn">Skip <i class="fa fa-chevron-circle-right"></i></div></a>
	</div>
	<h1>Select the desine of your card.</h1>
	<center>
	<div class="theme"><?php if($row['d_css']=='card_css8.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css8.css"><img src="../panel/images/template8.png"></a>
	</div>	
	
	<div class="theme "><?php if($row['d_css']=='card_css10.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css10.css"><img src="../panel/images/template10.png"></a>
	</div>
	<div class="theme "><?php if($row['d_css']=='card_css9.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css9.css"><img src="../panel/images/template9.png"></a>
	</div>
	<div class="theme "><?php if($row['d_css']=='card_css1.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css1.css"><img src="../panel/images/template.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css2.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css2.css"><img src="../panel/images/template1.png"></a>
	</div>
	
	<div class="theme"><?php if($row['d_css']=='card_css3.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css3.css"><img src="../panel/images/template2.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css4.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css4.css"><img src="../panel/images/template3.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css5.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css5.css"><img src="../panel/images/template4.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css6.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css6.css"><img src="../panel/images/template5.png"></a>
	</div>
	<div class="theme"><?php if($row['d_css']=='card_css7.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css7.css"><img src="../panel/images/template7.png"></a>
	</div>
	
		<div class="theme "><?php if($row['d_css']=='card_css11.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css11.css"><img src="../panel/images/template11.png"></a></div>
		
			<div class="theme "><?php if($row['d_css']=='card_css12.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css12.css"><img src="../panel/images/template12.png"></a></div>
		
			<div class="theme "><?php if($row['d_css']=='card_css13.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css13.css"><img src="../panel/images/template13.png"></a></div>
		
			<div class="theme "><?php if($row['d_css']=='card_css14.css?'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css14.css?"><img src="../panel/images/template14.png"></a></div>
		
			<div class="theme "><?php if($row['d_css']=='card_css15.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css15.css"><img src="../panel/images/template15.png"></a></div>
		
			<div class="theme "><?php if($row['d_css']=='card_css16.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css16.css"><img src="../panel/images/template16.png"></a></div>
		
			<div class="theme "><?php if($row['d_css']=='card_css17.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css17.css"><img src="../panel/images/template17.png"></a></div>
		
			<div class="theme "><?php if($row['d_css']=='card_css18.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css18.css"><img src="../panel/images/template18.png"></a></div>
		
			<div class="theme "><?php if($row['d_css']=='card_css19.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css19.css"><img src="../panel/images/template19.png"></a></div>
		
			<div class="theme "><?php if($row['d_css']=='card_css20.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css20.css"><img src="../panel/images/template20.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css21.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css20.css"><img src="../panel/images/template20.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css20.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css21.css"><img src="../panel/images/template21.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css22.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css22.css"><img src="../panel/images/template22.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css23.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css23.css"><img src="../panel/images/template23.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css24.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css24.css"><img src="../panel/images/template24.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css25.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css25.css"><img src="../panel/images/template25.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css26.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css26.css"><img src="../panel/images/template26.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css27.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css27.css"><img src="../panel/images/template27.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css28.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css28.css"><img src="../panel/images/template28.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css29.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css29.css"><img src="../panel/images/template29.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css30.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css30.css"><img src="../panel/images/template30.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css31.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css31.css"><img src="../panel/images/template31.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css32.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css32.css"><img src="../panel/images/template32.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css42.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css42.css"><img src="../panel/images/bg42.jpg"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css33.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css33.css"><img src="../panel/images/bg33.jpg"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css34.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css34.css"><img src="../panel/images/jay.gif"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css35.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css35.css"><img src="../panel/images/bg34.jpg"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css36.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css36.css"><img src="../panel/images/bg36.jpg"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css37.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css37.css"><img src="../panel/images/bg37.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css38.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css38.css"><img src="../panel/images/bg38.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css39.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css39.css"><img src="../panel/images/bg39.png"></a></div>
		
		
	<div class="theme "><?php if($row['d_css']=='card_css40.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css40.css"><img src="../panel/images/bg40.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css41.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css41.css"><img src="../panel/images/bg41.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css43.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css43.css"><img src="../panel/images/card43.jpg"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css44.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css44.css"><img src="../panel/images/bg44.png"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css45.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css45.css"><img src="../panel/images/bg45.jpg"></a></div>

	
	<div class="theme "><?php if($row['d_css']=='card_css46.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css46.css"><img src="../panel/images/bg46.jpg"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css47.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css47.css"><img src="../panel/images/bg47.jpg"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css48.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css48.css"><img src="../panel/images/bg48.jpg"></a></div>
		
		
		<div class="theme "><?php if($row['d_css']=='card_css49.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css49.css"><img src="../panel/images/bg49.jpg"></a></div>
		
		
		<div class="theme "><?php if($row['d_css']=='card_css50.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css50.css"><img src="../panel/images/bg50.jpg"></a></div>
		
		<div class="theme "><?php if($row['d_css']=='card_css51.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css51.css"><img src="../panel/images/bg51.jpg"></a></div>
		
			<div class="theme "><?php if($row['d_css']=='card_css52.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css52.css"><img src="../panel/images/bg52.jpg"></a></div>
		
			<div class="theme "><?php if($row['d_css']=='card_css53.css'){echo '<div class="selected">Selected</div>';} ?>
		<a href="select_theme.php?d_css=card_css53.css"><img src="../panel/images/bg53.jpg"></a></div>
		
	
		
		
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




<footer class="">

<p>Copyright 2025 || <?php echo $_SERVER['HTTP_HOST']; ?></p>

<script data-ad-client="ca-pub-8647574284151945" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>


