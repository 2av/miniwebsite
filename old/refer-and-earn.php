

<?php
require('header.php');

?>
    <main>
        <section class="banner">
            <div class="container">
                <div class="banner-wrap">
                    <div class="banner-content">
                        <h3>Enjoy Unlimited</h3>
                        <h1 class="heading Referral_Reward_text">Referral Rewards !</h1>
                        <a href="https://www.miniwebsite.in/panel/login/login.php" class="btn btn-primary" style="    width: max-content;text-decoration: none; color: #0a0a0b;">Start Referring </a>
                    </div>
                    <div class="banner-img">
                        <div class="icon-facebook">
                            <img class="img-fluid" src="assets/images/REFER & EARN/01 Start Referring/facebook.png" alt="">
                        </div>
                        <div class="icon-wallet">
                            <img class="img-fluid" src="assets/images/REFER & EARN/01 Start Referring/wallet.png" alt="">
                        </div>
                       <div class="main-img">
                         <img class="img-fluid" src="assets/images/REFER & EARN/01 Start Referring/img.png" alt="">
                        </div>
                        <div class="icon-wahtsapp">
                            <img class="img-fluid" src="assets/images/REFER & EARN/01 Start Referring/wahtsapp.png" alt="" >
                        </div>
                        <div class="icon-sahre">
                            <img class="img-fluid" src="assets/images/REFER & EARN/01 Start Referring/sahre.png" alt="">
                        </div>
                    </div>
                </div>
            </div>
        </section>

     
        <section class="why-miniweb">
            <div class="container">
                <h2 class="heading ">Benefits of  <span><span class="theme-color">Refer & Earn </span> Program</span></h2>
                <img class="img-fluid   referEarnImageDesktop" src="assets\images\REFER & EARN\02 Benefits of program\Referandearn.jpg" alt="">
                <img class="img-fluid  referEarnImageMobile" src="assets\images\REFER & EARN\02 Benefits of program\Referearn-mobile.jpg" alt="">
            </div>
        </section>

       
        <section class="textimonial">
            <div class="container">
                <div class="textimonial-wrap">
                    <h2 class="heading blue-bg">How   - <span><span class="theme-color">Refer & Earn  </span>Works?</span></h2>
                    <div class="refer_earn_works_desktop">
                    <ul>
                        <li><img class="img-fluid" src="assets/images/REFER & EARN/03 How refer and earn works/icon.png" alt=""></li>
                        <li><img class="img-fluid" src="assets/images/REFER & EARN/03 How refer and earn works/icon.png" alt=""></li>
                        <li><img class="img-fluid" src="assets/images/REFER & EARN/03 How refer and earn works/icon.png" alt=""></li>
                    </ul>
                    <div class="testimonial-carousel">
                       
                        <div class="testimonial-card ">
                            <img class="img-fluid" src="assets/images/REFER & EARN/03 How refer and earn works/01 create website.gif" alt="Fill the Enquiry Form or Call Us">
                            <h3>Create your <br>
                                Mini Website </h3>
                        </div>

                        <div class="testimonial-card">
                            <img class="img-fluid" src="assets/images/REFER & EARN/03 How refer and earn works/02 Share.gif" alt="Do Simple Formalities ">
                            <h3>
                                Share your referral link with businesses, working professionals and friends</h3>
                            
                        </div>

                        <div class="testimonial-card">
                            <img class="img-fluid" src="assets/images/REFER & EARN/03 How refer and earn works/03 Earn.gif" alt="Become Our Franchise Partner">
                            <h3>
                                Earn Rs. 250/- when someone subscribe to our paid plan using your referral link</h3>
                        </div>
                    </div>
</div>
<div class="refer_earn_works_mobile">
                    
                    <div class="testimonial-carousel">
                       <div class="pointer_section">
                       <img class="img-fluid pointer1" src="assets/images/REFER & EARN/03 How refer and earn works/icon.png" alt="">
                       <span>1</span>
                       </div>
                        <div class="testimonial-card testimonial-card1">
                            <img class="img-fluid" src="assets/images/REFER & EARN/03 How refer and earn works/01 create website.gif" alt="Fill the Enquiry Form or Call Us">
                            <h3>Create your <br>
                                Mini Website </h3>
                        </div>
                        <div class="pointer_section">
                       <img class="img-fluid pointer2" src="assets/images/REFER & EARN/03 How refer and earn works/icon.png" alt="">
                       <span>2</span>
                       </div>
                        <div class="testimonial-card">
                        
                            <img class="img-fluid" src="assets/images/REFER & EARN/03 How refer and earn works/02 Share.gif" alt="Do Simple Formalities ">
                            <h3>
                                Share your referral link with businesses, working professionals and friends</h3>
                            
                        </div>
                        <div class="pointer_section">
                       <img class="img-fluid pointer3" src="assets/images/REFER & EARN/03 How refer and earn works/icon.png" alt="">
                       <span>3</span>
                       </div>
                        <div class="testimonial-card">
                       
                            <img class="img-fluid" src="assets/images/REFER & EARN/03 How refer and earn works/03 Earn.gif" alt="Become Our Franchise Partner">
                            <h3>
                                Earn Rs. 250/- when someone subscribe to our paid plan using your referral link</h3>
                        </div>
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
                
                // Display FAQs for refer_earn page
                echo displayFrontendFAQs('refer_earn', 'faqAccordion');
                ?>
                
            </div>
        </section>

    </main>

    <style>
        .banner .banner-wrap {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 40px 0;
    gap: 40px;
}
.banner .banner-wrap .banner-content {
    width: 45%;
    flex-shrink: 0;
}
.banner .banner-wrap .banner-content h3 {
    color: #002169;
    font-size: 40px;
    line-height: 40px;
    font-weight: 700;
}
.banner .banner-wrap .banner-content .heading {
    color: #002169;
    font-size: 48px;
    display: inline;
    font-weight: 700;
    text-align: left;
    margin-bottom: 50px;
    display: inline;
}

section .heading {
    margin: 0 auto;
    text-align: center;
    position: relative;
}
.heading {
    font-size: 36px;
    color: #002169;
    font-weight: 700;
}
.banner .banner-wrap .banner-content .btn {
    margin: 0 auto;
    display: block;
    color: #000000;
    font-size: 32px;
    font-weight: 600;
    margin-top: 100px;
}
.banner-img {
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    width: 50%;
    max-width: 500px;
    margin: 0 auto;
    min-height: 400px;
}
.icon-facebook, .icon-wallet, .icon-wahtsapp, .icon-sahre {
    position: absolute;
    width: 80px;
    height: 80px;
    z-index: 3;
    transition: transform 0.3s ease;
}

.icon-facebook:hover, .icon-wallet:hover, .icon-wahtsapp:hover, .icon-sahre:hover {
    transform: scale(1.1);
}

.icon-facebook {
    top: 15%;
    left: 5%;
}

.icon-wahtsapp {
    top: 15%;
    right: 5%;
}

.icon-wallet {
    bottom: 40%;
    left: -16%;
}

.icon-sahre {
    bottom: 20%;
    right: 0%;
}

.main-img {
    position: relative;
    z-index: 2;
}

.main-img img {
    width: 280px;
    height: auto;
    position: relative;
    z-index: 2;
}
.why-miniweb {
    background-color: #002169;
    padding: 100px 0;
}
.why-miniweb .heading {
    color: #fff;
}
section .heading {
    margin: 0 auto;
    text-align: center;
    position: relative;
}
.why-miniweb img {
    margin: 0 auto;
}
.textimonial {
    padding: 100px 0;
    background-color: #ffffff;
}
.textimonial .textimonial-wrap .heading {
    color: #fff;
    padding: 20px 10px;
    width: 70%;
    border-radius: 16px;
}
section .heading {
    margin: 0 auto;
    text-align: center;
    position: relative;
}
.blue-bg {
    background-color: #002169;
}
.theme-color {
    color: #ffbe17;
} 
.textimonial {
    padding: 100px 0;
}
.textimonial .textimonial-wrap .heading {
    color: #fff;
    padding: 20px 10px;
    width: 70%;
    border-radius: 16px;
}

section .heading {
    margin: 0 auto;
    text-align: center;
    position: relative;
}
.blue-bg {
    background-color: #002169;
}
.textimonial .textimonial-wrap .heading::before {
    bottom: 10px;
}
section .heading::before {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    border-bottom: solid 2px #ffbe17;
    width: 175px;
    margin: 0 auto;
}
.theme-color {
    color: #ffbe17;
}
.textimonial .textimonial-wrap ul {
    display: flex;
    align-items: center;
    justify-content: space-around;
    list-style: none;
    width: 80%;
    margin-top: 100px;
    margin-left: auto;
    margin-right: auto;
    position: relative;
}
.textimonial .textimonial-wrap ul::before {
    content: "";
    position: absolute;
    border-bottom: 3px dashed #002169;
    display: block;
    width: 60%;
    height: 1px;
    z-index: 1;
    margin: 0 auto;
    left: 0;
    right: 0;
}
.textimonial .textimonial-wrap ul li {
    position: relative;
    counter-increment: step 0;
    z-index: 11;
}
.textimonial .textimonial-wrap ul li img {
    width: 60%;
}
.textimonial .textimonial-wrap ul li::after {
    text-indent: 0;
    display: block;
    counter-increment: step;
    content: "" counter(step);
    color: #fff;
    position: absolute;
    top: 50%;
    left: 50%;
    -webkit-transition: .2sease-in 0s;
    transition: .2sease-in 0s;
    font-size: 50px;
    line-height: 100px;
    transform: translate(-50%, -50%);
    margin-top: -5%;
    margin-left: -20%;
}
.testimonial-carousel .testimonial-card:first-child {
    border-top-left-radius: 50px;
    border-bottom-left-radius: 50px;
    background-color: #dfe9fe;
}
.testimonial-carousel .testimonial-card:last-child {
    border-top-right-radius: 50px;
    border-bottom-right-radius: 50px;
    background-color: #dfe9fe;
}
.testimonial-carousel {
    display: flex;
    justify-content: center;
    gap: 40px;
    width: 80%;
    margin: 0 auto;
}
.testimonial-card {
    margin: 0px auto;
    border-radius: 12px;
    text-align: center;
    color: #000;
    border: solid 1px #002169;
    position: relative;
    height: 290px;
    width: 30.33%;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-direction: column;
}

.testimonial-card img {
    width: 100px !important;
    height: 100px;
    border-radius: 50%;
    position: absolute;
    top: 11px;
    left: 50%;
    transform: translateX(-50%);
}

/* Responsive Design */
@media (max-width: 768px) {
    .banner .banner-wrap {
        flex-direction: column;
        text-align: center;
        gap: 30px;
    }
    
    .banner .banner-wrap .banner-content {
        width: 100%;
    }
    
    .banner-img {
        width: 100%;
        max-width: 400px;
        min-height: 300px;
    }
    
    .main-img img {
        width: 200px;
    }
    
    .icon-facebook, .icon-wallet, .icon-wahtsapp, .icon-sahre {
        width: 60px;
        height: 60px;
    }
    
    .banner .banner-wrap .banner-content h3 {
        font-size: 28px;
        line-height: 32px;
    }
    
    .banner .banner-wrap .banner-content .heading {
        font-size: 36px;
    }
    
    .banner .banner-wrap .banner-content .btn {
        font-size: 24px;
        margin-top: 50px;
    }
    
    .testimonial-carousel {
        flex-direction: column;
        gap: 20px;
    }
    
    .testimonial-card {
        width: 100%;
        height: auto;
        min-height: 200px;
    }
}

@media (max-width: 480px) {
    .banner .banner-wrap .banner-content h3 {
        font-size: 24px;
        line-height: 28px;
    }
    
    .banner .banner-wrap .banner-content .heading {
        font-size: 32px;
    }
    
    .banner .banner-wrap .banner-content .btn {
        font-size: 20px;
        padding: 12px 24px;
    }
    
    .main-img img {
        width: 180px;
    }
    
    .icon-facebook, .icon-wallet, .icon-wahtsapp, .icon-sahre {
        width: 50px;
        height: 50px;
    }
}
/* Referal and Reward style for desktop screen */
.Referral_Reward_text{    
    position: relative;
}
.Referral_Reward_text::before{
    content: '';
    width: 280px !important;
    background-color: #ffc107;
    position: absolute;
    height:4px;
    left: -40px !important;
}
.banner .banner-wrap .banner-content h3 {
    
    font-size:40px; 
    margin-bottom: 18px;
}
.banner .banner-wrap .banner-content {
    width: 45%;
    flex-shrink: 0;
    position: relative;
    bottom: 96px;
}
.banner .banner-wrap .banner-content .btn {
    
    font-size: 26px;
    font-weight: 600;
    margin-top: 75px;
    margin-left: 75px;
}
.banner .banner-wrap .banner-content .heading {
    font-size: 50px;
}
.why-miniweb .heading {
    margin-bottom:40px;
}
.why-miniweb .heading::before {
    content: "";
    position: absolute;
    bottom: 0;
    left: 45px;
    right: 0;
    border-bottom: solid 2px #ffbe17;
    width: 199px;
    margin: 0 auto;
}
.textimonial .heading.blue-bg::before{
    left: -23px;
}
.testimonial-card h3 {
    font-size: 20px;
    line-height: 22px;
    margin: 0;
}
.faq .heading::before {
    content: "";
    position: absolute;
    bottom: -8px;
    left: -32px;
    right: 0;
    border-bottom: solid 3px #ffbe17;
    width: 445px;
    margin: 0 auto;
}
.faq .faq-container {
    max-width: 84%;
}
.faq .faq-item .btn-link {
    font-size: 18px;
}
.referEarnImageMobile{
    display:none;
}
.referEarnImageDesktop{
    display:block;
}
.refer_earn_works_desktop{
    display:block;
}
.refer_earn_works_mobile{
    display:none;
}
.icon-wallet {
    width: 128px;
    height: 100px;
    bottom: 40%;
    left: -24%;
}
.testimonial-card img {
    top: 37px;
    left: 50%;
}

.testimonial-card h3 {
    font-size: 20px;
    line-height: 22px;
    margin: 0;
    margin-top: 53px;
}

@media screen and (max-width: 768px) {
    .referEarnImageMobile{
    display:block;
}
.referEarnImageDesktop{
    display:none;
}
    .faq .heading::before {
    content: "";
    position: absolute;
    bottom: -8px;
    left: 0px;
    right: 0;
    border-bottom: solid 3px #ffbe17;
    width: 260px;
    margin: 0 auto;
}
.banner .banner-wrap .banner-content .btn {
    font-size: 26px;
    font-weight: 600;
    margin-top: 75px;
    margin-left: 0px; 
}
.banner .banner-wrap
 {
    flex-direction: column-reverse;
        text-align: center;
        gap: 30px;
    }
    .banner .banner-wrap{
        padding:0px!important;
    }

    .icon-wahtsapp {
    top: 14%;
    right: -6%;
}
.icon-facebook {
        width: 55px;
        height: 55px;
    }
 .banner-img   .main-img{
    width: 75%;
 }
 .icon-wallet {
    bottom: 26%;
    left: -10%;
    width: 75px;
    height: 75px;
}
.icon-sahre {
    bottom: 20%;
    right: -4%;
    width: 55px;
        height: 55px;
}
.banner .banner-wrap .banner-content {
    width: 100%;
    flex-shrink: 0;
    position: relative;
     bottom: 0px; 
}
.banner .banner-wrap .banner-content h3 {
    font-size: 28px;
    margin-bottom: 18px;
    text-align: left;
}
.banner .banner-wrap .banner-content {
        width: 90%;
        flex-shrink: 0;
        position: relative;
        bottom: 0px;
    }
    .banner .banner-wrap .banner-content .heading {
    font-size: 35px;
}
.Referral_Reward_text::before {
    content: '';
    width: 214px !important;
     left: 4px !important;
}
.banner .banner-wrap .banner-content .btn {
        font-size: 20px;
        padding: 2px 10px;
        margin:50px auto;
    }
    .why-miniweb .heading {
    margin-bottom: 40px;
    font-size: 30px;
}
.why-miniweb .heading span{
 display:flex;
 justify-content:center;
 gap:10px;
}
.why-miniweb {
    background-color: #002169;
    padding: 50px 0;
}
.why-miniweb .heading::before {
    content: "";
    position: absolute;
    bottom: -4px;
    left: -12px;
    right: 0;
    border-bottom: solid 2px #ffbe17;
    width: 230px;
    margin: 0 auto;
}
.textimonial .textimonial-wrap .heading {
    color: #fff;
    padding: 20px 10px;
    width: 80%;
    border-radius: 16px;
    font-size: 25px;
}
.textimonial .textimonial-wrap .heading span {
    display:flex;
    justify-content:center;
    gap:10px;
}
.textimonial .textimonial-wrap .heading::before {
    bottom: 14px;
    left:1px;
}
.refer_earn_works_desktop{
    display:none;
}
.refer_earn_works_mobile{
    display:block;
}
.refer_earn_works_mobile .pointer_section{
    position: relative;
        margin: auto;
        width: 60px;
        margin-top: 20px;
}
.refer_earn_works_mobile .pointer_section span{
    position: absolute;
        left: 25px;
        top: 11px;
        font-size: 22px;
        color: #fff;
        font-weight: 700;
}
    
    
.testimonial-carousel {
        flex-direction: column;
        gap: 5px;
    }
    .testimonial-carousel .testimonial-card1 {
  border-top-left-radius: 50px !important;
  border-bottom-left-radius: 50px !important;
  background-color: #dfe9fe !important;
}
.testimonial-card {
        width: 100%;
        height: auto;
        min-height: 230px;
    }
    .testimonial-card img{
        width: 80px !important;
    height: 80px;
    border-radius: 50%;
    }
.testimonial-card1 img {
  
    position: absolute;
    top: 33px;
    left: 50%;
    transform: translateX(-50%);
}
.testimonial-card h3 {
    
    margin-top: 65px;
}
.textimonial{
    padding-bottom:40px !important;
}
.faq .heading{
    font-size:26px;
    margin-bottom: 35px;
}
.faq .heading::before {
        
        width: 232px;
        margin: 0 auto;
    }
    .faq .faq-container {
    max-width: 90%;
}
.faq .faq-item .btn-link {
    font-size: 17px;
}
.main-foot .heading::before {
    right: auto;
    left: 18px;
    width: 60px;
    border-bottom: solid 3px #ffbe17;
    bottom: -5px;
}

}

</style>
   
    <?php
require('footer.php');

?> 