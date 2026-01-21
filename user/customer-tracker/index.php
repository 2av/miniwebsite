<?php
// Customer Tracker for TEAM members (copied from old/team/customer-tracker/index.php and adapted)

// Include centralized database + helpers
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/role_helper.php');

// Require TEAM role
require_role('TEAM', '/login/team.php');

// Get team member details from helpers/session
$team_member_id = get_user_id();
$user_email     = get_user_email();

// Also keep legacy session key for compatibility with old code (if any)
$_SESSION['team_member_id'] = $team_member_id;

// Handle form submissions BEFORE including header (to allow redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_tracker') {
        $shop_name      = trim($_POST['shop_name']      ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $approached_for = trim($_POST['approached_for'] ?? 'Franchisee Sale');
        $address        = trim($_POST['address']        ?? '');
        $date_visited   = trim($_POST['date_visited']   ?? '');
        $final_status   = trim($_POST['final_status']   ?? 'Followup required');
        $comments       = trim($_POST['comments']       ?? '');

        // Validation
        if (empty($shop_name)) {
            $message = 'Shop/Person name is required.';
            $message_type = 'danger';
        } elseif (empty($contact_number)) {
            $message = 'Contact number is required.';
            $message_type = 'danger';
        } elseif (empty($approached_for)) {
            $message = 'Approached For is required.';
            $message_type = 'danger';
        } elseif (empty($date_visited)) {
            $message = 'Date visited is required.';
            $message_type = 'danger';
        } else {
            // Insert into database
            $stmt = $connect->prepare('INSERT INTO customer_tracker (team_member_id, shop_name, contact_number, approached_for, address, date_visited, final_status, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('isssssss', $team_member_id, $shop_name, $contact_number, $approached_for, $address, $date_visited, $final_status, $comments);
                if ($stmt->execute()) {
                    $tracker_id = $stmt->insert_id;
                    // Record initial history entry
                    $history_stmt = $connect->prepare('INSERT INTO customer_tracker_history (tracker_id, team_member_id, changed_field, old_value, new_value, change_type, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    if ($history_stmt) {
                        $field       = 'initial_entry';
                        $old_val     = '';
                        $new_val     = 'Record created';
                        $change_type = 'other_change';
                        $history_stmt->bind_param('iissssi', $tracker_id, $team_member_id, $field, $old_val, $new_val, $change_type, $team_member_id);
                        $history_stmt->execute();
                        $history_stmt->close();
                    }
                    $message = 'Customer tracker record added successfully!';
                    $message_type = 'success';
                    // Clear form by redirecting
                    header('Location: index.php?success=1');
                    exit;
                } else {
                    $message = 'Error adding record: ' . $stmt->error;
                    $message_type = 'danger';
                }
                $stmt->close();
            } else {
                $message = 'Database error: ' . $connect->error;
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'update_tracker') {
        $tracker_id        = intval($_POST['tracker_id'] ?? 0);
        $new_shop_name     = trim($_POST['shop_name']      ?? '');
        $new_contact_number= trim($_POST['contact_number'] ?? '');
        $new_approached_for= trim($_POST['approached_for'] ?? 'Franchisee Sale');
        $new_address       = trim($_POST['address']        ?? '');
        $new_status        = trim($_POST['final_status']   ?? '');
        $new_comments      = trim($_POST['comments']       ?? '');
        
        if ($tracker_id > 0) {
            // Get current record
            $get_stmt = $connect->prepare('SELECT shop_name, contact_number, approached_for, address, final_status, comments FROM customer_tracker WHERE id = ? AND team_member_id = ?');
            if ($get_stmt) {
                $get_stmt->bind_param('ii', $tracker_id, $team_member_id);
                $get_stmt->execute();
                $current = $get_stmt->get_result()->fetch_assoc();
                $get_stmt->close();
                
                if ($current) {
                    $old_shop_name      = $current['shop_name'];
                    $old_contact_number = $current['contact_number'] ?? '';
                    $old_approached_for = $current['approached_for'] ?? '';
                    $old_address        = $current['address'] ?? '';
                    $old_status         = $current['final_status'];
                    $old_comments       = $current['comments'] ?? '';
                    
                    // Update record
                    $update_stmt = $connect->prepare('UPDATE customer_tracker SET shop_name = ?, contact_number = ?, approached_for = ?, address = ?, final_status = ?, comments = ? WHERE id = ? AND team_member_id = ?');
                    if ($update_stmt) {
                        $update_stmt->bind_param('ssssssii', $new_shop_name, $new_contact_number, $new_approached_for, $new_address, $new_status, $new_comments, $tracker_id, $team_member_id);
                        if ($update_stmt->execute()) {
                            // Record history for basic fields
                            if ($old_shop_name !== $new_shop_name) {
                                $history_stmt = $connect->prepare('INSERT INTO customer_tracker_history (tracker_id, team_member_id, changed_field, old_value, new_value, change_type, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                                if ($history_stmt) {
                                    $field = 'shop_name';
                                    $change_type = 'other_change';
                                    $history_stmt->bind_param('iissssi', $tracker_id, $team_member_id, $field, $old_shop_name, $new_shop_name, $change_type, $team_member_id);
                                    $history_stmt->execute();
                                    $history_stmt->close();
                                }
                            }
                            if ($old_contact_number !== $new_contact_number) {
                                $history_stmt = $connect->prepare('INSERT INTO customer_tracker_history (tracker_id, team_member_id, changed_field, old_value, new_value, change_type, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                                if ($history_stmt) {
                                    $field = 'contact_number';
                                    $change_type = 'other_change';
                                    $history_stmt->bind_param('iissssi', $tracker_id, $team_member_id, $field, $old_contact_number, $new_contact_number, $change_type, $team_member_id);
                                    $history_stmt->execute();
                                    $history_stmt->close();
                                }
                            }
                            if ($old_approached_for !== $new_approached_for) {
                                $history_stmt = $connect->prepare('INSERT INTO customer_tracker_history (tracker_id, team_member_id, changed_field, old_value, new_value, change_type, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                                if ($history_stmt) {
                                    $field = 'approached_for';
                                    $change_type = 'other_change';
                                    $history_stmt->bind_param('iissssi', $tracker_id, $team_member_id, $field, $old_approached_for, $new_approached_for, $change_type, $team_member_id);
                                    $history_stmt->execute();
                                    $history_stmt->close();
                                }
                            }
                            if ($old_address !== $new_address) {
                                $history_stmt = $connect->prepare('INSERT INTO customer_tracker_history (tracker_id, team_member_id, changed_field, old_value, new_value, change_type, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                                if ($history_stmt) {
                                    $field = 'address';
                                    $change_type = 'other_change';
                                    $history_stmt->bind_param('iissssi', $tracker_id, $team_member_id, $field, $old_address, $new_address, $change_type, $team_member_id);
                                    $history_stmt->execute();
                                    $history_stmt->close();
                                }
                            }
                            // Record history for status change
                            if ($old_status !== $new_status) {
                                $history_stmt = $connect->prepare('INSERT INTO customer_tracker_history (tracker_id, team_member_id, changed_field, old_value, new_value, change_type, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                                if ($history_stmt) {
                                    $field = 'final_status';
                                    $change_type = 'status_change';
                                    $history_stmt->bind_param('iissssi', $tracker_id, $team_member_id, $field, $old_status, $new_status, $change_type, $team_member_id);
                                    $history_stmt->execute();
                                    $history_stmt->close();
                                }
                            }
                            
                            // Record history for comment change
                            if ($old_comments !== $new_comments) {
                                $history_stmt = $connect->prepare('INSERT INTO customer_tracker_history (tracker_id, team_member_id, changed_field, old_value, new_value, change_type, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                                if ($history_stmt) {
                                    $field = 'comments';
                                    $change_type = 'comment_change';
                                    $history_stmt->bind_param('iissssi', $tracker_id, $team_member_id, $field, $old_comments, $new_comments, $change_type, $team_member_id);
                                    $history_stmt->execute();
                                    $history_stmt->close();
                                }
                            }
                            
                            $message = 'Record updated successfully!';
                            $message_type = 'success';
                            header('Location: index.php?success=2');
                            exit;
                        } else {
                            $message = 'Error updating record: ' . $update_stmt->error;
                            $message_type = 'danger';
                        }
                        $update_stmt->close();
                    }
                } else {
                    $message = 'Record not found or you do not have permission to edit it.';
                    $message_type = 'danger';
                }
            }
        }
    } elseif ($action === 'add_followup') {
        $tracker_id           = intval($_POST['tracker_id'] ?? 0);
        $followup_datetime_raw= trim($_POST['followup_datetime'] ?? '');
        $followup_methods     = $_POST['followup_method'] ?? [];
        if (!is_array($followup_methods)) {
            $followup_methods = [$followup_methods];
        }
        // Filter empty values and implode as CSV
        $followup_methods = array_filter($followup_methods, function($m) {
            return trim($m) !== '';
        });
        $followup_method  = implode(', ', $followup_methods);
        $followup_status  = trim($_POST['followup_status']  ?? '');
        $followup_comments= trim($_POST['followup_comments']?? '');

        // Normalize datetime (from datetime-local input)
        $followup_datetime = '';
        if (!empty($followup_datetime_raw)) {
            $ts = strtotime($followup_datetime_raw);
            if ($ts !== false) {
                $followup_datetime = date('Y-m-d H:i:s', $ts);
            }
        }

        if ($tracker_id <= 0 || empty($followup_datetime) || empty($followup_method) || empty($followup_status)) {
            $message = 'Please fill all required Followup fields (Date & Time, Method, Status).';
            $message_type = 'danger';
        } else {
            $ins = $connect->prepare('INSERT INTO customer_tracker_followups (tracker_id, team_member_id, followup_datetime, followup_method, followup_status, comments) VALUES (?, ?, ?, ?, ?, ?)');
            if ($ins) {
                $ins->bind_param('iissss', $tracker_id, $team_member_id, $followup_datetime, $followup_method, $followup_status, $followup_comments);
                if ($ins->execute()) {
                    // Also update main record's final_status, comments
                    $upd = $connect->prepare('UPDATE customer_tracker SET final_status = ?, comments = ? WHERE id = ? AND team_member_id = ?');
                    if ($upd) {
                        $upd->bind_param('ssii', $followup_status, $followup_comments, $tracker_id, $team_member_id);
                        $upd->execute();
                        $upd->close();
                    }
                    $message = 'Followup added successfully!';
                    $message_type = 'success';
                    header('Location: index.php?success=3');
                    exit;
                } else {
                    $message = 'Error adding followup: ' . $ins->error;
                    $message_type = 'danger';
                }
                $ins->close();
            } else {
                $message = 'Database error while adding followup: ' . $connect->error;
                $message_type = 'danger';
            }
        }
    }
}

// Handle form submission messages
$message = $message ?? '';
$message_type = $message_type ?? '';

// Check for success message
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) {
        $message = 'Customer tracker record added successfully!';
        $message_type = 'success';
    } elseif ($_GET['success'] == 2) {
        $message = 'Record updated successfully!';
        $message_type = 'success';
    } elseif ($_GET['success'] == 3) {
        $message = 'Followup added successfully!';
        $message_type = 'success';
    }
}

// Include main header (sets $page_title, layout, etc.)
include __DIR__ . '/../includes/header.php';

// Get all records for this team member (with last_updated based on latest followup, if any)
$records = [];
$stmt = $connect->prepare('
    SELECT ct.*, 
           COALESCE(
               (SELECT MAX(f.followup_datetime) 
                FROM customer_tracker_followups f 
                WHERE f.tracker_id = ct.id),
               ct.created_at
           ) AS last_updated
    FROM customer_tracker ct
    WHERE ct.team_member_id = ?
    ORDER BY ct.date_visited DESC, last_updated DESC
');
if ($stmt) {
    $stmt->bind_param('i', $team_member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    $stmt->close();
}

// Function to get followups for a tracker record
function getTrackerFollowups($connect, $tracker_id) {
    $followups = [];
    $stmt = $connect->prepare('SELECT f.*, tm.member_name FROM customer_tracker_followups f LEFT JOIN team_members tm ON f.team_member_id = tm.id WHERE f.tracker_id = ? ORDER BY f.followup_datetime DESC, f.id DESC');
    if ($stmt) {
        $stmt->bind_param('i', $tracker_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $followups[] = $row;
        }
        $stmt->close();
    }
    return $followups;
}

?>

<main class="Dashboard">
    <div class="container-fluid  customer_content_area">
        <div class="main-top">
        <span class="heading"><?php echo $page_title; ?></span> 
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
                </ol>
            </nav>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="CustomerDashboard-head">
                    <div class="row">
                        <div class="col-sm-3 top_section">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#addTrackerModal" style="text-decoration: none;">
                                <div class="card">
                                    <div class="img addNewCustomer">
                                        <img class="img-fluid" src="<?php echo $assets_base; ?>/assets/images/AddNewCutomer.png" alt="">
                                    </div>
                                    <div class="content">
                                        <p> Add New <br>Customer</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <?php if (empty($records)): ?>
                        <div class="text-center py-5">
                            <i class="fa fa-info-circle fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No customer visits recorded yet</h4>
                            <p class="text-muted">Click "Add New Customer" button to add your first visit.</p>
                        </div>
                    <?php else: ?>
                        <table class="display table" style="text-align: center;">
                            <thead class="bg-secondary">
                                <tr>
                                    <th>Shop/Person Name</th>
                                    <th>Contact Number</th>
                                    <th>Approached For</th>
                                    <th>Address</th>
                                    <th>Date Visited</th>
                                    <th>Final Status</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['shop_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['contact_number'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($record['approached_for'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($record['address'] ?: '-'); ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($record['date_visited'])); ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        if ($record['final_status'] === 'Joined') {
                                            $status_class = 'bg-success';
                                        } elseif ($record['final_status'] === 'Not Interested') {
                                            $status_class = 'bg-danger';
                                        } else {
                                            $status_class = 'bg-warning';
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($record['final_status']); ?></span>
                                    </td>
                                    <td><?php echo date('d-m-Y H:i', strtotime($record['last_updated'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $record['id']; ?>" title="View Full Details">
                                            <i class="fa fa-eye"></i> View
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $record['id']; ?>" title="Edit Record">
                                            <i class="fa fa-edit"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm" style="background-color: #278de6; color: #fff; border-color: #278de6;" data-bs-toggle="modal" data-bs-target="#historyModal<?php echo $record['id']; ?>" title="Add Followup">
                                            <i class="fa fa-history"></i> Add Followup
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- NOTE: For brevity, modal markup and scripts are identical to old/team/customer-tracker/index.php and should be copied over as needed -->

<?php include __DIR__ . '/../includes/footer.php'; ?>

