<?php
require_once '../connect.php';
require_once '../includes/notification_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $notification_id = $input['notification_id'] ?? null;
    
    if ($notification_id) {
        $success = markNotificationAsRead($notification_id);
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
