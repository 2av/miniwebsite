<?php
session_start();

$captcha = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6);
$_SESSION['captcha'] = $captcha;
$captcha_error = '';
 
require('header.php');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_mail'])) {
    $name = htmlspecialchars(trim($_POST['name']));
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $contact = preg_match("/^\d{10}$/", $_POST['contact']) ? $_POST['contact'] : null;
    $address = htmlspecialchars(trim($_POST['address']));
    $message = htmlspecialchars(trim($_POST['message']));
    $captcha_input = strtoupper(trim($_POST['captcha']));
    
    if (!isset($_SESSION['captcha'])) {
        $captcha_error = "Captcha expired. Please try again.";
    } elseif ($captcha_input !== $_SESSION['captcha']) {
        $captcha_error = "Invalid captcha! Please try again.";
    } elseif (!$name || !$email || !$contact) {
        $captcha_error = "Please fill all required fields.";
    } else {
        // Send email
        $to = "akhilesh.vis17@gmail.com";
        $subject = "Franchise Enquiry from $name";
        $body = "
            <strong>Name:</strong> $name<br>
            <strong>Email:</strong> $email<br>
            <strong>Contact:</strong> $contact<br>
            <strong>Address:</strong> $address<br>
            <strong>Message:</strong><br>$message
        ";

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: Mini Website Care <no-reply@yourdomain.com>\r\n";
        $headers .= "Reply-To: $email\r\n";

        if (mail($to, $subject, $body, $headers)) {
            echo "Your enquiry has been sent successfully!";
        } else {
            echo "Failed to send email.";
        }
        // After successful submission, regenerate captcha
        unset($_SESSION['captcha']);
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
                                          Refresh
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
                            <input class="btn btn-primary" name="send_email" type="submit" value="SEND">
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
                            <img class="img-fluid" src="assets/images/03Howtobecome/01FilletheEnquiry.png" alt="Fill the Enquiry Form or Call Us">
                            <h3>Fill the Enquiry Form or Call Us </h3>
                        </div>

                        <div class="testimonial-card">
                            <img class="img-fluid" src="assets/images/03Howtobecome/02DoSimple.png" alt="Do Simple Formalities ">
                            <h3>Do Simple Formalities </h3>
                            
                        </div>

                        <div class="testimonial-card">
                            <img class="img-fluid" src="assets/images/03Howtobecome/03BecomeFranchise.png" alt="Become Our Franchise Partner">
                            <h3>Become Our Franchise Partner</h3>
                        </div>
                    </div>
                </div>
            </div>
        </section>



        <section class="faq">
            <div class="container">
                <h2 class="heading">Frequently Asked Questions (FAQ)</h2>
                <div class="faq-container" id="faqAccordion">
                    
                    <div class="faq-item">
                        <div class="card-header p-2">
                            <h5 class="mb-0">
                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#faq1">
                                    Who can take a franchise?
                                </button>
                            </h5>
                        </div>
                        <div id="faq1" class="collapse" data-parent="#faqAccordion">
                            <div class="card-body">Any company or person who wants to earn money by working honestly.
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="card-header p-2">
                            <h5 class="mb-0">
                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#faq2">
                                    Is it necessary to have GST number?

                                </button>
                            </h5>
                        </div>
                        <div id="faq2" class="collapse" data-parent="#faqAccordion">
                            <div class="card-body">No, it is not mandatory.
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="card-header p-2">
                            <h5 class="mb-0">
                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#faq3">
                                    Is it necessary to have an office and staff?
                                </button>
                            </h5>
                        </div>
                        <div id="faq3" class="collapse" data-parent="#faqAccordion">
                            <div class="card-body">
                                No, it is not mandatory, it is completely your choice.
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="card-header p-2">
                            <h5 class="mb-0">
                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#faq4">
                                    Is technical knowledge like coding or designing required?

                                </button>
                            </h5>
                        </div>
                        <div id="faq4" class="collapse" data-parent="#faqAccordion">
                            <div class="card-body">
                                No, it is not required.
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="card-header p-2">
                            <h5 class="mb-0">
                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#faq5">
                                    How to contact us for franchise?
                                </button>
                            </h5>
                        </div>
                        <div id="faq5" class="collapse" data-parent="#faqAccordion">
                            <div class="card-body">
                                Fill the inquire from or call us.
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="card-header p-2">
                            <h5 class="mb-0">
                                <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#faq6">
                                    Is customer support available?
                                </button>
                            </h5>
                        </div>
                        <div id="faq6" class="collapse" data-parent="#faqAccordion">
                            <div class="card-body">
                                Yes, miniwebsite.in offers customer support via email, chat and call to assist you with any questions or issues. Our customer Support Timings are
                            </div>
                        </div>
                    </div>

                </div> <!-- End Accordion -->
            </div>
        </section>

    </main>
   
    <?php
require('footer.php');

?>
