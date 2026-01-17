<?php

require('connect.php');
require('header.php');

?>

<!-----------------php 1st script----------------------->
<?php
$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE user_email="'.$_SESSION['user_email'].'" ');
$row22=mysqli_fetch_array($query);
		if(mysqli_num_rows($query)>>0){
		echo '<div class="main2">';

		$query_orders=mysqli_query($connect,'SELECT * FROM orders WHERE user_email="'.$_SESSION['user_email'].'" AND order_status="Order Placed"');


		if($row22['d_payment_status']=='Success'){
			echo '<a href="all_orders.php"><div class="order_box">';
			echo '<div class="order_alert">'.mysqli_num_rows($query_orders).'</div>';

			echo '<i class="fa fa-archive"></i><br> Manage Orders</div></a>';

		}else if($row22['d_card_status']=='Inactive'){
			echo '<div class="alert danger">Your Card is Deactivated/Cancelled. Pay now to Activate.</div>';
		}else if($row22['d_payment_status']=='Created'){
			echo '<a href="all_orders.php"><div class="order_box">';
			echo '<div class="order_alert">'.mysqli_num_rows($query_orders).'</div>';

			echo '<i class="fa fa-archive"></i> <br>Manage Orders</div></a>';
			// Hide trial card message when f_user_email is not blank
			if(empty($row22['f_user_email'])) {
				echo '<div class="alert info">Trial Cards are only available for 7 days. Please make payment to avoid Cancellation or Deactivation of your card</div>';
			}
		}
		else {}


echo '</div>';
		}else {echo '	<div class="main2">
	<a href="create_card.php"><div class="btn_create">+ Create New Card</div></a>

</div>'
?>

<script>
$(document).ready(function(){
	$('.close').on('click',function(){
		$('.pop_up_offer').slideToggle();
	})
})

</script>

<div class="pop_up_offer">
<div class="close">&times;</div>
<img src="images/SpecialOffer.png">
<h1>Hi <?php echo $_SESSION['user_name']; ?></h1>
<h3>You have unlocked 7 days Free Trial</h3><h2> you need to pay <br><del>1999 Rs</del><br> <strong>999 Rs</strong> for subscription for 1 year.</h2>

<a href="create_card.php"><div class="btn_create">Create Your Card</div></a>

</div>
<?php
;}

		?>


<!-----------------ending php 1st script----------------------->

<div class="container">
	<div class="card_row">

		<div class="row_contd">Card ID</div>
		<div class="row_contd">Company Name</div>
		<div class="row_contd">Payment Status</div>
		<div class="row_contd">Card Status</div>
		<div class="row_contd">Data</div>
		<div class="row_contd">Share</div>
		<div class="row_contd">Edit</div>
		<div class="row_contd"></div>
		<div class="row_contd"></div>


	</div>

	<?php
	$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE user_email="'.$_SESSION['user_email'].'"  ORDER BY id DESC LIMIT 10');

		if(mysqli_num_rows($query)>>0){
			while($row=mysqli_fetch_array($query)){
			echo '<li class="card_row2">';
			echo '<div class="row_contd"><a href="https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id'].'" target="_blank">'.$row['id'].'</div>';
			echo '<div class="row_contd">'.$row['d_comp_name'].' <i class="fa fa-external-link"></i></div></a>';
			echo '<div class="row_contd" id="'.$row['d_payment_status'].'">';
			
			// Change payment status to "Created" when f_user_email is not blank
			if(!empty($row['f_user_email'])) {
				echo 'Created';
			} else if($row['d_payment_status']=='Created') {
				echo 'Pending';
			} else {
				echo $row['d_payment_status'];
			}
			
			echo '</div>';
				echo '<div class="row_contd" id="'.$row['d_card_status'].'">';
				if($row['d_card_status']=='Active'){echo 'Active';}else if($row['d_card_status']=='Inactive'){echo 'Inactive';}
				echo '</div>';
			echo '<div class="row_contd">'.date("d-M-Y",strtotime($row['uploaded_date'])).'</div>';
			echo '<div class="row_contd"><a href="https://api.whatsapp.com/send?text=https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id'].'" target="_blank"><i class="fa fa-whatsapp"></i></a><a href="https://www.facebook.com/sharer/sharer.php?u=https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id'].'" target="_blank"><i class="fa fa-facebook"></i></a></div>';
			echo '<div class="row_contd"><a href="create_card.php?card_number='.$row['id'].'"><i class="fa fa-edit"></i></a></div>';
			
			// Disable Pay Now and Download Invoice buttons when f_user_email is not blank
			if(!empty($row['f_user_email'])) {
				echo '<div class="row_contd pay_now_btn" style="cursor:not-allowed; background-color:gray">Pay Now</div>';
//				echo '<div class="row_contd pay_now_btn" style="cursor:not-allowed; background-color:gray">Download Invoice</div>';
			} else if($row['d_payment_status']=='Success'){
				echo '<div class="row_contd pay_now_btn" style="cursor:not-allowed; background-color:gray">Paid</div>';
//				echo '<a href="download_invoice.php?id='.$row['id'].'" target="_blank"><div class="row_contd pay_now_btn">Download Invoice</div></a>';
			} else {
				echo '<a href="payment_page/pay.php?id='.$row['id'].'" target="_blank"><div class="row_contd pay_now_btn">Pay Now</div></a>';
//				echo '<a href="#"><div class="row_contd pay_now_btn" style="cursor:not-allowed; background-color:gray">Download Invoice</div></a>';	
			}
			
			echo '</li>';
			}
		}else {
			echo '<div class="alert info">No Data Available...</div>';
		}


	?>



</div>


<footer class="">

<p>Copyright 2025 || <?php echo $_SERVER['HTTP_HOST']; ?></p>

</footer>
