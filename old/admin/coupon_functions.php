<?php
function validateCoupon($coupon_code, $connect, $service_type) {
    $coupon_code = strtoupper(mysqli_real_escape_string($connect, $coupon_code));
    
    // Debug logging
    error_log("Validating coupon code: " . $coupon_code . " for service type: " . $service_type);
    
    // Map service types to plan_type values
    $plan_type = '';
    if ($service_type === 'franchise_registration') {
        $plan_type = 'Franchise';
    } elseif ($service_type === 'card_payment' || $service_type === 'MiniWebsite') {
        $plan_type = 'MiniWebsite';
    }
    
    // First try with specific plan type (only if plan_type is not empty)
    if (!empty($plan_type)) {
        $query = mysqli_query($connect, "SELECT * FROM deals WHERE coupon_code='$coupon_code' AND deal_status='Active' AND plan_type='$plan_type'");
        error_log("Searching for coupon with plan_type: " . $plan_type);
    } else {
        // If no plan_type mapping, start with general coupons
        $query = mysqli_query($connect, "SELECT * FROM deals WHERE coupon_code='$coupon_code' AND deal_status='Active' AND (plan_type IS NULL OR plan_type = '' OR plan_type = 'General')");
        error_log("Searching for general coupons (no specific plan_type mapping)");
    }
    
    // If no results, try without plan_type restriction (general coupons)
    if (mysqli_num_rows($query) == 0) {
        error_log("No specific deals found, trying all active coupons");
        $query = mysqli_query($connect, "SELECT * FROM deals WHERE coupon_code='$coupon_code' AND deal_status='Active'");
    }
    
    if (!$query) {
        error_log("Database query failed: " . mysqli_error($connect));
        return ['valid' => false, 'message' => 'Database error occurred'];
    }
    
    if(mysqli_num_rows($query) == 0) {
        error_log("No coupon found for code: " . $coupon_code);
        return ['valid' => false, 'message' => 'Invalid coupon code'];
    }
    
    $deal = mysqli_fetch_array($query);
    error_log("Found deal: " . json_encode($deal));
    
    // Check validity date with detailed debugging
    $validity_timestamp = strtotime($deal['validity_date']);
    $current_timestamp = time();
    
    // Convert to date-only comparison (ignore time component)
    $validity_date_only = date('Y-m-d', $validity_timestamp);
    $current_date_only = date('Y-m-d', $current_timestamp);
    
    error_log("Validity date from DB: " . $deal['validity_date']);
    error_log("Validity timestamp: " . $validity_timestamp);
    error_log("Current timestamp: " . $current_timestamp);
    error_log("Validity date only: " . $validity_date_only);
    error_log("Current date only: " . $current_date_only);
    error_log("Current full date: " . date('Y-m-d H:i:s', $current_timestamp));
    error_log("Validity date parsed: " . date('Y-m-d H:i:s', $validity_timestamp));
    
    if($validity_timestamp === false) {
        error_log("Failed to parse validity date: " . $deal['validity_date']);
        return ['valid' => false, 'message' => 'Invalid coupon date format'];
    }
    
    // Compare only the date part, not the time
    if($validity_date_only < $current_date_only) {
        error_log("Coupon expired. Validity date: " . $deal['validity_date'] . " (date: " . $validity_date_only . ")");
        error_log("Current date: " . $current_date_only);
        return ['valid' => false, 'message' => 'Coupon has expired'];
    }
    
    // Check usage limit (skip for franchise registration)
    if($service_type !== 'franchise_registration' && $deal['max_usage'] > 0 && $deal['current_usage'] >= $deal['max_usage']) {
        error_log("Usage limit reached. Current: " . $deal['current_usage'] . ", Max: " . $deal['max_usage']);
        return ['valid' => false, 'message' => 'Coupon usage limit reached'];
    }
    
    // For franchise registration, log usage but don't block
    if($service_type === 'franchise_registration') {
        error_log("Franchise registration coupon - skipping usage limit check. Current: " . $deal['current_usage'] . ", Max: " . $deal['max_usage']);
    }
    
    error_log("Coupon validation successful");
    return [
        'valid' => true,
        'deal' => $deal,
        'message' => 'Coupon is valid'
    ];
}

function applyCoupon($coupon_code, $connect, $service_type = '') {
    $coupon_code = strtoupper(mysqli_real_escape_string($connect, $coupon_code));
    
    // Debug logging
    error_log("Applying coupon code: " . $coupon_code . " for service type: " . $service_type);
    
    // Increment usage count
    $update = mysqli_query($connect, "UPDATE deals SET current_usage = current_usage + 1 WHERE coupon_code='$coupon_code'");
    
    if ($update) {
        error_log("Coupon usage count updated successfully");
    } else {
        error_log("Failed to update coupon usage count: " . mysqli_error($connect));
    }
    
    return $update;
}

function getCouponDiscount($amount, $deal) {
    $discount = 0;
    
    error_log("Calculating discount for amount: " . $amount);
    error_log("Deal details: " . json_encode($deal));
    
    if($deal['discount_amount'] > 0) {
        $discount = $deal['discount_amount'];
        error_log("Using fixed discount amount: " . $discount);
    } else if($deal['discount_percentage'] > 0) {
        $discount = ($amount * $deal['discount_percentage']) / 100;
        error_log("Using percentage discount: " . $deal['discount_percentage'] . "%, calculated: " . $discount);
    }
    
    error_log("Final discount calculated: " . $discount);
    return $discount;
}

// Debug function to check available coupons
function debugCoupons($connect, $coupon_code = '') {
    $coupon_code = strtoupper(mysqli_real_escape_string($connect, $coupon_code));
    
    if (!empty($coupon_code)) {
        $query = mysqli_query($connect, "SELECT coupon_code, plan_type, deal_status, validity_date, max_usage, current_usage FROM deals WHERE coupon_code='$coupon_code'");
    } else {
        $query = mysqli_query($connect, "SELECT coupon_code, plan_type, deal_status, validity_date, max_usage, current_usage FROM deals WHERE deal_status='Active' LIMIT 10");
    }
    
    $coupons = [];
    while ($row = mysqli_fetch_assoc($query)) {
        // Add parsed date info for debugging
        $row['validity_timestamp'] = strtotime($row['validity_date']);
        $row['validity_parsed'] = $row['validity_timestamp'] ? date('Y-m-d H:i:s', $row['validity_timestamp']) : 'INVALID';
        $row['current_time'] = date('Y-m-d H:i:s');
        $row['is_expired'] = ($row['validity_timestamp'] && $row['validity_timestamp'] < time()) ? 'YES' : 'NO';
        $coupons[] = $row;
    }
    
    error_log("Available coupons with date analysis: " . json_encode($coupons));
    return $coupons;
}
?>