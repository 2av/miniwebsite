<?php
require_once(__DIR__ . '/../app/config/database.php');
 
require('header.php');

// Handle deal mapping
if(isset($_POST['map_deal'])) {
    $user_email = mysqli_real_escape_string($connect, $_POST['user_email']);
    $deal_id = mysqli_real_escape_string($connect, $_POST['deal_id']);
    
    if(!empty($deal_id)) {
        // Check if mapping already exists
        $check_query = mysqli_query($connect, "SELECT * FROM deal_customer_mapping WHERE deal_id='$deal_id' AND customer_email='$user_email'");
        if(mysqli_num_rows($check_query) == 0) {
            $created_by = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : 'admin';
            $insert = mysqli_query($connect, "INSERT INTO deal_customer_mapping (deal_id, customer_email, created_by, created_date) VALUES ('$deal_id', '$user_email', '$created_by', NOW())");
            if($insert) {
                echo '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Deal mapped successfully!</div>';
            } else {
                echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error mapping deal!</div>';
            }
        } else {
            echo '<div class="alert alert-warning"><i class="fas fa-info-circle me-2"></i>Deal already mapped to this user!</div>';
        }
    }
}

// Handle franchise deal mapping
if(isset($_POST['map_deal_franchise'])) {
    $user_email = mysqli_real_escape_string($connect, $_POST['user_email']);
    $deal_id = mysqli_real_escape_string($connect, $_POST['deal_id']);
    
    if(!empty($deal_id)) {
        // Check if mapping already exists
        $check_query = mysqli_query($connect, "SELECT * FROM deal_customer_mapping WHERE deal_id='$deal_id' AND customer_email='$user_email'");
        if(mysqli_num_rows($check_query) == 0) {
            $created_by = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : 'admin';
            $insert = mysqli_query($connect, "INSERT INTO deal_customer_mapping (deal_id, customer_email, created_by, created_date) VALUES ('$deal_id', '$user_email', '$created_by', NOW())");
            if($insert) {
                echo '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Franchise deal mapped successfully!</div>';
            } else {
                echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error mapping franchise deal!</div>';
            }
        } else {
            echo '<div class="alert alert-warning"><i class="fas fa-info-circle me-2"></i>Franchise deal already mapped to this user!</div>';
        }
    }
}

// Handle deal removal
if(isset($_GET['remove_deal'])) {
    $mapping_id = mysqli_real_escape_string($connect, $_GET['remove_deal']);
    $delete = mysqli_query($connect, "DELETE FROM deal_customer_mapping WHERE id='$mapping_id'");
    if($delete) {
        echo '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Deal mapping removed successfully!</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<div class="main-content">
    <div class="page-header">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
        <h2><i class="fas fa-users me-3"></i>User Management</h2>
        <p>Manage users, deals, Sales Kit, and Collaboration status</p>
    </div>
    
    <!-- Debug Info -->
    <?php
    $debug_mw_deals = mysqli_query($connect, "SELECT COUNT(*) as count FROM deals WHERE deal_status='Active' AND plan_type='MiniWebsite'");
    $debug_franchise_deals = mysqli_query($connect, "SELECT COUNT(*) as count FROM deals WHERE deal_status='Active' AND plan_type='Franchise'");
    $mw_count = mysqli_fetch_array($debug_mw_deals)['count'];
    $franchise_count = mysqli_fetch_array($debug_franchise_deals)['count'];
    ?>
    <div style="background: #e9ecef; padding: 10px; margin: 10px 0; border-radius: 5px; font-size: 12px;">
        <strong>Debug Info:</strong> Mini Website Deals: <?php echo $mw_count; ?>, Franchise Deals: <?php echo $franchise_count; ?>
    </div>

    <!-- Stats Row -->
    <div class="row stats-row">
        <?php
        // Build the same where conditions for stats (now for ALL customers, regardless of collaboration)
        $stats_where_conditions = array();
        
        // Status filter for stats (map YES/NO to ACTIVE/INACTIVE)
        if(isset($_GET['status_filter']) && $_GET['status_filter']!='') {
            $status_value = $_GET['status_filter'] == 'YES' ? 'ACTIVE' : 'INACTIVE';
            $stats_where_conditions[] = "cl.status='".$status_value."'";
        }
        
        // Search filter for stats
        if(isset($_GET['search']) && $_GET['search']!='') {
            $search = mysqli_real_escape_string($connect, $_GET['search']);
            $stats_where_conditions[] = "(cl.name LIKE '%$search%' OR cl.email LIKE '%$search%' OR cl.phone LIKE '%$search%')";
        }
        
        // Date filter for stats
        if(isset($_GET['date_filter']) && $_GET['date_filter']!='') {
            switch($_GET['date_filter']) {
                case 'today':
                    $stats_where_conditions[] = "DATE(cl.created_at) = CURDATE()";
                    break;
                case 'week':
                    $stats_where_conditions[] = "cl.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                    break;
                case 'month':
                    $stats_where_conditions[] = "cl.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                    break;
                case 'year':
                    $stats_where_conditions[] = "cl.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                    break;
            }
        }
        
        // Add role filter for customers only
        $stats_where_conditions[] = "cl.role='CUSTOMER'";
        
        // Rebuild stats where clause
        $stats_where_clause = '';
        if(!empty($stats_where_conditions)) {
            $stats_where_clause = 'WHERE ' . implode(' AND ', $stats_where_conditions);
        }
        
        // Count all users with filters
        $total_users_query = "SELECT cl.* FROM user_details cl $stats_where_clause";
        $total_users = mysqli_num_rows(mysqli_query($connect, $total_users_query));
        
        // Count active users with filters
        $active_where_clause = $stats_where_clause;
        if(empty($stats_where_conditions)) {
            $active_where_clause = "WHERE cl.status='ACTIVE' AND cl.role='CUSTOMER' AND (cl.collaboration_enabled IS NULL OR cl.collaboration_enabled = 'NO')";
        } else {
            $active_where_clause .= " AND cl.status='ACTIVE'";
        }
        $active_users = mysqli_num_rows(mysqli_query($connect, "SELECT cl.* FROM user_details cl $active_where_clause"));
        
        // Count users with deals - Apply ALL filters including deal_filter and website_filter
        $deal_query = "SELECT DISTINCT cl.email FROM user_details cl";
        
        // Apply deal filter
        if(isset($_GET['deal_filter']) && $_GET['deal_filter'] == 'mapped') {
            $deal_query .= " INNER JOIN deal_customer_mapping dcm ON BINARY cl.email = BINARY dcm.customer_email";
        } elseif(isset($_GET['deal_filter']) && $_GET['deal_filter'] == 'unmapped') {
            $deal_query .= " LEFT JOIN deal_customer_mapping dcm ON BINARY cl.email = BINARY dcm.customer_email";
            $stats_where_conditions[] = "dcm.customer_email IS NULL";
        }
        
        // Apply website filter
        if(isset($_GET['website_filter']) && $_GET['website_filter']!='') {
            $deal_query .= " LEFT JOIN (SELECT user_email, COUNT(*) as website_count FROM digi_card GROUP BY user_email) dc ON cl.email = dc.user_email";
            switch($_GET['website_filter']) {
                case '0':
                    $stats_where_conditions[] = "(dc.website_count IS NULL OR dc.website_count = 0)";
                    break;
                case '1-5':
                    $stats_where_conditions[] = "dc.website_count BETWEEN 1 AND 5";
                    break;
                case '6-10':
                    $stats_where_conditions[] = "dc.website_count BETWEEN 6 AND 10";
                    break;
                case '10+':
                    $stats_where_conditions[] = "dc.website_count > 10";
                    break;
            }
        }
        
        // Rebuild where clause with all conditions
        $final_where_clause = '';
        if(!empty($stats_where_conditions)) {
            $final_where_clause = 'WHERE ' . implode(' AND ', $stats_where_conditions);
        }
        
        $deal_query .= " $final_where_clause";
        $mapped_deals = mysqli_num_rows(mysqli_query($connect, $deal_query));
        
        $total_websites = mysqli_num_rows(mysqli_query($connect, "SELECT * FROM digi_card"));
        ?>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="fas fa-users"></i>
                </div>
                <h4 class="mb-1"><?php echo $total_users; ?></h4>
                <p class="text-muted mb-0">Total Users</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <i class="fas fa-user-check"></i>
                </div>
                <h4 class="mb-1"><?php echo $active_users; ?></h4>
                <p class="text-muted mb-0">Active Users</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                    <i class="fas fa-handshake"></i>
                </div>
                <h4 class="mb-1"><?php echo $mapped_deals; ?></h4>
                <p class="text-muted mb-0">Users with Deals</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                    <i class="fas fa-globe"></i>
                </div>
                <h4 class="mb-1"><?php echo $total_websites; ?></h4>
                <p class="text-muted mb-0">Total Websites</p>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="table-card">
                <div class="card-header">
                    <i class="fas fa-filter me-2"></i>
                    Filters & Search
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="page_no" value="1">
                        
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status_filter" class="form-select">
                                <option value="">All Status</option>
                                <option value="YES" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter']=='YES') ? 'selected' : ''; ?>>Active</option>
                                <option value="NO" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter']=='NO') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Deal Status</label>
                            <select name="deal_filter" class="form-select">
                                <option value="">All Users</option>
                                <option value="mapped" <?php echo (isset($_GET['deal_filter']) && $_GET['deal_filter']=='mapped') ? 'selected' : ''; ?>>With Deals</option>
                                <option value="unmapped" <?php echo (isset($_GET['deal_filter']) && $_GET['deal_filter']=='unmapped') ? 'selected' : ''; ?>>Without Deals</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Website Count</label>
                            <select name="website_filter" class="form-select">
                                <option value="">All Counts</option>
                                <option value="0" <?php echo (isset($_GET['website_filter']) && $_GET['website_filter']=='0') ? 'selected' : ''; ?>>No Websites</option>
                                <option value="1-5" <?php echo (isset($_GET['website_filter']) && $_GET['website_filter']=='1-5') ? 'selected' : ''; ?>>1-5 Websites</option>
                                <option value="6-10" <?php echo (isset($_GET['website_filter']) && $_GET['website_filter']=='6-10') ? 'selected' : ''; ?>>6-10 Websites</option>
                                <option value="10+" <?php echo (isset($_GET['website_filter']) && $_GET['website_filter']=='10+') ? 'selected' : ''; ?>>10+ Websites</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Date Range</label>
                            <select name="date_filter" class="form-select">
                                <option value="">All Time</option>
                                <option value="today" <?php echo (isset($_GET['date_filter']) && $_GET['date_filter']=='today') ? 'selected' : ''; ?>>Today</option>
                                <option value="week" <?php echo (isset($_GET['date_filter']) && $_GET['date_filter']=='week') ? 'selected' : ''; ?>>This Week</option>
                                <option value="month" <?php echo (isset($_GET['date_filter']) && $_GET['date_filter']=='month') ? 'selected' : ''; ?>>This Month</option>
                                <option value="year" <?php echo (isset($_GET['date_filter']) && $_GET['date_filter']=='year') ? 'selected' : ''; ?>>This Year</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Search by name, email, or contact..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i>Apply Filters
                            </button>
                            <a href="manage_user.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                            <button type="button" class="btn btn-success" onclick="exportData()">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Filters Display -->
    <?php if(isset($_GET['status_filter']) || isset($_GET['deal_filter']) || isset($_GET['website_filter']) || isset($_GET['date_filter']) || isset($_GET['search'])): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="active-filters">
                <span class="filter-label">Active Filters:</span>
                <?php if(isset($_GET['status_filter']) && $_GET['status_filter']!=''): ?>
                    <span class="filter-tag">Status: <?php echo $_GET['status_filter']=='YES' ? 'Active' : 'Inactive'; ?></span>
                <?php endif; ?>
                <?php if(isset($_GET['deal_filter']) && $_GET['deal_filter']!=''): ?>
                    <span class="filter-tag">Deal: <?php echo ucfirst($_GET['deal_filter']); ?></span>
                <?php endif; ?>
                <?php if(isset($_GET['website_filter']) && $_GET['website_filter']!=''): ?>
                    <span class="filter-tag">Websites: <?php echo $_GET['website_filter']; ?></span>
                <?php endif; ?>
                <?php if(isset($_GET['date_filter']) && $_GET['date_filter']!=''): ?>
                    <span class="filter-tag">Date: <?php echo ucfirst($_GET['date_filter']); ?></span>
                <?php endif; ?>
                <?php if(isset($_GET['search']) && $_GET['search']!=''): ?>
                    <span class="filter-tag">Search: "<?php echo htmlspecialchars($_GET['search']); ?>"</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Users Table -->
    <div class="table-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-table me-2"></i>
                Users & Deal Mapping
            </div>
            
        </div>
        <div class="table-responsive table-container">
            <table class="table table-striped table-hover modern-table" style="text-align: center;">
                <thead class="bg-secondary">
                    <tr>
                        <th>User ID</th>
                        <th>User Email</th>
                        <th>User Name</th>
                        <th>User Number</th>
                        <th>Joined On</th>
                        <th>Referral Source</th>
                        <th>No. of MW</th>
                        <th>Pending Amount</th>
                        <th>Referral Details</th>
                        <th>Deal For MW</th>
                        <th>Deal For Franchise</th>
                        <th>Sales Kit</th>
                        <th>Collaboration</th>
                        <th>Refund</th>
                        <th>Reset Password</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(isset($_GET['page_no'])){
                    } else {
                        $_GET['page_no']='1';
                    }

                    // Number of records per page
                    $limit=10;
                    $start_from=($_GET['page_no']-1)*$limit;
                    
                    // Build query with search filter only
                    $where_conditions = array();
                    
                    // Add role filter for customers only
                    $where_conditions[] = "role='CUSTOMER'";
                    
                    // Search filter only
                    if(isset($_GET['search']) && $_GET['search']!='') {
                        $search = mysqli_real_escape_string($connect, $_GET['search']);
                        $where_conditions[] = "(name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
                    }
                    
                    // Add status filter if set
                    if(isset($_GET['status_filter']) && $_GET['status_filter']!='') {
                        $status_value = $_GET['status_filter'] == 'YES' ? 'ACTIVE' : 'INACTIVE';
                        $where_conditions[] = "status='".$status_value."'";
                    }
                    
                    // Add deal filter
                    if(isset($_GET['deal_filter']) && $_GET['deal_filter'] == 'mapped') {
                        $where_conditions[] = "email IN (SELECT DISTINCT customer_email FROM deal_customer_mapping)";
                    } elseif(isset($_GET['deal_filter']) && $_GET['deal_filter'] == 'unmapped') {
                        $where_conditions[] = "email NOT IN (SELECT DISTINCT customer_email FROM deal_customer_mapping WHERE customer_email IS NOT NULL)";
                    }
                    
                    // Add website filter
                    if(isset($_GET['website_filter']) && $_GET['website_filter']!='') {
                        switch($_GET['website_filter']) {
                            case '0':
                                $where_conditions[] = "email NOT IN (SELECT DISTINCT user_email FROM digi_card WHERE user_email IS NOT NULL)";
                                break;
                            case '1-5':
                                $where_conditions[] = "email IN (SELECT user_email FROM digi_card GROUP BY user_email HAVING COUNT(*) BETWEEN 1 AND 5)";
                                break;
                            case '6-10':
                                $where_conditions[] = "email IN (SELECT user_email FROM digi_card GROUP BY user_email HAVING COUNT(*) BETWEEN 6 AND 10)";
                                break;
                            case '10+':
                                $where_conditions[] = "email IN (SELECT user_email FROM digi_card GROUP BY user_email HAVING COUNT(*) > 10)";
                                break;
                        }
                    }
                    
                    // Add date filter
                    if(isset($_GET['date_filter']) && $_GET['date_filter']!='') {
                        switch($_GET['date_filter']) {
                            case 'today':
                                $where_conditions[] = "DATE(created_at) = CURDATE()";
                                break;
                            case 'week':
                                $where_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                                break;
                            case 'month':
                                $where_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                                break;
                            case 'year':
                                $where_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                                break;
                        }
                    }
                    
                    $where_clause = '';
                    if(!empty($where_conditions)) {
                        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
                    }
                    
                    $query = mysqli_query($connect, "SELECT id, email, phone, name, status, created_at, collaboration_enabled, saleskit_enabled, refund_status, refund_status_date, referred_by FROM user_details $where_clause ORDER BY id DESC LIMIT $start_from, $limit");

                    if(mysqli_num_rows($query)>0){
                        while($row=mysqli_fetch_array($query)){
                            // Map user_details fields to old field names for compatibility
                            $user_email = $row['email'] ?? '';
                            $user_name = $row['name'] ?? '';
                            $user_contact = $row['phone'] ?? '';
                            $user_active = ($row['status'] ?? 'INACTIVE') === 'ACTIVE' ? 'YES' : 'NO';
                            $uploaded_date = $row['created_at'] ?? '';
                            
                            $query2=mysqli_query($connect,'SELECT * FROM digi_card WHERE user_email="'.$user_email.'" ORDER BY id DESC ');
                            $website_count = mysqli_num_rows($query2);
                            
                            // Check for Mini Website deals
                            $mapped_mw_deal_query = mysqli_query($connect, "SELECT dcm.*, d.deal_name, d.coupon_code FROM deal_customer_mapping dcm JOIN deals d ON dcm.deal_id = d.id WHERE dcm.customer_email='".$user_email."' AND d.plan_type='MiniWebsite' LIMIT 1");
                            $has_mw_deal = mysqli_num_rows($mapped_mw_deal_query) > 0;
                            
                            // Check for Franchise deals
                            $mapped_franchise_deal_query = mysqli_query($connect, "SELECT dcm.*, d.deal_name, d.coupon_code FROM deal_customer_mapping dcm JOIN deals d ON dcm.deal_id = d.id WHERE dcm.customer_email='".$user_email."' AND d.plan_type='Franchise' LIMIT 1");
                            $has_franchise_deal = mysqli_num_rows($mapped_franchise_deal_query) > 0;
                            
                                                         echo '<tr>';
                             echo '<td>'.$row['id'].'</td>';
                             echo '<td>'.htmlspecialchars($user_email).'</td>';
                             echo '<td>'.htmlspecialchars($user_name).'</td>';
                             echo '<td>'.htmlspecialchars($user_contact).'</td>';
                             // Joined On
                             echo '<td><small class="text-muted">'.(!empty($uploaded_date) ? date('M d, Y', strtotime($uploaded_date)) : '-').'</small></td>';
                            // Referral Source (formatted, using unified user_details)
                            $ref_source_display = 'Direct';
                            $ref_by = isset($row['referred_by']) ? trim($row['referred_by']) : '';
                            if($ref_by !== '') {
                                $safe_ref_by = mysqli_real_escape_string($connect, $ref_by);
                                // Look up referrer in unified users table
                                $ref_user_q = mysqli_query($connect, "SELECT id, role FROM user_details WHERE email='".$safe_ref_by."' LIMIT 1");
                                if($ref_user_q && mysqli_num_rows($ref_user_q) > 0){
                                    $ref_user = mysqli_fetch_array($ref_user_q);
                                    $ref_id   = intval($ref_user['id']);
                                    $ref_role = strtoupper($ref_user['role'] ?? '');
                                    
                                    if ($ref_role === 'FRANCHISEE') {
                                        // Franchisee style label (FR-XXX)
                                        $ref_source_display = 'FR - '.str_pad($ref_id, 3, '0', STR_PAD_LEFT);
                                    } elseif ($ref_role === 'TEAM') {
                                        $ref_source_display = 'Team - '.$ref_id;
                                    } elseif ($ref_role === 'ADMIN') {
                                        $ref_source_display = 'Admin - '.$ref_id;
                                    } else {
                                        // Default for regular customers
                                        $ref_source_display = 'User - '.$ref_id;
                                    }

                                    // Check if collaboration referral recorded for this pair
                                    $safe_user_email = mysqli_real_escape_string($connect, $user_email);
                                    $frd_q = mysqli_query(
                                        $connect,
                                        "SELECT 1 FROM referral_earnings 
                                         WHERE referrer_email='".$safe_ref_by."' 
                                           AND referred_email='".$safe_user_email."' 
                                           AND is_collaboration='YES' 
                                         LIMIT 1"
                                    );
                                    if($frd_q && mysqli_num_rows($frd_q) > 0){
                                        $ref_source_display .= ' (FRD)';
                                    }
                                } else {
                                    // Fallback: show raw referrer email if not found in unified table
                                    $ref_source_display = 'Ref: '.$ref_by;
                                }
                            }
                            echo '<td>'.htmlspecialchars($ref_source_display).'</td>';
                             // No. of Websites
                             echo '<td><span class="websites-count">'.$website_count.'</span></td>';
                             
                             // Pending referral amount calculation
                             $ref_summary_q = mysqli_query($connect, "SELECT 
                                 COALESCE(SUM(re.amount), 0) AS total_referral_amount,
                                 COALESCE((
                                     SELECT SUM(rph2.amount) 
                                     FROM referral_payment_history rph2 
                                     INNER JOIN referral_earnings re2 ON rph2.referral_id = re2.id 
                                     WHERE BINARY re2.referrer_email = BINARY re.referrer_email
                                 ), 0) AS total_paid_amount
                                 FROM referral_earnings re 
                                 WHERE re.referrer_email = '".mysqli_real_escape_string($connect, $user_email)."'");
                             $total_referral_amount = 0;
                             $total_paid_amount = 0;
                             if($ref_summary_q && mysqli_num_rows($ref_summary_q) > 0){
                                 $ref_summary = mysqli_fetch_array($ref_summary_q);
                                 $total_referral_amount = (float)($ref_summary['total_referral_amount'] ?? 0);
                                 $total_paid_amount = (float)($ref_summary['total_paid_amount'] ?? 0);
                             }
                             $pending_amount = $total_referral_amount - $total_paid_amount;
                             if($pending_amount < 0){ $pending_amount = 0; }
                             echo '<td>₹'.number_format($pending_amount, 0).'</td>';
                            // Referral Details popup trigger
                            echo '<td><button type="button" class="btn btn-sm btn-outline-primary" onclick="showReferralDetails(\''.$user_email.'\')">View</button></td>';
                            
                            // Deal for MW
                            echo '<td>';
                            if($has_mw_deal) {
                                $deal_row = mysqli_fetch_array($mapped_mw_deal_query);
                                echo '<span class="deal-badge">';
                                echo substr($deal_row['deal_name'], 0, 15) . '...';
                                echo '<a href="?remove_deal='.$deal_row['id'].'" onclick="return confirm(\'Remove this deal mapping?\')" class="remove-deal">×</a>';
                                echo '</span>';
                            } else {
                                echo '<form method="POST" class="deal-form">';
                                echo '<input type="hidden" name="user_email" value="'.$user_email.'">';
                                echo '<input type="hidden" name="map_deal" value="1">';
                                echo '<select name="deal_id" class="form-select form-select-sm" required>';
                                echo '<option value="">Select Deal</option>';
                                
                                $deals_query = mysqli_query($connect, "SELECT * FROM deals WHERE deal_status='Active' AND plan_type='MiniWebsite' ORDER BY deal_name");
                                if(mysqli_num_rows($deals_query) > 0) {
                                    while($deal = mysqli_fetch_array($deals_query)) {
                                        echo '<option value="'.$deal['id'].'">'.substr($deal['deal_name'], 0, 20).'...</option>';
                                    }
                                } else {
                                    echo '<option value="" disabled>No Mini Website deals available</option>';
                                }
                                
                                echo '</select>';
                                echo '</form>';
                            }
                            echo '</td>';


                            echo '<td>';
                            if($has_franchise_deal) {
                                $deal_row = mysqli_fetch_array($mapped_franchise_deal_query);
                                echo '<span class="deal-badge">';
                                echo substr($deal_row['deal_name'], 0, 15) . '...';
                                echo '<a href="?remove_deal='.$deal_row['id'].'" onclick="return confirm(\'Remove this deal mapping?\')" class="remove-deal">×</a>';
                                echo '</span>';
                            } else {
                                echo '<form method="POST" class="deal-form-franchise">';
                                echo '<input type="hidden" name="user_email" value="'.$user_email.'">';
                                echo '<input type="hidden" name="map_deal_franchise" value="1">';
                                echo '<select name="deal_id" class="form-select form-select-sm" required>';
                                echo '<option value="">Select Deal</option>';
                                
                                $deals_query = mysqli_query($connect, "SELECT * FROM deals WHERE deal_status='Active' AND plan_type='Franchise' ORDER BY deal_name");
                                if(mysqli_num_rows($deals_query) > 0) {
                                    while($deal = mysqli_fetch_array($deals_query)) {
                                        echo '<option value="'.$deal['id'].'">'.substr($deal['deal_name'], 0, 20).'...</option>';
                                    }
                                } else {
                                    echo '<option value="" disabled>No Franchise deals available</option>';
                                }
                                
                                echo '</select>';
                                echo '</form>';
                            }
                            echo '</td>'; 
                            
                            
                            // Sales Kit toggle (normalize to YES/NO safely)
                            $saleskit_status_raw = isset($row['saleskit_enabled']) ? $row['saleskit_enabled'] : 'NO';
                            $saleskit_status = strtoupper(trim($saleskit_status_raw ?: 'NO'));
                            echo '<td class="collab-cell">';
                            echo '<label class="switch">';
                            echo '<input type="checkbox" class="saleskit-toggle" data-user-email="'.$user_email.'" '.($saleskit_status == 'YES' ? 'checked' : '').'>';
                            echo '<span class="slider"></span>';
                            echo '</label>';
                            echo '</td>';
                            
                            // Collaboration toggle (normalize to YES/NO safely)
                            $collab_status_raw = isset($row['collaboration_enabled']) ? $row['collaboration_enabled'] : 'NO';
                            $collab_status = strtoupper(trim($collab_status_raw ?: 'NO'));
                            echo '<td class="collab-cell">';
                            echo '<label class="switch">';
                            echo '<input type="checkbox" class="collaboration-toggle" data-user-email="'.$user_email.'" '.($collab_status == 'YES' ? 'checked' : '').'>';
                            echo '<span class="slider"></span>';
                            echo '</label>';
                            echo '</td>';
                            
                            // Refund status cell: dropdown for None/Claimed, static for Settled with date
                            $refund_status = isset($row['refund_status']) && $row['refund_status'] !== '' ? $row['refund_status'] : 'None';
                            $refund_status_date = isset($row['refund_status_date']) ? $row['refund_status_date'] : null;
                            echo '<td>';
                            if($refund_status === 'Refund Settled'){
                                $dateText = ($refund_status_date && $refund_status_date !== '0000-00-00 00:00:00') ? date('d-m-Y', strtotime($refund_status_date)) : '';
                                echo '<span class="badge bg-success">Refund Settled'.($dateText ? ' on '.$dateText : '').'</span>';
                            } else {
                                echo '<select class="form-select form-select-sm refund-status" data-user-email="'.htmlspecialchars($user_email).'">';
                                $refundOptions = array('None', 'Refund Claimed', 'Refund Settled');
                                foreach($refundOptions as $opt){
                                    $selected = ($refund_status === $opt) ? ' selected' : '';
                                    echo '<option value="'.htmlspecialchars($opt).'"'.$selected.'>'.htmlspecialchars($opt).'</option>';
                                }
                                echo '</select>';
                                if($refund_status === 'Refund Claimed'){
                                    $dateText = ($refund_status_date && $refund_status_date !== '0000-00-00 00:00:00') ? date('d-m-Y', strtotime($refund_status_date)) : date('d-m-Y');
                                    echo '<div><small class="text-muted">Refund claimed on '.$dateText.'</small></div>';
                                }
                            }
                            echo '</td>';
                            
                            // Reset password action
                            echo '<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="openResetPasswordModal(\''.addslashes($user_email).'\')">Reset</button></td>';
                            echo '</tr>';
                        }
                                         } else {
                         echo '<tr><td colspan="13" class="text-center py-4">No users found matching the filters</td></tr>';
                     }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .active-filters {
            background: white;
            padding: 15px 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-label {
            font-weight: 600;
            color: #495057;
        }
        
        .filter-tag {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .table-actions {
            display: flex;
            gap: 10px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .form-select, .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
        }
        
        .btn-outline-secondary, .btn-success {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
        }
         .collab-cell {
            width: 100px;
            text-align: center;
            padding: 15px;
        }

        /* Toggle Switch Styles */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 30px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 24px;
            width: 24px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        input:checked + .slider {
            background-color: #4CAF50;
        }

        input:focus + .slider {
            box-shadow: 0 0 1px #4CAF50;
        }

        input:checked + .slider:before {
            transform: translateX(30px);
        }

        /* Add ON/OFF text */
        .slider:after {
            content: 'OFF';
            color: white;
            display: block;
            position: absolute;
            transform: translate(-50%,-50%);
            top: 50%;
            left: 70%;
            font-size: 10px;
            font-weight: bold;
            font-family: Arial, sans-serif;
        }

        input:checked + .slider:after {
            content: 'ON';
            left: 30%;
        }

        /* Hover effect */
        .slider:hover {
            background-color: #bbb;
        }

        input:checked + .slider:hover {
            background-color: #45a049;
        }

        /* Animation */
        .slider, .slider:before {
            transition: all 0.3s ease;
        }

    </style>

    <script>
        
        // Show referral details for the selected user in a popup
        function showReferralDetails(userEmail){
            fetch('get_collaboration_details.php?referrer_email=' + encodeURIComponent(userEmail))
                .then(resp => resp.text())
                .then(html => {
                    const modal = document.getElementById('refDetailsModal');
                    const body = document.getElementById('refDetailsBody');
                    body.innerHTML = html;
                    // Execute any scripts included in the fetched HTML so inline handlers initialize
                    const scripts = Array.from(body.querySelectorAll('script'));
                    scripts.forEach((oldScript) => {
                        const newScript = document.createElement('script');
                        // Copy attributes (e.g., src, type)
                        Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                        if (!oldScript.src) {
                            newScript.textContent = oldScript.textContent;
                        }
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    });
                    modal.style.display = 'block';
                })
                .catch(() => {
                    alert('Failed to load referral details');
                });
        }
        // Collaboration & Sales Kit toggle handlers with confirmation (no auto page refresh)
        document.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('collaboration-toggle')) {
                const userEmail = e.target.getAttribute('data-user-email');
                const newStatus = e.target.checked ? 'YES' : 'NO';
                const toggleElement = e.target;
                
                // Ask for confirmation before changing collaboration status
                const actionText = newStatus === 'YES' ? 'enable Collaboration for' : 'disable Collaboration for';
                if (!confirm('Are you sure you want to ' + actionText + ' ' + userEmail + '?')) {
                    // Revert toggle if cancelled
                    toggleElement.checked = !toggleElement.checked;
                    return;
                }

                fetch('js_request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        user_email: userEmail,
                        toggle_user_collaboration: 'YES',
                        collaboration_status: newStatus
                    })
                })
                .then(resp => resp.text())
                .then(text => {
                    // On success, we keep the new toggle state and optionally show backend message
                    if (!text.toLowerCase().includes('success')) {
                        alert('Failed to update collaboration status: ' + text);
                        toggleElement.checked = !toggleElement.checked;
                    }
                })
                .catch((error) => {
                    alert('Error updating collaboration status: ' + error);
                    toggleElement.checked = !toggleElement.checked;
                });
            }
            if (e.target && e.target.classList.contains('saleskit-toggle')) {
                const userEmail = e.target.getAttribute('data-user-email');
                const newStatus = e.target.checked ? 'YES' : 'NO';
                const toggleElement = e.target;
                
                // Ask for confirmation before changing sales kit status
                const actionText = newStatus === 'YES' ? 'enable Sales Kit for' : 'disable Sales Kit for';
                if (!confirm('Are you sure you want to ' + actionText + ' ' + userEmail + '?')) {
                    // Revert toggle if cancelled
                    toggleElement.checked = !toggleElement.checked;
                    return;
                }

                fetch('js_request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        user_email: userEmail,
                        toggle_user_saleskit: 'YES',
                        saleskit_status: newStatus
                    })
                })
                .then(resp => resp.text())
                .then(text => {
                    // On success, we keep the new toggle state and optionally show backend message
                    if (!text.toLowerCase().includes('success')) {
                        alert('Failed to update saleskit status: ' + text);
                        toggleElement.checked = !toggleElement.checked;
                    }
                })
                .catch((error) => {
                    alert('Error updating saleskit status: ' + error);
                    toggleElement.checked = !toggleElement.checked;
                });
            }
        });
        function selectAll() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        }
        
        function bulkAction() {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            if(checkedBoxes.length === 0) {
                alert('Please select users first');
                return;
            }
            
            if(confirm('Are you sure you want to perform bulk action on ' + checkedBoxes.length + ' users?')) {
                // Implement bulk action logic here
                console.log('Bulk action on:', Array.from(checkedBoxes).map(cb => cb.value));
            }
        }
        
        function exportData() {
            // Get current filters
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = '?' + params.toString();
        }
        
        (function(){
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', selectAll);
            }
        })();
        
        // Confirm then save deal mapping via AJAX (no direct auto-submit)
        document.addEventListener('change', function(e){
            if(e.target && e.target.name === 'deal_id'){
                const form = e.target.closest('form');
                if(form && (form.classList.contains('deal-form') || form.classList.contains('deal-form-franchise'))){
                    const dealText = e.target.options[e.target.selectedIndex].textContent.trim();
                    if(e.target.value){
                        const isFranchise = form.classList.contains('deal-form-franchise');
                        const msg = 'Map this user to "' + dealText + '" for ' + (isFranchise ? 'Franchise' : 'Mini Website') + '?';
                        if(confirm(msg)){
                            const formData = new FormData(form);
                            fetch(window.location.href, {
                                method: 'POST',
                                body: formData
                            })
                            .then(r => r.text())
                            .then(() => { window.location.reload(); })
                            .catch(() => { alert('Failed to save mapping. Please try again.'); });
                        } else {
                            e.target.value = '';
                        }
                    }
                }
            }
            if(e.target && e.target.classList.contains('refund-status')){
                const select = e.target;
                const userEmail = select.getAttribute('data-user-email');
                const newStatus = select.value;
                const msg = 'Set refund status to "' + newStatus + '" for ' + userEmail + '?';
                if(confirm(msg)){
                    const params = new URLSearchParams({
                        update_refund_status: 'YES',
                        user_email: userEmail,
                        refund_status: newStatus
                    });
                    fetch('js_request.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: params
                    })
                    .then(r => r.text())
                    .then(t => {
                        if(!t.includes('success')){
                            alert('Failed to update refund status');
                        }
                    })
                    .catch(() => alert('Failed to update refund status'));
                } else {
                    // Revert selection if cancelled by reloading this row state
                    window.location.reload();
                }
            }
        });
    </script>


    <!-- Referral Details Modal -->
    <div class="modal" id="refDetailsModal" style="display:none;">
        <div class="modal-content" style="max-width:1100px;width:95%;margin:3% auto;">
            <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;background:#4a90e2;color:#fff;padding:12px 16px;">
                <h3 style="margin:0;font-size:18px;">Referral Details</h3>
                <span class="close" onclick="document.getElementById('refDetailsModal').style.display='none'" style="cursor:pointer;font-size:22px;">&times;</span>
            </div>
            <div class="modal-body" id="refDetailsBody" style="padding:0;max-height:75vh;overflow:auto;"></div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="pagination-modern">
        <?php 
        // Query user_details table for customers (all, regardless of collaboration)
        $query2 = mysqli_query($connect,"SELECT COUNT(*) AS total FROM user_details WHERE role='CUSTOMER'");
        $rowCount = $query2 ? mysqli_fetch_assoc($query2) : ['total' => 0];
        $pages = ceil(($rowCount['total'] ?? 0) / $limit);

        if ($pages > 1) {
            $current = isset($_GET['page_no']) ? max(1, (int)$_GET['page_no']) : 1;

            // Helper to build URL preserving other query params
            $baseParams = $_GET;
            unset($baseParams['page_no']);

            $buildUrl = function($page) use ($baseParams) {
                $params = $baseParams;
                $params['page_no'] = $page;
                return '?'.http_build_query($params);
            };

            // First & Prev
            if ($current > 1) {
                echo '<a href="'.$buildUrl(1).'" class="page-btn-modern">&laquo;</a>';
                echo '<a href="'.$buildUrl($current-1).'" class="page-btn-modern">&lsaquo;</a>';
            }

            // Window of max 5 page numbers around current
            $window = 5;
            $start = max(1, $current - 2);
            $end   = min($pages, $start + $window - 1);
            // Adjust start if we're near the end
            $start = max(1, $end - $window + 1);

            for ($i = $start; $i <= $end; $i++) {
                $activeClass = ($i === $current) ? ' active' : '';
                echo '<a href="'.$buildUrl($i).'" class="page-btn-modern'.$activeClass.'">'.$i.'</a>';
            }

            // Next & Last
            if ($current < $pages) {
                echo '<a href="'.$buildUrl($current+1).'" class="page-btn-modern">&rsaquo;</a>';
                echo '<a href="'.$buildUrl($pages).'" class="page-btn-modern">&raquo;</a>';
            }
        }
        ?>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="resetPasswordModalLabel">Reset Customer Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <div class="text-muted" style="font-size:13px;">You are updating password for:</div>
          <div><strong id="resetPwEmailText"></strong></div>
        </div>
        <div id="resetPwMsg" class="mt-2"></div>
        <input type="hidden" id="resetPwEmail" value="">
        <div class="mb-3 mt-3">
          <label class="form-label">New Password</label>
          <input type="password" class="form-control" id="resetPwNew" placeholder="Enter new password (min 6 chars)">
        </div>
        <div class="mb-3">
          <label class="form-label">Confirm Password</label>
          <input type="password" class="form-control" id="resetPwConfirm" placeholder="Confirm new password">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="submitResetPassword()">Update Password</button>
      </div>
    </div>
  </div>
</div>

<script>
  function openResetPasswordModal(email){
    document.getElementById('resetPwEmail').value = email;
    document.getElementById('resetPwEmailText').textContent = email;
    document.getElementById('resetPwNew').value = '';
    document.getElementById('resetPwConfirm').value = '';
    document.getElementById('resetPwMsg').innerHTML = '';
    const modalEl = document.getElementById('resetPasswordModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }

  function submitResetPassword(){
    const email = document.getElementById('resetPwEmail').value;
    const newPw = document.getElementById('resetPwNew').value;
    const conf  = document.getElementById('resetPwConfirm').value;

    if(!email){
      document.getElementById('resetPwMsg').innerHTML = '<div class="alert alert-danger mb-0">Email missing.</div>';
      return;
    }
    if(!newPw || newPw.length < 6){
      document.getElementById('resetPwMsg').innerHTML = '<div class="alert alert-warning mb-0">Password must be at least 6 characters.</div>';
      return;
    }
    if(newPw !== conf){
      document.getElementById('resetPwMsg').innerHTML = '<div class="alert alert-warning mb-0">Passwords do not match.</div>';
      return;
    }
    if(!confirm('Reset password for ' + email + '?')){
      return;
    }

    const params = new URLSearchParams({
      email: email,
      role: 'CUSTOMER',
      new_password: newPw,
      confirm_password: conf
    });

    document.getElementById('resetPwMsg').innerHTML = '<div class="alert alert-info mb-0">Updating...</div>';

    fetch('reset_user_password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'same-origin',
      body: params
    })
    .then(r => r.json())
    .then(data => {
      if(data && data.success){
        document.getElementById('resetPwMsg').innerHTML = '<div class="alert alert-success mb-0">' + (data.message || 'Password updated') + '</div>';
      } else {
        document.getElementById('resetPwMsg').innerHTML = '<div class="alert alert-danger mb-0">' + (data.message || 'Failed to update password') + '</div>';
      }
    })
    .catch(() => {
      document.getElementById('resetPwMsg').innerHTML = '<div class="alert alert-danger mb-0">Failed to update password.</div>';
    });
  }
</script>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  
</head>

  


