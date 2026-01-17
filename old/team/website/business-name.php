<?php
// Handle card_number from URL - store in session and cookie
// MUST be done before any output (before including header.php)
require_once('../../common/config.php');

// Clear any existing card_id_inprocess when creating a new website (no card_number in URL or new=1 parameter)
if((!isset($_GET['card_number']) || empty($_GET['card_number'])) || (isset($_GET['new']) && $_GET['new'] == '1')) {
    // If no card_number in URL or explicitly creating new, clear session and cookie to start fresh
    unset($_SESSION['card_id_inprocess']);
    setcookie('card_id_inprocess', '', time() - 3600, '/'); // Delete cookie
}

if(isset($_GET['card_number']) && !empty($_GET['card_number'])) {
    $card_number = mysqli_real_escape_string($connect, $_GET['card_number']);
    $current_user_email = $_SESSION['user_email'] ?? '';
    
    // Validate that the card belongs to the current user before setting session/cookie
    $validate_query = mysqli_query($connect, 'SELECT id FROM digi_card WHERE id="'.$card_number.'" AND user_email="'.$current_user_email.'" LIMIT 1');
    if(mysqli_num_rows($validate_query) > 0) {
        $_SESSION['card_id_inprocess'] = $card_number;
        // Store in cookie for 24 hours
        setcookie('card_id_inprocess', $card_number, time() + (86400 * 1), '/');
    } else {
        // Card doesn't belong to current user, clear it
        unset($_SESSION['card_id_inprocess']);
        setcookie('card_id_inprocess', '', time() - 3600, '/');
    }
} elseif(isset($_COOKIE['card_id_inprocess']) && !empty($_COOKIE['card_id_inprocess'])) {
    // If card_number not in URL but exists in cookie, validate it belongs to current user
    $cookie_card_id = mysqli_real_escape_string($connect, $_COOKIE['card_id_inprocess']);
    $current_user_email = $_SESSION['user_email'] ?? '';
    
    // Validate that the card belongs to the current user
    $validate_query = mysqli_query($connect, 'SELECT id FROM digi_card WHERE id="'.$cookie_card_id.'" AND user_email="'.$current_user_email.'" LIMIT 1');
    if(mysqli_num_rows($validate_query) > 0) {
        // Valid card, restore to session
        $_SESSION['card_id_inprocess'] = $cookie_card_id;
    } else {
        // Card doesn't belong to current user, clear cookie and session
        unset($_SESSION['card_id_inprocess']);
        setcookie('card_id_inprocess', '', time() - 3600, '/');
    }
}

// Get franchisee email from sender_user_id - check user_details
$franchisee_email = "";
$user_email_escaped = mysqli_real_escape_string($connect, $_SESSION['user_email']);
$user_email_lower = strtolower(trim($user_email_escaped));
$query_customer = mysqli_query($connect, "SELECT sender_user_id FROM user_details WHERE LOWER(TRIM(email)) = '$user_email_lower' LIMIT 1");
$row_customer = mysqli_fetch_array($query_customer);
if(!empty($row_customer['sender_user_id'])){
    // Get the sender's email from user_details
    $sender_user_id = intval($row_customer['sender_user_id']);
    $query_sender = mysqli_query($connect, "SELECT email FROM user_details WHERE id = $sender_user_id AND role = 'FRANCHISEE' LIMIT 1");
    if($query_sender && mysqli_num_rows($query_sender) > 0){
        $sender_row = mysqli_fetch_array($query_sender);
        $sender_email = $sender_row['email'];
        
        // Get franchisee email from franchisee_login
        $query_franchisee = mysqli_query($connect, "SELECT f_user_email FROM franchisee_login WHERE f_user_email='".mysqli_real_escape_string($connect, $sender_email)."' LIMIT 1");
        if($query_franchisee && mysqli_num_rows($query_franchisee) > 0){
            $row_franchisee = mysqli_fetch_array($query_franchisee);
            $franchisee_email = $row_franchisee['f_user_email'];
        }
    }
}

// Also check team_members table for team users
if(empty($franchisee_email)) {
    $query_team = mysqli_query($connect, 'SELECT * FROM team_members WHERE member_email="'.$_SESSION['user_email'].'"');
    $row_team = mysqli_fetch_array($query_team);
    if(!empty($row_team['franchisee_id'])){
        $query_franchisee = mysqli_query($connect, 'SELECT * FROM franchisee_login WHERE id="'.$row_team['franchisee_id'].'"');
        $row_franchisee = mysqli_fetch_array($query_franchisee);
        if($row_franchisee && isset($row_franchisee['f_user_email'])){
            $franchisee_email = $row_franchisee['f_user_email'];
        }
    }
}

// Handle form submission (Save button - no redirect) - MUST be before header.php
if(isset($_POST['process1'])){
    $comp_name = mysqli_real_escape_string($connect, $_POST['d_comp_name']);
    
    // Check if company name already exists - MUST be unique
    $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE d_comp_name="'.$comp_name.'"');
    if(mysqli_num_rows($query) == 0){
        // Company name is unique, create new card
        $card_id = str_replace(array(' ','.','&','/','','[',']'), array('-','','','-','',''), $comp_name);
        $date = date('Y-m-d H:i:s');
        
        $insert = mysqli_query($connect, 'INSERT INTO digi_card (d_comp_name,uploaded_date,d_payment_status,user_email,d_card_status,card_id,f_user_email,validity_date) VALUES ("'.$comp_name.'","'.$date.'","Created","'.$_SESSION['user_email'].'","Active","'.$card_id.'","'.$franchisee_email.'",DATE_ADD("'.$date.'", INTERVAL 1 YEAR))');
        
        if($insert){
            // Insert data in 2nd and 3rd database tables
            $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE d_comp_name="'.$comp_name.'" AND user_email="'.$_SESSION['user_email'].'" order by id desc limit 1');
            $row = mysqli_fetch_array($query);
            
            $insert_digi2 = mysqli_query($connect, 'INSERT INTO digi_card2 (id,user_email) VALUES ("'.$row['id'].'","'.$_SESSION['user_email'].'")');
            $insert_digi3 = mysqli_query($connect, 'INSERT INTO digi_card3 (id,user_email) VALUES ("'.$row['id'].'","'.$_SESSION['user_email'].'")');
            
            $_SESSION['card_id_inprocess'] = $row['id'];
            // Save success message in session to display after redirect
            $_SESSION['save_success'] = "Company Name Saved. CARD Number is: ".$row['id'];
            // Redirect to same page to show success message
            header('Location: business-name.php?card_number='.$row['id']);
            exit;
        }
    } else {
        // Company name already exists - show error
        $_SESSION['save_error'] = "Company Name already exists. Please choose a different name.";
        header('Location: business-name.php');
        exit;
    }
}

// Handle update functionality (process2 - Save button, no redirect) - MUST be before header.php
if(isset($_POST['process2'])){
    $comp_name = mysqli_real_escape_string($connect, $_POST['d_comp_name']);
    
    // Check if company name already exists for a different record
    $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE d_comp_name="'.$comp_name.'" AND id != "'.$_SESSION['card_id_inprocess'].'"');
    
    if(mysqli_num_rows($query) == 0){
        // Company name is unique (or belongs to current record), allow update
        $card_id = str_replace(array(' ','.','&','/','','[',']'), array('-','','','-','',''), $comp_name);
        $update = mysqli_query($connect, 'UPDATE digi_card SET d_comp_name="'.$comp_name.'", card_id="'.$card_id.'" WHERE id="'.$_SESSION['card_id_inprocess'].'"');
        if($update){
            $_SESSION['save_success'] = "Company Name Updated Successfully";
            header('Location: business-name.php?card_number='.$_SESSION['card_id_inprocess']);
            exit;
        }
    } else {
        // Company name already exists for a different record - show error
        $_SESSION['save_error'] = "Company Name already exists. Please choose a different name.";
        header('Location: business-name.php?card_number='.$_SESSION['card_id_inprocess']);
        exit;
    }
}

include 'header.php';
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
        <div class="main-top">
        <span class="heading">Business Name</span>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
                </ol>
            </nav>
        </div>
        
        <?php if(isset($_GET['card_number'])): ?>
            <?php
            $_SESSION['card_id_inprocess'] = $_GET['card_number'];
            $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');
            $row = mysqli_fetch_array($query);
            
            if(mysqli_num_rows($query) == 0): ?>
                <div class="alert alert-danger">Card id Removed/Not available.</div>
            <?php else: ?>
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
                <div class="card mb-4">
                    <div class="card-body">
                        
                        <form action="#" method="POST" enctype="multipart/form-data" class="business_name_form">
                            <div class="form-group" style="width: 80%; margin-top: 20px; margin-bottom: 0px;">
                                <label for="d_comp_name">Business or Company Name: <span class="text-danger">*</span></label>
                                <input type="text" name="d_comp_name" class="form-control" maxlength="199" value="<?php echo htmlspecialchars($row['d_comp_name']); ?>" placeholder="Enter Your Business or Company Name*" required>
                                <sup>This name will not be changed later on so choose wisely</sup>
                            </div>
                            <div class="Product-ServicesBtn" style="margin-top: 20px; width: 86%;">
                                <a href="../../team/dashboard/" class="btn btn-secondary align-left">
                                    <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                                    <span>Back</span>
                                </a>
                                <button type="submit" name="process2" class="btn btn-primary align-center">
                                    <img src="../../customer/assets/img/Save.png" class="img-fluid" width="35px" alt=""> 
                                    <span>Save</span>
                                </button>
                                <a href="select-theme.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right">
                                    <span>Next</span>
                                    <span class="right_angle angle"><i class="fa fa-angle-right"></i></span>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php if(isset($_SESSION['save_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['save_error']; unset($_SESSION['save_error']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h4>Business or Company Name</h4>
                    <form action="#" method="POST" enctype="multipart/form-data" class="business_name_form">
                        <div class="form-group" style="width: 80%; margin-top: 20px; margin-bottom: 0px;">
                            <label for="d_comp_name">Business or Company Name: <span class="text-danger">*</span></label>
                            <input type="text" name="d_comp_name" class="form-control" maxlength="199" placeholder="Enter Your Business or Company Name*" required>
                            <sup>This name will not be changed later on so choose wisely</sup>
                        </div>
                        <div class="Product-ServicesBtn" style="margin-top: 20px; width: 86%;">
                            <a href="../dashboard/" class="btn btn-secondary align-left">
                                <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                                <span>Back</span>
                            </a>
                            <button type="submit" name="process1" class="btn btn-primary align-center">
                                <img src="../assets/img/Save.png" class="img-fluid" width="35px" alt=""> 
                                <span>Save</span>
                            </button>
                            <a href="select-theme.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-right">
                                <span>Next</span>
                                <span class="right_angle angle"><i class="fa fa-angle-right"></i></span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
</main>

<script>
// Hide "Save" button when textbox has value
document.addEventListener('DOMContentLoaded', function () {
    var nameInput = document.querySelector('input[name="d_comp_name"]');
    var saveNextBtn = document.querySelector('.Product-ServicesBtn .btn.btn-primary.align-center');

    function toggleSaveNextVisibility() {
        if (!nameInput || !saveNextBtn) return;

        if (nameInput.value.trim() !== '') {
            // Textbox has value – hide Save
            saveNextBtn.classList.add('d-none');
        } else {
            // Empty – show Save
            saveNextBtn.classList.remove('d-none');
        }
    }

    if (nameInput) {
        toggleSaveNextVisibility();
        nameInput.addEventListener('input', toggleSaveNextVisibility);
        nameInput.addEventListener('change', toggleSaveNextVisibility);
    }
});
</script>


<style>
    .business_name_form{
        display: flex;
        align-items: center;
         gap: 20px;
         flex-direction: column;
    }
    .business_name_form label{
       font-size:24px !important;
    }
    .business_name_form button{
        padding: 8px;
        margin-top: 5px !important;
        width: 165px;
        font-size: 17px !important;
    }

    .business_name_form sup{
        font-size: 20px;
        top: 5px;
        left: 3px;
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
    .top_header_section{
        width: 80%; margin-top: 20px; margin-bottom: 0px;
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

    @media screen and (max-width: 768px) {
.top_header_section{
        width: 100%; margin-top: 0px; margin-bottom: 0px;
    }
    .card-body {
    padding: 30px 20px!important;
    padding-bottom: 100px !important;
}
.business_name_form label {
    font-size: 22px !important;
}
.d_comp_name{
    padding:20px 10px;
    font-size:16px;
}
.business_name_form sup {
    font-size: 16px;
    top: 5px;
    left: 3px;
}
.Product-ServicesBtn{
    width: 80% !important;
    padding:0px;
            margin-top: 40px !important;
}
.save_btn{
        position: absolute;
    bottom: 150px;
    width: 145px !important;
    left: 77px;
    height: 36px;
}
.Copyright-left,
.Copyright-right{
    padding:0px;
}
    }
</style>

<?php include '../footer.php'; ?>

