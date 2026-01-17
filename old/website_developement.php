<?php

require('connect.php');
?>


<header id="header">
	<div class="logo">
		<img src="images/logo.png"><h3>WEBSITE SERVICES</h3>
	</div>
	<div class="mobile_home">&equiv;</div>
	<div class="head_txt">
		<a href="index.php"><h3>Home</h3></a>
		<a href="panel/franchisee-login/login.php"><h3>Website Designs</h3></a>
		
		<a href="#contact"><h3>Contact</h3></a>
		<a href="#contact"><h3>Help</h3></a>
	</div>
	

</header>


<script>

$(document).ready(function(){
	$('.mobile_home').on('click',function(){
		$('#header').toggleClass('add_height');
		
	})
})

</script>

<div class="main">
	<div class="clip_path1"></div>
	<img src="images/young-positive-cool-lady-with-curly-hair-using-laptop-isolated_171337-6666.jpg">
	<div class="main_txt">
		<h1>CREATE YOUR OWN WEBSITE</h1>
		<h2>Create your own website </h2>
		
		<a href="https://api.whatsapp.com/send?phone=919725098250&text=Website Developement&source=&data=&app_absent=" target="_blank"><div class="btn_2">Contact Us</div></a>
	</div>
</div>
	
	
	<div class="row_contact">

	<h3>CALL US NOW TO CONNECT<br>
	FOR FRANCHISEE AND ANY ENQUIRY</h3>


	<h1><i class="fa fa-phone"></i> 9725098250</h1>
	<svg id="svg_fea" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
  <path fill="#0099ff" fill-opacity="1" d="M0,192L18.5,213.3C36.9,235,74,277,111,277.3C147.7,277,185,235,222,234.7C258.5,235,295,277,332,250.7C369.2,224,406,128,443,96C480,64,517,96,554,106.7C590.8,117,628,107,665,90.7C701.5,75,738,53,775,53.3C812.3,53,849,75,886,122.7C923.1,171,960,245,997,250.7C1033.8,256,1071,192,1108,138.7C1144.6,85,1182,43,1218,53.3C1255.4,64,1292,128,1329,160C1366.2,192,1403,192,1422,192L1440,192L1440,320L1421.5,320C1403.1,320,1366,320,1329,320C1292.3,320,1255,320,1218,320C1181.5,320,1145,320,1108,320C1070.8,320,1034,320,997,320C960,320,923,320,886,320C849.2,320,812,320,775,320C738.5,320,702,320,665,320C627.7,320,591,320,554,320C516.9,320,480,320,443,320C406.2,320,369,320,332,320C295.4,320,258,320,222,320C184.6,320,148,320,111,320C73.8,320,37,320,18,320L0,320Z"></path>
</svg>

</div>

<svg xmlns="http://www.w3.org/2000/svg" style="position: absolute;" viewBox="0 0 1440 320">
  <path fill="#0099ff" fill-opacity="1" d="M0,192L16,186.7C32,181,64,171,96,165.3C128,160,160,160,192,149.3C224,139,256,117,288,101.3C320,85,352,75,384,64C416,53,448,43,480,64C512,85,544,139,576,149.3C608,160,640,128,672,101.3C704,75,736,53,768,64C800,75,832,117,864,144C896,171,928,181,960,186.7C992,192,1024,192,1056,170.7C1088,149,1120,107,1152,90.7C1184,75,1216,85,1248,128C1280,171,1312,245,1344,245.3C1376,245,1408,171,1424,133.3L1440,96L1440,0L1424,0C1408,0,1376,0,1344,0C1312,0,1280,0,1248,0C1216,0,1184,0,1152,0C1120,0,1088,0,1056,0C1024,0,992,0,960,0C928,0,896,0,864,0C832,0,800,0,768,0C736,0,704,0,672,0C640,0,608,0,576,0C544,0,512,0,480,0C448,0,416,0,384,0C352,0,320,0,288,0C256,0,224,0,192,0C160,0,128,0,96,0C64,0,32,0,16,0L0,0Z"></path>
</svg>
	
	<div class="row2">

		<h1 >Affordable prices plus more</h1>
	<div class="flex_pricing">
		
		<div class="flex_pricingin">
			<h3>Website</h3>
			<h1>With Domain & Hosting</h1>
			
			<ul>
				<li class="back"><i class="fa fa-check"></i>Digital Card Making Website</li>
				<li ><i class="fa fa-check"></i> Personal Website </li>
				<li class="back"><i class="fa fa-check"></i> Business Website </li>
				<li><i class="fa fa-check"></i> Web Application </li>
				<li class="back"><i class="fa fa-check"></i> E-Commerce Website</li>
				
			</ul>
			<a href="#contact"><div class="btn_1">Contact Us</div></a>
		</div>
		
		
	</div>
</div>


</div>





<!-----------Contact--------------------->



<div class="row_bottom display_flex" id="contact">

	<div class="side1">
		
		
		<h1><img src="images/logo.png">AJOOBA DIGI CARD</h1>
		
		<h3>Contact Details</h3>

		<div class="row_bt_p"><i class="fa fa-map-marker"></i><h4>Address: Navrangpura, Ahmedabad,<br> Gujarat,380009</h4></div>
		
		<div class="row_bt_p"><i class="fa fa-phone"></i> <h4>9725098250</h4></div>
		<div class="row_bt_p"><i class="fa fa-envelope"></i><h4>hello@ajooba.io</h4></div>
		
	</div>
	<div class="side2">
	<h3>CONTACT US</h3>
		<form action="">
			<input type="" name="user_name" placeholder="Enter your name" required>
			<input type="" name="user_contact" placeholder="Enter contact number" required>
			<input type="" name="user_email" placeholder="Enter email" required>
			<textarea name="user_msg" placeholder="Enter your query" required></textarea>
			<input type="submit" value="Send" name="send_email">
		
		
		</form>
		
		
<?php
// query email function 
	
	
if(isset($_GET['send_email'])){
$to = "westandalone@gmail.com";
$subject = "Customer from Ajooba DIGI card Online ".$_GET['user_name'];

$message = '
<h1>'.$_GET['user_name'].' has sent a message</h1>
'.$_GET['user_contact'].' and '.$_GET['user_email'].'

<h2>Message is</h2>

'.$_GET['user_msg'].'

';

// Always set content-type when sending HTML email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
$headers .= 'From: <meradigicard@gmail.com>' . "\r\n";
//$headers .= 'Cc: <shankar.flipkart1@gmail.com>' . "\r\n";

if(mail($to,$subject,$message,$headers)){
	echo '<div class="success_alert">Thanks! We have received your email.<br> We will get back to you with in 24hrs.</div>';
}else {
	echo '<div class="danger_alert">Error Email! try again</div>';
}

}
	

?>
	</div>
	
</div>



<footer class="">

<p>Copyright 2025 || Website developed & Maintained by AJOOBA INFO SERVICES</p>

<script data-ad-client="ca-pub-2577996436540735" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>