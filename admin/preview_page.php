<?php

require_once(__DIR__ . '/../app/config/database.php');

?>


 <?php

if(!isset($_SESSION['admin_email'])){echo '<meta http-equiv="refresh" content="0;URL=login.php">';}
?>


<?php

if(isset($_GET['card_number'])){
		$_SESSION['card_id_inprocess']=$_GET['card_number'];
		$_SESSION['user_email']=$_GET['user_email'];
	}

$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" ');

if(mysqli_num_rows($query)==0){
	echo '<meta http-equiv="refresh" content="0;URL=index.php">';
}else {
	$row=mysqli_fetch_array($query);
}



?>
<link rel="stylesheet" href="<?php if(!empty($row['d_css'])){echo '../panel/'.$row['d_css'];}else {echo '../panel/card_css1.css';} ?>" >


<!----------------------copy from here ------------------------->

<?php
if($row['d_payment_status']=='Created'){echo '<div class="card_status1"><p>Trial</p></div>';}else if($row['d_payment_status']=='Success'){echo '<div class="card_status2"><p>Active</p></div>';}else if($row['d_payment_status']=='Failed'){echo '<div class="card_status3"><p>Inactive</p></div>';}

?>

<div class="btn_holder">
		<a href="index.php"><div class="back_btn"><i class="fa fa-chevron-circle-left"></i> Back</div></a>
		
	</div>




	<div class="card" id="home">
			
			<div class="card_content"><img src="<?php if(!empty($row['d_logo'])){echo 'data:image/*;base64,'.base64_encode($row['d_logo']);} ?>" alt="Logo"></div>
			<div class="card_content2">
				<h2><?php if(!empty($row['d_comp_name'])){echo $row['d_comp_name'];} ?></h2>
				<p><?php if(!empty($row['d_f_name'])){echo $row['d_f_name'].' '.$row['d_l_name'];} ?></p>
				<p><?php if(!empty($row['d_position'])){echo $row['d_position'];} ?></p>
				
			</div>
			<div class="dis_flex">
				<?php if(!empty($row['d_contact'])){echo '<a href="tel:+91'.$row['d_contact'].'" target="_blank"><div class="link_btn"><i class="fa fa-phone"></i> Call</div></a>';} ?>
				<?php if(!empty($row['d_whatsapp'])){echo '<a href="https://api.whatsapp.com/send?phone=91'.str_replace('+91','',$row['d_whatsapp']).'&text=Hi, '.$row['d_comp_name'].'" target="_blank"><div class="link_btn"><i class="fa fa-whatsapp"></i> WhatsApp</div></a>';} ?>
				
				
				
				<?php if(!empty($row['d_location'])){echo '<a href="'.$row['d_location'].'" target="_blank"><div class="link_btn"><i class="fa fa-map-marker"></i> Direction</div></a>';} ?>
				<?php if(!empty($row['d_email'])){echo '<a href="Mailto:'.$row['d_email'].'" target="_blank"><div class="link_btn"><i class="fa fa-envelope"></i> Mail</div></a>';} ?>
				<?php if(!empty($row['d_website'])){echo '<a href="https://'.$row['d_website'].'" target="_blank"><div class="link_btn"><i class="fa fa-globe"></i> Website</div></a>';} ?>
			
			</div>
	
			<div class="contact_details">
				<?php if(!empty($row['d_contact'])){echo '<div class="contact_d"><i class="fa fa-phone"></i><p>'.$row['d_contact'].'</p></div>';} ?>
				<?php if(!empty($row['d_contact2'])){echo '<div class="contact_d"><i class="fa fa-phone"></i><p>'.$row['d_contact2'].'</p></div>';} ?>
				<?php if(!empty($row['d_email'])){echo '<div class="contact_d"><i class="fa fa-envelope"></i><p>'.$row['d_email'].'</p></div>';} ?>
				<?php if(!empty($row['d_address'])){echo '<div class="contact_d"><i class="fa fa-map-marker" ></i><p>'.$row['d_address'].'</p></div>';} ?>
				
			</div>
			
			<div class="dis_flex">
				<div class="share_wtsp">
					<form action="https://api.whatsapp.com/send" id="wtsp_form" target="_blank"><input type="text"  name="phone" placeholder="WhatsApp Number with Country code	" value="+91"><input type="hidden" name="text" value="https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $row['card_id']; ?>"><div class="wtsp_share_btn" onclick="subForm()"><i class="fa fa-whatsapp"></i> Share On WhatsApp</div></form>
					
					<script>
					
					$(document).ready(function(){
						$('.wtsp_share_btn').on('click',function(){
							$('#wtsp_form').submit();
						})
						
					})
					</script>
				</div>
			</div>
			
			<div class="dis_flex">
			
			<?php if(!empty($row['d_contact'])){echo '<a href="../contact_download.php?id='.$row['id'].'"><div class="big_btns">Save to Contacts <i class="fa fa-download"></i></div></a>';} ?>
				
				<div class="big_btns" id="share_box_pop">Share <i class="fa fa-share-alt"></i></div>
			
			<div><a class="shadow-button save-card-button"><i class="fas fa-cloud-download-alt shadow-button-icon"></i>Save Card</a></div>
			
				<div class="share_box">
				
				
				<div class="close" id="close_sharer">&times;</div>
				<p>Share My Digital Card </p>
						<a href="https://api.whatsapp.com/send?text=https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $row['card_id']; ?>"><div class="shar_btns"><i class="fa fa-whatsapp" id="whatsapp2"  target="_blank"></i><p>WhatsApp</p></div></a>
					<a href="sms:?body=https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $row['card_id']; ?>" target="_blank"><div class="shar_btns"><i class="fas fa-comment-dots" ></i><p>SMS</p></div></a>
					
					<a href="https://www.facebook.com/sharer/sharer.php?u=https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $row['card_id']; ?>" target="_blank"><div class="shar_btns"><i class="fa fa-facebook" ></i><p>Facebook</p></div></a>
					<a href="https://twitter.com/intent/tweet?text=https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $row['card_id']; ?>" target="_blank"><div class="shar_btns"><i class="fa fa-twitter"></i><p>Twitter</p></div></a>
					<a href="" target="_blank"><div class="shar_btns"><i class="fa fa-instagram"></i><p>Instagram</p></div></a>
					<a href="https://www.linkedin.com/cws/share?url=https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $row['card_id']; ?>" target="_blank"><div class="shar_btns"><i class="fa fa-linkedin"></i><p>Linkedin</p></div></a>
				</div>
			
				<script>
					$(document).ready(function(){
						$('#close_sharer,#share_box_pop').on('click',function(){
							$('.share_box').slideToggle();
						});
					})
				
				
				</script>
			
			</div>
			<div class="dis_flex">
			
				<?php if(!empty($row['d_fb'])){echo '<a href="'.$row['d_fb'].'" target="_blank"><div class="social_med" ><i class="fa fa-facebook"></i></div></a>';} ?>
				<?php if(!empty($row['d_youtube'])){echo '<a href="'.$row['d_youtube'].'" target="_blank"><div class="social_med"><i class="fa fa-youtube"></i></div></a>';} ?>
				<?php if(!empty($row['d_twitter'])){echo '<a href="'.$row['d_twitter'].'" target="_blank"><div class="social_med"><i class="fa fa-twitter"></i></div></a>';} ?>
				<?php if(!empty($row['d_instagram'])){echo '<a href="'.$row['d_instagram'].'" target="_blank"><div class="social_med"><i class="fa fa-instagram"></i></div></a>';} ?>
				<?php if(!empty($row['d_linkedin'])){echo '<a href="'.$row['d_linkedin'].'" target="_blank"><div class="social_med"><i class="fa fa-linkedin"></i></div></a>';} ?>
				<?php if(!empty($row['d_pinterest'])){echo '<a href="'.$row['d_pinterest'].'" target="_blank"><div class="social_med"><i class="fa fa-pinterest"></i></div></a>';} ?>
			</div>
			
			
			
	
	</div>
	
	<div class="card2">
	
	<h3>Scan QR Code to download the contact details</h3>
	<img src="https://chart.googleapis.com/chart?cht=qr&chs=150x150&chl=https://<?php echo $_SERVER['HTTP_HOST'];?>/<?php echo $row['card_id']; ?>" id="qr_code_d">
	
	</div>
	
	
<!--------------about us --------------------------->	
	
	<div class="card2" id="about_us">
		<h3>About Us</h3>
	<?php if(!empty($row['d_comp_est_date'])){echo '<p>'.$row['d_comp_est_date'].'</p>';} ?>
	<?php if(!empty($row['d_about_us'])){echo '<p>'.$row['d_about_us'].'</p>';} ?>
			
		
		<?php if(!empty($row['d_pdf_upload'])){
    	// echo '<pre>';
    	// print_r($row);
    	// exit();<a href="download.php?file=codexworld.png">Dowload File</a>
    	echo '<p> <a href="https://dvcard.nl/'.$row['d_pdf_upload'].'"> View More</p>';
    	echo '<p> <a href="download_pdf.php?file='.$row['d_pdf_upload'].'"> Download File</p>';
    
    	;} ?>
		
	</div>
	
<!------------shopping online-------------------------->


<?php 
if(isset($row['id'])){
			
	$query3=mysqli_query($connect,'SELECT * FROM products WHERE id="'.$row['id'].'" ');
	$row3=mysqli_fetch_array($query3);
		}

	if(!empty($row3["pro_name1"]) || !empty($row3["pro_name2"]) || !empty($row3["pro_name3"]) || !empty($row3["pro_name4"]) || !empty($row3["pro_name5"])|| !empty($row3["pro_name6"])|| !empty($row3["pro_name7"])|| !empty($row3["pro_name8"])|| !empty($row3["pro_name9"])|| !empty($row3["pro_name10"])){ ?>
	<div class="card2" id="shop_online">
		<h3>Shop Online </h3><h3>From Our Store</h3>
		
		
		<?php 
		for($x=0;$x<=20;$x++){
			if(!empty($row3["pro_name$x"])){
				
				echo '<div class="order_box">';
				
				echo '<img src="data:image/*;base64,'.base64_encode($row3["pro_img$x"]).'" alt="Product">';
				echo '<h2>'.$row3["pro_name$x"].'</h2>';
				echo '<p><del>'.$row3["pro_mrp$x"].' <i class="fa fa-rupee"></i></del></p>';
				echo '<h4>'.$row3["pro_price$x"].' <i class="fa fa-rupee"></i></h4>';
				echo "<a href='https://$_SERVER[HTTP_HOST]/order_online/checkout.php?pro_id=$x&card_id=$row[id]' target='_blank'><div class='btn_buy'>Buy Now</div></a>";
				
				//echo "<a href='https://api.whatsapp.com/send?phone=91".str_replace("+91","",$row['d_whatsapp'])."&text=I am interested in Product: ".$row3["pro_name$x"].", Price: ".$row3["pro_mrp$x"]."' target='_blank'><div class='btn_buy'>Buy Now</div></a>";
				
				echo '</div>';
			} 
		}
			
		?>
		
		
	</div>
<?php } ?>
	
		
	
	
<!--------------youtube videos--------------------------->	

<?php 	if(!empty($row["d_youtube1"]) || !empty($row["d_youtube2"]) || !empty($row["d_youtube3"]) || !empty($row["d_youtube4"]) || !empty($row["d_youtube5"])){ ?>
	<div class="card2" id="youtube_video">
		<h3>Youtube Videos</h3>
		
		
		<?php 
		for($x=0;$x<=10;$x++){
			if(!empty($row["d_youtube$x"])){
				
					
				$array1=array('youtu.be/','watch?v=','&feature=youtu.be');
				$array2=array('www.youtube.com/embed/','embed/','');
				
				$youtubelink=str_replace($array1,$array2,$row["d_youtube$x"]);
			
				echo '<iframe src="'.$youtubelink.'" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
			} 
		}
			
		?>
		
		
	</div>
<?php } ?>
	
	
		
<!----------product and services ----------------------->		
<?php 
if(isset($row['id'])){
			
	$query2=mysqli_query($connect,'SELECT * FROM digi_card2 WHERE id="'.$row['id'].'" ');
	$row2=mysqli_fetch_array($query2);
		}

	if(!empty($row2["d_pro_img1"]) || !empty($row2["d_pro_img2"]) || !empty($row2["d_pro_img3"]) || !empty($row2["d_pro_img4"]) || !empty($row2["d_pro_img5"]) || !empty($row2["d_pro_img6"])|| !empty($row2["d_pro_img7"])|| !empty($row2["d_pro_img8"])|| !empty($row2["d_pro_img9"])|| !empty($row2["d_pro_img10"])) { ?>
	
	<div class="card2" id="product_services">
		<h3>Products & Services</h3>
		
		
		<?php 
		
		
		for($x=0;$x<=10;$x++){
			if(!empty($row2["d_pro_img$x"])){
			echo '<div class="product_s"><p>'.$row2["d_pro_name$x"].'</p>';
			echo '<img src="data:image/*;base64,'.base64_encode($row2["d_pro_img$x"]).'" alt="Logo">';
			echo '</div>';
			} 
		}
			
		?>
		
	
	</div>
	
<?php } ?>


		
<!----------image gallery----------------------->		
<?php 
if(isset($row['id'])){
	$query3=mysqli_query($connect,'SELECT * FROM digi_card3 WHERE id="'.$row['id'].'" ');
	$row3=mysqli_fetch_array($query3);
		}
	if(!empty($row3["d_gall_img1"]) || !empty($row["d_gall_img2"]) || !empty($row["d_gall_img3"]) || !empty($row["d_gall_img4"]) || !empty($row["d_gall_img5"]) || !empty($row["d_gall_img6"])|| !empty($row["d_gall_img7"])|| !empty($row["d_gall_img8"])|| !empty($row["d_gall_img9"])|| !empty($row["d_gall_img10"])) { ?>


		<div class="card2" id="gallery">
		<h3>Image Gallery</h3>
		
		
		<?php 
		
		
		for($x=0;$x<=10;$x++){
			if(!empty($row3["d_gall_img$x"])){
			echo '<div class="img_gall">';
			echo '<img src="data:image/*;base64,'.base64_encode($row3["d_gall_img$x"]).'" alt="Gallery Image">';
			echo '</div>';
			} 
		}
			
		?>

		
	</div>

<?php } ?>


		
<!----------payment info----------------------->	
<?php 	if(!empty($row["d_paytm"]) || !empty($row["d_account_no"]) ||!empty($row["d_qr_paytm"]) ||!empty($row["d_qr_phone_pay"]) ||!empty($row["d_qr_google_pay"]) || !empty($row["d_google_pay"]) || !empty($row["d_phone_pay"]) ){ ?>

	<div class="card2" id="payment">
		<h3>Payment Info</h3>
		
		
		<?php 	if(!empty($row["d_paytm"])){echo '<h2>Paytm</h2><p>'.$row['d_paytm'].'</p>';}	?>
		<?php 	if(!empty($row["d_google_pay"])){echo '<h2>Google Pay</h2><p>'.$row['d_google_pay'].'</p>';}?>
		<?php 	if(!empty($row["d_phone_pay"])){echo '<h2>PhonePe</h2><p>'.$row['d_phone_pay'].'</p>';}	?>
		
		<h3>Bank Account Details</h3>
		
		<?php 	if(!empty($row["d_ac_name"])){echo '<h2>Name:</h2><p>'.$row['d_ac_name'].'</p>';}	?>
		<?php 	if(!empty($row["d_account_no"])){echo '<h2>Account Number:</h2><p>'.$row['d_account_no'].'</p>';}?>
		<?php 	if(!empty($row["d_ifsc"])){echo '<h2>IFSC Code:</h2><p>'.$row['d_ifsc'].'</p>';	}?>
		<?php 	if(!empty($row["d_ac_type"])){echo '<h2>Account Type:</h2><p>'.$row['d_ac_type'].'</p>';	}?>
		<?php 	if(!empty($row["d_bank_name"])){echo '<h2>BANK Name:</h2><p>'.$row['d_bank_name'].'</p>';}	?>
		
		
		<?php if(!empty($row["d_qr_paytm"])){echo '<img src="data:image/*;base64,'.base64_encode($row["d_qr_paytm"]).'" alt="Paytm QR">';	}	?>
		<?php if(!empty($row["d_qr_google_pay"])){echo '<img src="data:image/*;base64,'.base64_encode($row["d_qr_google_pay"]).'" alt="Google Pay QR">';	}	?>
		<?php if(!empty($row["d_qr_phone_pay"])){echo '<img src="data:image/*;base64,'.base64_encode($row["d_qr_phone_pay"]).'" alt="PhonePe QR">';	}	?>
		
		
		
	</div>
	<?php } ?>	
	
	
<!----------email to  info----------------------->	
	<div class="card2" id="enquery">
		
		<form action="#" method="post">
			<h3>Contact Us</h3>
			
			<input type="" name="c_name" placeholder="Enter Your Name" required>
			<input type="" name="c_contact" maxlength="13"  placeholder="Enter Your Mobile No" required>
			<input type="email" name="c_email"  placeholder="Enter Your Email Address">
			<textarea name="c_msg" placeholder="Enter your Message or Query" required></textarea>
			<input type="submit" Value="Send!" name="email_to_client">
		
		</form>
		
<?php
if(isset($_POST['email_to_client'])){
        	$to = $row['user_email'];
        $subject = "Customer from DIGI card Online ".$_POST['c_name'];
        
        $message ='
        Name:'.$_POST['c_name'].'
        Contact Number: '.$_POST['c_contact'].'
        Message:'.$_POST['c_msg'];
        
        $headers .= 'From: <'.$_POST['c_email'].'>' . "\r\n";
        $headers .= 'Cc: <'.$_POST['c_email'].'>' . "\r\n";
        
        if(mail($to,$subject,$message,$headers)){
        	echo '<div class="alert success">Thanks! We have received your email.<br> We will get back to you with in 24hrs.</div>';
        }else {
        	echo '<div class="alert danger">Error Email! try again</div>';
        }
}






?>	<br>
		<br>
		<br>
		
		<a href="index.php"><div class="create_card_btn"><?php echo $_SERVER['HTTP_HOST'];?> || Create Your Card Now || <?php echo date('Y');?></div></a>
	<style>
	.create_card_btn {
		         background: linear-gradient(45deg, black, black);
    color: white;
    width: auto;
    padding: 20px;
    border-radius: 2px;
    line-height: 0.8;
    margin: 11px auto;
    font-size: 9px;
    text-align: center;
	}
	
	
	
#svg_down{position: fixed;
    bottom: 0;
    z-index: -1;
    left: 0;}

	
	</style>
	
	
	
	<br>
	<br>
	<br>
	<br>
	<div class="menu_bottom">
		<div class="menu_container">
			<div class="menu_item" onclick="location.href='#home'"><i class="fa fa-home"></i> Home</div>
			<div class="menu_item" onclick="location.href='#about_us'"><i class="fa fa-briefcase"></i>About Us</div>
			<div class="menu_item" onclick="location.href='#product_services'"><i class="fa fa-ticket"></i>Product & Services</div>
			<div class="menu_item" onclick="location.href='#shop_online'"><i class="fa fa-archive"></i>Shop</div>
			<div class="menu_item" onclick="location.href='#gallery'"><i class="fa fa-image"></i>Gallery</div>
			<div class="menu_item" onclick="location.href='#youtube_video'"><i class="fa fa-video-camera"></i>Youtube Videos</div>
			<div class="menu_item" onclick="location.href='#payment'"><i class="fa fa-money"></i>Payment</div>
			<div class="menu_item" onclick="location.href='#enquery'"><i class="fa fa-comment"></i>Enquiry</div>
		</div>
	</div>





