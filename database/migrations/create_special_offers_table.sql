-- Create card_special_offers table for storing special offers
CREATE TABLE IF NOT EXISTS `card_special_offers` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
