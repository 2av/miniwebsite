<?php
// Handle card_number parameter from URL, session, or cookie
// MUST be done before any output (before including header.php)
require_once(__DIR__ . '/../../app/config/database.php');
if(isset($_GET['card_number']) && !empty($_GET['card_number'])){
    $card_number = mysqli_real_escape_string($connect, $_GET['card_number']);
    $_SESSION['card_id_inprocess'] = $card_number;
    // Store in cookie for 24 hours
    setcookie('card_id_inprocess', $card_number, time() + (86400 * 1), '/');
} elseif(isset($_COOKIE['card_id_inprocess']) && !empty($_COOKIE['card_id_inprocess'])) {
    // If card_number not in URL but exists in cookie, restore to session
    $_SESSION['card_id_inprocess'] = $_COOKIE['card_id_inprocess'];
}

// Get current card data
if(!isset($_SESSION['card_id_inprocess']) || empty($_SESSION['card_id_inprocess'])) {
    // Can't use echo here as headers not sent yet, so redirect directly
    header('Location: business-name.php');
    exit;
}

$card_id = mysqli_real_escape_string($connect, $_SESSION['card_id_inprocess']);
$user_email = mysqli_real_escape_string($connect, $_SESSION['user_email']);

// Handle theme selection via POST (Save button - no redirect) - MUST be before header.php
if(isset($_POST['d_css']) && isset($_POST['save_theme'])){
    $d_css = mysqli_real_escape_string($connect, $_POST['d_css']);
    
    $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$card_id.'"');
    if(mysqli_num_rows($query) == 1){
        // Update theme in database
        $update = mysqli_query($connect, 'UPDATE digi_card SET d_css="'.$d_css.'" WHERE id="'.$card_id.'"');
        
        if($update){
            $_SESSION['save_success'] = "Theme Saved Successfully";
            header('Location: select-theme.php?card_number='.$card_id);
            exit;
        } else {
            $_SESSION['save_error'] = "Error! Try Again.";
            header('Location: select-theme.php?card_number='.$card_id);
            exit;
        }
    }
}

include '../includes/header.php';
?>

<?php

$query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$card_id.'" AND user_email="'.$user_email.'"');

if(mysqli_num_rows($query) == 0){
    echo '<script>alert("Card id does not match with your email account"); window.location.href="business-name.php";</script>';
    exit;
} else {
    $row = mysqli_fetch_array($query);
}

// Build theme options dynamically from theme/css/themeN.css + layoutN.css pairs.
$theme_css_dir = __DIR__ . '/../../theme/css';
$theme_numbers = [];
$theme_files = glob($theme_css_dir . '/theme*.css');
if (is_array($theme_files)) {
    foreach ($theme_files as $theme_file) {
        if (preg_match('/theme(\d+)\.css$/', $theme_file, $m)) {
            $theme_no = intval($m[1]);
            if ($theme_no > 0 && file_exists($theme_css_dir . '/layout' . $theme_no . '.css')) {
                $theme_numbers[] = $theme_no;
            }
        }
    }
}
$theme_numbers = array_values(array_unique($theme_numbers));
sort($theme_numbers, SORT_NUMERIC);
if (empty($theme_numbers)) {
    $theme_numbers = [1];
}

$themes = [];
foreach ($theme_numbers as $theme_no) {
    $template_image_web = '../../assets/images/templates/template' . $theme_no . '.png';
    $template_image_abs = __DIR__ . '/../../assets/images/templates/template' . $theme_no . '.png';
    if (!file_exists($template_image_abs)) {
        $template_image_web = '../../assets/images/templates/template1.png';
    }
    $themes[] = [
        'number' => $theme_no,
        'image' => $template_image_web,
        // Keep DB/admin compatibility: Theme N maps to card_css(N+1)
        'css' => 'panel/card_css' . ($theme_no + 1) . '.css',
        'name' => 'Theme ' . $theme_no,
    ];
}

$saved_theme_css = isset($row['d_css']) ? trim((string) $row['d_css']) : '';
$selected_theme_number = (int) $themes[0]['number'];
if (preg_match('/card_css(\d+)\.css$/', $saved_theme_css, $m)) {
    $card_css_no = intval($m[1]);
    if ($card_css_no > 1) {
        $selected_theme_number = $card_css_no - 1;
    }
}

$available_theme_numbers = array_column($themes, 'number');
if (!in_array($selected_theme_number, $available_theme_numbers, true)) {
    $selected_theme_number = (int) $themes[0]['number'];
}

$theme_css_value = 'panel/card_css' . ($selected_theme_number + 1) . '.css';
?>

<!-- Phase B · Step 8 — select-theme.php uses the central .mw-* design system (header.php).
     JS hooks preserved: .theme-item, .selected, .selected-overlay, data-theme,
     #selectedTheme, .theme-select-link, .theme_img, form#themeForm, name="d_css", name="save_theme". -->
<main class="Dashboard mw-page">
    <div class="container-fluid customer_content_area mw-container">
        <div class="main-top mw-page-header">
            <h1 class="heading mw-page-title">Theme</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mw-breadcrumb">
                    <li class="breadcrumb-item mw-breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item mw-breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
                </ol>
            </nav>
        </div>

        <div class="card mb-4 mw-card">
            <div class="card-body SelectTheme mw-card-body">
                <div class="mb-6">
                    <h2 class="mw-section-title">Select Your Mini Website Template<span class="req">*</span></h2>
                    <p class="mw-helper-text">
                        <i class="fa fa-info-circle" aria-hidden="true"></i>
                        <span>Tap a theme to preview its layout. Click <strong style="color:var(--mw-color-text);font-weight:600">Save</strong> to apply.</span>
                    </p>
                </div>

                <?php if(isset($_SESSION['save_success'])): ?>
                    <div class="alert alert-dismissible fade show mw-alert mw-alert-success" role="alert">
                        <i class="fa fa-check-circle mw-alert-icon" aria-hidden="true"></i>
                        <div class="mw-alert-body"><?php echo $_SESSION['save_success']; unset($_SESSION['save_success']); ?></div>
                        <button type="button" class="btn-close mw-alert-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                <?php endif; ?>
                <?php if(isset($_SESSION['save_error'])): ?>
                    <div class="alert alert-dismissible fade show mw-alert mw-alert-danger" role="alert">
                        <i class="fa fa-exclamation-circle mw-alert-icon" aria-hidden="true"></i>
                        <div class="mw-alert-body"><?php echo $_SESSION['save_error']; unset($_SESSION['save_error']); ?></div>
                        <button type="button" class="btn-close mw-alert-close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                <?php endif; ?>

                <form id="themeForm" method="POST" action="" class="mw-form">
                    <input type="hidden" name="d_css" id="selectedTheme" value="<?php echo htmlspecialchars($theme_css_value); ?>">
                    <input type="hidden" name="save_theme" value="1">

                    <div class="theme_section" role="radiogroup" aria-label="Theme templates">
                        <?php foreach($themes as $theme): ?>
                            <?php $is_selected = ($theme_css_value === $theme['css']); ?>
                            <div class="theme-item <?php echo $is_selected ? 'selected' : ''; ?>" data-theme="<?php echo htmlspecialchars($theme['css']); ?>" role="radio" aria-checked="<?php echo $is_selected ? 'true' : 'false'; ?>" tabindex="0">
                                <a href="javascript:void(0);" class="theme-select-link">
                                    <div class="theme-item-thumb">
                                        <img class="theme_img" src="<?php echo htmlspecialchars($theme['image']); ?>" alt="<?php echo htmlspecialchars($theme['name']); ?> preview" loading="lazy">
                                    </div>
                                    <div class="theme-item-name"><?php echo htmlspecialchars($theme['name']); ?></div>
                                    <?php if($is_selected): ?>
                                        <div class="selected-overlay mw-pill mw-pill-primary">
                                            <i class="fa fa-check" aria-hidden="true"></i>
                                            <span class="selected-overlay-text">Selected</span>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="Product-ServicesBtn mw-btn-row">
                        <a href="business-name.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left mw-btn mw-btn-back">
                            <span class="left_angle angle mw-btn-angle"><i class="fa fa-angle-left" aria-hidden="true"></i></span>
                            <span>Back</span>
                        </a>
                        <button type="submit" class="btn btn-primary align-center save_btn mw-btn mw-btn-save">
                            <img src="../../assets/images/Save.png" alt="" class="img-fluid" style="width:1.25rem;height:1.25rem;flex-shrink:0;">
                            <span>Save</span>
                        </button>
                        <a href="company-details.php?card_number=<?php echo $_SESSION['card_id_inprocess']; ?>" class="btn btn-secondary align-right mw-btn mw-btn-next">
                            <span>Next</span>
                            <span class="right_angle angle mw-btn-angle"><i class="fa fa-angle-right" aria-hidden="true"></i></span>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<style>
    /* Theme picker grid — cards stretch to fill cells; preview fills thumb with inner padding */
    .SelectTheme .theme_section {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
        width: 100%;
        align-items: stretch;
    }
    @media (min-width: 576px) {
        .SelectTheme .theme_section { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
    }
    @media (min-width: 992px) {
        .SelectTheme .theme_section { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.25rem; }
    }
    .SelectTheme .theme-item {
        position: relative;
        display: flex;
        flex-direction: column;
        width: 100%;
        min-width: 0;
        height: 100%;
        box-sizing: border-box;
        cursor: pointer;
        background: var(--mw-color-surface);
        border: 2px solid var(--mw-color-border);
        border-radius: var(--mw-radius-chip);
        padding: clamp(0.5rem, 2vw, 0.75rem);
        transition: border-color .15s, box-shadow .15s, transform .1s, background .15s;
    }
    .SelectTheme .theme-item:hover {
        border-color: var(--mw-color-border-strong);
        box-shadow: var(--mw-shadow-soft);
        transform: translateY(-2px);
    }
    .SelectTheme .theme-item:focus-visible { outline: none; box-shadow: var(--mw-ring-focus); }
    .SelectTheme .theme-item.selected {
        border-color: var(--mw-color-primary);
        background: rgb(201 162 39 / 0.06);
        box-shadow: var(--mw-shadow-card), 0 0 0 2px rgb(201 162 39 / 0.30);
    }
    .SelectTheme .theme-item .theme-select-link {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        height: 100%;
        position: relative;
        text-decoration: none;
        color: inherit;
    }
    .SelectTheme .theme-item-thumb {
        flex: 1 1 auto;
        width: 100%;
        min-height: 9.5rem;
        aspect-ratio: 3 / 4;
        box-sizing: border-box;
        padding: clamp(0.375rem, 1.5vw, 0.625rem);
        border-radius: 0.5rem;
        background: #f1f5f9;
        overflow: hidden;
    }
    .SelectTheme .theme-item-thumb img.theme_img {
        display: block;
        width: 100%;
        height: 100%;
        max-width: none;
        object-fit: cover;
        object-position: top center;
        border-radius: 0.375rem;
    }
    .SelectTheme .theme-item-name {
        flex-shrink: 0;
        margin-top: 0.5rem;
        padding: 0 0.125rem;
        text-align: center;
        font-size: var(--mw-font-body);
        font-weight: 600;
        color: var(--mw-color-text-muted);
        line-height: 1.3;
    }
    .SelectTheme .theme-item.selected .theme-item-name { color: var(--mw-color-primary-dark); }
    .SelectTheme .selected-overlay { position: absolute; top: 0.5rem; right: 0.5rem; z-index: 2; }
    @media (max-width: 639px) {
        .SelectTheme .selected-overlay-text { display: none; }
        .SelectTheme .theme-item-thumb { min-height: 8.5rem; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const themeItems = document.querySelectorAll('.theme-item');
    const selectedThemeInput = document.getElementById('selectedTheme');

    // Phase B · Step 8 — uses central .mw-pill .mw-pill-primary classes so styling
    // stays consistent with the server-rendered overlay and the rest of the design system.
    const OVERLAY_CLASSES = 'selected-overlay mw-pill mw-pill-primary';
    const OVERLAY_HTML = '<i class="fa fa-check" aria-hidden="true"></i><span class="selected-overlay-text">Selected</span>';

    themeItems.forEach(function(item) {
        item.addEventListener('click', function() {
            const themeValue = this.getAttribute('data-theme');

            // Remove selected class from all items
            themeItems.forEach(function(themeItem) {
                themeItem.classList.remove('selected');
                themeItem.setAttribute('aria-checked', 'false');
                const overlay = themeItem.querySelector('.selected-overlay');
                if(overlay) {
                    overlay.remove();
                }
            });

            // Add selected class to clicked item
            this.classList.add('selected');
            this.setAttribute('aria-checked', 'true');

            // Add selected overlay (matches server-rendered Tailwind classes for visual parity)
            const overlay = document.createElement('div');
            overlay.className = OVERLAY_CLASSES;
            overlay.innerHTML = OVERLAY_HTML;
            this.querySelector('a').appendChild(overlay);

            // Update hidden input
            selectedThemeInput.value = themeValue;
        });

        // Keyboard accessibility — Space/Enter selects the focused theme card
        item.addEventListener('keydown', function(e) {
            if (e.key === ' ' || e.key === 'Enter') {
                e.preventDefault();
                this.click();
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>




