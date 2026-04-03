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

// Single theme (preview thumbnail). Live card styling is theme/css/* in n.php; d_css kept for DB/admin.
$themes = [
    '../../assets/images/templates/template1.png' => 'panel/card_css2.css',
];
$theme_css_value = (string) reset($themes);
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
                        <?php foreach($themes as $theme_image => $css_file): ?>
                            <div class="col theme-item selected" data-theme="<?php echo htmlspecialchars($css_file); ?>">
                                <a href="javascript:void(0);" class="theme-select-link">
                                    <img class="img-fluid theme_img" src="<?php echo $theme_image; ?>" alt="Theme">
                                    <div class="selected-overlay">Selected</div>
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




