<!DOCTYPE html>
<html lang="en">
<?php
// Include database connection and role helpers
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/role_helper.php');
require_once(__DIR__ . '/../../app/helpers/verification_helper.php');
require_once(__DIR__ . '/../../app/helpers/menu_helper.php');

// Require login for all user pages
require_login('/login/customer.php');

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
    $page_title = ($current_role === 'TEAM') ? "Sales Details" : "Referral Details";
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
} elseif($current_dir == 'customer-manager' || $current_dir == 'customer-tracker-customer' || $current_dir == 'customer-tracker') {
    $page_title = "Customer Manager";
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
    $franchise_agreement_paid = isFranchiseeRegistrationAgreementPaid($user_email);
}

// Build role-aware menu visibility conditions (used by sidebar + profile dropdown)
$user_conditions = [];
if ($current_role == 'CUSTOMER') {
    $user_conditions['collaboration_enabled'] = $collaboration_enabled;
    $user_conditions['saleskit_enabled'] = $saleskit_enabled;
} elseif ($current_role == 'FRANCHISEE') {
    $user_conditions['is_verified'] = $is_verified;
    $user_conditions['franchise_agreement_paid'] = $franchise_agreement_paid;
}

// Pre-compute visible menu items once (so dropdown + sidebar stay consistent)
$visible_menu_items = get_visible_menu_items($current_role, $user_conditions);
$mw_nav_active_color = get_menu_nav_active_color();

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

// MW ID (digi_card.id) for profile header
$mw_id = null;
if (!empty($user_email) && isset($connect)) {
    $card_in_process = null;
    if (!empty($_SESSION['card_id_inprocess'])) {
        $card_in_process = (int)$_SESSION['card_id_inprocess'];
    } elseif (!empty($_COOKIE['card_id_inprocess'])) {
        $card_in_process = (int)$_COOKIE['card_id_inprocess'];
    }
    if ($card_in_process > 0) {
        $stmt = $connect->prepare("SELECT id FROM digi_card WHERE id = ? AND (user_email = ? OR f_user_email = ?) LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("iss", $card_in_process, $user_email, $user_email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $mw_id = (int)$row['id'];
            }
            $stmt->close();
        }
    }
    if ($mw_id === null) {
        if ($current_role === 'FRANCHISEE') {
            $stmt = $connect->prepare("SELECT id FROM digi_card WHERE f_user_email = ? ORDER BY id DESC LIMIT 1");
        } else {
            $stmt = $connect->prepare("SELECT id FROM digi_card WHERE user_email = ? ORDER BY id DESC LIMIT 1");
        }
        if ($stmt) {
            $stmt->bind_param("s", $user_email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $mw_id = (int)$row['id'];
            }
            $stmt->close();
        }
    }
}

// FR ID (user_details.id) for franchisee profile header
$fr_id = null;
if ($current_role === 'FRANCHISEE') {
    $fr_id = get_user_id();
    if (empty($fr_id) && !empty($user_email) && isset($connect)) {
        $stmt = $connect->prepare("SELECT id FROM user_details WHERE email = ? AND role = 'FRANCHISEE' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $user_email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $fr_id = (int)$row['id'];
            }
            $stmt->close();
        }
    } else {
        $fr_id = (int)$fr_id;
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/v4-font-face.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/v4-shims.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Cropper.js CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" />
    <!-- ============================================================
         MiniWebsite Design System (Phase A · Step 1 + design tokens)
         ============================================================
         SINGLE SOURCE OF TRUTH for the entire site.

         To restyle the whole project, change values in :root below.
         Both Tailwind utilities AND .mw-* component classes consume
         these CSS variables, so a single edit cascades everywhere.

         Quick map:
           --mw-color-*    → brand colors        (utilities: bg-primary, text-secondary…)
           --mw-font-*     → font sizes          (utilities: text-page-title, text-body, text-btn…)
           --mw-radius-*   → border radii        (utilities: rounded-card, rounded-btn, rounded-pill…)
           --mw-shadow-*   → box shadows         (utilities: shadow-soft, shadow-card, shadow-elevated)
           --mw-icon-*     → icon sizes          (utilities: w-icon / h-icon, w-menu-icon / h-menu-icon…)
           --mw-card-*     → card padding        (used by .mw-card-body)
           --mw-btn-*      → button padding      (used by .mw-btn)
           --mw-input-*    → input sizing        (used by .mw-input)
         ============================================================ -->
    <style id="mw-design-tokens">
        :root {
            /* ---- Brand colors ---- */
            --mw-color-primary:         #ffbe17;
            --mw-color-primary-dark:    #2b4ba9;
            --mw-color-primary-light:   #e0bf4a;
            --mw-color-secondary:       #5c6b7a;
            --mw-color-secondary-dark:  #0f0f1c;
            --mw-color-secondary-light: #2a2a44;
            --mw-color-accent:          #ffc107;

            /* ---- Surface / text ---- */
            --mw-color-bg:              #f8fafc;   /* page background */
            --mw-color-surface:         #ffffff;   /* card background */
            --mw-color-border:          #e2e8f0;
            --mw-color-border-strong:   #cbd5e1;
            --mw-color-text:            #0f172a;   /* primary text */
            --mw-color-text-muted:      #64748b;   /* secondary text */
            --mw-color-text-subtle:     #94a3b8;   /* helper / placeholder */

            /* ---- State colors ---- */
            --mw-color-success:         #16a34a;
            --mw-color-success-bg:      #f0fdf4;
            --mw-color-success-border:  #bbf7d0;
            --mw-color-success-text:    #14532d;
            --mw-color-warning:         #d97706;
            --mw-color-warning-bg:      #fff7ed;
            --mw-color-warning-border:  #fed7aa;
            --mw-color-warning-text:    #9a3412;
            --mw-color-danger:          #dc2626;
            --mw-color-danger-bg:       #fef2f2;
            --mw-color-danger-border:   #fecaca;
            --mw-color-danger-text:     #991b1b;
            --mw-color-info:            #0284c7;
            --mw-color-info-bg:         #eff6ff;
            --mw-color-info-border:     #bfdbfe;
            --mw-color-info-text:       #1e3a8a;

            /* ---- Sidebar nav (from user/menu_config.json → theme.nav_icon_active) ---- */
            --mw-color-nav-active:      <?php echo htmlspecialchars($mw_nav_active_color, ENT_QUOTES, 'UTF-8'); ?>;

            /* ---- Typography (mobile-first; *-lg = >=md breakpoint override) ---- */
            --mw-font-page-title:       1.5rem;    /* 24px - h1 mobile */
            --mw-font-page-title-lg:    1.875rem;  /* 30px - h1 desktop */
            --mw-font-section-title:    1.125rem;  /* 18px - h2 mobile */
            --mw-font-section-title-lg: 1.25rem;   /* 20px - h2 desktop */
            --mw-font-card-title:       1rem;      /* 16px */
            --mw-font-label:            0.875rem;  /* 14px - form labels */
            --mw-font-label-lg:         1.125rem;  /* 18px - primary field labels (mobile) */
            --mw-font-label-lg-md:      1.375rem;  /* 22px - primary field labels (desktop) */
            --mw-font-body:             0.875rem;  /* 14px - body / inputs */
            --mw-font-body-lg:          1.125rem;  /* 18px - primary field inputs */
            --mw-font-helper:           0.8125rem; /* 13px - helper text under inputs */
            --mw-font-caption:          0.75rem;   /* 12px - captions */
            --mw-font-btn:              0.875rem;  /* 14px - buttons */
            --mw-font-btn-sm:           0.8125rem; /* 13px - small buttons */
            --mw-font-breadcrumb:       0.875rem;
            --mw-font-pill:             0.6875rem; /* 11px - small badge / pill */

            /* ---- Spacing ---- */
            --mw-content-padding-top:       1.5rem;   /* gap above .main-top */
            --mw-content-padding-x:         1rem;     /* page content left/right (mobile) */
            --mw-content-padding-x-md:      2.5rem;   /* page content left/right (desktop) */
            --mw-page-header-gap:           1.5rem;   /* gap below .main-top before card/content */
            --mw-page-header-padding-bottom: 1rem;    /* padding above header bottom border */
            --mw-topnav-height:             5.5rem;   /* fixed top bar (full logo + menu) */
            --mw-sidebar-width:             260px;    /* aligns top divider with sidebar edge */
            --mw-sidebar-width-mobile:      280px;    /* off-canvas sidebar on small screens */
            --mw-card-padding:          1.5rem;
            --mw-card-padding-lg:       2rem;
            --mw-btn-padding-x:         1.25rem;
            --mw-btn-padding-y:         0.625rem;
            --mw-btn-padding-x-lg:      1.5rem;
            --mw-btn-padding-x-sm:      0.75rem;
            --mw-btn-padding-y-sm:      0.375rem;
            --mw-btn-icon-size:         2.25rem;   /* icon-only square */
            --mw-input-padding-x:       0.875rem;
            --mw-input-padding-y:       0.625rem;
            --mw-input-padding-icon:    2.5rem;    /* left pad when input has leading icon */

            /* ---- Border radii ---- */
            --mw-radius-card:           1rem;      /* 16px */
            --mw-radius-btn:            0.5rem;    /* 8px  */
            --mw-radius-input:          0.5rem;
            --mw-radius-chip:           0.75rem;
            --mw-radius-pill:           9999px;

            /* ---- Icon / control sizes ---- */
            --mw-icon-sm:               1rem;      /* 16px */
            --mw-icon:                  1.25rem;   /* 20px - default (sidebar / menu / inline) */
            --mw-icon-lg:               1.5rem;    /* 24px */
            --mw-icon-xl:               2rem;      /* 32px */
            --mw-avatar:                2.5rem;    /* 40px */
            --mw-avatar-lg:             3rem;
            --mw-input-height:          2.5rem;
            --mw-input-height-lg:       3rem;      /* 48px - primary field inputs */
            --mw-input-padding-y-lg:    0.75rem;
            --mw-input-padding-x-lg:    1rem;
            --mw-btn-angle:             1.25rem;   /* circular chevron in Back/Next buttons */

            /* ---- Modal widths (sm / md / lg) ---- */
            --mw-modal-width-sm:        24rem;     /* 384px */
            --mw-modal-width-md:        32rem;     /* 512px — default */
            --mw-modal-width-lg:        48rem;     /* 768px */
            --mw-modal-width-xl:        71.25rem;  /* 1140px */
            --mw-modal-z-index:         1055;
            --mw-modal-padding:         1.25rem;
            --mw-modal-padding-lg:      1rem;
            --mw-modal-header-bg-start: #1e4db7;
            --mw-modal-header-bg-end:   #153e9b;
            --mw-modal-header-bg:       linear-gradient(90deg, #1e4db7 0%, #153e9b 100%);
            --mw-modal-header-text:     #ffffff;
            --mw-modal-body-bg:         #ffffff;
            --mw-modal-surface-tint:    #f8fafc;
            --mw-modal-border:          #e2e8f0;
            --mw-modal-label:           #1a2b4b;
            --mw-modal-input-border:    #e2e8f0;
            --mw-modal-input-text:      #4a5678;
            --mw-modal-placeholder:     #8a94a6;
            --mw-modal-muted:           #8a94a6;
            --mw-modal-section:         #1e4db7;
            --mw-modal-accent:          #ffc107;
            --mw-modal-accent-hover:    #e6ac00;
            --mw-modal-cancel-bg:       #ffffff;
            --mw-modal-cancel-border:   #e2e8f0;
            --mw-modal-cancel-text:     #4a5568;
            --mw-modal-radius:          14px;

            /* ---- Website builder tables ---- */
            --mw-table-header-bg:       #eff3f7;
            --mw-table-header-text:     var(--mw-color-text);
            --mw-table-header-border:   var(--mw-color-border);
            --mw-table-cell-border:     #eff3f7;
            --mw-table-header-font:     1rem;       /* 16px */
            --mw-table-header-weight:   600;
            --mw-table-header-padding-y: 0.75rem;
            --mw-table-header-padding-x: 1rem;
            --mw-table-cell-padding-y:  0.625rem;
            --mw-table-cell-padding-x:  1rem;

            /* ---- Shadows ---- */
            --mw-shadow-soft:           0 1px 2px 0 rgb(0 0 0 / 0.04), 0 1px 3px 0 rgb(0 0 0 / 0.06);
            --mw-shadow-card:           0 1px 3px 0 rgb(0 0 0 / 0.06), 0 4px 12px -2px rgb(0 0 0 / 0.05);
            --mw-shadow-elevated:       0 8px 24px -4px rgb(0 0 0 / 0.08), 0 4px 8px -2px rgb(0 0 0 / 0.04);
            --mw-ring-focus:            0 0 0 3px rgb(201 162 39 / 0.45);
            --mw-ring-input:            0 0 0 3px rgb(201 162 39 / 0.18);
        }
    </style>
    <?php
    require_once __DIR__ . '/../../common/mw_modal.php';
    mw_modal_print_styles();
    ?>

    <!-- Tailwind CSS — utilities are wired to the :root tokens above. -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            // Disable Tailwind's base reset so Bootstrap 4 keeps working unchanged.
            // Re-enable in Phase E · Step 32 once Bootstrap is removed.
            corePlugins: { preflight: false },
            theme: {
                extend: {
                    colors: {
                        // Brand (wired to CSS vars → change once in :root, all utilities update)
                        primary:   { DEFAULT: 'var(--mw-color-primary)',   dark: 'var(--mw-color-primary-dark)',   light: 'var(--mw-color-primary-light)' },
                        secondary: { DEFAULT: 'var(--mw-color-secondary)', dark: 'var(--mw-color-secondary-dark)', light: 'var(--mw-color-secondary-light)' },
                        accent:    'var(--mw-color-accent)',
                        // Semantic neutrals
                        background: 'var(--mw-color-bg)',
                        surface:    'var(--mw-color-surface)',
                        foreground: 'var(--mw-color-text)',
                        muted:      'var(--mw-color-border)',
                        'muted-foreground': 'var(--mw-color-text-muted)',
                        border:     'var(--mw-color-border)',
                        input:      'var(--mw-color-border)',
                        ring:       'var(--mw-color-primary)',
                        // States
                        success: 'var(--mw-color-success)',
                        warning: 'var(--mw-color-warning)',
                        danger:  'var(--mw-color-danger)',
                        info:    'var(--mw-color-info)',
                    },
                    fontFamily: {
                        sans:    ['Barlow', 'system-ui', 'sans-serif'],
                        display: ['Barlow', 'system-ui', 'sans-serif'],
                    },
                    fontSize: {
                        // Semantic typography tokens — use these instead of text-2xl/text-lg/etc.
                        'page-title':       ['var(--mw-font-page-title)',       { lineHeight: '1.2', fontWeight: '400' }],
                        'page-title-lg':    ['var(--mw-font-page-title-lg)',    { lineHeight: '1.2', fontWeight: '400' }],
                        'section-title':    ['var(--mw-font-section-title)',    { lineHeight: '1.4', fontWeight: '600' }],
                        'section-title-lg': ['var(--mw-font-section-title-lg)', { lineHeight: '1.4', fontWeight: '600' }],
                        'card-title':       ['var(--mw-font-card-title)',       { lineHeight: '1.4', fontWeight: '600' }],
                        'label':            ['var(--mw-font-label)',            { lineHeight: '1.4', fontWeight: '600' }],
                        'body':             ['var(--mw-font-body)',             { lineHeight: '1.5' }],
                        'helper':           ['var(--mw-font-helper)',           { lineHeight: '1.4' }],
                        'caption':          ['var(--mw-font-caption)',          { lineHeight: '1.4' }],
                        'btn':              ['var(--mw-font-btn)',              { lineHeight: '1.4', fontWeight: '600' }],
                        'breadcrumb':       ['var(--mw-font-breadcrumb)',       { lineHeight: '1.4' }],
                        'pill':             ['var(--mw-font-pill)',             { lineHeight: '1',   fontWeight: '700' }],
                    },
                    boxShadow: {
                        'soft':     'var(--mw-shadow-soft)',
                        'card':     'var(--mw-shadow-card)',
                        'elevated': 'var(--mw-shadow-elevated)',
                    },
                    borderRadius: {
                        'card':  'var(--mw-radius-card)',
                        'btn':   'var(--mw-radius-btn)',
                        'input': 'var(--mw-radius-input)',
                        'chip':  'var(--mw-radius-chip)',
                        'pill':  'var(--mw-radius-pill)',
                        'xl':    '0.75rem',
                        '2xl':   'var(--mw-radius-card)',
                    },
                    width: {
                        'icon-sm':   'var(--mw-icon-sm)',
                        'icon':      'var(--mw-icon)',
                        'icon-lg':   'var(--mw-icon-lg)',
                        'icon-xl':   'var(--mw-icon-xl)',
                        'menu-icon': 'var(--mw-icon)',
                        'btn-icon':  'var(--mw-icon)',
                        'btn-angle': 'var(--mw-btn-angle)',
                        'avatar':    'var(--mw-avatar)',
                        'avatar-lg': 'var(--mw-avatar-lg)',
                    },
                    maxWidth: {
                        'modal-sm': 'var(--mw-modal-width-sm)',
                        'modal-md': 'var(--mw-modal-width-md)',
                        'modal-lg': 'var(--mw-modal-width-lg)',
                        'modal-xl': 'var(--mw-modal-width-xl)',
                    },
                    height: {
                        'icon-sm':   'var(--mw-icon-sm)',
                        'icon':      'var(--mw-icon)',
                        'icon-lg':   'var(--mw-icon-lg)',
                        'icon-xl':   'var(--mw-icon-xl)',
                        'menu-icon': 'var(--mw-icon)',
                        'btn-icon':  'var(--mw-icon)',
                        'btn-angle': 'var(--mw-btn-angle)',
                        'avatar':    'var(--mw-avatar)',
                        'avatar-lg': 'var(--mw-avatar-lg)',
                        'input':     'var(--mw-input-height)',
                    },
                }
            }
        };
    </script>

    <!-- ============================================================
         MiniWebsite reusable component classes (.mw-*)
         ============================================================
         Use these instead of repeating long Tailwind chains across pages.
         All values come from the :root CSS vars above → restyle once,
         every page updates.

         Quick reference:
           .mw-page             page wrapper (light bg, min-height)
           .mw-container        max-width container with horizontal padding
           .mw-page-header      page title row (alias: .Dashboard .main-top)
           .mw-page-title       page h1 / .main-top .heading
           .mw-breadcrumb       horizontal breadcrumb list
           .mw-card             card surface (border, radius, shadow)
           .mw-card-body        card inner padding
           .mw-section-title    h2 with gold accent underline
           .mw-table-header     website builder table thead row
           .mw-table-scroll     horizontal scroll wrapper for wide tables
           .mw-alert            base alert (use with .mw-alert-success/danger/warning/info)
           .mw-alert-icon       leading status icon
           .mw-alert-close      × close button
           .mw-form             vertical-stack form container
           .mw-form-narrow      centered narrow form (max 40rem)
           .mw-meta             helper lines under inputs (URL, status)
           .mw-alert-compact    smaller inline alert under a field
           .mw-label            form input label (semibold)
           .mw-label-lg         larger label for primary fields (Business Name, URL, etc.)
           .mw-input            text input
           .mw-input-lg         larger input for primary fields
           .mw-input-icon-wrap  wrapper that positions a leading icon
           .mw-input-icon       leading icon (absolutely positioned)
           .mw-stat-grid        responsive grid of summary stat cards
           .mw-stat-card        single stat card (icon + label + value)
           .mw-stat-icon        icon badge inside stat card
           .mw-dash-card-grid   action card row (dashboard, wallet, etc.) — CSS in common.css
           .mw-dash-card-icon   icon box (58×100px desktop; responsive) — CSS in common.css
           .mw-dash-card-icon--blue / --gold   icon colour variants
           .mw-btn-row          responsive Back/Save/Next button row
           .mw-btn              base button (use with a variant class)
           .mw-btn-back / -save / -next / -primary / -secondary / -accent
           .mw-btn-cancel / -danger / -success / -warning / -info
           .mw-btn-outline-*    outlined variants
           .mw-btn-sm / -lg / -icon   sizes
           .mw-btn-angle        circular chevron inside Back/Next buttons
           .mw-btn-img          optional icon image inside Save button
           .mw-pill             small rounded badge (use with -primary / -muted)
           .mw-modal            common popup overlay (brand header; .mw-modal-light for white body)
           .mw-modal-panel      popup box — .mw-modal-sm|md|lg|xl on panel
           .mw-modal-section-title  section heading inside modal body
         ============================================================ -->
    <style id="mw-components">
        /* Page chrome --------------------------------------------------- */
        .mw-page          { min-height: calc(100vh - 200px); background: var(--mw-color-bg); }
        .mw-container     { max-width: 64rem; margin-inline: auto; padding: 1.5rem 1rem; }
        @media (min-width: 768px) {
            .mw-container { max-width: 80rem; }
            :root {
                --mw-content-padding-top: 2rem;
                --mw-page-header-gap: 0rem;
                --mw-topnav-height: 8.125rem;
            }
        }
        .mw-container-narrow { max-width: 48rem; margin-inline: auto; padding: 1.5rem 1rem; }
        @media (min-width: 768px) { .mw-container-narrow { padding: 1.5rem; } }

        /* Content area — top + side padding (all Dashboard pages) */
        .Dashboard .customer_content_area,
        main.Dashboard > .customer_content_area,
        main.Dashboard > .container-fluid.customer_content_area {
            margin-top: 0 !important;
            padding-top: var(--mw-content-padding-top) !important;
            padding-bottom: 0 !important;
            padding-left: var(--mw-content-padding-x) !important;
            padding-right: var(--mw-content-padding-x) !important;
            box-sizing: border-box;
        }
        @media (min-width: 768px) {
            .Dashboard .customer_content_area,
            main.Dashboard > .customer_content_area,
            main.Dashboard > .container-fluid.customer_content_area {
                padding-left: var(--mw-content-padding-x-md) !important;
                padding-right: var(--mw-content-padding-x-md) !important;
            }
        }
        /* mw-container on same node — use page padding tokens, not duplicate mw-container pad */
        .Dashboard .customer_content_area.mw-container,
        main.Dashboard.mw-page > .customer_content_area.mw-container {
            padding-top: var(--mw-content-padding-top) !important;
            padding-bottom: 0 !important;
            padding-left: var(--mw-content-padding-x) !important;
            padding-right: var(--mw-content-padding-x) !important;
            margin-top: 0 !important;
            max-width: none !important;
        }
        @media (min-width: 768px) {
            .Dashboard .customer_content_area.mw-container,
            main.Dashboard.mw-page > .customer_content_area.mw-container {
                padding-left: var(--mw-content-padding-x-md) !important;
                padding-right: var(--mw-content-padding-x-md) !important;
            }
        }

        /* Page header strip (.main-top on every Dashboard page) -------- */
        .mw-page-header,
        .Dashboard .main-top,
        main.Dashboard .main-top {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: var(--mw-page-header-gap);
            margin-left: 0;
            margin-right: 0;
            padding: 0 0 var(--mw-page-header-padding-bottom);
            border-bottom: 1px solid var(--mw-color-border);
            align-items: stretch;
            justify-content: flex-start;
        }
        @media (min-width: 640px) {
            .mw-page-header,
            .Dashboard .main-top,
            main.Dashboard .main-top {
                flex-direction: row;
                align-items: flex-end;
                justify-content: space-between;
            }
        }
        .mw-page-title,
        .Dashboard .main-top > .heading,
        .Dashboard .main-top > h1.heading,
        .Dashboard .main-top > span.heading,
        .Dashboard .main-top > h1.mw-page-title {
            font-size: var(--mw-font-page-title);
            font-weight: 400;
            line-height: 1.2;
            color: var(--mw-color-text);
            margin: 0;
            padding-left: 0;
            letter-spacing: normal;
        }
        @media (min-width: 768px) {
            .mw-page-title,
            .Dashboard .main-top > .heading,
            .Dashboard .main-top > h1.heading,
            .Dashboard .main-top > span.heading,
            .Dashboard .main-top > h1.mw-page-title {
                font-size: var(--mw-font-page-title-lg);
            }
        }
        .Dashboard .main-top > .heading::after,
        .Dashboard .main-top > h1.heading::after,
        .mw-page-title::after { content: none; display: none; border: 0; }

        /* Breadcrumb (legacy .breadcrumb inside .main-top too) ---------- */
        .mw-breadcrumb,
        .Dashboard .main-top .breadcrumb {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.375rem;
            background: transparent;
            padding: 0;
            margin: 0;
            font-size: var(--mw-font-breadcrumb);
            list-style: none;
        }
        .Dashboard .main-top nav { display: block; margin: 0; }
        .mw-breadcrumb-item,
        .Dashboard .main-top .breadcrumb .breadcrumb-item {
            color: var(--mw-color-text-muted);
            display: inline-flex;
            align-items: center;
        }
        .mw-breadcrumb-item a,
        .Dashboard .main-top .breadcrumb .breadcrumb-item a {
            color: var(--mw-color-text-muted);
            text-decoration: none;
            transition: color .15s;
        }
        .mw-breadcrumb-item a:hover,
        .Dashboard .main-top .breadcrumb .breadcrumb-item a:hover {
            color: var(--mw-color-primary-dark);
        }
        .mw-breadcrumb-item.active,
        .Dashboard .main-top .breadcrumb .breadcrumb-item.active {
            color: var(--mw-color-primary-dark);
            font-weight: 500;
        }
        .mw-breadcrumb-item + .mw-breadcrumb-item::before,
        .Dashboard .main-top .breadcrumb .breadcrumb-item + .breadcrumb-item::before {
            content: '/' !important;
            margin-inline: 0.375rem;
            color: var(--mw-color-text-subtle) !important;
            font-weight: 400;
            float: none !important;
            padding: 0 !important;
        }

        /* Card ---------------------------------------------------------- */
        .mw-card          { background: var(--mw-color-surface); border: 1px solid var(--mw-color-border); border-radius: var(--mw-radius-card); box-shadow: var(--mw-shadow-card); overflow: hidden; }
        .mw-card-body     { padding: var(--mw-card-padding); }
        @media (min-width: 768px) { .mw-card-body { padding: var(--mw-card-padding-lg); } }

        /* Section heading with gold accent underline -------------------- */
        .mw-section-title { font-size: var(--mw-font-section-title); font-weight: 600; color: var(--mw-color-text); margin: 0 0 0.25rem; display: inline-block; position: relative; padding-bottom: 0.5rem; line-height: 1.3; }
        @media (min-width: 768px) { .mw-section-title { font-size: var(--mw-font-section-title-lg); } }
        .mw-section-title::after { content: ''; position: absolute; left: 0; bottom: 0; width: 3rem; height: 2px; background: var(--mw-color-primary); border-radius: 9999px; }
        .mw-section-title .req { color: var(--mw-color-danger); margin-left: 0.125rem; }

        .mw-helper-text   { margin: 0.75rem 0 0; font-size: var(--mw-font-helper); color: var(--mw-color-text-muted); display: flex; align-items: flex-start; gap: 0.5rem; }
        .mw-helper-text i { color: var(--mw-color-text-subtle); margin-top: 0.15rem; flex-shrink: 0; }

        /* Form ---------------------------------------------------------- */
        .mw-form          { display: flex; flex-direction: column; gap: 1.5rem; width: 100%; }
        .mw-form-grid-2   { display: grid; grid-template-columns: 1fr; gap: 1rem 1.25rem; }
        @media (min-width: 768px) { .mw-form-grid-2 { grid-template-columns: 1fr 1fr; } }
        .mw-form-group    { display: flex; flex-direction: column; gap: 0.375rem; }
        .mw-form-narrow    { max-width: 40rem; margin-inline: auto; width: 100%; }

        .mw-label         { display: inline-flex; align-items: center; gap: 0.5rem; font-size: var(--mw-font-label); font-weight: 600; color: var(--mw-color-text); margin: 0; }
        .mw-label-lg      { font-size: var(--mw-font-label-lg); font-weight: 600; line-height: 1.3; }
        @media (min-width: 768px) { .mw-label-lg { font-size: var(--mw-font-label-lg-md); } }
        .mw-label .req    { color: var(--mw-color-danger); margin-left: 0.125rem; }

        .mw-input         { width: 100%; padding: var(--mw-input-padding-y) var(--mw-input-padding-x); font-size: var(--mw-font-body); line-height: 1.4; color: var(--mw-color-text); background: var(--mw-color-surface); border: 1px solid var(--mw-color-border); border-radius: var(--mw-radius-input); transition: border-color .15s, box-shadow .15s; }
        .mw-input-lg      { font-size: var(--mw-font-body-lg); min-height: var(--mw-input-height-lg); padding: var(--mw-input-padding-y-lg) var(--mw-input-padding-x-lg); font-weight: 500; }
        .mw-input::placeholder { color: var(--mw-color-text-subtle); }
        .mw-input:hover   { border-color: var(--mw-color-border-strong); }
        .mw-input:focus   { outline: none; border-color: var(--mw-color-primary); box-shadow: var(--mw-ring-input); }
        .mw-input:disabled{ background: #f8fafc; color: var(--mw-color-text-subtle); cursor: not-allowed; }

        .mw-input-icon-wrap { position: relative; }
        .mw-input-icon-wrap .mw-input { padding-left: var(--mw-input-padding-icon); }
        .mw-input-icon      { position: absolute; left: var(--mw-input-padding-x); top: 50%; transform: translateY(-50%); color: var(--mw-color-text-subtle); pointer-events: none; font-size: var(--mw-font-body); }

        /* Stat summary cards -------------------------------------------- */
        .mw-stat-grid       { display: grid; grid-template-columns: 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        @media (min-width: 640px) { .mw-stat-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1024px) { .mw-stat-grid.mw-stat-grid-3 { grid-template-columns: repeat(3, 1fr); } }
        .mw-stat-card       { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1rem; margin: 0; width: auto; max-width: none; height: auto; background: #eff3f7; border: none; border-radius: var(--mw-radius-card); box-sizing: border-box; }
        .mw-stat-icon       { flex-shrink: 0; display: flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: var(--mw-radius-input); }
        .mw-stat-icon .fa,
        .mw-stat-icon .fa-solid { font-size: 1.25rem; line-height: 1; width: 1.25rem; text-align: center; display: inline-block; }
        .mw-stat-body       { min-width: 0; flex: 1; }
        .mw-stat-label      { margin: 0; padding: 0; font-size: var(--mw-font-helper); font-weight: 600; color: var(--mw-color-text-muted); line-height: 1.4; }
        .mw-stat-value      { margin: 0.25rem 0 0; padding: 0; font-size: 1.25rem; font-weight: 700; color: var(--mw-color-text); line-height: 1.25; }

        /* Dashboard / wallet action cards → assets/css/common.css (.mw-dash-card-*) */

        /* Alerts -------------------------------------------------------- */
        .mw-alert         { display: flex; align-items: flex-start; gap: 0.75rem; padding: 1rem; border-radius: var(--mw-radius-card); border: 1px solid transparent; margin-bottom: 1rem; font-size: var(--mw-font-body); }
        .mw-alert-icon    { margin-top: 0.125rem; font-size: 1.125rem; flex-shrink: 0; }
        .mw-alert-body    { flex: 1; min-width: 0; }
        .mw-alert-success { background: var(--mw-color-success-bg); border-color: var(--mw-color-success-border); color: var(--mw-color-success-text); }
        .mw-alert-success .mw-alert-icon { color: var(--mw-color-success); }
        .mw-alert-danger  { background: var(--mw-color-danger-bg);  border-color: var(--mw-color-danger-border);  color: var(--mw-color-danger-text); }
        .mw-alert-danger  .mw-alert-icon { color: var(--mw-color-danger); }
        .mw-alert-warning { background: var(--mw-color-warning-bg); border-color: var(--mw-color-warning-border); color: var(--mw-color-warning-text); }
        .mw-alert-warning .mw-alert-icon { color: var(--mw-color-warning); }
        .mw-alert-info    { background: var(--mw-color-info-bg);    border-color: var(--mw-color-info-border);    color: var(--mw-color-info-text); }
        .mw-alert-info    .mw-alert-icon { color: var(--mw-color-info); }
        .mw-alert-close   { margin-left: auto; background: transparent; border: 0; padding: 0; width: 1.5rem; height: 1.5rem; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; line-height: 1; color: inherit; opacity: 0.6; cursor: pointer; border-radius: 0.25rem; flex-shrink: 0; }
        .mw-alert-close:hover { opacity: 1; }
        .mw-alert-compact { margin-bottom: 0 !important; padding: 0.5rem 0.75rem !important; font-size: var(--mw-font-helper) !important; gap: 0.5rem !important; }

        /* Meta lines (URL / status under form fields) -------------------- */
        .mw-meta              { margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.375rem; font-size: var(--mw-font-caption); color: var(--mw-color-text-muted); }
        .mw-meta-line         { margin: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 0.375rem; line-height: 1.4; }
        .mw-meta-line > i     { color: var(--mw-color-text-subtle); flex-shrink: 0; }
        .mw-meta-label        { font-weight: 600; color: var(--mw-color-text); }
        .mw-meta-link         { color: var(--mw-color-primary-dark); text-decoration: underline; word-break: break-all; }
        .mw-meta-link:hover   { color: var(--mw-color-primary); }
        .mw-meta-link--muted  { color: var(--mw-color-text-muted); }
        .mw-meta-link--muted:hover { color: var(--mw-color-text); }
        .mw-meta-line.is-warning { color: #92400e; }
        .mw-meta-line.is-warning > i { color: var(--mw-color-warning); }
        .mw-meta-line.is-danger  { color: var(--mw-color-danger-text); }
        .mw-meta-line.is-danger  > i { color: var(--mw-color-danger); }

        /* Legacy styles.css overrides inside .mw-page (mobile-first) --- */
        main.mw-page {
            font-size: var(--mw-font-body);
            line-height: 1.5;
            color: var(--mw-color-text);
        }
        /* Legacy styles.css / common.css — enforce design tokens on all pages */
        .Dashboard .main-top > .heading,
        .Dashboard .main-top > h1.heading,
        .Dashboard .main-top > span.heading,
        .Dashboard .main-top > h1.mw-page-title,
        main.mw-page .mw-page-title,
        main.mw-page .main-top .heading.mw-page-title,
        main.mw-page h1.mw-page-title {
            font-size: var(--mw-font-page-title) !important;
            line-height: 1.2 !important;
            font-weight: 400 !important;
            color: var(--mw-color-text) !important;
            letter-spacing: normal !important;
            margin: 0 !important;
            padding-left: 0 !important;
        }
        @media (min-width: 768px) {
            .Dashboard .main-top > .heading,
            .Dashboard .main-top > h1.heading,
            .Dashboard .main-top > span.heading,
            .Dashboard .main-top > h1.mw-page-title,
            main.mw-page .mw-page-title,
            main.mw-page .main-top .heading.mw-page-title {
                font-size: var(--mw-font-page-title-lg) !important;
            }
        }
        .Dashboard .main-top > .heading::after,
        .Dashboard .main-top > h1.heading::after,
        main.mw-page .mw-page-title::after,
        main.mw-page .main-top .heading.mw-page-title::after {
            content: none !important;
            display: none !important;
            border: 0 !important;
        }
        .Dashboard .main-top .breadcrumb,
        .Dashboard .main-top .breadcrumb .breadcrumb-item,
        main.mw-page .mw-breadcrumb,
        main.mw-page .mw-breadcrumb .breadcrumb-item {
            font-size: var(--mw-font-breadcrumb) !important;
            line-height: 1.4 !important;
        }
        .Dashboard .main-top nav {
            display: block !important;
        }
        main.mw-page .mw-section-title,
        main.mw-page .heading.mw-section-title,
        main.mw-page .heading1.mw-section-title,
        main.mw-page .heading2.mw-section-title {
            font-size: var(--mw-font-section-title) !important;
            line-height: 1.3 !important;
            letter-spacing: normal !important;
            color: var(--mw-color-text) !important;
        }
        @media (min-width: 768px) {
            main.mw-page .mw-section-title,
            main.mw-page .heading.mw-section-title,
            main.mw-page .heading1.mw-section-title,
            main.mw-page .heading2.mw-section-title {
                font-size: var(--mw-font-section-title-lg) !important;
            }
        }
        main.mw-page .mw-label,
        main.mw-page .card-body .mw-label,
        main.mw-page .mw-form-group .mw-label {
            font-size: var(--mw-font-label) !important;
            line-height: 1.4 !important;
            font-weight: 600 !important;
            color: var(--mw-color-text) !important;
        }
        main.mw-page .mw-label-lg,
        main.mw-page .card-body .mw-label-lg {
            font-size: var(--mw-font-label-lg) !important;
            line-height: 1.3 !important;
        }
        @media (min-width: 768px) {
            main.mw-page .mw-label-lg,
            main.mw-page .card-body .mw-label-lg {
                font-size: var(--mw-font-label-lg-md) !important;
            }
        }
        main.mw-page .mw-input,
        main.mw-page .form-control.mw-input {
            font-size: var(--mw-font-body) !important;
            line-height: 1.4 !important;
            font-weight: 400 !important;
            min-height: var(--mw-input-height) !important;
            color: var(--mw-color-text) !important;
        }
        main.mw-page .mw-input-lg,
        main.mw-page .form-control.mw-input-lg {
            font-size: var(--mw-font-body-lg) !important;
            font-weight: 500 !important;
            min-height: var(--mw-input-height-lg) !important;
            padding: var(--mw-input-padding-y-lg) var(--mw-input-padding-x-lg) !important;
        }
        /* Legacy assets/css/common.css — bank form on mw-page pages */
        main.mw-page #bankDetailsForm input.mw-input,
        main.mw-page #bankDetailsForm .form-control.mw-input {
            padding: var(--mw-input-padding-y) var(--mw-input-padding-x) !important;
            font-size: var(--mw-font-body) !important;
            line-height: 1.4 !important;
            font-weight: 400 !important;
            min-height: var(--mw-input-height) !important;
            color: var(--mw-color-text) !important;
            background: var(--mw-color-surface) !important;
            border: 1px solid var(--mw-color-border) !important;
            border-radius: var(--mw-radius-input) !important;
        }
        main.mw-page #bankDetailsForm input.mw-input:focus,
        main.mw-page #bankDetailsForm .form-control.mw-input:focus {
            outline: none !important;
            border-color: var(--mw-color-primary) !important;
            box-shadow: var(--mw-ring-input) !important;
        }
        main.mw-page #bankDetailsForm input.mw-input[readonly],
        main.mw-page #bankDetailsForm .form-control.mw-input[readonly] {
            background: #f8fafc !important;
            color: var(--mw-color-text-subtle) !important;
        }
        main.mw-page #bankDetailsForm .mw-btn,
        main.mw-page #bankDetailsForm button.mw-btn {
            margin-top: 0 !important;
            font-size: var(--mw-font-btn) !important;
            padding: var(--mw-btn-padding-y) var(--mw-btn-padding-x) !important;
            border: none !important;
            opacity: 1 !important;
        }
        @media (min-width: 768px) {
            main.mw-page #bankDetailsForm .mw-btn,
            main.mw-page #bankDetailsForm button.mw-btn {
                width: auto !important;
                align-self: flex-start !important;
                display: inline-flex !important;
            }
        }
        @media (max-width: 767.98px) {
            main.mw-page #bankDetailsForm .mw-btn,
            main.mw-page #bankDetailsForm button.mw-btn {
                display: flex !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                justify-content: center !important;
                align-self: stretch !important;
            }
        }
        main.mw-page .Product-ServicesBtn.mw-btn-row,
        main.mw-page .mw-btn-row.Product-ServicesBtn {
            padding: 0 !important;
            margin-top: 1rem !important;
        }
        main.mw-page .mw-btn-row .mw-btn,
        main.mw-page .mw-btn-row .btn.mw-btn,
        main.mw-page .card-body .mw-btn,
        main.mw-page .card-body .btn.mw-btn,
        main.mw-page .Product-ServicesBtn .btn-primary,
        main.mw-page .Product-ServicesBtn .btn-secondary {
            display: inline-flex !important;
            font-size: var(--mw-font-btn) !important;
            line-height: 1.4 !important;
            font-weight: 600 !important;
            margin: 0 !important;
            margin-top: 0 !important;
            padding: var(--mw-btn-padding-y) var(--mw-btn-padding-x) !important;
        }
        main.mw-page .Product-ServicesBtn button span:not(.angle),
        main.mw-page .Product-ServicesBtn a span:not(.angle) {
            font-size: inherit !important;
            font-weight: inherit !important;
        }
        @media (max-width: 767.98px) {
            :root {
                --mw-content-padding-top: 1.25rem;
                --mw-page-header-gap: 0rem;
                --mw-page-header-padding-bottom: 0.75rem;
            }
            .mw-page-header,
            .Dashboard .main-top,
            main.Dashboard .main-top,
            main.mw-page .main-top.mw-page-header {
                flex-direction: column !important;
                align-items: stretch !important;
                justify-content: flex-start !important;
                margin-bottom: var(--mw-page-header-gap) !important;
                padding-bottom: var(--mw-page-header-padding-bottom) !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            main.mw-page .mw-card-body { padding: 1rem; }
        }

        /* Buttons & button row ------------------------------------------ */
        .mw-btn-row       { display: flex; flex-direction: column; gap: 0.75rem; align-items: stretch; justify-content: space-between; margin-top: 1rem; width: 100%; box-sizing: border-box; }
        @media (min-width: 768px) {
            .mw-btn-row {
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 0.75rem !important;
            }
            .mw-btn-row .mw-btn,
            .mw-btn-row .save_btn {
                position: static !important;
                width: auto !important;
                margin: 0 !important;
                flex: 0 0 auto !important;
                order: unset !important;
            }
        }

        .mw-btn,
        a.mw-btn,
        button.mw-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: var(--mw-btn-padding-y) var(--mw-btn-padding-x);
            font-size: var(--mw-font-btn);
            font-weight: 600;
            line-height: 1.4;
            border: 1px solid transparent;
            border-radius: var(--mw-radius-btn);
            text-decoration: none;
            cursor: pointer;
            transition: background .15s, color .15s, border-color .15s, box-shadow .15s;
            white-space: nowrap;
            box-sizing: border-box;
            vertical-align: middle;
        }
        .mw-btn:focus     { outline: none; }
        .mw-btn:focus-visible { box-shadow: var(--mw-ring-focus); }
        .mw-btn:disabled,
        .mw-btn[aria-disabled="true"] { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
        .mw-btn-sm        { padding: var(--mw-btn-padding-y-sm) var(--mw-btn-padding-x-sm); font-size: var(--mw-font-btn-sm); min-height: 2rem; }
        .mw-btn-lg        { padding: var(--mw-btn-padding-y) var(--mw-btn-padding-x-lg); }
        .mw-btn-icon      { width: var(--mw-btn-icon-size); height: var(--mw-btn-icon-size); min-width: var(--mw-btn-icon-size); padding: 0; }
        .mw-btn-img       { width: 1.25rem; height: 1.25rem; flex-shrink: 0; object-fit: contain; }

        /* Solid variants */
        .mw-btn-back,
        .mw-btn-next      { background: var(--mw-color-secondary); color: #fff; box-shadow: var(--mw-shadow-soft); border-color: var(--mw-color-secondary); }
        .mw-btn-back:hover,
        .mw-btn-next:hover { background: var(--mw-color-secondary-dark); color: #fff; text-decoration: none; }
        .mw-btn-secondary { background: #f1f5f9; color: var(--mw-color-text); border-color: #e2e8f0; }
        .mw-btn-secondary:hover { background: #e2e8f0; color: var(--mw-color-text); text-decoration: none; }
        .mw-btn-save,
        .mw-btn-primary   { background: var(--mw-color-primary); color: var(--mw-color-secondary); box-shadow: var(--mw-shadow-soft); border-color: var(--mw-color-primary); }
        .mw-btn-save:hover,
        .mw-btn-primary:hover { background: var(--mw-color-primary-dark); color: var(--mw-color-secondary); box-shadow: var(--mw-shadow-card); text-decoration: none; }
        .mw-btn-accent    { background: var(--mw-modal-accent, #ffc107); color: #1a2b4b; border-color: var(--mw-modal-accent, #ffc107); }
        .mw-btn-accent:hover { background: var(--mw-modal-accent-hover, #e6ac00); border-color: var(--mw-modal-accent-hover, #e6ac00); color: #1a2b4b; text-decoration: none; }
        .mw-btn-cancel    { background: var(--mw-modal-cancel-bg, #f7f8fb); color: var(--mw-modal-cancel-text, #4e5873); border-color: var(--mw-modal-cancel-border, #c8cfdd); }
        .mw-btn-cancel:hover { background: #eef1f6; color: var(--mw-modal-cancel-text, #4e5873); text-decoration: none; }
        .mw-btn-danger    { background: var(--mw-color-danger); color: #fff; border-color: var(--mw-color-danger); }
        .mw-btn-danger:hover { background: #b91c1c; color: #fff; text-decoration: none; }
        .mw-btn-success   { background: var(--mw-color-success); color: #fff; border-color: var(--mw-color-success); }
        .mw-btn-success:hover { background: #15803d; color: #fff; text-decoration: none; }
        .mw-btn-warning   { background: var(--mw-color-warning); color: #fff; border-color: var(--mw-color-warning); }
        .mw-btn-warning:hover { background: #b45309; color: #fff; text-decoration: none; }
        .mw-btn-info      { background: #278de6; color: #fff; border-color: #278de6; }
        .mw-btn-info:hover { background: #1f74c2; color: #fff; text-decoration: none; }

        /* Outline variants */
        .mw-btn-outline-primary   { background: #fff; color: #2d6adf; border-color: #2d6adf; }
        .mw-btn-outline-primary:hover { background: #f0f6ff; color: #1f58c6; border-color: #1f58c6; text-decoration: none; }
        .mw-btn-outline-secondary { background: #fff; color: var(--mw-color-text-muted); border-color: var(--mw-color-border-strong); }
        .mw-btn-outline-secondary:hover { background: #f8fafc; color: var(--mw-color-text); text-decoration: none; }
        .mw-btn-outline-success   { background: #fff; color: var(--mw-color-success); border-color: var(--mw-color-success); }
        .mw-btn-outline-success:hover { background: var(--mw-color-success-bg); text-decoration: none; }
        .mw-btn-outline-danger    { background: #fff; color: var(--mw-color-danger); border-color: var(--mw-color-danger); }
        .mw-btn-outline-danger:hover { background: var(--mw-color-danger-bg); text-decoration: none; }
        .mw-btn-outline-warning   { background: #fff; color: var(--mw-color-warning); border-color: var(--mw-color-warning); }
        .mw-btn-outline-warning:hover { background: var(--mw-color-warning-bg); text-decoration: none; }
        .mw-btn-outline-info      { background: #fff; color: #278de6; border-color: #278de6; }
        .mw-btn-outline-info:hover { background: #eff6ff; text-decoration: none; }

        .mw-btn-angle     { display: inline-flex; align-items: center; justify-content: center; width: var(--mw-btn-angle); height: var(--mw-btn-angle); border-radius: 9999px; background: #fff; color: var(--mw-color-text); box-shadow: 0 1px 2px rgb(0 0 0 / 0.08); font-size: 0.75rem; flex-shrink: 0; }
        .mw-btn-back .mw-btn-angle,
        .mw-btn-next .mw-btn-angle { background: rgb(255 255 255 / 0.15); color: #fff; box-shadow: none; }

        /* Site-wide: .mw-btn wins over legacy styles.css Bootstrap rules */
        main.Dashboard .mw-btn,
        main.Dashboard a.mw-btn,
        main.Dashboard button.mw-btn,
        #layoutSidenav_content .mw-btn {
            display: inline-flex !important;
            font-size: var(--mw-font-btn) !important;
            line-height: 1.4 !important;
            font-weight: 600 !important;
            margin: 0 !important;
            margin-top: 0 !important;
            padding: var(--mw-btn-padding-y) var(--mw-btn-padding-x) !important;
            min-height: auto !important;
            text-decoration: none !important;
        }
        main.Dashboard .mw-btn-sm,
        #layoutSidenav_content .mw-btn-sm {
            padding: var(--mw-btn-padding-y-sm) var(--mw-btn-padding-x-sm) !important;
            font-size: var(--mw-font-btn-sm) !important;
            min-height: 2rem !important;
        }
        main.Dashboard .mw-btn-row,
        #layoutSidenav_content .mw-btn-row {
            padding: 0 !important;
            margin-top: 1rem !important;
        }
        main.Dashboard .mw-btn-row .mw-btn span:not(.mw-btn-angle),
        #layoutSidenav_content .mw-btn-row .mw-btn span:not(.mw-btn-angle) {
            font-size: inherit !important;
            font-weight: inherit !important;
            color: inherit !important;
        }
        main.Dashboard .mw-btn.mw-btn-back,
        main.Dashboard a.mw-btn.mw-btn-back,
        main.Dashboard .mw-btn.mw-btn-next,
        main.Dashboard a.mw-btn.mw-btn-next,
        main.mw-page .mw-btn.mw-btn-back,
        main.mw-page a.mw-btn.mw-btn-back,
        main.mw-page .mw-btn.mw-btn-next,
        main.mw-page a.mw-btn.mw-btn-next,
        #layoutSidenav_content .mw-btn.mw-btn-back,
        #layoutSidenav_content .mw-btn.mw-btn-next {
            background: #5c6b7a !important;
            color: #fff !important;
            border-color: #5c6b7a !important;
            box-shadow: var(--mw-shadow-soft) !important;
            font-size: 20px !important;
        }
        main.Dashboard .mw-btn.mw-btn-back:hover,
        main.Dashboard a.mw-btn.mw-btn-back:hover,
        main.Dashboard .mw-btn.mw-btn-next:hover,
        main.Dashboard a.mw-btn.mw-btn-next:hover,
        main.mw-page .mw-btn.mw-btn-back:hover,
        main.mw-page a.mw-btn.mw-btn-back:hover,
        main.mw-page .mw-btn.mw-btn-next:hover,
        main.mw-page a.mw-btn.mw-btn-next:hover,
        #layoutSidenav_content .mw-btn.mw-btn-back:hover,
        #layoutSidenav_content .mw-btn.mw-btn-next:hover {
            background: #4d5966 !important;
            border-color: #4d5966 !important;
            color: #fff !important;
        }
        main.Dashboard .mw-btn.mw-btn-save,
        main.Dashboard button.mw-btn.mw-btn-save,
        main.Dashboard a.mw-btn.mw-btn-save,
        main.Dashboard .save_btn.mw-btn,
        main.mw-page .mw-btn.mw-btn-save,
        main.mw-page button.mw-btn.mw-btn-save,
        main.mw-page a.mw-btn.mw-btn-save,
        main.mw-page .save_btn.mw-btn,
        #layoutSidenav_content .mw-btn.mw-btn-save,
        #layoutSidenav_content .save_btn.mw-btn {
            background: #ffbe17 !important;
            border-color: #ffbe17 !important;
            color: #0f172a !important;
            box-shadow: var(--mw-shadow-soft) !important;
            font-size: 20px !important;
        }
        main.Dashboard .mw-btn.mw-btn-save:hover,
        main.Dashboard button.mw-btn.mw-btn-save:hover,
        main.mw-page .mw-btn.mw-btn-save:hover,
        main.mw-page button.mw-btn.mw-btn-save:hover,
        main.mw-page .save_btn.mw-btn:hover,
        #layoutSidenav_content .mw-btn.mw-btn-save:hover,
        #layoutSidenav_content .save_btn.mw-btn:hover {
            background: #e6ab15 !important;
            border-color: #e6ab15 !important;
            color: #0f172a !important;
        }
        main.Dashboard .Product-ServicesBtn.mw-btn-row .mw-btn span:not(.mw-btn-angle):not(.angle),
        main.mw-page .Product-ServicesBtn.mw-btn-row .mw-btn span:not(.mw-btn-angle):not(.angle),
        main.Dashboard .Product-ServicesBtn.mw-btn-row .save_btn span:not(.mw-btn-angle):not(.angle),
        main.mw-page .Product-ServicesBtn.mw-btn-row .save_btn span:not(.mw-btn-angle):not(.angle) {
            font-size: inherit !important;
        }
        main.Dashboard .Product-ServicesBtn.mw-btn-row .mw-btn-back .mw-btn-angle,
        main.Dashboard .Product-ServicesBtn.mw-btn-row .mw-btn-back .angle,
        main.Dashboard .Product-ServicesBtn.mw-btn-row .mw-btn-next .mw-btn-angle,
        main.Dashboard .Product-ServicesBtn.mw-btn-row .mw-btn-next .angle,
        main.mw-page .Product-ServicesBtn.mw-btn-row .mw-btn-back .mw-btn-angle,
        main.mw-page .Product-ServicesBtn.mw-btn-row .mw-btn-back .angle,
        main.mw-page .Product-ServicesBtn.mw-btn-row .mw-btn-next .mw-btn-angle,
        main.mw-page .Product-ServicesBtn.mw-btn-row .mw-btn-next .angle {
            background: rgb(255 255 255 / 0.15) !important;
            color: #fff !important;
            box-shadow: none !important;
        }

        /* Step nav (mobile layout) — replaces legacy website-step-nav.css ---
           On screens < 768px the website-builder Back/Save/Next row is laid out
           as: Save full width on row 1, Back | Next 50/50 on row 2. All
           !important so we win against any lingering legacy rules until
           website-step-nav.css is deleted in Step 16. */
        @media screen and (max-width: 767.98px) {
            .mw-btn-row {
                flex-direction: row !important;
                flex-wrap: wrap !important;
                align-items: stretch !important;
                gap: 0.75rem !important;
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin-top: 1.75rem !important;
                margin-left: auto !important;
                margin-right: auto !important;
                box-sizing: border-box !important;
            }
            .mw-btn-row .mw-btn {
                margin: 0 !important;
                min-height: 3rem !important;
                box-sizing: border-box !important;
            }
            .mw-btn-row .mw-btn-save {
                order: 1 !important;
                flex: 1 1 100% !important;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
            }
            .mw-btn-row .mw-btn-back {
                order: 2 !important;
                flex: 1 1 calc(50% - 0.375rem) !important;
                max-width: calc(50% - 0.375rem) !important;
            }
            .mw-btn-row .mw-btn-next {
                order: 3 !important;
                flex: 1 1 calc(50% - 0.375rem) !important;
                max-width: calc(50% - 0.375rem) !important;
            }
            .mw-btn-row .mw-btn-angle {
                width: 1.375rem !important;
                height: 1.375rem !important;
                min-width: 1.375rem !important;
                min-height: 1.375rem !important;
                font-size: 0.875rem !important;
            }
        }

        /* Product / Services / Offers / Gallery tables ------------------
           Carried over from legacy website-step-nav.css (deleted in Step 16)
           so step-page tables remain readable on small screens. */
        main.Dashboard .customer_content_area .Product-ServicesTable .image-gallery-actions,
        .Dashboard .customer_content_area .Product-ServicesTable .image-gallery-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            flex-wrap: nowrap;
            vertical-align: middle;
        }
        main.Dashboard .customer_content_area .Product-ServicesTable .image-gallery-actions a,
        .Dashboard .customer_content_area .Product-ServicesTable .image-gallery-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            min-width: 1.75rem;
            min-height: 1.75rem;
            line-height: 1;
        }
        main.Dashboard .customer_content_area .Product-ServicesTable .image-gallery-actions .fa-edit,
        .Dashboard .customer_content_area .Product-ServicesTable .image-gallery-actions .fa-edit {
            font-size: 1rem;
            color: var(--mw-color-info);
        }
        main.Dashboard .customer_content_area .Product-ServicesTable .image-gallery-actions .fa-trash,
        .Dashboard .customer_content_area .Product-ServicesTable .image-gallery-actions .fa-trash {
            font-size: 1rem;
            color: var(--mw-color-danger);
        }

        /* Website builder tables — all viewports: one-line headers + horizontal scroll */
        main.Dashboard .customer_content_area .Product-ServicesTable,
        .Dashboard .customer_content_area .Product-ServicesTable,
        main.mw-page .Product-ServicesTable {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
            box-sizing: border-box;
        }
        main.Dashboard .customer_content_area .Product-ServicesTable table.display.table,
        .Dashboard .customer_content_area .Product-ServicesTable table.display.table,
        main.mw-page .Product-ServicesTable table.display.table {
            width: max-content;
            min-width: 100%;
            max-width: none;
            margin-bottom: 0;
            table-layout: auto;
        }
        main.Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact table.display.table,
        .Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact table.display.table,
        main.mw-page .Product-ServicesTable.image-gallery-table-compact table.display.table {
            width: 100%;
            min-width: 17.5rem;
        }
        main.Dashboard .customer_content_area .Product-ServicesTable table.display.table thead th,
        .Dashboard .customer_content_area .Product-ServicesTable table.display.table thead th,
        main.mw-page .Product-ServicesTable table.display.table thead th {
            white-space: nowrap !important;
            vertical-align: middle;
            width: auto !important;
            background-color: var(--mw-table-header-bg) !important;
            color: var(--mw-table-header-text) !important;
            font-size: var(--mw-table-header-font) !important;
            font-weight: var(--mw-table-header-weight) !important;
            line-height: 1.3;
            padding: var(--mw-table-header-padding-y) var(--mw-table-header-padding-x) !important;
            text-align: left;
            border: 0 !important;
            border-bottom: 2px solid var(--mw-table-header-border) !important;
        }
        main.mw-page .Product-ServicesTable table.display.table thead th.text-right {
            text-align: right !important;
        }
        main.Dashboard .customer_content_area .Product-ServicesTable table.display.table tbody td,
        .Dashboard .customer_content_area .Product-ServicesTable table.display.table tbody td,
        main.mw-page .Product-ServicesTable table.display.table tbody td {
            padding: var(--mw-table-cell-padding-y) var(--mw-table-cell-padding-x);
            vertical-align: middle !important;
            text-align: left !important;
            font-weight: 400 !important;
            border-bottom: 2px solid var(--mw-table-cell-border) !important;
        }
        main.mw-page .Product-ServicesTable table.display.table tbody td.text-right,
        main.mw-page .Product-ServicesTable.image-gallery-table-compact table.display.table tbody td:nth-child(2) {
            text-align: right !important;
        }
        main.Dashboard .customer_content_area .Product-ServicesTable table.display.table th,
        main.Dashboard .customer_content_area .Product-ServicesTable table.display.table td,
        .Dashboard .customer_content_area .Product-ServicesTable table.display.table th,
        .Dashboard .customer_content_area .Product-ServicesTable table.display.table td,
        main.mw-page .Product-ServicesTable table.display.table th,
        main.mw-page .Product-ServicesTable table.display.table td {
            width: auto !important;
        }
        main.Dashboard .customer_content_area .Product-ServicesTable table.display.table .product-th-with-filter,
        main.mw-page .Product-ServicesTable table.display.table .product-th-with-filter {
            white-space: nowrap !important;
        }
        main.mw-page .mw-card .mw-card-body,
        main.mw-page .card .card-body {
            min-width: 0;
            overflow-x: visible;
        }

        @media screen and (max-width: 767.98px) {
            main.Dashboard .customer_content_area .Product-ServicesTable,
            .Dashboard .customer_content_area .Product-ServicesTable {
                margin-left: 0;
                margin-right: 0;
            }
            main.Dashboard .customer_content_area .Product-ServicesTable table.display.table tbody td,
            .Dashboard .customer_content_area .Product-ServicesTable table.display.table tbody td {
                padding: var(--mw-table-cell-padding-y) 0.75rem !important;
            }
            main.Dashboard .customer_content_area .Product-ServicesTable table.display.table thead th,
            .Dashboard .customer_content_area .Product-ServicesTable table.display.table thead th {
                padding: var(--mw-table-cell-padding-y) 0.75rem !important;
            }
            main.Dashboard .customer_content_area .Product-ServicesTable table.display.table tbody td:first-child,
            .Dashboard .customer_content_area .Product-ServicesTable table.display.table tbody td:first-child {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            main.Dashboard .customer_content_area .Product-ServicesTable table.display.table thead th:first-child,
            .Dashboard .customer_content_area .Product-ServicesTable table.display.table thead th:first-child {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }

            /* Image gallery: stable 2-col layout; scroll wrapper; icons never overlap */
            main.Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact,
            .Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }
            main.Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact table.display.table.table-image-gallery,
            .Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact table.display.table.table-image-gallery {
                width: 100% !important;
                min-width: 17.5rem !important;
                max-width: 100% !important;
                white-space: normal !important;
                table-layout: fixed !important;
                border-collapse: collapse !important;
            }
            main.Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact table.display.table th:first-child,
            .Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact table.display.table th:first-child {
                width: auto !important;
                min-width: 0 !important;
                padding: 0.625rem 0.5rem !important;
                vertical-align: middle !important;
                box-sizing: border-box !important;
            }
            main.Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact table.display.table td:first-child,
            .Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact table.display.table td:first-child {
                width: auto !important;
                min-width: 0 !important;
                padding: 0.625rem 0.5rem !important;
                vertical-align: middle !important;
                overflow: hidden !important;
                box-sizing: border-box !important;
            }
            main.Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact table.display.table th:nth-child(2),
            main.Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact table.display.table td:nth-child(2),
            .Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact table.display.table th:nth-child(2),
            .Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact table.display.table td:nth-child(2) {
                width: 7.25rem !important;
                min-width: 7.25rem !important;
                max-width: 7.5rem !important;
                padding: 0.625rem 0.5rem !important;
                text-align: right !important;
                vertical-align: middle !important;
                box-sizing: border-box !important;
                overflow: visible !important;
            }
            main.Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact .image-gallery-actions,
            .Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact .image-gallery-actions {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: flex-end !important;
                gap: 0.75rem !important;
                flex-wrap: nowrap !important;
                max-width: 100% !important;
            }
            main.Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact .image-gallery-actions a,
            .Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact .image-gallery-actions a {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                flex: 0 0 auto !important;
                min-width: 2.25rem !important;
                min-height: 2.25rem !important;
                box-sizing: border-box !important;
            }
            main.Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact .image-gallery-actions .fa-edit,
            .Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact .image-gallery-actions .fa-edit {
                font-size: 1rem !important;
                color: var(--mw-color-info) !important;
                line-height: 1 !important;
            }
            main.Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact .image-gallery-actions .fa-trash,
            .Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact .image-gallery-actions .fa-trash {
                font-size: 1rem !important;
                color: var(--mw-color-danger) !important;
                line-height: 1 !important;
            }
            main.Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact table.display.table td:first-child img,
            .Dashboard .customer_content_area .Product-ServicesTable.image-gallery-table-compact table.display.table td:first-child img {
                width: 2.25rem !important;
                max-width: 2.25rem !important;
                height: auto !important;
                vertical-align: middle !important;
                display: inline-block !important;
            }
        }

        /* Pill / badge -------------------------------------------------- */
        .mw-pill          { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.15rem 0.5rem; border-radius: var(--mw-radius-pill); font-size: var(--mw-font-pill); font-weight: 700; line-height: 1; }
        .mw-pill-primary  { background: var(--mw-color-primary); color: var(--mw-color-secondary); box-shadow: var(--mw-shadow-card); }
        .mw-pill-muted    { background: #f1f5f9; color: var(--mw-color-text-muted); }
        .mw-pill-success  { background: var(--mw-color-success-bg); color: var(--mw-color-success-text); }
        .mw-pill-danger   { background: var(--mw-color-danger-bg);  color: var(--mw-color-danger-text); }

        /* Modal styles: common/mw_modal.php → mw_modal_print_styles() */

        /* Sidebar nav icon (Font Awesome) -------------------------------- */
        .mw-nav-icon      { font-size: var(--mw-icon); line-height: 1; color: var(--mw-color-text-muted); transition: color .15s; }
        .sb-sidenav .sb-sidenav-menu .nav .nav-link:hover .mw-nav-icon {
            color: var(--mw-color-nav-active);
        }
        .sb-sidenav .sb-sidenav-menu .nav .nav-link.active,
        .sb-sidenav .sb-sidenav-menu .nav .nav-link.mw-nav-link-active {
            color: var(--mw-color-nav-active) !important;
            background-color: color-mix(in srgb, var(--mw-color-nav-active) 10%, transparent) !important;
            border-left-color: var(--mw-color-nav-active) !important;
        }
        .sb-sidenav .sb-sidenav-menu .nav .nav-link.active .mw-nav-icon,
        .sb-sidenav .sb-sidenav-menu .nav .nav-link.mw-nav-link-active .mw-nav-icon {
            color: var(--mw-color-nav-active) !important;
        }
        .sb-sidenav .sb-sidenav-menu .nav .nav-link.active .sb-nav-link-icon,
        .sb-sidenav .sb-sidenav-menu .nav .nav-link.mw-nav-link-active .sb-nav-link-icon {
            background-color: color-mix(in srgb, var(--mw-color-nav-active) 14%, transparent) !important;
        }

        /* App footer (user/includes/footer.php) */
        footer[role="contentinfo"] .Copyright {
            font-size: var(--mw-font-body, 0.875rem);
        }
        footer[role="contentinfo"] .Copyright-right,
        footer[role="contentinfo"] .Copyright-right p {
            color: var(--mw-color-nav-active, #2b4ba9) !important;
            font-size: var(--mw-font-caption, 0.75rem) !important;
        }
        @media (max-width: 767.98px) {
            footer[role="contentinfo"] .Copyright {
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                text-align: center !important;
                gap: 0.5rem !important;
            }
            footer[role="contentinfo"] .Copyright-left,
            footer[role="contentinfo"] .Copyright-right {
                width: 100%;
                padding-left: 0 !important;
                padding-right: 0 !important;
                text-align: center !important;
            }
            footer[role="contentinfo"] .Copyright-left a {
                justify-content: center !important;
            }
        }

        /* Header profile chip — right-align name + ID lines */
        .upload-profile .profile-text       { align-items: flex-end; text-align: right; }
        .upload-profile .profile-name-line  { justify-content: flex-end; }
        .upload-profile .profile-mw-id      { text-align: right; }

        /* Top nav — mobile mark + full logo on desktop (≥768px) */
        .sb-topnav.navbar,
        .sb-topnav {
            height: var(--mw-topnav-height) !important;
            min-height: var(--mw-topnav-height) !important;
            padding-top: 0.625rem !important;
            padding-bottom: 0.625rem !important;
            align-items: stretch !important;
            background: #fff !important;
            border-bottom: 1px solid var(--mw-color-border);
        }
        .sb-topnav .head-left {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            flex: 0 0 var(--mw-sidebar-width) !important;
            width: var(--mw-sidebar-width) !important;
            max-width: var(--mw-sidebar-width) !important;
            min-width: 0;
            box-sizing: border-box;
            padding-left: 1rem !important;
            padding-right: 0.75rem !important;
            gap: 0.5rem;
        }
        .sb-topnav .navbar-brand {
            width: auto !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
            flex-shrink: 1;
            min-width: 0;
        }
        .sb-topnav .navbar-brand .mw-logo {
            width: auto !important;
            height: auto !important;
            object-fit: contain;
            object-position: left center;
        }
        .sb-topnav .navbar-brand .mw-logo--desktop { display: none !important; }
        .sb-topnav .navbar-brand .mw-logo--mobile {
            display: block !important;
            max-height: 2.5rem !important;
            max-width: 9rem !important;
        }
        @media (min-width: 768px) {
            .sb-topnav .navbar-brand .mw-logo--desktop {
                display: block !important;
                max-width: 16.9rem !important;
                max-height: 4.55rem !important;
            }
            .sb-topnav .navbar-brand .mw-logo--mobile { display: none !important; }
            .sb-topnav .head-left { padding-left: 1.25rem !important; }
        }
        @media (max-width: 767.98px) {
            :root { --mw-sidebar-width: var(--mw-sidebar-width-mobile, 280px); }
            /* Prevent profile chip from covering logo / sidebar toggle on narrow viewports */
            .sb-topnav .head-left {
                flex: 0 1 auto !important;
                width: auto !important;
                max-width: calc(100vw - 8.75rem) !important;
                padding-left: 0.5rem !important;
                padding-right: 0.375rem !important;
            }
            .sb-topnav .head-left .navbar-brand.ps-3 {
                padding-left: 0 !important;
            }
            .sb-topnav .navbar-brand {
                flex: 1 1 auto;
                min-width: 0;
                max-width: calc(100% - 3.25rem) !important;
            }
            .sb-topnav .navbar-brand .mw-logo--mobile {
                max-height: 2rem !important;
                max-width: 6.5rem !important;
            }
            .sb-topnav .head-right {
                flex: 0 0 auto;
                flex-shrink: 0;
                min-width: 0;
                padding-right: 0.5rem !important;
            }
            .sb-topnav #sidebarToggle {
                position: relative;
                z-index: 2;
            }
            .upload-profile-wrap {
                width: auto !important;
                max-width: 8.25rem;
            }
            .upload-profile-wrap .upload-profile .profile-mw-id {
                display: none !important;
            }
            .upload-profile-wrap .upload-profile .profile-text {
                min-width: 0;
                max-width: 4.25rem;
                overflow: hidden;
            }
            .upload-profile-wrap .upload-profile .profile-name-line {
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .upload-profile-wrap .upload-profile .circle img.profile-pic {
                width: 42px !important;
                height: 42px !important;
            }
            .upload-profile-wrap .upload-profile .p-image {
                left: 28px;
            }
        }
        /* Vertical rule — lines up with sidebar / main content split */
        .mw-topnav-divider {
            flex: 0 0 1px;
            align-self: stretch;
            width: 1px;
            margin: 0;
            padding: 0;
            border: 0;
        }
        #sidebarToggle,
        .sb-topnav #sidebarToggle,
        .sb-topnav.navbar-dark #sidebarToggle {
            flex-shrink: 0;
            margin: 0 !important;
            width: 2rem !important;
            height: 2rem !important;
            min-width: 2rem !important;
            min-height: 2rem !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: var(--mw-color-text-muted) !important;
            background: #f1f5f9 !important;
            border: 1px solid var(--mw-color-border) !important;
            border-radius: var(--mw-radius-input) !important;
            padding: 0 !important;
        }
        #sidebarToggle .fa,
        .sb-topnav #sidebarToggle .fa {
            font-size: 1rem !important;
            line-height: 1 !important;
        }
        .sb-nav-fixed #layoutSidenav #layoutSidenav_content {
            top: var(--mw-topnav-height) !important;
        }

        /* Profile quick-nav dropdown — keep fully visible, anchor to the right */
        .sb-topnav,
        .sb-topnav .head-right,
        .sb-topnav .head-right .navbar-nav,
        .upload-profile-wrap {
            overflow: visible !important;
        }
        .sb-topnav .head-right {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            flex: 1 1 auto;
            min-width: 0;
            position: relative;
            padding-right: 1rem;
        }
        .sb-topnav .head-right > .navbar-nav {
            width: auto !important;
            margin-left: auto !important;
            margin-right: 0 !important;
        }
        .upload-profile-wrap {
            position: relative !important;
            width: max-content !important;
            max-width: 100%;
            margin-left: auto !important;
        }
        .upload-profile-wrap > .nav-link {
            display: inline-flex !important;
            justify-content: flex-end !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        .upload-profile-wrap .upload-profile {
            margin-left: auto;
        }
        .navbar-expand .sb-topnav .head-right .navbar-nav .mw-profile-dropdown.dropdown-menu,
        .upload-profile-wrap .mw-profile-dropdown.dropdown-menu {
            position: absolute !important;
            top: calc(100% + 0.375rem) !important;
            right: 0 !important;
            left: auto !important;
            transform: none !important;
            min-width: 16rem !important;
            width: max-content !important;
            max-width: min(20rem, calc(100vw - 1.5rem)) !important;
            z-index: 1050 !important;
            overflow: hidden !important;
            margin: 0 !important;
            display: none;
            background-color: #fff !important;
            border: 1px solid var(--mw-color-border) !important;
            border-radius: 0.75rem !important;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1) !important;
            padding: 0.375rem !important;
        }
        .upload-profile-wrap .mw-profile-dropdown.dropdown-menu.show {
            display: block !important;
        }
        .upload-profile-wrap .mw-profile-dropdown.dropdown-menu,
        .upload-profile-wrap .mw-profile-dropdown .dropdown-item,
        .upload-profile-wrap .mw-profile-dropdown .dropdown-item span {
            font-size: 14px !important;
        }
        .upload-profile-wrap .mw-profile-dropdown .dropdown-item {
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            white-space: nowrap !important;
            overflow: visible !important;
        }
        .upload-profile-wrap .mw-profile-dropdown .dropdown-item span,
        .upload-profile-wrap .mw-profile-dropdown .dropdown-item i {
            flex-shrink: 0 !important;
            overflow: visible !important;
            text-overflow: clip !important;
            white-space: nowrap !important;
        }
        @media screen and (max-width: 767.98px) {
            .upload-profile-wrap .mw-profile-dropdown.dropdown-menu {
                right: 0 !important;
                left: auto !important;
                max-width: calc(100vw - 1.25rem) !important;
                font-size: 16px !important;
            }
            .upload-profile-wrap .mw-profile-dropdown .dropdown-item,
            .upload-profile-wrap .mw-profile-dropdown .dropdown-item span {
                font-size: 16px !important;
            }
        }

        /* Responsive table scroll — wide tables stay inside the viewport */
        .mw-table-scroll {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
            box-sizing: border-box;
        }
        .mw-table-scroll > table,
        .mw-table-scroll > .table,
        .mw-table-scroll table.display.table {
            width: max-content;
            min-width: 100%;
            margin-bottom: 0;
        }
        .mw-table-scroll table thead th {
            white-space: nowrap;
            vertical-align: middle;
        }
        .mw-table-scroll.mw-table-scroll-wide > table,
        .mw-table-scroll.mw-table-scroll-wide > .table,
        .mw-table-scroll.mw-table-scroll-wide table.display.table { min-width: 52rem; }
        .mw-table-scroll.mw-table-scroll-xl > table,
        .mw-table-scroll.mw-table-scroll-xl > .table,
        .mw-table-scroll.mw-table-scroll-xl table.display.table { min-width: 64rem; }

        .mw-table-cell-inline {
            display: inline-flex;
            align-items: center;
            gap: 0.3125rem;
            flex-wrap: nowrap;
            vertical-align: middle;
        }

        /* Keep dashboard / main content from being pushed wider than the screen */
        #layoutSidenav_content { min-width: 0; max-width: 100%; box-sizing: border-box; }

        /* Mobile / tablet sidebar — wide enough for full menu labels; content fills viewport */
        @media (max-width: 991.98px) {
            #layoutSidenav {
                display: block !important;
            }
            #layoutSidenav #layoutSidenav_nav,
            .sb-nav-fixed #layoutSidenav #layoutSidenav_nav {
                position: fixed !important;
                top: var(--mw-topnav-height, 5.5rem) !important;
                left: 0 !important;
                bottom: 0 !important;
                height: auto !important;
                flex-basis: auto !important;
                width: var(--mw-sidebar-width-mobile, 280px) !important;
                max-width: min(var(--mw-sidebar-width-mobile, 280px), 88vw) !important;
                transform: translateX(-100%) !important;
                z-index: 1040 !important;
            }
            #layoutSidenav #layoutSidenav_content,
            .sb-nav-fixed #layoutSidenav #layoutSidenav_content {
                margin-left: 0 !important;
                padding-left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                flex: none !important;
                min-width: 0 !important;
                box-sizing: border-box !important;
            }
            body.sb-sidenav-toggled #layoutSidenav #layoutSidenav_nav {
                transform: translateX(0) !important;
            }
            body.sb-sidenav-toggled #layoutSidenav #layoutSidenav_content {
                margin-left: 0 !important;
                padding-left: 0 !important;
            }
            .sb-nav-fixed #layoutSidenav #layoutSidenav_nav .sb-sidenav {
                padding-top: 0 !important;
            }
            .sb-sidenav .sb-sidenav-menu .nav .nav-link .truncate {
                overflow: visible !important;
                text-overflow: clip !important;
                white-space: normal !important;
            }
        }

        @media (min-width: 992px) {
            body.sb-sidenav-toggled.sb-nav-fixed #layoutSidenav #layoutSidenav_content {
                padding-left: 0 !important;
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
        }
        main.Dashboard .customer_content_area,
        main.Dashboard .card,
        main.Dashboard .card-body { max-width: 100%; min-width: 0; box-sizing: border-box; }
        main.Dashboard .ManageUsers,
        main.Dashboard .table-container { max-width: 100%; min-width: 0; }

        /* Dashboard tables — full width on desktop, scroll on small screens */
        main.Dashboard .mw-dashboard-table-wrap,
        main.Dashboard #dashboardTableWrap {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
            box-sizing: border-box;
        }
        main.Dashboard .mw-dashboard-table-wrap table.display.table,
        main.Dashboard #dashboardTableWrap table.display.table {
            width: 100%;
            min-width: 100%;
            margin-bottom: 0;
            table-layout: auto;
        }
        @media screen and (min-width: 768px) {
            main.Dashboard .card-body > .mw-dashboard-table-wrap,
            main.Dashboard .ManageUsers > .mw-dashboard-table-wrap,
            main.Dashboard .card-body > #dashboardTableWrap {
                margin-left: -30px;
                margin-right: -30px;
                width: calc(100% + 60px);
                max-width: calc(100% + 60px);
            }
        }

        @media screen and (max-width: 767.98px) {
            main.Dashboard .card,
            main.Dashboard .card-body,
            main.Dashboard .customer_content_area {
                overflow-x: visible;
                max-width: 100%;
            }
            main.Dashboard .mw-dashboard-table-wrap,
            main.Dashboard #dashboardTableWrap {
                display: block;
                width: 100% !important;
                max-width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                overflow-x: auto !important;
                overflow-y: visible;
                -webkit-overflow-scrolling: touch;
                overscroll-behavior-x: contain;
            }
            main.Dashboard .mw-dashboard-table-wrap table#ReferredUsers,
            main.Dashboard #dashboardTableWrap table#ReferredUsers,
            main.Dashboard .mw-dashboard-table-wrap table.display.table {
                width: auto !important;
                max-width: none !important;
                min-width: 52rem !important;
                table-layout: auto !important;
                margin-bottom: 0 !important;
            }
            main.Dashboard .mw-dashboard-table-wrap table#ReferredUsers th,
            main.Dashboard .mw-dashboard-table-wrap table#ReferredUsers td,
            main.Dashboard #dashboardTableWrap table#ReferredUsers th,
            main.Dashboard #dashboardTableWrap table#ReferredUsers td {
                white-space: nowrap !important;
            }
            .mw-table-scroll table th,
            .mw-table-scroll table td { padding: 0.625rem 0.75rem; font-size: 0.8125rem; }
        }
    </style>
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
    <?php if ($current_dir === 'website'): ?>
    <style id="mw-website-form-controls-head">
        /* Website builder — normal weight on inputs/selects/textareas only */
        body.mw-website-builder .form-control,
        body.mw-website-builder input.form-control,
        body.mw-website-builder select.form-control,
        body.mw-website-builder textarea.form-control,
        body.mw-website-builder .form-control-sm,
        body.mw-website-builder .operation-locations-chips-field.form-control {
            font-weight: 400 !important;
        }
        body.mw-website-builder .form-control::placeholder {
            font-weight: 400 !important;
        }
    </style>
    <?php endif; ?>
</head>

<body class="sb-nav-fixed<?php echo ($current_dir === 'website') ? ' mw-website-builder' : ''; ?>">
    
    <!-- Top navigation (modernized in Phase A · Step 2) -->
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark !shadow-card">
        <div class="head-left flex items-center">
            <a class="navbar-brand ps-3 inline-flex items-center transition-opacity hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/60 focus-visible:ring-offset-2 focus-visible:ring-offset-secondary rounded-md"
               href="<?php echo $nav_base; ?>/dashboard"
               aria-label="Go to dashboard">
                <img src="<?php echo $assets_base; ?>/assets/images/mw_logo_mobile.png"
                     class="mw-logo mw-logo--mobile img-fluid"
                     alt="Mini Website"
                     width="144"
                     height="40">
                <img src="<?php echo $assets_base; ?>/assets/img/logo.png"
                     class="mw-logo mw-logo--desktop img-fluid"
                     alt="Mini Website — Create Share Succeed"
                     width="270"
                     height="73">
            </a>
            <button class="btn btn-link btn-sm order-1 order-lg-0 hover:!text-primary-dark focus:outline-none focus-visible:!ring-2 focus-visible:!ring-primary/60 !transition !no-underline"
                    id="sidebarToggle"
                    href="#!"
                    type="button"
                    aria-label="Toggle navigation sidebar"
                    aria-controls="layoutSidenav_nav"
                    aria-expanded="false">
                <i class="fa fa-bars" aria-hidden="true"></i>
            </button>
        </div>
        <span class="mw-topnav-divider" role="presentation" aria-hidden="true"></span>
        <div class="head-right">
            <ul class="navbar-nav ms-auto ms-md-0 flex items-center">
                <li class="nav-item upload-profile-wrap relative">
                    <!-- Profile chip: existing custom CSS handles all visual styling (.upload-profile, .circle, .profile-pic, .profile-text, .profile-name-line, .profile-mw-id, .p-image, .upload-button).
                         Only ARIA + focus ring + cursor are added here for accessibility. -->
                    <a class="nav-link cursor-pointer focus:outline-none focus-visible:!ring-2 focus-visible:!ring-primary/60 focus-visible:!ring-offset-2 !rounded-lg transition"
                       role="button"
                       tabindex="0"
                       aria-haspopup="true"
                       aria-expanded="false"
                       aria-label="Open profile menu">
                        <div class="upload-profile">
                            <div class="circle">
                                <img class="profile-pic"
                                     src="<?php echo htmlspecialchars($user_image); ?>"
                                     alt="<?php echo htmlspecialchars($user_name ?? 'User'); ?> profile"
                                     srcset="">
                                <div class="profile-text">
                                    <div class="profile-name-line">
                                        <?php echo htmlspecialchars($user_name ?? 'Guest'); ?>
                                        <i class="fa fa-angle-double-down" aria-hidden="true"></i>
                                    </div>
                                    <?php if ($current_role === 'FRANCHISEE' && !empty($fr_id)): ?>
                                    <span class="profile-mw-id">FR ID: <?php echo (int)$fr_id; ?></span>
                                    <?php elseif (!empty($mw_id)): ?>
                                    <span class="profile-mw-id">MW ID: MW<?php echo (int)$mw_id; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="p-image" title="Click to upload profile image (Max: 250KB, Formats: JPG, PNG, GIF)">
                                <img src="<?php echo $assets_base; ?>/assets/images/camera.png"
                                     class="upload-button img-fluid"
                                     alt="Upload profile photo">
                                <input class="file-upload" type="file" accept="image/*" name="profile_image" id="profile_image">
                            </div>
                        </div>
                    </a>
                    <!-- shadcn-style dropdown menu -->
                    <ul class="dropdown-menu dropdown-menu-end mw-profile-dropdown !mt-2 !min-w-[16rem] !rounded-xl !shadow-elevated !bg-white !border !border-border !p-1.5 !text-sm" role="menu">
                        <li role="none">
                            <a role="menuitem"
                               class="dropdown-item !flex !items-center !gap-2 !px-3 !py-2 !rounded-md !text-foreground hover:!bg-muted hover:!text-foreground focus:!bg-muted transition-colors"
                               href="<?php echo $nav_base; ?>/dashboard">
                                <i class="fa fa-tachometer w-4 text-muted-foreground" aria-hidden="true"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
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
                            <li role="none">
                                <a role="menuitem"
                                   class="dropdown-item !flex !items-center !gap-2 !px-3 !py-2 !rounded-md !text-foreground hover:!bg-muted hover:!text-foreground focus:!bg-muted transition-colors"
                                   href="<?php echo $menu_link; ?>">
                                    <i class="fa fa-angle-right w-4 text-muted-foreground" aria-hidden="true"></i>
                                    <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <li role="none">
                            <a role="menuitem"
                               class="dropdown-item !flex !items-center !gap-2 !px-3 !py-2 !rounded-md !text-foreground hover:!bg-muted hover:!text-foreground focus:!bg-muted transition-colors"
                               href="<?php echo $nav_base; ?>/change-password">
                                <i class="fa fa-key w-4 text-muted-foreground" aria-hidden="true"></i>
                                <span>Change Password</span>
                            </a>
                        </li>
                        <li role="none"><hr class="dropdown-divider !my-1.5 !border-border"></li>
                        <li role="none">
                            <a role="menuitem"
                               class="dropdown-item !flex !items-center !gap-2 !px-3 !py-2 !rounded-md !text-danger hover:!bg-red-50 hover:!text-danger focus:!bg-red-50 transition-colors"
                               href="<?php echo $assets_base; ?>/login/logout.php">
                                <i class="fa fa-sign-out w-4" aria-hidden="true"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion !bg-white !border-r !border-border" id="sidenavAccordion" aria-label="Primary">
                <div class="sb-sidenav-menu !py-2">
                    <div class="nav !gap-0.5 !px-2" role="navigation">
                        <?php
                        // Phase A · Step 2 — Reusable shadcn-style classes for sidebar nav links.
                        // Using "!" prefix to override existing high-specificity Bootstrap selectors
                        // (e.g. .sb-sidenav .sb-sidenav-menu .nav .nav-link) without modifying styles.css yet.
                        $nav_link_classes     = 'group !flex !items-center !gap-3 !my-0.5 !px-3 !py-2.5 !rounded-lg !text-sm !font-medium !text-slate-700 hover:!bg-slate-100 hover:!text-foreground focus:outline-none focus-visible:!ring-2 focus-visible:!ring-primary/50 !border-b-0 !border-l-2 !border-l-transparent transition-colors';
                        $nav_link_active      = 'mw-nav-link-active !font-semibold';
                        $nav_icon_classes     = 'sb-nav-link-icon !mr-0 inline-flex items-center justify-center w-9 h-9 rounded-md bg-slate-100 group-hover:bg-primary/15 transition-colors';
                        $nav_label_classes    = 'flex-1 truncate';
                        $nav_arrow_classes    = 'sb-sidenav-collapse-arrow !hidden';
                        $nav_disabled_classes = '!opacity-50 !cursor-not-allowed hover:!bg-transparent';

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
                            <?php
                            // Website-builder sidebar from user/menu_config.json → website_menu_items
                            $ws_items = [];
                            foreach (load_website_menu_config() as $ws_cfg) {
                                $path = trim((string) ($ws_cfg['path'] ?? ''), '/');
                                $page_file = $path !== '' ? basename($path) : '';
                                $is_dashboard = ($path === 'dashboard');
                                $ws_items[] = [
                                    'label'            => $ws_cfg['label'] ?? '',
                                    'icon'             => $ws_cfg['icon'] ?? 'circle-o',
                                    'url'              => $nav_base . '/' . $path . ($is_dashboard ? '' : $card_query),
                                    'active'           => $is_dashboard ? ($current_dir === 'dashboard') : ($current_page_ws === $page_file),
                                    'target'           => null,
                                    'separator_before' => !empty($ws_cfg['separator_before']),
                                ];
                            }
                            if ($collaboration_enabled) {
                                $ws_items[] = ['label' => 'Collaboration Details', 'icon' => 'exchange', 'url' => $nav_base . '/collaboration/',  'active' => ($current_dir == 'collaboration' || $current_dir == 'collaboration-details'), 'target' => null];
                            }
                            if ($saleskit_enabled) {
                                $ws_items[] = ['label' => 'Sales Kit',     'icon' => 'folder-open', 'url' => $nav_base . '/kit/', 'active' => ($current_dir == 'kit'), 'target' => null];
                            }
                            if ($collaboration_enabled) {
                                $ws_items[] = ['label' => 'Marketing Kit', 'icon' => 'bullhorn',  'url' => $nav_base . '/kit/', 'active' => ($current_dir == 'kit'), 'target' => null];
                            }
                            $ws_items[] = ['label' => 'Preview', 'icon' => 'external-link', 'url' => $assets_base . '/n.php?n=' . htmlspecialchars($business_name_slug), 'active' => !empty($business_name_slug), 'target' => '_blank', 'separator_before' => true];

                            foreach ($ws_items as $item):
                                if (!empty($item['separator_before'])):
                            ?>
                                <hr class="!my-2 !border-border !mx-3 !border-t" />
                            <?php
                                endif;
                                $is_active   = !empty($item['active']);
                                $link_class  = $nav_link_classes . ($is_active ? ' ' . $nav_link_active : '');
                                $target_attr = !empty($item['target']) ? ' target="' . htmlspecialchars($item['target'], ENT_QUOTES, 'UTF-8') . '" rel="noopener noreferrer"' : '';
                            ?>
                            <a class="nav-link collapsed <?php echo $is_active ? 'active' : ''; ?> <?php echo $link_class; ?>" href="<?php echo $item['url']; ?>"<?php echo $target_attr; ?><?php echo $is_active ? ' aria-current="page"' : ''; ?>>
                                <div class="<?php echo $nav_icon_classes; ?>">
                                    <?php echo render_nav_icon($item['icon'], $assets_base); ?>
                                </div>
                                <span class="<?php echo $nav_label_classes; ?>"><?php echo $item['label']; ?></span>
                                <div class="<?php echo $nav_arrow_classes; ?>"><i class="fa fa-angle-down" aria-hidden="true"></i></div>
                            </a>
                            <?php endforeach; ?>
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
                                
                                $icon_path = $menu_item['icon'];
                                $menu_link = $nav_base . $menu_item['url'];
                                $menu_item_id = $menu_item['id'] ?? '';
                                $franchise_agreement_paid_nav = !empty($user_conditions['franchise_agreement_paid']);
                                $franchise_verified_nav = !empty($user_conditions['is_verified']);

                                // FRANCHISEE: keep same nav-link layout as enabled items; disable rows in place (Dashboard → Verification → Wallet → Marketing Kit)
                                if ($current_role === 'FRANCHISEE' && $menu_item_id === 'verification' && !$franchise_agreement_paid_nav) {
                            ?>
                                <div class="nav-link collapsed <?php echo $nav_link_classes . ' ' . $nav_disabled_classes; ?>" title="Complete franchise registration payment to unlock Verification." aria-disabled="true">
                                    <div class="<?php echo $nav_icon_classes; ?>"><?php echo render_nav_icon($icon_path, $assets_base); ?></div>
                                    <span class="<?php echo $nav_label_classes; ?>"><?php echo htmlspecialchars($menu_item['label']); ?></span>
                                    <div class="<?php echo $nav_arrow_classes; ?>"><i class="fa fa-angle-down" aria-hidden="true"></i></div>
                                </div>
                            <?php
                                    continue;
                                }
                                if ($current_role === 'FRANCHISEE' && $menu_item_id === 'wallet' && (!$franchise_agreement_paid_nav || !$franchise_verified_nav)) {
                                    $wallet_title = !$franchise_agreement_paid_nav
                                        ? 'Complete franchise registration payment to unlock Wallet.'
                                        : 'Document verification required to access Wallet.';
                            ?>
                                <div class="nav-link collapsed <?php echo $nav_link_classes . ' ' . $nav_disabled_classes; ?>" title="<?php echo htmlspecialchars($wallet_title, ENT_QUOTES, 'UTF-8'); ?>" aria-disabled="true">
                                    <div class="<?php echo $nav_icon_classes; ?>"><?php echo render_nav_icon($icon_path, $assets_base); ?></div>
                                    <span class="<?php echo $nav_label_classes; ?>"><?php echo htmlspecialchars($menu_item['label']); ?></span>
                                    <div class="<?php echo $nav_arrow_classes; ?>"><i class="fa fa-angle-down" aria-hidden="true"></i></div>
                                </div>
                            <?php
                                    continue;
                                }
                                if ($current_role === 'FRANCHISEE' && $menu_item_id === 'franchisee_kit' && !$franchise_verified_nav) {
                            ?>
                                <div class="nav-link collapsed <?php echo $nav_link_classes . ' ' . $nav_disabled_classes; ?>" title="Document verification required" aria-disabled="true">
                                    <div class="<?php echo $nav_icon_classes; ?>"><?php echo render_nav_icon($icon_path, $assets_base); ?></div>
                                    <span class="<?php echo $nav_label_classes; ?>"><?php echo htmlspecialchars($menu_item['label']); ?></span>
                                    <div class="<?php echo $nav_arrow_classes; ?>"><i class="fa fa-angle-down" aria-hidden="true"></i></div>
                                </div>
                            <?php
                                    continue;
                                }
                                $std_link_class = $nav_link_classes . ($is_active ? ' ' . $nav_link_active : '');
                            ?>
                                <a class="nav-link collapsed <?php echo $is_active ? 'active' : ''; ?> <?php echo $std_link_class; ?>" href="<?php echo htmlspecialchars($menu_link, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
                                    <div class="<?php echo $nav_icon_classes; ?>"><?php echo render_nav_icon($icon_path, $assets_base); ?></div>
                                    <span class="<?php echo $nav_label_classes; ?>"><?php echo htmlspecialchars($menu_item['label']); ?></span>
                                    <div class="<?php echo $nav_arrow_classes; ?>"><i class="fa fa-angle-down" aria-hidden="true"></i></div>
                                </a>
                            <?php endforeach; ?>

                            <?php
                            // Franchisee-only Download Invoice button (only after franchise_agreement payment verification)
                            if ($current_role == 'FRANCHISEE'):
                                $franchise_invoice_available = false;
                                $franchise_email_for_invoice = $_SESSION['f_user_email'] ?? ($_SESSION['franchisee_email'] ?? ($_SESSION['user_email'] ?? ''));
                                if (!empty($franchise_email_for_invoice)) {
                                    $safe_invoice_email = mysqli_real_escape_string($connect, $franchise_email_for_invoice);
                                    $franchise_invoice_check = mysqli_query(
                                        $connect,
                                        "SELECT id FROM invoice_details
                                         WHERE (user_email='$safe_invoice_email' OR billing_email='$safe_invoice_email')
                                           AND (service_name='Franchisee Registration Fees' OR payment_type='Franchisee' OR reference_number LIKE 'FRAN%')
                                         ORDER BY id DESC
                                         LIMIT 1"
                                    );
                                    $franchise_invoice_available = ($franchise_invoice_check && mysqli_num_rows($franchise_invoice_check) > 0);
                                }
                            ?>
                                <div class="!mt-5 !px-3 !pb-2">
                                    <?php if ($franchise_invoice_available): ?>
                                        <a href="<?php echo $nav_base; ?>/dashboard/download_invoice.php"
                                           class="!inline-flex !items-center !justify-center !gap-2 !w-full !px-4 !py-2.5 !bg-accent hover:!bg-accent/90 !text-secondary !font-semibold !text-sm !rounded-lg !shadow-soft hover:!shadow-card !no-underline focus:outline-none focus-visible:!ring-2 focus-visible:!ring-primary/60 transition"
                                           target="_blank"
                                           rel="noopener noreferrer">
                                            <i class="fa fa-download" aria-hidden="true"></i>
                                            <span>Download Invoice</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="!inline-flex !items-center !justify-center !gap-2 !w-full !px-4 !py-2.5 !bg-slate-200 !text-slate-500 !font-semibold !text-sm !rounded-lg !cursor-not-allowed !opacity-70"
                                              title="Invoice will be available only after franchise agreement payment verification."
                                              aria-disabled="true">
                                            <i class="fa fa-download" aria-hidden="true"></i>
                                            <span>Download Invoice</span>
                                        </span>
                                    <?php endif; ?>
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

<?php require_once(__DIR__ . '/../../common/mw_button.php'); ?>
<?php require_once(__DIR__ . '/../../common/image_upload_crop_modal.php'); ?>








