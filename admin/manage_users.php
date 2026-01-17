<?php

require_once(__DIR__ . '/../app/config/database.php');
require('header.php');

?>


<div class="main3">
	<a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back </h3></a>
	<h1 class="close_form">Manage Users</h1>
	
	
	





</div>
<div class="all_franchisee">
	<div class="card_row">
	
		<p>User ID</p>
		<p>Franchisee Email</p>
		<p>Franchisee Contact</p>
		<p>Franchisee Password</p>
		<p>Franchisee Name</p>
		<p>Status</p>
		<p>Total MiniWebsites</p>
		
		
		<p>Date</p>
		
		
	</div>
	<?php
	
	

if(isset($_GET['page_no'])){
				
			}else {$_GET['page_no']='1';}

			
			 
			 $limit=30;
			 
			  $start_from=($_GET['page_no']-1)*$limit;
			 
	// Query user_details table for customers
	$query=mysqli_query($connect,'SELECT * FROM user_details WHERE role="CUSTOMER" ORDER BY id DESC LIMIT '.$start_from.','.$limit.'');
	

		if(mysqli_num_rows($query)>0){
			
			while($row=mysqli_fetch_array($query)){
				// Map user_details fields to old field names for compatibility
				$user_email = $row['email'] ?? '';
				$user_contact = $row['phone'] ?? '';
				$user_password = $row['password'] ?? '';
				$user_name = $row['name'] ?? '';
				$user_active = ($row['status'] ?? 'INACTIVE') === 'ACTIVE' ? '1' : '0';
				$uploaded_date = $row['created_at'] ?? '';
				
				// check from digi card all card made by customer
			$query2=mysqli_query($connect,'SELECT * FROM digi_card WHERE user_email="'.$user_email.'" ORDER BY id DESC ');
			
			echo '<li class="card_row2">';
			echo '<p>'.$row['id'].'</p>';
			echo '<p>'.htmlspecialchars($user_email).'</p>';
			echo '<p>'.htmlspecialchars($user_contact).'</p>';
			echo '<p>'.htmlspecialchars($user_password).'</p>';
			
			echo '<p>'.htmlspecialchars($user_name).' </p>';
			echo '<p>'.$user_active.'</p>';
				echo '<p>'.mysqli_num_rows($query2).'</p>';
				
				
			echo '<p>'.$uploaded_date.'</p>';
			

			
			echo '</li>';
			}
		}else {
			echo '<div class="alert info">No Data Available...</div>';
		}
	?>
	

</div>

<!-------------------Pagination-------------------->
		<div class="pagination">
			<?php 



				

				// Query user_details table for customers
				$query2=mysqli_query($connect,'SELECT * FROM user_details WHERE role="CUSTOMER" ORDER BY id DESC ');
			
			 $pages=ceil(mysqli_num_rows($query2)/30);

			for($i=1;$i<=$pages;$i++){
				if($_GET['page_no']==$i){
					echo '<a href="?page_no='.$i.'"><div class="page_btn active">'. $i.'</div></a>';
				}else {
					echo '<a href="?page_no='.$i.'"><div class="page_btn">'. $i.'</div></a>';
				}
				
			}


			?>
	</div>


<br /><br /><br /> <br /><br /><br /><br />
 <footer class="footer-area"><center>
           <br />
                    <a href="index.html" class="footer-logo">
                        						<img src="../panel/images/f_logo.png" alt="V`card`" width="auto" height="50px">
						                    </a>
                    <p>&copy; Copyright 2025 - All Rights Reserved. Crafted With <?php echo $_SERVER['HTTP_HOST']; ?> for Someone Special ! </p> 
					<p><a target="_blank" href="https://support.ajooba.io">Support Forum</a> | <a target="_blank" href="https://support.ajooba.io/faq">Faq's</a> | <a target="_blank" href="https://support.ajooba.io/articles/category/digital-vcard">Knowlege Base</a> </p>
			
        </center></footer>


