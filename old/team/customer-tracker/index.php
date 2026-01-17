<?php
// Start session and include database connection first
require_once('../../common/config.php');

// Get team member ID from session
$team_member_id = $_SESSION['team_member_id'] ?? 0;
$user_email = $_SESSION['user_email'] ?? '';


// Handle form submissions BEFORE including header (to allow redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_tracker') {
        $shop_name = trim($_POST['shop_name'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $approached_for = trim($_POST['approached_for'] ?? 'Franchisee Sale');
        $address = trim($_POST['address'] ?? '');
        $date_visited = trim($_POST['date_visited'] ?? '');
        $final_status = trim($_POST['final_status'] ?? 'Followup required');
        $comments = trim($_POST['comments'] ?? '');

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
                        $field = 'initial_entry';
                        $old_val = '';
                        $new_val = 'Record created';
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
        $tracker_id = intval($_POST['tracker_id'] ?? 0);
        $new_shop_name = trim($_POST['shop_name'] ?? '');
        $new_contact_number = trim($_POST['contact_number'] ?? '');
        $new_approached_for = trim($_POST['approached_for'] ?? 'Franchisee Sale');
        $new_address = trim($_POST['address'] ?? '');
        $new_status = trim($_POST['final_status'] ?? '');
        $new_comments = trim($_POST['comments'] ?? '');
        
        if ($tracker_id > 0) {
            // Get current record
            $get_stmt = $connect->prepare('SELECT shop_name, contact_number, approached_for, address, final_status, comments FROM customer_tracker WHERE id = ? AND team_member_id = ?');
            if ($get_stmt) {
                $get_stmt->bind_param('ii', $tracker_id, $team_member_id);
                $get_stmt->execute();
                $current = $get_stmt->get_result()->fetch_assoc();
                $get_stmt->close();
                
                if ($current) {
                    $old_shop_name = $current['shop_name'];
                    $old_contact_number = $current['contact_number'] ?? '';
                    $old_approached_for = $current['approached_for'] ?? '';
                    $old_address = $current['address'] ?? '';
                    $old_status = $current['final_status'];
                    $old_comments = $current['comments'] ?? '';
                    
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
        $tracker_id = intval($_POST['tracker_id'] ?? 0);
        $followup_datetime_raw = trim($_POST['followup_datetime'] ?? '');
        $followup_methods = $_POST['followup_method'] ?? [];
        if (!is_array($followup_methods)) {
            $followup_methods = [$followup_methods];
        }
        // Filter empty values and implode as CSV
        $followup_methods = array_filter($followup_methods, function($m) {
            return trim($m) !== '';
        });
        $followup_method = implode(', ', $followup_methods);
        $followup_status = trim($_POST['followup_status'] ?? '');
        $followup_comments = trim($_POST['followup_comments'] ?? '');

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
                    // Also update main record's final_status, comments and keep last_updated via followups table
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
$message = '';
$message_type = '';

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

// Now include the header after all potential redirects
include '../header.php';

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
                                        <img class="img-fluid" src="../../Common/assets/img/AddNewCutomer.png" alt="">
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

<!-- Modals for all records -->
<?php foreach ($records as $record): ?>
                                        <!-- View Modal -->
                                        <div class="modal fade" id="viewModal<?php echo $record['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-xl">
                                                <div class="modal-content" style="background-color: #fff;">
                                                  
                                                    <div class="modal-body" style="background-color: #fff; color: #333;">
                                                      
                                                        
                                                         <!-- Followup History Table -->
                                                         <h3 style="color: #333; margin-bottom: 20px;">
                                                             Followup History
                                                             <button
                                                                 type="button"
                                                                 class="btn btn-sm btn-primary ms-2 js-open-followup-from-view"
                                                                 data-tracker-id="<?php echo $record['id']; ?>"
                                                             >
                                                                 Add Followup
                                                             </button>
                                                         </h3>
                                                        <?php 
                                                        $followups = getTrackerFollowups($connect, $record['id']);
                                                        if (empty($followups)): ?>
                                                            <div class="alert alert-info mb-0">
                                                                <i class="fa fa-info-circle"></i> No followups added yet for this record.
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-bordered" style="background-color: #fff;">
                                                                    <thead class="table-dark">
                                                                        <tr>
                                                                            <th style="color: #fff;">Date &amp; Time</th>
                                                                            <th style="color: #fff;">Followup Method</th>
                                                                            <th style="color: #fff;">Followup Status</th>
                                                                            <th style="color: #fff;">Any Comments</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody style="background-color: #fff;">
                                                                        <?php foreach ($followups as $fu): ?>
                                                                        <tr style="background-color: #fff;">
                                                                            <td style="color: #333;"><?php echo date('d-m-Y H:i', strtotime($fu['followup_datetime'])); ?></td>
                                                                            <td style="color: #333;"><?php echo htmlspecialchars($fu['followup_method']); ?></td>
                                                                            <td style="color: #333;">
                                                                                <?php
                                                                                $fu_status_class = '';
                                                                                if ($fu['followup_status'] === 'Joined') {
                                                                                    $fu_status_class = 'bg-success';
                                                                                } elseif ($fu['followup_status'] === 'Not Interested') {
                                                                                    $fu_status_class = 'bg-danger';
                                                                                } else {
                                                                                    $fu_status_class = 'bg-warning';
                                                                                }
                                                                                ?>
                                                                                <span class="badge <?php echo $fu_status_class; ?>"><?php echo htmlspecialchars($fu['followup_status']); ?></span>
                                                                            </td>
                                                                            <td style="color: #333;"><?php echo htmlspecialchars($fu['comments'] ?: '-'); ?></td>
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

                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal<?php echo $record['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-warning text-dark">
                                                        <h5 class="modal-title"><i class="fa fa-edit"></i> Edit Customer Visit</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="action" value="update_tracker">
                                                        <input type="hidden" name="tracker_id" value="<?php echo $record['id']; ?>">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="final_status" value="<?php echo htmlspecialchars($record['final_status']); ?>">
                                                            <input type="hidden" name="comments" value="<?php echo htmlspecialchars($record['comments'] ?? ''); ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label"><strong>Shop/Person Name:</strong></label>
                                                                <input type="text" class="form-control" name="shop_name" value="<?php echo htmlspecialchars($record['shop_name']); ?>">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label"><strong>Contact Number:</strong></label>
                                                                <input type="text" class="form-control" name="contact_number" value="<?php echo htmlspecialchars($record['contact_number'] ?: ''); ?>">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label"><strong>Approached For:</strong></label>
                                                                <div class="d-flex flex-wrap">
                                                                    <div class="form-check form-check-inline mr-3 mb-2">
                                                                        <input class="form-check-input" type="radio" name="approached_for" id="edit_approached_franchisee_<?php echo $record['id']; ?>" value="Franchisee Sale" <?php echo (($record['approached_for'] ?? 'Franchisee Sale') === 'Franchisee Sale') ? 'checked' : ''; ?>>
                                                                        <label class="form-check-label" for="edit_approached_franchisee_<?php echo $record['id']; ?>">Franchisee Sale</label>
                                                                    </div>
                                                                    <div class="form-check form-check-inline mr-3 mb-2">
                                                                        <input class="form-check-input" type="radio" name="approached_for" id="edit_approached_miniwebsite_<?php echo $record['id']; ?>" value="MiniWebsite Sale" <?php echo (($record['approached_for'] ?? '') === 'MiniWebsite Sale') ? 'checked' : ''; ?>>
                                                                        <label class="form-check-label" for="edit_approached_miniwebsite_<?php echo $record['id']; ?>">MiniWebsite Sale</label>
                                                                    </div>
                                                                    <div class="form-check form-check-inline mr-3 mb-2">
                                                                        <input class="form-check-input" type="radio" name="approached_for" id="edit_approached_both_<?php echo $record['id']; ?>" value="Both" <?php echo (($record['approached_for'] ?? '') === 'Both') ? 'checked' : ''; ?>>
                                                                        <label class="form-check-label" for="edit_approached_both_<?php echo $record['id']; ?>">Both</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label"><strong>Address:</strong></label>
                                                                <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($record['address'] ?: ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> Update</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Add Followup / History Modal -->
                                        <div class="modal fade" id="historyModal<?php echo $record['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content" style="background-color: #fff;">
                                                    <div class="modal-header text-white" style="background-color: #002169;">
                                                        <h5 class="modal-title"><i class="fa fa-history"></i> Add Followup</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body" style="background-color: #fff; color: #333;">
                                                        <!-- Add Followup Form -->
                                                        <form method="POST" action="">
                                                            <input type="hidden" name="action" value="add_followup">
                                                            <input type="hidden" name="tracker_id" value="<?php echo $record['id']; ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label"><strong>Date &amp; Time<span class="text-danger">*</span></strong></label>
                                                                <input type="datetime-local" class="form-control" name="followup_datetime" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label"><strong>Followup Method<span class="text-danger">*</span></strong></label>
                                                                <div class="d-flex flex-wrap">
                                                                    <div class="form-check form-check-inline mr-3 mb-2">
                                                                        <input class="form-check-input" type="radio" name="followup_method[]" id="followup_call_<?php echo $record['id']; ?>" value="Call">
                                                                        <label class="form-check-label" for="followup_call_<?php echo $record['id']; ?>">Call</label>
                                                                    </div>
                                                                    <div class="form-check form-check-inline mr-3 mb-2">
                                                                        <input class="form-check-input" type="radio" name="followup_method[]" id="followup_visited_<?php echo $record['id']; ?>" value="Visited">
                                                                        <label class="form-check-label" for="followup_visited_<?php echo $record['id']; ?>">Visited</label>
                                                                    </div>
                                                                    <div class="form-check form-check-inline mr-3 mb-2">
                                                                        <input class="form-check-input" type="radio" name="followup_method[]" id="followup_message_<?php echo $record['id']; ?>" value="Message">
                                                                        <label class="form-check-label" for="followup_message_<?php echo $record['id']; ?>">Message</label>
                                                                    </div>
                                                                    <div class="form-check form-check-inline mr-3 mb-2">
                                                                        <input class="form-check-input" type="radio" name="followup_method[]" id="followup_email_<?php echo $record['id']; ?>" value="Email">
                                                                        <label class="form-check-label" for="followup_email_<?php echo $record['id']; ?>">Email</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label"><strong>Followup Status<span class="text-danger">*</span></strong></label>
                                                                <select class="form-select" name="followup_status" required>
                                                                    <option value="Joined">Joined</option>
                                                                    <option value="Not Interested">Not Interested</option>
                                                                    <option value="Followup required" selected>Followup Required</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label"><strong>Any Comments</strong></label>
                                                                <textarea class="form-control" name="followup_comments" rows="3"></textarea>
                                                            </div>
                                                            <div class="mb-3">
                                                                <button type="submit" class="btn btn-primary">
                                                                     Add Status
                                                                </button>
                                                            </div>
                                                        </form>

                                                        <hr>

                                                        <!-- Followup History List -->
                                                        <?php 
                                                        $followups = getTrackerFollowups($connect, $record['id']);
                                                        if (empty($followups)): ?>
                                                            <div class="alert alert-info mb-0">
                                                                <i class="fa fa-info-circle"></i> No followups added yet for this record.
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-bordered" style="background-color: #fff;">
                                                                    <thead class="table-dark">
                                                                        <tr>
                                                                            <th style="color: #fff;">Date &amp; Time</th>
                                                                            <th style="color: #fff;">Followup Method</th>
                                                                            <th style="color: #fff;">Followup Status</th>
                                                                            <th style="color: #fff;">Any Comments</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody style="background-color: #fff;">
                                                                        <?php foreach ($followups as $fu): ?>
                                                                        <tr style="background-color: #fff;">
                                                                            <td style="color: #333;"><?php echo date('d-m-Y H:i', strtotime($fu['followup_datetime'])); ?></td>
                                                                            <td style="color: #333;"><?php echo htmlspecialchars($fu['followup_method']); ?></td>
                                                                            <td style="color: #333;">
                                                                                <?php
                                                                                $fu_status_class = '';
                                                                                if ($fu['followup_status'] === 'Joined') {
                                                                                    $fu_status_class = 'bg-success';
                                                                                } elseif ($fu['followup_status'] === 'Not Interested') {
                                                                                    $fu_status_class = 'bg-danger';
                                                                                } else {
                                                                                    $fu_status_class = 'bg-warning';
                                                                                }
                                                                                ?>
                                                                                <span class="badge <?php echo $fu_status_class; ?>"><?php echo htmlspecialchars($fu['followup_status']); ?></span>
                                                                            </td>
                                                                            <td style="color: #333;"><?php echo htmlspecialchars($fu['comments'] ?: '-'); ?></td>
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

<!-- Add Customer Visit Modal -->
<div class="modal fade" id="addTrackerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa fa-plus-circle"></i> Add Customer Visit</h5>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_tracker">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="shop_name" class="form-label">Name of Shop/Person <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="shop_name" name="shop_name" required>
                    </div>

                    <div class="mb-3">
                        <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="contact_number" name="contact_number" placeholder="e.g., +91 1234567890" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Approached For <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap">
                            <div class="form-check form-check-inline mr-3 mb-2">
                                <input class="form-check-input" type="radio" name="approached_for" id="approached_franchisee" value="Franchisee Sale" checked required>
                                <label class="form-check-label" for="approached_franchisee">Franchisee Sale</label>
                            </div>
                            <div class="form-check form-check-inline mr-3 mb-2">
                                <input class="form-check-input" type="radio" name="approached_for" id="approached_miniwebsite" value="MiniWebsite Sale" required>
                                <label class="form-check-label" for="approached_miniwebsite">MiniWebsite Sale</label>
                            </div>
                            <div class="form-check form-check-inline mr-3 mb-2">
                                <input class="form-check-input" type="radio" name="approached_for" id="approached_both" value="Both" required>
                                <label class="form-check-label" for="approached_both">Both</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="date_visited" class="form-label">Date Visited <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_visited" name="date_visited" required value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="final_status" class="form-label">Final Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="final_status" name="final_status" required>
                            <option value="Joined">Joined</option>
                            <option value="Not Interested">Not Interested</option>
                            <option value="Followup required" selected>Followup required</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="comments" class="form-label">Comments</label>
                        <textarea class="form-control" id="comments" name="comments" rows="3" placeholder="Any additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Save Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap === 'undefined') {
        return;
    }

    // Global safeguard: when ANY modal is fully hidden, make sure
    // any stray Bootstrap backdrops are removed and body is reset.
    document.addEventListener('hidden.bs.modal', function () {
        var backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(function (bd) {
            if (bd.parentNode) {
                bd.parentNode.removeChild(bd);
            }
        });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
    });

    var followupButtons = document.querySelectorAll('.js-open-followup-from-view');
    followupButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var trackerId = this.getAttribute('data-tracker-id');
            var viewModalEl = this.closest('.modal');
            var followupModalEl = document.getElementById('historyModal' + trackerId);
            if (!followupModalEl) return;

            var openFollowup = function () {
                var followupInstance = bootstrap.Modal.getOrCreateInstance(followupModalEl);
                followupInstance.show();
                if (viewModalEl) {
                    viewModalEl.removeEventListener('hidden.bs.modal', openFollowup);
                }
            };

            if (viewModalEl) {
                var viewInstance = bootstrap.Modal.getInstance(viewModalEl);
                if (viewInstance) {
                    viewModalEl.addEventListener('hidden.bs.modal', openFollowup);
                    viewInstance.hide();
                } else {
                    openFollowup();
                }
            } else {
                openFollowup();
            }
        });
    });
});

// Ensure sidebar toggle works on mobile
(function() {
    function initSidebarToggle() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                document.body.classList.toggle('sb-sidenav-toggled');
                localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
            });
        }
    }
    
    // Try multiple times to ensure it works
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebarToggle);
    } else {
        initSidebarToggle();
    }
    
    // Also try after a short delay in case scripts load late
    setTimeout(initSidebarToggle, 100);
})();
</script>
<style>
    .table-container thead tr th, .table-container tr td {
    padding-left: 30px !important;
    font-weight: 500 !important;
    font-size:16px;
}
.CustomerDashboard-head .card .img.addNewCustomer{
    width: 70px !important;
}
@media (max-width: 768px) {
    
    .Copyright-left,
.Copyright-right{
    padding:0px;
}}

</style>    
<?php include '../footer.php'; ?>

