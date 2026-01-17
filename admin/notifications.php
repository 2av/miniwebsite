<?php
require_once('connect.php');
require_once('includes/notification_helper.php');

// Handle actions BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_read':
                $notification_id = intval($_POST['notification_id']);
                markNotificationAsRead($notification_id);
                break;
            case 'mark_all_read':
                markAllNotificationsAsRead();
                break;
            case 'delete':
                $notification_id = intval($_POST['notification_id']);
                $delete_query = "DELETE FROM admin_notifications WHERE id = $notification_id";
                mysqli_query($connect, $delete_query);
                break;
        }
        
        // Redirect to refresh the page
        header('Location: notifications.php');
        exit();
    }
}

// Include header AFTER handling redirects
include_once('header.php');

// Get notifications with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count
$count_query = "SELECT COUNT(*) as total FROM admin_notifications";
$count_result = mysqli_query($connect, $count_query);
$total_notifications = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_notifications / $limit);

// Get notifications
$notifications_query = "SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$notifications_result = mysqli_query($connect, $notifications_query);
$notifications = [];
if ($notifications_result) {
    while ($row = mysqli_fetch_assoc($notifications_result)) {
        $notifications[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Admin Panel</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 20px;
        }
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .notification-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .notification-card.unread {
            border-left: 4px solid #007bff;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .notification-icon.account_creation { background: linear-gradient(135deg, #28a745, #20c997); }
        .notification-icon.payment { background: linear-gradient(135deg, #ffc107, #fd7e14); }
        .notification-icon.verification { background: linear-gradient(135deg, #17a2b8, #6f42c1); }
        .notification-icon.order { background: linear-gradient(135deg, #dc3545, #e83e8c); }
        .notification-icon.franchisee { background: linear-gradient(135deg, #6c757d, #495057); }
        .notification-icon.general { background: linear-gradient(135deg, #6f42c1, #e83e8c); }
        
        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .priority-low { background: #e9ecef; color: #6c757d; }
        .priority-medium { background: #cce7ff; color: #0056b3; }
        .priority-high { background: #f8d7da; color: #721c24; }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .pagination-wrapper {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="d-flex align-items-center mb-2">
                        <a href="index.php" class="btn btn-outline-secondary me-3">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                        <h2 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h2>
                    </div>
                    <p class="text-muted mb-0">Manage and view all system notifications</p>
                </div>
                <div class="d-flex gap-2">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-double me-2"></i>Mark All as Read
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <div class="row">
                <div class="col-md-3">
                    <label for="type_filter" class="form-label">Type</label>
                    <select class="form-select" id="type_filter">
                        <option value="">All Types</option>
                        <option value="account_creation">Account Creation</option>
                        <option value="payment">Payment</option>
                        <option value="verification">Verification</option>
                        <option value="order">Order</option>
                        <option value="franchisee">Franchisee</option>
                        <option value="general">General</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="priority_filter" class="form-label">Priority</label>
                    <select class="form-select" id="priority_filter">
                        <option value="">All Priorities</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status_filter" class="form-label">Status</label>
                    <select class="form-select" id="status_filter">
                        <option value="">All Status</option>
                        <option value="unread">Unread</option>
                        <option value="read">Read</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="search_filter" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search_filter" placeholder="Search notifications...">
                </div>
            </div>
        </div>

        <!-- Notifications List -->
        <?php if (empty($notifications)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fs-1 text-muted mb-3"></i>
                <h4>No Notifications</h4>
                <p class="text-muted">There are no notifications to display.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-card p-4 <?php echo $notification['is_read'] ? '' : 'unread'; ?>" 
                     data-type="<?php echo $notification['type']; ?>"
                     data-priority="<?php echo $notification['priority']; ?>"
                     data-status="<?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                    <div class="row">
                        <div class="col-md-1">
                            <div class="notification-icon <?php echo $notification['type']; ?> text-white">
                                <i class="<?php echo getNotificationIcon($notification['type']); ?>"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-1 <?php echo $notification['is_read'] ? 'text-muted' : 'fw-bold'; ?>">
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </h5>
                                <div class="d-flex gap-2">
                                    <span class="priority-badge priority-<?php echo $notification['priority']; ?>">
                                        <?php echo ucfirst($notification['priority']); ?>
                                    </span>
                                    <?php if (!$notification['is_read']): ?>
                                        <span class="badge bg-primary">New</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <div class="d-flex gap-3 text-muted small">
                                <span><i class="fas fa-clock me-1"></i><?php echo formatNotificationTime($notification['created_at']); ?></span>
                                <span><i class="fas fa-tag me-1"></i><?php echo ucfirst(str_replace('_', ' ', $notification['type'])); ?></span>
                                <?php if ($notification['user_email']): ?>
                                    <span><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($notification['user_email']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-3 text-end">
                            <div class="btn-group-vertical" role="group">
                                <?php if (!$notification['is_read']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success mb-1">
                                            <i class="fas fa-check me-1"></i>Mark Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this notification?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <nav aria-label="Notifications pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="text-center text-muted">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                    (<?php echo $total_notifications; ?> total notifications)
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Filter functionality
        function filterNotifications() {
            const typeFilter = document.getElementById('type_filter').value;
            const priorityFilter = document.getElementById('priority_filter').value;
            const statusFilter = document.getElementById('status_filter').value;
            const searchFilter = document.getElementById('search_filter').value.toLowerCase();
            
            document.querySelectorAll('.notification-card').forEach(card => {
                let show = true;
                
                // Type filter
                if (typeFilter && card.dataset.type !== typeFilter) {
                    show = false;
                }
                
                // Priority filter
                if (priorityFilter && card.dataset.priority !== priorityFilter) {
                    show = false;
                }
                
                // Status filter
                if (statusFilter && card.dataset.status !== statusFilter) {
                    show = false;
                }
                
                // Search filter
                if (searchFilter) {
                    const text = card.textContent.toLowerCase();
                    if (!text.includes(searchFilter)) {
                        show = false;
                    }
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }
        
        // Add event listeners to filters
        document.getElementById('type_filter').addEventListener('change', filterNotifications);
        document.getElementById('priority_filter').addEventListener('change', filterNotifications);
        document.getElementById('status_filter').addEventListener('change', filterNotifications);
        document.getElementById('search_filter').addEventListener('input', filterNotifications);
        
        // Auto-refresh notifications every 60 seconds
        setInterval(() => {
            location.reload();
        }, 60000);
    </script>
</body>
</html>



