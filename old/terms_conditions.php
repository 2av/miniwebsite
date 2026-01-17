<?php
// Database connection
$db_host = "p004.bom1.mysecurecloudhost.com";
$db_user = "wwwmoody_miniweb_vcard";
$db_pass = "miniweb_vcard";
$db_name = "miniweb_vcard";

try {
    $connect = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    // Get terms and conditions content
    $query = mysqli_query($connect, "SELECT * FROM content_management WHERE content_type='terms_conditions' AND is_active=1");
    
    if(mysqli_num_rows($query) > 0) {
        $content = mysqli_fetch_array($query);
        $page_title = $content['title'];
        $page_content = $content['content'];
        $meta_description = $content['meta_description'];
        $meta_keywords = $content['meta_keywords'];
    } else {
        // Fallback to default content
        $page_title = "Terms and Conditions";
        $page_content = "<p>Content not available. Please contact administrator.</p>";
        $meta_description = "Terms and Conditions for MiniWebsite";
        $meta_keywords = "terms, conditions, legal";
    }
} catch (Exception $e) {
    // Fallback content
    $page_title = "Terms and Conditions";
    $page_content = "<p>Content not available. Please contact administrator.</p>";
    $meta_description = "Terms and Conditions for MiniWebsite";
    $meta_keywords = "terms, conditions, legal";
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
          
            
        }

        .container {
           
            margin: auto;
            
            padding: 20px;
            
            
        }

        
 
    </style>
</head>

<body>
    <div class="container">
        <?php if(isset($content['last_updated'])): ?>
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