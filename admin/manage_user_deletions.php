<?php
require_once(__DIR__ . '/../app/config/database.php');

// Handle AJAX actions BEFORE including header
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// If it's an AJAX request, handle it and exit before outputting HTML
if ($action === 'bulk_soft_delete' && isset($_POST['userIds'])) {
    $userIds = $_POST['userIds'];
    $deletedCount = 0;
    $errors = [];
    
    foreach ($userIds as $id) {
        $id = intval($id);
        if ($id <= 0) continue;
        
        $updateQuery = "UPDATE user_details SET isDeleted = 1 WHERE id = $id";
        $result = mysqli_query($connect, $updateQuery);
        
        if ($result) {
            $deletedCount++;
        } else {
            $dbError = mysqli_error($connect);
            // Check if column doesn't exist
            if (strpos($dbError, "Unknown column") !== false) {
                $errors[] = "isDeleted column not found. Run /admin/add_isdeleted_column.php first";
            } else {
                $errors[] = "User ID $id: " . $dbError;
            }
        }
    }
    
    $response = [
        'success' => $deletedCount > 0,
        'message' => $deletedCount > 0 ? $deletedCount . " user(s) marked as deleted" : "No users deleted"
    ];
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
        if ($deletedCount == 0) {
            $response['success'] = false;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// AJAX: Bulk restore users
if ($action === 'bulk_restore' && isset($_POST['userIds'])) {
    $userIds = $_POST['userIds'];
    $restoredCount = 0;
    $errors = [];
    
    foreach ($userIds as $id) {
        $id = intval($id);
        if ($id <= 0) continue;
        
        $updateQuery = "UPDATE user_details SET isDeleted = 0 WHERE id = $id";
        $result = mysqli_query($connect, $updateQuery);
        
        if ($result) {
            $restoredCount++;
        } else {
            $dbError = mysqli_error($connect);
            // Check if column doesn't exist
            if (strpos($dbError, "Unknown column") !== false) {
                $errors[] = "isDeleted column not found. Run /admin/add_isdeleted_column.php first";
            } else {
                $errors[] = "User ID $id: " . $dbError;
            }
        }
    }
    
    $response = [
        'success' => $restoredCount > 0,
        'message' => $restoredCount > 0 ? $restoredCount . " user(s) restored" : "No users restored"
    ];
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
        if ($restoredCount == 0) {
            $response['success'] = false;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Now include header for page display
require('header.php');

// Get filter parameters
$filterRole = isset($_GET['role']) ? $_GET['role'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = [];

if ($filterStatus === 'deleted') {
    $whereConditions[] = "isDeleted = 1";
} elseif ($filterStatus === 'active') {
    $whereConditions[] = "isDeleted = 0";
}

if ($filterRole && in_array($filterRole, ['CUSTOMER', 'FRANCHISEE', 'TEAM', 'ADMIN'])) {
    $whereConditions[] = "role = '" . $connect->real_escape_string($filterRole) . "'";
}

if ($searchTerm) {
    $escaped = $connect->real_escape_string($searchTerm);
    $whereConditions[] = "(name LIKE '%$escaped%' OR email LIKE '%$escaped%')";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM user_details $whereClause";
$countResult = mysqli_query($connect, $countQuery);
$countRow = mysqli_fetch_assoc($countResult);
$totalUsers = $countRow['total'];
$totalPages = ceil($totalUsers / $perPage);

// Get users
$usersQuery = "SELECT id, email, name, role, status, isDeleted, created_at, updated_at 
               FROM user_details 
               $whereClause 
               ORDER BY updated_at DESC 
               LIMIT $offset, $perPage";
$usersResult = mysqli_query($connect, $usersQuery);
$users = mysqli_fetch_all($usersResult, MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage User Deletions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 20px;
        }
        .main-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .header-section h1 {
            color: #333;
            margin: 0;
            font-size: 28px;
        }
        .back-link {
            color: #6c757d;
            text-decoration: none;
        }
        .back-link:hover {
            color: #333;
        }
        .filters-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
        }
        .filter-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-item {
            flex: 1;
            min-width: 150px;
        }
        .filter-item label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        .filter-item select,
        .filter-item input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn-filter {
            padding: 8px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-filter:hover {
            background: #0056b3;
        }
        .btn-reset {
            padding: 8px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-reset:hover {
            background: #5a6268;
        }
        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-card {
            flex: 1;
            min-width: 150px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
        }
        .stat-card.deleted {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card.active {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        .bulk-actions {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
            border-left: 4px solid #004085;
        }
        .bulk-actions.active {
            display: block;
        }
        .bulk-actions-text {
            font-weight: 600;
            color: #004085;
            margin-bottom: 10px;
        }
        .bulk-actions-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-bulk {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-bulk-soft-delete {
            background: #ffc107;
            color: #000;
        }
        .btn-bulk-soft-delete:hover {
            background: #e0a800;
        }
        .btn-bulk-restore {
            background: #28a745;
            color: white;
        }
        .btn-bulk-restore:hover {
            background: #218838;
        }
        .btn-soft-delete {
            background: #ffc107;
            color: #000;
        }
        .btn-soft-delete:hover {
            background: #e0a800;
        }
        .btn-restore {
            background: #28a745;
            color: white;
        }
        .btn-restore:hover {
            background: #218838;
        }
        .table-responsive {
            border-radius: 6px;
            overflow: hidden;
        }
        table {
            margin-bottom: 0;
        }
        thead {
            background: #f8f9fa;
        }
        thead th {
            padding: 15px;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        thead th input[type="checkbox"] {
            cursor: pointer;
        }
        tbody td {
            padding: 12px 15px;
            vertical-align: middle;
        }
        tbody tr:hover {
            background: #f8f9fa;
        }
        tbody tr.selected {
            background: #e7f3ff !important;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-deleted {
            background: #f8d7da;
            color: #721c24;
        }
        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e7f3ff;
            color: #004085;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .pagination {
            margin-top: 30px;
            justify-content: center;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            margin: 0 3px;
            border-radius: 4px;
            text-decoration: none;
            color: #007bff;
        }
        .pagination a:hover {
            background: #e9ecef;
        }
        .pagination .active {
            background: #007bff;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .modal-dialog {
            max-width: 500px;
        }
        .alert {
            margin-bottom: 20px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header-section">
            <div>
                <h1><i class="fas fa-trash-alt"></i> User Deletion Manager</h1>
                <small class="text-muted">Delete, restore, or manage user records</small>
            </div>
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Admin</a>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Total Records</div>
            </div>
            <?php
            $activeCount = intval(mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as count FROM user_details WHERE isDeleted = 0"))['count']);
            $deletedCount = intval(mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as count FROM user_details WHERE isDeleted = 1"))['count']);
            ?>
            <div class="stat-card active">
                <div class="stat-value"><?php echo $activeCount; ?></div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card deleted">
                <div class="stat-value"><?php echo $deletedCount; ?></div>
                <div class="stat-label">Deleted Users</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" class="filter-group">
                <div class="filter-item">
                    <label>Search (Name/Email)</label>
                    <input type="text" name="search" placeholder="Type name or email..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
                <div class="filter-item">
                    <label>Role</label>
                    <select name="role">
                        <option value="">All Roles</option>
                        <option value="CUSTOMER" <?php echo $filterRole === 'CUSTOMER' ? 'selected' : ''; ?>>Customer</option>
                        <option value="FRANCHISEE" <?php echo $filterRole === 'FRANCHISEE' ? 'selected' : ''; ?>>Franchisee</option>
                        <option value="TEAM" <?php echo $filterRole === 'TEAM' ? 'selected' : ''; ?>>Team Member</option>
                        <option value="ADMIN" <?php echo $filterRole === 'ADMIN' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Users</option>
                        <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active Only</option>
                        <option value="deleted" <?php echo $filterStatus === 'deleted' ? 'selected' : ''; ?>>Deleted Only</option>
                    </select>
                </div>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                <a href="manage_user_deletions.php" class="btn-reset"><i class="fas fa-redo"></i> Reset</a>
            </form>
        </div>

        <!-- Bulk Actions Panel -->
        <div class="bulk-actions" id="bulkActionsPanel">
            <div class="bulk-actions-text">
                <i class="fas fa-check-circle"></i> <span id="selectedCount">0</span> user(s) selected
            </div>
            <div class="bulk-actions-buttons">
                <button class="btn-bulk btn-bulk-soft-delete" onclick="bulkSoftDelete()">
                    <i class="fas fa-trash"></i> Mark as Deleted
                </button>
                <button class="btn-bulk btn-bulk-restore" onclick="bulkRestore()">
                    <i class="fas fa-undo"></i> Restore
                </button>
                <button class="btn-bulk btn-secondary" onclick="clearSelection()">
                    <i class="fas fa-times"></i> Clear Selection
                </button>
            </div>
        </div>

        <!-- Users Table -->
        <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            </th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php foreach ($users as $user): ?>
                            <tr class="user-row" data-user-id="<?php echo $user['id']; ?>">
                                <td>
                                    <input type="checkbox" class="user-checkbox" value="<?php echo $user['id']; ?>" onchange="updateBulkPanel()">
                                </td>
                                <td><strong>#<?php echo $user['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><small><?php echo htmlspecialchars($user['email']); ?></small></td>
                                <td><span class="role-badge"><?php echo htmlspecialchars($user['role']); ?></span></td>
                                <td>
                                    <span class="status-badge <?php echo $user['isDeleted'] == 1 ? 'status-deleted' : 'status-active'; ?>">
                                        <?php echo $user['isDeleted'] == 1 ? 'DELETED' : 'ACTIVE'; ?>
                                    </span>
                                </td>
                                <td><small><?php echo date('M d, Y', strtotime($user['created_at'])); ?></small></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($user['isDeleted'] == 1): ?>
                                            <button class="btn-sm btn-restore" onclick="singleRestore(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">
                                                <i class="fas fa-undo"></i> Restore
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-sm btn-soft-delete" onclick="singleSoftDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&role=<?php echo urlencode($filterRole); ?>&status=<?php echo urlencode($filterStatus); ?>&search=<?php echo urlencode($searchTerm); ?>">« First</a>
                        <a href="?page=<?php echo $page - 1; ?>&role=<?php echo urlencode($filterRole); ?>&status=<?php echo urlencode($filterStatus); ?>&search=<?php echo urlencode($searchTerm); ?>">‹ Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&role=<?php echo urlencode($filterRole); ?>&status=<?php echo urlencode($filterStatus); ?>&search=<?php echo urlencode($searchTerm); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&role=<?php echo urlencode($filterRole); ?>&status=<?php echo urlencode($filterStatus); ?>&search=<?php echo urlencode($searchTerm); ?>">Next ›</a>
                        <a href="?page=<?php echo $totalPages; ?>&role=<?php echo urlencode($filterRole); ?>&status=<?php echo urlencode($filterStatus); ?>&search=<?php echo urlencode($searchTerm); ?>">Last »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                <h4>No users found</h4>
                <p>No users match your search criteria.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage"></p>
                    <div id="confirmWarning" style="display:none; margin-top: 15px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                        <strong style="color: #856404;">⚠️ Warning:</strong>
                        <p style="margin: 5px 0; color: #856404;">This action will permanently delete the user(s) and all associated data.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let confirmAction = null;
        let confirmUserIds = null;

        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBulkPanel();
        }

        function updateBulkPanel() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            const count = checkboxes.length;
            const panel = document.getElementById('bulkActionsPanel');
            
            if (count > 0) {
                panel.classList.add('active');
                document.getElementById('selectedCount').textContent = count;
            } else {
                panel.classList.remove('active');
            }

            // Update row highlighting
            document.querySelectorAll('.user-row').forEach(row => {
                const checkbox = row.querySelector('.user-checkbox');
                if (checkbox.checked) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            });

            // Update select all checkbox
            const allCheckboxes = document.querySelectorAll('.user-checkbox');
            const allChecked = allCheckboxes.length > 0 && Array.from(allCheckboxes).every(cb => cb.checked);
            document.getElementById('selectAll').checked = allChecked;
        }

        function clearSelection() {
            document.querySelectorAll('.user-checkbox').forEach(cb => {
                cb.checked = false;
            });
            document.getElementById('selectAll').checked = false;
            updateBulkPanel();
        }

        function getSelectedUserIds() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }

        function bulkSoftDelete() {
            const userIds = getSelectedUserIds();
            if (userIds.length === 0) {
                alert('Please select at least one user');
                return;
            }

            confirmUserIds = userIds;
            confirmAction = 'bulk_soft_delete';

            const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            document.getElementById('confirmTitle').textContent = 'Mark Users as Deleted';
            document.getElementById('confirmMessage').textContent = `Are you sure you want to mark ${userIds.length} user(s) as deleted? This action is reversible.`;
            document.getElementById('confirmWarning').style.display = 'none';
            document.getElementById('confirmBtn').className = 'btn btn-warning';
            document.getElementById('confirmBtn').textContent = 'Mark as Deleted';
            
            modal.show();
        }

        function bulkRestore() {
            const userIds = getSelectedUserIds();
            if (userIds.length === 0) {
                alert('Please select at least one user');
                return;
            }

            confirmUserIds = userIds;
            confirmAction = 'bulk_restore';

            const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            document.getElementById('confirmTitle').textContent = 'Restore Users';
            document.getElementById('confirmMessage').textContent = `Are you sure you want to restore ${userIds.length} user(s)?`;
            document.getElementById('confirmWarning').style.display = 'none';
            document.getElementById('confirmBtn').className = 'btn btn-success';
            document.getElementById('confirmBtn').textContent = 'Restore';
            
            modal.show();
        }

        function singleSoftDelete(userId, userName) {
            confirmUserIds = [userId];
            confirmAction = 'bulk_soft_delete';

            const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            document.getElementById('confirmTitle').textContent = 'Mark User as Deleted';
            document.getElementById('confirmMessage').textContent = `Are you sure you want to mark "${userName}" as deleted? This action is reversible.`;
            document.getElementById('confirmWarning').style.display = 'none';
            document.getElementById('confirmBtn').className = 'btn btn-warning';
            document.getElementById('confirmBtn').textContent = 'Mark as Deleted';
            
            modal.show();
        }

        function singleRestore(userId, userName) {
            confirmUserIds = [userId];
            confirmAction = 'bulk_restore';

            const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            document.getElementById('confirmTitle').textContent = 'Restore User';
            document.getElementById('confirmMessage').textContent = `Are you sure you want to restore "${userName}"?`;
            document.getElementById('confirmWarning').style.display = 'none';
            document.getElementById('confirmBtn').className = 'btn btn-success';
            document.getElementById('confirmBtn').textContent = 'Restore';
            
            modal.show();
        }



        document.getElementById('confirmBtn').addEventListener('click', function() {
            if (!confirmAction || !confirmUserIds || confirmUserIds.length === 0) return;
            
            const formData = new FormData();
            formData.append('action', confirmAction);
            confirmUserIds.forEach(id => {
                formData.append('userIds[]', id);
            });
            
            // Disable button to prevent double click
            document.getElementById('confirmBtn').disabled = true;
            
            fetch('manage_user_deletions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP Error: ' + response.status);
                }
                return response.text(); // Get text first to debug
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                        document.getElementById('confirmBtn').disabled = false;
                    }
                } catch (e) {
                    console.error('Parse error:', e);
                    console.error('Response was:', text);
                    alert('Server error. Please check the console.');
                    document.getElementById('confirmBtn').disabled = false;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Error: ' + error.message);
                document.getElementById('confirmBtn').disabled = false;
            });
            
            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
        });
    </script>
</body>
</html>
