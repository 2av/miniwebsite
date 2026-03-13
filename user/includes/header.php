<!DOCTYPE html>
<html lang="en">
<?php
// Include database connection and role helpers
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/role_helper.php');
require_once(__DIR__ . '/../../app/helpers/verification_helper.php');
require_once(__DIR__ . '/../../app/helpers/menu_helper.php');

// Require login for all user pages
require_login('/panel/login/login.php');

// Get current user role
$current_role = get_current_user_role();
$user_email = get_user_email();
$user_name = get_user_name();

// Get current page info for dynamic titles
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Set page title based on role and current page
$page_title = ucfirst(strtolower($current_role)) . " Portal";
if($current_dir == 'dashboard') {
    $page_title = ucfirst(strtolower($current_role)) . " Dashboard";
} elseif($current_dir == 'referral' || $current_dir == 'referral-details') {
    $page_title = "Referral Details";
} elseif($current_dir == 'collaboration' || $current_dir == 'collaboration-details') {
    $page_title = "Collaboration Details";
} elseif($current_dir == 'verification') {
    $page_title = "Verification";
} elseif($current_dir == 'wallet') {
    $page_title = "Wallet";
} elseif($current_dir == 'teams') {
    $page_title = "Teams";
} elseif($current_dir == 'kit') {
    $page_title = "Kit";
} elseif($current_dir == 'website') {
    $page_title = "Website Builder";
} elseif($current_dir == 'profile') {
    $page_title = "Profile";
}

// Role-specific checks - read from session (set during login)
$collaboration_enabled = false;
$saleskit_enabled = false;
$is_verified = false;

if ($current_role == 'CUSTOMER') {
    // Read from session (set during login in login/customer.php)
    $collaboration_enabled = isset($_SESSION['collaboration_enabled']) ? (bool)$_SESSION['collaboration_enabled'] : false;
    $saleskit_enabled = isset($_SESSION['saleskit_enabled']) ? (bool)$_SESSION['saleskit_enabled'] : false;
} elseif ($current_role == 'FRANCHISEE') {
    $is_verified = isFranchiseeVerified($user_email);
}

// Build role-aware menu visibility conditions (used by sidebar + profile dropdown)
$user_conditions = [];
if ($current_role == 'CUSTOMER') {
    $user_conditions['collaboration_enabled'] = $collaboration_enabled;
    $user_conditions['saleskit_enabled'] = $saleskit_enabled;
} elseif ($current_role == 'FRANCHISEE') {
    $user_conditions['is_verified'] = $is_verified;
}

// Pre-compute visible menu items once (so dropdown + sidebar stay consistent)
$visible_menu_items = get_visible_menu_items($current_role, $user_conditions);

// Get base path for assets (works for both localhost subfolder and production root)
function get_assets_base_path() {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $script_dir = dirname($script_name);
    
    // Handle both /user/dashboard and /user/dashboard/index.php
    // Remove /user and everything after it
    $base = preg_replace('#/user(/.*)?$#', '', $script_dir);
    
    // Normalize: if it's just '/', return empty string; otherwise return as is
    if ($base === '/' || $base === '') {
        return '';
    }
    
    // Ensure it starts with / for proper URL (should already have it)
    return $base;
}
$assets_base = get_assets_base_path();

// Get base path for navigation links (includes /user)
$nav_base = $assets_base . '/user';

// Load profile image from database (saved by common/upload_profile.php)
$user_image = ($assets_base ? $assets_base : '') . '/assets/images/profile-default.png';
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
                $user_image = ($assets_base ? $assets_base : '') . '/' . $img_path;
            }
        }
        $stmt->close();
    }
}

// Dynamic site base URL for referral links (protocol + host from current request)
$site_base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'miniwebsite.in');
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
    <link href="<?php echo $assets_base; ?>/assets/css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?php echo $assets_base; ?>/assets/css/responsive.css">
    <link rel="stylesheet" href="<?php echo $assets_base; ?>/assets/css/dashboard-professional.css">
    <link rel="stylesheet" href="<?php echo $assets_base; ?>/assets/css/common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Cropper.js CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" />
     <!-- Instagram JS (IMPORTANT) -->
    <script async src="https://www.instagram.com/embed.js"></script>
    <script>
        // Global variables for upload functionality
        var APP_BASE_PATH = '<?php echo addslashes($assets_base); ?>';
        var UPLOAD_PROFILE_URL = '<?php echo addslashes($assets_base . '/common/upload_profile.php'); ?>';
        
        function copyToClipboard(type) {
            // Map types to element IDs on the page
            const idMap = {
                'link': 'regular_link_value',
                'regular_link': 'regular_link_value',
                'code': 'regular_code_value',
                'regular_code': 'regular_code_value',
                'collab_link': 'collab_link_value',
                'collab_code': 'collab_code_value'
            };

            const targetId = idMap[type] || idMap['link'];
            const el = document.getElementById(targetId);
            let textToCopy = el ? (el.innerText || el.textContent).trim() : '';

            if (!textToCopy) {
                alert('Nothing to copy.');
                return;
            }

            navigator.clipboard.writeText(textToCopy).then(() => {
                if (type.includes('link')) {
                    alert('Referral link copied!');
                } else {
                    alert('Referral code copied!');
                }
            }).catch(err => {
                console.error('Failed to copy: ', err);
                const textArea = document.createElement('textarea');
                textArea.value = textToCopy;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert(type.includes('link') ? 'Referral link copied!' : 'Referral code copied!');
            });
        }
    </script>
</head>

<body class="sb-nav-fixed">
    
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <div class="head-left">
            <a class="navbar-brand ps-3" href="<?php echo $nav_base; ?>/dashboard">
                <img src="<?php echo $assets_base; ?>/assets/images/logo.png" class="img-fluid" alt="" srcset="">
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
                                    echo htmlspecialchars($user_name ?? 'Guest'); 
                                    ?> 

                                    <i class="fa fa-angle-double-down"></i>
                                </span>
                            </div>
                            <div class="p-image" title="Click to upload profile image (Max: 250KB, Formats: JPG, PNG, GIF)">
                                <img src="<?php echo $assets_base; ?>/assets/images/camera.png" class="upload-button img-fluid" alt="">
                                <input class="file-upload" type="file" accept="image/*" name="profile_image" id="profile_image">
                            </div>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo $nav_base; ?>/dashboard">Dashboard</a></li>
                        <?php
                        // Role-wise quick links (based on same menu config as sidebar)
                        // Show first few items except dashboard.
                        $quick = [];
                        foreach ($visible_menu_items as $mi) {
                            $mid = $mi['id'] ?? '';
                            if ($mid === 'dashboard') continue;
                            $quick[] = $mi;
                            if (count($quick) >= 3) break;
                        }
                        foreach ($quick as $mi):
                            $label = $mi['label'] ?? '';
                            $url   = $mi['url'] ?? '';
                            if ($label === '' || $url === '') continue;
                            $menu_link = $nav_base . $url;
                        ?>
                            <li><a class="dropdown-item" href="<?php echo $menu_link; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a></li>
                        <?php endforeach; ?>
                        <li><a class="dropdown-item" href="<?php echo $nav_base; ?>/change-password">Change Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo $assets_base; ?>/login/logout.php">Logout</a></li>
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
                        // Get current page directory for active state
                        $current_dir = basename(dirname($_SERVER['PHP_SELF']));

                        // Special sidebar for website builder pages
                        if ($current_dir === 'website') {
                            // Get card_number from session or cookie
                            $card_number = '';
                            if (isset($_SESSION['card_id_inprocess']) && !empty($_SESSION['card_id_inprocess'])) {
                                $card_number = $_SESSION['card_id_inprocess'];
                            } elseif (isset($_COOKIE['card_id_inprocess']) && !empty($_COOKIE['card_id_inprocess'])) {
                                $card_number = $_COOKIE['card_id_inprocess'];
                            }

                            // Get business name (card_id) from database for Preview link
                            $business_name_slug = '';
                            if (!empty($card_number)) {
                                $safe_card_id = mysqli_real_escape_string($connect, $card_number);
                                $safe_user_email = mysqli_real_escape_string($connect, $user_email);
                                $card_query_db = mysqli_query($connect, "SELECT card_id FROM digi_card WHERE id='{$safe_card_id}' AND user_email='{$safe_user_email}'");
                                if ($card_query_db && mysqli_num_rows($card_query_db) > 0) {
                                    $card_row = mysqli_fetch_array($card_query_db);
                                    $business_name_slug = !empty($card_row['card_id']) ? $card_row['card_id'] : '';
                                }
                            }

                            // Build query string for menu links if card_number exists
                            $card_query = !empty($card_number) ? '?card_number=' . htmlspecialchars($card_number) : '';
                            $current_page_ws = basename($_SERVER['PHP_SELF']);
                        ?>
                            <a class="nav-link collapsed <?php echo ($current_dir == 'dashboard') ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>/dashboard">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/Dashboard.png" class="img-fluid" alt="" srcset=""></div>
                                Dashboard
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                            <hr/>
                            <a class="nav-link collapsed <?php echo ($current_page_ws == 'business-name.php') ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>/website/business-name.php<?php echo $card_query; ?>">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/BusinessName.png" class="img-fluid" alt="" srcset=""></div>
                                Business Name
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a> 
                            <a class="nav-link collapsed <?php echo ($current_page_ws == 'select-theme.php') ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>/website/select-theme.php<?php echo $card_query; ?>">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/SelectTheme.png" class="img-fluid" alt="" srcset=""></div>
                                Select Theme
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                            <a class="nav-link collapsed <?php echo ($current_page_ws == 'company-details.php') ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>/website/company-details.php<?php echo $card_query; ?>">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/CompanyDetails.png" class="img-fluid" alt="" srcset=""></div>
                                Company Details
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                            <a class="nav-link collapsed <?php echo ($current_page_ws == 'social-links.php') ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>/website/social-links.php<?php echo $card_query; ?>">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/SocialLinks.png" class="img-fluid" alt="" srcset=""></div>
                                Social Links
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                            <a class="nav-link collapsed <?php echo ($current_page_ws == 'videos.php') ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>/website/videos.php<?php echo $card_query; ?>">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/Video.png" class="img-fluid" alt="" srcset=""></div>
                                Videos
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                            <a class="nav-link collapsed <?php echo ($current_page_ws == 'payment-details.php') ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>/website/payment-details.php<?php echo $card_query; ?>">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/PaymentDetails.png" class="img-fluid" alt="" srcset=""></div>
                                Payment Details
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                            <a class="nav-link collapsed <?php echo ($current_page_ws == 'services.php') ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>/website/services.php<?php echo $card_query; ?>">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/ProductServices.png" class="img-fluid" alt="" srcset=""></div>
                                Services
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                            <a class="nav-link collapsed <?php echo ($current_page_ws == 'products.php') ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>/website/products.php<?php echo $card_query; ?>">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/ProductPricing.png" class="img-fluid" alt="" srcset=""></div>
                                Products
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                            <a class="nav-link collapsed <?php echo ($current_page_ws == 'special-offers.php') ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>/website/special-offers.php<?php echo $card_query; ?>">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/SpecialOffers.png" class="img-fluid" alt="" srcset=""></div>
                                Special Offers
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                            <a class="nav-link collapsed <?php echo ($current_page_ws == 'image-gallery.php') ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>/website/image-gallery.php<?php echo $card_query; ?>">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/ImageGallery.png" class="img-fluid" alt="" srcset=""></div>
                                Image Gallery
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                            <?php if ($collaboration_enabled): ?>
                            <a class="nav-link collapsed <?php echo ($current_dir == 'collaboration' || $current_dir == 'collaboration-details') ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>/collaboration/">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/collaboration.png" class="img-fluid" alt="" srcset=""></div>
                                Collaboration Details
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                            <?php endif; ?>
                            <?php if ($saleskit_enabled): ?>
                            <a class="nav-link collapsed <?php echo ($current_dir == 'kit') ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>/kit/">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/pay.png" class="img-fluid" alt="" srcset=""></div>
                                Sales Kit
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                            <?php endif; ?>
                            <?php if ($collaboration_enabled): ?>
                            <a class="nav-link collapsed <?php echo ($current_dir == 'kit') ? 'active' : ''; ?>" href="<?php echo $nav_base; ?>/kit/">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/marketingkit.png" class="img-fluid" alt="" srcset=""></div>
                                Marketing Kit
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                            <?php endif; ?>
                            <a target="_blank" class="nav-link collapsed <?php echo (!empty($business_name_slug)) ? 'active' : ''; ?>" href="<?php echo $assets_base; ?>/n.php?n=<?php echo htmlspecialchars($business_name_slug); ?>">
                                <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/Preview.png" class="img-fluid" alt="" srcset=""></div>
                                Preview
                                <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                            </a>
                        <?php
                        } else {
                            // Standard sidebar using JSON menu config
                            
                            if (isset($_GET['menu_debug']) || (isset($_SESSION['menu_debug']) && $_SESSION['menu_debug'] === true)) {
                                $debug_info = [];
                                $debug_info[] = "=== MENU CONFIG DEBUG ===";
                                $debug_info[] = "Current Role: " . $current_role;
                                $debug_info[] = "User Conditions: " . json_encode($user_conditions, JSON_PRETTY_PRINT);
                                $debug_info[] = "Visible Menu Items: " . count($visible_menu_items);
                                $debug_info[] = "";
                                $debug_info[] = "Visible Menu IDs:";
                                foreach ($visible_menu_items as $item) {
                                    $debug_info[] = "  - " . ($item['id'] ?? 'unknown') . " (" . ($item['label'] ?? '') . ")";
                                }
                                
                                $debug_alert = "<script>
                                    alert(" . json_encode(implode("\\n", $debug_info)) . ");
                                </script>";
                                echo $debug_alert;
                            }
                            
                            // Render menu items
                            foreach ($visible_menu_items as $menu_item):
                                $menu_url = trim($menu_item['url'], '/');
                                // Strip query string for path comparison
                                $menu_path = (strpos($menu_url, '?') !== false) ? substr($menu_url, 0, strpos($menu_url, '?')) : $menu_url;
                                $menu_id = str_replace('.php', '', $menu_path);
                                $is_active = ($current_dir === $menu_id || $current_dir === $menu_url);
                                // Kit page: Sales Kit active when kit param is not franchise_sales; Franchisee Sales Kit active when kit=franchise_sales
                                if ($current_dir === 'kit' && isset($menu_item['id'])) {
                                    $kit_param = isset($_GET['kit']) ? trim($_GET['kit']) : '';
                                    if ($menu_item['id'] === 'franchisee_sales_kit_team') {
                                        $is_active = ($kit_param === 'franchise_sales');
                                    } elseif ($menu_item['id'] === 'kit') {
                                        $is_active = ($kit_param !== 'franchise_sales');
                                    }
                                }
                                
                                $icon_path = $assets_base . '/assets/images/' . $menu_item['icon'];
                                $menu_link = $nav_base . $menu_item['url'];
                            ?>
                                <a class="nav-link collapsed <?php echo $is_active ? 'active' : ''; ?>" href="<?php echo $menu_link; ?>">
                                    <div class="sb-nav-link-icon"><img src="<?php echo $icon_path; ?>" class="img-fluid" alt="" srcset=""></div>
                                    <?php echo htmlspecialchars($menu_item['label']); ?>
                                    <div class="sb-sidenav-collapse-arrow"><i class="fa fa-angle-down"></i></div>
                                </a>
                            <?php endforeach; ?>
                            
                            <?php
                            if ($current_role == 'FRANCHISEE' && !$is_verified):
                                $disabled_items = [
                                    ['label' => 'Wallet', 'icon' => 'wallet.png'],
                                    ['label' => 'Marketing Kit', 'icon' => 'marketingkit.png']
                                ];
                                foreach ($disabled_items as $item):
                            ?>
                                <div class="nav-link collapsed" style="opacity: 0.6; cursor: not-allowed;" title="Document verification required">
                                    <div class="sb-nav-link-icon"><img src="<?php echo $assets_base; ?>/assets/images/<?php echo $item['icon']; ?>" class="img-fluid" alt="" srcset=""></div>
                                    <?php echo htmlspecialchars($item['label']); ?>
                                </div>
                            <?php 
                                endforeach;
                            endif;

                            // Franchisee-only Download Invoice button (franchisee joining invoice)
                            if ($current_role == 'FRANCHISEE'):
                            ?>
                                <div class="nav-link" style="margin-top: 20px;">
                                    <a href="<?php echo $nav_base; ?>/dashboard/download_invoice.php"
                                       class="btn btn-warning btn-sm"
                                       style="width: 100%; background-color: #ffc107; border-color: #ffc107; color: #000; padding: 10px; border-radius: 5px; text-decoration: none; display: block; text-align: center;"
                                       target="_blank">
                                        <i class="fa fa-download"></i>
                                        <span>Download Invoice</span>
                                    </a>
                                </div>
                            <?php
                            endif;
                        } // end sidebar branch
                        ?>
                        


                         
                    </div>
                </div>
            </nav>
        </div>
        <div id="layoutSidenav_content">

<?php require_once(__DIR__ . '/../../common/image_upload_crop_modal.php'); ?>








