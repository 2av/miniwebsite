<?php
require('connect.php');
require('header.php');

// Check if admin is logged in
if(!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    echo '<script>alert("Please login first!"); window.location.href="login.php";</script>';
    exit;
}

// Add deal mapping
if(isset($_POST['add_mapping'])){
    $deal_id = mysqli_real_escape_string($connect, $_POST['deal_id']);
    $customer_email = mysqli_real_escape_string($connect, $_POST['customer_email']);
    
    // Check if mapping already exists
    $check_query = mysqli_query($connect, "SELECT * FROM deal_customer_mapping WHERE deal_id='$deal_id' AND customer_email='$customer_email'");
    if(mysqli_num_rows($check_query) == 0){
        $insert = mysqli_query($connect, "INSERT INTO deal_customer_mapping (deal_id, customer_email, created_by, created_date) VALUES ('$deal_id', '$customer_email', '".$_SESSION['admin_email']."', NOW())");
        
        if($insert){
            echo '<div class="alert success">Customer mapped to deal successfully!</div>';
        } else {
            echo '<div class="alert danger">Error mapping customer!</div>';
        }
    } else {
        echo '<div class="alert danger">Customer already mapped to this deal!</div>';
    }
}

// Remove deal mapping
if(isset($_GET['remove_mapping'])){
    $mapping_id = mysqli_real_escape_string($connect, $_GET['remove_mapping']);
    $delete = mysqli_query($connect, "DELETE FROM deal_customer_mapping WHERE id='$mapping_id'");
    if($delete){
        echo '<div class="alert success">Mapping removed successfully!</div>';
    }
}
?>

<div class="main3">
    <a href="manage_deals.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back to deals</h3></a>
    <h1>Deal Customer Mapping</h1>
    
    <div class="add_deal_form">
        <h3>Map Customer to Deal</h3>
        <form method="POST">
            <div class="input_box">
                <p>Select Deal *</p>
                <select name="deal_id" required>
                    <option value="">Select Deal</option>
                    <?php
                    $deals_query = mysqli_query($connect, "SELECT * FROM deals WHERE deal_status='Active' ORDER BY deal_name");
                    while($deal = mysqli_fetch_array($deals_query)){
                        echo '<option value="'.$deal['id'].'">'.$deal['deal_name'].' ('.$deal['coupon_code'].')</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div class="input_box">
                <p>Select Customer *</p>
                <select name="customer_email" required>
                    <option value="">Select Customer</option>
                    <?php
                    // Query user_details table for customers
                    $customers_query = mysqli_query($connect, "SELECT DISTINCT email, name FROM user_details WHERE role='CUSTOMER' ORDER BY name");
                    
                    if($customers_query && mysqli_num_rows($customers_query) > 0) {
                        while($customer = mysqli_fetch_array($customers_query)){
                            echo '<option value="'.$customer['email'].'">'.$customer['name'].' ('.$customer['email'].')</option>';
                        }
                    } else {
                        echo '<option value="">No customers found</option>';
                    }
                    ?>
                </select>
            </div>
            
            <input type="submit" name="add_mapping" value="Map Customer to Deal">
        </form>
    </div>
</div>

<div class="container">
    <div class="deals_table">
        <div class="card_row">
            <p>Deal Name</p>
            <p>Coupon Code</p>
            <p>Customer Name</p>
            <p>Customer Email</p>
            <p>Mapped Date</p>
            <p>Action</p>
        </div>
        
        <?php
        $query = mysqli_query($connect, "SELECT dcm.*, d.deal_name, d.coupon_code 
                                        FROM deal_customer_mapping dcm 
                                        JOIN deals d ON dcm.deal_id = d.id 
                                        ORDER BY dcm.id DESC");

        if(mysqli_num_rows($query) > 0){
            while($row = mysqli_fetch_array($query)){
                // Get customer name separately to avoid collation issues - using user_details
                $customer_query = mysqli_query($connect, "SELECT name FROM user_details WHERE email = '".$row['customer_email']."' AND role='CUSTOMER' LIMIT 1");
                $customer_name = 'Unknown';
                if($customer_query && mysqli_num_rows($customer_query) > 0) {
                    $customer_data = mysqli_fetch_array($customer_query);
                    $customer_name = $customer_data['user_name'];
                }
                
                echo '<div class="card_row2">';
                echo '<p>' . htmlspecialchars($row['deal_name']) . '</p>';
                echo '<p><strong>' . htmlspecialchars($row['coupon_code']) . '</strong></p>';
                echo '<p>' . htmlspecialchars($customer_name) . '</p>';
                echo '<p>' . htmlspecialchars($row['customer_email']) . '</p>';
                echo '<p>' . date('d-m-Y', strtotime($row['created_date'])) . '</p>';
                echo '<p>';
                echo '<a href="?remove_mapping=' . $row['id'] . '" onclick="return confirm(\'Remove this mapping?\')" title="Remove Mapping"><i class="fa fa-trash"></i></a>';
                echo '</p>';
                echo '</div>';
            }
        } else {
            echo '<div class="card_row2"><p colspan="6">No mappings found</p></div>';
        }
        ?>
    </div>
</div>







