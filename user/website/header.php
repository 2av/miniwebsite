<?php
// Include database connection and role helpers
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/role_helper.php');

// Handle card_number from URL - store in session and cookie
// MUST be done before any output
if(isset($_GET['card_number']) && !empty($_GET['card_number'])) {
    $card_number = mysqli_real_escape_string($connect, $_GET['card_number']);
    $_SESSION['card_id_inprocess'] = $card_number;
    // Store in cookie for 24 hours
    setcookie('card_id_inprocess', $card_number, time() + (86400 * 1), '/');
} elseif(isset($_COOKIE['card_id_inprocess']) && !empty($_COOKIE['card_id_inprocess'])) {
    // If card_number not in URL but exists in cookie, restore to session
    if(!isset($_SESSION['card_id_inprocess']) || empty($_SESSION['card_id_inprocess'])) {
        $_SESSION['card_id_inprocess'] = $_COOKIE['card_id_inprocess'];
    }
}

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

// Load profile image from database (saved by common/upload_profile.php)
$user_email = get_user_email();
$current_role = get_current_user_role();
$user_image = '../../assets/images/profile-default.png';
if (!empty($user_email) && !empty($current_role) && isset($connect)) {
    $stmt = $connect->prepare("SELECT image FROM user_details WHERE email = ? AND role = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ss", $user_email, $current_role);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && ($row = $res->fetch_assoc()) && !empty($row['image'])) {
            $img_path = $row['image'];
            $full_path = __DIR__ . '/../../' . $img_path;
            if (file_exists($full_path)) {
                $user_image = '../../' . $img_path;
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
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
    <link href="../../assets/css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/css/responsive.css">
    <link rel="stylesheet" href="../../assets/css/dashboard-professional.css">
    <link rel="stylesheet" href="../../assets/css/common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body class="sb-nav-fixed">
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <div class="head-left">
            <a class="navbar-brand ps-3" href="index.php">
                <img src="../../../assets/images/logo.png" class="img-fluid" alt="" srcset="">
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
                                <img class="profile-pic" src="<?php echo htmlspecialchars($user_image); ?>" alt="" srcset=""> 
                                <span>
                                    <?php 
                                    echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Guest'; 
                                    ?> 
                                    <i class="fa fa-angle-double-down"></i>
                                </span>
                            </div>
                            <div class="p-image" title="Click to upload profile image (Max: 250KB, Formats: JPG, PNG, GIF)">
                                <img src="../../../assets/images/camera.png" class="upload-button img-fluid" alt="">
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
                        
                        // Get card_number from session or cookie (already handled at top of file)
                        $card_number = '';
                        if(isset($_SESSION['card_id_inprocess']) && !empty($_SESSION['card_id_inprocess'])) {
                            $card_number = $_SESSION['card_id_inprocess'];
                        } elseif(isset($_COOKIE['card_id_inprocess']) && !empty($_COOKIE['card_id_inprocess'])) {
                            $card_number = $_COOKIE['card_id_inprocess'];
                        }
                        
                        // Get business name (card_id) from database for Preview link
                        $business_name_slug = '';
                        if(!empty($card_number)) {
                            $card_query_db = mysqli_query($connect, 'SELECT card_id FROM digi_card WHERE id="'.mysqli_real_escape_string($connect, $card_number).'" AND user_email="'.$_SESSION['user_email'].'"');
                            if($card_query_db && mysqli_num_rows($card_query_db) > 0) {
                                $card_row = mysqli_fetch_array($card_query_db);
                                $business_name_slug = !empty($card_row['card_id']) ? $card_row['card_id'] : '';
                            }
                        }
                        
                        // Build query string for menu links if card_number exists
                        $card_query = !empty($card_number) ? '?card_number=' . htmlspecialchars($card_number) : '';
                        ?>
                        <a class="nav-link collapsed <?php echo ($current_dir == 'dashboard') ? 'active' : ''; ?>" href="../dashboard/">
                            <div class="sb-nav-link-icon"><img src="../../../assets/images/Dashboard.png" class="img-fluid" alt="" srcset=""></div>
                            Dashboard
                            <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                        </a>
                        <hr/>
                        <a class="nav-link collapsed <?php echo ($current_page == 'business-name.php') ? 'active' : ''; ?>" href="business-name.php<?php echo $card_query; ?>">
                                    <div class="sb-nav-link-icon"><img src="../../../assets/images/BusinessName.png" class="img-fluid" alt="" srcset=""></div>
                                    Business Name
                                    <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                                </a> 
                                <a class="nav-link collapsed <?php echo ($current_page == 'select-theme.php') ? 'active' : ''; ?>" href="select-theme.php<?php echo $card_query; ?>">
                                    <div class="sb-nav-link-icon"><img src="../../../assets/images/SelectTheme.png" class="img-fluid" alt="" srcset=""></div>
                                    Select Theme
                                    <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                                </a>
                                <a class="nav-link collapsed <?php echo ($current_page == 'company-details.php') ? 'active' : ''; ?>" href="company-details.php<?php echo $card_query; ?>">
                                    <div class="sb-nav-link-icon"><img src="../../../assets/images/CompanyDetails.png" class="img-fluid" alt="" srcset=""></div>
                                    Company Details
                                    <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                                </a>
                                <a class="nav-link collapsed <?php echo ($current_page == 'social-links.php') ? 'active' : ''; ?>" href="social-links.php<?php echo $card_query; ?>">
                                    <div class="sb-nav-link-icon"><img src="../../../assets/images/SocialLinks.png" class="img-fluid" alt="" srcset=""></div>
                                    Social Links
                                    <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                                </a>
                                <a class="nav-link collapsed <?php echo ($current_page == 'payment-details.php') ? 'active' : ''; ?>" href="payment-details.php<?php echo $card_query; ?>">
                                    <div class="sb-nav-link-icon"><img src="../../../assets/images/PaymentDetails.png" class="img-fluid" alt="" srcset=""></div>
                                    Payment Details
                                    <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                                </a>
                                <a class="nav-link collapsed <?php echo ($current_page == 'product-and-services.php') ? 'active' : ''; ?>" href="product-and-services.php<?php echo $card_query; ?>">
                                    <div class="sb-nav-link-icon"><img src="../../../assets/images/ProductServices.png" class="img-fluid" alt="" srcset=""></div>
                                    Product &amp; Services
                                    <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                                </a>
                                <a class="nav-link collapsed <?php echo ($current_page == 'product-pricing.php') ? 'active' : ''; ?>" href="product-pricing.php<?php echo $card_query; ?>">
                                    <div class="sb-nav-link-icon"><img src="../../../assets/images/ProductPricing.png" class="img-fluid" alt="" srcset=""></div>
                                    Product Pricing
                                    <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                                </a>
                                <a class="nav-link collapsed <?php echo ($current_page == 'special-offers.php') ? 'active' : ''; ?>" href="special-offers.php<?php echo $card_query; ?>">
                                    <div class="sb-nav-link-icon"><img src="../../../assets/images/SpecialOffers.png" class="img-fluid" alt="" srcset=""></div>
                                    Special Offers
                                    <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                                </a>
                                <a class="nav-link collapsed <?php echo ($current_page == 'image-gallery.php') ? 'active' : ''; ?>" href="image-gallery.php<?php echo $card_query; ?>">
                                    <div class="sb-nav-link-icon"><img src="../../../assets/images/ImageGallery.png" class="img-fluid" alt="" srcset=""></div>
                                    Image Gallery
                                    <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                                </a>
                                <a target="_blank" class="nav-link collapsed <?php echo ($current_page == 'n.php' && !empty($business_name_slug)) ? 'active' : ''; ?>" href="../../n.php?n=<?php echo htmlspecialchars($business_name_slug); ?>">
                                    <div class="sb-nav-link-icon"><img src="../../../assets/images/Preview.png" class="img-fluid" alt="" srcset=""></div>
                                    Preview
                                    <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                                </a>
                    </div>
                </div>
            </nav>
        </div>
        <div id="layoutSidenav_content">

<!-- Profile Image Crop Modal (same as tests/image_upload_crop: zoom, rotate, flip, preview) -->
<style>
    #profileImageCropModal .modal-dialog {
        max-width: 900px;
        width: 95%;
    }
    #profileImageCropModal .img-container {
        width: 100%;
        height: 380px;
        max-height: 380px;
        overflow: hidden;
        background: #000;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 5px;
    }
    #profileImageCropModal .img-container img {
        max-width: 100%;
        max-height: 100%;
        display: block;
    }
    #profileImageCropModal .profile-crop-preview-box {
        width: 160px;
        height: 160px;
        overflow: hidden;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        background: #fff;
        margin: 0 auto;
    }
    #profileImageCropModal .profile-crop-preview-box img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    #profileImageCropModal .profile-crop-zoom-slider {
        margin-bottom: 0.5rem;
    }
    @media (max-width: 768px) {
        #profileImageCropModal .modal-dialog {
            max-width: 98%;
        }
        #profileImageCropModal .img-container {
            height: 300px;
            max-height: 300px;
        }
        #profileImageCropModal .profile-crop-preview-box {
            width: 120px;
            height: 120px;
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
                        <div class="col-md-8">
                            <div class="img-container mb-3">
                                <img id="imageToCrop" src="" alt="Profile Image">
                            </div>
                            <div class="controls">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label small">Zoom</label>
                                        <input type="range" class="form-range profile-crop-zoom-slider" id="profileZoomSlider" min="0" max="3" step="0.1" value="1">
                                        <div class="d-flex justify-content-between mb-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="zoomOut" title="Zoom Out">
                                                <i class="fa fa-search-minus"></i> Zoom Out
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="zoomIn" title="Zoom In">
                                                <i class="fa fa-search-plus"></i> Zoom In
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">Rotate &amp; Flip</label>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="rotateLeft" title="Rotate Left">
                                                <i class="fa fa-rotate-left"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="rotateRight" title="Rotate Right">
                                                <i class="fa fa-rotate-right"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="flipHorizontal" title="Flip Horizontal">
                                                <i class="fa fa-arrows-alt-h"></i> H
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="flipVertical" title="Flip Vertical">
                                                <i class="fa fa-arrows-alt-v"></i> V
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="resetCrop" title="Reset">
                                                <i class="fa fa-refresh"></i> Reset
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="preview-section border rounded p-3 bg-light">
                                <h6 class="mb-2">Preview (512&times;512)</h6>
                                <div id="profileCropPreviewBox" class="profile-crop-preview-box"></div>
                                <p class="small text-muted mt-2 mb-0"><strong>Dimensions:</strong> <span id="profileCroppedDimensions">512 &times; 512 px</span></p>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12 text-center">
                            <p class="text-muted mb-0" style="font-size: 13px;">
                                <i class="fa fa-info-circle"></i> Drag to adjust the crop area. Image will be saved as 512&times;512 and optimized after upload.
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












