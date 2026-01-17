<?php
require_once '../connect.php';
require_once '../includes/notification_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $unread_count = getUnreadNotificationsCount();
    
    echo json_encode([
        'success' => true, 
        'unread_count' => $unread_count
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>



