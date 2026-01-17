<?php
session_start();

// Import PHPMailer classes at the top level
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Generate captcha in session if not already set
if (!isset($_SESSION['captcha'])) {
    $captcha = substr(str_shuffle("23456789ABCDEFGHJKLMNPQRSTUVWXYZ"), 0, 6);
    $_SESSION['captcha'] = $captcha;
}

$captcha_error = '';

require('header.php');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_mail'])) {
    $name     = htmlspecialchars(trim($_POST['name']));
    $email    = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $contact  = preg_match("/^\d{10}$/", $_POST['contact']) ? $_POST['contact'] : null;
    $address  = htmlspecialchars(trim($_POST['address']));
    $message  = htmlspecialchars(trim($_POST['message']));
    $captcha_input = strtoupper(trim($_POST['captcha']));

    if (!isset($_SESSION['captcha'])) {
        $captcha_error = "Captcha expired. Please try again.";
    } elseif ($captcha_input !== strtoupper($_SESSION['captcha'])) {
        $captcha_error = "Invalid captcha! Please try again.";
    } elseif (!$name || !$email || !$contact) {
        $captcha_error = "Please fill all required fields.";
    } else {
        // Include PHPMailer and email configuration
        require_once __DIR__ . '/vendor/autoload.php';
        require_once(__DIR__ . '/app/config/email.php');
        
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
            
            // Recipients
            $mail->setFrom(SMTP_USERNAME, 'MiniWebsite Support');
            $mail->addAddress('support@miniwebsite.in', 'Franchise Inquiry');
            $mail->addReplyTo($email, $name);
            
            // Add BCC to ALL_EMAILS if defined
            if (defined('ALL_EMAILS')) {
                $mail->addBCC(ALL_EMAILS);
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = "Franchise Enquiry from $name";
            $mail->Body = "
                <strong>Name:</strong> $name<br>
                <strong>Email:</strong> $email<br>
                <strong>Contact:</strong> $contact<br>
                <strong>Address:</strong> $address<br>
                <strong>Message:</strong><br>$message
            ";
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $mail->Body));
            
            // Send the email
            if($mail->send()) {
                // Display success message in a popup
                echo "
                <div id='successPopup' class='modal' style='display:block; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);'>
                    <div style='background-color:white; margin:15% auto; padding:20px; border:1px solid #888; width:80%; max-width:500px; border-radius:5px; text-align:center;'>
                        <div style='margin-bottom:15px;'>
                            <i class='fa fa-check-circle' style='font-size:48px; color:#28a745;'></i>
                        </div>
                        <h4 style='margin-bottom:15px; color:#155724;'>Enquiry Sent Successfully!</h4>
                        <p style='color:black;'>Thank you for your interest in our franchise. We will get back to you as soon as possible.</p>
                        <button onclick='document.getElementById(\"successPopup\").style.display=\"none\";' 
                            style='margin-top:15px; padding:8px 16px; background-color:#28a745; color:white; border:none; border-radius:4px; cursor:pointer;'>
                            Close
                        </button>
                    </div>
                </div>";
                
                // Clear form fields
                echo "<script>
                    document.getElementById('enquiryForm').reset();
                </script>";
                
                // Clear the captcha after successful submission
                unset($_SESSION['captcha']);
                
                // Generate new captcha
                $captcha = substr(str_shuffle('23456789ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 6);
                $_SESSION['captcha'] = $captcha;
                echo "<script>refreshCaptcha();</script>";
            } else {
                throw new Exception("Mailer Error: " . $mail->ErrorInfo);
            }
        } catch (Exception $e) {
            // Try alternative method if PHPMailer fails
            $success = false;
            
            try {
                // Fallback to PHP mail() function
                $to = "akhilesh.vis17@gmail.com";
                $subject = "Franchise Enquiry from $name";
                
                // Headers
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: " . SMTP_USERNAME . "\r\n";
                $headers .= "Reply-To: " . $email . "\r\n";
                
                $body = "
                    <strong>Name:</strong> $name<br>
                    <strong>Email:</strong> $email<br>
                    <strong>Contact:</strong> $contact<br>
                    <strong>Address:</strong> $address<br>
                    <strong>Message:</strong><br>$message
                ";
                
                if(mail($to, $subject, $body, $headers)) {
                    $success = true;
                }
            } catch (Exception $e2) {
                error_log("Backup email method failed: " . $e2->getMessage());
            }
            
            if($success) {
                // Display success message
                echo "
                <div id='successPopup' class='modal' style='display:block; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);'>
                    <div style='background-color:white; margin:15% auto; padding:20px; border:1px solid #888; width:80%; max-width:500px; border-radius:5px; text-align:center;'>
                        <div style='margin-bottom:15px;'>
                            <i class='fa fa-check-circle' style='font-size:48px; color:#28a745;'></i>
                        </div>
                        <h4 style='margin-bottom:15px; color:#155724;'>Enquiry Sent Successfully!</h4>
                        <p style='color:black;'>Thank you for your interest in our franchise. We will get back to you as soon as possible.</p>
                        <button onclick='document.getElementById(\"successPopup\").style.display=\"none\";' 
                            style='margin-top:15px; padding:8px 16px; background-color:#28a745; color:white; border:none; border-radius:4px; cursor:pointer;'>
                            Close
                        </button>
                    </div>
                </div>";
                
                // Clear form fields
                echo "<script>
                    document.getElementById('enquiryForm').reset();
                </script>";
                
                // Clear the captcha after successful submission
                unset($_SESSION['captcha']);
                
                // Generate new captcha
                $captcha = substr(str_shuffle('23456789ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 6);
                $_SESSION['captcha'] = $captcha;
                echo "<script>refreshCaptcha();</script>";
            } else {
                echo "<div class='alert alert-danger'>Please try again or contact us directly at support@miniwebsite.in</div>";
                error_log("Failed to send franchise inquiry email. From: $email, Subject: $subject");
            }
        }
    }
}
?>


    <main class="franchasee">
        <section class="banner">
            <div class="container">
                <div class="banner-wrap">
                    <div class="banner-content">
                        <h3>Start Earning with</h3>
                        <h1 class="heading">MiniWebsite.in</h1>
                        <p>"Every Business needs an online presence, and we make the easier than ever. As a miniwebsite.in franchisee you'll be part of a rapidly growing network that is transforming how a small businesses and enterprenures establish their digital footprints."</p>
                        <?php
                        $phoneNumber = '9429693574';
                        $message = urlencode('Hi, I am interested to take franchise of miniwebsite.in, Please provide more details.');

                        $whatsappLink = "https://wa.me/$phoneNumber?text=$message";
                        ?>
                        <button class="btn btn-primary" onclick="window.location.href='<?php echo $whatsappLink; ?>'">Contact Us</button>

                    </div>
                    <div class="banner-form blue-bg">
                        <h2 class="heading">Franchise Enquiry</h2>
                        <small>Fill out the form, and we’ll respond asap.</small>
                        <form action="" method="post" autocomplete="off" id="enquiryForm">
                            <div class="form-group">
                                <input type="text" name="name" placeholder="Name*" class="form-control" required pattern="[A-Za-z\s]+" title="Only letters and spaces allowed">
                            </div>
                            <div class="form-group">
                                <input type="email" name="email" placeholder="Email Id*" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <input type="tel" name="contact" placeholder="Contact*" class="form-control" required pattern="\d{10}" title="Enter 10-digit mobile number">
                            </div>
                            <div class="form-group">
                                <input type="text" name="address" placeholder="Your Address" class="form-control">
                            </div>
                            <div class="form-group">
                                <textarea name="message" placeholder="Any Message (Optional)" class="form-control"></textarea>
                            </div>
                            <div class="form-group">
                                <div class="captcha-wrapper">
                                    <input type="text" name="captcha" id="captcha-input" class="form-control" required
                                           placeholder="Enter the code shown below" style="margin-bottom: 10px;">
                                    <div class="captcha-container">
                                        <img src="generate_captcha.php" id="captcha-image" alt="CAPTCHA">
                                        <button type="button" class="refresh-captcha" onclick="refreshCaptcha()">
                                          <i class="fa fa-refresh"></i> Refresh
                                        </button>
                                    </div>
                                    <?php if (!empty($captcha_error)): ?>
                                        <div class="error-message"><?php echo $captcha_error; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <script>
                            function refreshCaptcha() {
                                const img = document.getElementById('captcha-image');
                                img.src = 'generate_captcha.php?' + new Date().getTime();
                            }

                            // Refresh captcha on page load if there was an error
                            <?php if (!empty($captcha_error)): ?>
                            document.addEventListener('DOMContentLoaded', refreshCaptcha);
                            <?php endif; ?>
                            </script>
                            <input class="btn btn-primary" name="send_mail" type="submit" value="SEND">
                        </form>

                    </div>





                </div>
            </div>
        </section>


        <section class="why-miniweb">
            <div class="container">
                <h2 class="heading"><span class="theme-color">Mini Website Franchise</span> - Advantages</h2>
                <div class="features-container">
                    <div class="feature-box">
                        <img class="img-fluid" src="assets/images/05AWhyMiniwebsite/01_EasyStart.gif" alt="Easy To Start">
                        <span class="feature-text">Easy To Start</span>
                    </div>
                    <div class="feature-box">
                        <img class="img-fluid" src="assets/images/05AWhyMiniwebsite/02_Hassle.gif"
                            alt="Hassle Free Business Model">
                        <span class="feature-text">Hassle Free Business Model</span>
                    </div>
                    <div class="feature-box feature-box3">
                        <img class="img-fluid" src="assets/images/05AWhyMiniwebsite/03_Can Start.gif" alt="Can Start In Just 2 Days">
                        <span class="feature-text">Can Start In Just 2 Days</span>
                    </div>
                    <div class="feature-box feature-box4">
                        <img class="img-fluid" src="assets/images/05AWhyMiniwebsite/04_Lesscompetitin.gif" alt="Very Less Competition">
                        <span class="feature-text">Very Less Competition</span>
                    </div>
                    <div class="feature-box">
                        <img class="img-fluid" src="assets/images/05AWhyMiniwebsite/05_Affordable.gif" alt="Huge Profit Margins">
                        <span class="feature-text">Huge Profit Margins</span>
                    </div>
                    <div class="feature-box">
                        <img class="img-fluid" src="assets/images/05AWhyMiniwebsite/06_Nominal.gif"
                            alt="Very Nominal Investment">
                        <span class="feature-text">Very Nominal Investment</span>
                    </div>
                    <div class="feature-box feature-box3">
                        <img class="img-fluid" src="assets/images/05AWhyMiniwebsite/07_High.gif"
                            alt="High Demand Business">
                        <span class="feature-text">High Demand Business</span>
                    </div>
                    <div class="feature-box feature-box4">
                        <img class="img-fluid" src="assets/images/05AWhyMiniwebsite/08_Notechnicalskills.gif"
                            alt="No Technical Skills Required">
                        <span class="feature-text">No Technical Skills Required</span>
                    </div>
                    <div class="feature-box">
                        <img class="img-fluid" src="assets/images/05AWhyMiniwebsite/09_Reliable_support.gif"
                            alt="Reliable Support System">
                        <span class="feature-text">Reliable Support System</span>
                    </div>
                </div>
            </div>
        </section>


        <section class="textimonial">
            <div class="container">
                <div class="textimonial-wrap">
                    <h2 class="heading blue-bg">How to Become - <span class="theme-color">Our Franchise Partner</span></h2>
                    <ul>
                        <li><img class="img-fluid" src="assets/images/03Howtobecome/icon.png" alt=""></li>
                        <li><img class="img-fluid" src="assets/images/03Howtobecome/icon.png" alt=""></li>
                        <li><img class="img-fluid" src="assets/images/03Howtobecome/icon.png" alt=""></li>
                    </ul>
                    <div class="testimonial-carousel">

                        <div class="testimonial-card">
                            <img class="img-fluid" src="assets/images/03Howtobecome/info.gif" alt="Fill the Enquiry Form or Call Us">
                            <h3>Fill the Enquiry Form or Call Us </h3>
                        </div>

                        <div class="testimonial-card">
                            <img class="img-fluid" src="assets/images/03Howtobecome/assign.gif" alt="Do Simple Formalities ">
                            <h3>Do Simple Formalities </h3>

                        </div>

                        <div class="testimonial-card">
                            <img class="img-fluid" src="assets/images/03Howtobecome/strategic-alliance.gif" alt="Become Our Franchise Partner">
                            <h3>Become Our Franchise Partner</h3>
                        </div>
                    </div>
                </div>
            </div>
        </section>



        <section class="faq">
            <div class="container">
                <h2 class="heading">Frequently Asked Questions (FAQ)</h2>
                <?php
                // Include frontend FAQ helper functions
                require_once('frontend_faq_helper.php');
                
                // Display FAQs for franchise page
                echo displayFrontendFAQs('franchise', 'faqAccordion');
                ?>

                 
            </div>
        </section>

    </main>

    <?php
require('footer.php');

?>



