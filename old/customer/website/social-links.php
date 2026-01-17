<?php
// Handle card_number from URL, session, or cookie
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
    header('Location: business-name.php');
    exit;
}

$query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');

if(mysqli_num_rows($query) == 0){
    echo '<script>alert("Card id does not match with your email account"); window.location.href="business-name.php";</script>';
    exit;
} else {
    $row = mysqli_fetch_array($query);
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
        d_pinterest="'.mysqli_real_escape_string($connect, $_POST['d_pinterest']).'",
        d_youtube1="'.mysqli_real_escape_string($connect, $_POST['d_youtube1']).'",
        d_youtube2="'.mysqli_real_escape_string($connect, $_POST['d_youtube2']).'",
        d_youtube3="'.mysqli_real_escape_string($connect, $_POST['d_youtube3']).'",
        d_youtube4="'.mysqli_real_escape_string($connect, $_POST['d_youtube4']).'",
        d_youtube5="'.mysqli_real_escape_string($connect, $_POST['d_youtube5']).'"
        WHERE id="'.$_SESSION['card_id_inprocess'].'"');
        
        if($update){
            $_SESSION['save_success'] = "Social Links Updated Successfully!";
            header('Location: social-links.php?card_number='.$_SESSION['card_id_inprocess']);
            exit;
        } else {
            $_SESSION['save_error'] = "Error! Try Again.";
            header('Location: social-links.php?card_number='.$_SESSION['card_id_inprocess']);
            exit;
        }
    } else {
        $_SESSION['save_error'] = "Detail Not Available. Try Again.";
        header('Location: social-links.php?card_number='.$_SESSION['card_id_inprocess']);
        exit;
    }
}

include 'header.php';
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
        <div class="main-top">
        <span class="heading">Social Media Links</span>
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
                <label class="heading">Social Media Links:</label>
                <form action="" method="POST" enctype="multipart/form-data" id="card_form">
                    <div class="form-group">
                        <label for="d_fb">Facebook  <img src="../assets/img/facebook.png" width="30" alt=""></label>
                        <input type="text" name="d_fb" id="d_fb" maxlength="200" class="form-control" placeholder="Enter Your Facebook Business Page/Profile Link" value="<?php echo !empty($row['d_fb']) ? htmlspecialchars($row['d_fb']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="d_instagram">Instagram  <img src="../assets/img/instagram.png" width="30" alt=""></label>
                        <input type="text" name="d_instagram" id="d_instagram" maxlength="200" class="form-control" placeholder="Enter Your Instagram Link" value="<?php echo !empty($row['d_instagram']) ? htmlspecialchars($row['d_instagram']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="d_youtube">YouTube  <img src="../assets/img/youtube.png" width="30" alt=""></label>
                        <input type="text" name="d_youtube" id="d_youtube" maxlength="200" class="form-control" placeholder="Enter Your YouTube Channel Link" value="<?php echo !empty($row['d_youtube']) ? htmlspecialchars($row['d_youtube']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="d_twitter">X (Twitter)  <img src="../assets/img/twitter.png" width="30" alt=""></label>
                        <input type="text" name="d_twitter" id="d_twitter" maxlength="200" class="form-control" placeholder="Enter Your X Link" value="<?php echo !empty($row['d_twitter']) ? htmlspecialchars($row['d_twitter']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="d_linkedin">LinkedIn  <img src="../assets/img/linkedin.png" width="30" alt=""></label>
                        <input type="text" name="d_linkedin" id="d_linkedin" maxlength="200" class="form-control" placeholder="Enter Your LinkedIn Profile Link" value="<?php echo !empty($row['d_linkedin']) ? htmlspecialchars($row['d_linkedin']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="d_pinterest">Pinterest  <img src="../assets/img/pinterest.png" width="30" alt=""></label>
                        <input type="text" name="d_pinterest" id="d_pinterest" maxlength="200" class="form-control" placeholder="Enter Your Pinterest Link" value="<?php echo !empty($row['d_pinterest']) ? htmlspecialchars($row['d_pinterest']) : ''; ?>">
                    </div>

                    <label class="heading2">YouTube Video Links:</label>

                    <div class="form-group">
                        <label for="d_youtube1">YouTube Video Link 01 </label>
                        <input type="text" name="d_youtube1" id="d_youtube1" maxlength="200" class="form-control" placeholder="Enter Your YouTube Video Link" value="<?php echo !empty($row['d_youtube1']) ? htmlspecialchars($row['d_youtube1']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="d_youtube2">YouTube Video Link 02</label>
                        <input type="text" name="d_youtube2" id="d_youtube2" maxlength="200" class="form-control" placeholder="Enter Your YouTube Video Link" value="<?php echo !empty($row['d_youtube2']) ? htmlspecialchars($row['d_youtube2']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="d_youtube3">YouTube Video Link 03 </label>
                        <input type="text" name="d_youtube3" id="d_youtube3" maxlength="200" class="form-control" placeholder="Enter Your YouTube Video Link" value="<?php echo !empty($row['d_youtube3']) ? htmlspecialchars($row['d_youtube3']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="d_youtube4">YouTube Video Link 04 </label>
                        <input type="text" name="d_youtube4" id="d_youtube4" maxlength="200" class="form-control" placeholder="Enter Your YouTube Video Link" value="<?php echo !empty($row['d_youtube4']) ? htmlspecialchars($row['d_youtube4']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="d_youtube5">YouTube Video Link 05 </label>
                        <input type="text" name="d_youtube5" id="d_youtube5" maxlength="200" class="form-control" placeholder="Enter Your YouTube Video Link" value="<?php echo !empty($row['d_youtube5']) ? htmlspecialchars($row['d_youtube5']) : ''; ?>">
                    </div>

                    <div class="Product-ServicesBtn" style="margin-top: 20px;">
                        <a href="company-details.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left">
                            <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                            <span>Back</span>
                        </a>
                        <button type="submit" name="process3" class="btn btn-primary align-center save_btn">
                            <img src="../assets/img/Save.png" class="img-fluid" width="35px" alt=""> 
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

<?php include '../footer.php'; ?>
