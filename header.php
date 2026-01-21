<?php
// Dynamic base path:
// - localhost subfolder: /miniwebsite
// - server root: '' (empty)
if (!function_exists('mw_base_path')) {
    function mw_base_path(): string {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        // If running under /miniwebsite/* keep that, otherwise assume root
        if (strpos($scriptName, '/miniwebsite/') === 0 || $scriptName === '/miniwebsite') {
            return '/miniwebsite';
        }
        return '';
    }
}
$base_path = mw_base_path();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mini Website</title>
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $base_path; ?>/assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $base_path; ?>/assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $base_path; ?>/assets/images/favicon.ico">
    <link rel="manifest" href="<?php echo $base_path; ?>/assets/images/favicon.ico">

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Baloo+Bhai+2:wght@400..800&family=Baloo+Bhaina+2:wght@400..800&family=Barlow:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/bootstrap.min.css">


<link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/owl.carousel.min.css">
<link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/owl.theme.default.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/animate.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/layout.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/responsive.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/captcha.css">

</head>

<body>
    <header>
        <nav class="navbar navbar-expand-lg px-3">
            <div class="container">
                <a class="navbar-brand" href="<?php echo $base_path; ?>/">
                    <img class="img-fluid" src="<?php echo $base_path; ?>/assets/images/main-logo.png" alt="logo">
                </a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <img src="<?php echo $base_path; ?>/assets/images/navbar-img.png" alt="Menu" width="30" height="30" class="img-fluid">
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
                                <a class="dropdown-item" href="<?php echo $base_path; ?>/refer-and-earn.php">Refer & Earn</a>
                                <a class="dropdown-item" href="<?php echo $base_path; ?>/franchisee.php"> Franchise Partner</a>
                            </div> 
                        </li>
                   
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown"
                                aria-expanded="false">
                                Login <i class="fa fa-angle-double-down"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="<?php echo $base_path; ?>/login/customer.php">Customer Login</a>
                                <a class="dropdown-item" href="<?php echo $base_path; ?>/login/franchisee.php">Franchise Login</a>
                                <a class="dropdown-item" href="<?php echo $base_path; ?>/login/team.php">Team Login</a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- JavaScript (dynamic paths + use files that exist in this repo) -->
    <script src="<?php echo $base_path; ?>/assets/js/jquery.slim.min.js"></script>
    <script>
      // jQuery fallback (some pages still use it)
      if (!window.jQuery) {
        document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
      }
    </script>
    <script src="<?php echo $base_path; ?>/assets/js/bootstrap.bundle.min.js"></script>
    <script>
      // Bootstrap fallback (optional)
      if (!window.bootstrap && !(window.jQuery && window.jQuery.fn && window.jQuery.fn.dropdown)) {
        document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"><\/script>');
      }
    </script>
    
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
    // Dropdown functionality for main header (NO jQuery required)
    (function () {
      function closeAllDropdowns() {
        document.querySelectorAll('.navbar .dropdown-menu.show').forEach(function (m) {
          m.classList.remove('show');
        });
      }

      // Toggle dropdowns on click
      document.querySelectorAll('.navbar .nav-item.dropdown > .dropdown-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function (e) {
          e.preventDefault();

          var menu = toggle.nextElementSibling;
          if (!menu || !menu.classList.contains('dropdown-menu')) return;

          // close others
          document.querySelectorAll('.navbar .dropdown-menu.show').forEach(function (m) {
            if (m !== menu) m.classList.remove('show');
          });

          menu.classList.toggle('show');
        });
      });

      // Close when clicking a dropdown item
      document.querySelectorAll('.navbar .dropdown-menu .dropdown-item').forEach(function (item) {
        item.addEventListener('click', function () {
          var menu = item.closest('.dropdown-menu');
          if (menu) menu.classList.remove('show');
        });
      });

      // Close on outside click
      document.addEventListener('click', function (e) {
        if (!e.target.closest('.navbar .nav-item.dropdown')) {
          closeAllDropdowns();
        }
      });

      // Close on escape
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAllDropdowns();
      });

      // Smooth scrolling inside scrollable dropdowns
      document.querySelectorAll('.scrollable-dropdown').forEach(function (dd) {
        dd.addEventListener('wheel', function (e) {
          e.preventDefault();
          dd.scrollTop += e.deltaY;
        }, { passive: false });
      });
    })();
    </script>


