<!DOCTYPE html>
<html lang="en">
<?php
// Include database connection first
 
require_once('../../common/config.php');

// Enhanced session validation - Check for team member session (using old session names)
if(!isset($_SESSION['user_email']) || !isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    // Redirect to team login page (don't destroy session here as it might be needed)
    header('Location: ../login.php?session=expired');
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
$page_title = "Team Portal";
if($current_dir == 'dashboard') {
    $page_title = "Team Dashboard";
} elseif($current_dir == 'referral-details') {
    $page_title = "Referral Details";
} elseif($current_dir == 'change-password') {
    $page_title = "Change Password";
} elseif($current_dir == 'customer-tracker') {
    $page_title = "Customer Tracker";
}elseif($current_dir == 'kit') {
    // Check if franchise_sales kit is requested
    if(isset($_GET['kit']) && $_GET['kit'] == 'franchise_sales') {
        $page_title = "Franchisee Sales Kit";
    } else {
        $page_title = "Sales Kit";
    }
}elseif($current_dir == 'idcard') {
    $page_title = "ID Card";
}

// Get team member info (using old session names)
$user_email = $_SESSION['user_email'] ?? '';
$user_name = $_SESSION['user_name'] ?? 'Team Member';
$team_member_id = $_SESSION['team_member_id'] ?? '';

// Get team member profile image from user_details table
$profile_image_src = '../../common/assets/img/profile-default.png';
if (!empty($user_email)) {
    $profile_query = mysqli_query($connect, "SELECT image FROM user_details WHERE email='$user_email' AND role='TEAM' LIMIT 1");
    if ($profile_query && mysqli_num_rows($profile_query) > 0) {
        $profile_data = mysqli_fetch_array($profile_query);
        if (!empty($profile_data['image'])) {
            // Check if it's a file path or base64 data
            if (strpos($profile_data['image'], 'profile_images/') === 0) {
                // It's a file path
                $profile_image_src = '../../team/' . $profile_data['image'] . '?t=' . time();
            } elseif (strlen($profile_data['image']) > 100) {
                // Likely base64 or binary data
                $profile_image_src = 'data:image/jpeg;base64,' . base64_encode($profile_data['image']);
            }
        }
    }
}
?>
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title><?php echo $page_title; ?> - Mini Website</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+Bhai+2:wght@400..800&family=Barlow:wght@400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
    <link href="../../customer/assets/css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../customer/assets/css/responsive.css">
    <link rel="stylesheet" href="../../customer/assets/css/dashboard-professional.css">
    <link rel="stylesheet" href="../../common/assets/css/common.css">
    <!-- Cropper.js CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" />
    
<!-- Instagram Embed JS (Required) -->
<script async src="https://www.instagram.com/embed.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body class="sb-nav-fixed">
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <div class="head-left">
            <a class="navbar-brand ps-3" href="../dashboard/">
                <img src="../../customer/assets/img/logo.png" class="img-fluid" alt="" srcset="">
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
                                <img class="profile-pic" src="<?php echo htmlspecialchars($profile_image_src); ?>" alt="" srcset=""> 
                                <span>
                                    <?php 
                                    echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Team Member'; 
                                    ?> 
                                    <i class="fa fa-angle-double-down"></i>
                                </span>
                            </div>
                            <div class="p-image" title="Click to upload profile image (Max: 250KB, Formats: JPG, PNG, GIF)">
                                <img src="../../customer/assets/img/camera.png" class="upload-button img-fluid" alt="">
                                <input class="file-upload" type="file" accept="image/*" name="profile_image" id="profile_image">
                            </div>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../dashboard/">Dashboard</a></li>
                        <li><a class="dropdown-item" href="../referral-details/">Referral Details</a></li>
                        <li><a class="dropdown-item" href="../kit/">Sales Kit</a></li>
                        <li><a class="dropdown-item" href="../kit/?kit=franchise_sales">Franchisee Sales Kit</a></li>
                        <li><a class="dropdown-item" href="../customer-tracker/">Customer Tracker</a></li>
                        <li><a class="dropdown-item" href="../idcard/index.php">Id card</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
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
                            <div class="sb-nav-link-icon"><img src="../../customer/assets/img/Dashboard.png" class="img-fluid" alt="" srcset=""></div>
                            Dashboard
                            <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                        </a>
                        <a class="nav-link collapsed <?php echo ($current_dir == 'referral-details') ? 'active' : ''; ?>" href="../referral-details/">
                            <div class="sb-nav-link-icon"><img src="../../customer/assets/img/ReferralDetails.png" class="img-fluid" alt="" srcset=""></div>
                            Referral Details
                            <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                        </a>
                        <a class="nav-link collapsed <?php echo ($current_dir == 'customer-tracker') ? 'active' : ''; ?>" href="../customer-tracker/">
                            <div class="sb-nav-link-icon"><img src="../../common/assets/img/Customertracker.png" class="img-fluid" alt="" srcset=""></div>
                            Customer Tracker
                            <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                        </a>
                        <a class="nav-link collapsed <?php echo ($current_dir == 'kit' && (!isset($_GET['kit']) || $_GET['kit'] != 'franchise_sales')) ? 'active' : ''; ?>" href="../kit/">
                            <div class="sb-nav-link-icon"><img src="../../common/assets/img/SalesKit.png" class="img-fluid" alt="" srcset=""></div>
                            Sales Kit
                            <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                        </a>
                        <a class="nav-link collapsed <?php echo ($current_dir == 'kit' && isset($_GET['kit']) && $_GET['kit'] == 'franchise_sales') ? 'active' : ''; ?>" href="../kit/?kit=franchise_sales">
                            <div class="sb-nav-link-icon"><img src="../../common/assets/img/FranchiseeSalesKit.png" class="img-fluid" alt="" srcset=""></div>
                           Franchisee Sales Kit
                            <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                        </a>
                        <a class="nav-link collapsed <?php echo ($current_dir == 'idcard') ? 'active' : ''; ?>" href="../idcard/">
                            <div class="sb-nav-link-icon"><img src="../../common/assets/img/IDCard.png" class="img-fluid" alt="" srcset=""></div>
                            ID Card
                            <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                        </a>                  
                         
                        

                         
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



