<?php

require('connect.php');
require('header.php');
require_once('../../common/verification_check.php');

$query_franchisee=mysqli_query($connect,'SELECT * FROM franchisee_login WHERE f_user_email="'.$_SESSION['f_user_email'].'"');
$row_franchisee=mysqli_fetch_array($query_franchisee);

// Check if franchisee is verified
$franchisee_email = $_SESSION['f_user_email'] ?? '';
$is_verified = isFranchiseeVerified($franchisee_email);
?>



<div class="dashboard">
<?php if(!$is_verified): ?>
	<div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; margin: 10px; border-radius: 5px;">
		<i class="fa fa-exclamation-triangle"></i>
		<strong>Document Verification Required!</strong> 
		Please complete your document verification to access all features. 
		<a href="../../franchisee/verification/" style="color: #856404; text-decoration: underline;">Verify Documents</a>
	</div>
<?php endif; ?>

<!-----------Dash side 1------------------------->

	<div class="dash_side1">

		<!----------alll links --------------------------->
		<div class="dash_link">
			<?php if($is_verified): ?>
				<a href="create_account.php"><li class="active">+ Create New Card</li></a>
			<?php else: ?>
				<li class="disabled" style="opacity: 0.6; cursor: not-allowed;" title="Document verification required">+ Create New Card <small>(Verification Required)</small></li>
			<?php endif; ?>

			<a href="user_manager.php"><li><i class="fa fa-group"></i> Manage Users</li></a>
			<a href="card_manager.php"><li><i class="fa fa-credit-card"></i> Manage Cards</li></a>
			
			<?php if($is_verified): ?>
				<a href="wallet.php"><li><i class='fa fa-money'></i> Wallet</li></a>
			<?php else: ?>
				<li class="disabled" style="opacity: 0.6; cursor: not-allowed;" title="Document verification required"><i class='fa fa-money'></i> Wallet <small>(Verification Required)</small></li>
			<?php endif; ?>
			
			<!-- <a href="my_account.php"><li><i class="fa fa-gear"></i> My Account</li></a> -->
			<a href="logout.php"><li><i class="fa fa-sign-out"></i> Logout</li></a>

		</div>
	</div>


<!-----------Dash side 2------------------------->
	<div class="dash_side2">
		<div class="das_box" onclick="location.href='card_manager.php'">
			<p>Total Cards</p>
			<p><i class="fa fa-credit-card"></i> <?php $query=mysqli_query($connect,'SELECT * FROM digi_card WHERE f_user_email="'.$_SESSION['f_user_email'].'"');
					echo mysqli_num_rows($query); ?>
			</p>

		</div>
		<div class="das_box" onclick="location.href='user_manager.php'">
			<p>Users</p>
			<p><i class="fa fa-group"></i> <?php $query=mysqli_query($connect,'SELECT DISTINCT user_email FROM digi_card WHERE f_user_email="'.$_SESSION['f_user_email'].'"');
					echo mysqli_num_rows($query); ?>
			</p>

		</div>
		<div class="das_box" <?php echo $is_verified ? 'onclick="location.href=\'wallet.php\'"' : 'style="opacity: 0.6; cursor: not-allowed;" title="Document verification required"'; ?>>
			<p>Wallet</p>
			<p><i class="fa fa-rupee"></i> <?php 
				$query_franchisee=mysqli_query($connect,'SELECT * FROM wallet WHERE f_user_email="'.$_SESSION['f_user_email'].'" ORDER BY ID DESC');
				$row_franchisee=mysqli_fetch_array($query_franchisee);
				
				// Check if wallet record exists and has balance
				if($row_franchisee && isset($row_franchisee['w_balance'])) {
					$balance = (float)$row_franchisee['w_balance'];
					if($balance < 299) { 
						echo '<span style="color:Red" title="Please Recharge to create card.">'.number_format($balance,2).' <i class="fa fa-question-circle" title="Amount is less, reacharge wallet to create card."></i></span>';
					} else {
						echo '<span style="color:green">'.number_format($balance,2).'</span>';
					}
				} else {
					// No wallet record found - show 0 balance
					echo '<span style="color:Red" title="No wallet found. Please contact admin.">0.00 <i class="fa fa-exclamation-triangle" title="No wallet record found"></i></span>';
				}
				
				if(!$is_verified) {
					echo '<br><small style="color: #dc3545;">Verification Required</small>';
				}
			?>
			</p>

		</div>


		<!---------------chart details------------------------->
			<div class="user_details">

				<?php



				$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE f_user_email="'.$_SESSION['f_user_email'].'" ');


				?>
					<h3>Your Account Summary</h3>
				<?php

				$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE f_user_email="'.$_SESSION['f_user_email'].'" ');
				echo '<div class="flex_box "><p>Total Cards</p><p>'.mysqli_num_rows($query).'</p></div>';

				?>
					<?php

				$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE f_user_email="'.$_SESSION['f_user_email'].'" and d_payment_status="Success"');
				echo '<div class="flex_box"><p>Active Cards</p><p>'.mysqli_num_rows($query).'</p></div>';

				?>
					<?php

				$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE f_user_email="'.$_SESSION['f_user_email'].'" and d_payment_status="Failed"');
				echo '<div class="flex_box "><p>Inactive Cards</p><p>'.mysqli_num_rows($query).'</p></div>';

				?>
					<?php

				$query=mysqli_query($connect,'SELECT * FROM digi_card WHERE f_user_email="'.$_SESSION['f_user_email'].'" and d_payment_status="Created"');
				echo '<div class="flex_box"><p>Trial Cards</p><p>'.mysqli_num_rows($query).'</p></div>';

				?>
					<?php

				$query=mysqli_query($connect,'SELECT SUM(d_payment_amount) as payment FROM digi_card WHERE f_user_email="'.$_SESSION['f_user_email'].'" and d_payment_status="Success" ');
				$row=mysqli_fetch_array($query);
				$payment_amount = is_null($row['payment']) ? 0 : $row['payment'];
				echo '<div class="flex_box "><p>Payment Total </p><p>'.number_format($payment_amount,2).' Rs</p></div>';

				?>



				</div>

		<!---------------chart details------------------------->


	</div>



</div>


<footer class="">

<p><?php echo $_SERVER['HTTP_HOST']; ?></p>

<script data-ad-client="ca-pub-2577996436540735" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script></footer>
