<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mini Website</title>
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon.ico">
    <link rel="manifest" href="/assets/images/favicon.ico">

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Baloo+Bhai+2:wght@400..800&family=Baloo+Bhaina+2:wght@400..800&family=Barlow:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="assets/css/font-awesome.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">


<link rel="stylesheet" href="assets/css/owl.carousel.min.css">
<link rel="stylesheet" href="assets/css/owl.theme.default.css">
    <link rel="stylesheet" href="assets/css/animate.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/captcha.css">

</head>

<body>
    <header>
        <nav class="navbar navbar-expand-lg px-3">
            <div class="container">
                <a class="navbar-brand" href="/index.php">
                    <img class="img-fluid" src="assets/images/main-logo.png" alt="logo">
                </a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <img src="assets/images/navbar-img.png" alt="Menu" width="30" height="30" class="img-fluid">
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ml-auto">
                    <?php
                    // Get the current page filename
                    $current_page = basename($_SERVER['PHP_SELF']);
                    $current_path = $_SERVER['PHP_SELF'];

                    // Show all menu items except on franchisee.php and manage_referrals.php
                    if ($current_page !== 'franchisee.php' && $current_page !== 'refer-and-earn.php' && strpos($current_path, 'manage_referrals.php') === false) {
                    ?>
                        <li class="nav-item active">
                            <a class="nav-link DemoSamples" href="javascript:void(0)">Demo Samples</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link OurPlans" href="javascript:void(0)">Our Plans</a>
                        </li>
                        <?php
                    }
                    ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle btn-primary" href="#" role="button" data-toggle="dropdown"
                                aria-expanded="false">
                                Earn With Us <i class="fa fa-angle-double-down"></i>
                            </a>
                             <div class="dropdown-menu scrollable-dropdown">
                                <a class="dropdown-item" href="refer-and-earn.php">Refer & Earn</a>
                                <a class="dropdown-item" href="franchisee.php"> Franchise Partner</a>
                            </div> 
                        </li>
                   
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown"
                                aria-expanded="false">
                                Login <i class="fa fa-angle-double-down"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="panel/login/login.php">Customer Login</a>
                                <a class="dropdown-item" href="panel/franchisee-login/login.php">Franchise Login</a>
                                <a class="dropdown-item" href="team/login.php">Team Login</a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Bootstrap JavaScript -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    
    <style>
    /* Scrollable dropdown styles for main header */
    .scrollable-dropdown {
        max-height: 300px;
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-width: thin;
        scrollbar-color: #6c757d #f8f9fa;
    }
    
    .scrollable-dropdown::-webkit-scrollbar {
        width: 6px;
    }
    
    .scrollable-dropdown::-webkit-scrollbar-track {
        background: #f8f9fa;
        border-radius: 3px;
    }
    
    .scrollable-dropdown::-webkit-scrollbar-thumb {
        background: #6c757d;
        border-radius: 3px;
    }
    
    .scrollable-dropdown::-webkit-scrollbar-thumb:hover {
        background: #495057;
    }
    
    .scrollable-dropdown .dropdown-item {
        padding: 8px 15px;
       
        transition: all 0.2s ease;
    }
    
    
    
    .scrollable-dropdown .dropdown-item i {
        margin-right: 8px;
        width: 16px;
        text-align: center;
    }
    </style>
    
    <script>
    // Initialize dropdown functionality for main header
    $(document).ready(function() {
        // Enable Bootstrap dropdowns
        $('.dropdown-toggle').dropdown();
        
        // Close dropdown when clicking on menu items
        $('.dropdown-item').on('click', function() {
            $(this).closest('.dropdown-menu').removeClass('show');
        });
        
        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.dropdown').length) {
                $('.dropdown-menu').removeClass('show');
            }
        });
        
        // Close dropdown on escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.dropdown-menu').removeClass('show');
            }
        });
        
        // Smooth scrolling for dropdown
        $('.scrollable-dropdown').on('wheel', function(e) {
            e.preventDefault();
            $(this).scrollTop($(this).scrollTop() + e.originalEvent.deltaY);
        });
    });
    </script>