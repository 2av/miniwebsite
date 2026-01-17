<?php

require('connect.php');
require('header.php');

?>


<div class="container">

	<a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back </h3></a>	
	<h2> Card Manager</h2>
<div class="card_row">
	
		<p>User ID</p>
		<p>Card ID</p>
		
		<p>Company Name</p>
		<p>Payment Status</p>
		<p>Card Status</p>
		<p>Data</p>
		<p>Share</p>
		<!-- <p>Action</p>
		<p>Edit Card</p>
		<p>Payment</p> -->
		
		
	</div>
	
	<?php
	
	

if(isset($_GET['page_no'])){
				
			}else {$_GET['page_no']='1';}

			
			 
			 $limit=50;
			 
			  $start_from=($_GET['page_no']-1)*$limit;
			 
	$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE f_user_email="'.$_SESSION['f_user_email'].'" ORDER BY id DESC LIMIT '.$start_from.','.$limit.'');
	

		if(mysqli_num_rows($query)>>0){
			while($row=mysqli_fetch_array($query)){
			echo '<li class="card_row2">';
			echo '<p>'.$row['user_email'].'</p>';
			echo '<p><a href="https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id'].'" target="_blank">'.$row['id'].'</p>';
			echo '<p>'.$row['d_comp_name'].' <i class="fa fa-external-link"></i></p></a>';
			echo '<p>'.$row['d_payment_status'].'</p>';
				echo '<p  >';
				if($row['d_payment_status']=='Created'){echo 'Active';}else if($row['d_payment_status']=='Success'){echo 'Active';}else if($row['d_payment_status']=='Failed'){echo 'Inactive';}
				echo '</p>';
			echo '<p>'.$row['uploaded_date'].'</p>';
			echo '<p><a href="https://api.whatsapp.com/send?text=https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id'].'" target="_blank"><i class="fa fa-whatsapp"></i></a></p>';
			//echo '<p><a href="https://api.whatsapp.com/send?text=https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id'].'" target="_blank"><i class="fa fa-whatsapp"></i></a><a href="https://www.facebook.com/sharer/sharer.php?u=https://'.$_SERVER['HTTP_HOST'].'/'.$row['card_id'].'" target="_blank"><i class="fa fa-facebook"></i></a></p>';

			// activate card 
			// echo '<p id="active_btn" class="idact'.$row['id'].'" onclick="activateUser('.$row['id'].')"><span class=" '.$row['d_card_status'].'">';
			// if(!empty($row['d_card_status'])){echo $row['d_card_status'];}else {echo "Click To Activate";}
			// echo '</span></p>';

			//echo '<p>	<a href="create_card.php?card_number='.$row['id'].'&user_email='.$row['user_email'].'"><i class="fa fa-edit"></i></a></p>';
			//	echo '<p><a href="card_manager.php?id='.$row['id'].'"><i class="fa fa-edit"></i></a></p>';
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



				

				$query2=mysqli_query($connect,'SELECT * FROM digi_card WHERE  f_user_email="'.$_SESSION['f_user_email'].'" ORDER BY id DESC ');
			
			 $pages=ceil(mysqli_num_rows($query2)/50);

			for($i=1;$i<=$pages;$i++){
				if($_GET['page_no']==$i){
					echo '<a href="?page_no='.$i.'"><div class="page_btn active">'. $i.'</div></a>';
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


<footer class="">

<p>Copyright 2025 || <?php echo $_SERVER['HTTP_HOST']; ?></p>

<script data-ad-client="ca-pub-2577996436540735" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>