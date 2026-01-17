<?php
/**
 * Get Deals for Upgrade Order Management
 * Returns all active joining deals with their current upgrade order
 */

require('connect_ajax.php');

header('Content-Type: application/json');

try {
    $query = "SELECT id, deal_name, deal_code, total_fees, upgrade_order 
              FROM joining_deals 
              WHERE is_active = 'YES' 
              ORDER BY upgrade_order ASC";
    
    $result = mysqli_query($connect, $query);
    
    if(!$result) {
        throw new Exception('Database query failed: ' . mysqli_error($connect));
    }
    
    $deals = [];
    while($row = mysqli_fetch_assoc($result)) {
        $deals[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'deals' => $deals,
        'message' => 'Deals loaded successfully'
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'deals' => []
    ]);
}
?>
