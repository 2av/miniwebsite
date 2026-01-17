
<button onclick="scrollToTop()" id="backToTop">
<img src="assets/images/arrow.png" class="img-fluid" width="30" alt="" />
</button>
<div class="fixd-said">
<!-- Call Button -->
<a href="https://wa.me/919429693574" target="_blank" id="whatsapp-btn">
    <img src="assets/images/whatsapp.png" class="img-fluid" width="30" alt="WhatsApp" />
</a>

<!-- Call Button -->
<a href="tel:+91-9429693574" id="call-btn">
    <img src="assets/images/phone.png" class="img-fluid" width="30" alt="Call" />
</a>
</div>
<?php
require_once(__DIR__ . '/app/config/email.php');
    ?>

<footer class="footer">
        <section class="main-foot">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <div class="contact-section">
                        <img class="img-fluid footer-logo" src="assets/images/09Footer/Miniwebsite logo_1800x.png" alt="miniwebsite.in">
                        

                        <p class="contact-info">
                            <a href="tel:+91-9429693574"><img class="img-fluid" src="assets/images/09Footer/foot-call.png" alt=""> +91-9429693574</a>
                        </p>
                        <p class="contact-info">
                            <a href="mailto:support@miniwebsite.in"><img class="img-fluid" src="assets/images/09Footer/foot-mail.png" alt=""> support@miniwebsite.in</a>
                        </p>
                        <p class="contact-info">
                            <img class="img-fluid" src="assets/images/09Footer/foot-map.png" alt=""> 
                            plot no 535 Sanjay colony sector 23 near madrasi mandir, Faridabad - 121005, Haryana, India
                        </p>
                    </div>
                    </div>

                    <div class="col-md-6">
                        <div class="contact-section contact-section-form">
                        <h4 class="heading"><strong>Post a comment</strong></h4>
                        <p>We are always available for you. Required fields are marked *</p>

                        <form class="contact-form" method="POST" action="" id="commentForm" onsubmit="return validateCommentForm()">
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <input class="form-control" type="text" name="first_name" id="first_name" placeholder="First Name*" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <input class="form-control" type="text" name="last_name" id="last_name" placeholder="Last Name*" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <input class="form-control" type="email" name="email" id="email" placeholder="Email*" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <input class="form-control" type="text" name="contact" id="contact" placeholder="Contact" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <textarea class="form-control" rows="3" name="message" id="message" placeholder="Any Message"></textarea>
                            </div>
                            <div class="text-center">
                                <button type="submit" name="submit_comment" class="btn btn-primary">SEND US MESSAGE</button>
                            </div>
                            
                            <script>
                            function validateCommentForm() {
                                // Get form elements
                                var firstName = document.getElementById('first_name');
                                var lastName = document.getElementById('last_name');
                                var email = document.getElementById('email');
                                var contact = document.getElementById('contact');
                                var message = document.getElementById('message');
                                var isValid = true;
                                
                                // Reset all error states
                                firstName.classList.remove('is-invalid');
                                lastName.classList.remove('is-invalid');
                                email.classList.remove('is-invalid');
                                contact.classList.remove('is-invalid');
                                message.classList.remove('is-invalid');
                                
                                // Validate first name (required, letters and spaces only)
                                if(!firstName.value.trim() || !/^[A-Za-z\s]+$/.test(firstName.value.trim())) {
                                    firstName.classList.add('is-invalid');
                                    isValid = false;
                                }
                                
                                // Validate last name (required, letters and spaces only)
                                if(!lastName.value.trim() || !/^[A-Za-z\s]+$/.test(lastName.value.trim())) {
                                    lastName.classList.add('is-invalid');
                                    isValid = false;
                                }
                                
                                // Validate email (required, valid format)
                                if(!email.value.trim() || !/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(email.value.trim())) {
                                    email.classList.add('is-invalid');
                                    isValid = false;
                                }
                                
                                // Validate contact (required, 10-13 digits)
                                if(!contact.value.trim() || !/^[0-9]{10,13}$/.test(contact.value.trim())) {
                                    contact.classList.add('is-invalid');
                                    isValid = false;
                                }
                                
                                // If validation fails, show alert
                                if(!isValid) {
                                    alert('Please fill in all required fields correctly');
                                }
                                
                                return isValid;
                            }
                            
                            // Add event listeners for real-time validation
                            document.getElementById('first_name').addEventListener('blur', function() {
                                if(!this.value.trim() || !/^[A-Za-z\s]+$/.test(this.value.trim())) {
                                    this.classList.add('is-invalid');
                                } else {
                                    this.classList.remove('is-invalid');
                                }
                            });
                            
                            document.getElementById('last_name').addEventListener('blur', function() {
                                if(!this.value.trim() || !/^[A-Za-z\s]+$/.test(this.value.trim())) {
                                    this.classList.add('is-invalid');
                                } else {
                                    this.classList.remove('is-invalid');
                                }
                            });
                            
                            document.getElementById('email').addEventListener('blur', function() {
                                if(!this.value.trim() || !/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(this.value.trim())) {
                                    this.classList.add('is-invalid');
                                } else {
                                    this.classList.remove('is-invalid');
                                }
                            });
                            
                            document.getElementById('contact').addEventListener('blur', function() {
                                if(!this.value.trim() || !/^[0-9]{10,13}$/.test(this.value.trim())) {
                                    this.classList.add('is-invalid');
                                } else {
                                    this.classList.remove('is-invalid');
                                }
                            });
                            </script>
                            
                            <style>
                            .is-invalid {
                                border-color: #dc3545 !important;
                                background-color: #fff8f8 !important;
                            }
                            </style>
                            
                            <?php
                            if(isset($_POST['submit_comment'])) {
                                // Collect form data
                                $first_name = htmlspecialchars(trim($_POST['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $last_name = htmlspecialchars(trim($_POST['last_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
                                $contact = htmlspecialchars(trim($_POST['contact'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $message = htmlspecialchars(trim($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8');
                                
                                // Server-side validation
                                $errors = [];
                                
                                // Validate first name
                                if(empty($first_name) || !preg_match('/^[A-Za-z\s]+$/', $first_name)) {
                                    $errors[] = "Please enter a valid first name (letters and spaces only).";
                                }
                                
                                // Validate last name
                                if(empty($last_name) || !preg_match('/^[A-Za-z\s]+$/', $last_name)) {
                                    $errors[] = "Please enter a valid last name (letters and spaces only).";
                                }
                                
                                // Validate email
                                if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    $errors[] = "Please enter a valid email address.";
                                }
                                
                                // Validate contact
                                if(empty($contact) || !preg_match('/^[0-9]{10,13}$/', $contact)) {
                                    $errors[] = "Please enter a valid contact number (10-13 digits).";
                                }
                                
                                // If validation passes, send email
                                if(empty($errors)) {
                                    try {
                                        // Make sure PHPMailer is available
                                        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                                            require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
                                            require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
                                            require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
                                        }
                                        
                                        // Create a new PHPMailer instance
                                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                                        
                                        // Server settings
                                        $mail->isSMTP();
                                        $mail->Host       = SMTP_HOST;
                                        $mail->SMTPAuth   = SMTP_AUTH;
                                        $mail->Username   = SMTP_USERNAME;
                                        $mail->Password   = SMTP_PASSWORD;
                                        $mail->SMTPSecure = SMTP_SECURE;
                                        $mail->Port       = SMTP_PORT;
                                        $mail->CharSet    = 'UTF-8';
                                        
                                        // Enable debug output for troubleshooting
                                        $mail->SMTPDebug = 0; // Set to 2 for detailed debug output
                                        
                                        // Additional SMTP settings for better compatibility
                                        $mail->SMTPOptions = array(
                                            'ssl' => array(
                                                'verify_peer' => false,
                                                'verify_peer_name' => false,
                                                'allow_self_signed' => true
                                            )
                                        );
                                        
                                        // Recipients
                                        $mail->setFrom(SMTP_USERNAME, 'MiniWebsite Post Comment Form');
                                        
                                        // Primary recipient
                                        $mail->addAddress(SUPPORT_EMAIL);
                                        
                                        // Always BCC to allmails@miniwebsite.in
                                        $mail->addBCC('allmails@miniwebsite.in');
                                        
                                        // Add Reply-To as the customer's email
                                        $mail->addReplyTo($email, $first_name . ' ' . $last_name);
                                        
                                        // Content
                                        $mail->isHTML(true);
                                        $mail->Subject = "New Comment from Website: " . $first_name . " " . $last_name;
                                        
                                        // Email content with HTML formatting
                                        $mail->Body = '
                                        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                                            <h2 style="color: #3498db; border-bottom: 1px solid #eee; padding-bottom: 10px;">New Website Comment</h2>
                                            <p><strong>Name:</strong> ' . $first_name . ' ' . $last_name . '</p>
                                            <p><strong>Email:</strong> ' . $email . '</p>
                                            <p><strong>Contact:</strong> ' . $contact . '</p>
                                            <p><strong>Message:</strong><br>' . nl2br($message) . '</p>
                                            <p style="color: #777; font-size: 12px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px;">
                                            This email was sent from the comment form on ' . $_SERVER['HTTP_HOST'] . ' at ' . date('Y-m-d H:i:s') . '</p>
                                        </div>';
                                        
                                        $mail->AltBody = "Name: $first_name $last_name\nEmail: $email\nContact: $contact\nMessage: $message";
                                        
                                        // Send email
                                        if($mail->send()) {
                                            // Display a popup success message instead of inline alert
                                            echo '
                                            <div id="successPopup" class="modal" style="display:block; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);">
                                                <div style="background-color:white; margin:15% auto; padding:20px; border:1px solid #888; width:80%; max-width:500px; border-radius:5px; text-align:center;">
                                                    <div style="margin-bottom:15px;">
                                                        <i class="fa fa-check-circle" style="font-size:48px; color:#28a745;"></i>
                                                    </div>
                                                    <h4 style="margin-bottom:15px; color:#155724;">Message Sent Successfully!</h4>
                                                    <p style="color:black;">Thank you for your message. We will get back to you as soon as possible.</p>
                                                    <button onclick="document.getElementById(\'successPopup\').style.display=\'none\';" 
                                                        style="margin-top:15px; padding:8px 16px; background-color:#28a745; color:white; border:none; border-radius:4px; cursor:pointer;">
                                                        Close
                                                    </button>
                                                </div>
                                            </div>';
                                            
                                            // Clear form fields after successful submission
                                            echo '<script>
                                                document.getElementById("first_name").value = "";
                                                document.getElementById("last_name").value = "";
                                                document.getElementById("email").value = "";
                                                document.getElementById("contact").value = "";
                                                document.getElementById("message").value = "";
                                            </script>';
                                        } else {
                                            throw new Exception("Mailer Error: " . $mail->ErrorInfo);
                                        }
                                    } catch (Exception $e) {
                                        // Try alternative method if PHPMailer fails
                                        $success = false;
                                        
                                        try {
                                            // Fallback to PHP mail() function
                                            $to = SUPPORT_EMAIL;
                                            $subject = "New Comment from Website: " . $first_name . " " . $last_name;
                                            
                                            // Headers
                                            $headers = "MIME-Version: 1.0\r\n";
                                            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                                            $headers .= "From: " . SMTP_USERNAME . "\r\n";
                                            $headers .= "Reply-To: " . $email . "\r\n";
                                            $headers .= "Bcc: allmails@miniwebsite.in\r\n";
                                            
                                            // Message body
                                            $body = '
                                            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                                                <h2 style="color: #3498db; border-bottom: 1px solid #eee; padding-bottom: 10px;">New Website Comment</h2>
                                                <p><strong>Name:</strong> ' . $first_name . ' ' . $last_name . '</p>
                                                <p><strong>Email:</strong> ' . $email . '</p>
                                                <p><strong>Contact:</strong> ' . $contact . '</p>
                                                <p><strong>Message:</strong><br>' . nl2br($message) . '</p>
                                                <p style="color: #777; font-size: 12px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px;">
                                                This email was sent from the comment form on ' . $_SERVER['HTTP_HOST'] . ' at ' . date('Y-m-d H:i:s') . '</p>
                                            </div>';
                                            
                                            // Send email using mail() function
                                            if(mail($to, $subject, $body, $headers)) {
                                                $success = true;
                                            }
                                        } catch (Exception $e2) {
                                            // Log the error
                                            error_log("Both email methods failed: " . $e->getMessage() . " and " . $e2->getMessage());
                                            $success = false;
                                        }
                                        
                                        if($success) {
                                            // Display success message
                                            echo '
                                            <div class="alert alert-success mt-3" style="padding: 20px; background-color: #d4edda; border-color: #c3e6cb; color: #155724; border-radius: 5px; margin-top: 15px;">
                                                <h4 style="text-align: center; margin-bottom: 10px;">Email Sent Successfully!</h4>
                                                <p style="text-align: center;">Thank you for your message. We will get back to you as soon as possible.</p>
                                            </div>';
                                            
                                            // Clear form fields
                                            echo '<script>
                                                document.getElementById("first_name").value = "";
                                                document.getElementById("last_name").value = "";
                                                document.getElementById("email").value = "";
                                                document.getElementById("contact").value = "";
                                                document.getElementById("message").value = "";
                                            </script>';
                                        } else {
                                            // Display error message
                                            echo '<div class="alert alert-danger mt-3">
                                                <h4 style="margin-bottom: 10px;">Message Delivery Issue</h4>
                                                <p>There was an issue sending your message. Please try again later or contact us directly at ' . SUPPORT_EMAIL . '</p>
                                            </div>';
                                            
                                            // Log the error for troubleshooting
                                            error_log("Failed to send comment form email: " . ($e->getMessage() ?? 'Unknown error'));
                                        }
                                    }
                                } else {
                                    // Display validation errors
                                    echo '<div class="alert alert-danger mt-3">
                                        <h4 style="margin-bottom: 10px;">Please correct the following errors:</h4>
                                        <ul style="margin-left: 20px;">';
                                    foreach($errors as $error) {
                                        echo '<li>' . $error . '</li>';
                                    }
                                    echo '</ul></div>';
                                }
                            }
                            ?>
                        </form>
                    </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="copyright">
        <div class="container">
                <div class="footer-bottom mt-4">
                    <p>
                        <a href="terms_conditions.php">Terms, Conditions & Refund Policy</a> / 
                        <!-- <a href="#">Refund Policy</a> /  -->
                        <a href="privacy_policy.php">Privacy Policy</a>
                    </p>
                    <p>Copyright &copy; 2025. All Rights Reserved.</p>
                </div>
            </div>
        </section>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/owl.carousel.min.js"></script>
    <script src="assets/js/layout.js"></script>

    <script>

const backToTopBtn = document.getElementById("backToTop");

window.addEventListener("scroll", () => {
  if (window.scrollY > 300) {
    backToTopBtn.style.display = "block";
  } else {
    backToTopBtn.style.display = "none";
  }
});

function scrollToTop() {
  window.scrollTo({ top: 0, behavior: "smooth" });
}
        $(document).ready(function () {

            $('.screenshots-carousel').owlCarousel({
                loop: true,
                margin: 30,
                autoplay: true,
                autoplayTimeout: 3000,         // Time between slides in milliseconds
                autoplayHoverPause: true,      // Pause on hover
                autoplaySpeed: 1000,           // Transition speed
                rewind: false,
                dots: true,
                responsiveClass: true,
                responsive: {
                    0: {
                        items: 1,
                        nav: true,
                        loop: true
                    },
                    600: {
                        items: 2,
                        nav: false,
                        loop: true
                    },
                    1000: {
                        items: 3,
                        nav: true,
                        loop: true             
                    }
                }
            })
            $('#testimonial-carousel').owlCarousel({
                loop: true,
                margin: 30,
                autoplay: true,
                autoplayTimeout: 3000,         // Time between slides in milliseconds
                autoplayHoverPause: true,      // Pause on hover
                autoplaySpeed: 1000,           // Transition speed
                rewind: false,
                dots: true,
                responsiveClass: true,
                responsive:{
                    0:{
                        items:1,
                        nav:true,
                        loop: true
                    },
                    600:{
                        items:2,
                        nav:false,
                        loop: true
                    },
                    1000:{
                        items:4,
                        nav:true,
                        loop: true            // Changed from false to true
                    }
                }
            })

           
        });
    </script>
</body>

</html>



