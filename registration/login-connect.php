<?php
// Centralized database + session
require_once __DIR__ . '/../app/config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" />
    <title>Mini Website Registration</title>

    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon.ico">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Baloo+Bhai+2:wght@400..800&family=Baloo+Bhaina+2:wght@400..800&family=Barlow:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="../assets/css/font-awesome.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/layout.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="login-styles.css">

     <script src="../assets/js/jquery.slim.min.js"></script>
     <script>
       if (!window.jQuery) {
         document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
       }
     </script>
     <script src="../assets/js/bootstrap.bundle.min.js"></script>
     <script>
       if (!window.bootstrap && !(window.jQuery && window.jQuery.fn && window.jQuery.fn.dropdown)) {
         document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"><\/script>');
       }
     </script>
</head>
<body>

