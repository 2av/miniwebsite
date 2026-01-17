<?php

require('connect.php');
require('header.php');

?>

<!-----------------php 1st script----------------------->

<script>
							
							// if approved
							
						
								function order_status(id){
										
										$('#order'+id).html('Wait...');
										var o_status = document.getElementById("order_status_option");
										var order_status = o_status.options[o_status.selectedIndex].value;
									
									console.log(order_status);
										$.ajax({
											url:'js_request.php',
											method:'POST',
											data:{order_id:id,order_status:order_status},
											dataType:'text',
											success:function(data){
												$('#order'+id).html(data);
											}
											
										});
										
									}
									
							</script>

<div class="container">
	<div class="card_row">
	
		<div class="row_contd">Order ID</div>
		<div class="row_contd">Product</div>
		<div class="row_contd">Product ID</div>
		
		<div class="row_contd">Order Status</div>
		<div class="row_contd">Name</div>
		<div class="row_contd">Email</div>
		<div class="row_contd">Contact</div>
		<div class="row_contd">Address</div>
		<div class="row_contd">Action</div>
		
		
		
	</div>
<?php

		if(isset($_GET['page_no'])){
				
			}else {$_GET['page_no']='1';}

			
			 
			 $limit=50;
			 
			  $start_from=($_GET['page_no']-1)*$limit;
		
		
		$query_orders=mysqli_query($connect,'SELECT * FROM orders WHERE user_email="'.$_SESSION['user_email'].'" ORDER BY id DESC LIMIT '.$start_from.','.$limit.'');
	
		
		// update notifications 
		
		if(mysqli_num_rows($query_orders) >>0){
			while($row=mysqli_fetch_array($query_orders)){
				$query_product=mysqli_query($connect, 'SELECT * FROM products WHERE id="'.$row['card_id'].'"');
				$rowP=mysqli_fetch_array($query_product);
				$x=$row['pro_id'];
				
				echo '<div class="card_row2">';
				echo '<div class="row_contd">'.$row['id'].'</div>';
				echo '<div class="row_contd"><img src="data:image/*;base64,'.base64_encode($rowP['pro_img'.$x]).'" alt="Logo"><br>';
				echo ''.$rowP['pro_name'.$x].'<br> Rs. '.$row['payment_amount'].'</div>';
				
				
		echo '<div class="row_contd">'.$row['pro_id'].'</div>
		<div class="row_contd '.$row['order_status'].'" id="order'.$row['id'].'" >'.$row['order_status'].'</div>
		<div class="row_contd">'.$row['c_name'].'</div>
		<div class="row_contd">'.$row['c_email'].'</div>
		<div class="row_contd">'.$row['c_contact'].'</div>
		
		<textarea class="row_contd">'.$row['c_address'].'
		
		
		'.$row['c_state'].'
		'.$row['c_city'].'
		'.$row['c_pincode'].'</textarea>
		
		<div class="row_contd">
			<select name="" id="order_status_option" onchange="order_status('.$row['id'].')">
				<option value="">-:Select:-</option>
				<option>Shipped</option>
				<option>Complete</option>
				<option>Cancel</option>
				</select></div>';
				
				echo '</div>';
			}
		}else {
			echo '<div class="alert info"> No Order Available</div>';
		}
		//check all orders
		
		
		
		?>
		
		
		
	</div>
	<div class="pagination">
			<?php 



				

				$query2=mysqli_query($connect,'SELECT * FROM orders WHERE  user_email="'.$_SESSION['user_email'].'" ORDER BY id DESC ');
			
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
			
<footer class="">

<p>Copyright 2025 || <?php echo $_SERVER['HTTP_HOST']; ?></p>

<script data-ad-client="ca-pub-8647574284151945" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>