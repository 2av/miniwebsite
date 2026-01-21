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

// Define theme mapping - mapping theme images to actual CSS files
// All 66 themes from panel/login/select_theme.php
$themes = [
    // Themes 1-32 (template images)
    '../../assets/images/templates/template.png'  => '../../assets/css/templates/card_css1.css',
    '../../assets/images/templates/template1.png' => '../../assets/css/templates/card_css2.css',
    '../../assets/images/templates/template2.png' => '../../assets/css/templates/card_css3.css',
    '../../assets/images/templates/template3.png' => '../../assets/css/templates/card_css4.css',
    '../../assets/images/templates/template4.png' => '../../assets/css/templates/card_css5.css',
    '../../assets/images/templates/template5.png' => '../../assets/css/templates/card_css6.css',
    '../../assets/images/templates/template7.png' => '../../assets/css/templates/card_css7.css',
    '../../assets/images/templates/template8.png' => '../../assets/css/templates/card_css8.css',
    '../../assets/images/templates/template9.png' => '../../assets/css/templates/card_css9.css',
    '../../assets/images/templates/template10.png' => '../../assets/css/templates/card_css10.css',
    '../../assets/images/templates/template11.png' => '../../assets/css/templates/card_css11.css',
    '../../assets/images/templates/template12.png' => '../../assets/css/templates/card_css12.css',
    '../../assets/images/templates/template13.png' => '../../assets/css/templates/card_css13.css',
    '../../assets/images/templates/template14.png' => '../../assets/css/templates/card_css14.css',
    '../../assets/images/templates/template15.png' => '../../assets/css/templates/card_css15.css',
    '../../assets/images/templates/template16.png' => '../../assets/css/templates/card_css16.css',
    '../../assets/images/templates/template17.png' => '../../assets/css/templates/card_css17.css',
    '../../assets/images/templates/template18.png' => '../../assets/css/templates/card_css18.css',
    '../../assets/images/templates/template19.png' => '../../assets/css/templates/card_css19.css',
    '../../assets/images/templates/template20.png' => '../../assets/css/templates/card_css20.css',
    '../../assets/images/templates/template21.png' => '../../assets/css/templates/card_css21.css',
    '../../assets/images/templates/template22.png' => '../../assets/css/templates/card_css22.css',
    '../../assets/images/templates/template23.png' => '../../assets/css/templates/card_css23.css',
    '../../assets/images/templates/template24.png' => '../../assets/css/templates/card_css24.css',
    '../../assets/images/templates/template25.png' => '../../assets/css/templates/card_css25.css',
    '../../assets/images/templates/template26.png' => '../../assets/css/templates/card_css26.css',
    '../../assets/images/templates/template27.png' => '../../assets/css/templates/card_css27.css',
    '../../assets/images/templates/template28.png' => '../../assets/css/templates/card_css28.css',
    '../../assets/images/templates/template29.png' => '../../assets/css/templates/card_css29.css',
    '../../assets/images/templates/template30.png' => '../../assets/css/templates/card_css30.css',
    '../../assets/images/templates/template31.png' => '../../assets/css/templates/card_css31.css',
    '../../assets/images/templates/template32.png' => '../../assets/css/templates/card_css32.css',
    // Themes 33-67 (background images)
    '../../assets/images/templates/bg33.jpg' => '../../assets/css/templates/card_css33.css',
    '../../assets/images/templates/jay.gif'  => '../../assets/css/templates/card_css34.css',
    '../../assets/images/templates/bg34.jpg' => '../../assets/css/templates/card_css35.css',
    '../../assets/images/templates/bg36.jpg' => '../../assets/css/templates/card_css36.css',
    '../../assets/images/templates/bg37.png' => '../../assets/css/templates/card_css37.css',
    '../../assets/images/templates/bg38.png' => '../../assets/css/templates/card_css38.css',
    '../../assets/images/templates/bg39.png' => '../../assets/css/templates/card_css39.css',
    '../../assets/images/templates/bg40.png' => '../../assets/css/templates/card_css40.css',
    '../../assets/images/templates/bg41.png' => '../../assets/css/templates/card_css41.css',
    '../../assets/images/templates/bg42.jpg' => '../../assets/css/templates/card_css42.css',
    '../../assets/images/templates/card43.jpg' => '../../assets/css/templates/card_css43.css',
    '../../assets/images/templates/bg44.png' => '../../assets/css/templates/card_css44.css',
    '../../assets/images/templates/bg45.jpg' => '../../assets/css/templates/card_css45.css',
    '../../assets/images/templates/bg46.jpg' => '../../assets/css/templates/card_css46.css',
    '../../assets/images/templates/bg47.jpg' => '../../assets/css/templates/card_css47.css',
    '../../assets/images/templates/bg48.jpg' => '../../assets/css/templates/card_css48.css',
    '../../assets/images/templates/bg49.jpg' => '../../assets/css/templates/card_css49.css',
    '../../assets/images/templates/bg50.jpg' => '../../assets/css/templates/card_css50.css',
    '../../assets/images/templates/bg51.jpg' => '../../assets/css/templates/card_css51.css',
    '../../assets/images/templates/bg52.jpg' => '../../assets/css/templates/card_css52.css',
    '../../assets/images/templates/bg53.jpg' => '../../assets/css/templates/card_css53.css',
    '../../assets/images/templates/bg56.gif' => '../../assets/css/templates/card_css56.css',
    '../../assets/images/templates/bg57.png' => '../../assets/css/templates/card_css57.css',
    '../../assets/images/templates/bg58.jpg' => '../../assets/css/templates/card_css58.css',
    '../../assets/images/templates/bg59.jpg' => '../../assets/css/templates/card_css59.css',
    '../../assets/images/templates/bg60.jpg' => '../../assets/css/templates/card_css60.css',
    '../../assets/images/templates/bg61.jpg' => '../../assets/css/templates/card_css61.css',
    '../../assets/images/templates/bg62.jpg' => '../../assets/css/templates/card_css62.css',
    '../../assets/images/templates/bg63.jpg' => '../../assets/css/templates/card_css63.css',
    '../../assets/images/templates/bg64.jpg' => '../../assets/css/templates/card_css64.css',
    '../../assets/images/templates/bg65.jpg' => '../../assets/css/templates/card_css65.css',
    '../../assets/images/templates/bg66.jpg' => '../../assets/css/templates/card_css66.css',
    '../../assets/images/templates/bg67.jpg' => '../../assets/css/templates/card_css67.css',
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
                            <img src="../../../assets/images/Save.png" class="img-fluid" width="35px" alt=""> 
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

<?php include '../includes/footer.php'; ?>




