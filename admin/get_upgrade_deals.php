<?php
/**
 * Get Available Upgrade Deals
 * Returns deals that are higher in hierarchy than the current deal
 * Uses database-driven upgrade_order column
 */

require('connect_ajax.php');

header('Content-Type: application/json');

try {
    $current_deal_code = $_POST['current_deal_code'] ?? '';
    
    if(empty($current_deal_code)) {
        throw new Exception('Current deal code is required');
    }
    
    // Get current deal's upgrade order
    $current_deal_query = mysqli_query($connect, "SELECT upgrade_order FROM joining_deals 
        WHERE deal_code = '" . mysqli_real_escape_string($connect, $current_deal_code) . "' 
        AND is_active = 'YES' LIMIT 1");
    
    if(!$current_deal_query || mysqli_num_rows($current_deal_query) == 0) {
        throw new Exception('Current deal not found or inactive');
    }
    
    $current_deal = mysqli_fetch_array($current_deal_query);
    $current_order = intval($current_deal['upgrade_order']);
    
    // Get all deals that are higher in hierarchy (higher upgrade_order)
    $query = "SELECT * FROM joining_deals 
              WHERE upgrade_order > $current_order 
              AND is_active = 'YES' 
              ORDER BY upgrade_order ASC";
    
    $result = mysqli_query($connect, $query);
    
    if(!$result) {
        throw new Exception('Database query failed: ' . mysqli_error($connect));
    }
    
    $deals = [];
    while($row = mysqli_fetch_assoc($result)) {
        // Get mapped deals information
        $mapped_deals = [];
        
        // Get MW deal name if mapped
        if(!empty($row['mw_deal_id']) && $row['mw_deal_id'] > 0) {
            $mw_deal_query = mysqli_query($connect, "SELECT deal_name FROM deals WHERE id = " . intval($row['mw_deal_id']) . " LIMIT 1");
            if($mw_deal_query && mysqli_num_rows($mw_deal_query) > 0) {
                $mw_deal = mysqli_fetch_array($mw_deal_query);
                $mapped_deals['mw'] = [
                    'id' => intval($row['mw_deal_id']),
                    'name' => $mw_deal['deal_name']
                ];
            }
        }
        
        // Get Franchise deal name if mapped
        if(!empty($row['franchise_deal_id']) && $row['franchise_deal_id'] > 0) {
            $franchise_deal_query = mysqli_query($connect, "SELECT deal_name FROM deals WHERE id = " . intval($row['franchise_deal_id']) . " LIMIT 1");
            if($franchise_deal_query && mysqli_num_rows($franchise_deal_query) > 0) {
                $franchise_deal = mysqli_fetch_array($franchise_deal_query);
                $mapped_deals['franchise'] = [
                    'id' => intval($row['franchise_deal_id']),
                    'name' => $franchise_deal['deal_name']
                ];
            }
        }
        
        $row['mapped_deals'] = $mapped_deals;
        $deals[] = $row;
    }
    
    if(empty($deals)) {
        echo json_encode([
            'success' => true,
            'deals' => [],
            'message' => 'No upgrade options available for this deal'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'deals' => $deals,
        'message' => 'Upgrade options loaded successfully',
        'current_order' => $current_order
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'deals' => []
    ]);
}
?>



