<?php
require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/coupon_functions.php');
require_once(__DIR__ . '/../includes/india_states_ut.php');
require('header.php');

// Check if admin is logged in
if(!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    echo '<script>alert("Please login first!"); window.location.href="login.php";</script>';
    exit;
}

// Ensure the state-wise deal column exists
mw_ensure_deal_state_column($connect);

// Indian states/UTs for the deal State dropdown
$deal_state_options = function_exists('mw_india_state_names') ? mw_india_state_names() : [];

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
    $deal_state = mysqli_real_escape_string($connect, trim($_POST['deal_state'] ?? ''));
    
    // Get admin email safely
    $created_by = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : 'admin';
    
    // Check if coupon code already exists
    $check_query = mysqli_query($connect, "SELECT * FROM deals WHERE coupon_code='$coupon_code'");
    if(mysqli_num_rows($check_query) == 0){
        $insert = mysqli_query($connect, "INSERT INTO deals (plan_name, plan_type, deal_name, coupon_code, bonus_amount, discount_amount, discount_percentage, validity_date, max_usage, deal_state, created_by) VALUES ('$plan_name', '$plan_type', '$deal_name', '$coupon_code', '$bonus_amount', '$discount_amount', '$discount_percentage', '$validity_date', '$max_usage', '$deal_state', '$created_by')");
        
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
    $deal_state = mysqli_real_escape_string($connect, trim($_POST['deal_state'] ?? ''));
    
    // Check if coupon code already exists for other deals
    $check_query = mysqli_query($connect, "SELECT * FROM deals WHERE coupon_code='$coupon_code' AND id!='$deal_id'");
    if(mysqli_num_rows($check_query) == 0){
        $update = mysqli_query($connect, "UPDATE deals SET plan_name='$plan_name', plan_type='$plan_type', deal_name='$deal_name', coupon_code='$coupon_code', bonus_amount='$bonus_amount', discount_amount='$discount_amount', discount_percentage='$discount_percentage', validity_date='$validity_date', max_usage='$max_usage', deal_state='$deal_state' WHERE id='$deal_id'");
        
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
                <p>State (Optional - for state-wise MiniWebsite deals)</p>
                <?php $edit_deal_state = isset($edit_deal['deal_state']) ? $edit_deal['deal_state'] : ''; ?>
                <select name="deal_state">
                    <option value="">All States (No state restriction)</option>
                    <?php foreach ($deal_state_options as $state_name): ?>
                    <option value="<?php echo htmlspecialchars($state_name); ?>" <?php echo ($edit_deal_state === $state_name) ? 'selected' : ''; ?>><?php echo htmlspecialchars($state_name); ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color:#666;">If set, this deal auto-applies during Mini Website payment for customers registered in this state.</small>
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
    <div class="deals_table_wrap">
        <table class="deals_table_pro">
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Plan Type</th>
                    <th>State</th>
                    <th>Deal Name</th>
                    <th>Coupon Code</th>
                    <th>Date Created</th>
                    <th>Bonus (Referrer)</th>
                    <th>Discount (User)</th>
                    <th>Validity Date</th>
                    <th>Usage</th>
                    <th>Status</th>
                    <th class="col-action">Action</th>
                </tr>
            </thead>
            <tbody>
        <?php
        $query = mysqli_query($connect, 'SELECT * FROM deals ORDER BY id DESC');
        
        if(mysqli_num_rows($query) > 0){
            while($row = mysqli_fetch_array($query)){
                $usage_text = ($row['max_usage'] == 0) ? $row['current_usage'] . '/∞' : $row['current_usage'] . '/' . $row['max_usage'];
                $validity_status = (strtotime($row['validity_date']) < time()) ? 'Expired' : 'Valid';

                // Discount text
                if($row['discount_amount'] > 0){
                    $discount_text = '₹' . $row['discount_amount'];
                } else if($row['discount_percentage'] > 0){
                    $discount_text = $row['discount_percentage'] . '%';
                } else {
                    $discount_text = '-';
                }

                $has_state = !empty($row['deal_state']);

                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['plan_name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['plan_type']) . '</td>';
                echo '<td>' . ($has_state ? '<span class="state-badge">' . htmlspecialchars($row['deal_state']) . '</span>' : '<span class="state-all">All</span>') . '</td>';
                echo '<td>' . htmlspecialchars($row['deal_name']) . '</td>';
                echo '<td><span class="coupon-chip">' . htmlspecialchars($row['coupon_code']) . '</span></td>';
                echo '<td>' . date('d-m-Y', strtotime($row['uploaded_date'])) . '</td>';
                echo '<td>₹' . htmlspecialchars($row['bonus_amount']) . '</td>';
                echo '<td>' . $discount_text . '</td>';
                echo '<td class="' . ($validity_status == 'Expired' ? 'expired' : 'valid') . '">' . date('d-m-Y', strtotime($row['validity_date'])) . '</td>';
                echo '<td>' . $usage_text . '</td>';
                echo '<td><span class="status-pill status_' . strtolower($row['deal_status']) . '">' . htmlspecialchars($row['deal_status']) . '</span></td>';
                echo '<td class="col-action">';
                echo '<a class="act-btn act-edit" href="?edit_deal=' . $row['id'] . '" title="Edit Deal"><i class="fa fa-edit"></i></a>';
                echo '<a class="act-btn act-toggle" href="?toggle_status=' . $row['id'] . '&current_status=' . $row['deal_status'] . '" onclick="return confirm(\'Toggle status?\')" title="Toggle Status"><i class="fa fa-toggle-on"></i></a>';
                echo '<a class="act-btn act-delete" href="?delete_deal=' . $row['id'] . '" onclick="return confirm(\'Delete this deal?\')" title="Delete Deal"><i class="fa fa-trash"></i></a>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="12" class="no-deals">No deals found</td></tr>';
        }
        ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.add_deal_form {
    background: #f9f9f9;
    padding: 20px;
    margin: 20px 0;
    border-radius: 5px;
}

/* Full-width layout for the deals listing on this page */
.container {
    width: auto !important;
    max-width: none !important;
    height: auto !important;
    min-height: auto !important;
    overflow: visible !important;
    margin: 15px !important;
}

.deals_table_wrap {
    margin-top: 20px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    overflow-x: auto;
    width: 100%;
}

.deals_table_pro {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    min-width: 1100px;
}

.deals_table_pro thead th {
    background: #1f2937;
    color: #fff;
    font-weight: 600;
    text-align: left;
    padding: 12px 14px;
    white-space: nowrap;
    border-bottom: 2px solid #111827;
    position: sticky;
    top: 0;
}

.deals_table_pro tbody td {
    padding: 11px 14px;
    border-bottom: 1px solid #eef0f2;
    color: #374151;
    vertical-align: middle;
    white-space: nowrap;
}

.deals_table_pro tbody tr:nth-child(even) {
    background: #fafbfc;
}

.deals_table_pro tbody tr:hover {
    background: #eff6ff;
}

.coupon-chip {
    display: inline-block;
    background: #eef2ff;
    color: #3730a3;
    font-weight: 700;
    letter-spacing: 0.4px;
    padding: 3px 9px;
    border-radius: 5px;
    font-size: 12px;
}

.state-badge {
    display: inline-block;
    background: #ecfdf5;
    color: #047857;
    padding: 3px 9px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.state-all {
    color: #9ca3af;
    font-style: italic;
}

.status-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.status-pill.status_active {
    background: #dcfce7;
    color: #15803d;
}

.status-pill.status_inactive {
    background: #fee2e2;
    color: #b91c1c;
}

.expired {
    color: #dc2626;
    font-weight: 600;
}

.valid {
    color: #16a34a;
    font-weight: 600;
}

.col-action {
    text-align: center;
}

.act-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 6px;
    margin: 0 2px;
    text-decoration: none;
    color: #fff;
    font-size: 13px;
    transition: opacity 0.15s ease;
}

.act-btn:hover {
    opacity: 0.85;
}

.act-edit { background: #2563eb; }
.act-toggle { background: #6b7280; }
.act-delete { background: #dc2626; }

.no-deals {
    text-align: center;
    padding: 30px !important;
    color: #6b7280;
    font-style: italic;
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




