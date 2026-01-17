<?php
require_once(__DIR__ . '/../app/config/database.php');

// Check column structure
$result = mysqli_query($connect, "DESCRIBE digi_card");
while($row = mysqli_fetch_array($result)) {
    if($row['Field'] == 'complimentary_enabled') {
        echo "Column exists: " . $row['Field'] . " - Type: " . $row['Type'] . " - Default: " . $row['Default'] . "<br>";
        break;
    }
}

// Check current values
$data_query = mysqli_query($connect, "SELECT id, complimentary_enabled FROM digi_card WHERE id IN (SELECT id FROM digi_card ORDER BY id DESC LIMIT 5)");
echo "<br>Recent records:<br>";
while($data_row = mysqli_fetch_array($data_query)) {
    echo "ID: " . $data_row['id'] . " - Status: '" . $data_row['complimentary_enabled'] . "'<br>";
}

// Test update
if(isset($_GET['test_update'])) {
    $test_id = $_GET['test_update'];
    $test_update = mysqli_query($connect, "UPDATE digi_card SET complimentary_enabled='Yes' WHERE id='$test_id'");
    if($test_update) {
        echo "<br>Test update successful for ID: $test_id";
    } else {
        echo "<br>Test update failed: " . mysqli_error($connect);
    }
}
?>


