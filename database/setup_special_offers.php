<?php
/**
 * Setup script to create the card_special_offers table
 * Run this once to set up the Special Offers feature
 */

require_once(__DIR__ . '/../app/config/database.php');

$sql = "CREATE TABLE IF NOT EXISTS `card_special_offers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `card_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `offer_title` varchar(255) NOT NULL,
  `offer_description` longtext,
  `offer_image` varchar(255),
  `badge` varchar(100),
  `discount_percentage` int(3) DEFAULT 0,
  `start_date` date,
  `start_time` time,
  `end_date` date,
  `end_time` time,
  `status` varchar(50) DEFAULT 'Active',
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `card_id` (`card_id`),
  KEY `user_id` (`user_id`),
  KEY `display_order` (`display_order`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($connect->query($sql) === TRUE) {
    echo "Table 'card_special_offers' created successfully!<br>";
} else {
    echo "Error creating table: " . $connect->error . "<br>";
}

// Create the upload directory for offer images
$uploadDir = __DIR__ . '/../assets/upload/websites/special-offers/';
if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0775, true)) {
        echo "Directory created successfully: " . $uploadDir . "<br>";
    } else {
        echo "Failed to create directory: " . $uploadDir . "<br>";
    }
} else {
    echo "Directory already exists: " . $uploadDir . "<br>";
}

echo "<br><strong>Setup Complete!</strong><br>";
echo "The Special Offers feature is now ready to use.<br>";
echo "You can now access the Special Offers page from the website menu.";

$connect->close();
?>
