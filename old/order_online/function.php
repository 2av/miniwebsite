<?php 
require('connect.php');
if(isset($_GET['card_id']) && isset($_GET['pro_id'])){
			
	$query3=mysqli_query($connect,'SELECT * FROM products WHERE id="'.$_GET['card_id'].'" ');
	$row3=mysqli_fetch_array($query3);
		// data from digicard database
		
			$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE  id="'.$_GET['card_id'].'" ');

			$row=mysqli_fetch_array($query);
				
	
	$x=$_GET['pro_id'];
	
	$pro_price=$row3["pro_price$x"];
	if(mysqli_num_rows($query3) >> 0){
		
		$query4=mysqli_query($connect,'SELECT * FROM orders WHERE card_id="'.$_GET['card_id'].'" AND c_contact="'.$_GET['contact'].'" AND order_status="Order Placed" AND
		pro_id="'.$_GET['pro_id'].'"');
		
		if(mysqli_num_rows($query4) >> 0){
				echo '<div class="order_confirmation">
				<i class="fa fa-check-circle"></i>
				<p>Thank You! Your Order has been placed.
					for any help contact us On '.$row['d_contact'].' or '.$row['user_email'].'
				</p>
				<a href="../index.php"><div class="back_btn">Continue</div></a>
			</div>
			';
		}else {
			
			if($_GET['payment_type']=='COD'){
				
			$insert=mysqli_query($connect,'INSERT INTO orders (card_id,pro_id,c_address,c_email,c_contact,c_name,c_city,c_state,c_pincode,payment_type,payment_amount,order_status,uploaded_date,user_email) VALUES	("'.$_GET['card_id'].'","'.$_GET['pro_id'].'","'.$_GET['address'].'","'.$_GET['email'].'","'.$_GET['contact'].'","'.$_GET['name'].'","'.$_GET['city'].'","'.$_GET['state'].'","'.$_GET['pincode'].'","'.$_GET['payment_type'].'","'.$pro_price.'","Order Placed","'.$date.'","'.$row['user_email'].'")' );
			
			
			
			
			}else {
				
				
			$insert=mysqli_query($connect,'INSERT INTO orders (card_id,pro_id,c_address,c_email,c_contact,c_name,c_city,c_state,c_pincode,payment_type,payment_amount,order_status,uploaded_date,user_email) VALUES	("'.$_GET['card_id'].'","'.$_GET['pro_id'].'","'.$_GET['address'].'","'.$_GET['email'].'","'.$_GET['contact'].'","'.$_GET['name'].'","'.$_GET['city'].'","'.$_GET['state'].'","'.$_GET['pincode'].'","'.$_GET['payment_type'].'","'.$pro_price.'","Order Placed","'.$date.'","'.$row['user_email'].'")' );
			}
			
			
			
			echo '<div class="order_confirmation">
				<i class="fa fa-check-circle"></i>
				<p>Thank You! Your Order has been placed.
					for any help contact us On '.$row['d_contact'].' or '.$row['user_email'].'
				</p>
				<a href="../index.php"><div class="back_btn">Continue</div></a>
			</div>
			';}
		
		
	}
	
	
}
	
	
	?>
	
	