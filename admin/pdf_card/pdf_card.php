<?php

require('../../connect.php');
require('../../header.php');

print_r('je;pp');
exit();


?>
<div class="filter_bar"><a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back </h3></a>
<h1>User's Card Manager</h1>

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
		
		
	</div>
	
	<?php
	
	

if(isset($_GET['page_no'])){
				
			}else {$_GET['page_no']='1';}

			
			 
			 $limit=30;
			 
			  $start_from=($_GET['page_no']-1)*$limit;
			  
// var case with filter
	$query=mysqli_query($connect,'SELECT *  FROM digi_card 
	WHERE
	CASE
    WHEN "'.$filter.'"="All" THEN   d_payment_status LIKE "%"  ELSE  d_payment_status="'.$filter.'"  END
	 AND f_user_email="" 
	ORDER BY id DESC LIMIT '.$start_from.','.$limit.'');
// var case ends
	
		if(mysqli_num_rows($query)>>0){
			while($row=mysqli_fetch_array($query)){
			echo '<li class="card_row2">';
			echo '<p>'.$row['user_email'].'</p>';
			echo '<p>'.$row['f_user_email'].'</p>';
			echo '<p><a href="https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id'].'" target="_blank">'.$row['id'].'</p>';
			echo '<p>'.$row['card_id'].'</p>';
			echo '<p>'.$row['d_comp_name'].' <i class="fa fa-external-link"></i></p></a>';
			echo '<p>'.$row['d_payment_status'].'</p>';
			echo '<p>'.$row['d_card_status'].'</p>';
				
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
		}else {
			echo '<div class="alert info">No Data Available...</div>';
		}
	?>
	

</div>

<!-------------------Pagination-------------------->
		<div class="pagination">
			<?php 



				

				$query2=mysqli_query($connect,'SELECT *  FROM digi_card 
	WHERE
	CASE
    WHEN "'.$filter.'"="All" THEN   d_payment_status LIKE "%"  ELSE  d_payment_status="'.$filter.'"  END
	 AND f_user_email=""  ORDER BY id DESC ');
			
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
							


 <footer class="footer-area">
     <br /><br />
     <center>
           
                    <a href="index.html" class="footer-logo">
                        						<img src="/panel/images/logo.png" alt="Vcard" width="auto" height="50px">
						                    </a>
                    <p>&copy; Copyright 2025 - All Rights Reserved. Crafted With <?php echo $_SERVER['HTTP_HOST']; ?> for Someone Special ! </p> 
					<p><a target="_blank" href="https://support.ajooba.io">Support Forum</a> | <a target="_blank" href="https://support.ajooba.io/faq">Faq's</a> | <a target="_blank" href="https://support.ajooba.io/articles/category/digital-vcard">Knowlege Base</a> </p>
			
        </center></footer>


