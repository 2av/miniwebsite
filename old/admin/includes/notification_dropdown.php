<?php
require_once __DIR__ . '/notification_helper.php';

// Get notification data
$unread_count = getUnreadNotificationsCount();
$recent_notifications = getRecentNotifications(5, true);
?>

<!-- Notification Bell -->
<div class="dropdown notification-dropdown">
    <button class="btn btn-link position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="notification-bell" style="color: #007bff; font-size: 20px; font-weight: bold;">🔔</span>
        <?php if ($unread_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
            </span>
        <?php endif; ?>
    </button>
    
    <div class="dropdown-menu dropdown-menu-end notification-menu" aria-labelledby="notificationDropdown">
        <div class="notification-header d-flex justify-content-between align-items-center p-3 border-bottom">
            <h6 class="mb-0">Notifications</h6>
            <?php if ($unread_count > 0): ?>
                <button class="btn btn-sm btn-outline-primary" onclick="markAllAsRead()">
                    Mark all as read
                </button>
            <?php endif; ?>
        </div>
        
        <div class="notification-list" style="max-height: 400px; overflow-y: auto;">
            <?php if (empty($recent_notifications)): ?>
                <div class="text-center p-4 text-muted">
                    <i class="fas fa-bell-slash fs-2 mb-2"></i>
                    <p class="mb-0">No notifications yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_notifications as $notification): ?>
                    <div class="notification-item p-3 border-bottom <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>" 
                         data-notification-id="<?php echo $notification['id']; ?>">
                        <div class="d-flex align-items-start">
                            <div class="notification-icon me-3">
                                <i class="<?php echo getNotificationIcon($notification['type']); ?> <?php echo getNotificationColor($notification['priority']); ?> fs-5"></i>
                            </div>
                            <div class="notification-content flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="notification-title mb-1 <?php echo $notification['is_read'] ? 'text-muted' : 'fw-bold'; ?>">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo formatNotificationTime($notification['created_at']); ?>
                                    </small>
                                </div>
                                <p class="notification-message mb-1 text-muted small">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </p>
                                <?php if ($notification['user_email']): ?>
                                    <small class="text-info">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($notification['user_email']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <?php if (!$notification['is_read']): ?>
                                <div class="notification-status ms-2">
                                    <span class="badge bg-primary rounded-pill">New</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="notification-footer p-3 border-top text-center">
            <a href="notifications.php" class="btn btn-outline-secondary btn-sm">
                View All Notifications
            </a>
        </div>
    </div>
</div>

<style>
/* Ensure notification bell icon is always visible */
.notification-dropdown .btn-link .notification-bell {
    color: #007bff !important;
    font-size: 20px !important;
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
    cursor: pointer;
    transition: all 0.3s ease;
}

.notification-dropdown .btn-link .notification-bell:hover {
    color: #0056b3 !important;
    transform: scale(1.1);
}



.notification-dropdown .dropdown-menu {
    width: 400px;
    max-width: 90vw;
}

.notification-item {
    transition: background-color 0.2s ease;
    cursor: pointer;
}

.notification-item:hover {
    background-color: #f8f9fa !important;
}

.notification-item.unread {
    background-color: #e3f2fd;
}

.notification-title {
    font-size: 0.9rem;
    line-height: 1.2;
}

.notification-message {
    font-size: 0.8rem;
    line-height: 1.3;
}

.notification-icon {
    width: 24px;
    text-align: center;
}

.notification-status .badge {
    font-size: 0.7rem;
}

.notification-header h6 {
    font-size: 1rem;
    font-weight: 600;
}

.notification-footer .btn {
    font-size: 0.8rem;
}

/* Animation for new notifications */
@keyframes notificationPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.notification-item.unread {
    animation: notificationPulse 2s infinite;
}
</style>

<script>
// Ensure notification icon is always visible
document.addEventListener('DOMContentLoaded', function() {
    const notificationIcon = document.querySelector('.notification-dropdown .btn-link i');
    if (notificationIcon) {
        // Check if Font Awesome is loaded
        if (!notificationIcon.classList.contains('fa-bell') || getComputedStyle(notificationIcon, '::before').content === 'none') {
            // Fallback to emoji if Font Awesome fails
            notificationIcon.innerHTML = '🔔';
            notificationIcon.style.fontFamily = 'Arial, sans-serif';
            notificationIcon.style.fontSize = '1.25rem';
        }
        
        // Ensure icon is visible
        notificationIcon.style.display = 'inline-block';
        notificationIcon.style.visibility = 'visible';
        notificationIcon.style.opacity = '1';
        notificationIcon.style.color = '#007bff';
    }
});

// Mark notification as read when clicked
document.querySelectorAll('.notification-item').forEach(item => {
    item.addEventListener('click', function() {
        const notificationId = this.dataset.notificationId;
        markNotificationAsRead(notificationId);
        
        // Update UI
        this.classList.remove('bg-light');
        this.classList.add('text-muted');
        this.querySelector('.notification-title').classList.remove('fw-bold');
        this.querySelector('.notification-title').classList.add('text-muted');
        
        // Remove new badge if exists
        const statusBadge = this.querySelector('.notification-status');
        if (statusBadge) {
            statusBadge.remove();
        }
        
        // Update unread count
        updateUnreadCount();
    });
});

// Mark all notifications as read
function markAllAsRead() {
    fetch('ajax/mark_all_notifications_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            document.querySelectorAll('.notification-item').forEach(item => {
                item.classList.remove('bg-light');
                item.classList.add('text-muted');
                item.querySelector('.notification-title').classList.remove('fw-bold');
                item.querySelector('.notification-title').classList.add('text-muted');
                
                const statusBadge = item.querySelector('.notification-status');
                if (statusBadge) {
                    statusBadge.remove();
                }
            });
            
            // Update unread count
            updateUnreadCount();
            
            // Hide mark all as read button
            const markAllBtn = document.querySelector('.notification-header .btn');
            if (markAllBtn) {
                markAllBtn.style.display = 'none';
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Update unread count
function updateUnreadCount() {
    const badge = document.querySelector('.notification-dropdown .badge');
    if (badge) {
        const currentCount = parseInt(badge.textContent);
        if (currentCount > 1) {
            badge.textContent = currentCount - 1;
        } else {
            badge.remove();
        }
    }
}

// Auto-refresh notifications every 30 seconds
setInterval(() => {
    fetch('ajax/get_notifications_count.php')
    .then(response => response.json())
    .then(data => {
        const badge = document.querySelector('.notification-dropdown .badge');
        if (data.unread_count > 0) {
            if (badge) {
                badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
            } else {
                const newBadge = document.createElement('span');
                newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                newBadge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                document.querySelector('.notification-dropdown .btn').appendChild(newBadge);
            }
        } else if (badge) {
            badge.remove();
        }
    })
    .catch(error => console.error('Error:', error));
}, 30000);
</script>
