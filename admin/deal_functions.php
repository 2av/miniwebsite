<?php
function canCustomerUseDeal($customer_email, $deal_id, $connect) {
    // Check if customer is specifically mapped to this deal
    $mapping_query = mysqli_query($connect, "SELECT * FROM deal_customer_mapping WHERE deal_id='$deal_id' AND customer_email='$customer_email'");
    
    // If mapping exists, customer can use the deal
    if(mysqli_num_rows($mapping_query) > 0) {
        return true;
    }
    
    // If no mappings exist for this deal, it's available to all customers
    $any_mapping_query = mysqli_query($connect, "SELECT * FROM deal_customer_mapping WHERE deal_id='$deal_id'");
    if(mysqli_num_rows($any_mapping_query) == 0) {
        return true;
    }
    
    // Deal has mappings but customer is not included
    return false;
}
?>



