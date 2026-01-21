<?php
// Check for export FIRST - before any HTML output
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // For export, use database config directly (no HTML output)
    require_once(__DIR__ . '/../app/config/database.php');
    
    if (!isset($_SESSION['admin_email'])) {
        header('Location: login.php');
        exit;
    }
    
    // Get filter parameters
    $filter_team_member = isset($_GET['team_member']) ? intval($_GET['team_member']) : 0;
    $filter_status = isset($_GET['status']) ? $_GET['status'] : '';
    $filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    
    // Build query with filters
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    if ($filter_team_member > 0) {
        $where_conditions[] = 'ct.team_member_id = ?';
        $params[] = $filter_team_member;
        $param_types .= 'i';
    }
    
    if (!empty($filter_status)) {
        $where_conditions[] = 'ct.final_status = ?';
        $params[] = $filter_status;
        $param_types .= 's';
    }
    
    if (!empty($filter_date_from)) {
        $where_conditions[] = 'ct.date_visited >= ?';
        $params[] = $filter_date_from;
        $param_types .= 's';
    }
    
    if (!empty($filter_date_to)) {
        $where_conditions[] = 'ct.date_visited <= ?';
        $params[] = $filter_date_to;
        $param_types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get customer tracker records with team member info - using user_details
    $query = "SELECT ct.*, tm.name AS member_name, tm.email AS member_email 
              FROM customer_tracker ct 
              LEFT JOIN user_details tm ON ct.team_member_id = tm.legacy_team_id AND tm.role='TEAM'
              $where_clause 
              ORDER BY ct.date_visited DESC, ct.created_at DESC";
    
    $records = [];
    if (!empty($params)) {
        $stmt = $connect->prepare($query);
        if ($stmt) {
            $stmt->bind_param($param_types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
            $stmt->close();
        }
    } else {
        $result = mysqli_query($connect, $query);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $records[] = $row;
            }
        }
    }
    
    // Handle Excel Export
    // Get team member name for filename - using user_details
    $team_member_name = 'All';
    if ($filter_team_member > 0) {
        // First try to find by legacy_team_id, then by id
        $tm_stmt = $connect->prepare('SELECT name FROM user_details WHERE (legacy_team_id = ? OR id = ?) AND role="TEAM" LIMIT 1');
        if ($tm_stmt) {
            $tm_stmt->bind_param('ii', $filter_team_member, $filter_team_member);
            $tm_stmt->execute();
            $tm_result = $tm_stmt->get_result();
            if ($tm_row = $tm_result->fetch_assoc()) {
                $team_member_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $tm_row['name']);
            }
            $tm_stmt->close();
        }
    }
    
    // Set headers for Excel download
    $filename = 'Customer_Tracker_' . $team_member_name . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write CSV headers
    fputcsv($output, [
        'ID',
        'Shop/Person Name',
        'Contact Number',
        'Address',
        'Approached For',
        'Date Visited',
        'Final Status',
        'Comments',
        'Team Member Name',
        'Team Member Email',
        'Recorded On'
    ]);
    
    // Write data rows
    foreach ($records as $record) {
        fputcsv($output, [
            $record['id'],
            $record['shop_name'] ?? '',
            $record['contact_number'] ?? '',
            $record['address'] ?? '',
            $record['approached_for'] ?? '',
            !empty($record['date_visited']) ? date('d-m-Y', strtotime($record['date_visited'])) : '',
            $record['final_status'] ?? '',
            $record['comments'] ?? '',
            $record['member_name'] ?? 'Unknown',
            $record['member_email'] ?? '',
            !empty($record['created_at']) ? date('d-m-Y H:i', strtotime($record['created_at'])) : ''
        ]);
    }
    
    fclose($output);
    exit;
}

// Normal page flow - include connect.php (which includes header) for regular page display
require_once(__DIR__ . '/../app/config/database.php');

if (!isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

// Get filter parameters
$filter_team_member = isset($_GET['team_member']) ? intval($_GET['team_member']) : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($filter_team_member > 0) {
    $where_conditions[] = 'ct.team_member_id = ?';
    $params[] = $filter_team_member;
    $param_types .= 'i';
}

if (!empty($filter_status)) {
    $where_conditions[] = 'ct.final_status = ?';
    $params[] = $filter_status;
    $param_types .= 's';
}

if (!empty($filter_date_from)) {
    $where_conditions[] = 'ct.date_visited >= ?';
    $params[] = $filter_date_from;
    $param_types .= 's';
}

if (!empty($filter_date_to)) {
    $where_conditions[] = 'ct.date_visited <= ?';
    $params[] = $filter_date_to;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get customer tracker records with team member info - using user_details
$query = "SELECT ct.*, tm.name AS member_name, tm.email AS member_email 
          FROM customer_tracker ct 
          LEFT JOIN user_details tm ON ct.team_member_id = tm.legacy_team_id AND tm.role='TEAM'
          $where_clause 
          ORDER BY ct.date_visited DESC, ct.created_at DESC";

$records = [];
if (!empty($params)) {
    $stmt = $connect->prepare($query);
    if ($stmt) {
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        $stmt->close();
    }
} else {
    $result = mysqli_query($connect, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $records[] = $row;
        }
    }
}

// Get all team members for filter dropdown - using user_details
$team_members = [];
// Use legacy_team_id for compatibility with customer_tracker.team_member_id
$team_stmt = $connect->prepare('SELECT legacy_team_id AS id, name AS member_name, email AS member_email FROM user_details WHERE role="TEAM" ORDER BY name ASC');
if ($team_stmt) {
    $team_stmt->execute();
    $team_result = $team_stmt->get_result();
    while ($row = $team_result->fetch_assoc()) {
        $team_members[] = $row;
    }
    $team_stmt->close();
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_visits,
    SUM(CASE WHEN final_status = 'Joined' THEN 1 ELSE 0 END) as joined_count,
    SUM(CASE WHEN final_status = 'Not Interested' THEN 1 ELSE 0 END) as not_interested_count,
    SUM(CASE WHEN final_status = 'Followup required' THEN 1 ELSE 0 END) as followup_count
    FROM customer_tracker ct
    $where_clause";

$stats = [];
if (!empty($params)) {
    $stats_stmt = $connect->prepare($stats_query);
    if ($stats_stmt) {
        $stats_stmt->bind_param($param_types, ...$params);
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        $stats = $stats_result->fetch_assoc();
        $stats_stmt->close();
    }
} else {
    $stats_result = mysqli_query($connect, $stats_query);
    if ($stats_result) {
        $stats = mysqli_fetch_assoc($stats_result);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Tracker - Admin</title>
    <style>
        .filter-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-box h3 {
            margin: 0;
            font-size: 2rem;
            color: #667eea;
        }
        .stat-box p {
            margin: 5px 0 0 0;
            color: #666;
        }
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
        .badge-status-joined {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
        }
        .badge-status-not-interested {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
        }
        .badge-status-followup {
            background-color: #ffc107;
            color: #000;
            padding: 5px 10px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container-fluid" style="padding: 20px;">
        <h2><i class="fas fa-users"></i> Customer Tracker Management</h2>
        <hr>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-box">
                    <h3><?php echo $stats['total_visits'] ?? 0; ?></h3>
                    <p>Total Visits</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h3 style="color: #28a745;"><?php echo $stats['joined_count'] ?? 0; ?></h3>
                    <p>Joined</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h3 style="color: #dc3545;"><?php echo $stats['not_interested_count'] ?? 0; ?></h3>
                    <p>Not Interested</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h3 style="color: #ffc107;"><?php echo $stats['followup_count'] ?? 0; ?></h3>
                    <p>Followup Required</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <h5><i class="fas fa-filter"></i> Filters</h5>
            <form method="GET" action="" class="row">
                <div class="col-md-3 mb-3">
                    <label for="team_member">Team Member:</label>
                    <select class="form-control" id="team_member" name="team_member">
                        <option value="0">All Team Members</option>
                        <?php foreach ($team_members as $member): ?>
                            <option value="<?php echo $member['id']; ?>" <?php echo $filter_team_member == $member['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['member_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="status">Status:</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="Joined" <?php echo $filter_status == 'Joined' ? 'selected' : ''; ?>>Joined</option>
                        <option value="Not Interested" <?php echo $filter_status == 'Not Interested' ? 'selected' : ''; ?>>Not Interested</option>
                        <option value="Followup required" <?php echo $filter_status == 'Followup required' ? 'selected' : ''; ?>>Followup Required</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="date_from">Date From:</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="date_to">Date To:</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label>&nbsp;</label><br>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply Filters</button>
                    <a href="customer-tracker.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
                </div>
            </form>
        </div>

        <!-- Records Table -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> Customer Visit Records (<?php echo count($records); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($records)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No customer visit records found with the selected filters.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Shop/Person Name</th>
                                    <th>Contact Number</th>
                                    <th>Address</th>
                                    <th>Date Visited</th>
                                    <th>Final Status</th>
                                    <th>Team Member</th>
                                    <th>Comments</th>
                                    <th>Recorded On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?php echo $record['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($record['shop_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($record['contact_number'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($record['address'] ?: '-'); ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($record['date_visited'])); ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        if ($record['final_status'] === 'Joined') {
                                            $status_class = 'badge-status-joined';
                                        } elseif ($record['final_status'] === 'Not Interested') {
                                            $status_class = 'badge-status-not-interested';
                                        } else {
                                            $status_class = 'badge-status-followup';
                                        }
                                        ?>
                                        <span class="<?php echo $status_class; ?>"><?php echo htmlspecialchars($record['final_status']); ?></span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($record['member_name'] ?? 'Unknown'); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($record['member_email'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['comments'] ?: '-'); ?></td>
                                    <td><?php echo date('d-m-Y H:i', strtotime($record['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $record['id']; ?>" title="View Full Details">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#historyModal<?php echo $record['id']; ?>" title="View History">
                                            <i class="fas fa-history"></i> History
                                        </button>
                                    </td>
                                </tr>

                                <!-- View Modal -->
                                <div class="modal fade" id="viewModal<?php echo $record['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content" style="background-color: #fff;">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title"><i class="fas fa-eye"></i> Customer Visit Details</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body" style="background-color: #fff; color: #333;">
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <th width="40%" style="color: #333;">Shop/Person Name:</th>
                                                        <td style="color: #333;"><?php echo htmlspecialchars($record['shop_name']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th style="color: #333;">Contact Number:</th>
                                                        <td style="color: #333;"><?php echo htmlspecialchars($record['contact_number'] ?: '-'); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th style="color: #333;">Address:</th>
                                                        <td style="color: #333;"><?php echo htmlspecialchars($record['address'] ?: '-'); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th style="color: #333;">Date Visited:</th>
                                                        <td style="color: #333;"><?php echo date('d-m-Y', strtotime($record['date_visited'])); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th style="color: #333;">Final Status:</th>
                                                        <td>
                                                            <?php
                                                            $status_class = '';
                                                            if ($record['final_status'] === 'Joined') {
                                                                $status_class = 'badge-status-joined';
                                                            } elseif ($record['final_status'] === 'Not Interested') {
                                                                $status_class = 'badge-status-not-interested';
                                                            } else {
                                                                $status_class = 'badge-status-followup';
                                                            }
                                                            ?>
                                                            <span class="<?php echo $status_class; ?>"><?php echo htmlspecialchars($record['final_status']); ?></span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th style="color: #333;">Team Member:</th>
                                                        <td style="color: #333;">
                                                            <?php echo htmlspecialchars($record['member_name'] ?? 'Unknown'); ?><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($record['member_email'] ?? ''); ?></small>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th style="color: #333;">Comments:</th>
                                                        <td style="color: #333;"><?php echo htmlspecialchars($record['comments'] ?: '-'); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th style="color: #333;">Recorded On:</th>
                                                        <td style="color: #333;"><?php echo date('d-m-Y H:i', strtotime($record['created_at'])); ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="modal-footer" style="background-color: #fff;">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- History Modal -->
                                <div class="modal fade" id="historyModal<?php echo $record['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content" style="background-color: #fff;">
                                            <div class="modal-header bg-secondary text-white">
                                                <h5 class="modal-title"><i class="fas fa-history"></i> Change History - <?php echo htmlspecialchars($record['shop_name']); ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body" style="background-color: #fff; color: #333;">
                                                <?php
                                                // Get history for this record - using user_details
                                                $history_query = "SELECT h.*, tm.name AS member_name 
                                                    FROM customer_tracker_history h 
                                                    LEFT JOIN user_details tm ON h.changed_by = tm.legacy_team_id AND tm.role='TEAM'
                                                    WHERE h.tracker_id = ? 
                                                    ORDER BY h.changed_at DESC";
                                                $history_stmt = $connect->prepare($history_query);
                                                $history = [];
                                                if ($history_stmt) {
                                                    $history_stmt->bind_param('i', $record['id']);
                                                    $history_stmt->execute();
                                                    $history_result = $history_stmt->get_result();
                                                    while ($hrow = $history_result->fetch_assoc()) {
                                                        $history[] = $hrow;
                                                    }
                                                    $history_stmt->close();
                                                }
                                                
                                                if (empty($history)): ?>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle"></i> No history available for this record.
                                                    </div>
                                                <?php else: ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered" style="background-color: #fff;">
                                                            <thead class="table-dark">
                                                                <tr>
                                                                    <th style="color: #fff;">Date & Time</th>
                                                                    <th style="color: #fff;">Changed By</th>
                                                                    <th style="color: #fff;">Field</th>
                                                                    <th style="color: #fff;">Old Value</th>
                                                                    <th style="color: #fff;">New Value</th>
                                                                    <th style="color: #fff;">Type</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody style="background-color: #fff;">
                                                                <?php foreach ($history as $hist): ?>
                                                                <tr style="background-color: #fff;">
                                                                    <td style="color: #333;"><?php echo date('d-m-Y H:i', strtotime($hist['changed_at'])); ?></td>
                                                                    <td style="color: #333;"><?php echo htmlspecialchars($hist['member_name'] ?? 'Unknown'); ?></td>
                                                                    <td style="color: #333;">
                                                                        <?php
                                                                        $field_name = $hist['changed_field'];
                                                                        if ($field_name === 'final_status') {
                                                                            echo '<span class="badge bg-primary">Status</span>';
                                                                        } elseif ($field_name === 'comments') {
                                                                            echo '<span class="badge bg-info">Comments</span>';
                                                                        } else {
                                                                            echo htmlspecialchars($field_name);
                                                                        }
                                                                        ?>
                                                                    </td>
                                                                    <td style="color: #333;">
                                                                        <?php 
                                                                        if ($hist['changed_field'] === 'final_status') {
                                                                            echo '<span class="badge bg-secondary">' . htmlspecialchars($hist['old_value'] ?: '-') . '</span>';
                                                                        } else {
                                                                            echo htmlspecialchars(substr($hist['old_value'] ?: '-', 0, 50));
                                                                            echo strlen($hist['old_value']) > 50 ? '...' : '';
                                                                        }
                                                                        ?>
                                                                    </td>
                                                                    <td style="color: #333;">
                                                                        <?php 
                                                                        if ($hist['changed_field'] === 'final_status') {
                                                                            $new_status_class = '';
                                                                            if ($hist['new_value'] === 'Joined') $new_status_class = 'bg-success';
                                                                            elseif ($hist['new_value'] === 'Not Interested') $new_status_class = 'bg-danger';
                                                                            else $new_status_class = 'bg-warning';
                                                                            echo '<span class="badge ' . $new_status_class . '">' . htmlspecialchars($hist['new_value']) . '</span>';
                                                                        } else {
                                                                            echo htmlspecialchars(substr($hist['new_value'] ?: '-', 0, 50));
                                                                            echo strlen($hist['new_value']) > 50 ? '...' : '';
                                                                        }
                                                                        ?>
                                                                    </td>
                                                                    <td style="color: #333;">
                                                                        <?php
                                                                        $type_class = '';
                                                                        if ($hist['change_type'] === 'status_change') {
                                                                            $type_class = 'bg-primary';
                                                                        } elseif ($hist['change_type'] === 'comment_change') {
                                                                            $type_class = 'bg-info';
                                                                        } else {
                                                                            $type_class = 'bg-secondary';
                                                                        }
                                                                        ?>
                                                                        <span class="badge <?php echo $type_class; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $hist['change_type']))); ?></span>
                                                                    </td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer" style="background-color: #fff;">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>




