<?php

require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../app/config/email.php');

// Handle user collaboration toggle - using user_details
if(isset($_POST['toggle_user_collaboration'])){
    $user_email = mysqli_real_escape_string($connect, $_POST['user_email']);
    $status = mysqli_real_escape_string($connect, $_POST['collaboration_status']);
    
    $query = mysqli_query($connect, "UPDATE user_details SET collaboration_enabled='$status' WHERE email='$user_email' AND role='CUSTOMER'");
    
    if($query){
        // If collaboration is being enabled (YES), send congratulatory email
        if($status == 'YES') {
            $email_sent = sendCollaborationUpgradeEmail($user_email);
            if($email_sent) {
                echo '<span class="alert success">User collaboration status updated successfully and congratulatory email sent!</span>';
            } else {
                echo '<span class="alert success">User collaboration status updated successfully, but email could not be sent.</span>';
            }
        } else {
            echo '<span class="alert success">User collaboration status updated successfully</span>';
        }
    } else {
        echo '<span class="alert danger">Failed to update user collaboration status: ' . mysqli_error($connect) . '</span>';
    }
}

// Handle user sales kit toggle - using user_details
if(isset($_POST['toggle_user_saleskit'])){
    $user_email = mysqli_real_escape_string($connect, $_POST['user_email']);
    $status = mysqli_real_escape_string($connect, $_POST['saleskit_status']);
    
    $query = mysqli_query($connect, "UPDATE user_details SET saleskit_enabled='$status' WHERE email='$user_email' AND role='CUSTOMER'");
    
    if($query){
        echo '<span class="alert success">User sales kit status updated successfully</span>';
    } else {
        echo '<span class="alert danger">Failed to update user sales kit status: ' . mysqli_error($connect) . '</span>';
    }
}

// Handle MW Referral toggle - using user_details
if(isset($_POST['toggle_mw_referral'])){
    $user_email = mysqli_real_escape_string($connect, $_POST['user_email']);
    $status = intval($_POST['mw_referral_status']);
    
    $query = mysqli_query($connect, "UPDATE user_details SET mw_referral_id=$status WHERE email='$user_email' AND role='CUSTOMER'");
    
    if($query){
        echo '<span class="alert success">User MW Referral ID status updated successfully</span>';
    } else {
        echo '<span class="alert danger">Failed to update user MW Referral ID status: ' . mysqli_error($connect) . '</span>';
    }
}

// Update refund status - using user_details
if(isset($_POST['update_refund_status'])){
    $user_email = mysqli_real_escape_string($connect, $_POST['user_email']);
    $refund_status = mysqli_real_escape_string($connect, $_POST['refund_status']);
    
    // Allow only expected values
    $allowed = array('None','Refund Claimed','Refund Settled');
    if(!in_array($refund_status, $allowed, true)){
        echo '<span class="alert danger">Invalid refund status</span>';
        exit;
    }
    
    // Set date when status changes from None to another value
    if($refund_status === 'None'){
        $query = mysqli_query($connect, "UPDATE user_details SET refund_status='None', refund_status_date=NULL WHERE email='$user_email' AND role='CUSTOMER'");
    } else {
        $query = mysqli_query($connect, "UPDATE user_details SET refund_status='$refund_status', refund_status_date=IFNULL(refund_status_date, NOW()) WHERE email='$user_email' AND role='CUSTOMER'");
    }
    if($query){
        echo '<span class="alert success">success</span>';
    } else {
        echo '<span class="alert danger">Failed to update refund status: ' . mysqli_error($connect) . '</span>';
    }
}

// Function to send collaboration upgrade email
function sendCollaborationUpgradeEmail($user_email) {
    try {
        // Check if PHPMailer is available
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = SMTP_AUTH;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;
            
            // Recipients
            $mail->setFrom(DEFAULT_FROM_EMAIL, DEFAULT_FROM_NAME);
            $mail->addAddress($user_email);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Congratulations! Your Account Has Been Upgraded with Collaborator Feature';
            
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #4a90e2; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                    .button { display: inline-block; background: #4a90e2; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>Congratulations!</h1>
                    </div>
                    <div class="content">
                        <h2>Your Account Has Been Upgraded!</h2>
                        <p>Dear Valued Customer,</p>
                        <p>We are excited to inform you that your MiniWebsite account has been upgraded with the <strong>Collaborator Feature</strong>!</p>
                        
                        <h3>What does this mean for you?</h3>
                        <ul>
                            <li>You can now refer franchisees and earn commissions</li>
                            <li>Access to collaboration referral links</li>
                            <li>Enhanced earning opportunities</li>
                            <li>Priority support for collaboration features</li>
                        </ul>
                        
                        <p>You can now access your collaboration features by logging into your account and visiting the collaboration section.</p>
                        
                        <a href="https://miniwebsite.in/panel/login/login.php" class="button">Login to Your Account</a>
                        
                        <p>If you have any questions about your new collaborator features, please don\'t hesitate to contact our support team.</p>
                        
                        <p>Thank you for being a valued member of the MiniWebsite family!</p>
                        
                        <p>Best regards,<br>
                        <strong>MiniWebsite Team</strong></p>
                    </div>
                    <div class="footer">
                        <p>This is an automated message. Please do not reply to this email.</p>
                        <p>© 2025 MiniWebsite. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>';
            
            $mail->send();
            return true;
            
        } else {
            // Fallback to basic mail function
            return sendBasicCollaborationEmail($user_email);
        }
        
    } catch (Exception $e) {
        error_log("Collaboration email error: " . $e->getMessage());
        return false;
    }
}

// Fallback function for basic mail
function sendBasicCollaborationEmail($user_email) {
    $subject = 'Congratulations! Your Account Has Been Upgraded with Collaborator Feature';
    $message = '
    Dear Valued User,
    
    Congratulations! Your MiniWebsite account has been upgraded with the Collaborator Feature!
    
    What does this mean for you?
    - You can now refer franchisees as well and earn commissions
    - Access to collaboration referral links
    - Enhanced earning opportunities
    - Priority support for collaboration features
    
    You can now access your collaboration features by logging into your account.
    
    Login: https://miniwebsite.in/panel/login/login.php
    
    If you have any questions, please contact our support team.
    
    Thank you for being a valued member of the MiniWebsite family!
    
    Best regards,
    MiniWebsite Team
    ';
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . DEFAULT_FROM_EMAIL . "\r\n";
    
    try {
        return @mail($user_email, $subject, $message, $headers);
    } catch (Exception $e) {
        error_log("Basic collaboration email failed: " . $e->getMessage());
        return false;
    }
}

// image remove from page 
// AJAX: delete product from card_products_services
if(isset($_POST['action']) && $_POST['action'] === 'delete_product' && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);

    // Ensure user is logged in
    if(!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        echo '<div class="alert danger">Unauthorized. Please login and try again.</div>';
        exit;
    }

    $user_email = mysqli_real_escape_string($connect, $_SESSION['user_email']);
    $user_email_lower = strtolower(trim($user_email));

    // Get user id
    $user_q = mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = '$user_email_lower' LIMIT 1");
    if(!$user_q || mysqli_num_rows($user_q) == 0) {
        echo '<div class="alert danger">User account not found. Please login again.</div>';
        exit;
    }
    $user_row = mysqli_fetch_array($user_q);
    $user_id = intval($user_row['id']);

    // Try to find the product in card_products_services first (services)
    $verify_q = mysqli_query($connect, "SELECT id, product_image FROM card_products_services WHERE id = $product_id AND user_id = $user_id LIMIT 1");
    if($verify_q && mysqli_num_rows($verify_q) > 0) {
        $prod = mysqli_fetch_array($verify_q);
        // Delete product from services table
        $del_q = mysqli_query($connect, "DELETE FROM card_products_services WHERE id = $product_id AND user_id = $user_id LIMIT 1");
        if($del_q) {
            // Remove associated image file if exists and looks like a filename
            if(!empty($prod['product_image']) && is_string($prod['product_image']) && strpos($prod['product_image'], '.') !== false) {
                $file_path = __DIR__ . '/../assets/upload/websites/product-and-services/' . $prod['product_image'];
                if(file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
            echo '<div class="alert success">success</div>';
        } else {
            echo '<div class="alert danger">Failed to delete product: ' . mysqli_error($connect) . '</div>';
        }
        exit;
    }

    // If not found, try card_product_pricing (products)
    $verify_q2 = mysqli_query($connect, "SELECT id, product_image FROM card_product_pricing WHERE id = $product_id AND user_id = $user_id LIMIT 1");
    if($verify_q2 && mysqli_num_rows($verify_q2) > 0) {
        $prod = mysqli_fetch_array($verify_q2);
        $del_q2 = mysqli_query($connect, "DELETE FROM card_product_pricing WHERE id = $product_id AND user_id = $user_id LIMIT 1");
        if($del_q2) {
            if(!empty($prod['product_image']) && is_string($prod['product_image']) && strpos($prod['product_image'], '.') !== false) {
                $file_path = __DIR__ . '/../assets/upload/websites/product-pricing/' . $prod['product_image'];
                if(file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
            echo '<div class="alert success">success</div>';
        } else {
            echo '<div class="alert danger">Failed to delete product: ' . mysqli_error($connect) . '</div>';
        }
        exit;
    }

    // Not found in either table
    echo '<div class="alert danger">Product not found or access denied.</div>';
    exit;
}

// AJAX: delete gallery image from card_image_gallery (image-gallery.php)
if(isset($_POST['action']) && $_POST['action'] === 'delete_gallery_image' && isset($_POST['image_id'])) {
    $image_id = intval($_POST['image_id']);

    if(!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        echo '<div class="alert danger">Unauthorized. Please login and try again.</div>';
        exit;
    }

    $user_email = mysqli_real_escape_string($connect, $_SESSION['user_email']);
    $user_email_lower = strtolower(trim($user_email));

    $user_q = mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = '$user_email_lower' LIMIT 1");
    if(!$user_q || mysqli_num_rows($user_q) == 0) {
        echo '<div class="alert danger">User account not found. Please login again.</div>';
        exit;
    }
    $user_row = mysqli_fetch_array($user_q);
    $user_id = intval($user_row['id']);

    $verify_q = mysqli_query($connect, "SELECT id, gallery_image FROM card_image_gallery WHERE id = $image_id AND user_id = $user_id LIMIT 1");
    if(!$verify_q || mysqli_num_rows($verify_q) == 0) {
        echo '<div class="alert danger">Image not found or access denied.</div>';
        exit;
    }
    $img = mysqli_fetch_array($verify_q);

    $del_q = mysqli_query($connect, "DELETE FROM card_image_gallery WHERE id = $image_id AND user_id = $user_id LIMIT 1");
    if($del_q) {
        if(!empty($img['gallery_image']) && is_string($img['gallery_image']) && strpos($img['gallery_image'], '.') !== false) {
            $file_path = __DIR__ . '/../assets/upload/websites/image-gallery/' . $img['gallery_image'];
            if(file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        echo '<div class="alert success">success</div>';
    } else {
        echo '<div class="alert danger">Failed to delete image: ' . mysqli_error($connect) . '</div>';
    }
    exit;
}

if(isset($_POST['id'])){
	$query=mysqli_query($connect,'SELECT * FROM digi_card2 WHERE id="'.$_POST['id'].'" ');
	$value=$_POST['d_pro_img'];
	if(mysqli_num_rows($query) > 0){
		$remove_img=mysqli_query($connect,"UPDATE digi_card2 SET d_pro_img$value='',d_pro_name$value='' WHERE id=".$_POST['id']." ");
		if($remove_img){echo '<div class="alert success">"'.$value.'" Image and discription is removed.</div>';}
	}else {
		echo '<div class="alert danger">Image Id is not available</div>';
	}
}
	
if(isset($_POST['id_gal'])){
	$query=mysqli_query($connect,'SELECT * FROM digi_card3 WHERE id="'.$_POST['id_gal'].'" ');
	$value=$_POST['d_gall_img'];
	if(mysqli_num_rows($query) > 0){
		$id_gal=mysqli_query($connect,"UPDATE digi_card3 SET d_gall_img$value='' WHERE id=".$_POST['id_gal']." ");
		if($id_gal){echo '<div class="alert success">"'.$value.'" Image is removed.</div>';}
	}else {
		echo '<div class="alert danger">Image Id is not available</div>';
	}
}
	
?>



