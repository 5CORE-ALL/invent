-- Server fallback: add customer_followups.resolved_at when you cannot run `php artisan migrate --force`.
-- Safe to run multiple times (skips ALTER if column already exists).
-- MySQL 5.7+ / 8.x

SET @db := DATABASE();
SET @exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'customer_followups'
    AND COLUMN_NAME = 'resolved_at'
);

SET @sql := IF(
  @exists = 0,
  'ALTER TABLE `customer_followups` ADD COLUMN `resolved_at` DATETIME NULL',
  'SELECT ''column resolved_at already exists'' AS note'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Approximate historical resolve time for rows migrated before tracking (matches Laravel migration).
UPDATE `customer_followups`
SET `resolved_at` = `updated_at`
WHERE `status` = 'Resolved'
  AND (`resolved_at` IS NULL OR `resolved_at` = '0000-00-00 00:00:00');
