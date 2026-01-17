<?php
require_once '../connect.php';
require_once '../includes/notification_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = markAllNotificationsAsRead();
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark notifications as read']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>



