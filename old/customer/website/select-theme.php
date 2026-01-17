<?php
// Handle card_number parameter from URL, session, or cookie
// MUST be done before any output (before including header.php)
require_once('../../common/config.php');
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

include 'header.php';
?>

<?php

$query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$card_id.'" AND user_email="'.$user_email.'"');

if(mysqli_num_rows($query) == 0){
    echo '<script>alert("Card id does not match with your email account"); window.location.href="business-name.php";</script>';
    exit;
} else {
    $row = mysqli_fetch_array($query);
}

// Define theme mapping - mapping theme images to actual CSS files
// All 66 themes from panel/login/select_theme.php
$themes = [
    // Themes 1-12 (template images)
    '../../panel/images/template.png' => 'card_css1.css',
    '../../panel/images/template1.png' => 'card_css2.css',
    '../../panel/images/template2.png' => 'card_css3.css',
    '../../panel/images/template3.png' => 'card_css4.css',
    '../../panel/images/template4.png' => 'card_css5.css',
    '../../panel/images/template5.png' => 'card_css6.css',
    '../../panel/images/template7.png' => 'card_css7.css',
    '../../panel/images/template8.png' => 'card_css8.css',
    '../../panel/images/template9.png' => 'card_css9.css',
    '../../panel/images/template10.png' => 'card_css10.css',
    '../../panel/images/template11.png' => 'card_css11.css',
    '../../panel/images/template12.png' => 'card_css12.css',
    '../../panel/images/template13.png' => 'card_css13.css',
    '../../panel/images/template14.png' => 'card_css14.css',
    '../../panel/images/template15.png' => 'card_css15.css',
    '../../panel/images/template16.png' => 'card_css16.css',
    '../../panel/images/template17.png' => 'card_css17.css',
    '../../panel/images/template18.png' => 'card_css18.css',
    '../../panel/images/template19.png' => 'card_css19.css',
    '../../panel/images/template20.png' => 'card_css20.css',
    '../../panel/images/template21.png' => 'card_css21.css',
    '../../panel/images/template22.png' => 'card_css22.css',
    '../../panel/images/template23.png' => 'card_css23.css',
    '../../panel/images/template24.png' => 'card_css24.css',
    '../../panel/images/template25.png' => 'card_css25.css',
    '../../panel/images/template26.png' => 'card_css26.css',
    '../../panel/images/template27.png' => 'card_css27.css',
    '../../panel/images/template28.png' => 'card_css28.css',
    '../../panel/images/template29.png' => 'card_css29.css',
    '../../panel/images/template30.png' => 'card_css30.css',
    '../../panel/images/template31.png' => 'card_css31.css',
    '../../panel/images/template32.png' => 'card_css32.css',
    // Themes 33-67 (bg images and other formats)
    '../../panel/images/bg33.jpg' => 'card_css33.css',
    '../../panel/images/jay.gif' => 'card_css34.css',
    '../../panel/images/bg34.jpg' => 'card_css35.css',
    '../../panel/images/bg36.jpg' => 'card_css36.css',
    '../../panel/images/bg37.png' => 'card_css37.css',
    '../../panel/images/bg38.png' => 'card_css38.css',
    '../../panel/images/bg39.png' => 'card_css39.css',
    '../../panel/images/bg40.png' => 'card_css40.css',
    '../../panel/images/bg41.png' => 'card_css41.css',
    '../../panel/images/bg42.jpg' => 'card_css42.css',
    '../../panel/images/card43.jpg' => 'card_css43.css',
    '../../panel/images/bg44.png' => 'card_css44.css',
    '../../panel/images/bg45.jpg' => 'card_css45.css',
    '../../panel/images/bg46.jpg' => 'card_css46.css',
    '../../panel/images/bg47.jpg' => 'card_css47.css',
    '../../panel/images/bg48.jpg' => 'card_css48.css',
    '../../panel/images/bg49.jpg' => 'card_css49.css',
    '../../panel/images/bg50.jpg' => 'card_css50.css',
    '../../panel/images/bg51.jpg' => 'card_css51.css',
    '../../panel/images/bg52.jpg' => 'card_css52.css',
    '../../panel/images/bg53.jpg' => 'card_css53.css',
    '../../panel/images/bg56.gif' => 'card_css56.css',
    '../../panel/images/bg57.png' => 'card_css57.css',
    '../../panel/images/bg58.jpg' => 'card_css58.css',
    '../../panel/images/bg59.jpg' => 'card_css59.css',
    '../../panel/images/bg60.jpg' => 'card_css60.css',
    '../../panel/images/bg61.jpg' => 'card_css61.css',
    '../../panel/images/bg62.jpg' => 'card_css62.css',
    '../../panel/images/bg63.jpg' => 'card_css63.css',
    '../../panel/images/bg64.jpg' => 'card_css64.css',
    '../../panel/images/bg65.jpg' => 'card_css65.css',
    '../../panel/images/bg66.jpg' => 'card_css66.css',
    '../../panel/images/bg67.jpg' => 'card_css67.css'
];
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
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                <?php if(isset($_SESSION['save_error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['save_error']; unset($_SESSION['save_error']); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                <form id="themeForm" method="POST" action="">
                    <input type="hidden" name="d_css" id="selectedTheme" value="<?php echo htmlspecialchars($row['d_css']); ?>">
                    <input type="hidden" name="save_theme" value="1">
                    <div class="d-flex flex-wrap w-100 theme_section row-items-4">
                        <?php foreach($themes as $theme_image => $css_file): ?>
                            <div class="col theme-item <?php echo ($row['d_css'] == $css_file) ? 'selected' : ''; ?>" data-theme="<?php echo htmlspecialchars($css_file); ?>">
                                <a href="javascript:void(0);" class="theme-select-link">
                                    <img class="img-fluid theme_img" src="<?php echo $theme_image; ?>" alt="Theme">
                                    <?php if($row['d_css'] == $css_file): ?>
                                        <div class="selected-overlay">Selected</div>
                                    <?php endif; ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="Product-ServicesBtn" style="margin-top: 20px;">
                        <a href="business-name.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left">
                            <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                            <span>Back</span>
                        </a>
                        <button type="submit" class="btn btn-primary align-center save_btn">
                            <img src="../assets/img/Save.png" class="img-fluid" width="35px" alt=""> 
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
    width: 75% !important;
    padding:0px !important;
            margin-top: 40px !important;
            margin:auto;
}
.save_btn{
        position: absolute;
    bottom: 150px;
    width: 115px !important;
    left: 100px;
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
    .Product-ServicesBtn .btn-primary {
        padding: 7px !important;
        margin-top: 22px !important;
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

<?php include '../footer.php'; ?>