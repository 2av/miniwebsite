<?php
require_once(__DIR__ . '/../app/config/database.php');
require('header.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MiniWebsite</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 2px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                   
                    
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="create_account.php">
                            <i class="fas fa-plus-circle me-2"></i> Create New Miniwebsite
                        </a>
                        <a class="nav-link" href="manage_user.php">
                            <i class="fas fa-users me-2"></i> User Details
                        </a>
                      
                        <a class="nav-link" href="manage_user_card.php">
                            <i class="fas fa-credit-card me-2"></i> Miniwebsites Details
                        </a>
                        <a class="nav-link" href="manage_franchisee.php">
                            <i class="fas fa-user-tie me-2"></i> Franchisee Details
                        </a>

                        <a class="nav-link" href="manage_franchisee_distributor.php">
                            <i class="fas fa-handshake me-2"></i> Franchisee Distributor
                        </a>
                        <a class="nav-link" href="all_orders_invoice.php">
                            <i class="fas fa-file-invoice-dollar"></i> All Orders
                        </a>
                        
                       
                        <a class="nav-link" href="manage_referrals.php">
                            <i class="fas fa-handshake me-2"></i> Manage Referrals
                        </a>
                        <a class="nav-link" href="manage_deals.php">
                            <i class="fas fa-tags me-2"></i> Deals & Coupons
                        </a>
                        <a class="nav-link" href="add_money.php">
                            <i class="fas fa-wallet me-2"></i> Recharge Wallet
                        </a>
                        <a class="nav-link" href="manage_faq.php">
                            <i class="fas fa-question-circle me-2"></i> FAQ Management
                        </a>
                        <a class="nav-link" href="manage_content.php">
                            <i class="fas fa-file-alt me-2"></i> Content Management
                        </a>
                        <a class="nav-link" href="kit_management.php">
                            <i class="fas fa-toolbox me-2"></i> Kit Management
                        </a>
                        
                        <a class="nav-link" href="change-password.php">
                            <i class="fas fa-key me-2"></i> Change Password
                        </a>
                        
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content p-4">
                    <!-- Page Header -->
                    <div class="page-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1">Dashboard Overview</h2>
                                <p class="text-muted mb-0">Welcome back, Admin! Here's what's happening today.</p>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">Last updated: <?php echo date('M d, Y H:i'); ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card" onclick="location.href='manage_user_card.php'">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                                        <i class="fas fa-globe"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h3 class="mb-0"><?php 
                                            $query = mysqli_query($connect,'SELECT * FROM digi_card');
                                            echo mysqli_num_rows($query); 
                                        ?></h3>
                                        <p class="text-muted mb-0">Total Miniwebsites</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card" onclick="location.href='manage_franchisee_card.php'">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                                        <i class="fas fa-store"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h3 class="mb-0"><?php 
                                            $query = mysqli_query($connect,'SELECT * FROM digi_card WHERE f_user_email!=""');
                                            echo mysqli_num_rows($query); 
                                        ?></h3>
                                        <p class="text-muted mb-0">Franchisee Sites</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card" onclick="location.href='manage_franchisee.php'">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h3 class="mb-0"><?php 
                                            // Query user_details table for franchisees
                                            $query = mysqli_query($connect,'SELECT * FROM user_details WHERE role="FRANCHISEE"');
                                            echo mysqli_num_rows($query); 
                                        ?></h3>
                                        <p class="text-muted mb-0">All Franchisee</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card" onclick="location.href='manage_user.php'">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a, #fee140);">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h3 class="mb-0"><?php 
                                            // Query user_details table for customers
                                            $query = mysqli_query($connect,'SELECT * FROM user_details WHERE role="CUSTOMER"');
                                            echo mysqli_num_rows($query); 
                                        ?></h3>
                                        <p class="text-muted mb-0">All Users</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Summary -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white border-0 py-3">
                                    <h5 class="mb-0">Account Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <?php
                                        $stats = [
                                            ['label' => 'Total MiniWebsites', 'query' => 'SELECT * FROM digi_card', 'icon' => 'fas fa-globe', 'color' => '#667eea'],
                                            ['label' => 'Active MiniWebsites', 'query' => 'SELECT * FROM digi_card WHERE d_payment_status="Success"', 'icon' => 'fas fa-check-circle', 'color' => '#28a745'],
                                            ['label' => 'Inactive MiniWebsites', 'query' => 'SELECT * FROM digi_card WHERE d_payment_status="Failed"', 'icon' => 'fas fa-times-circle', 'color' => '#dc3545'],
                                            ['label' => 'Trial MiniWebsites', 'query' => 'SELECT * FROM digi_card WHERE d_payment_status="Created"', 'icon' => 'fas fa-clock', 'color' => '#ffc107']
                                        ];

                                        foreach($stats as $stat) {
                                            $query = mysqli_query($connect, $stat['query']);
                                            $count = mysqli_num_rows($query);
                                            echo '<div class="col-md-6">
                                                    <div class="d-flex align-items-center p-3 bg-light rounded">
                                                        <i class="'.$stat['icon'].'" style="color: '.$stat['color'].'; font-size: 24px;"></i>
                                                        <div class="ms-3">
                                                            <h6 class="mb-0">'.$stat['label'].'</h6>
                                                            <h4 class="mb-0 text-primary">'.$count.'</h4>
                                                        </div>
                                                    </div>
                                                  </div>';
                                        }

                                        $payment_query = mysqli_query($connect,'SELECT SUM(d_payment_amount) as payment FROM digi_card WHERE d_payment_status="Success"');
                                        $payment_row = mysqli_fetch_array($payment_query);
                                        echo '<div class="col-12">
                                                <div class="d-flex align-items-center p-3 bg-success bg-opacity-10 rounded">
                                                    <i class="fas fa-rupee-sign" style="color: #28a745; font-size: 24px;"></i>
                                                    <div class="ms-3">
                                                        <h6 class="mb-0">Payment Total</h6>
                                                        <h4 class="mb-0 text-success">₹'.number_format($payment_row['payment'],2).'</h4>
                                                    </div>
                                                </div>
                                              </div>';
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white border-0 py-3">
                                    <h5 class="mb-0">Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="create_account.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Create New Miniwebsite
                                        </a>
                                        <a href="manage_deals.php" class="btn btn-outline-primary">
                                            <i class="fas fa-tags me-2"></i>Manage Deals
                                        </a>
                                        <a href="manage_referrals.php" class="btn btn-outline-success">
                                            <i class="fas fa-handshake me-2"></i>Manage Referrals
                                        </a>
                                        <a href="all_orders.php" class="btn btn-outline-info">
                                            <i class="fas fa-shopping-cart me-2"></i>View All Orders
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<!-- Start Footer Area -->
<footer class="footer-area">
    <!-- Empty footer with height -->
    <div style="height: 80px;"></div>
</footer>



