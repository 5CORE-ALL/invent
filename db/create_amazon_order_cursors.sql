-- Amazon Order Cursors table for cursor-based pagination
-- Stores NextToken and state for resumable fetches

CREATE TABLE IF NOT EXISTS `amazon_order_cursors` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cursor_key` varchar(255) NOT NULL COMMENT 'Unique key based on date range',
  `next_token` text DEFAULT NULL COMMENT 'Amazon NextToken for pagination',
  `status` enum('running','failed','completed') NOT NULL DEFAULT 'running',
  `started_at` timestamp NULL DEFAULT NULL,
  `last_page_at` timestamp NULL DEFAULT NULL COMMENT 'Last successful page fetch',
  `completed_at` timestamp NULL DEFAULT NULL,
  `orders_fetched` int(11) NOT NULL DEFAULT 0 COMMENT 'Total orders fetched in this cursor',
  `pages_fetched` int(11) NOT NULL DEFAULT 0 COMMENT 'Total pages fetched',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `amazon_order_cursors_cursor_key_unique` (`cursor_key`),
  KEY `idx_status` (`status`),
  KEY `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
