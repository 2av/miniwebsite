<?php

date_default_timezone_set("Asia/Kolkata");

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session parameters
    $session_lifetime = 86400; // 24 hours in seconds
    $secure = isset($_SERVER['HTTPS']); // Set to true if using HTTPS
    $httponly = true; // Prevents JavaScript access to session cookie

    // Set the session cookie parameters
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'domain' => '',  // Current domain
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'  // Allows session to persist across same-site requests
    ]);
    
    session_start();
}

if($_SERVER['HTTP_HOST']=="test.miniwebsite.in"){
    $connect=mysqli_connect("localhost","miniweb_vcard_test","miniweb_vcard_test","miniweb_vcard_test") or die ('Database not available...');
} elseif($_SERVER['HTTP_HOST']=="localhost"){
    $connect=mysqli_connect("localhost","root","","mydigibr_card") or die ('Database not available...');
} else {
    $connect=mysqli_connect("localhost","miniweb_vcard","miniweb_vcard","miniweb_vcard") or die ('Connection issue #567844 Error');
}

$date=date('Y-m-d H:i:s');

?>

<title> <?php echo $_SERVER['HTTP_HOST']; ?> || Digital Visiting Card</title>

<head>

<link rel="icon" href="images/favicon.png" type="image/png">

 <meta name="keywords" content="Digital Visiting Card Online, Business Card Online, Visiting card, v card">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">

 <meta name="description" content="Best digital visiting card online with many designs, Now create in just 5 minutes and get it instantly.">
  <!-- Required meta tags -->
  <meta charset="utf-8" />
  <link rel='stylesheet' href='panel/all.css' integrity='sha384-lZN37f5QGtY3VHgisS14W3ExzMWZxybE1SJSEsQp9S+oqd12jhcu+A56Ebc1zFSJ' crossorigin='anonymous'>
  
  <link rel="stylesheet" href="panel/awesome.min.css">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
 <meta      name='viewport'      content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' />

<link rel="stylesheet" href="css.css" >
<link rel="stylesheet" href="mobile_css.css" >
<script src="master_js.js"></script>


</head>
