<?php

require('connect.php');


$login_users='CREATE TABLE IF NOT EXISTS customer_login (
id INT(6)  AUTO_INCREMENT PRIMARY KEY,
ip VARCHAR(100) 	DEFAULT  "" ,
user_email VARCHAR(200) 	DEFAULT  "" ,
user_contact VARCHAR(200) 	DEFAULT  "" ,
user_name VARCHAR(200) 	DEFAULT  "" ,
user_password VARCHAR(200) 	DEFAULT  "" ,
user_token VARCHAR(200) 	DEFAULT  "" ,
user_active VARCHAR(200) 	DEFAULT  "" ,
select_service VARCHAR(200) 	DEFAULT  "" ,
sender_token VARCHAR(500) 	DEFAULT  "" ,

uploaded_date DATETIME NULL DEFAULT CURRENT_TIMESTAMP 

)';


$login_users2=mysqli_query($connect,$login_users);


if($login_users2) {echo '<div class="success">login_users table created</div>';} else {echo '<div class="danger">login_users table Error</div>';}

//franchisee wallet  

$wallet='CREATE TABLE IF NOT EXISTS wallet (
id INT(6)  AUTO_INCREMENT PRIMARY KEY,
ip VARCHAR(100) 	DEFAULT  "" ,
f_user_email VARCHAR(200) 	DEFAULT  "" ,
w_balance VARCHAR(200) 	DEFAULT  "" ,
w_deposit VARCHAR(200) 	DEFAULT  "" ,
w_withdraw VARCHAR(200) 	DEFAULT  "" ,
w_order_id VARCHAR(200) 	DEFAULT  "" ,
w_txn_msg VARCHAR(200) 	DEFAULT  "" ,

uploaded_date DATETIME NULL DEFAULT CURRENT_TIMESTAMP 

)';


$wallet2=mysqli_query($connect,$wallet);


if($wallet2) {echo '<div class="success">wallet table created</div>';} else {echo '<div class="danger">wallet table Error</div>';}

//card database 


$franchisee_login='CREATE TABLE IF NOT EXISTS franchisee_login (
id INT(6)  AUTO_INCREMENT PRIMARY KEY,
ip VARCHAR(100) 	DEFAULT  "" ,
f_user_email VARCHAR(200) 	DEFAULT  "" ,
f_user_contact VARCHAR(200) 	DEFAULT  "" ,
f_user_name VARCHAR(200) 	DEFAULT  "" ,
f_user_password VARCHAR(200) 	DEFAULT  "" ,
f_user_token VARCHAR(200) 	DEFAULT  "" ,
f_user_active VARCHAR(200) 	DEFAULT  "" ,
f_user_image LONGBLOB,
f_user_google_pay VARCHAR(200) 	DEFAULT  "" ,
f_user_paytm VARCHAR(200) 	DEFAULT  "" ,
f_user_rz_pay VARCHAR(300) 	DEFAULT  "" ,
f_user_rz_pay2 VARCHAR(300) 	DEFAULT  "" ,
f_select_service VARCHAR(200) 	DEFAULT  "" ,
f_wallet_balance VARCHAR(200) 	DEFAULT  "" ,

uploaded_date DATETIME NULL DEFAULT CURRENT_TIMESTAMP 

)';


$franchisee_login2=mysqli_query($connect,$franchisee_login);


if($franchisee_login2) {echo '<div class="success">franchisee_login table created</div>';} else {echo '<div class="danger">franchisee_login table Error</div>';}
// admin login

$admin_login='CREATE TABLE IF NOT EXISTS admin_login (
id INT(6)  AUTO_INCREMENT PRIMARY KEY,
ip VARCHAR(100) 	DEFAULT  "" ,
admin_email VARCHAR(200) 	DEFAULT  "" ,
admin_contact VARCHAR(200) 	DEFAULT  "" ,
admin_password VARCHAR(200) 	DEFAULT  "" ,
admin_name VARCHAR(200) 	DEFAULT  "" ,
admin_image LONGBLOB,
admin_google_pay VARCHAR(200) 	DEFAULT  "" ,
admin_paytm VARCHAR(200) 	DEFAULT  "" ,
admin_rz_pay VARCHAR(300) 	DEFAULT  "" ,
admin_rz_pay2 VARCHAR(300) 	DEFAULT  "" ,

uploaded_date DATETIME NULL DEFAULT CURRENT_TIMESTAMP 

)';


$admin_login2=mysqli_query($connect,$admin_login);


if($admin_login2) {echo '<div class="success">admin_login table created</div>';} else {echo '<div class="danger">admin_login table Error</div>';}

//card database 


$digi_card='CREATE TABLE IF NOT EXISTS digi_card (
id INT(6)  AUTO_INCREMENT PRIMARY KEY,
ip VARCHAR(50) 	DEFAULT  "" ,
f_user_email VARCHAR(200) 	DEFAULT  "" ,
user_email VARCHAR(200) 	DEFAULT  "" ,
card_id VARCHAR(200) 	DEFAULT  "" ,
password VARCHAR(200) 	DEFAULT  "" ,
d_css VARCHAR(100) 	DEFAULT  "" ,
d_mobile_css VARCHAR(100) 	DEFAULT  "" ,
d_comp_name VARCHAR(200) 	DEFAULT  "" ,
d_logo LONGBLOB,
d_title VARCHAR(10) 	DEFAULT  "" ,
d_f_name VARCHAR(30) 	DEFAULT  "" ,
d_l_name VARCHAR(30) 	DEFAULT  "" ,
d_position VARCHAR(30) 	DEFAULT  "" ,
d_contact VARCHAR(30) 	DEFAULT  "" ,
d_contact2 VARCHAR(30) 	DEFAULT  "" ,
d_whatsapp VARCHAR(30) 	DEFAULT  "" ,
d_address VARCHAR(500) 	DEFAULT  "" ,
d_email VARCHAR(100) 	DEFAULT  "" ,
d_website VARCHAR(150) 	DEFAULT  "" ,
d_location VARCHAR(1000) 	DEFAULT  "" ,
d_fb VARCHAR(700) 	DEFAULT  "" ,
d_twitter VARCHAR(700) 	DEFAULT  "" ,
d_instagram VARCHAR(700) 	DEFAULT  "" ,
d_linkedin VARCHAR(500) 	DEFAULT  "" ,
d_youtube VARCHAR(300) 	DEFAULT  "" ,
d_pinterest VARCHAR(500) 	DEFAULT  "" ,
d_website2 VARCHAR(500) 	DEFAULT  "" ,
d_comp_est_date VARCHAR(100) 	DEFAULT  "" ,
d_nature_of_business VARCHAR(200) 	DEFAULT  "" ,
d_speciality VARCHAR(200) 	DEFAULT  "" ,
d_about_us VARCHAR(2000) 	DEFAULT  "" ,
d_paytm VARCHAR(20) 	DEFAULT  "" ,
d_google_pay VARCHAR(20) 	DEFAULT  "" ,
d_phone_pay VARCHAR(20) 	DEFAULT  "" ,
d_account_no VARCHAR(40) 	DEFAULT  "" ,
d_ifsc VARCHAR(40) 	DEFAULT  "" ,
d_ac_name VARCHAR(100) 	DEFAULT  "" ,
d_bank_name VARCHAR(100) 	DEFAULT  "" ,
d_ac_type VARCHAR(30) 	DEFAULT  "" ,
d_qr_paytm LONGBLOB,
d_qr_google_pay LONGBLOB,
d_qr_phone_pay LONGBLOB,
d_youtube1 VARCHAR(150) 	DEFAULT  "" ,
d_youtube2 VARCHAR(150) 	DEFAULT  "" ,
d_youtube3 VARCHAR(150) 	DEFAULT  "" ,
d_youtube4 VARCHAR(150) 	DEFAULT  "" ,
d_youtube5 VARCHAR(150) 	DEFAULT  "" ,
d_pro_name1 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name2 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name3 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name4 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name5 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name6 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name7 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name8 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name9 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name10 VARCHAR(100) 	DEFAULT  "" ,
d_payment_status VARCHAR(200) 	DEFAULT  "" ,
d_card_status VARCHAR(200) 	DEFAULT  "" ,
d_payment_amount VARCHAR(200) 	DEFAULT  "" ,
d_order_id VARCHAR(200) 	DEFAULT  "" ,
d_logo_location VARCHAR(1000) 	DEFAULT  "" ,
uploaded_date DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
d_payment_date DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
validity_date DATETIME NULL DEFAULT NULL

)';


$digi_card2=mysqli_query($connect,$digi_card);


if($digi_card2) {echo '<div class="success">digi_card table created</div>';} else {echo '<div class="danger">digi_card table Error</div>';}


//card database 


$digi_cardSecond='CREATE TABLE IF NOT EXISTS digi_card2 (
id VARCHAR(50) 	DEFAULT  "",
user_email VARCHAR(200) 	DEFAULT  "" ,
card_id VARCHAR(200) 	DEFAULT  "" ,
d_pro_img1 LONGBLOB,
d_pro_img2 LONGBLOB,
d_pro_img3 LONGBLOB,
d_pro_img4 LONGBLOB,
d_pro_img5 LONGBLOB,
d_pro_img6 LONGBLOB,
d_pro_img7 LONGBLOB,
d_pro_img8 LONGBLOB,
d_pro_img9 LONGBLOB,
d_pro_img10 LONGBLOB,
d_pro_name1 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name2 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name3 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name4 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name5 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name6 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name7 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name8 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name9 VARCHAR(100) 	DEFAULT  "" ,
d_pro_name10 VARCHAR(100) 	DEFAULT  "" 

)';


$digi_cardSecond2=mysqli_query($connect,$digi_cardSecond);


if($digi_cardSecond2) {echo '<div class="success">digi_cardSecond table created</div>';} else {echo '<div class="danger">digi_card table Error</div>';}


//card database 


$digi_cardSe='CREATE TABLE IF NOT EXISTS digi_card3 (
id VARCHAR(50) 	DEFAULT  "",
user_email VARCHAR(200) 	DEFAULT  "" ,
card_id VARCHAR(200) 	DEFAULT  "" ,
d_gall_img1 LONGBLOB,
d_gall_img2 LONGBLOB,
d_gall_img3 LONGBLOB,
d_gall_img4 LONGBLOB,
d_gall_img5 LONGBLOB,
d_gall_img6 LONGBLOB,
d_gall_img7 LONGBLOB,
d_gall_img8 LONGBLOB,
d_gall_img9 LONGBLOB,
d_gall_img10 LONGBLOB

)';


$digi_cardSe2=mysqli_query($connect,$digi_cardSe);


if($digi_cardSe2) {echo '<div class="success">digi_cardSe table created</div>';} else {echo '<div class="danger">digi_cardSe table Error</div>';}




// product table 

$product='CREATE TABLE IF NOT EXISTS products (
id VARCHAR(200) 	DEFAULT  "",
user_email VARCHAR(200) 	DEFAULT  "" ,
pro_name1 VARCHAR(200) 	DEFAULT  "" ,
pro_img1 LONGBLOB,
pro_mrp1 VARCHAR(200) 	DEFAULT  "" ,
pro_price1 VARCHAR(200) 	DEFAULT  "" ,
pro_tax1 VARCHAR(200) 	DEFAULT  "" ,

pro_name2 VARCHAR(200) 	DEFAULT  "" ,
pro_img2 LONGBLOB,
pro_mrp2 VARCHAR(200) 	DEFAULT  "" ,
pro_price2 VARCHAR(200) 	DEFAULT  "" ,
pro_tax2 VARCHAR(200) 	DEFAULT  "" ,

pro_name3 VARCHAR(200) 	DEFAULT  "" ,
pro_img3 LONGBLOB,
pro_mrp3 VARCHAR(200) 	DEFAULT  "" ,
pro_price3 VARCHAR(200) 	DEFAULT  "" ,
pro_tax3 VARCHAR(200) 	DEFAULT  "" ,

pro_name4 VARCHAR(200) 	DEFAULT  "" ,
pro_img4 LONGBLOB,
pro_mrp4 VARCHAR(200) 	DEFAULT  "" ,
pro_price4 VARCHAR(200) 	DEFAULT  "" ,
pro_tax4 VARCHAR(200) 	DEFAULT  "" ,

pro_name5 VARCHAR(200) 	DEFAULT  "" ,
pro_img5 LONGBLOB,
pro_mrp5 VARCHAR(200) 	DEFAULT  "" ,
pro_price5 VARCHAR(200) 	DEFAULT  "" ,
pro_tax5 VARCHAR(200) 	DEFAULT  "" ,

pro_name6 VARCHAR(200) 	DEFAULT  "" ,
pro_img6 LONGBLOB,
pro_mrp6 VARCHAR(200) 	DEFAULT  "" ,
pro_price6 VARCHAR(200) 	DEFAULT  "" ,
pro_tax6 VARCHAR(200) 	DEFAULT  "" ,

pro_name7 VARCHAR(200) 	DEFAULT  "" ,
pro_img7 LONGBLOB,
pro_mrp7 VARCHAR(200) 	DEFAULT  "" ,
pro_price7 VARCHAR(200) 	DEFAULT  "" ,
pro_tax7 VARCHAR(200) 	DEFAULT  "" ,

pro_name8 VARCHAR(200) 	DEFAULT  "" ,
pro_img8 LONGBLOB,
pro_mrp8 VARCHAR(200) 	DEFAULT  "" ,
pro_price8 VARCHAR(200) 	DEFAULT  "" ,
pro_tax8 VARCHAR(200) 	DEFAULT  "" ,

pro_name9 VARCHAR(200) 	DEFAULT  "" ,
pro_img9 LONGBLOB,
pro_mrp9 VARCHAR(200) 	DEFAULT  "" ,
pro_price9 VARCHAR(200) 	DEFAULT  "" ,
pro_tax9 VARCHAR(200) 	DEFAULT  "" ,

pro_name10 VARCHAR(200) 	DEFAULT  "" ,
pro_img10 LONGBLOB,
pro_mrp10 VARCHAR(200) 	DEFAULT  "" ,
pro_price10 VARCHAR(200) 	DEFAULT  "" ,
pro_tax10 VARCHAR(200) 	DEFAULT  "" ,

pro_name11 VARCHAR(200) 	DEFAULT  "" ,
pro_img11 LONGBLOB,
pro_mrp11 VARCHAR(200) 	DEFAULT  "" ,
pro_price11 VARCHAR(200) 	DEFAULT  "" ,
pro_tax11 VARCHAR(200) 	DEFAULT  "" ,

pro_name12 VARCHAR(200) 	DEFAULT  "" ,
pro_img12 LONGBLOB,
pro_mrp12 VARCHAR(200) 	DEFAULT  "" ,
pro_price12 VARCHAR(200) 	DEFAULT  "" ,
pro_tax12 VARCHAR(200) 	DEFAULT  "" ,

pro_name13 VARCHAR(200) 	DEFAULT  "" ,
pro_img13 LONGBLOB,
pro_mrp13 VARCHAR(200) 	DEFAULT  "" ,
pro_price13 VARCHAR(200) 	DEFAULT  "" ,
pro_tax13 VARCHAR(200) 	DEFAULT  "" ,

pro_name14 VARCHAR(200) 	DEFAULT  "" ,
pro_img14 LONGBLOB,
pro_mrp14 VARCHAR(200) 	DEFAULT  "" ,
pro_price14 VARCHAR(200) 	DEFAULT  "" ,
pro_tax14 VARCHAR(200) 	DEFAULT  "" ,

pro_name15 VARCHAR(200) 	DEFAULT  "" ,
pro_img15 LONGBLOB,
pro_mrp15 VARCHAR(200) 	DEFAULT  "" ,
pro_price15 VARCHAR(200) 	DEFAULT  "" ,
pro_tax15 VARCHAR(200) 	DEFAULT  "" ,

pro_name16 VARCHAR(200) 	DEFAULT  "" ,
pro_img16 LONGBLOB,
pro_mrp16 VARCHAR(200) 	DEFAULT  "" ,
pro_price16 VARCHAR(200) 	DEFAULT  "" ,
pro_tax16 VARCHAR(200) 	DEFAULT  "" ,

pro_name17 VARCHAR(200) 	DEFAULT  "" ,
pro_img17 LONGBLOB,
pro_mrp17 VARCHAR(200) 	DEFAULT  "" ,
pro_price17 VARCHAR(200) 	DEFAULT  "" ,
pro_tax17 VARCHAR(200) 	DEFAULT  "" ,

pro_name18 VARCHAR(200) 	DEFAULT  "" ,
pro_img18 LONGBLOB,
pro_mrp18 VARCHAR(200) 	DEFAULT  "" ,
pro_price18 VARCHAR(200) 	DEFAULT  "" ,
pro_tax18 VARCHAR(200) 	DEFAULT  "" ,

pro_name19 VARCHAR(200) 	DEFAULT  "" ,
pro_img19 LONGBLOB,
pro_mrp19 VARCHAR(200) 	DEFAULT  "" ,
pro_price19 VARCHAR(200) 	DEFAULT  "" ,
pro_tax19 VARCHAR(200) 	DEFAULT  "" ,

pro_name20 VARCHAR(200) 	DEFAULT  "" ,
pro_img20 LONGBLOB,
pro_mrp20 VARCHAR(200) 	DEFAULT  "" ,
pro_price20 VARCHAR(200) 	DEFAULT  "" ,
pro_tax20 VARCHAR(200) 	DEFAULT  "" ,

uploaded_date DATETIME NULL DEFAULT CURRENT_TIMESTAMP 

)';


$product2=mysqli_query($connect,$product);


if($product2) {echo '<div class="success">product table created</div>';} else {echo '<div class="danger">product table Error</div>';}

// product table 

$orders='CREATE TABLE IF NOT EXISTS orders (
id VARCHAR(200) 	DEFAULT  "",
card_id VARCHAR(200) 	DEFAULT  "" ,
user_email VARCHAR(200) 	DEFAULT  "" ,
pro_id VARCHAR(200) 	DEFAULT  "" ,
c_address VARCHAR(200) 	DEFAULT  "" ,
c_email VARCHAR(200) 	DEFAULT  "" ,
c_contact VARCHAR(200) 	DEFAULT  "" ,
c_name VARCHAR(200) 	DEFAULT  "" ,
c_city VARCHAR(200) 	DEFAULT  "" ,
c_state VARCHAR(200) 	DEFAULT  "" ,
c_pincode VARCHAR(200) 	DEFAULT  "" ,
payment_type VARCHAR(200) 	DEFAULT  "" ,
payment_amount VARCHAR(200) 	DEFAULT  "" ,
order_status VARCHAR(200) 	DEFAULT  "" ,

uploaded_date DATETIME NULL DEFAULT CURRENT_TIMESTAMP 

)';


$orders2=mysqli_query($connect,$orders);


if($orders2) {echo '<div class="success">orders table created</div>';} else {echo '<div class="danger">orders table Error</div>';}




// views 




$views='CREATE TABLE IF NOT EXISTS views (
id INT(6)  AUTO_INCREMENT PRIMARY KEY,
ip VARCHAR(100) 	DEFAULT  "" ,
card_id VARCHAR(100) 	DEFAULT  "",
uploaded_date DATETIME NULL DEFAULT CURRENT_TIMESTAMP 

)';


$views2=mysqli_query($connect,$views);


if($views2) {echo '<div class="success">views table created</div>';} else {echo '<div class="danger">views table Error</div>';}

