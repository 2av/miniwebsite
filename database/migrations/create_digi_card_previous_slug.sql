-- Previous MiniWebsite URL slug per card (digi_card.id is too wide for another column)
CREATE TABLE IF NOT EXISTS digi_card_previous_slug (
  digi_card_id INT UNSIGNED NOT NULL PRIMARY KEY,
  previous_slug VARCHAR(255) NOT NULL,
  UNIQUE KEY uk_previous_slug (previous_slug(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
