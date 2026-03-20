-- Three separate tables for Walmart Sheet Upload

-- 1. Walmart Price Data Table
CREATE TABLE IF NOT EXISTS `walmart_price_data` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku` VARCHAR(255) NOT NULL,
    `item_id` VARCHAR(255) NULL DEFAULT NULL,
    `product_name` TEXT NULL DEFAULT NULL,
    `lifecycle_status` VARCHAR(255) NULL DEFAULT NULL,
    `publish_status` VARCHAR(255) NULL DEFAULT NULL,
    `price` DECIMAL(10, 2) NULL DEFAULT NULL,
    `currency` VARCHAR(10) NULL DEFAULT NULL,
    `comparison_price` DECIMAL(10, 2) NULL DEFAULT NULL,
    `buy_box_price` DECIMAL(10, 2) NULL DEFAULT NULL,
    `buy_box_shipping_price` DECIMAL(10, 2) NULL DEFAULT NULL,
    `msrp` DECIMAL(10, 2) NULL DEFAULT NULL,
    `ratings` DECIMAL(3, 2) NULL DEFAULT NULL,
    `reviews_count` INT NULL DEFAULT NULL,
    `brand` VARCHAR(255) NULL DEFAULT NULL,
    `product_category` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `walmart_price_data_sku_index` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Walmart Listing Views Data Table
CREATE TABLE IF NOT EXISTS `walmart_listing_views_data` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku` VARCHAR(255) NOT NULL,
    `item_id` VARCHAR(255) NULL DEFAULT NULL,
    `product_name` TEXT NULL DEFAULT NULL,
    `product_type` VARCHAR(255) NULL DEFAULT NULL,
    `listing_quality` VARCHAR(255) NULL DEFAULT NULL,
    `content_discoverability` VARCHAR(255) NULL DEFAULT NULL,
    `ratings_reviews` VARCHAR(255) NULL DEFAULT NULL,
    `competitive_price_score` VARCHAR(255) NULL DEFAULT NULL,
    `shipping_score` VARCHAR(255) NULL DEFAULT NULL,
    `transactibility_score` VARCHAR(255) NULL DEFAULT NULL,
    `conversion_rate` DECIMAL(10, 2) NULL DEFAULT NULL,
    `competitive_price` VARCHAR(255) NULL DEFAULT NULL,
    `walmart_price` DECIMAL(10, 2) NULL DEFAULT NULL,
    `gmv` DECIMAL(10, 2) NULL DEFAULT NULL,
    `ratings` DECIMAL(3, 2) NULL DEFAULT NULL,
    `priority` VARCHAR(50) NULL DEFAULT NULL,
    `oos` VARCHAR(10) NULL DEFAULT NULL,
    `condition` VARCHAR(50) NULL DEFAULT NULL,
    `page_views` INT NULL DEFAULT NULL,
    `total_issues` INT NULL DEFAULT NULL,
    `customer_favourites` VARCHAR(10) NULL DEFAULT NULL,
    `collectible_grade` VARCHAR(50) NULL DEFAULT NULL,
    `fast_free_shipping` VARCHAR(10) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `walmart_listing_views_data_sku_index` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Walmart Order Data Table
CREATE TABLE IF NOT EXISTS `walmart_order_data` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku` VARCHAR(255) NOT NULL,
    `po_number` VARCHAR(255) NULL DEFAULT NULL,
    `order_number` VARCHAR(255) NULL DEFAULT NULL,
    `order_date` DATE NULL DEFAULT NULL,
    `ship_by` DATE NULL DEFAULT NULL,
    `delivery_date` DATE NULL DEFAULT NULL,
    `customer_name` VARCHAR(255) NULL DEFAULT NULL,
    `customer_address` TEXT NULL DEFAULT NULL,
    `qty` INT NULL DEFAULT NULL,
    `item_cost` DECIMAL(10, 2) NULL DEFAULT NULL,
    `shipping_cost` DECIMAL(10, 2) NULL DEFAULT NULL,
    `tax` DECIMAL(10, 2) NULL DEFAULT NULL,
    `status` VARCHAR(255) NULL DEFAULT NULL,
    `carrier` VARCHAR(255) NULL DEFAULT NULL,
    `tracking_number` VARCHAR(255) NULL DEFAULT NULL,
    `tracking_url` TEXT NULL DEFAULT NULL,
    `item_description` TEXT NULL DEFAULT NULL,
    `shipping_method` VARCHAR(255) NULL DEFAULT NULL,
    `fulfillment_entity` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `walmart_order_data_sku_index` (`sku`),
    KEY `walmart_order_data_order_number_index` (`order_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

