<?php

require_once(__DIR__ . '/../app/config/database.php');
require('header.php');

?>
<div class="filter_bar">
<a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back </h3></a>
<h1>Franchisee's MiniWebsite Manager</h1>




	<h3>Filter </h3>
		<form action="">
			<select name="filter_option">
				<option value="">-Select-</option>
				<option>Payment Done</option>
				<option>Payment Not Done</option>
				<option>Trail Cards</option>
				
				
			</select>
			<input type="submit" name="filter">
		</form>
		
	<h3> Search</h3>
	<form action="">
			<input type="search" name="search_item" placeholder="Search id/Company/Franchisee/User">
			<input type="submit" name="search" value="Search">
		</form>
		
<?php

if(isset($_GET['filter'])){
				  
				  if($_GET['filter_option']=='Payment Done'){$filter="Success";}
				  else if($_GET['filter_option']=='Payment Not Done'){$filter="Created";}
				  else if($_GET['filter_option']=='Trail Cards'){$filter="Created";}
					else {$filter="All";}
					
			  }else {$filter="All";}
			  
			 
?>		
		
		
</div>

<!--------Top filter -------------------------->
<div class="container">
	<div class="card_row">
	
		<p>User ID</p>
		<p>Franchisee/Admin ID</p>
		
		<p>MW ID</p>
		<p>MiniWebsite ID</p>
		
		<p>Company Name</p>
		<p>Payment Status</p>
		<p>MiniWebsite Status</p>
		<p>Data</p>
		<p>Payment Data</p>
		<p>Payment Amount</p>
		<p>Share</p>
		<p>Action</p>
		<p>Edit</p>
		
		
	</div>
	
	<?php
	
	

if(isset($_GET['page_no'])){
				
			}else {$_GET['page_no']='1';}

			
			 
			 $limit=30;
			 
			  $start_from=($_GET['page_no']-1)*$limit;
			  
// var case with filter - Updated with proper JOINs using user_details
	$query=mysqli_query($connect,'SELECT 
		dc.*,
		cl.id as customer_user_id,
		cl.name as customer_name,
		fl.id as franchisee_user_id,
		fl.name as franchisee_name,
		al.id as admin_user_id,
		al.name as admin_name
	FROM digi_card dc
	LEFT JOIN user_details cl ON dc.user_email = cl.email AND cl.role="CUSTOMER"
	LEFT JOIN user_details fl ON dc.f_user_email = fl.email AND fl.role="FRANCHISEE"
	LEFT JOIN user_details al ON dc.f_user_email = al.email AND al.role="ADMIN"
	WHERE
	CASE
    WHEN "'.$filter.'"="All" THEN   dc.d_payment_status LIKE "%"  ELSE  dc.d_payment_status="'.$filter.'"  
	END
	 AND dc.f_user_email!="" 
	ORDER BY dc.id DESC LIMIT '.$start_from.','.$limit.'');
// var case ends
	
		if(mysqli_num_rows($query)>0){
			while($row=mysqli_fetch_array($query)){
			echo '<li class="card_row2">';
			// User ID from customer_login table
			$user_id = $row['customer_user_id'] ?? 'N/A';
			$user_id_style = ($user_id === 'N/A') ? 'style="background-color: #ffebee; color: #c62828; font-weight: bold;" title="Customer email: '.$row['user_email'].'"' : '';
			echo '<p '.$user_id_style.'>'.$user_id.'</p>';
			
			// Franchisee ID from franchisee_login table OR Admin ID from admin_login table
			$franchisee_id = $row['franchisee_user_id'] ?? 'N/A';
			$admin_id = $row['admin_user_id'] ?? null;
			
			if($franchisee_id === 'N/A' && $admin_id) {
				// Show admin ID with different styling
				echo '<p style="background-color: #e8f5e8; color: #2e7d32; font-weight: bold;" title="Admin: '.$row['admin_name'].' ('.$row['f_user_email'].')">admin</p>';
			} else if($franchisee_id === 'N/A') {
				// No franchisee and no admin found
				echo '<p style="background-color: #fff3e0; color: #ef6c00; font-weight: bold;" title="Franchisee email: '.$row['f_user_email'].'">N/A</p>';
			} else {
				// Show franchisee ID normally
				echo '<p>'.$franchisee_id.'</p>';
			}
			// MW ID from digi_card table
			echo '<p><a href="https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id'].'" target="_blank">'.$row['id'].'</a></p>';
			// MiniWebsite ID from digi_card table
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



				

				$query2=mysqli_query($connect,'SELECT dc.* FROM digi_card dc WHERE dc.f_user_email!="" ORDER BY dc.id DESC ');
			
			 $pages=ceil(mysqli_num_rows($query2)/30);

			for($i=1;$i<=$pages;$i++){
				if($_GET['page_no']==$i){
					echo '<a href="?page_no='.$i.'&filter_option='.$filter.'&filter=Submit"><div class="page_btn active">'. $i.'</div></a>';
				}else {
					echo '<a href="?page_no='.$i.'"><div class="page_btn">'. $i.'</div></a>';
				}
				
			}


			?>
	</div>

<!-------------------Pagination-------------------->

<script>
							
							// if approved
								function activateUser(id){
										
										$('.idact'+id).css('color','blue').html('Wait...');
									
										$.ajax({
											url:'js_request.php',
											method:'POST',
											data:{card_id:id,activate_user:'YES'},
											dataType:'text',
											success:function(data){
												$('.idact'+id).html(data);
											}
											
										});
										
									}
									
							</script>
		</div>
 <br /><br /><br />		
<footer class="footer-area">
   <center>
           <br />
                    <a href="index.html" class="footer-logo">
                        						<img src="../panel/images/f_logo.png" alt="Vcard" width="auto" height="50px">
						                    </a>
                    <p>&copy; Copyright 2025 - All Rights Reserved. Crafted With <?php echo $_SERVER['HTTP_HOST']; ?> for Someone Special ! </p> 
					<p><a target="_blank" href="https://support.ajooba.io">Support Forum</a> | <a target="_blank" href="https://support.ajooba.io/faq">Faq's</a> | <a target="_blank" href="https://support.ajooba.io/articles/category/digital-vcard">Knowlege Base</a> </p>
			
        </center></footer>


