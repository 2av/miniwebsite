<!DOCTYPE html>
<html lang="en">
<?php
// Include database connection first
 
require_once('../../common/config.php');

// Enhanced session validation
if(!isset($_SESSION['user_email']) || !isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    // Clear any potentially corrupted session data
    session_unset();
    session_destroy();

    // Start a new session
    session_start();

    // Redirect to login page with correct path
    header('Location: ../../panel/login/login.php?session=expired');
    exit;
}

// Refresh session periodically to prevent timeout
if(isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 3600)) {
    // Update login time if session is older than 1 hour
    $_SESSION['login_time'] = time();
}

// Get current page info for dynamic titles
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Set page title based on current page
$page_title = "Customer Portal";
if($current_dir == 'dashboard') {
    $page_title = "Customer Dashboard";
} elseif($current_dir == 'referral-details') {
    $page_title = "Referral Details";
} elseif($current_dir == 'collaboration-details') {
    $page_title = "Collaboration Details";
}

// Check if user has collaboration enabled (using unified user_details table)
$user_email = $_SESSION['user_email'];
$collab_query = mysqli_query($connect, "SELECT collaboration_enabled, saleskit_enabled FROM user_details WHERE email='$user_email' AND role='CUSTOMER' LIMIT 1");
$collab_data = mysqli_fetch_array($collab_query);
$collaboration_enabled = ($collab_data['collaboration_enabled'] == 'YES');
$saleskit_enabled = ($collab_data['saleskit_enabled'] == 'YES');

// Profile image is now served by get_profile_image.php
$user_image = null;
// Debug: Console log the collab data
echo "<script>console.log('Saleskit Enabled:', " . ($saleskit_enabled ? 'true' : 'false') . ");</script>";

// Profile image is now served by get_profile_image.php
?>
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title><?php echo $page_title; ?> - Mini Website</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+Bhai+2:wght@400..800&family=Barlow:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
    <link href="../assets/css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/dashboard-professional.css">
    <link rel="stylesheet" href="../../common/assets/css/common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Cropper.js CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" />
     <!-- Instagram JS (IMPORTANT) -->
    <script async src="https://www.instagram.com/embed.js"></script>
</head>

<body class="sb-nav-fixed">
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <div class="head-left">
            <a class="navbar-brand ps-3" href="index.php">
                <img src="../assets/img/logo.png" class="img-fluid" alt="" srcset="">
            </a>
            <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!">
                <i class="fa fa-bars"></i>
            </button>
        </div>
        <div class="head-right">
            <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
                <li class="nav-item upload-profile-wrap">
                    <a class="nav-link">
                        <div class="upload-profile">
                            <div class="circle">
                                <img class="profile-pic" src="../<?php echo $user_image; ?>" alt="" srcset=""> 
                                <span>
                                    <?php 
                                    echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Guest'; 
                                    ?> 
                                    <i class="fa fa-angle-double-down"></i>
                                </span>
                            </div>
                            <div class="p-image" title="Click to upload profile image (Max: 250KB, Formats: JPG, PNG, GIF)">
                                <img src="../assets/img/camera.png" class="upload-button img-fluid" alt="">
                                <input class="file-upload" type="file" accept="image/*" name="profile_image" id="profile_image">
                            </div>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../dashboard/">Dashboard</a></li>
                        <li><a class="dropdown-item" href="../change-password/">Change Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../../panel/login/logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion" id="sidenavAccordion">
                <div class="sb-sidenav-menu">
                    <div class="nav">
                        <?php
                        // Get current page filename and directory
                        $current_page = basename($_SERVER['PHP_SELF']);
                        $current_dir = basename(dirname($_SERVER['PHP_SELF']));
                        $request_uri = $_SERVER['REQUEST_URI'];
                        ?>
                        <a class="nav-link collapsed <?php echo ($current_dir == 'dashboard') ? 'active' : ''; ?>" href="../dashboard/">
                            <div class="sb-nav-link-icon"><img src="../assets/img/Dashboard.png" class="img-fluid" alt="" srcset=""></div>
                            Dashboard
                            <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                        </a>
                        <?php if(!$collaboration_enabled): ?>
                        <a class="nav-link collapsed <?php echo ($current_dir == 'referral-details') ? 'active' : ''; ?>" href="../referral-details/">
                            <div class="sb-nav-link-icon"><img src="../assets/img/ReferralDetails.png" class="img-fluid" alt="" srcset=""></div>
                            Referral Details
                            <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                        </a>
                        <?php if($saleskit_enabled): ?>
                        <a class="nav-link collapsed <?php echo ($current_dir == 'kit') ? 'active' : ''; ?>" href="../kit/">
                                <div class="sb-nav-link-icon"><img src="../../common/assets/img/wallet.png" class="img-fluid" alt="" srcset=""></div>
                                Sales Kit
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                        <?php endif; ?>
                        <?php else: ?>
                        <a class="nav-link collapsed <?php echo ($current_dir == 'collaboration-details') ? 'active' : ''; ?>" href="../collaboration-details/">
                            <div class="sb-nav-link-icon"><img src="../assets/img/collaboration.png" class="img-fluid" alt="" srcset=""></div>
                            Collaboration Details
                            <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                        </a>
                        <a class="nav-link collapsed <?php echo ($current_dir == 'kit') ? 'active' : ''; ?>" href="../kit/">
                                <div class="sb-nav-link-icon"><img src="../../common/assets/img/marketingkit.png" class="img-fluid" alt="" srcset=""></div>
                                Marketing Kit
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                        <?php endif; ?>
                        


                         
                    </div>
                </div>
            </nav>
        </div>
        <div id="layoutSidenav_content">

<!-- Profile Image Crop Modal -->
<style>
    #profileImageCropModal .modal-dialog {
        max-width: 650px;
        width: 90%;
    }
    #profileImageCropModal .img-container {
        width: 100%;
        height: 550px;
        max-height: 550px;
        overflow: hidden;
        background: #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    #profileImageCropModal .img-container img {
        max-width: 100%;
        max-height: 100%;
        display: block;
    }
    @media (max-width: 768px) {
        #profileImageCropModal .modal-dialog {
            max-width: 95%;
        }
        #profileImageCropModal .img-container {
            height: 400px;
            max-height: 400px;
        }
    }
</style>
<div class="modal fade" id="profileImageCropModal" tabindex="-1" role="dialog" aria-labelledby="profileImageCropModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileImageCropModalLabel">
                    <i class="fa fa-image me-2"></i>Adjust & Crop Profile Image
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="img-container">
                                <img id="imageToCrop" src="" alt="Profile Image">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="btn-toolbar justify-content-center mb-3" role="toolbar">
                                <div class="btn-group mr-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="rotateLeft" title="Rotate Left">
                                        <i class="fa fa-rotate-left"></i> Rotate Left
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="rotateRight" title="Rotate Right">
                                        <i class="fa fa-rotate-right"></i> Rotate Right
                                    </button>
                                </div>
                                <div class="btn-group mr-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="zoomIn" title="Zoom In">
                                        <i class="fa fa-search-plus"></i> Zoom In
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="zoomOut" title="Zoom Out">
                                        <i class="fa fa-search-minus"></i> Zoom Out
                                    </button>
                                </div>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="resetCrop" title="Reset">
                                        <i class="fa fa-refresh"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 text-center">
                            <p class="text-muted mb-0" style="font-size: 13px;">
                                <i class="fa fa-info-circle"></i> Drag to adjust the crop area. The image will be automatically optimized to 600x600 pixels and ~250KB after upload.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="uploadCroppedImage">
                    <i class="fa fa-upload"></i> Upload & Save
                </button>
            </div>
        </div>
    </div>
</div>





