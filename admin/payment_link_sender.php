<?php

require_once(__DIR__ . '/../app/config/database.php');
require('header.php');

?>


<div class="container2">
	<h1>Card Manager</h1>
	
<?php
	
	
			 
	$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_GET['id'].'" ');
	
	if(mysqli_num_rows($query) > 0){
		$row=mysqli_fetch_array($query);
			// Query user_details table for admin
			$query2=mysqli_query($connect,'SELECT * FROM user_details WHERE email="'.$_SESSION['admin_email'].'" AND role="ADMIN"');
	$row2=mysqli_fetch_array($query2);
	// Map user_details fields to old field names for compatibility
	if($row2) {
		$row2['admin_email'] = $row2['email'] ?? '';
		$row2['admin_google_pay'] = $row2['google_pay'] ?? '';
		$row2['admin_paytm'] = $row2['paytm'] ?? '';
		$row2['admin_rz_pay'] = $row2['rz_pay'] ?? '';
		$row2['admin_rz_pay2'] = $row2['rz_pay2'] ?? '';
	}
	}else {
		echo '<div class="alert danger">Card ID does not exists in your account!</div>';
		echo '<style> .my_account_form{display:none;}</style>';
	}
	
	
		
		

		
?>

<div  class="my_account_form">
		<h3> Send Payment Link </h3>
		<p>
		<div class="input_area"><p>User Email</p>
		<input type="readonly"  value="<?php echo $row['d_payment_date'];  ?>"  name="update_details" required>
		</div>
		<div class="input_area"><p>User Email</p>
		<input type="readonly"  value="<?php  if(isset($row['user_email'])){echo $row['user_email'];}else {echo $row['d_email'];}  ?>"  name="update_details" required>
		</div>
		<div class="input_area"><p>User Contact</p>
		<input type="readonly" id="mobileNumber"  value="<?php echo str_replace(array('+91'),array(''),$row['d_contact']);  ?>"  name="update_details" required>
		</div>
		<br>
		<div class="input_area"><p>Message</p>
		<textarea   id="message"  name="message" required>
Dear <?php echo $row['d_f_name'].' '.$row['d_l_name']; ?>
Payment Reminder for 

<?php echo ' ( https://www.'.$_SERVER['HTTP_HOST'].'/'.$row['card_id'].' ) '; ?>


 Pay activation fee to avoid deactivation of your card. 

<?php 

// for google pay and paytm in case if we change
/*if(!empty($row2['admin_google_pay']) ||!empty($row2['admin_paytm']) ){

if(!empty($row2['admin_google_pay'])){echo ' Google Pay:'.str_replace("+91","",$row2['admin_google_pay']); }

 if(!empty($row2['admin_paytm'])){echo '
  Paytm:'.str_replace("+91","",$row2['admin_paytm']); }
}else  {echo 'Please Go to My Account and update your Paytm/GooglePay number';} */
 ?>
 
 Click here to pay: <?php echo 'https://www.'.$_SERVER['HTTP_HOST'].'/panel/login/payment_page/pay.php?id='.$row['id']; ?>
 
 
 Thanks
 <?php echo $_SERVER['HTTP_HOST']; ?>
		
		</textarea>
		</div>
		
		<script>
			$(document).ready(function(){
				
				
				
				
				
				
				$('#message,#mobileNumber').on('input',function(){
					var message=$('#message').val();
						var mobileNumber=$('#mobileNumber').val();
		// send combine msg
		console.log(mobileNumber);
				})
				
				
		// send details to customer
				$('#send_payment_link').on('click',function(){
					
					var message=$('#message').val();
						var mobileNumber=$('#mobileNumber').val();
				
					var URL="https://web.whatsapp.com/send?phone=91"+mobileNumber+"&text="+message;
						
						
						window.open(URL);
						
					})
			})
			
		
		</script>
		
		<?php if(!empty($row2['admin_google_pay']) ||!empty($row2['admin_paytm']) ){echo '<div class="btn_payment" id="send_payment_link">Send Payment Link</div>';}else {echo '<a href="my_account.php"><div class="alert danger" >Opps! Please Update Details in My Account </div></a>';} ?>
		
		
		
		
	</div>



<?php

if(isset($_POST['send_payment_link']))	{
	
		$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_POST['id'].'" AND admin_email="'.$_SESSION['admin_email'].'" ');
	
							
			$update=mysqli_query($connect,'UPDATE digi_card SET 
			
			d_payment_amount="'.$_POST['d_payment_amount'].'"
			
			WHERE id="'.$_POST['id'].'" AND admin_email="'.$_SESSION['admin_email'].'"');
			
		// enter details in database ending
		
		if($update){
			echo '<a href=""><div class="alert info">Details Updated Wait...</div></a>';
		
			
		}else {
			echo '<a href=""><div class="alert danger">Error! Try Again.</div></a>';
		}
		
}else {
	
	
}
		

?>


</div>

<script>
// function for submitting this data
$(document).ready(function(){
	$('#update_details').on('click',function(e){
		$('.my_account_form').submit();
	});
})


	

</script>



</div>


	

 <footer class="footer-area">
 
     <center>
           <br />
                    <a href="index.html" class="footer-logo">
                        						<img src="../panel/images/f_logo.png" alt="Vcard" width="auto" height="50px">
						                    </a>
                    <p>&copy; Copyright 2025 - All Rights Reserved. Crafted With <?php echo $_SERVER['HTTP_HOST']; ?> for Someone Special ! </p> 
					<p><a target="_blank" href="https://support.ajooba.io">Support Forum</a> | <a target="_blank" href="https://support.ajooba.io/faq">Faq's</a> | <a target="_blank" href="https://support.ajooba.io/articles/category/digital-vcard">Knowlege Base</a> </p>
			
        </center></footer>


