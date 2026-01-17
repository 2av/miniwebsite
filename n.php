<?php
require_once(__DIR__ . '/app/config/database.php');
// Add autoloader for Composer packages
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Function to send email with proper formatting and multiple recipients using PHPMailer with SMTP
 *
 * @param string $to Primary recipient email
 * @param string $subject Email subject
 * @param string $message_content Message content
 * @param array $sender Sender information (name, email)
 * @param array $additional_recipients Additional recipients (cc, bcc)
 * @return boolean Success or failure
 */
function send_formatted_email($to, $subject, $message_content, $sender, $additional_recipients = []) {
    // Validate email addresses
    $to = filter_var($to, FILTER_SANITIZE_EMAIL);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false; // Invalid primary recipient
    }

    // Sanitize sender information
    $sender_name = isset($sender['name']) ? trim(preg_replace('/[\r\n\t\f\v]/', '', $sender['name'])) : '';
    $sender_email = isset($sender['email']) ? filter_var($sender['email'], FILTER_SANITIZE_EMAIL) : '';

    if (!filter_var($sender_email, FILTER_VALIDATE_EMAIL)) {
        // Use default sender from config if available, otherwise use a fallback
        $sender_email = defined('DEFAULT_FROM_EMAIL') ? DEFAULT_FROM_EMAIL : 'support@miniwebsite.in';
        $sender_name = defined('DEFAULT_FROM_NAME') ? DEFAULT_FROM_NAME : 'MiniWebsite Support';
    }

    // Create HTML message with proper encoding
    $html_message = "<!DOCTYPE html>\n<html>\n<head>\n";
    $html_message .= "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\n";
    $html_message .= "<title>" . htmlspecialchars($subject) . "</title>\n";
    $html_message .= "</head>\n<body>\n";
    $html_message .= $message_content;
    $html_message .= "\n</body>\n</html>";

    try {
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();                                      // Use SMTP
        $mail->Host       = SMTP_HOST;                        // SMTP server address
        $mail->SMTPAuth   = SMTP_AUTH;                        // Enable SMTP authentication
        $mail->Username   = SMTP_USERNAME;                    // SMTP username
        $mail->Password   = SMTP_PASSWORD;                    // SMTP password
        $mail->SMTPSecure = SMTP_SECURE;                      // Enable SSL encryption
        $mail->Port       = SMTP_PORT;                        // TCP port to connect to
        $mail->CharSet    = 'UTF-8';

        // Additional SMTP settings for better compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $mail->setLanguage('en'); // Set language to English

        // Recipients
        $mail->setFrom($sender_email, $sender_name);
        $mail->addAddress($to);                              // Add a recipient
        $mail->addReplyTo($sender_email, $sender_name);

        // Add CC recipients if provided
        if (!empty($additional_recipients['cc'])) {
            $cc_email = filter_var($additional_recipients['cc'], FILTER_SANITIZE_EMAIL);
            if (filter_var($cc_email, FILTER_VALIDATE_EMAIL)) {
                $mail->addCC($cc_email);
            }
        }

        // Add BCC recipients if provided
        if (!empty($additional_recipients['bcc'])) {
            $bcc_email = filter_var($additional_recipients['bcc'], FILTER_SANITIZE_EMAIL);
            if (filter_var($bcc_email, FILTER_VALIDATE_EMAIL)) {
                $mail->addBCC($bcc_email);
            }
        }

        // Content
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $html_message;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message_content)); // Plain text version

        // Send the email
        return $mail->send();

    } catch (\Exception $e) {
        // Log the error
        error_log("Email sending failed: " . $e->getMessage());

        // Return false to indicate failure
        return false;
    }
}



// Get the card ID from the URL parameter
$card_id = isset($_GET['n']) ? $_GET['n'] : '';

// Query the database for the card
$query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE card_id="'.$card_id.'" ');

// Fetch the card data
$row = mysqli_fetch_array($query);
?>



<head>


        <!-- HTML Meta Tags kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk-->
        <title><?php if(isset($row) && $row && !empty($row['d_comp_name'])){echo $row['d_comp_name'];} ?> || Digital Visiting Miniwebsite  </title>


        <!-- Facebook Meta Tags -->
		 <!--<meta property="og:image" content="https://digiweblive.com/panel/favicons/220118012208Screenshot_20220104-094428_WhatsAppBusiness.jpg">-->
		 <meta property="og:image" content="https://<?php echo $_SERVER['HTTP_HOST']; ?><?php if(isset($row) && $row && !empty($row['d_logo'])){echo '/'.str_replace('../','',$row['d_logo_location']);} ?>">

        <meta property="og:url" content="https://<?php echo $_SERVER['HTTP_HOST'].'/'.(isset($row) && $row ? $row['card_id'] : ''); ?>">
        <meta property="og:type" content="website">
        <meta property="og:title" content="<?php if(isset($row) && $row && !empty($row['d_comp_name'])){echo $row['d_comp_name'];} ?> || Digital Visiting Miniwebsite Online">
        <meta property="og:description" content=" <?php if(isset($row) && $row && !empty($row['d_f_name'])){echo $row['d_f_name'].' '.$row['d_l_name'];} ?><?php if(isset($row) && $row && !empty($row['d_position'])){echo ' ('.$row['d_position'].')';} ?><?php if(isset($row) && $row && !empty($row['d_about_us'])){echo ' '.$row['d_about_us'].'';} ?>">

        <!-- Twitter Meta Tags -->
		 <meta name="twitter:image" content="<?php if(isset($row) && $row && !empty($row['d_logo'])){echo 'panel/'.str_replace('../','',$row['d_logo_location']);} ?>">

        <meta property="twitter:url" content="https://<?php echo $_SERVER['HTTP_HOST'].'/'.(isset($row) && $row ? $row['card_id'] : ''); ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?php if(isset($row) && $row && !empty($row['d_comp_name'])){echo $row['d_comp_name'];} ?> || Digital Visiting MiniWebsite Online">
        <meta name="twitter:description" content=" <?php if(isset($row) && $row && !empty($row['d_f_name'])){echo $row['d_f_name'].' '.$row['d_l_name'];} ?><?php if(isset($row) && $row && !empty($row['d_position'])){echo ' ('.$row['d_position'].')';} ?><?php if(isset($row) && $row && !empty($row['d_about_us'])){echo ' '.$row['d_about_us'];} ?>">

        <!-- Meta Tags Generated via -->



		<link rel="icon" href="<?php if(isset($row) && $row && !empty($row['d_logo'])){echo 'data:image/x-icon;base64,'.base64_encode($row['d_logo']);} ?>" type="image/*" sizes="16x16"/>



  <!-- Required meta tags -->
		  <meta charset="utf-8" />
		  <!-- Required meta tags -->
		  <meta charset="utf-8" />
		  <link rel='stylesheet' href='panel/all.css' integrity='sha384-lZN37f5QGtY3VHgisS14W3ExzMWZxybE1SJSEsQp9S+oqd12jhcu+A56Ebc1zFSJ' crossorigin='anonymous'>
		  <link rel="stylesheet" href="panel/awesome.min.css">
		  <link rel="preconnect" href="https://fonts.googleapis.com">
		  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		  <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@300;400;700&display=swap" rel="stylesheet">

 <!-- Required css  -->
		 <meta      name='viewport'      content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' />

		<link rel="stylesheet" href="css.css" >
		<link rel="stylesheet" href="mobile_css.css" >
		<script src="master_js.js"></script>


		<style>
.full_page_alert {position: fixed;
    width: -webkit-fill-available;
    height: -webkit-fill-available;
    background: white;
    top: 0;
    z-index: 9999999;
    padding: 63px;
    text-align: center;}

</style>



<?php
// Query the database for the card using the same card_id variable
$query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE card_id="'.$card_id.'" ');

if(mysqli_num_rows($query) == 0){
	// Card not found, redirect to homepage
	echo '<meta http-equiv="refresh" content="5;URL=../index.php">';
}else {
	$row = mysqli_fetch_array($query);
}

if(isset($row) && $row && strlen($row['f_user_email'] ?? '') < 3){
// check if more then 1 year

if(isset($row) && $row && $row['d_card_status']=="Active" && $row['d_payment_status']=="Success"){
			// Check validity using the new validity_date field
			$validity_date = strtotime($row['validity_date']);
			$today_date = strtotime($date);

			if($validity_date && $today_date > $validity_date && isset($row) && $row && $row['complimentary_enabled']=="No"){
				mysqli_query($connect,'UPDATE digi_card SET d_payment_status="Pending", d_card_status="Inactive" WHERE id="'.$row['id'].'"');
			}else {
				mysqli_query($connect,'UPDATE digi_card SET d_payment_status="Success", d_card_status="Active" WHERE id="'.$row['id'].'"');
			}
}
    // Enforce 7-day trial window for unpaid cards
    if (isset($row) && $row && $row['d_payment_status'] == "Created") {
        $today_ts = strtotime($date);
        // Trial ends 7 days after uploaded_date (complimentary bypasses)
        $trial_end_ts = strtotime($row['uploaded_date'] . ' +7 days');
        if ($row['complimentary_enabled'] == "No" && $today_ts > $trial_end_ts) {
            // Trial expired -> Inactive to block external access
            mysqli_query($connect, 'UPDATE digi_card SET d_card_status="Inactive" WHERE id="' . $row['id'] . '"');
        } else {
            // Within trial -> ensure Active
            if ($row['d_card_status'] != 'Active') {
                mysqli_query($connect, 'UPDATE digi_card SET d_card_status="Active" WHERE id="' . $row['id'] . '"');
            }
        }
    }


}

	// check if trial avtive





    if(isset($row) && $row && $row['d_card_status']=="Inactive"){

    echo '<div class="full_page_alert">
    <style>
        body {
            font-family: "Roboto", sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f9f9f9;
            margin: 0;
        }
        .full_page_alert {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            width: 100%;
        }
        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 90%;
            max-width: 500px;
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .alert-box {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px 30px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 700;
            font-size: 18px;
            text-align: center;
        }
        .message-box {
            font-size: 16px;
            margin-bottom: 30px;
            color: #555;
            text-align: center;
        }
        .button-box {
            background-color: #4caf50;
            color: #fff;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-size: 18px;
            cursor: pointer;
            margin-bottom: 30px;
            text-decoration: none;
            text-align: center;
        }
        .button-box:hover {
            background-color: #45a049;
        }
        .help-box {
            text-align: center;
            font-size: 16px;
            color: #333;
        }
        .whatsapp-icon {
            width: 50px;
            margin-top: 15px;
        }
    </style>
    <div class="container">
        <div class="alert-box">Miniwebsite is Deactivated.</div>
        <div class="message-box">Your 7 day trial is expired. Pay now to upgrade your plan.</div>
        <a href="https://'.$_SERVER['HTTP_HOST'].'/pay?id='.$row['id'].'" class="button-box">Pay Now</a>
        <div class="help-box">
            Need Help?<br>
            Contact us on our customer support number<br><br>
            <a href="https://wa.me/9429693574?text=Hi%2C%20I%20am%20interested%20to%20take%20franchise%20of%20miniwebsite.in%2C%20Please%20provide%20more%20details." target="_blank" style="display: inline-block; margin-left: 10px; vertical-align: middle;">
                <i class="fa fa-whatsapp" style="font-size: 24px; color: #25D366;"></i>
            </a>
        </div>
    </div>
</div>';
	
	return;
}


?>
<link rel="stylesheet" href="<?php if(!empty($row['d_css'])){echo 'panel/'.$row['d_css'];}else {echo 'panel/card_css1.css';} ?>" >

<script>

$(document).ready(function(){
	$('.mobile_home').on('click',function(){
		$('#header').toggleClass('add_height');

	})
})



</script>
<!----------------------copy from here ------------------------->


	<div class="card" id="home" >

	<?php
//view counter

			$query_views=mysqli_query($connect,'SELECT * FROM views WHERE ip="'.$_SERVER['REMOTE_ADDR'].'" AND card_id="'.(isset($row) && $row ? $row['id'] : '').'"');
		// count views
			$query_views_count=mysqli_query($connect,'SELECT * FROM views WHERE card_id="'.(isset($row) && $row ? $row['id'] : '').'"');
			// count views
			echo '<div class="view_counter"><i class="fa fa-eye"></i> <br>'.mysqli_num_rows($query_views_count).'</div>';
			if(mysqli_num_rows($query_views) >> 0){}
			else {
				$insert_view=mysqli_query($connect,'INSERT INTO views (ip,uploaded_date,card_id) VALUES ("'.$_SERVER['REMOTE_ADDR'].'","'.$date.'","'.(isset($row) && $row ? $row['id'] : '').'")');
			}
// view counter
			?>

			<div class="card_content"><img src="<?php if(isset($row) && $row && !empty($row['d_logo'])){echo 'data:image/*;base64,'.base64_encode($row['d_logo']);} ?>" alt="Logo"></div>
			<div class="card_content2">
				<h2><?php if(isset($row) && $row && !empty($row['d_comp_name'])){echo $row['d_comp_name'];} ?></h2>
				<p><?php if(isset($row) && $row && !empty($row['d_f_name'])){echo $row['d_f_name'].' '.$row['d_l_name'];} ?></p>
				<p><?php if(isset($row) && $row && !empty($row['d_position'])){echo $row['d_position'];} ?></p>

			</div>
			<div class="dis_flex">
				<?php if(isset($row) && $row && !empty($row['d_contact'])){echo '<a href="tel:+91'.$row['d_contact'].'" target="_blank"><div class="link_btn"><i class="fa fa-phone"></i> Call</div></a>';} ?>
				<?php if(isset($row) && $row && !empty($row['d_whatsapp'])){echo '<a href="https://api.whatsapp.com/send?phone=91'.str_replace('+91','',$row['d_whatsapp']).'&text=Hi, '.$row['d_comp_name'].'" target="_blank"><div class="link_btn"><i class="fa fa-whatsapp"></i> WhatsApp</div></a>';} ?>



				<?php if(isset($row) && $row && !empty($row['d_location'])){echo '<a href="'.$row['d_location'].'" target="_blank"><div class="link_btn"><i class="fa fa-map-marker"></i> Direction</div></a>';} ?>
				<?php if(isset($row) && $row && !empty($row['d_email'])){echo '<a href="Mailto:'.$row['d_email'].'" target="_blank"><div class="link_btn"><i class="fa fa-envelope"></i> Mail</div></a>';} ?>
				<?php if(isset($row) && $row && !empty($row['d_website'])){echo '<a href="https://'.$row['d_website'].'" target="_blank"><div class="link_btn"><i class="fa fa-globe"></i> Website</div></a>';} ?>

			</div>

			<div class="contact_details">
				<?php if(!empty($row['d_contact'])){echo '<div class="contact_d"><i class="fa fa-phone"></i><p>'.$row['d_contact'].'</p></div>';} ?>
				<?php if(!empty($row['d_contact2'])){echo '<div class="contact_d"><i class="fa fa-phone"></i><p>'.$row['d_contact2'].'</p></div>';} ?>
				<?php if(!empty($row['d_email'])){echo '<div class="contact_d"><i class="fa fa-envelope"></i><p>'.$row['d_email'].'</p></div>';} ?>
				<?php if(!empty($row['d_address'])){echo '<div class="contact_d"><i class="fa fa-map-marker" ></i><p>'.$row['d_address'].'</p></div>';} ?>

			</div>

			<div class="dis_flex">
				<div class="share_wtsp">
					<form action="https://api.whatsapp.com/send" id="wtsp_form" target="_blank"><input type="text"  name="phone" placeholder="WhatsApp Number with Country code	" value="+91"><input type="hidden" name="text" value="https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $row['card_id']; ?>"><div class="wtsp_share_btn" onclick="subForm()"><i class="fa fa-whatsapp"></i> Share On WhatsApp</div></form>

					<script>

					$(document).ready(function(){
						$('.wtsp_share_btn').on('click',function(){
							$('#wtsp_form').submit();
						})

					})
					</script>
				</div>
			</div>

			<div class="dis_flex">

			<?php if(!empty($row['d_contact'])){echo '<a href="contact_download.php?id='.$row['id'].'"><div class="big_btns">Save to Contacts <i class="fa fa-download"></i></div></a>';} ?>

				<div class="big_btns" id="share_box_pop">Share <i class="fa fa-share-alt"></i></div>

				<div class="share_box">


				<div class="close" id="close_sharer">&times;</div>
				<p>Share My Digital Miniwebsite </p>
                <div><i class="fa-brands fa-x-twitter"></i></div>
						<a href="https://api.whatsapp.com/send?text=https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $row['card_id']; ?>"><div class="shar_btns"><i class="fa fa-whatsapp" id="whatsapp2"  target="_blank"></i><p>WhatsApp</p></div></a>
					<a href="sms:?body=https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $row['card_id']; ?>" target="_blank"><div class="shar_btns"><i class="fa fa-comment" ></i><p>SMS</p></div></a>

					<a href="https://www.facebook.com/sharer/sharer.php?u=https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $row['card_id']; ?>" target="_blank"><div class="shar_btns"><i class="fa fa-facebook" ></i><p>Facebook</p></div></a>
					<a href="https://twitter.com/intent/tweet?text=https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $row['card_id']; ?>" target="_blank"><div class="shar_btns"><i class="fa fa-twitter"></i><p>Twitter</p></div></a>
					<a href="" target="_blank"><div class="shar_btns"><i class="fa fa-instagram"></i><p>Instagram</p></div></a>
					<a href="https://www.linkedin.com/cws/share?url=https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $row['card_id']; ?>" target="_blank"><div class="shar_btns"><i class="fa fa-linkedin"></i><p>Linkedin</p></div></a>
				</div>

				<script>
					$(document).ready(function(){
						$('#close_sharer,#share_box_pop').on('click',function(){
							$('.share_box').slideToggle();
						});
					})


				</script>

			</div>
			<div class="dis_flex">

				<?php if(!empty($row['d_fb'])){echo '<a href="'.$row['d_fb'].'" target="_blank"><div class="social_med" ><i class="fa fa-facebook"></i></div></a>';} ?>
				<?php if(!empty($row['d_youtube'])){echo '<a href="'.$row['d_youtube'].'" target="_blank"><div class="social_med"><i class="fa fa-youtube"></i></div></a>';} ?>
				<?php if(!empty($row['d_twitter'])){echo '<a href="'.$row['d_twitter'].'" target="_blank"><div class="social_med"><i class="fa fa-twitter"></i></div></a>';} ?>
				<?php if(!empty($row['d_instagram'])){echo '<a href="'.$row['d_instagram'].'" target="_blank"><div class="social_med"><i class="fa fa-instagram"></i></div></a>';} ?>
				<?php if(!empty($row['d_linkedin'])){echo '<a href="'.$row['d_linkedin'].'" target="_blank"><div class="social_med"><i class="fa fa-linkedin"></i></div></a>';} ?>
				<?php if(!empty($row['d_pinterest'])){echo '<a href="'.$row['d_pinterest'].'" target="_blank"><div class="social_med"><i class="fa fa-pinterest"></i></div></a>';} ?>
			</div>




	</div>

	<div class="card2">

	<h3>Scan QR Code to download the contact details</h3>
	<div class="qr_code_container" id="qrContainer" style="width: 100%; box-sizing: border-box;">
		<?php
		// Create the QR code URL with clean URL format
		$qrCodeUrl = "https://" . $_SERVER['HTTP_HOST'] . "/" . (isset($row) && $row ? $row['card_id'] : '');
		$encodedUrl = urlencode($qrCodeUrl);
		// Use a more reliable QR code service - increased size for better visibility and scaling
		$qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=" . $encodedUrl;
		// Get business name and person name for display
		$businessName = isset($row) && $row && !empty($row['d_comp_name']) ? htmlspecialchars($row['d_comp_name']) : '';
		$personName = isset($row) && $row ? trim(htmlspecialchars(($row['d_f_name'] ?? '') . ' ' . ($row['d_l_name'] ?? ''))) : '';
		$websiteUrl = "www.miniwebsite.in";
		?>
		<div class="qr_card_wrapper" style="text-align: center; width: 100%; max-width: 100%; box-sizing: border-box; overflow: visible; position: relative;">
			<!-- Display simple QR code image on page -->
			<img id="qrDisplayImage" src="<?php echo $qrImageUrl; ?>" alt="QR Code" style="max-width: 200px; height: auto; margin: 0 auto; display: block;">
			<!-- Hidden canvas for download (with full design) -->
			<canvas id="qrCanvas" style="display: none;"></canvas>
		</div>

		<div class="download-btn-wrapper">
			<button id="downloadQrBtn" class="qr_download_btn" style="cursor: pointer; border: none;">
				<i class="fa fa-download"></i> Download QR Code
			</button>
		</div>
	</div>

	</div>


	<script>
	document.addEventListener('DOMContentLoaded', function() {
		const canvas = document.getElementById('qrCanvas');
		const ctx = canvas.getContext('2d');
		
		// Data from PHP
		const backgroundImageUrl = 'assets/images/Miniwebsite_QR.png';
		const qrImageUrl = '<?php echo $qrImageUrl; ?>';
		const businessName = '<?php echo $businessName; ?>';
		const personName = '<?php echo $personName; ?>';
		const websiteUrl = '<?php echo $websiteUrl; ?>';
		
		// Wait for Roboto Condensed font to load
		if (document.fonts && document.fonts.check) {
			document.fonts.load('bold 16px "Roboto Condensed"').then(function() {
				initCanvas();
			}).catch(function() {
				// If font loading fails, proceed anyway
				initCanvas();
			});
		} else {
			// Fallback if font loading API not available
			setTimeout(initCanvas, 500);
		}
		
		// Settings object to store all adjustable values
		let settings = {
			businessName: { font: 0.08, x: 0, y: 0.12 },
			personName: { font: 0.04, x: 0, y: 60 },
			scanText: { font: 0.080, x: 0, y: 200 },
			accessText: { font: 0.04, x: 0, y: 120 },
			website: { font: 0.04, x: 0.05, y: 0.015 },
			qr: { x: 8, y: -70, size: 0.555 }
		};
		
		// Function to update settings from input fields
		function updateSettingsFromInputs() {
			settings.businessName.font = parseFloat(document.getElementById('businessNameFont').value) || 0.08;
			settings.businessName.x = parseFloat(document.getElementById('businessNameX').value) || 0;
			settings.businessName.y = parseFloat(document.getElementById('businessNameY').value) || 0.12;
			
			settings.personName.font = parseFloat(document.getElementById('personNameFont').value) || 0.04;
			settings.personName.x = parseFloat(document.getElementById('personNameX').value) || 0;
			settings.personName.y = parseFloat(document.getElementById('personNameY').value) || 60;
			
			settings.scanText.font = parseFloat(document.getElementById('scanTextFont').value) || 0.080;
			settings.scanText.x = parseFloat(document.getElementById('scanTextX').value) || 0;
			settings.scanText.y = parseFloat(document.getElementById('scanTextY').value) || 200;
			
			settings.accessText.font = parseFloat(document.getElementById('accessTextFont').value) || 0.04;
			settings.accessText.x = parseFloat(document.getElementById('accessTextX').value) || 0;
			settings.accessText.y = parseFloat(document.getElementById('accessTextY').value) || 120;
			
			settings.website.font = parseFloat(document.getElementById('websiteFont').value) || 0.04;
			settings.website.x = parseFloat(document.getElementById('websiteX').value) || 0.05;
			settings.website.y = parseFloat(document.getElementById('websiteY').value) || 0.015;
			
			settings.qr.x = parseFloat(document.getElementById('qrX').value) || 8;
			settings.qr.y = parseFloat(document.getElementById('qrY').value) || -70;
			settings.qr.size = parseFloat(document.getElementById('qrSizePercent').value) || 0.555;
			
			// Redraw canvas with new settings immediately
			if (imagesLoaded >= totalImages) {
				drawCanvas();
			}
		}
		
		// Add event listeners to all input fields for real-time updates
		function setupSettingsListeners() {
			const inputs = ['businessNameFont', 'businessNameX', 'businessNameY',
							'personNameFont', 'personNameX', 'personNameY',
							'scanTextFont', 'scanTextX', 'scanTextY',
							'accessTextFont', 'accessTextX', 'accessTextY',
							'websiteFont', 'websiteX', 'websiteY',
							'qrX', 'qrY', 'qrSizePercent'];
			
			inputs.forEach(id => {
				const input = document.getElementById(id);
				if (input) {
					input.addEventListener('input', updateSettingsFromInputs);
					input.addEventListener('change', updateSettingsFromInputs);
				}
			});
		}
		
		// Variables to track image loading
		let imagesLoaded = 0;
		const totalImages = 2;
		let bgImage, qrImage;
		let canvasReady = false; // Track if canvas is ready for download
		
		function initCanvas() {
			// Load background image
			bgImage = new Image();
			bgImage.crossOrigin = 'anonymous';
			
			// Load QR code image
			qrImage = new Image();
			qrImage.crossOrigin = 'anonymous';
		
		function drawCanvas() {
			if (imagesLoaded < totalImages) return;
			
			// Canvas stays hidden - only used for download
			// Set canvas size to match background image
			canvas.width = bgImage.width;
			canvas.height = bgImage.height;
			
			// Draw background image
			ctx.drawImage(bgImage, 0, 0);
			
			// Calculate QR code position (center of canvas) - fit within the yellow border in background image
			// Adjust size to fill the yellow border area without covering the border itself
			const padding = 18; // Padding around QR code
			const qrSize = Math.min(canvas.width * 0.555, canvas.height * 0.555);
			const qrX = (canvas.width - qrSize) / 2 + 8;
			const qrY = (canvas.height - qrSize) / 2 - 70;
			
			// Draw white background for QR code with padding and rounded corners
			ctx.fillStyle = '#FFFFFF';
			const borderRadius = 12; // Rounded corner radius
			const bgX = qrX - padding;
			const bgY = qrY - padding;
			const bgWidth = qrSize + (padding * 2);
			const bgHeight = qrSize + (padding * 2);
			
			// Draw rounded rectangle for white background
			ctx.beginPath();
			ctx.moveTo(bgX + borderRadius, bgY);
			ctx.lineTo(bgX + bgWidth - borderRadius, bgY);
			ctx.quadraticCurveTo(bgX + bgWidth, bgY, bgX + bgWidth, bgY + borderRadius);
			ctx.lineTo(bgX + bgWidth, bgY + bgHeight - borderRadius);
			ctx.quadraticCurveTo(bgX + bgWidth, bgY + bgHeight, bgX + bgWidth - borderRadius, bgY + bgHeight);
			ctx.lineTo(bgX + borderRadius, bgY + bgHeight);
			ctx.quadraticCurveTo(bgX, bgY + bgHeight, bgX, bgY + bgHeight - borderRadius);
			ctx.lineTo(bgX, bgY + borderRadius);
			ctx.quadraticCurveTo(bgX, bgY, bgX + borderRadius, bgY);
			ctx.closePath();
			ctx.fill();
			
			// Draw QR code - fill the area inside the yellow border from background image
			ctx.drawImage(qrImage, qrX, qrY, qrSize, qrSize);
			
			// Draw business name at top (on blue background area)
			if (businessName) {
				ctx.fillStyle = '#FFFFFF';
				ctx.font = 'bold ' + Math.floor(canvas.width * 0.08) + 'px "Roboto Condensed"';
				ctx.textAlign = 'center';
				ctx.textBaseline = 'middle';
				ctx.fillText(businessName.toUpperCase(), canvas.width / 2, canvas.height * 0.12);
			}
			
			// Draw person name below QR code - shifted further down
			if (personName) {
				ctx.fillStyle = '#202023';
				ctx.font = 'bold ' + Math.floor(canvas.width * 0.04) + 'px "Roboto Condensed"';
				ctx.textAlign = 'center';
				ctx.textBaseline = 'top';
				ctx.fillText(personName.toUpperCase(), canvas.width / 2, qrY + qrSize + 60);
			}
			
			// Draw "SCAN TO VIEW AND SAVE!" text - shifted further down
			ctx.fillStyle = '#202023';
			ctx.font = 'bold ' + Math.floor(canvas.width * 0.070) + 'px "Roboto Condensed"  ';
			ctx.textAlign = 'center';
			ctx.textBaseline = 'top';
			const scanTextY = personName ? qrY + qrSize + 180 : qrY + qrSize + 160;
			ctx.fillText('SCAN TO VIEW AND SAVE!', canvas.width / 2, scanTextY);
			
			// Draw "Access our Miniwebsite & Contact Info" text - shifted further down
			ctx.fillStyle = '#202023';
			ctx.font = Math.floor(canvas.width * 0.03) + 'px "Roboto Condensed"';
			ctx.textAlign = 'center';
			ctx.textBaseline = 'top';
			ctx.fillText('Access our Miniwebsite & Contact Info', canvas.width / 2, scanTextY + 120);
			
			// Draw website URL at bottom (on blue background area)
			ctx.fillStyle = '#FFFFFF';
			ctx.font = Math.floor(canvas.width * 0.04) + 'px "Roboto Condensed"';
			ctx.textAlign = 'right';
			ctx.textBaseline = 'bottom';
			ctx.fillText(websiteUrl, canvas.width - (canvas.width * 0.05), canvas.height - (canvas.height * 0.015));
			
			// Mark canvas as ready for download
			canvasReady = true;
		}
		
			// Handle background image load
			bgImage.onload = function() {
				imagesLoaded++;
				drawCanvas();
			};
			
			// Handle QR code image load
			qrImage.onload = function() {
				imagesLoaded++;
				drawCanvas();
			};
			
			// Handle errors
			bgImage.onerror = function() {
				console.error('Failed to load background image');
			};
			
			qrImage.onerror = function() {
				console.error('Failed to load QR code image');
			};
			
			// Start loading images
			bgImage.src = backgroundImageUrl;
			qrImage.src = qrImageUrl;
		}
		
		// Download button handler
		document.getElementById('downloadQrBtn').addEventListener('click', function() {
			if (!canvasReady) {
				alert('Please wait, QR code is still being prepared for download...');
				return;
			}
			const link = document.createElement('a');
			link.download = 'QR_Code_<?php echo isset($row) && $row ? $row['card_id'] : 'card'; ?>.png';
			link.href = canvas.toDataURL('image/png');
			link.click();
		});
		
	});
	</script>


<!--------------about us --------------------------->

	<div class="card2" id="about_us">
		<h3>About Us</h3>
	<?php if(!empty($row['d_comp_est_date'])){echo '<p>'.$row['d_comp_est_date'].'</p>';} ?>
	<?php if(!empty($row['d_about_us'])){echo '<p>'.$row['d_about_us'].'</p>';} ?>



	</div>

<!------------shopping online-------------------------->


<?php
$product_count = 0;
$query_pricing = null;
$displayed_products = 0;
$use_old_table = false;
$old_products_data = null;

if(isset($row['id']) && !empty($row['id'])){
	// First try new card_product_pricing table
	$card_id_for_query = intval($row['id']);
	$query_pricing = mysqli_query($connect, 'SELECT * FROM card_product_pricing WHERE card_id="'.$card_id_for_query.'" ORDER BY display_order ASC, id ASC');
	
	if($query_pricing){
		$product_count = mysqli_num_rows($query_pricing);
	}
	
	// If no products in new table, check old products table (backward compatibility)
	if($product_count == 0){
		$query_old = mysqli_query($connect, 'SELECT * FROM products WHERE id="'.$card_id_for_query.'" LIMIT 1');
		if($query_old && mysqli_num_rows($query_old) > 0){
			$old_products_data = mysqli_fetch_array($query_old);
			$use_old_table = true;
			// Count products in old table
			for($x=1;$x<=20;$x++){
				if(!empty($old_products_data["pro_name$x"])){
					$product_count++;
				}
			}
		}
	}
}

// Display section if there are products
if($product_count > 0){ ?>
	<div class="card2" id="shop_online">
		<h3>Shop Online </h3><h3>From Our Store</h3>

		<?php
		if($use_old_table && $old_products_data){
			// Display from old products table
			for($x=1;$x<=20;$x++){
				if(!empty($old_products_data["pro_name$x"])){
					$displayed_products++;
					echo '<div class="order_box">';

					// Display image if available
					if(!empty($old_products_data["pro_img$x"])){
						echo '<img src="data:image/*;base64,'.base64_encode($old_products_data["pro_img$x"]).'" alt="Product">';
					} else {
						echo '<div style="width:100%; height:200px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; color:#999; border-radius:8px;"><i class="fa fa-image" style="font-size:48px;"></i></div>';
					}
					
					echo '<h2>'.htmlspecialchars($old_products_data["pro_name$x"]).'</h2>';
					
					if(!empty($old_products_data["pro_mrp$x"]) && floatval($old_products_data["pro_mrp$x"]) > 0){
						echo '<p><del>'.number_format($old_products_data["pro_mrp$x"], 2).' <i class="fa fa-rupee"></i></del></p>';
					}
					if(!empty($old_products_data["pro_price$x"]) && floatval($old_products_data["pro_price$x"]) > 0){
						echo '<h4>'.number_format($old_products_data["pro_price$x"], 2).' <i class="fa fa-rupee"></i></h4>';
					}

					// WhatsApp inquiry link
					if(!empty($row['d_whatsapp'])){
						$mrp_display = !empty($old_products_data["pro_mrp$x"]) && floatval($old_products_data["pro_mrp$x"]) > 0 ? number_format($old_products_data["pro_mrp$x"], 2) : (!empty($old_products_data["pro_price$x"]) && floatval($old_products_data["pro_price$x"]) > 0 ? number_format($old_products_data["pro_price$x"], 2) : 'N/A');
						$whatsapp_text = urlencode("Hello sir, I checked your products on your digital visiting card. I am interested in Product: ".$old_products_data["pro_name$x"].", Price: ".$mrp_display);
						echo "<a href='https://api.whatsapp.com/send?phone=91".str_replace("+91","",$row['d_whatsapp'])."&text=".$whatsapp_text."' target='_blank'><div class='btn_buy'>Inquire Now</div></a>";
					} else {
						if(!empty($row['d_contact'])){
							echo "<a href='tel:+91".str_replace("+91","",$row['d_contact'])."'><div class='btn_buy'>Contact Now</div></a>";
						} else {
							echo "<div class='btn_buy' style='opacity:0.6; cursor:default;'>Inquire Now</div>";
						}
					}

					echo '</div>';
				}
			}
		} else if($query_pricing) {
			// Display from new card_product_pricing table
			while($row3 = mysqli_fetch_array($query_pricing)){
				// Only require product_name, image is optional
				if(!empty($row3["product_name"])){
					$displayed_products++;
					echo '<div class="order_box">';

					// Display image if available, otherwise show placeholder
					if(!empty($row3["product_image"])){
						echo '<img src="data:image/*;base64,'.base64_encode($row3["product_image"]).'" alt="Product">';
					} else {
						// Show placeholder div if no image
						echo '<div style="width:100%; height:200px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; color:#999; border-radius:8px;"><i class="fa fa-image" style="font-size:48px;"></i></div>';
					}
					
					echo '<h2>'.htmlspecialchars($row3["product_name"]).'</h2>';
					
					if(!empty($row3["mrp"]) && floatval($row3["mrp"]) > 0){
						echo '<p><del>'.number_format($row3["mrp"], 2).' <i class="fa fa-rupee"></i></del></p>';
					}
					if(!empty($row3["selling_price"]) && floatval($row3["selling_price"]) > 0){
						echo '<h4>'.number_format($row3["selling_price"], 2).' <i class="fa fa-rupee"></i></h4>';
					}

					// WhatsApp inquiry link - only show if WhatsApp number exists
					if(!empty($row['d_whatsapp'])){
						$mrp_display = !empty($row3["mrp"]) && floatval($row3["mrp"]) > 0 ? number_format($row3["mrp"], 2) : (!empty($row3["selling_price"]) && floatval($row3["selling_price"]) > 0 ? number_format($row3["selling_price"], 2) : 'N/A');
						$whatsapp_text = urlencode("Hello sir, I checked your products on your digital visiting card. I am interested in Product: ".$row3["product_name"].", Price: ".$mrp_display);
						echo "<a href='https://api.whatsapp.com/send?phone=91".str_replace("+91","",$row['d_whatsapp'])."&text=".$whatsapp_text."' target='_blank'><div class='btn_buy'>Inquire Now</div></a>";
					} else {
						// Show contact button if no WhatsApp
						if(!empty($row['d_contact'])){
							echo "<a href='tel:+91".str_replace("+91","",$row['d_contact'])."'><div class='btn_buy'>Contact Now</div></a>";
						} else {
							echo "<div class='btn_buy' style='opacity:0.6; cursor:default;'>Inquire Now</div>";
						}
					}

					echo '</div>';
				}
			}
		}
		
		// If no products were displayed, hide the section
		if($displayed_products == 0){
			echo '<style>#shop_online { display: none; }</style>';
		}
		?>

	</div>
<?php } ?>




<!--------------youtube videos--------------------------->

<?php 	if(!empty($row["d_youtube1"]) || !empty($row["d_youtube2"]) || !empty($row["d_youtube3"]) || !empty($row["d_youtube4"]) || !empty($row["d_youtube5"])){ ?>
	<div class="card2" id="youtube_video">
		<h3>Youtube Videos</h3>


		<?php
		for($x=0;$x<=10;$x++){
			if(!empty($row["d_youtube$x"])){


				$array1=array('youtu.be/','watch?v=','&feature=youtu.be');
				$array2=array('www.youtube.com/embed/','embed/','');

				$youtubelink=str_replace($array1,$array2,$row["d_youtube$x"]);

				echo '<iframe src="'.$youtubelink.'" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
			}
		}

		?>


	</div>
<?php } ?>



<!----------product and services ----------------------->
<?php
$product_services_count = 0;
$query_products_services = null;
if(isset($row['id'])){
	$query_products_services=mysqli_query($connect,'SELECT * FROM card_products_services WHERE card_id="'.$row['id'].'" ORDER BY display_order ASC');
	$product_services_count = mysqli_num_rows($query_products_services);
}

	if($product_services_count > 0 && $query_products_services) { ?>

	<div class="card2" id="product_services">
		<h3>Products & Services</h3>


		<?php
		while($row2 = mysqli_fetch_array($query_products_services)){
			// Display if there's a product name or image
			if(!empty($row2["product_name"]) || !empty($row2["product_image"])){
				echo '<div class="product_s">';
				if(!empty($row2["product_name"])){
					echo '<p>'.$row2["product_name"].'</p>';
				}
				if(!empty($row2["product_image"])){
					echo '<img src="data:image/*;base64,'.base64_encode($row2["product_image"]).'" alt="Logo">';
				}
				echo '</div>';
			}
		}

		?>


	</div>

<?php } ?>



<!----------image gallery----------------------->
<style>
/* Override gallery styles to display full width and stack vertically */
#gallery .img_gall {
    display: block !important;
    width: 100% !important;
    margin: 10px 0 !important;
    vertical-align: top !important;
}
#gallery .img_gall img {
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    display: block !important;
    margin: 0 auto !important;
    border-radius: 8px;
}
</style>
<?php
$gallery_count = 0;
$query_gallery = null;
if(isset($row['id'])){
	$query_gallery=mysqli_query($connect,'SELECT * FROM card_image_gallery WHERE card_id="'.$row['id'].'" ORDER BY display_order ASC');
	$gallery_count = mysqli_num_rows($query_gallery);
}
	if($gallery_count > 0 && $query_gallery) { ?>


		<div class="card2" id="gallery">
		<h3>Image Gallery</h3>


		<?php
		while($row3 = mysqli_fetch_array($query_gallery)){
			if(!empty($row3["gallery_image"])){
				echo '<div class="img_gall">';
				echo '<img src="data:image/*;base64,'.base64_encode($row3["gallery_image"]).'" alt="Gallery Image">';
				echo '</div>';
			}
		}

		?>


	</div>

<?php } ?>



<!----------payment info----------------------->
<?php 	if(!empty($row["d_paytm"]) || !empty($row["d_account_no"]) ||!empty($row["d_qr_paytm"]) ||!empty($row["d_qr_phone_pay"]) ||!empty($row["d_qr_google_pay"]) || !empty($row["d_google_pay"]) || !empty($row["d_phone_pay"]) ){ ?>

	<div class="card2" id="payment">
		<h3>Payment Info</h3>


		<?php 	if(!empty($row["d_paytm"])){echo '<h2>Paytm</h2><p>'.$row['d_paytm'].'</p>';}	?>
		<?php 	if(!empty($row["d_google_pay"])){echo '<h2>Google Pay</h2><p>'.$row['d_google_pay'].'</p>';}?>
		<?php 	if(!empty($row["d_phone_pay"])){echo '<h2>PhonePe</h2><p>'.$row['d_phone_pay'].'</p>';}	?>

		<h3>Bank Account Details</h3>

		<?php 	if(!empty($row["d_ac_name"])){echo '<h2>Name:</h2><p>'.$row['d_ac_name'].'</p>';}	?>
		<?php 	if(!empty($row["d_account_no"])){echo '<h2>Account Number:</h2><p>'.$row['d_account_no'].'</p>';}?>
		<?php 	if(!empty($row["d_ifsc"])){echo '<h2>IFSC Code:</h2><p>'.$row['d_ifsc'].'</p>';	}?>
		<?php 	if(!empty($row["d_ac_type"])){echo '<h2>GST Number:</h2><p>'.$row['d_ac_type'].'</p>';	}?>
		<?php 	if(!empty($row["d_bank_name"])){echo '<h2>BANK Name:</h2><p>'.$row['d_bank_name'].'</p>';}	?>


		<?php if(!empty($row["d_qr_paytm"])){echo '<img src="data:image/*;base64,'.base64_encode($row["d_qr_paytm"]).'" alt="Paytm QR">';	}	?>
		<?php if(!empty($row["d_qr_google_pay"])){echo '<img src="data:image/*;base64,'.base64_encode($row["d_qr_google_pay"]).'" alt="Google Pay QR">';	}	?>
		<?php if(!empty($row["d_qr_phone_pay"])){echo '<img src="data:image/*;base64,'.base64_encode($row["d_qr_phone_pay"]).'" alt="PhonePe QR">';	}	?>



	</div>
	<?php } ?>


<!----------email to  info----------------------->
<div class="card2" id="enquery">
    <form action="#enquery" method="post" id="enquiryForm" onsubmit="return validateForm()">
        <h3>Contact Us</h3>

        <input type="text" name="c_name" id="c_name" placeholder="Enter Your Name" required 
               pattern="[A-Za-z\s]+" title="Please enter a valid name (letters and spaces only)">
               
        <input type="tel" name="c_contact" id="c_contact" maxlength="13" placeholder="Enter Your Mobile No" required 
               pattern="[0-9]{10,13}" title="Please enter a valid 10-13 digit phone number">
               
        <input type="email" name="c_email" id="c_email" placeholder="Enter Your Email Address"
               pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" title="Please enter a valid email address">
               
        <textarea name="c_msg" id="c_msg" placeholder="Enter your Message or Query" required
                  minlength="10" title="Please enter at least 10 characters"></textarea>
                  
        <input type="submit" value="Send!" name="email_to_client" id="submitBtn">
    </form>

<script>
function validateForm() {
    // Get form elements
    var name = document.getElementById('c_name');
    var contact = document.getElementById('c_contact');
    var email = document.getElementById('c_email');
    var message = document.getElementById('c_msg');
    var isValid = true;
    
    // Reset all error states
    name.classList.remove('error');
    contact.classList.remove('error');
    email.classList.remove('error');
    message.classList.remove('error');
    
    // Validate name (required, letters and spaces only)
    if(!name.value.trim() || !/^[A-Za-z\s]+$/.test(name.value.trim())) {
        name.classList.add('error');
        isValid = false;
    }
    
    // Validate contact (required, 10-13 digits)
    if(!contact.value.trim() || !/^[0-9]{10,13}$/.test(contact.value.trim())) {
        contact.classList.add('error');
        isValid = false;
    }
    
    // Validate email if provided
    if(email.value.trim() && !/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(email.value.trim())) {
        email.classList.add('error');
        isValid = false;
    }
    
    // Validate message (required, at least 10 chars)
    if(!message.value.trim() || message.value.trim().length < 10) {
        message.classList.add('error');
        isValid = false;
    }
    
    // Show alert if validation fails
    if(!isValid) {
        alert('Please fill in all required fields correctly');
    }
    
    return isValid;
}

// Add event listeners for real-time validation feedback
document.getElementById('c_name').addEventListener('blur', function() {
    if(!this.value.trim() || !/^[A-Za-z\s]+$/.test(this.value.trim())) {
        this.classList.add('error');
    } else {
        this.classList.remove('error');
    }
});

document.getElementById('c_contact').addEventListener('blur', function() {
    if(!this.value.trim() || !/^[0-9]{10,13}$/.test(this.value.trim())) {
        this.classList.add('error');
    } else {
        this.classList.remove('error');
    }
});

document.getElementById('c_email').addEventListener('blur', function() {
    if(this.value.trim() && !/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(this.value.trim())) {
        this.classList.add('error');
    } else {
        this.classList.remove('error');
    }
});

document.getElementById('c_msg').addEventListener('blur', function() {
    if(!this.value.trim() || this.value.trim().length < 10) {
        this.classList.add('error');
    } else {
        this.classList.remove('error');
    }
});
</script>

<style>
.error {
    border: 2px solid #ff0000 !important;
    background-color: #ffeeee !important;
}
</style>

<?php
if(isset($_POST['email_to_client'])){
    // Server-side validation
    $errors = [];
    
    // Validate and sanitize input - use trim to remove whitespace
    // Replace deprecated FILTER_SANITIZE_STRING with htmlspecialchars
    $name = htmlspecialchars(trim($_POST['c_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $contact = htmlspecialchars(trim($_POST['c_contact'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = filter_var(trim($_POST['c_email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $message = htmlspecialchars(trim($_POST['c_msg'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    // Name validation (required, letters and spaces only)
    if(empty($name) || !preg_match('/^[A-Za-z\s]+$/', $name)) {
        $errors[] = "Please enter a valid name (letters and spaces only).";
    }
    
    // Contact validation (required, 10-13 digits)
    if(empty($contact) || !preg_match('/^[0-9]{10,13}$/', $contact)) {
        $errors[] = "Please enter a valid 10-13 digit phone number.";
    }
    
    // Email validation if provided
    if(!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    // Message validation (required, at least 10 chars)
    if(empty($message) || strlen($message) < 10) {
        $errors[] = "Please enter a message with at least 10 characters.";
    }
    
    // If validation passes, send email
    if(empty($errors)) {
        // Use default email if not provided
        if(empty($email)) {
            $email = 'noreply@' . $_SERVER['HTTP_HOST'];
        }
        
        // Load email configuration if not already loaded
        if (!defined('SUPPORT_EMAIL') && file_exists(__DIR__ . '/common/email_config.php')) {
            require_once(__DIR__ . '/app/config/email.php');
        }

        // Define default values if constants are not defined
        if (!defined('SUPPORT_EMAIL')) {
            define('SUPPORT_EMAIL', 'support@miniwebsite.in');
        }

        if (!defined('ALL_EMAILS')) {
            define('ALL_EMAILS', 'allmails@miniwebsite.in');
        }

        // Original recipient (card owner's email)
        //$to = !empty($row['d_email']) ? $row['d_email'] : SUPPORT_EMAIL;
        $to = $row['d_email'];
        
        // Support email addresses
        $support_email = SUPPORT_EMAIL;
        $all_emails = ALL_EMAILS;

        // Subject with customer name and website info
        $subject = "Customer Inquiry from ".$_SERVER['HTTP_HOST']." - ".$name;

        // Create message content with styling
        $message_content = '';
        $message_content .= '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $message_content .= '<h2 style="color: #3498db; border-bottom: 1px solid #eee; padding-bottom: 10px;">New Customer Inquiry</h2>';
        $message_content .= '<p><strong>From:</strong> '.$name.'</p>';
        $message_content .= '<p><strong>Contact Number:</strong> '.$contact.'</p>';
        $message_content .= '<p><strong>Email:</strong> '.$email.'</p>';
        $message_content .= '<p><strong>Message:</strong><br>'.nl2br($message).'</p>';
        if (!empty($row['card_id'])) {
            $message_content .= '<p><strong>Miniwebsite ID:</strong> '.$row['card_id'].'</p>';
        }
        $message_content .= '<p><strong>Sent from:</strong> '.$_SERVER['HTTP_HOST'].'</p>';
        $message_content .= '<p style="color: #777; font-size: 12px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px;">';
        $message_content .= 'This email was sent from the contact form on '.$_SERVER['HTTP_HOST'].' at '.date('Y-m-d H:i:s').'</p>';
        $message_content .= '</div>';

        // Send the email using PHPMailer
        $email_sent = false;

        try {
            // Create a new PHPMailer instance
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = SMTP_AUTH;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            
            // Additional SMTP settings for better compatibility
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // FROM: Use the support email as the sender
            $mail->setFrom(SMTP_USERNAME, 'MiniWebsite Support');
            
            // TO: Send to the entered email or user's email
            $mail->addAddress($to);
            
            // Add Reply-To as the customer's email
            $mail->addReplyTo($email, $name);
            
            // Add CC to support_email as additional recipient
            $mail->addCC($support_email);
            
            // BCC: Add the all_emails address as BCC
            if (!empty($all_emails)) {
                $mail->addBCC($all_emails);
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message_content;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message_content));
            
            // Send the email
            if($mail->send()) {
                $email_sent = true;
            }
        } catch (Exception $e) {
            // Log the error
            error_log("PHPMailer error: " . $e->getMessage());
            $email_sent = false;
            
            // Try alternative method as backup
            try {
                // Set up sender information for our function
                $sender = [
                    'name' => $name,
                    'email' => $email
                ];
                
                // Set up additional recipients
                $additional_recipients = [
                    'cc' => $support_email,
                    'bcc' => $all_emails
                ];
                
                // Try our custom function as backup
                if(send_formatted_email($to, $subject, $message_content, $sender, $additional_recipients)) {
                    $email_sent = true;
                }
            } catch (Exception $e2) {
                error_log("Backup email method failed: " . $e2->getMessage());
            }
        }

        // Display appropriate message
        if($email_sent){
            echo '<div class="alert success">
                <h4 style="margin-bottom: 10px;">Message Sent Successfully!</h4>
                <p>We have received your inquiry.<br/>We will contact you soon</p>
            </div>';
        } else {
            echo '<div class="alert danger">
                <h4 style="margin-bottom: 10px;">Message Delivery Issue</h4>
                <p>There was an issue sending your message. Please try again later or contact us directly at '.$support_email.'</p>
            </div>';
            
            // Log key information for troubleshooting
            error_log("Failed to send contact form email. To: $to, From: $email, Subject: $subject");
        }
    } else {
        // Display validation errors
        echo '<div class="alert danger">';
        echo '<h4 style="margin-bottom: 10px;">Please correct the following errors:</h4>';
        echo '<ul style="margin-left: 20px;">';
        foreach($errors as $error) {
            echo '<li>' . $error . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
}
?>	<br>
		<br>



		<br>

		<a href="index.php"><div class="create_card_btn"><?php echo $_SERVER['HTTP_HOST'];?> || Create Your Miniwebsite Now || <?php echo date('Y');?></div></a>
	<style>
	.create_card_btn {
		         background: linear-gradient(45deg, black, black);
    color: white;
    width: auto;
    padding: 20px;
    border-radius: 2px;
    line-height: 0.8;
    margin: 11px auto;
    font-size: 9px;
    text-align: center;
	}



#svg_down{position: fixed;
    bottom: 0;
    z-index: -1;
    left: 0;}


	</style>



	<br>

	<br>

	<br>
	<br>
	<div class="menu_bottom">
		<div class="menu_container">
			<div class="menu_item" onclick="location.href='#home'"><i class="fa fa-home"></i> Home</div>
			<div class="menu_item" onclick="location.href='#about_us'"><i class="fa fa-briefcase"></i>About Us</div>
			<div class="menu_item" onclick="location.href='#product_services'"><i class="fa fa-ticket"></i>Product & Services</div>
			<div class="menu_item" onclick="location.href='#shop_online'"><i class="fa fa-archive"></i>Shop Online</div>
			<div class="menu_item" onclick="location.href='#gallery'"><i class="fa fa-image"></i>Gallery</div>
			<div class="menu_item" onclick="location.href='#youtube_video'"><i class="fa fa-video-camera"></i>Youtube Videos</div>
			<div class="menu_item" onclick="location.href='#payment'"><i class="fa fa-money"></i>Payment</div>
			<div class="menu_item" onclick="location.href='#enquery'"><i class="fa fa-comment"></i>Enquiry</div>
		</div>
	</div>



