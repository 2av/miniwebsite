<?php
// Migration script to convert profile images from database to files
// Run this once to migrate existing profile images

require_once('../common/config.php');

// Create upload directory if it doesn't exist
$upload_dir = 'profile_images/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Get all users with profile images
$query = "SELECT user_email, user_image FROM customer_login WHERE user_image IS NOT NULL AND user_image != '' AND user_image NOT LIKE 'profile_images/%'";
$result = mysqli_query($connect, $query);

$migrated = 0;
$errors = 0;

while($row = mysqli_fetch_assoc($result)) {
    $user_email = $row['user_email'];
    $image_data = $row['user_image'];
    
    // Skip if it's already a file path
    if(strpos($image_data, 'profile_images/') === 0) {
        continue;
    }
    
    // Generate unique filename
    $unique_filename = uniqid() . '_' . time() . '.jpg';
    $upload_path = $upload_dir . $unique_filename;
    
    // Save image data to file
    if(file_put_contents($upload_path, $image_data)) {
        // Update database with file path
        $stmt = $connect->prepare("UPDATE customer_login SET user_image = ? WHERE user_email = ?");
        $stmt->bind_param("ss", $upload_path, $user_email);
        
        if($stmt->execute()) {
            $migrated++;
            echo "Migrated profile image for: $user_email<br>";
        } else {
            $errors++;
            echo "Error updating database for: $user_email<br>";
            unlink($upload_path); // Delete the file if database update failed
        }
        
        $stmt->close();
    } else {
        $errors++;
        echo "Error saving file for: $user_email<br>";
    }
}

echo "<br>Migration completed!<br>";
echo "Successfully migrated: $migrated images<br>";
echo "Errors: $errors<br>";
?>
