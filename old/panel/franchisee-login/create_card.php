<?php

require('connect.php');
require('header.php');


?>

<div class="main3">

<a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back </h3></a>


<?php
if(isset($_GET['card_number']) && isset($_GET['user_email'])){
		$_SESSION['card_id_inprocess']=$_GET['card_number'];
		$_SESSION['user_email']=$_GET['user_email'];
		$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'" AND f_user_email="'.$_SESSION['f_user_email'].'" ');


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
		
		<?php
		
		// check balance on website
		
		$q_wallet=mysqli_query($connect,'SELECT * FROM wallet WHERE f_user_email="'.$_SESSION['f_user_email'].'" ORDER BY ID DESC');
		
		if(mysqli_num_rows($q_wallet) > 0){
			
			$row_wa=mysqli_fetch_array($q_wallet);
			
			if($row_wa['w_balance'] < 236 ){
				echo '<a href="wallet.php"><div class="alert danger">Oops! Your wallet balance is '.$row_wa['w_balance'].' Rs. Please recherge to create card. balance should be more then 236 to create a card.</div><div class="btn_3">Click here</div></a>';
			}else {
				
				?>
	<h1>Business or Company Name</h1>
	
	<form action="#" method="POST" class="close_form" enctype="multipart/form-data">
		<div class="input_box"><p>Company Name *</p><input type="text" name="d_comp_name" maxlength="199" value="" placeholder="Enter Company Name" required></div>
		
			
		<input type="submit" class="" name="process1" value="Submit & Next" >
	
	
	</form>
	
	
	
	<?php
			}
			
			
		}else {
			echo '<a href="wallet.php"><div class="alert danger">Oops! Your wallet balance is 0.00 Rs. Please recherge to create card</div><div class="btn_3">Click here</div></a>';
		}
	
	// check balance on website
		
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
		
			if($row['d_comp_name']==$_POST['d_comp_name'] && $row['id']==$_SESSION['card_id_inprocess']){
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
				
	$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE d_comp_name="'.$_POST['d_comp_name'].'"  ORDER BY id DESC');
	
	
	if(mysqli_num_rows($query)==0){
		
		  $card_id=str_replace(array(' ','.','&','/','','[',']'),array('-','','','-','',''),$_POST['d_comp_name']);
		$date = date('Y-m-d H:i:s');
		$insert=mysqli_query($connect,'INSERT INTO digi_card (d_comp_name,uploaded_date,d_payment_status,user_email,f_user_email,d_card_status,card_id,validity_date) VALUES ("'.$_POST['d_comp_name'].'","'.$date.'","Created","'.$_SESSION['user_email'].'","'.$_SESSION['f_user_email'].'","Active","'.$card_id.'",DATE_ADD("'.$date.'", INTERVAL 1 YEAR))');
		if($insert){
			
			
	
			
			// inser data in 2nd database 
			
			
			
			echo '<style>  form {display:none;} </style>';
			$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE d_comp_name="'.$_POST['d_comp_name'].'" AND user_email="'.$_SESSION['user_email'].'" order by id desc limit 1');
			$row=mysqli_fetch_array($query);
			
			$insert_digi2=mysqli_query($connect,'INSERT INTO digi_card2 (id,user_email) VALUES ("'.$row['id'].'","'.$_SESSION['user_email'].'")');
			$insert_digi3=mysqli_query($connect,'INSERT INTO digi_card3 (id,user_email) VALUES ("'.$row['id'].'","'.$_SESSION['user_email'].'")');
			
			
				echo '<a href="select_theme.php"><div class="alert success">Company Name Added. CARD Number is:'.$row['card_id'].'<br> Wait... For next page.</div></a>';
				$_SESSION['card_id_inprocess']=$row['id'];
				echo '<meta http-equiv="refresh" content="1;URL=select_theme.php">';
				
				
				// update wallet too
				
			$query_wa=mysqli_query($connect,'SELECT * FROM wallet WHERE f_user_email="'.$_SESSION['f_user_email'].'" ORDER BY id DESC');
	
				$row_wa=mysqli_fetch_array($query_wa);
				
				
					$balance=$row_wa['w_balance']-236;
				
				
				// insert into wallet
				$insert_wall=mysqli_query($connect,'INSERT INTO wallet (f_user_email,w_withdraw,w_order_id,w_balance,uploaded_date) VALUES ("'.$_SESSION['f_user_email'].'","-236","'.$row['id'].'","'.$balance.'","'.$date.'")');
   
				
		}
	}else {
		
		// if card id is already available then this function will run
		$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE d_comp_name="'.$_POST['d_comp_name'].'" ');
		$count=mysqli_num_rows($query);
			$row=mysqli_fetch_array($query);
			
			
		$card_id=str_replace(array(' ','.','&','/','','[',']'),array('-','','','-','',''),$_POST['d_comp_name']).($count+1);
		$date = date('Y-m-d H:i:s');
		 
			 
			
			
			
			
			
			$insert=mysqli_query($connect,'INSERT INTO digi_card (d_comp_name,uploaded_date,d_payment_status,user_email,f_user_email,d_card_status,card_id,validity_date) VALUES ("'.$_POST['d_comp_name'].'","'.$date.'","Created","'.$_SESSION['user_email'].'","'.$_SESSION['f_user_email'].'","Active","'.$card_id.'",DATE_ADD("'.$date.'", INTERVAL 1 YEAR))');
		if($insert){
			// inser data in 2nd database 
			
			echo '<style>  form {display:none;} </style>';
			$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE d_comp_name="'.$_POST['d_comp_name'].'" AND user_email="'.$_SESSION['user_email'].'" order by id desc limit 1');
			$row=mysqli_fetch_array($query);
			
			$insert_digi2=mysqli_query($connect,'INSERT INTO digi_card2 (id,user_email) VALUES ("'.$row['id'].'","'.$_SESSION['user_email'].'")');
			$insert_digi3=mysqli_query($connect,'INSERT INTO digi_card3 (id,user_email) VALUES ("'.$row['id'].'","'.$_SESSION['user_email'].'")');
			
			
				echo '<a href="select_theme.php"><div class="alert success">Company Name Added. CARD Number is:'.$row['card_id'].'<br> Wait... For next page.</div></a>';
				$_SESSION['card_id_inprocess']=$row['id'];
				echo '<meta http-equiv="refresh" content="1;URL=select_theme.php">';
	
	// update wallet too
				
			$query_wa=mysqli_query($connect,'SELECT * FROM wallet WHERE f_user_email="'.$_SESSION['f_user_email'].'" ORDER BY id DESC');
	
				$row_wa=mysqli_fetch_array($query_wa);
				
				
					$balance=$row_wa['w_balance']-236;
				
				
				// insert into wallet
			$insert_wall=mysqli_query($connect,'INSERT INTO wallet (f_user_email,w_withdraw,w_order_id,w_balance,uploaded_date) VALUES ("'.$_SESSION['f_user_email'].'","-236","'.$row['id'].'","'.$balance.'","'.$date.'")');
   
				
		}
		
		
		
	}
	
}
?>

</div>




<footer class="">

<p>Copyright 2025 || <?php echo $_SERVER['HTTP_HOST']; ?></p>

<script data-ad-client="ca-pub-2577996436540735" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>