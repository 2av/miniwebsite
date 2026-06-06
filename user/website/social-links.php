<?php
// Handle card_number from URL, session, or cookie
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
    header('Location: business-name.php');
    exit;
}

$query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');

if(mysqli_num_rows($query) == 0){
    echo '<script>alert("Card id does not match with your email account"); window.location.href="business-name.php";</script>';
    exit;
} else {
    // Use a dedicated variable to avoid collisions with included files (e.g. header.php)
    $cardRow = mysqli_fetch_array($query);
}

// Handle form submission
if(isset($_POST['process3'])){
    $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'"');
    if(mysqli_num_rows($query) == 1){
        
        // Update social links in database
        $update = mysqli_query($connect, 'UPDATE digi_card SET 
        d_fb="'.mysqli_real_escape_string($connect, $_POST['d_fb']).'",
        d_twitter="'.mysqli_real_escape_string($connect, $_POST['d_twitter']).'",
        d_instagram="'.mysqli_real_escape_string($connect, $_POST['d_instagram']).'",
        d_linkedin="'.mysqli_real_escape_string($connect, $_POST['d_linkedin']).'",
        d_youtube="'.mysqli_real_escape_string($connect, $_POST['d_youtube']).'",
        d_pinterest="'.mysqli_real_escape_string($connect, $_POST['d_pinterest']).'"
        WHERE id="'.$_SESSION['card_id_inprocess'].'"');
        
        if($update){
            $_SESSION['save_success'] = "Social Links Updated Successfully!";
            // Re-fetch updated record so fields show latest saved values
            $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');
            if($query && mysqli_num_rows($query) > 0){
                $cardRow = mysqli_fetch_array($query);
            }
            // Redirect if possible (prevents form resubmission on refresh)
            if (!headers_sent()) {
                header('Location: social-links.php?card_number='.$_SESSION['card_id_inprocess']);
                exit;
            }
        } else {
            $_SESSION['save_error'] = "Error! Try Again.";
            if (!headers_sent()) {
                header('Location: social-links.php?card_number='.$_SESSION['card_id_inprocess']);
                exit;
            }
        }
    } else {
        $_SESSION['save_error'] = "Detail Not Available. Try Again.";
        header('Location: social-links.php?card_number='.$_SESSION['card_id_inprocess']);
        exit;
    }
}

include '../includes/header.php';
?>

<!-- Phase B · Step 6 — social-links.php uses the central .mw-* design system (header.php).
     JS hooks preserved: form#card_form, name="d_fb|d_twitter|d_instagram|d_linkedin|d_youtube|d_pinterest",
     name="process3", .btn.btn-primary.align-center, .save_btn. -->
<main class="Dashboard mw-page">
    <div class="container-fluid customer_content_area mw-container">
        <div class="main-top mw-page-header">
            <h1 class="heading mw-page-title">Social Media Links</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mw-breadcrumb">
                    <li class="breadcrumb-item mw-breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item mw-breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
                </ol>
            </nav>
        </div>

        <?php if(isset($_SESSION['save_success'])): ?>
            <div class="alert alert-dismissible fade show mw-alert mw-alert-success" role="alert">
                <i class="fa fa-check-circle mw-alert-icon" aria-hidden="true"></i>
                <div class="mw-alert-body"><?php echo $_SESSION['save_success']; unset($_SESSION['save_success']); ?></div>
                <button type="button" class="close mw-alert-close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['save_error'])): ?>
            <div class="alert alert-dismissible fade show mw-alert mw-alert-danger" role="alert">
                <i class="fa fa-exclamation-circle mw-alert-icon" aria-hidden="true"></i>
                <div class="mw-alert-body"><?php echo $_SESSION['save_error']; unset($_SESSION['save_error']); ?></div>
                <button type="button" class="close mw-alert-close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>
        <?php if(isset($error_message)): ?>
            <div class="alert mw-alert mw-alert-danger" role="alert">
                <i class="fa fa-exclamation-circle mw-alert-icon" aria-hidden="true"></i>
                <div class="mw-alert-body"><?php echo $error_message; ?></div>
            </div>
        <?php endif; ?>

        <div class="card mb-4 mw-card">
            <div class="card-body mw-card-body">
                <div class="mb-6">
                    <h2 class="heading mw-section-title">Social Media Links</h2>
                    <p class="mw-helper-text">
                        <i class="fa fa-info-circle" aria-hidden="true"></i>
                        <span>Add the URLs of your business pages &mdash; they'll appear on your public Mini Website.</span>
                    </p>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" id="card_form" class="mw-form">
                    <?php
                    // DRY config of all 6 social inputs
                    $social_fields = [
                        ['name' => 'd_fb',        'label' => 'Facebook',    'icon' => 'facebook.png',  'placeholder' => 'Enter Your Facebook Business Page/Profile Link'],
                        ['name' => 'd_instagram', 'label' => 'Instagram',   'icon' => 'instagram.png', 'placeholder' => 'Enter Your Instagram Link'],
                        ['name' => 'd_youtube',   'label' => 'YouTube',     'icon' => 'youtube.png',   'placeholder' => 'Enter Your YouTube Channel Link'],
                        ['name' => 'd_twitter',   'label' => 'X (Twitter)', 'icon' => 'twitter.png',   'placeholder' => 'Enter Your X Link'],
                        ['name' => 'd_linkedin',  'label' => 'LinkedIn',    'icon' => 'linkedin.png',  'placeholder' => 'Enter Your LinkedIn Profile Link'],
                        ['name' => 'd_pinterest', 'label' => 'Pinterest',   'icon' => 'pinterest.png', 'placeholder' => 'Enter Your Pinterest Link'],
                    ];
                    ?>
                    <div class="mw-form-grid-2">
                        <?php foreach ($social_fields as $f): ?>
                            <?php $f_value = !empty($cardRow[$f['name']]) ? htmlspecialchars($cardRow[$f['name']]) : ''; ?>
                            <div class="form-group mw-form-group">
                                <label for="<?php echo $f['name']; ?>" class="mw-label">
                                    <img src="../../assets/images/<?php echo $f['icon']; ?>" style="width:var(--mw-icon);height:var(--mw-icon);object-fit:contain;" alt="<?php echo $f['label']; ?> icon">
                                    <span><?php echo $f['label']; ?></span>
                                </label>
                                <div class="mw-input-icon-wrap">
                                    <span class="mw-input-icon"><i class="fa fa-link" aria-hidden="true"></i></span>
                                    <input type="text"
                                           name="<?php echo $f['name']; ?>"
                                           id="<?php echo $f['name']; ?>"
                                           maxlength="200"
                                           class="form-control mw-input"
                                           placeholder="<?php echo $f['placeholder']; ?>"
                                           value="<?php echo $f_value; ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="Product-ServicesBtn mw-btn-row">
                        <a href="payment-details.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left mw-btn mw-btn-back">
                            <span class="left_angle angle mw-btn-angle"><i class="fa fa-angle-left" aria-hidden="true"></i></span>
                            <span>Back</span>
                        </a>
                        <button type="submit" name="process3" class="btn btn-primary align-center save_btn mw-btn mw-btn-save">
                            <img src="../../assets/images/Save.png" alt="" style="width:1.25rem;height:1.25rem;flex-shrink:0;">
                            <span>Save</span>
                        </button>
                        <a href="../dashboard/" class="btn btn-secondary align-right mw-btn mw-btn-next">
                            <span>Finish</span>
                            <span class="right_angle angle mw-btn-angle"><i class="fa fa-angle-right" aria-hidden="true"></i></span>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>





