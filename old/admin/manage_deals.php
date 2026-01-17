<?php
require('connect.php');
require('header.php');

// Check if admin is logged in
if(!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    echo '<script>alert("Please login first!"); window.location.href="login.php";</script>';
    exit;
}

// Add new deal
if(isset($_POST['add_deal'])){
    $plan_name = mysqli_real_escape_string($connect, $_POST['plan_name']);
    $plan_type = mysqli_real_escape_string($connect, $_POST['plan_type']);
    $deal_name = mysqli_real_escape_string($connect, $_POST['deal_name']);
    $coupon_code = strtoupper(mysqli_real_escape_string($connect, $_POST['coupon_code']));
    $bonus_amount = mysqli_real_escape_string($connect, $_POST['bonus_amount']);
    $discount_amount = mysqli_real_escape_string($connect, $_POST['discount_amount']);
    $discount_percentage = mysqli_real_escape_string($connect, $_POST['discount_percentage']);
    $validity_date = mysqli_real_escape_string($connect, $_POST['validity_date']);
    $max_usage = mysqli_real_escape_string($connect, $_POST['max_usage']);
    
    // Get admin email safely
    $created_by = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : 'admin';
    
    // Check if coupon code already exists
    $check_query = mysqli_query($connect, "SELECT * FROM deals WHERE coupon_code='$coupon_code'");
    if(mysqli_num_rows($check_query) == 0){
        $insert = mysqli_query($connect, "INSERT INTO deals (plan_name, deal_name, coupon_code, bonus_amount, discount_amount, discount_percentage, validity_date, max_usage, created_by) VALUES ('$plan_name', '$deal_name', '$coupon_code', '$bonus_amount', '$discount_amount', '$discount_percentage', '$validity_date', '$max_usage', '$created_by')");
        
        if($insert){
            echo '<div class="alert success">Deal Added Successfully!</div>';
        } else {
            echo '<div class="alert danger">Error adding deal!</div>';
        }
    } else {
        echo '<div class="alert danger">Coupon code already exists!</div>';
    }
}

// Update deal status
if(isset($_GET['toggle_status'])){
    $deal_id = mysqli_real_escape_string($connect, $_GET['toggle_status']);
    $current_status = $_GET['current_status'];
    $new_status = ($current_status == 'Active') ? 'Inactive' : 'Active';
    
    $update = mysqli_query($connect, "UPDATE deals SET deal_status='$new_status' WHERE id='$deal_id'");
    if($update){
        echo '<div class="alert success">Status updated!</div>';
    }
}

// Delete deal
if(isset($_GET['delete_deal'])){
    $deal_id = mysqli_real_escape_string($connect, $_GET['delete_deal']);
    $delete = mysqli_query($connect, "DELETE FROM deals WHERE id='$deal_id'");
    if($delete){
        echo '<div class="alert success">Deal deleted!</div>';
    }
}

// Edit deal
if(isset($_POST['edit_deal'])){
    $deal_id = mysqli_real_escape_string($connect, $_POST['deal_id']);
    $plan_name = mysqli_real_escape_string($connect, $_POST['plan_name']);
    $plan_type = mysqli_real_escape_string($connect, $_POST['plan_type']);  
    $deal_name = mysqli_real_escape_string($connect, $_POST['deal_name']);
    $coupon_code = strtoupper(mysqli_real_escape_string($connect, $_POST['coupon_code']));
    $bonus_amount = mysqli_real_escape_string($connect, $_POST['bonus_amount']);
    $discount_amount = mysqli_real_escape_string($connect, $_POST['discount_amount']);
    $discount_percentage = mysqli_real_escape_string($connect, $_POST['discount_percentage']);
    $validity_date = mysqli_real_escape_string($connect, $_POST['validity_date']);
    $max_usage = mysqli_real_escape_string($connect, $_POST['max_usage']);
    
    // Check if coupon code already exists for other deals
    $check_query = mysqli_query($connect, "SELECT * FROM deals WHERE coupon_code='$coupon_code' AND id!='$deal_id'");
    if(mysqli_num_rows($check_query) == 0){
        $update = mysqli_query($connect, "UPDATE deals SET plan_name='$plan_name', plan_type='$plan_type', deal_name='$deal_name', coupon_code='$coupon_code', bonus_amount='$bonus_amount', discount_amount='$discount_amount', discount_percentage='$discount_percentage', validity_date='$validity_date', max_usage='$max_usage' WHERE id='$deal_id'");
        
        if($update){
            echo '<div class="alert success">Deal Updated Successfully!</div>';
        } else {
            echo '<div class="alert danger">Error updating deal!</div>';
        }
    } else {
        echo '<div class="alert danger">Coupon code already exists!</div>';
    }
}

// Get deal for editing
$edit_deal = null;
if(isset($_GET['edit_deal'])){
    $edit_id = mysqli_real_escape_string($connect, $_GET['edit_deal']);
    $edit_query = mysqli_query($connect, "SELECT * FROM deals WHERE id='$edit_id'");
    if(mysqli_num_rows($edit_query) > 0){
        $edit_deal = mysqli_fetch_array($edit_query);
    }
}
?>

<div class="main3">
    <a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back</h3></a>
    <h1>Manage Deals & Coupons</h1>
    <a href="manage_deal_mapping.php" class="btn btn-primary" style="margin-bottom: 20px;">
        <i class="fa fa-users"></i> Manage Deal Customer Mapping
    </a>
    
    <div class="add_deal_form">
        <h3><?php echo isset($edit_deal) ? 'Edit Deal' : 'Add New Deal'; ?></h3>
        <form method="POST">
            <?php if(isset($edit_deal)): ?>
                <input type="hidden" name="deal_id" value="<?php echo $edit_deal['id']; ?>">
            <?php endif; ?>
            
            <div class="input_box">
                <p>Plan Name *</p>
                <select name="plan_name" required>
                    <option value="">Select Plan</option>
                    <option value="Basic" <?php echo (isset($edit_deal) && $edit_deal['plan_name'] == 'Basic') ? 'selected' : ''; ?>>Basic Plan</option>
                    <option value="Premium" <?php echo (isset($edit_deal) && $edit_deal['plan_name'] == 'Premium') ? 'selected' : ''; ?>>Premium Plan</option>
                    <option value="Enterprise" <?php echo (isset($edit_deal) && $edit_deal['plan_name'] == 'Enterprise') ? 'selected' : ''; ?>>Enterprise Plan</option>
                </select>
            </div>
            <div class="input_box">
                <p>Plan Type *</p>
                <select name="plan_type" required>
                    <option value="">Select Plan Type</option>
                    <option value="MiniWebsite" <?php echo (isset($edit_deal) && $edit_deal['plan_type'] == 'MiniWebsite') ? 'selected' : ''; ?>>Mini Website</option>
                    <option value="Franchise" <?php echo (isset($edit_deal) && $edit_deal['plan_type'] == 'Franchise') ? 'selected' : ''; ?>>Franchise</option>
 
                </select>
            </div>
            
            <div class="input_box">
                <p>Deal Name *</p>
                <input type="text" name="deal_name" placeholder="e.g., New Year Offer" value="<?php echo isset($edit_deal) ? htmlspecialchars($edit_deal['deal_name']) : ''; ?>" required>
            </div>
            
            <div class="input_box">
                <p>Coupon Code *</p>
                <input type="text" name="coupon_code" placeholder="e.g., NEWYEAR2024" style="text-transform: uppercase;" value="<?php echo isset($edit_deal) ? htmlspecialchars($edit_deal['coupon_code']) : ''; ?>" required>
            </div>
            
            <div class="input_box">
                <p>Bonus Amount (For Referrer)</p>
                <input type="number" name="bonus_amount" placeholder="0" min="0" value="<?php echo isset($edit_deal) ? $edit_deal['bonus_amount'] : '0'; ?>">
            </div>
            
            <div class="input_box">
                <p>Discount Amount (For Referred User)</p>
                <input type="number" name="discount_amount" placeholder="0" min="0" value="<?php echo isset($edit_deal) ? $edit_deal['discount_amount'] : '0'; ?>">
            </div>
            
            <div class="input_box">
                <p>Discount Percentage</p>
                <input type="number" name="discount_percentage" placeholder="0" min="0" max="100" value="<?php echo isset($edit_deal) ? $edit_deal['discount_percentage'] : '0'; ?>">
            </div>
            
            <div class="input_box">
                <p>Validity Date *</p>
                <input type="date" name="validity_date" value="<?php echo isset($edit_deal) ? $edit_deal['validity_date'] : ''; ?>" required>
            </div>
            
            <div class="input_box">
                <p>Maximum Usage (0 = Unlimited)</p>
                <input type="number" name="max_usage" placeholder="0" min="0" value="<?php echo isset($edit_deal) ? $edit_deal['max_usage'] : '0'; ?>">
            </div>
            
            <input type="submit" name="<?php echo isset($edit_deal) ? 'edit_deal' : 'add_deal'; ?>" value="<?php echo isset($edit_deal) ? 'Update Deal' : 'Add Deal'; ?>">
            
            <?php if(isset($edit_deal)): ?>
                <a href="manage_deals.php" class="cancel_btn">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="container">
    <div class="deals_table">
        <div class="card_row">
            <p>Plan</p>
            <p>Plan Type</p>
            <p>Deal Name</p>
            <p>Coupon Code</p>
            <p>Date Created</p>
            <p>Bonus (Referrer)</p>
            <p>Discount (User)</p>
            <p>Validity Date</p>
            <p>Usage</p>
            <p>Status</p>
            <p>Action</p>
        </div>
        
        <?php
        $query = mysqli_query($connect, 'SELECT * FROM deals ORDER BY id DESC');
        
        if(mysqli_num_rows($query) > 0){
            while($row = mysqli_fetch_array($query)){
                $usage_text = ($row['max_usage'] == 0) ? $row['current_usage'] . '/∞' : $row['current_usage'] . '/' . $row['max_usage'];
                $validity_status = (strtotime($row['validity_date']) < time()) ? 'Expired' : 'Valid';
                
                echo '<div class="card_row2">';
                echo '<p>' . $row['plan_name'] . '</p>';
                echo '<p>' . $row['plan_type'] . '</p>';
                echo '<p>' . $row['deal_name'] . '</p>';
                echo '<p><strong>' . $row['coupon_code'] . '</strong></p>';
                echo '<p>' . date('d-m-Y', strtotime($row['uploaded_date'])) . '</p>';
                echo '<p>₹' . $row['bonus_amount'] . '</p>';
                
                // Show discount
                if($row['discount_amount'] > 0){
                    echo '<p>₹' . $row['discount_amount'] . '</p>';
                } else if($row['discount_percentage'] > 0){
                    echo '<p>' . $row['discount_percentage'] . '%</p>';
                } else {
                    echo '<p>-</p>';
                }
                
                echo '<p class="' . ($validity_status == 'Expired' ? 'expired' : 'valid') . '">' . date('d-m-Y', strtotime($row['validity_date'])) . '</p>';
                echo '<p>' . $usage_text . '</p>';
                echo '<p><span class="status_' . strtolower($row['deal_status']) . '">' . $row['deal_status'] . '</span></p>';
                echo '<p>';
                echo '<a href="?edit_deal=' . $row['id'] . '" title="Edit Deal"><i class="fa fa-edit"></i></a> ';
                echo '<a href="?toggle_status=' . $row['id'] . '&current_status=' . $row['deal_status'] . '" onclick="return confirm(\'Toggle status?\')" title="Toggle Status"><i class="fa fa-toggle-on"></i></a> ';
                echo '<a href="?delete_deal=' . $row['id'] . '" onclick="return confirm(\'Delete this deal?\')" title="Delete Deal"><i class="fa fa-trash"></i></a>';
                echo '</p>';
                echo '</div>';
            }
        } else {
            echo '<div class="card_row2"><p colspan="10">No deals found</p></div>';
        }
        ?>
    </div>
</div>

<style>
.add_deal_form {
    background: #f9f9f9;
    padding: 20px;
    margin: 20px 0;
    border-radius: 5px;
}

.deals_table {
    margin-top: 20px;
}

.card_row, .card_row2 {
    display: grid;
    grid-template-columns: 1fr 1.5fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr;
    gap: 10px;
    padding: 10px;
    border-bottom: 1px solid #ddd;
}

.card_row {
    background: #333;
    color: white;
    font-weight: bold;
}

.card_row2:hover {
    background: #f5f5f5;
}

.status_active {
    color: green;
    font-weight: bold;
}

.status_inactive {
    color: red;
    font-weight: bold;
}

.expired {
    color: red;
}

.valid {
    color: green;
}

@media (max-width: 768px) {
    .card_row, .card_row2 {
        grid-template-columns: 1fr;
        text-align: left;
    }
}

.cancel_btn {
    background: #666;
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 3px;
    margin-left: 10px;
    display: inline-block;
}

.cancel_btn:hover {
    background: #555;
}

.add_deal_form input[type="submit"] {
    background: #007cba;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 3px;
    cursor: pointer;
}

.add_deal_form input[type="submit"]:hover {
    background: #005a87;
}
</style>

