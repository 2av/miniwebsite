
<?php

require('../connect.php');

?>



<?php
$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_GET['card_number'].'" ');

if(mysqli_num_rows($query)==0){
	
	echo '<meta http-equiv="refresh" content="2000;URL=../index.php">';
}else {
	$row=mysqli_fetch_array($query);
}


if($row['d_card_status']=="Inactive"){
	echo '<div class="full_page_alert">This card does not exists or Deactivated. Contact Admin</div>';
	echo '<meta http-equiv="refresh" content="20;URL=../index.php">';
}else {}


?>
<link rel="stylesheet" href="<?php if(!empty($row['d_css'])){echo $row['d_css'];}else {echo 'card_css1.css';} ?>" >

<script>

$(document).ready(function(){
	$('.mobile_home').on('click',function(){
		$('#header').toggleClass('add_height');
		
	})
})

</script>

<style>
.full_page_alert {position: fixed;
    width: -webkit-fill-available;
    height: -webkit-fill-available;
    background: white;
    top: 0;
    z-index: 9999999;
    padding: 63px;
    text-align: center;}

</style>
<!----------------------copy from here ------------------------->


	<div class="card" id="home">
			
			<div class="card_content"><img src="<?php if(!empty($row['d_logo'])){echo 'data:image/*;base64,'.base64_encode($row['d_logo']);} ?>" alt="Logo"></div>
			<div class="card_content2">
				<h2><?php if(!empty($row['d_comp_name'])){echo $row['d_comp_name'];} ?></h2>
				<p><?php if(!empty($row['d_f_name'])){echo $row['d_f_name'].' '.$row['d_l_name'];} ?></p>
			</div>
			<div class="dis_flex">
				<?php if(!empty($row['d_contact'])){echo '<a href="tel:+91'.$row['d_contact'].'" target="_blank"><div class="link_btn"><i class="fa fa-phone"></i> Call</div></a>';} ?>
				<?php if(!empty($row['d_whatsapp'])){echo '<a href="https://api.whatsapp.com/send?text=www.meradigicard.com" target="_blank"><div class="link_btn"><i class="fa fa-whatsapp"></i> WhatsApp</div></a>';} ?>
				<?php if(!empty($row['d_location'])){echo '<a href="'.$row['d_location'].'" target="_blank"><div class="link_btn"><i class="fa fa-map-marker"></i> Direction</div></a>';} ?>
				<?php if(!empty($row['d_email'])){echo '<a href="Mailto:'.$row['d_email'].'" target="_blank"><div class="link_btn"><i class="fa fa-envelope"></i> Mail</div></a>';} ?>
				<?php if(!empty($row['d_website'])){echo '<a href="https://'.$row['d_website'].'" target="_blank"><div class="link_btn"><i class="fa fa-globe"></i> Website</div></a>';} ?>
			
			</div>
	
			<div class="contact_details">
				<?php if(!empty($row['d_contact'])){echo '<div class="contact_d"><i class="fa fa-phone"></i><p>'.$row['d_contact'].'</p></div>';} ?>
				<?php if(!empty($row['d_contact2'])){echo '<div class="contact_d"><i class="fa fa-phone"></i><p>'.$row['d_contact2'].'</p></div>';} ?>
				<?php if(!empty($row['d_email'])){echo '<div class="contact_d"><i class="fa fa-envelope"></i><p>'.$row['d_email'].'</p></div>';} ?>
				<?php if(!empty($row['d_location'])){echo '<div class="contact_d"><i class="fa fa-map-marker" style="width:28px;"></i><p>'.$row['d_address'].'</p></div>';} ?>
				
			</div>
			
			<div class="dis_flex">
				<div class="share_wtsp">
					<form action="https://api.whatsapp.com/send" target="_blank"><input type="text"  name="phone" placeholder="WhatsApp Number with Country code	" value="+91"><input type="hidden" name="text" value="https://<?php echo $_SERVER['HTTP_HOST']; ?>/cards/preview_page.php?card_number=<?php echo $row['id']; ?>"><input type="submit" value="Share On WhatsApp"></form>
					
				</div>
			</div>
			
			<div class="dis_flex">
			
			<?php if(!empty($row['d_contact'])){echo '<a href="../contact_download.php?id='.$row['id'].'"><div class="big_btns">Save to Contacts <i class="fa fa-download"></i></div></a>';} ?>
				
				<div class="big_btns">Share <i class="fa fa-share-alt"></i></div>
			
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
	
	
	</div>
	
	<div class="card2" id="about_us">
		<h3>About Us</h3>
	<?php if(!empty($row['d_comp_est_date'])){echo '<p>'.$row['d_comp_est_date'].'</p>';} ?>
	<?php if(!empty($row['d_about_us'])){echo '<p>'.$row['d_about_us'].'</p>';} ?>
			
		
	
	</div>
	<div class="card2" id="youtube_video">
		<h3>Youtube Videos</h3>
		
		
		<?php 
		for($x=0;$x<=10;$x++){
			if(!empty($row["d_youtube$x"])){$youtubelink=str_replace('watch?v=','embed/',$row["d_youtube$x"]);
			
				echo '<iframe src="'.$youtubelink.'" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
			} 
		}
			
		?>
		
		
		
		
		
	</div>
	
	
	<div class="card2" id="product_services">
		<h3>Products & Services</h3>
		
		
		<?php 
		
			
	$query2=mysqli_query($connect,'SELECT * FROM digi_card2 WHERE id="'.$row['id'].'" ');
	$row2=mysqli_fetch_array($query2);
		for($x=0;$x<=10;$x++){
			if(!empty($row2["d_pro_img$x"])){
			echo '<div class="product_s"><p>'.$row2["d_pro_name$x"].'</p>';
			echo '<img src="data:image/*;base64,'.base64_encode($row2["d_pro_img$x"]).'" alt="Logo">';
			echo '</div>';
			} 
		}
			
		?>
		
		
		
		
		
	</div>
		<div class="card2" id="gallery">
		<h3>Image Gallery</h3>
		
		
		<?php 
		
		
	$query3=mysqli_query($connect,'SELECT * FROM digi_card3 WHERE id="'.$row['id'].'" ');
	$row3=mysqli_fetch_array($query3);
		for($x=0;$x<=10;$x++){
			if(!empty($row3["d_gall_img$x"])){
			echo '<div class="img_gall">';
			echo '<img src="data:image/*;base64,'.base64_encode($row3["d_gall_img$x"]).'" alt="Gallery Image">';
			echo '</div>';
			} 
		}
			
		?>
		
		
		
		
		
	</div>
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
		
		
		
	</div>
		
	
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
		
		<a href="../index.php"><div class="create_card_btn">Create Your Own Business Card<br><br> Free!</div></a>
	<style>
	.create_card_btn {
		 background: #167ac6;
    color: white;
    font-weight: 900;
    width: fit-content;
    padding: 18px;
    border-radius: 6px;
    line-height: 0.8;
    margin: 11px auto;
    text-align: center;
    animation: create_card_btn 1s linear infinite;
	}
	@keyframes create_card_btn {
		0%{transform:scale(1)}
		50%{transform:scale(1.2)}
		100%{transform:scale(1)}
	}
	
	
	
#svg_down{position: fixed;
    bottom: 0;
    z-index: -1;
    left: 0;}

	
	</style>
	</div>
	
	
	
	<br>
	<br>
	<br>
	<br>
	<div class="menu_bottom">
		<div class="menu_container">
			<div class="menu_item" onclick="location.href='#home'"><i class="fa fa-home"></i> Home</div>
			<div class="menu_item" onclick="location.href='#about_us'"><i class="fa fa-briefcase"></i>About Us</div>
			<div class="menu_item" onclick="location.href='#product_services'"><i class="fas fa-box-open"></i>Product & Services</div>
			<div class="menu_item" onclick="location.href='#gallery'"><i class="fa fa-file-image"></i>Gallery</div>
			<div class="menu_item" onclick="location.href='#youtube_video'"><i class="fas fa-video"></i>Youtube Videos</div>
			<div class="menu_item" onclick="location.href='#payment'"><i class="fas fa-money"></i>Payment</div>
			<div class="menu_item" onclick="location.href='#enquery'"><i class="fa fa-comment"></i>Enquery</div>
		</div>
	</div>



<svg id="svg_down" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
  <path fill="#0086c5" fill-opacity="1" d="M0,128L18.5,122.7C36.9,117,74,107,111,122.7C147.7,139,185,181,222,208C258.5,235,295,245,332,224C369.2,203,406,149,443,138.7C480,128,517,160,554,170.7C590.8,181,628,171,665,160C701.5,149,738,139,775,138.7C812.3,139,849,149,886,176C923.1,203,960,245,997,218.7C1033.8,192,1071,96,1108,69.3C1144.6,43,1182,85,1218,138.7C1255.4,192,1292,256,1329,266.7C1366.2,277,1403,235,1422,213.3L1440,192L1440,320L1421.5,320C1403.1,320,1366,320,1329,320C1292.3,320,1255,320,1218,320C1181.5,320,1145,320,1108,320C1070.8,320,1034,320,997,320C960,320,923,320,886,320C849.2,320,812,320,775,320C738.5,320,702,320,665,320C627.7,320,591,320,554,320C516.9,320,480,320,443,320C406.2,320,369,320,332,320C295.4,320,258,320,222,320C184.6,320,148,320,111,320C73.8,320,37,320,18,320L0,320Z"></path>
</svg>
