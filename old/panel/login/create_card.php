<?php

require('connect.php');
require('header.php');


// sender token script


$query_customer=mysqli_query($connect,'SELECT * FROM customer_login WHERE user_email="'.$_SESSION['user_email'].'"');
$row_customer=mysqli_fetch_array($query_customer);
if(!empty($row_customer['sender_token'])){
	
	$query_franchisee=mysqli_query($connect,'SELECT * FROM franchisee_login WHERE id="'.$row_customer['sender_token'].'"');
	$row_franchisee=mysqli_fetch_array($query_franchisee);
	
	if($row_franchisee && isset($row_franchisee['f_user_email'])){
		$franchisee_email=$row_franchisee['f_user_email'];
	} else {
		$franchisee_email="";
	}
}else {
	$franchisee_email="";
}

?>


<div class="main3">
	<div class="btn_holder">
		<a href="../../customer/dashboard"><div class="back_btn"><i class="fa fa-chevron-circle-left"></i> Back</div></a>
		
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
	<?php $used_changes = isset($row['name_change_count']) ? (int)$row['name_change_count'] : 0; ?>
		<div class="" style="margin-top:6px;">
			<p style="margin:0;opacity:0.8;">Company name change limit: <?php echo $used_changes; ?>/2 used</p>
		</div>
	
		<div class="input_box"><p>Company Name *</p><input type="text" id="d_comp_name" name="d_comp_name" maxlength="199" value="<?php echo $row['d_comp_name']; ?>" placeholder="Enter Company Name" required></div>
		<div class="" style="margin-top:20px;">
			<p style="margin:0;opacity:0.8;">Your MiniWebsite URL Link:
			<span id="url_preview" style="font-weight:600;word-break:break-all;">
				<?php 
				$preview_slug = str_replace(array(' ','.','&','/','','[',']'),array('-','','','-','',''), $row['d_comp_name']);
				echo htmlspecialchars('https://'.$_SERVER['HTTP_HOST'].'/n.php?n='.$preview_slug);
				?>
			</span>
			</p>
		</div>
		<br>
			
		<input type="submit" class="" name="process2" value="Submit & Next" >
	
	
	</form>
	<script>
		(function(){
			function slugify(value){
				var s = value || '';
				s = s.replace(/\s+/g,'-');
				s = s.replace(/[\.\[\]]+/g,'');
				s = s.replace(/&+/g,'');
				s = s.replace(/\/+/, '-');
				return s;
			}
			var input = document.getElementById('d_comp_name');
			var preview = document.getElementById('url_preview');
			if(input && preview){
				input.addEventListener('input', function(){
					var slug = slugify(this.value);
					preview.textContent = '<?php echo 'https://'.$_SERVER['HTTP_HOST'].'/n.php?n='; ?>' + slug;
				});
			}
		})();
	</script>
	
	<?php
		}
		
	}else {
		?>
	<h1>Business or Company Name</h1>
	
	<form action="#" method="POST" class="close_form" enctype="multipart/form-data">
		<div class="input_box"><p>Company Name *</p><input type="text" id="d_comp_name" name="d_comp_name" maxlength="199" value="" placeholder="Enter Company Name" required></div>
		<div class="input_box" style="margin-top:6px;">
			<p style="margin:0;opacity:0.8;">Your MiniWebsite URL Link: <span id="url_preview">https://<?php echo $_SERVER['HTTP_HOST']; ?>/n.php?n=</span></p>
		</div>
		
		<input type="submit" class="" name="process1" value="Submit & Next" >
	
	
	</form>
	<script>
		(function(){
			function slugify(value){
				var s = value || '';
				s = s.replace(/\s+/g,'-');
				s = s.replace(/[\.\[\]]+/g,'');
				s = s.replace(/&+/g,'');
				s = s.replace(/\/+/, '-');
				return s;
			}
			var input = document.getElementById('d_comp_name');
			var preview = document.getElementById('url_preview');
			if(input && preview){
				input.addEventListener('input', function(){
					var slug = slugify(this.value);
					preview.textContent = '<?php echo 'https://'.$_SERVER['HTTP_HOST'].'/n.php?n='; ?>' + slug;
				});
			}
		})();
	</script>
	
	
	
	<?php
	}
	


	// update comp name end


?>




<?php
// u[pdate comp name funtion

	if(isset($_POST['process2'])){	
	// Enforce change limit (max 2)
	$currentCardId = $_SESSION['card_id_inprocess'];
	$limitRow = mysqli_fetch_array(mysqli_query($connect,'SELECT name_change_count FROM digi_card WHERE id="'.$currentCardId.'"'));
	$usedCount = isset($limitRow['name_change_count']) ? (int)$limitRow['name_change_count'] : 0;
	if($usedCount >= 2){
		echo '<style>  form {display:block;} </style>';
		echo '<div class="alert danger">Company name change limit reached (2/2). You cannot change it further.</div>';
	} else {
	$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE d_comp_name="'.$_POST['d_comp_name'].'"  ORDER BY id DESC');
	$row=mysqli_fetch_array($query);
	
	if(mysqli_num_rows($query)==0){
		
		 $card_id=str_replace(array(' ','.','&','/','','[',']'),array('-','','','-','',''),$_POST['d_comp_name']);
		
		$update=mysqli_query($connect,'UPDATE digi_card SET d_comp_name="'.$_POST['d_comp_name'].'", card_id="'.$card_id.'", name_change_count = name_change_count + 1 WHERE id="'.$_SESSION['card_id_inprocess'].'"');
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
			$update=mysqli_query($connect,'UPDATE digi_card SET d_comp_name="'.$_POST['d_comp_name'].'", card_id="'.$card_id.'", name_change_count = name_change_count + 1 WHERE id="'.$_SESSION['card_id_inprocess'].'"');
				echo '<meta http-equiv="refresh" content="1;URL=select_theme.php">';
				echo '<style>  form {display:none;} </style>';
				echo '<div class="alert info">Company/Business Name Updated. </div>';
		
				
			}
			
		
		
		}
	}
	}

?>






<?php
if(isset($_POST['process1'])){
				
				
	$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE d_comp_name="'.$_POST['d_comp_name'].'" ');
	if(mysqli_num_rows($query)==0){
		
		
		
				$card_id=str_replace(array(' ','.','&','/','','[',']'),array('-','','','-','',''),$_POST['d_comp_name']);
		$date = date('Y-m-d H:i:s');
		$insert=mysqli_query($connect,'INSERT INTO digi_card (d_comp_name,uploaded_date,d_payment_status,user_email,d_card_status,card_id,f_user_email,validity_date) VALUES ("'.$_POST['d_comp_name'].'","'.$date.'","Created","'.$_SESSION['user_email'].'","Active","'.$card_id.'","'.$franchisee_email.'",DATE_ADD("'.$date.'", INTERVAL 1 YEAR))');
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
			$date = date('Y-m-d H:i:s');
			
			
			$insert=mysqli_query($connect,'INSERT INTO digi_card (d_comp_name,uploaded_date,d_payment_status,user_email,d_card_status,card_id,f_user_email) VALUES ("'.$_POST['d_comp_name'].'","'.$date.'","Created","'.$_SESSION['user_email'].'","Active","'.$card_id.'","'.$franchisee_email.'")');
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




<footer class="">

<p>Copyright 2025 || <?php echo $_SERVER['HTTP_HOST']; ?></p>

<script data-ad-client="ca-pub-8647574284151945" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>