<?php
require_once __DIR__ . '/app/config/database.php';

try {
    $query = mysqli_query($connect, "SELECT * FROM content_management WHERE content_type='mw_full_franchise_agreement' AND is_active=1");

    if (mysqli_num_rows($query) > 0) {
        $content = mysqli_fetch_array($query);
        $page_title = $content['title'];
        $page_content = $content['content'];
        $meta_description = $content['meta_description'];
        $meta_keywords = $content['meta_keywords'];
    } else {
        $page_title = 'MW Full Franchise Agreement';
        $page_content = '<p>Content not available. Please contact administrator.</p>';
        $meta_description = 'MW Full Franchise Agreement for MiniWebsite';
        $meta_keywords = 'franchise, agreement, miniwebsite';
    }
} catch (Exception $e) {
    $page_title = 'MW Full Franchise Agreement';
    $page_content = '<p>Content not available. Please contact administrator.</p>';
    $meta_description = 'MW Full Franchise Agreement for MiniWebsite';
    $meta_keywords = 'franchise, agreement, miniwebsite';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - MiniWebsite</title>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords); ?>">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.4;
            margin: 0;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php if (isset($content['last_updated'])): ?>
        <div style="font-size: 0.9em; color: #6c757d; font-style: italic; margin-bottom: 20px;">
            Last updated: <?php echo date('F j, Y', strtotime($content['last_updated'])); ?>
        </div>
        <?php endif; ?>

        <div class="content">
            <?php echo $page_content; ?>
        </div>
    </div>
</body>

</html>
