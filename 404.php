<?php
// Simple global 404 page for Mini Website

http_response_code(404);

// Include role helper so we know if user is logged in
require_once __DIR__ . '/app/helpers/role_helper.php';
$current_role = get_current_user_role();

// Decide where "Home" should go
$base_path = ''; // adjust if project is under subfolder like /miniwebsite
$home_url = $base_path . '/';
if ($current_role !== null) {
    $home_url = $base_path . '/user/dashboard';
}

// Try to include global header/footer if they exist to keep branding consistent
$has_global_header = file_exists(__DIR__ . '/header.php');
$has_global_footer = file_exists(__DIR__ . '/footer.php');

if ($has_global_header) {
    include __DIR__ . '/header.php';
} else {
    ?><!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Page Not Found - Mini Website</title>
        <link rel="stylesheet" href="assets/css/styles.css">
        <link rel="stylesheet" href="assets/css/common.css">
        <style>
            .not-found-wrapper {
                min-height: 80vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 40px 15px;
            }
            .not-found-card {
                max-width: 520px;
                width: 100%;
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.06);
                text-align: center;
                padding: 30px 24px;
            }
            .not-found-card h1 {
                font-size: 28px;
                margin: 16px 0 8px;
            }
            .not-found-card p {
                font-size: 15px;
                color: #666;
                margin-bottom: 20px;
            }
            .not-found-card .btn-primary {
                padding: 10px 24px;
                font-weight: 600;
                border-radius: 6px;
            }
        </style>
    </head>
    <body><?php
}
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area not-found-wrapper">
        <div class="not-found-card">
            <img src="assets/images/error-404-monochrome.svg" alt="404" style="max-width:220px; width:70%; margin-bottom:20px;">
            <h1>Oops! Page not found</h1>
            <p>
                The page you are looking for doesn’t exist, was moved, or the URL is incorrect.
            </p>
            <a href="<?php echo htmlspecialchars($home_url); ?>" class="btn btn-primary">
                Go to Home
            </a>
        </div>
    </div>
</main>

<?php
if ($has_global_footer) {
    include __DIR__ . '/footer.php';
} else {
    ?></body></html><?php
}
?>
