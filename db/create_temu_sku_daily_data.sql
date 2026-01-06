-- Create temu_sku_daily_data table for storing daily metrics
-- Run this SQL manually in phpMyAdmin or MySQL

CREATE TABLE IF NOT EXISTS `temu_sku_daily_data` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku` varchar(255) NOT NULL,
  `record_date` date NOT NULL,
  `base_price` decimal(10,2) DEFAULT NULL,
  `product_clicks` int(11) NOT NULL DEFAULT 0,
  `temu_l30` int(11) NOT NULL DEFAULT 0,
  `cvr_percent` decimal(10,2) NOT NULL DEFAULT 0.00,
  `spend` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `temu_sku_daily_data_sku_record_date_unique` (`sku`, `record_date`),
  KEY `temu_sku_daily_data_sku_index` (`sku`),
  KEY `temu_sku_daily_data_record_date_index` (`record_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Success message
SELECT 'Table temu_sku_daily_data created successfully!' AS message;

