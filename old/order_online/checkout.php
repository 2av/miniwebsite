<?php

require('connect.php');
?>





<?php 
if(isset($_GET['card_id']) && isset($_GET['pro_id'])){
			
	$query3=mysqli_query($connect,'SELECT * FROM products WHERE id="'.$_GET['card_id'].'" ');
	$row3=mysqli_fetch_array($query3);
	
	$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE id="'.$_GET['card_id'].'" ');

    
$row=mysqli_fetch_array($query);
	}
	
	$x=$_GET['pro_id'];
	if(mysqli_num_rows($query3) >> 0){
		

	if(!empty($row3["pro_name$x"]) || !empty($row3["pro_mrp$x"]) || !empty($row3["pro_price$x"]) ){ ?>
	<div class="card2" id="checkout">
		<h3>Checkout</h3><br>
		
		
		<?php 
	
			if(!empty($row3["pro_name$x"])){
				
				echo '<div class="checkout_preview">';
				
				echo '<img src="data:image/*;base64,'.base64_encode($row3["pro_img$x"]).'" alt="Product">';
				echo '<h2>'.$row3["pro_name$x"].'</h2>';
				
				echo '<h4>Total Payable Amount: </h4><h4>'.$row3["pro_price$x"].' <i class="fa fa-rupee"></i></h4>';
			
				echo '</div>';
				
				
			echo '<form action="function.php">';
				echo '<div class="checkout_preview">';
				echo '<h3>Payment Type</h3>';
				echo '<select name="payment_type"><option value="" required>-:Select:-</option><option>COD</option><option>Online</option></select>';
				echo '</div>';
				
				echo '<div class="checkout_preview">';?>
						<!----------payment info----------------------->	
<?php 	if(!empty($row["d_paytm"]) || !empty($row["d_account_no"]) ||!empty($row["d_qr_paytm"]) ||!empty($row["d_qr_phone_pay"]) ||!empty($row["d_qr_google_pay"]) || !empty($row["d_google_pay"]) || !empty($row["d_phone_pay"]) ){ ?>

	
		
		
		<?php 	if(!empty($row["d_paytm"])){echo '<h2>Paytm</h2><p>'.$row['d_paytm'].'</p>';}	?>
		<?php 	if(!empty($row["d_google_pay"])){echo '<h2>Google Pay</h2><p>'.$row['d_google_pay'].'</p>';}?>
		<?php 	if(!empty($row["d_phone_pay"])){echo '<h2>PhonePe</h2><p>'.$row['d_phone_pay'].'</p>';}	?>
		
	
		<?php 	if(!empty($row["d_ac_name"])){echo '<h2>Name:</h2><p>'.$row['d_ac_name'].'</p>';}	?>
		<?php 	if(!empty($row["d_account_no"])){echo '<h2>Account Number:</h2><p>'.$row['d_account_no'].'</p>';}?>
		<?php 	if(!empty($row["d_ifsc"])){echo '<h2>IFSC Code:</h2><p>'.$row['d_ifsc'].'</p>';	}?>
		<?php 	if(!empty($row["d_ac_type"])){echo '<h2>Account Type:</h2><p>'.$row['d_ac_type'].'</p>';	}?>
		<?php 	if(!empty($row["d_bank_name"])){echo '<h2>BANK Name:</h2><p>'.$row['d_bank_name'].'</p>';}	?>
		
		
		<?php if(!empty($row["d_qr_paytm"])){echo '<img src="data:image/*;base64,'.base64_encode($row["d_qr_paytm"]).'" alt="Paytm QR">';	}	?>
		<?php if(!empty($row["d_qr_google_pay"])){echo '<img src="data:image/*;base64,'.base64_encode($row["d_qr_google_pay"]).'" alt="Google Pay QR">';	}	?>
		<?php if(!empty($row["d_qr_phone_pay"])){echo '<img src="data:image/*;base64,'.base64_encode($row["d_qr_phone_pay"]).'" alt="PhonePe QR">';	}	?>
		
		
		

	<?php } ?>	
	
	
<!----------email to  info----------------------->	<?php
				
				
				echo '</div>';
				
				
				echo '<div class="checkout_preview">';
				
					
					
					echo '<h3>Your Address Details</h3>';
					echo '<input type="" name="pro_id" value="'.$_GET['pro_id'].'" hidden required>';
					echo '<input type="" name="card_id"  value="'.$_GET['card_id'].'" hidden required>';
					echo '<input type="" name="name" placeholder="Enter your name " required>';
					echo '<input type="" name="contact" placeholder="Enter your Contact number" required>';
					echo '<input type="" name="email" placeholder="Enter your Email id" required>';
					echo '<textarea type="" name="address" placeholder="Street Address..." required></textarea>';
					echo '<input type="" name="city" placeholder="City " required>';
					echo '<select name="state" placeholder="State" required>';
					include('states.php');
					echo '</select>';
					echo '<input type="number" name="pincode" placeholder="Pin code/Postal code" max="999999" min="111111" required>';
					echo '<input type="submit" name="submit_form" value="Place Order">';
					
				
				echo '</div>';
			echo '</form>';		
				
			} 
		
			
		?>
		
		
	</div>
<?php } 

	}else {echo '<div class="alert danger">Product not available</div>';}
?>
	
		
	
	
	
	
	
	
	