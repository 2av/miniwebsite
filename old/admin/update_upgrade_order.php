<?php
/**
 * Update Upgrade Order
 * Updates the upgrade_order for joining deals
 */

require('connect_ajax.php');

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if(!isset($input['orders']) || !is_array($input['orders'])) {
        throw new Exception('Invalid orders data');
    }
    
    $orders = $input['orders'];
    
    if(empty($orders)) {
        throw new Exception('No orders provided');
    }
    
    // Start transaction
    mysqli_autocommit($connect, false);
    
    $success_count = 0;
    $errors = [];
    
    foreach($orders as $order) {
        if(!isset($order['deal_id']) || !isset($order['upgrade_order'])) {
            $errors[] = 'Invalid order data: missing deal_id or upgrade_order';
            continue;
        }
        
        $deal_id = intval($order['deal_id']);
        $upgrade_order = intval($order['upgrade_order']);
        
        if($deal_id <= 0 || $upgrade_order <= 0) {
            $errors[] = "Invalid values for deal_id: $deal_id, upgrade_order: $upgrade_order";
            continue;
        }
        
        $update_query = "UPDATE joining_deals 
                        SET upgrade_order = $upgrade_order 
                        WHERE id = $deal_id AND is_active = 'YES'";
        
        if(mysqli_query($connect, $update_query)) {
            $success_count++;
        } else {
            $errors[] = "Failed to update deal_id $deal_id: " . mysqli_error($connect);
        }
    }
    
    if($success_count === count($orders)) {
        // All updates successful
        mysqli_commit($connect);
        echo json_encode([
            'success' => true,
            'message' => "Successfully updated upgrade order for $success_count deals",
            'updated_count' => $success_count
        ]);
    } else {
        // Some updates failed
        mysqli_rollback($connect);
        echo json_encode([
            'success' => false,
            'message' => "Updated $success_count out of " . count($orders) . " deals",
            'errors' => $errors,
            'updated_count' => $success_count
        ]);
    }
    
} catch(Exception $e) {
    // Rollback transaction
    mysqli_rollback($connect);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'updated_count' => 0
    ]);
} finally {
    // Restore autocommit
    mysqli_autocommit($connect, true);
}
?>
