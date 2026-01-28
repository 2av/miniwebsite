<?php 
// ID Card Generator for TEAM members (copied from old/team/idcard/index.php and adapted)

require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/helpers/role_helper.php');

// Require TEAM role
require_role('TEAM', '/login/team.php');

// Include shared header/layout
include __DIR__ . '/../includes/header.php';

// Handle profile picture upload
$upload_message = '';
$upload_status  = '';
$profile_image_path = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];
    
    if ($file['error'] == UPLOAD_ERR_OK) {
        $filename       = $file['name'];
        $imageFileType  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $file_allow     = array('png', 'jpeg', 'jpg', 'gif');
        $filesize       = $file['size'];
        $maxSize        = 250000; // 250KB
        
        // Check file type
        if (in_array($imageFileType, $file_allow)) {
            // Check file size
            if ($filesize <= $maxSize) {
                // Verify it's actually an image
                $check = getimagesize($file['tmp_name']);
                if ($check !== false) {
                    // Create upload directory if it doesn't exist
                    $upload_dir = __DIR__ . '/uploads/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $unique_filename = uniqid() . '_' . time() . '.' . $imageFileType;
                    $upload_path     = $upload_dir . $unique_filename;
                    
                    // Move uploaded file to destination
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $profile_image_path = 'uploads/' . $unique_filename;
                        // Store in session for persistence
                        $_SESSION['idcard_profile_image'] = $profile_image_path;
                        
                        // Save to database for permanent storage (unified user_details table for TEAM)
                        $team_member_id = get_user_id();
                        if (!empty($team_member_id)) {
                            // Ensure profile_image column exists on user_details
                            $check_profile_image = mysqli_query($connect, "SHOW COLUMNS FROM user_details LIKE 'profile_image'");
                            if ($check_profile_image && mysqli_num_rows($check_profile_image) == 0) {
                                mysqli_query($connect, "ALTER TABLE user_details ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL");
                            }
                            
                            // Update profile image in database
                            $safe_path = mysqli_real_escape_string($connect, $profile_image_path);
                            mysqli_query($connect, "UPDATE user_details SET profile_image = '{$safe_path}' WHERE id = '" . (int)$team_member_id . "' AND role='TEAM'");
                        }
                        
                        $upload_message = 'Profile picture uploaded successfully!';
                        $upload_status  = 'success';
                        header('Location: index.php?upload=success');
                        exit;
                    } else {
                        $upload_message = 'Failed to move uploaded file.';
                        $upload_status  = 'error';
                    }
                } else {
                    $upload_message = 'File is not a valid image.';
                    $upload_status  = 'error';
                }
            } else {
                $upload_message = 'File size exceeds 250KB limit. Please select a smaller image.';
                $upload_status  = 'error';
            }
        } else {
            $upload_message = 'Only PNG, JPG, JPEG or GIF files are allowed.';
            $upload_status  = 'error';
        }
    } else {
        $upload_message = 'Error uploading file. Please try again.';
        $upload_status  = 'error';
    }
}

// Get TEAM member data from unified user_details table
$team_member_id = get_user_id();
$auto_id        = '';
$auto_email     = get_user_email() ?? '';
$auto_department= '';
$auto_mobile    = '';
$auto_name      = get_user_name() ?? '';

if (!empty($team_member_id)) {
    // Ensure optional columns exist on user_details for TEAM
    $check_department = mysqli_query($connect, "SHOW COLUMNS FROM user_details LIKE 'department'");
    if ($check_department && mysqli_num_rows($check_department) == 0) {
        mysqli_query($connect, "ALTER TABLE user_details ADD COLUMN department VARCHAR(100) DEFAULT NULL");
    }
    $check_profile_image = mysqli_query($connect, "SHOW COLUMNS FROM user_details LIKE 'profile_image'");
    if ($check_profile_image && mysqli_num_rows($check_profile_image) == 0) {
        mysqli_query($connect, "ALTER TABLE user_details ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL");
    }
    
    // Get TEAM member data including profile image from user_details
    $member_query  = "SELECT id, name, email, phone, department, profile_image FROM user_details WHERE id = '" . (int)$team_member_id . "' AND role='TEAM' LIMIT 1";
    $member_result = mysqli_query($connect, $member_query);
    if ($member_result && $member = mysqli_fetch_assoc($member_result)) {
        // Use actual user_details ID (6 digits with leading zeros)
        $auto_id        = str_pad((int)$member['id'], 6, '0', STR_PAD_LEFT);
        $auto_email     = $member['email']       ?? $auto_email;
        $auto_department= $member['department']  ?? '';
        $auto_mobile    = $member['phone']       ?? '';
        $auto_name      = $member['name']        ?? $auto_name;
        
        // Get profile image from database (permanent storage)
        if (!empty($member['profile_image']) && file_exists(__DIR__ . '/' . $member['profile_image'])) {
            $profile_image_path = $member['profile_image'];
            $_SESSION['idcard_profile_image'] = $profile_image_path;
        }
    }
}

// Get saved profile image if exists (session fallback)
if (empty($profile_image_path)) {
    if (isset($_SESSION['idcard_profile_image']) && file_exists(__DIR__ . '/' . $_SESSION['idcard_profile_image'])) {
        $profile_image_path = $_SESSION['idcard_profile_image'];
    }
}

// Static website name
$website_name = 'www.miniwebsite.in';
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
        <div class="main-top">
        <span class="heading">ID Card</span>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo $nav_base; ?>/dashboard">Mini Website</a></li>
                    <li class="breadcrumb-item active" aria-current="page">ID Card</li>
                </ol>
            </nav>
        </div>
       
        <div class="card mb-4">
            <div class="card-body">
                <div class="idcard-head">
                    <h2 class="heading">ID Card Generator</h2>
                    <p class="text-muted mb-4 sub_title">Upload your profile picture to generate your personalized ID card. All information is automatically loaded from your account.</p>
                    
                    <!-- Upload Message -->
                    <?php if (isset($_GET['upload']) && $_GET['upload'] == 'success'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Profile picture uploaded successfully!
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php elseif (!empty($upload_message)): ?>
                    <div class="alert alert-<?php echo $upload_status == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($upload_message); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- ID Card Preview -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="idcard-preview-wrapper">
                                <div class="col-md-5">
                                <canvas id="idcardCanvas" class="idcard-canvas"></canvas>
                                </div>
                                
                                 <!-- Upload Section -->
                    
                        <div class="col-md-7">
                            <div class="upload-download-box">
                                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                    <div class="form-group displayFlex">
                                        <label for="profile_picture" class="font-weight-semibold heading">Choose Profile Picture</label>
                                        <input type="file" class="form-control-file" id="profile_picture" name="profile_picture" accept="image/*" onchange="previewProfilePicture(this)">
                                        <small class="form-text text-muted">Max 250KB • JPG, PNG, GIF • Square image works best</small>
                                    </div>
                                    <div class="form-group ">
                                        <div id="profilePreview" class="profile-preview">
                                            <?php if (!empty($profile_image_path)): ?>
                                                <img src="<?php echo htmlspecialchars($profile_image_path); ?>" alt="Profile Preview" id="previewImg">
                                            <?php else: ?>
                                                <p class="text-muted mb-0">No profile picture uploaded</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="action-buttons d-flex flex-column flex-sm-row align-items-center justify-content-between">
                                        <button type="submit" class="btn btn-primary flex-grow-1 mb-3 mb-sm-0">
                                            Upload & Refresh Preview
                                        </button>
                                        <button type="button" class="btn btn-success flex-grow-1" onclick="downloadIdCard()">
                                            <i class="fa fa-download mr-2"></i>Download ID Card
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                    
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hidden inputs for JavaScript to access PHP values -->
                    <input type="hidden" id="employeeId" value="<?php echo htmlspecialchars($auto_id); ?>">
                    <input type="hidden" id="name" value="<?php echo htmlspecialchars($auto_name); ?>">
                    <input type="hidden" id="phone" value="<?php echo htmlspecialchars($auto_mobile); ?>">
                    <input type="hidden" id="email" value="<?php echo htmlspecialchars($auto_email); ?>">
                    <input type="hidden" id="website" value="<?php echo htmlspecialchars($website_name); ?>">
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Global variables
const CARD_WIDTH_INCH = 2.125;
const CARD_HEIGHT_INCH = 3.34;
const EXPORT_DPI = 300;

const canvas = document.getElementById('idcardCanvas');
const ctx = canvas.getContext('2d');
canvas.width = Math.round(CARD_WIDTH_INCH * EXPORT_DPI);
canvas.height = Math.round(CARD_HEIGHT_INCH * EXPORT_DPI);

let backgroundImage = new Image();
let profileImage = new Image();
let profileImageLoaded = false;
let backgroundImageLoaded = false;
let baseImageWidth = 443;
let baseImageHeight = 685;

// Load background image
backgroundImage.onload = function() {
    backgroundImageLoaded = true;
    baseImageWidth = backgroundImage.width;
    baseImageHeight = backgroundImage.height;
    drawIdCard();
};

backgroundImage.onerror = function() {
    console.error('Failed to load background image');
    alert('Failed to load ID card background image. Please contact administrator.');
};

backgroundImage.src = '<?php echo $assets_base; ?>/assets/images/idcard/idcardbackground.PNG';

// Load profile image if exists
<?php if (!empty($profile_image_path)): ?>
profileImage.onload = function() {
    profileImageLoaded = true;
    drawIdCard();
};
profileImage.onerror = function() {
    profileImageLoaded = false;
    console.error('Failed to load profile image');
    drawIdCard(); // Draw without profile image
};
profileImage.src = '<?php echo htmlspecialchars($profile_image_path); ?>';
<?php endif; ?>

// Function to draw ID card
function drawIdCard() {
    if (!backgroundImageLoaded) return;

    const widthScale = canvas.width / baseImageWidth;
    const heightScale = canvas.height / baseImageHeight;
    const scaleX = (val) => val * widthScale;
    const scaleY = (val) => val * heightScale;
    
    // Clear canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Draw background
    ctx.drawImage(backgroundImage, 0, 0, canvas.width, canvas.height);
    
    // Get canvas dimensions
    const cardWidth = canvas.width;
    const cardHeight = canvas.height;
    
    // Draw ID number in top-right corner (white text)
    const employeeId = document.getElementById('employeeId').value || '';
    if (employeeId) {
        ctx.fillStyle = '#ffffff';
        ctx.font = `${Math.max(scaleY(20), 14)}px "Poppins", sans-serif`;
        ctx.textAlign = 'right';
        ctx.fillText('ID: ' + employeeId, cardWidth - scaleX(30), scaleY(30));
        ctx.textAlign = 'left'; // Reset alignment
    }
    
    // Always reserve space for profile picture area (centered horizontally)
    const profileWidth = scaleX(199); // Width of profile picture
    const profileHeight = scaleY(220); // Height of profile picture
    const profileX = (cardWidth - profileWidth) / 2; // Center horizontally
    const profileY = scaleY(125); // Y position from top
    const cornerRadius = scaleX(12);
    const borderWidth = scaleX(4);
    
    // Draw profile picture frame (always draw, even if no image)
    ctx.save();
    
    // Draw yellow border (outer rounded rect)
    ctx.fillStyle = '#FFBE17'; // Yellow color
    ctx.beginPath();
    ctx.roundRect(profileX - borderWidth, profileY - borderWidth, profileWidth + (borderWidth * 2), profileHeight + (borderWidth * 2), cornerRadius + 2);
    ctx.fill();
    
    // Draw white background (inner rounded rect)
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.roundRect(profileX, profileY, profileWidth, profileHeight, cornerRadius);
    ctx.fill();
    
    // Draw profile image if loaded
    if (profileImageLoaded && profileImage.complete) {
        // Clip to rounded rectangle and draw profile image
        ctx.beginPath();
        ctx.roundRect(profileX, profileY, profileWidth, profileHeight, cornerRadius);
        ctx.clip();
        ctx.drawImage(profileImage, profileX, profileY, profileWidth, profileHeight);
    }
    
    ctx.restore();
    
    // Draw text content (centered)
    ctx.textAlign = 'center';
    const centerX = cardWidth / 2;
    
    // Always calculate Y position based on profile picture area (consistent positioning)
    const profileAreaEnd = 125 + 220; // Profile picture area ends here
    let currentY = scaleY(profileAreaEnd + 50); // Consistent spacing after profile picture area
    const lineHeight = scaleY(40); // Space between lines
    
    // Name
    ctx.fillStyle = '#000000';
    ctx.font = `bold ${Math.max(scaleY(35), 18)}px "Poppins", sans-serif`;
    const name = document.getElementById('name').value || 'Your Name';
    ctx.fillText(name.toUpperCase(), centerX, currentY);
    
    // Designation
    currentY += lineHeight + 3;
    ctx.fillStyle = '#0066CC';
    ctx.font = `bold ${Math.max(scaleY(33), 16)}px "Poppins", sans-serif`;
    ctx.fillText('(Sales Executive)', centerX, currentY);
    
    // Contact information
    currentY += lineHeight + scaleY(15);
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#000000';
    ctx.font = `${Math.max(scaleY(25), 12)}px "Poppins", sans-serif`;
    const contactIconX = (cardWidth / 2) - scaleX(160);
    const iconColumnWidth = scaleX(28);
    const iconTextGap = scaleX(10);
    const contactTextX = contactIconX + iconColumnWidth + iconTextGap;
    
    const phone = document.getElementById('phone').value || '';
    if (phone) {
        ctx.fillText('☎', contactIconX, currentY);
        ctx.fillText(phone, contactTextX, currentY);
        currentY += lineHeight;
    }
    
    const email = document.getElementById('email').value || '';
    if (email) {
        ctx.fillText('✉', contactIconX, currentY);
        ctx.fillText(email, contactTextX, currentY);
        currentY += lineHeight;
    }
    
    const website = document.getElementById('website').value || '';
    if (website) {
        drawWebsiteIcon(contactIconX, currentY, iconColumnWidth);
        ctx.fillText(website, contactTextX, currentY);
    }
    
    ctx.textAlign = 'left';
    ctx.textBaseline = 'alphabetic';
}

// Helper function for rounded rectangles (if not supported natively)
if (!CanvasRenderingContext2D.prototype.roundRect) {
    CanvasRenderingContext2D.prototype.roundRect = function(x, y, width, height, radius) {
        this.beginPath();
        this.moveTo(x + radius, y);
        this.lineTo(x + width - radius, y);
        this.quadraticCurveTo(x + width, y, x + width, y + radius);
        this.lineTo(x + width, y + height - radius);
        this.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        this.lineTo(x + radius, y + height);
        this.quadraticCurveTo(x, y + height, x, y + height - radius);
        this.lineTo(x, y + radius);
        this.quadraticCurveTo(x, y, x + radius, y);
        this.closePath();
    };
}

function previewProfilePicture(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewDiv = document.getElementById('profilePreview');
            previewDiv.innerHTML = '<img src=\"' + e.target.result + '\" alt=\"Profile Preview\" id=\"previewImg\" style=\"max-width: 200px; max-height: 200px; border-radius: 50%;\">';
            
            profileImage.onload = function() {
                profileImageLoaded = true;
                drawIdCard();
            };
            profileImage.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function downloadIdCard() {
    const link = document.createElement('a');
    link.download = 'id_card_' + Date.now() + '.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
}

// Helper to draw website icon
function drawWebsiteIcon(iconX, centerY, iconWidth) {
    const radius = iconWidth * 0.35;
    const centerX = iconX + radius + iconWidth * 0.15;
    
    ctx.save();
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = Math.max(radius * 0.25, 1);
    
    ctx.beginPath();
    ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
    ctx.stroke();
    
    ctx.beginPath();
    ctx.ellipse(centerX, centerY, radius * 0.9, radius * 0.5, 0, 0, Math.PI * 2);
    ctx.stroke();
    ctx.beginPath();
    ctx.ellipse(centerX, centerY, radius * 0.9, radius * 0.25, 0, 0, Math.PI * 2);
    ctx.stroke();
    
    ctx.beginPath();
    ctx.moveTo(centerX - radius, centerY);
    ctx.lineTo(centerX + radius, centerY);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(centerX, centerY - radius);
    ctx.lineTo(centerX, centerY + radius);
    ctx.stroke();
    
    ctx.restore();
}

document.addEventListener('DOMContentLoaded', function() {
    drawIdCard();
});
</script>

<style>
.idcard-preview-wrapper {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    border: 2px dashed #dee2e6;
}

.idcard-canvas {
    width: 2.125in;
    height: 3.34in;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.sub_title{
    font-size:22px;
}
.idcard-preview-wrapper{
    display:flex;
}
.displayFlex{
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: flex-start;
}
.action-buttons{
    gap:90px;
}

.action-buttons button{
    color:#fff !important;
    font-size:16px !important;
}

#profile_picture{
    font-size:16px;
}
.profile-preview {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 4px;
    min-height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-preview img {
    max-width: 200px;
    max-height: 200px;
    border-radius: 50%;
    border: 3px solid #007bff;
    object-fit: cover;
}

.card {
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 1rem;
}

.btn-primary {
    background-color: #007bff;
    border-color: #007bff;
}

.btn-success {
    background-color: #28a745;
    border-color: #28a745;
}

.alert {
    margin-bottom: 20px;
}

.upload-download-box {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 25px;
    background: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.upload-download-box .form-group label {
    font-weight: 600;
}

.upload-download-box .btn {
    width: 100%;
}

@media (min-width: 576px) {
    .upload-download-box .btn {
        width: auto;
        min-width: 180px;
    }
    .upload-download-box .action-buttons > * + * {
        margin-left: 15px;
    }
}
.heading {
    font-size: 24px !important;
}
@media (max-width: 768px) {
    .idcard-preview-wrapper {
        display: flex;
        flex-direction: column;
        gap:40px;
    }
    .upload-download-box {
        border: 1px solid #e9ecef;
        border-radius: 10px;
        padding: 25px 15px;
        background: #fff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    .action-buttons {
        gap: 10px;
    }
    .Copyright-left,
    .Copyright-right{
        padding:0px;
    }
    .action-buttons button {
        color: #fff !important;
        font-size: 14px !important;
        margin:0px;
    }
    .heading {
        font-size: 22px !important;
    }
    .sub_title{
        font-size:20px;
    }
    .idcard-preview-wrapper {
        padding:20px 0px;
    }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>

