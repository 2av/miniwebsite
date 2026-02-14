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

        // Build update parts dynamically for 20 youtube fields
        $updates = array();
        for($i = 1; $i <= 20; $i++){
            $field = 'd_youtube' . $i;
            $value = isset($_POST[$field]) ? mysqli_real_escape_string($connect, $_POST[$field]) : '';
            $updates[] = $field . '="' . $value . '"';
        }
        $update_sql = 'UPDATE digi_card SET ' . implode(', ', $updates) . ' WHERE id="' . $_SESSION['card_id_inprocess'] . '"';

        $update = mysqli_query($connect, $update_sql);

        if($update){
            $_SESSION['save_success'] = "Video Links Updated Successfully!";
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

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
        <div class="main-top">
        <span class="heading">Videos</span>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
                </ol>
            </nav>
        </div>
        
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
        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
            <label class="heading2">YouTube Video Links:</label>
                <form action="" method="POST" enctype="multipart/form-data" id="card_form">
                   
         

                    <?php
                    // Generate 20 YouTube link inputs
                    for ($i = 1; $i <= 20; $i++) {
                        $field = 'd_youtube' . $i;
                        $labelNum = str_pad($i, 2, '0', STR_PAD_LEFT);
                        $value = isset($cardRow[$field]) ? htmlspecialchars($cardRow[$field]) : '';
                    ?>
                    <div class="form-group">
                        <label for="<?php echo $field; ?>">YouTube Video Link <?php echo $labelNum; ?> </label>
                        <input type="text" name="<?php echo $field; ?>" id="<?php echo $field; ?>" maxlength="200" class="form-control" placeholder="Enter Your YouTube Video Link" value="<?php echo $value; ?>">
                    </div>
                    <?php } ?>


                    <div class="Product-ServicesBtn" style="margin-top: 20px;">
                        <a href="social-links.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left">
                            <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                            <span>Back</span>
                        </a>
                        <button type="submit" name="process3" class="btn btn-primary align-center save_btn">
                            <img src="../../assets/images/Save.png" class="img-fluid" width="35px" alt=""> 
                            <span>Save</span>
                        </button>
                        <a href="payment-details.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right">
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
     .submitBtnSection{
        margin-top:100px;
    }
     footer{
        margin-bottom:54px;
    }
    .savebutton span{
        font-size:27px;
    }
    .savebutton{
        display: flex !important;
    margin: auto !important;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    }
    
    .Dashboard .heading,.Dashboard .heading2{
        font-size:24px !important;
    }
    .Dashboard label{
        font-size:22px !important;
    }
    .Dashboard input:focus{
        outline:none;
        box-shadow:none;
    }

    .Dashboard input{
        padding: 15px;
        height: 55px;
        font-size:16px;
    }
    
    .text-center button{
        width:120px !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center;
        gap:10px !important;
        margin: auto !important;
    }
    .card-body form{
        padding:0px 35px;
    }
    .card-body form label:not(.heading){
        margin-left:5px;
    }
    .card-body .heading{
        margin-top: 15px;
    margin-left: 40px;
    margin-bottom: 20px;
    position:relative;
    font-weight: 500;
    }
    .card-body .heading2{
        margin-top: 30px;
    
    margin-bottom: 20px;
    position:relative;
    font-weight: 500;
    }
    .card-body .heading:after
    {
        content: '';
    width: 175px;
    height: 2px;
    background: #ffb300;
    position: absolute;
    left: 3px;
    bottom: 0px;
    }
    .card-body .heading2:after
    {
        content: '';
    width: 175px;
    height: 2px;
    background: #ffb300;
    position: absolute;
    left: 3px;
    bottom: 1px;
    }
    @media screen and (max-width: 768px) {
        .card-body form {
    padding: 0px 15px;
}
.card-body {
    padding: 10px !important;
    padding-bottom: 100px !important;
}
.card-body .heading {
    margin-top: 15px;
    margin-left: 20px;
    margin-bottom: 20px;
    position: relative;
    font-weight: 500;
}
.submitBtnSection{
        margin-top:45px;
    }

    .Dashboard .heading, .Dashboard .heading2 {
    font-size: 22px !important;
}
.Dashboard label {
    font-size: 20px !important;
}
#card_form img{
    width: 22px;
}

    .Copyright-left,
.Copyright-right{
    padding:0px;
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
        width: 138px !important;
        left: 87px;
        height: 36px;
}
    }

    .Product-ServicesBtn{
        
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

</style>

<?php include '../includes/footer.php'; ?>





