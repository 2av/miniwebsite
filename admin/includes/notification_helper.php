<?php
// Notification helper functions for admin panel

/**
 * Create a new notification
 */
function createNotification($type, $title, $message, $user_email = null, $user_type = null, $related_id = null, $priority = 'medium') {
    global $connect;
    
    $type = mysqli_real_escape_string($connect, $type);
    $title = mysqli_real_escape_string($connect, $title);
    $message = mysqli_real_escape_string($connect, $message);
    $user_email = $user_email ? mysqli_real_escape_string($connect, $user_email) : null;
    $user_type = $user_type ? mysqli_real_escape_string($connect, $user_type) : null;
    $related_id = $related_id ? intval($related_id) : null;
    $priority = mysqli_real_escape_string($connect, $priority);
    
    $query = "INSERT INTO admin_notifications (type, title, message, user_email, user_type, related_id, priority) 
              VALUES ('$type', '$title', '$message', " . 
              ($user_email ? "'$user_email'" : "NULL") . ", " .
              ($user_type ? "'$user_type'" : "NULL") . ", " .
              ($related_id ? $related_id : "NULL") . ", " .
              "'$priority')";
    
    return mysqli_query($connect, $query);
}

/**
 * Get unread notifications count
 */
function getUnreadNotificationsCount() {
    global $connect;
    
    $query = "SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0";
    $result = mysqli_query($connect, $query);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['count'];
    }
    
    return 0;
}

/**
 * Get recent notifications
 */
function getRecentNotifications($limit = 10, $include_read = false) {
    global $connect;
    
    $where_clause = $include_read ? "" : "WHERE is_read = 0";
    $query = "SELECT * FROM admin_notifications $where_clause ORDER BY created_at DESC LIMIT $limit";
    $result = mysqli_query($connect, $query);
    
    $notifications = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
    }
    
    return $notifications;
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($notification_id) {
    global $connect;
    
    $notification_id = intval($notification_id);
    $query = "UPDATE admin_notifications SET is_read = 1 WHERE id = $notification_id";
    
    return mysqli_query($connect, $query);
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsAsRead() {
    global $connect;
    
    $query = "UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0";
    
    return mysqli_query($connect, $query);
}

/**
 * Get notification icon based on type
 */
function getNotificationIcon($type) {
    $icons = [
        'account_creation' => 'fas fa-user-plus',
        'payment' => 'fas fa-credit-card',
        'verification' => 'fas fa-check-circle',
        'order' => 'fas fa-shopping-cart',
        'franchisee' => 'fas fa-user-tie',
        'general' => 'fas fa-bell'
    ];
    
    return $icons[$type] ?? 'fas fa-bell';
}

/**
 * Get notification color based on priority
 */
function getNotificationColor($priority) {
    $colors = [
        'low' => 'text-muted',
        'medium' => 'text-primary',
        'high' => 'text-danger'
    ];
    
    return $colors[$priority] ?? 'text-primary';
}

/**
 * Format notification time
 */
function formatNotificationTime($timestamp) {
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}
?>



