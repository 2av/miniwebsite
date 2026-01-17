<?php

require('connect.php');
require('header.php');
require_once('../../common/verification_check.php');

// Check if franchisee is verified
$franchisee_email = $_SESSION['f_user_email'] ?? '';
$is_verified = isFranchiseeVerified($franchisee_email);

// Redirect to verification page if not verified
if(!$is_verified) {
    header('Location: ../../franchisee/verification/');
    exit();
}
$query_franchisee=mysqli_query($connect,'SELECT * FROM wallet WHERE f_user_email="'.$_SESSION['f_user_email'].'" ORDER BY ID DESC');

$row_franchisee=mysqli_fetch_array($query_franchisee);

// Get current balance safely
$current_balance = 0;
if($row_franchisee && isset($row_franchisee['w_balance'])) {
    $current_balance = (float)$row_franchisee['w_balance'];
}

?>


<div class="wallet_main">

<!-----------wallet details------------------------->
	<div class="wallet_side1">
		<h2><i class="fas fa-wallet"></i> Current Balance</h2>
		<p><i class="fa fa-rupee"></i><?php echo number_format($current_balance, 2); ?></p>
	</div>
	
	
	<div class="wallet_side1">
	<h2> Add Money</h2>
		<form action="payment_page/pay.php" method="POST">
			<input type="number" min="500" value="500" name="deposit" placeholder="Minimum Rs 500" required>
			<?php
			// Get franchisee details
			$franchisee_query = mysqli_query($connect, 'SELECT * FROM franchisee_login WHERE f_user_email="'.$_SESSION['f_user_email'].'"');
			$franchisee_data = mysqli_fetch_array($franchisee_query);
			
			// Extract name parts (assuming full name is in one field)
			$full_name = isset($franchisee_data['f_user_name']) ? $franchisee_data['f_user_name'] : '';
			$name_parts = explode(' ', $full_name, 2);
			$f_name = isset($name_parts[0]) ? $name_parts[0] : '';
			$l_name = isset($name_parts[1]) ? $name_parts[1] : '';
			$f_contact = isset($franchisee_data['f_user_contact']) ? $franchisee_data['f_user_contact'] : '';
			
			// Store in session
			$_SESSION['f_name'] = $f_name;
			$_SESSION['l_name'] = $l_name;
			$_SESSION['f_contact'] = $f_contact;
			?>
			<input type="hidden" name="f_name" value="<?php echo htmlspecialchars($f_name); ?>">
			<input type="hidden" name="l_name" value="<?php echo htmlspecialchars($l_name); ?>">
			<input type="hidden" name="f_contact" value="<?php echo htmlspecialchars($f_contact); ?>">
			<input type="submit" name="add_money">
		</form>
	
	</div>
<!-----------Wallet history-------------------------->
	<div class="wallet_history">
	
	<div class="card_row">
	
		<p>Date</p>
		<p>Balance</p>
		
		<p>Deposit</p>
		<p>Withdraw</p>
		<p>Order Id</p>
		<p>Txn Msg</p>
		
		
	</div>
	
	<?php
	// Use session email if no wallet record exists
	$user_email = ($row_franchisee && isset($row_franchisee['f_user_email'])) ? $row_franchisee['f_user_email'] : $_SESSION['f_user_email'];
	$q_wallet=mysqli_query($connect,'SELECT * FROM wallet WHERE f_user_email="'.$user_email.'" ORDER BY ID DESC LIMIT 10');
	
	if(mysqli_num_rows($q_wallet) > 0){
		while($q_row=mysqli_fetch_array($q_wallet)){
			
				echo '<div class="card_row2">';
				echo "<p>".date('d M y-h:s A',strtotime($q_row['uploaded_date']))."</p>";
				echo "<p> $q_row[w_balance] </p>";
				echo "<p style='color: #27b927;'> $q_row[w_deposit] </p>";
				echo "<p style='color:red'> $q_row[w_withdraw] </p>";
				echo "<p> $q_row[w_order_id] </p>";
				echo "<p> $q_row[w_txn_msg] </p>";
				
				echo '</div>';
		}
	}else {
		echo '<div class="alert info">No Txn Found!</div>';
	}
	
	
		?>
		
	
	</div>

</div>

