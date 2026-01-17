<?php
require('connect.php');

header('Content-Type: application/json');

$deal_id = isset($_GET['id']) ? mysqli_real_escape_string($connect, $_GET['id']) : '';

if(empty($deal_id)) {
    echo json_encode(['error' => 'Missing deal ID']);
    exit;
}

$query = mysqli_query($connect, "SELECT * FROM joining_deals WHERE id='$deal_id' LIMIT 1");

if($query && mysqli_num_rows($query) > 0) {
    $deal = mysqli_fetch_array($query);
    echo json_encode($deal);
} else {
    echo json_encode(['error' => 'Deal not found']);
}
?>
