<?php

require_once(__DIR__ . '/../app/config/database.php');
require('header.php');

?>


	<a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back </h3></a>


	<h1 class="title2">Search All Cards</h1>
	
	<form action="" class="search"><input type="search" name="search_card" placeholder="Search Company name, Contact, Email"><input type="submit" name="search" value="search"></form>

<div class="container">

	
	<?php
		
		if(isset($_GET['search_card'])){
			$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE d_comp_name LIKE "%'.$_GET['search_card'].'%" OR d_contact="'.$_GET['search_card'].'" OR user_email="'.$_GET['search_card'].'" ORDER BY id DESC LIMIT 50');
			
				if(mysqli_num_rows($query) > 0 ){
					echo '<div class="card_row">
	
		<p>User ID</p>
		<p>Franchisee ID</p>
		
		<p>ID</p>
		<p>Card ID</p>
		
		<p>Company Name</p>
		<p>Payment Status</p>
		<p>Card Status</p>
		<p>Data</p>
		<p>Payment Data</p>
		<p>Payment Amount</p>
		<p>Share</p>
		<p>Action</p>
		<p>Edit</p>
		<p>Payment</p>
		
		
	</div>';
				}else {
					echo '<div class="alert info">No result found.</div>';
				}
			
		while($row=mysqli_fetch_array($query)){
			echo '<li class="card_row2">';
			echo '<p>'.$row['user_email'].'</p>';
			echo '<p>'.$row['f_user_email'].'</p>';
			echo '<p><a href="https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id'].'" target="_blank">'.$row['id'].'</p>';
			echo '<p>'.$row['card_id'].'</p>';
			echo '<p>'.$row['d_comp_name'].' <i class="fa fa-external-link"></i></p></a>';
			echo '<p>'.$row['d_payment_status'].'</p>';
				echo '<p  >';
				if($row['d_payment_status']=='Created'){echo 'Trial Active';}else if($row['d_payment_status']=='Success'){echo 'Active';}else if($row['d_payment_status']=='Failed'){echo 'Inactive';}
				echo '</p>';
			echo '<p>'.$row['uploaded_date'].'</p>';
			echo '<p>'.$row['d_payment_date'].'</p>';
			echo '<p>'.$row['d_payment_amount'].'</p>';
			echo '<p><a href="https://api.whatsapp.com/send?text=https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id'].'" target="_blank"><i class="fa fa-whatsapp"></i></a><a href="https://www.facebook.com/sharer/sharer.php?u=https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id'].'" target="_blank"><i class="fa fa-facebook"></i></a></p>';

			// activate card 
			echo '<p id="active_btn" class="idact'.$row['id'].'" onclick="activateUser('.$row['id'].')"><span class=" '.$row['d_card_status'].'">';
			echo $row['d_card_status'];
			echo '</span></p>';

			echo '<p><a href="select_theme.php?card_number='.$row['id'].'&user_email='.$row['user_email'].'"><i class="fa fa-edit"></i></a></p>';
			echo '<p><a href="payment_link_sender.php?id='.$row['id'].'" target="_blank"><i class="fa fa-edit"></i></a></p>';
			echo '</li>';
			}
	
		}else {echo '<div class="alert info">Search by Company Name, Contact Number or Email id.</div>';}
	
	?>
	
	
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


