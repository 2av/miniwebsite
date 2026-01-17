<?php
require('connect_ajax.php');

header('Content-Type: application/json');

try {
    // SQL to create table
    $create_table_sql = "
    CREATE TABLE IF NOT EXISTS `franchisee_verification` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_email` varchar(255) NOT NULL,
      `gpay_document` varchar(255) DEFAULT NULL,
      `paytm_document` varchar(255) DEFAULT NULL,
      `status` enum('submitted','approved','rejected') DEFAULT 'submitted',
      `admin_remarks` text DEFAULT NULL,
      `reviewed_at` datetime DEFAULT NULL,
      `reviewed_by` varchar(100) DEFAULT NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_email` (`user_email`),
      KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if(mysqli_query($connect, $create_table_sql)) {
        echo json_encode(['success' => true, 'message' => 'Table created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error creating table: ' . mysqli_error($connect)]);
    }
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>



