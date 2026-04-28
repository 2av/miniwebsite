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

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
        <div class="main-top">
        <span class="heading">Theme</span>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
                </ol>
            </nav>
        </div>
        
        <div class="card mb-4">
            <div class="card-body SelectTheme">
                <label>Select Your Mini Website Template*</label>
                <?php if(isset($_SESSION['save_success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['save_success']; unset($_SESSION['save_success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if(isset($_SESSION['save_error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['save_error']; unset($_SESSION['save_error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <form id="themeForm" method="POST" action="">
                    <input type="hidden" name="d_css" id="selectedTheme" value="<?php echo htmlspecialchars($theme_css_value); ?>">
                    <input type="hidden" name="save_theme" value="1">
                    <div class="d-flex flex-wrap w-100 theme_section row-items-4">
                        <?php foreach($themes as $theme): ?>
                            <?php $is_selected = ($theme_css_value === $theme['css']); ?>
                            <div class="col theme-item <?php echo $is_selected ? 'selected' : ''; ?>" data-theme="<?php echo htmlspecialchars($theme['css']); ?>">
                                <a href="javascript:void(0);" class="theme-select-link">
                                    <img class="img-fluid theme_img" src="<?php echo htmlspecialchars($theme['image']); ?>" alt="<?php echo htmlspecialchars($theme['name']); ?>">
                                    <div style="margin-top: 8px; text-align: center; font-weight: 600;"><?php echo htmlspecialchars($theme['name']); ?></div>
                                    <?php if($is_selected): ?>
                                        <div class="selected-overlay">Selected</div>
                                    <?php endif; ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="Product-ServicesBtn" style="margin-top: 20px; width: 86%;">
                        <a href="business-name.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left">
                            <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                            <span>Back</span>
                        </a>
                        <button type="submit" class="btn btn-primary align-center save_btn">
                            <img src="../../assets/images/Save.png" class="img-fluid" width="35px" alt=""> 
                            <span>Save</span>
                        </button>
                        <a href="company-details.php?card_number=<?php echo $_SESSION['card_id_inprocess']; ?>" class="btn btn-secondary align-right">
                            <span>Next</span>
                            <span class="right_angle angle"><i class="fa fa-angle-right"></i></span>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<style>
.theme-item {
    position: relative;
    margin-bottom: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.theme-item:hover {
    transform: scale(1.05);
}

.theme-item a {
    display: block;
    position: relative;
    border: 2px solid transparent;
    border-radius: 8px;
    overflow: hidden;
}
.SelectTheme label{
    font-size:24px !important; 
}

.theme-item.selected a {
    border-color: #007bff;
    box-shadow: 0 0 10px rgba(0, 123, 255, 0.3);
}

.selected-overlay {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #007bff;
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
    z-index: 10;
}

.theme-item img {
    width: 100%;
    height: auto;
    border-radius: 6px;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    margin-top: 20px;
}

@media (max-width: 768px) {
    .theme_section .theme-item{
        display: contents !important;
    }
    .theme_section {
    gap:25px;
    }

    .theme_section .theme-item .theme_img{
        width: 100%; !important;
        max-width: 100% !important;
    }
    .SelectTheme label {
    font-size: 22px !important;
}

.Product-ServicesBtn{
    width: 80% !important;
    padding:0px !important;
            margin-top: 40px !important;
}
.save_btn{
        position: absolute;
    bottom: 150px;
    width: 145px !important;
    left: 96px;
    height: 36px;
}
.Copyright-left,
.Copyright-right{
    padding:0px;
}

}
.Product-ServicesBtn{
    padding: 0px 40px;
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
}
.Product-ServicesBtn button,
.Product-ServicesBtn a{
    display: flex !important;
    color: #fff !important;
    justify-content: center;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}
.Product-ServicesBtn button .angle,
.Product-ServicesBtn a .angle{
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff !important;
    color:#000;
    font-weight:bold;
    display: flex;
    justify-content: center;
    align-items: center;
}
.Product-ServicesBtn button span:not(.angle),
.Product-ServicesBtn a span:not(.angle){
    font-weight:500;
    font-size:16px;
}
.Product-ServicesBtn .align-center{
    padding: 4px 10px;
}
.Product-ServicesBtn .align-center img{
    width: 23px;
}
.Product-ServicesBtn .align-center span{
    color:#000;
}

.Product-ServicesBtn  .btn{
        line-height:24px !important;
    }
    .Product-ServicesBtn button {
        padding: 7px !important;
        margin-top: 22px !important;
    }
    .save_btn{
    width: 115px !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const themeItems = document.querySelectorAll('.theme-item');
    const selectedThemeInput = document.getElementById('selectedTheme');
    
    themeItems.forEach(function(item) {
        item.addEventListener('click', function() {
            const themeValue = this.getAttribute('data-theme');
            
            // Remove selected class from all items
            themeItems.forEach(function(themeItem) {
                themeItem.classList.remove('selected');
                const overlay = themeItem.querySelector('.selected-overlay');
                if(overlay) {
                    overlay.remove();
                }
            });
            
            // Add selected class to clicked item
            this.classList.add('selected');
            
            // Add selected overlay
            const overlay = document.createElement('div');
            overlay.className = 'selected-overlay';
            overlay.textContent = 'Selected';
            this.querySelector('a').appendChild(overlay);
            
            // Update hidden input
            selectedThemeInput.value = themeValue;
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>




