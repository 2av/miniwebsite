<?php
include '../config.php';

// Add Complimentary_enabled column if it doesn't exist
$check_column = mysqli_query($connect, "SHOW COLUMNS FROM digi_card LIKE 'Complimentary_enabled'");
if(mysqli_num_rows($check_column) == 0) {
    $alter_query = "ALTER TABLE digi_card ADD Complimentary_enabled VARCHAR(10) DEFAULT 'No'";
    if(mysqli_query($connect, $alter_query)) {
        echo "Column Complimentary_enabled added successfully";
    } else {
        echo "Error adding column: " . mysqli_error($connect);
    }
} else {
    echo "Column already exists";
}
?>


