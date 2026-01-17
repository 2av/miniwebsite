<?php

require('connect.php');
require('header.php');

?>

<div class="main3">

<div class="navigator_up">
		<a href="select_theme.php"><div class="nav_cont  " ><i class="fa fa-map"></i> Select Theme</div></a>
		<a href="create_card2.php"><div class="nav_cont"><i class="fa fa-bank"></i> Company Details</div></a>
		<a href="create_card3.php"><div class="nav_cont "><i class="fa fa-facebook"></i> Social Links</div></a>
		<a href="create_card4.php"><div class="nav_cont"><i class="fa fa-rupee"></i> Payment Options</div></a>
		<a href="create_card5.php"><div class="nav_cont "><i class="fa fa-ticket"></i> Products & Services</div></a>
		<a href="create_card7.php"><div class="nav_cont"><i class="fa fa-archive"></i> E-commerce</div></a>
		<a href="create_card6.php"><div class="nav_cont"><i class="fa fa-image"></i> Image Gallery</div></a>
		<a href="preview_page.php"><div class="nav_cont"><i class="fa fa-laptop"></i> Preview Card</div></a>
	
	</div>
	<div class="btn_holder">
		<a href="index.php"><div class="back_btn"><i class="fa fa-chevron-circle-left"></i> Back</div></a>
		<a href="select_theme.php"><div class="skip_btn">Skip <i class="fa fa-chevron-circle-right"></i></div></a>
	</div>
	
<?php
if(isset($_GET['card_number'])){
		$_SESSION['card_id_inprocess']=$_GET['card_number'];
		$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'" ');


	$row=mysqli_fetch_array($query);
	
	if(mysqli_num_rows($query)==0){echo '<div class="alert danger">Card id Removed/Not available.</div>';}else {
		
	
	// updte comp name
	?>
	
	<h1>Update Business or Company Name</h1>
	
	<form action="#" method="POST" class="close_form" enctype="multipart/form-data">
		<div class="input_box"><p>Company Name *</p><input type="text" name="d_comp_name" maxlength="199" value="<?php echo $row['d_comp_name']; ?>" placeholder="Enter Company Name" required></div>
		
			
		<input type="submit" class="" name="process2" value="Submit & Next" >
	
	
	</form>
	
	<?php
		}
		
	}else {
		?>
	<h1>Business or Company Name</h1>
	
	<form action="#" method="POST" class="close_form" enctype="multipart/form-data">
		<div class="input_box"><p>Company Name *</p><input type="text" name="d_comp_name" maxlength="199" value="" placeholder="Enter Company Name" required></div>
		
			
		<input type="submit" class="" name="process1" value="Submit & Next" >
	
	
	</form>
	
	
	
	<?php
	}
	
	// update comp name end

?>

<?php
// u[pdate comp name funtion

	if(isset($_POST['process2'])){	
	$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE d_comp_name="'.$_POST['d_comp_name'].'"  ORDER BY id DESC');
	$row=mysqli_fetch_array($query);
	
	if(mysqli_num_rows($query)==0){
		
		 $card_id=str_replace(array(' ','.','&','/','','[',']'),array('-','','','-','',''),$_POST['d_comp_name']);
		
		$update=mysqli_query($connect,'UPDATE digi_card SET d_comp_name="'.$_POST['d_comp_name'].'", card_id="'.$card_id.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
				echo '<meta http-equiv="refresh" content="1;URL=select_theme.php">';
				echo '<style>  form {display:none;} </style>';
				echo '<div class="alert success">Company Name Updated</div>';
	}else {
		
			if($row['d_comp_name']=$_POST['d_comp_name'] && $row['id']=$_SESSION['card_id_inprocess']){
				echo '<style>  form {display:none;} </style>';
				echo '<meta http-equiv="refresh" content="1;URL=select_theme.php">';
				echo '<div class="alert info">Redirecting...</div>';
			}else{
		// if comp name is not availble in the same id then create new one
		
		$count=mysqli_num_rows($query);
			
		 $card_id=str_replace(array(' ','.','&','/','','[',']'),array('-','','','-','',''),$_POST['d_comp_name']).($count+1);
			$update=mysqli_query($connect,'UPDATE digi_card SET d_comp_name="'.$_POST['d_comp_name'].'", card_id="'.$card_id.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
				echo '<meta http-equiv="refresh" content="1;URL=select_theme.php">';
				echo '<style>  form {display:none;} </style>';
				echo '<div class="alert info">Company/Business Name Updated. </div>';
		
				
			}
			
		
		
		}
	
	}

?>






<?php
if(isset($_POST['process1'])){
				
				
	$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE d_comp_name="'.$_POST['d_comp_name'].'" ');
	if(mysqli_num_rows($query)==0){
		
		
		
				$card_id=str_replace(array(' ','.','&','/','','[',']'),array('-','','','-','',''),$_POST['d_comp_name']);
		$insert=mysqli_query($connect,'INSERT INTO digi_card (d_comp_name,uploaded_date,d_payment_status,user_email,d_card_status,card_id,f_user_email,validity_date) VALUES ("'.$_POST['d_comp_name'].'","'.$date.'","Created","'.$_SESSION['user_email'].'","Active","'.$card_id.'","'.$_SESSION['admin_email'].'",DATE_ADD("'.$date.'", INTERVAL 1 YEAR))');
		if($insert){
			// inser data in 2nd database 
			
			
			$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE d_comp_name="'.$_POST['d_comp_name'].'" AND user_email="'.$_SESSION['user_email'].'" order by id desc limit 1');
			$row=mysqli_fetch_array($query);
			
			$insert_digi2=mysqli_query($connect,'INSERT INTO digi_card2 (id,user_email) VALUES ("'.$row['id'].'","'.$_SESSION['user_email'].'")');
			$insert_digi3=mysqli_query($connect,'INSERT INTO digi_card3 (id,user_email) VALUES ("'.$row['id'].'","'.$_SESSION['user_email'].'")');
			
			
				echo '<a href="select_theme.php"><div class="alert success">Company Name Added. CARD Number is:'.$row['id'].'<br> Wait... For next page.</div></a>';
				$_SESSION['card_id_inprocess']=$row['id'];
				echo '<meta http-equiv="refresh" content="1;URL=select_theme.php">';
				echo '<style>  form {display:none;} </style>';
  
exit; 
				
		}
	}else {
		// if card id is already available then this function will run
		$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE d_comp_name="'.$_POST['d_comp_name'].'" ');
		$count=mysqli_num_rows($query);
			$row=mysqli_fetch_array($query);
			
			
			
					$card_id=str_replace(array(' ','.','&','/','','[',']'),array('-','','','-','',''),$_POST['d_comp_name']).($count+1);
			
			
			$insert=mysqli_query($connect,'INSERT INTO digi_card (d_comp_name,uploaded_date,d_payment_status,user_email,d_card_status,card_id,f_user_email) VALUES ("'.$_POST['d_comp_name'].'","'.$date.'","Created","'.$_SESSION['user_email'].'","Active","'.$card_id.'","'.$_SESSION['admin_email'].'")');
		if($insert){
			// inser data in 2nd database 
			
			echo '<style>  form {display:none;} </style>';
			$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE d_comp_name="'.$_POST['d_comp_name'].'" AND user_email="'.$_SESSION['user_email'].'" order by id desc limit 1');
			$row=mysqli_fetch_array($query);
			
			$insert_digi2=mysqli_query($connect,'INSERT INTO digi_card2 (id,user_email,card_id) VALUES ("'.$row['id'].'","'.$_SESSION['user_email'].'","'.$card_id.'")');
			$insert_digi3=mysqli_query($connect,'INSERT INTO digi_card3 (id,user_email,card_id) VALUES ("'.$row['id'].'","'.$_SESSION['user_email'].'","'.$card_id.'")');
			
			
				echo '<a href="select_theme.php"><div class="alert success">Company Name Added. CARD Number is:'.$row['id'].'<br> Wait... For next page.</div></a>';
				$_SESSION['card_id_inprocess']=$row['id'];
				echo '<meta http-equiv="refresh" content="0;URL=select_theme.php">';
				echo '<style>  form {display:none;} </style>';
		}
		
	
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