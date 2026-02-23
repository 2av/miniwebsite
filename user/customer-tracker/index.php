<?php
// Customer Tracker for TEAM members (copied from old/team/customer-tracker/index.php and adapted)

// Include centralized database + helpers
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/role_helper.php');

// Require TEAM role
require_role('TEAM', '/login/team.php');

/**
 * Ensure customer tracker tables do NOT depend on legacy `team_members` table.
 * Some DBs still have FK constraints referencing `team_members(id)` which will
 * break inserts when TEAM users are stored in `user_details`.
 */
function ensureCustomerTrackerForeignKeys(mysqli $connect): void {
    $tablesToFix = [
        // table => [columns_to_fix]
        'customer_tracker' => ['team_member_id'],
        'customer_tracker_followups' => ['team_member_id'],
        'customer_tracker_history' => ['team_member_id', 'changed_by'],
    ];

    foreach ($tablesToFix as $table => $columns) {
        foreach ($columns as $col) {
            // Find any FK on $col that references team_members
            $sql = "
                SELECT
                    kcu.CONSTRAINT_NAME,
                    kcu.REFERENCED_TABLE_NAME
                FROM information_schema.KEY_COLUMN_USAGE kcu
                WHERE kcu.TABLE_SCHEMA = DATABASE()
                  AND kcu.TABLE_NAME = ?
                  AND kcu.COLUMN_NAME = ?
                  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
                LIMIT 1
            ";
            $stmt = $connect->prepare($sql);
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('ss', $table, $col);
            $stmt->execute();
            $res = $stmt->get_result();
            $fk  = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!$fk) {
                continue;
            }

            $constraintName = $fk['CONSTRAINT_NAME'] ?? '';
            $refTable       = $fk['REFERENCED_TABLE_NAME'] ?? '';

            // If already pointing to user_details, nothing to do
            if (strcasecmp($refTable, 'user_details') === 0) {
                continue;
            }

            // Only auto-fix legacy `team_members` reference
            if (strcasecmp($refTable, 'team_members') !== 0 || $constraintName === '') {
                continue;
            }

            // Best-effort schema fix (may require ALTER privileges)
            try {
                // Ensure column type compatible with user_details.id (INT UNSIGNED)
                @$connect->query("ALTER TABLE `$table` MODIFY `$col` INT UNSIGNED NOT NULL");

                // Drop legacy FK
                $connect->query("ALTER TABLE `$table` DROP FOREIGN KEY `$constraintName`");

                // Add new FK to user_details(id)
                // NOTE: MySQL FK can't enforce role='TEAM', role logic is handled in app code.
                $newFkName = "{$table}_{$col}_ud_fk";
                $connect->query("ALTER TABLE `$table` ADD CONSTRAINT `$newFkName` FOREIGN KEY (`$col`) REFERENCES `user_details`(`id`) ON DELETE CASCADE");
            } catch (Throwable $e) {
                error_log("Failed to migrate FK for {$table}.{$col}: " . $e->getMessage());
                // If this fails, inserts may still fail; user will need to run ALTER TABLE manually.
            }
        }
    }
}

// Run FK migration before any insert/update
ensureCustomerTrackerForeignKeys($connect);

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
        
        if ($tracker_id > 0) {
            // Get current record (edit only updates shop_name, contact_number, approached_for, address)
            $get_stmt = $connect->prepare('SELECT shop_name, contact_number, approached_for, address FROM customer_tracker WHERE id = ? AND team_member_id = ?');
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
                    
                    // Update only the four edit fields
                    $update_stmt = $connect->prepare('UPDATE customer_tracker SET shop_name = ?, contact_number = ?, approached_for = ?, address = ? WHERE id = ? AND team_member_id = ?');
                    if ($update_stmt) {
                        $update_stmt->bind_param('ssssii', $new_shop_name, $new_contact_number, $new_approached_for, $new_address, $tracker_id, $team_member_id);
                        if ($update_stmt->execute()) {
                            // Record history for changed fields only
                            if ($old_shop_name !== $new_shop_name) {
                                $history_stmt = $connect->prepare('INSERT INTO customer_tracker_history (tracker_id, team_member_id, changed_field, old_value, new_value, change_type, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                                if ($history_stmt) {
                                    $changed_field = 'shop_name';
                                    $change_type = 'other_change';
                                    $history_stmt->bind_param('iissssi', $tracker_id, $team_member_id, $changed_field, $old_shop_name, $new_shop_name, $change_type, $team_member_id);
                                    $history_stmt->execute();
                                    $history_stmt->close();
                                }
                            }
                            if ($old_contact_number !== $new_contact_number) {
                                $history_stmt = $connect->prepare('INSERT INTO customer_tracker_history (tracker_id, team_member_id, changed_field, old_value, new_value, change_type, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                                if ($history_stmt) {
                                    $changed_field = 'contact_number';
                                    $change_type = 'other_change';
                                    $history_stmt->bind_param('iissssi', $tracker_id, $team_member_id, $changed_field, $old_contact_number, $new_contact_number, $change_type, $team_member_id);
                                    $history_stmt->execute();
                                    $history_stmt->close();
                                }
                            }
                            if ($old_approached_for !== $new_approached_for) {
                                $history_stmt = $connect->prepare('INSERT INTO customer_tracker_history (tracker_id, team_member_id, changed_field, old_value, new_value, change_type, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                                if ($history_stmt) {
                                    $changed_field = 'approached_for';
                                    $change_type = 'other_change';
                                    $history_stmt->bind_param('iissssi', $tracker_id, $team_member_id, $changed_field, $old_approached_for, $new_approached_for, $change_type, $team_member_id);
                                    $history_stmt->execute();
                                    $history_stmt->close();
                                }
                            }
                            if ($old_address !== $new_address) {
                                $history_stmt = $connect->prepare('INSERT INTO customer_tracker_history (tracker_id, team_member_id, changed_field, old_value, new_value, change_type, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                                if ($history_stmt) {
                                    $changed_field = 'address';
                                    $change_type = 'other_change';
                                    $history_stmt->bind_param('iissssi', $tracker_id, $team_member_id, $changed_field, $old_address, $new_address, $change_type, $team_member_id);
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
    // Join with unified user_details table to get TEAM member name
    $sql = '
        SELECT f.*, ud.name AS member_name
        FROM customer_tracker_followups f
        LEFT JOIN user_details ud ON f.team_member_id = ud.id AND ud.role = "TEAM"
        WHERE f.tracker_id = ?
        ORDER BY f.followup_datetime DESC, f.id DESC
    ';
    $stmt = $connect->prepare($sql);
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
                            <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#addTrackerModal" style="text-decoration: none;">
                                <div class="card">
                                    <div class="img addNewCustomer">
                                        <img class="img-fluid" src="<?php echo $assets_base; ?>/assets/images/AddNewCutomer.png" alt="">
                                    </div>
                                    <div class="content">
                                        <p> Add Customer <br>Visit</p>
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
                            <p class="text-muted">Click "Add Customer Visit" button to add your first visit.</p>
                        </div>
                    <?php else: ?>
                        <table class="display table" style="text-align: center;">
                            <thead class="bg-secondary">
                                <tr >
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

<!-- Add Customer Visit Modal (styled to match design) -->
<style>
#addTrackerModal .modal-header {  color: #fff; border-bottom: none; }
#addTrackerModal .modal-header .modal-title { display: flex; align-items: center; gap: 10px; font-weight: 600; }
#addTrackerModal .modal-header .modal-title .add-visit-icon { width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.2); display: inline-flex; align-items: center; justify-content: center; }
#addTrackerModal .modal-header .btn-close { filter: brightness(0) invert(1); opacity: 0.9; }
#addTrackerModal .modal-body { color: #fff; }
#addTrackerModal .modal-body .form-label { color: #fff; }
#addTrackerModal .modal-body .form-control,
#addTrackerModal .modal-body .form-select { background: #fff; color: #333; }
#addTrackerModal .modal-body .form-check-label { color: #fff; }
#addTrackerModal .modal-footer {  border-top: none; justify-content: flex-end; }
#addTrackerModal .modal-footer .btn-cancel-tracker { background: #6c757d; color: #fff; border-color: #6c757d; }
#addTrackerModal .modal-footer .btn-save-tracker { background: #f0ad4e; color: #fff; border-color: #f0ad4e; }
#addTrackerModal .modal-footer .btn-save-tracker:hover { background: #ec971f; border-color: #ec971f; color: #fff; }
/* Add Followup modal: dark blue header with icon; white form body; table dark with white text and yellow status badge; footer Close only */
.history-modal-popup .modal-header {  color: #fff; border-bottom: none; }
.history-modal-popup .modal-header .modal-title { display: flex; align-items: center; gap: 8px; font-weight: 600; }
.history-modal-popup .modal-header .btn-close { filter: brightness(0) invert(1); opacity: 0.9; }
.history-modal-popup .history-modal-body { background: #fff; color: #333; padding: 1.25rem; }
.history-modal-popup .history-modal-body .form-label { color: #333; }
.history-modal-popup .history-modal-body .form-control,
.history-modal-popup .history-modal-body .form-select { background: #fff; color: #333; border: 1px solid #ced4da; }
.history-modal-popup .history-modal-body .form-check-label { color: #333; }
.history-modal-popup .history-modal-body .add-followup-form-section { margin-bottom: 1rem; }
.history-modal-popup .history-modal-body hr { margin: 1.25rem 0; border-color: #dee2e6; }
.history-modal-popup .history-modal-body .followup-table-wrap { background: #2c3e50; padding: 0; border-radius: 4px; overflow: hidden; }
.history-modal-popup .history-modal-body .followup-table-wrap h6 { color: #fff; margin: 0.75rem 1rem; font-weight: 600; }
.history-modal-popup .history-modal-body .view-followup-table { margin-bottom: 0; color: #fff; }
.history-modal-popup .history-modal-body .view-followup-table thead th { background: #2c3e50; color: #fff !important; border-color: rgba(255,255,255,0.2); padding: 0.6rem 0.75rem; }
.history-modal-popup .history-modal-body .view-followup-table tbody td { background: #34495e; color: #fff !important; border-color: rgba(255,255,255,0.1); padding: 0.6rem 0.75rem; }
.history-modal-popup .history-modal-body .view-followup-table .add-followup-status-badge { padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; background: #f0ad4e; color: #333; }
.history-modal-popup .history-modal-body .text-muted { color: #6c757d !important; }
.history-modal-popup .modal-footer { background: #fff; border-top: 1px solid #dee2e6; }
.history-modal-popup .modal-footer .btn-followup-close { background: #6c757d; color: #fff; border-color: #6c757d; }
.history-modal-popup .modal-footer .btn-followup-close:hover { background: #5a6268; color: #fff; }
.history-modal-popup .btn-add-status { background: #f0ad4e; color: #333; border-color: #f0ad4e; font-weight: 500; }
.history-modal-popup .btn-add-status:hover { background: #ec971f; border-color: #ec971f; color: #333; }
/* View modal - Followup History (match design: title + Add Followup button, table with dark header, status pills, Close button) */
.view-followup-modal .view-followup-header { background: #fff; border-bottom: 1px solid #dee2e6; flex-wrap: wrap; gap: 0.5rem; }
.view-followup-modal .view-followup-header .modal-title { color: #333; font-weight: 600; }
.view-followup-modal .btn-add-followup-from-view { background: #f0ad4e; color: #fff; border-color: #f0ad4e; font-weight: 500; }
.view-followup-modal .btn-add-followup-from-view:hover { background: #ec971f; border-color: #ec971f; color: #fff; }
.view-followup-modal .view-followup-body { background: #fff; color: #333; }
.view-followup-modal .view-followup-thead { background: #2c3e50; color: #fff; }
.view-followup-modal .view-followup-thead th { border: none; font-weight: 600; padding: 0.75rem; color: #fff; }
.view-followup-modal .view-followup-table tbody tr { background: #fff; }
.view-followup-modal .view-followup-table tbody td { color: #333; padding: 0.75rem; vertical-align: middle; }
.view-followup-modal .view-followup-status-badge { display: inline-block; padding: 0.25rem 0.6rem; border-radius: 50px; font-size: 0.875rem; font-weight: 500; color: #333; }
.view-followup-modal .view-followup-badge-followup { background: #f0ad4e; color: #333; }
.view-followup-modal .view-followup-badge-joined { background: #5cb85c; color: #fff; }
.view-followup-modal .view-followup-badge-notinterested { background: #d9534f; color: #fff; }
.view-followup-modal .view-followup-footer { background: #fff; border-top: 1px solid #dee2e6; }
.view-followup-modal .btn-close-followup-view { background: #6c757d; color: #fff; border-color: #6c757d; }
.view-followup-modal .btn-close-followup-view:hover { background: #5a6268; border-color: #545b62; color: #fff; }
/* Edit Customer Visit modal - yellow header, dark blue body, radio for Approached For, Update button */
.edit-customer-visit-modal .modal-header { background: #f0ad4e; color: #333; border-bottom: none; }
.edit-customer-visit-modal .modal-header .modal-title { display: flex; align-items: center; gap: 8px; font-weight: 600; }
.edit-customer-visit-modal .modal-header .btn-close { opacity: 0.8; }
.edit-customer-visit-modal .modal-body {  color: #fff; }
.edit-customer-visit-modal .modal-body .form-label { color: #fff; }
.edit-customer-visit-modal .modal-body .form-control,
.edit-customer-visit-modal .modal-body .form-select { background: #fff; color: #333; border: 1px solid #ced4da; }
.edit-customer-visit-modal .modal-body .form-check-label { color: #fff; }
.edit-customer-visit-modal .modal-footer {  border-top: none; }
.edit-customer-visit-modal .modal-footer .btn-edit-cancel { background: #6c757d; color: #fff; border-color: #6c757d; }
.edit-customer-visit-modal .modal-footer .btn-edit-update { background: #f0ad4e; color: #333; border-color: #f0ad4e; }
.edit-customer-visit-modal .modal-footer .btn-edit-update:hover { background: #ec971f; border-color: #ec971f; color: #333; }
</style>
<div class="modal fade" id="addTrackerModal" tabindex="-1" aria-labelledby="addTrackerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTrackerModalLabel">
                    <span class="add-visit-icon"><i class="fa fa-plus"></i></span>
                    Add Customer Visit
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_tracker">

                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Name of Shop/Person <span class="text-danger">*</span></label>
                            <input type="text" name="shop_name" class="form-control" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                            <input type="text" name="contact_number" class="form-control" placeholder="e.g., +91 1234567890" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Approached For <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="approached_for" id="approached_franchisee" value="Franchisee Sale" required checked>
                                    <label class="form-check-label" for="approached_franchisee">Franchisee Sale</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="approached_for" id="approached_miniwebsite" value="MiniWebsite Sale">
                                    <label class="form-check-label" for="approached_miniwebsite">MiniWebsite Sale</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="approached_for" id="approached_both" value="Both">
                                    <label class="form-check-label" for="approached_both">Both</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3" style="resize: vertical;"></textarea>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Date Visited <span class="text-danger">*</span></label>
                            <input type="date" name="date_visited" class="form-control" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Final Status <span class="text-danger">*</span></label>
                            <select name="final_status" class="form-select" required>
                                <option value="Followup required" selected>Followup required</option>
                                <option value="Joined">Joined</option>
                                <option value="Not Interested">Not Interested</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Comments</label>
                            <textarea name="comments" class="form-control" rows="2" placeholder="Any additional notes..." style="resize: vertical;"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel-tracker" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-save-tracker"><i class="fa fa-save"></i> Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($records)): ?>
    <?php foreach ($records as $record): ?>
        <?php $tracker_id = (int)$record['id']; ?>
        <!-- View Details Modal - Followup History (styled to match design) -->
        <div class="modal fade view-followup-modal" id="viewModal<?php echo $tracker_id; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $tracker_id; ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header view-followup-header d-flex align-items-center justify-content-between flex-wrap">
                        <h5 class="modal-title mb-0" id="viewModalLabel<?php echo $tracker_id; ?>">Followup History</h5>
                        <button type="button" class="btn btn-add-followup-from-view" data-bs-dismiss="modal" data-history-modal="#historyModal<?php echo $tracker_id; ?>" aria-label="Add Followup">Add Followup</button>
                        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body view-followup-body">
                        <?php $followups = getTrackerFollowups($connect, $tracker_id); ?>
                        <?php if (empty($followups)): ?>
                            <p class="text-muted mb-0">No followups recorded yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm view-followup-table">
                                    <thead class="view-followup-thead">
                                        <tr>
                                            <th>Date &amp; Time</th>
                                            <th>Followup Method</th>
                                            <th>Followup Status</th>
                                            <th>Any Comments</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($followups as $f):
                                            $st = $f['followup_status'] ?? '';
                                            $badgeClass = 'view-followup-badge-followup';
                                            if ($st === 'Joined') $badgeClass = 'view-followup-badge-joined';
                                            elseif ($st === 'Not Interested') $badgeClass = 'view-followup-badge-notinterested';
                                        ?>
                                            <tr>
                                                <td><?php echo !empty($f['followup_datetime']) ? date('d-m-Y H:i', strtotime($f['followup_datetime'])) : '-'; ?></td>
                                                <td><?php echo htmlspecialchars($f['followup_method'] ?: '-'); ?></td>
                                                <td><span class="view-followup-status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($st ?: '-'); ?></span></td>
                                                <td><?php echo htmlspecialchars($f['comments'] ?: '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer view-followup-footer">
                        <button type="button" class="btn btn-close-followup-view" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Customer Visit Modal (styled to match design) -->
        <?php
        $edit_approached = trim($record['approached_for'] ?? '');
        if (!in_array($edit_approached, ['Franchisee Sale', 'MiniWebsite Sale', 'Both'], true)) {
            $edit_approached = 'Franchisee Sale';
        }
        ?>
        <div class="modal fade edit-customer-visit-modal" id="editModal<?php echo $tracker_id; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $tracker_id; ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel<?php echo $tracker_id; ?>">
                            <i class="fa fa-edit"></i> Edit Customer Visit
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post" action="">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="update_tracker">
                            <input type="hidden" name="tracker_id" value="<?php echo $tracker_id; ?>">

                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="form-label">Shop/Person Name <span class="text-danger">*</span></label>
                                    <input type="text" name="shop_name" class="form-control" value="<?php echo htmlspecialchars($record['shop_name']); ?>" required>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($record['contact_number'] ?: ''); ?>">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Approached For</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="approached_for" id="edit_approached_franchisee_<?php echo $tracker_id; ?>" value="Franchisee Sale" <?php echo ($edit_approached === 'Franchisee Sale') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="edit_approached_franchisee_<?php echo $tracker_id; ?>">Franchisee Sale</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="approached_for" id="edit_approached_miniwebsite_<?php echo $tracker_id; ?>" value="MiniWebsite Sale" <?php echo ($edit_approached === 'MiniWebsite Sale') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="edit_approached_miniwebsite_<?php echo $tracker_id; ?>">MiniWebsite Sale</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="approached_for" id="edit_approached_both_<?php echo $tracker_id; ?>" value="Both" <?php echo ($edit_approached === 'Both') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="edit_approached_both_<?php echo $tracker_id; ?>">Both</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="3" style="resize: vertical;"><?php echo htmlspecialchars($record['address'] ?: ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-edit-cancel" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-edit-update"><i class="fa fa-arrow-up"></i> Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Followup / History Modal (styled to match design) -->
        <div class="modal fade history-modal-popup" id="historyModal<?php echo $tracker_id; ?>" tabindex="-1" aria-labelledby="historyModalLabel<?php echo $tracker_id; ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="historyModalLabel<?php echo $tracker_id; ?>">
                            <i class="fa fa-refresh"></i> Add Followup
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body history-modal-body">
                        <form method="post" action="" class="add-followup-form-section">
                            <input type="hidden" name="action" value="add_followup">
                            <input type="hidden" name="tracker_id" value="<?php echo $tracker_id; ?>">

                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="form-label">Date &amp; Time <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="datetime-local" name="followup_datetime" class="form-control" required>
                                        <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                    </div>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Followup Method <span class="text-danger">*</span></label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="followup_method" id="followup_method_call_<?php echo $tracker_id; ?>" value="Call" required>
                                            <label class="form-check-label" for="followup_method_call_<?php echo $tracker_id; ?>">Call</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="followup_method" id="followup_method_visited_<?php echo $tracker_id; ?>" value="Visited">
                                            <label class="form-check-label" for="followup_method_visited_<?php echo $tracker_id; ?>">Visited</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="followup_method" id="followup_method_message_<?php echo $tracker_id; ?>" value="Message">
                                            <label class="form-check-label" for="followup_method_message_<?php echo $tracker_id; ?>">Message</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="followup_method" id="followup_method_email_<?php echo $tracker_id; ?>" value="Email">
                                            <label class="form-check-label" for="followup_method_email_<?php echo $tracker_id; ?>">Email</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Followup Status <span class="text-danger">*</span></label>
                                    <select name="followup_status" class="form-select" required>
                                        <option value="Followup required" selected>Followup Required</option>
                                        <option value="Joined">Joined</option>
                                        <option value="Not Interested">Not Interested</option>
                                    </select>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Any Comments</label>
                                    <textarea name="followup_comments" class="form-control" rows="2" placeholder="Any additional notes..." style="resize: vertical;"></textarea>
                                </div>
                                <div class="col-12 mb-0">
                                    <button type="submit" class="btn btn-add-status">Add Status</button>
                                </div>
                            </div>
                        </form>

                        <hr>
                        <div class="followup-table-wrap">
                            <h6>Followup Records</h6>
                            <?php $followups = getTrackerFollowups($connect, $tracker_id); ?>
                            <?php if (empty($followups)): ?>
                                <p class="text-muted mb-0 px-3 pb-3" style="color: #adb5bd !important;">No followups recorded yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm view-followup-table">
                                        <thead>
                                            <tr>
                                                <th>Date &amp; Time</th>
                                                <th>Followup Method</th>
                                                <th>Followup Status</th>
                                                <th>Any Comments</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($followups as $f):
                                                $st = $f['followup_status'] ?? '';
                                                $badgeClass = 'add-followup-status-badge';
                                            ?>
                                                <tr>
                                                    <td><?php echo !empty($f['followup_datetime']) ? date('d-m-Y H:i', strtotime($f['followup_datetime'])) : '-'; ?></td>
                                                    <td><?php echo htmlspecialchars($f['followup_method'] ?: '-'); ?></td>
                                                    <td><span class="<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($st ?: '-'); ?></span></td>
                                                    <td><?php echo htmlspecialchars($f['comments'] ?: '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-followup-close" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
(function() {
    document.querySelectorAll('.btn-add-followup-from-view').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var targetId = this.getAttribute('data-history-modal');
            var viewModal = this.closest('.modal');
            if (viewModal && targetId) {
                viewModal.addEventListener('hidden.bs.modal', function openHistory() {
                    var historyEl = document.querySelector(targetId);
                    if (historyEl && typeof bootstrap !== 'undefined') {
                        (new bootstrap.Modal(historyEl)).show();
                    }
                }, { once: true });
            }
        });
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

