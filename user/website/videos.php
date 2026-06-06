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

// Helper to add missing columns (uses TEXT for long strings so digi_card stays under MySQL row-size limit)
function ensureColumnExists($connect, $table, $column, $definition){
    $res = @mysqli_query($connect, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    if(!$res || mysqli_num_rows($res) == 0){
        $sql = "ALTER TABLE `{$table}` ADD `{$column}` {$definition}";
        try {
            if (!mysqli_query($connect, $sql)) {
                return false;
            }
        } catch (Throwable $e) {
            return false;
        }
    }
    return true;
}

for ($vi = 1; $vi <= 20; $vi++) {
    ensureColumnExists($connect, 'digi_card', 'd_youtube' . $vi, "VARCHAR(150) DEFAULT ''");
}

$query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');

if(mysqli_num_rows($query) == 0){
    echo '<script>alert("Card id does not match with your email account"); window.location.href="business-name.php";</script>';
    exit;
}
// Use associative array so $cardRow['d_youtube1'] etc. work correctly
$cardRow = mysqli_fetch_assoc($query);

// Handle form submission
if(isset($_POST['process3'])){
    $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'"');
    if(mysqli_num_rows($query) == 1){

        for($i = 1; $i <= 20; $i++){
            ensureColumnExists($connect, 'digi_card', 'd_youtube' . $i, "VARCHAR(150) DEFAULT ''");
        }

        $updates = array();

        for($i = 1; $i <= 20; $i++){
            $field = 'd_youtube' . $i;
            $value = isset($_POST[$field]) ? mysqli_real_escape_string($connect, trim($_POST[$field])) : '';
            $updates[] = $field . '="' . $value . '"';
        }

        $update_sql = 'UPDATE digi_card SET ' . implode(', ', $updates) . ' WHERE id="' . $_SESSION['card_id_inprocess'] . '"';

        $update = mysqli_query($connect, $update_sql);

        if($update){
            $_SESSION['save_success'] = 'Video Links Updated Successfully!';
            // Re-fetch updated record so form shows saved values (before redirect)
            $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');
            if($query && mysqli_num_rows($query) > 0){
                $cardRow = mysqli_fetch_assoc($query);
            }
            // Redirect to prevent form resubmission - page will load with saved data
            if (!headers_sent()) {
                header('Location: videos.php?card_number='.$_SESSION['card_id_inprocess']);
                exit;
            }
        } else {
            $_SESSION['save_error'] = "Error! Try Again.";
            if (!headers_sent()) {
                header('Location: videos.php?card_number='.$_SESSION['card_id_inprocess']);
                exit;
            }
        }
    } else {
        $_SESSION['save_error'] = "Detail Not Available. Try Again.";
        header('Location: payment-details.php?card_number='.$_SESSION['card_id_inprocess']);
        exit;
    }
}

include '../includes/header.php';
?>

<!-- Phase B · Step 7 — videos.php uses the central .mw-* design system (header.php).
     JS hooks preserved: form#card_form, name="d_youtube1..20", name="process3",
     .btn.btn-primary.align-center, .save_btn. -->
<main class="Dashboard mw-page">
    <div class="container-fluid customer_content_area mw-container">
        <div class="main-top mw-page-header">
            <h1 class="heading mw-page-title">Videos</h1>
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
                    <h2 class="heading2 mw-section-title">Video Links</h2>
                    <p class="description mw-helper-text">
                        <i class="fa fa-info-circle" aria-hidden="true"></i>
                        <span>You can add up to <strong style="color:var(--mw-color-text);font-weight:600">20 videos</strong> (YouTube, Facebook, or Instagram links).</span>
                    </p>
                </div>

                <form action="" method="POST" id="card_form" class="mw-form">
                    <div class="mw-form-grid-2">
                        <?php
                        for ($i = 1; $i <= 20; $i++) {
                            $field = 'd_youtube' . $i;
                            $labelNum = str_pad($i, 2, '0', STR_PAD_LEFT);
                            $value = isset($cardRow[$field]) && $cardRow[$field] !== null ? htmlspecialchars($cardRow[$field]) : '';
                            $has_value = trim($value) !== '';
                        ?>
                        <div class="form-group mw-form-group">
                            <label for="<?php echo $field; ?>" class="mw-label">
                                <span class="videos-num-badge <?php echo $has_value ? 'is-filled' : ''; ?>"><?php echo $labelNum; ?></span>
                                <span>Video Link</span>
                            </label>
                            <div class="mw-input-icon-wrap">
                                <span class="mw-input-icon"><i class="fa fa-play-circle" aria-hidden="true"></i></span>
                                <input type="text"
                                       name="<?php echo $field; ?>"
                                       id="<?php echo $field; ?>"
                                       maxlength="500"
                                       class="form-control mw-input"
                                       placeholder="YouTube, Facebook, or Instagram video link"
                                       value="<?php echo $value; ?>">
                            </div>
                        </div>
                        <?php } ?>
                    </div>

                    <div class="Product-ServicesBtn mw-btn-row">
                        <a href="products.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left mw-btn mw-btn-back">
                            <span class="left_angle angle mw-btn-angle"><i class="fa fa-angle-left" aria-hidden="true"></i></span>
                            <span>Back</span>
                        </a>
                        <button type="submit" name="process3" class="btn btn-primary align-center save_btn mw-btn mw-btn-save">
                            <img src="../../assets/images/Save.png" alt="" style="width:1.25rem;height:1.25rem;flex-shrink:0;">
                            <span>Save</span>
                        </button>
                        <a href="image-gallery.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right mw-btn mw-btn-next">
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
    .videos-num-badge          { display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: 0.375rem; font-size: var(--mw-font-pill); font-weight: 700; background: #f1f5f9; color: var(--mw-color-text-muted); flex-shrink: 0; }
    .videos-num-badge.is-filled{ background: rgb(201 162 39 / 0.15); color: var(--mw-color-primary-dark); }
</style>

<?php include '../includes/footer.php'; ?>





