<?php

require('connect.php');
require('header.php');

?>

<div class="container">

	<a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back </h3></a>	
	<div class="card_row">
	
		<p>User ID</p>
		<p>Email</p>
		
	 
		<p>Card</p>
		<p>Active</p>
		<p>Date</p>
		
		
	</div>
	
	<?php
	
	

if(isset($_GET['page_no'])){
				
			}else {$_GET['page_no']='1';}

			
			 
			 $limit=50;
			 
			  $start_from=($_GET['page_no']-1)*$limit;
			 
	$query=mysqli_query($connect,'SELECT DISTINCT user_email FROM digi_card WHERE f_user_email="'.$_SESSION['f_user_email'].'" ORDER BY id DESC ');
	

		if(mysqli_num_rows($query)>>0){
			
			
			while($row=mysqli_fetch_array($query)){
				$queryuser=mysqli_query($connect,'SELECT * FROM customer_login  WHERE user_email="'.$row['user_email'].'" ORDER BY id DESC LIMIT '.$start_from.','.$limit.'');
			$row22=mysqli_fetch_array($queryuser);
			
				$querycard=mysqli_query($connect,'SELECT * FROM digi_card  WHERE user_email="'.$row['user_email'].'"');
			$rowcard=mysqli_num_rows($querycard);
			
			
			echo '<li class="card_row2">';
			echo '<p>'.$row22['id'].'</p>';
			echo '<p>'.$row22['user_email'].'</p>';
			 
			echo '<p>'.$rowcard.'</p>';
			echo '<p>'.$row22['user_active'].'</p>';
			echo '<p>'.$row22['uploaded_date'].'</p>';
			
					
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



				

				$query2=mysqli_query($connect,'SELECT DISTINCT user_email  FROM digi_card WHERE  f_user_email="'.$_SESSION['f_user_email'].'" ORDER BY id DESC ');
			
			 $pages=ceil(mysqli_num_rows($query2)/50);

			for($i=1;$i<=$pages;$i++){
				if($_GET['page_no']==$i){
					echo '<a href="index.php?page_no='.$i.'"><div class="page_btn active">'. $i.'</div></a>';
				}else {
					echo '<a href="index.php?page_no='.$i.'"><div class="page_btn">'. $i.'</div></a>';
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