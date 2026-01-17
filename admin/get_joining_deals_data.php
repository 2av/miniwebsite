<?php
/**
 * Get Joining Deals Data for Management Interface
 * Returns paginated joining deals data with filters
 */

require('connect_ajax.php');

header('Content-Type: application/json');

try {
    // Get filter parameters
    $status_filter = $_POST['status'] ?? '';
    $deal_filter = $_POST['deal'] ?? '';
    $payment_filter = $_POST['payment'] ?? '';
    $search = $_POST['search'] ?? '';
    $page = intval($_POST['page'] ?? 1);
    $limit = 15;
    $offset = ($page - 1) * $limit;
    
    // Build WHERE conditions
    $where_conditions = array();
    
    if(!empty($status_filter)) {
        switch($status_filter) {
            case 'ACTIVE':
                $where_conditions[] = "ujdm.mapping_status = 'ACTIVE' AND (ujdm.expiry_date IS NULL OR ujdm.expiry_date > NOW()) AND ujdm.payment_status = 'PAID'";
                break;
            case 'PENDING_PAYMENT':
                $where_conditions[] = "ujdm.mapping_status = 'ACTIVE' AND ujdm.payment_status = 'PENDING' AND jd.total_fees > 0";
                break;
            case 'EXPIRED':
                $where_conditions[] = "ujdm.expiry_date < NOW()";
                break;
            case 'PAYMENT_FAILED':
                $where_conditions[] = "ujdm.payment_status = 'FAILED'";
                break;
        }
    }
    
    if(!empty($deal_filter)) {
        $where_conditions[] = "ujdm.deal_code = '" . mysqli_real_escape_string($connect, $deal_filter) . "'";
    }
    
    if(!empty($payment_filter)) {
        $where_conditions[] = "ujdm.payment_status = '" . mysqli_real_escape_string($connect, $payment_filter) . "'";
    }
    
    if(!empty($search)) {
        $search_escaped = mysqli_real_escape_string($connect, $search);
        $where_conditions[] = "(ujdm.user_email LIKE '%$search_escaped%' OR jd.deal_name LIKE '%$search_escaped%')";
    }
    
    $where_clause = '';
    if(!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Main query with deal status calculation
    $query = "SELECT ujdm.*, jd.deal_name, jd.deal_code, jd.total_fees, jd.commission_amount,
        CASE 
            WHEN ujdm.expiry_date < NOW() THEN 'EXPIRED'
            WHEN ujdm.payment_status = 'PENDING' AND jd.total_fees > 0 THEN 'PENDING_PAYMENT'
            WHEN ujdm.payment_status = 'PAID' THEN 'ACTIVE'
            WHEN ujdm.payment_status = 'FAILED' THEN 'PAYMENT_FAILED'
            ELSE 'ACTIVE'
        END as deal_status,
        DATEDIFF(ujdm.expiry_date, NOW()) as days_remaining
        FROM user_joining_deals_mapping ujdm
        JOIN joining_deals jd ON ujdm.joining_deal_id = jd.id
        $where_clause
        ORDER BY ujdm.created_at DESC
        LIMIT $offset, $limit";
    
    $result = mysqli_query($connect, $query);
    
    if(!$result) {
        throw new Exception('Database query failed: ' . mysqli_error($connect));
    }
    
    $deals = array();
    while($row = mysqli_fetch_assoc($result)) {
        $deals[] = $row;
    }
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total 
        FROM user_joining_deals_mapping ujdm
        JOIN joining_deals jd ON ujdm.joining_deal_id = jd.id
        $where_clause";
    
    $count_result = mysqli_query($connect, $count_query);
    $total_records = mysqli_fetch_assoc($count_result)['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'deals' => $deals,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $total_pages,
            'totalRecords' => $total_records,
            'limit' => $limit
        ]
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>



