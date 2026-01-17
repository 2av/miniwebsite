<?php
require_once(__DIR__ . '/../app/config/database.php');

// Check if column exists
$result = mysqli_query($connect, "SHOW COLUMNS FROM digi_card LIKE 'Complimentary_enabled'");
if(mysqli_num_rows($result) == 0) {
    // Add the column
    $alter = mysqli_query($connect, "ALTER TABLE digi_card ADD Complimentary_enabled VARCHAR(10) DEFAULT 'No'");
    if($alter) {
        echo "Column added successfully";
    } else {
        echo "Error: " . mysqli_error($connect);
    }
} else {
    echo "Column already exists";
}

// Test the specific record
if(isset($_GET['test_id'])) {
    $test_query = mysqli_query($connect, "SELECT id, Complimentary_enabled FROM digi_card WHERE id = '".$_GET['test_id']."'");
    $test_row = mysqli_fetch_array($test_query);
    echo "<br>Record ".$_GET['test_id'].": " . $test_row['Complimentary_enabled'];
}
?>


