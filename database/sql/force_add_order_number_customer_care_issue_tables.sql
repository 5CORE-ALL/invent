-- Optional manual run (e.g. phpMyAdmin) if you cannot use Artisan.
-- If a line errors with "Duplicate column", skip it — column already exists.
-- Charset: VARCHAR(255) NULL, placed after `sku` (MySQL/MariaDB).

ALTER TABLE `label_issue_issues` ADD COLUMN `order_number` VARCHAR(255) NULL AFTER `sku`;
ALTER TABLE `label_issue_issue_histories` ADD COLUMN `order_number` VARCHAR(255) NULL AFTER `sku`;
ALTER TABLE `carrier_issue_issues` ADD COLUMN `order_number` VARCHAR(255) NULL AFTER `sku`;
ALTER TABLE `carrier_issue_issue_histories` ADD COLUMN `order_number` VARCHAR(255) NULL AFTER `sku`;
ALTER TABLE `other_issue_issues` ADD COLUMN `order_number` VARCHAR(255) NULL AFTER `sku`;
ALTER TABLE `other_issue_issue_histories` ADD COLUMN `order_number` VARCHAR(255) NULL AFTER `sku`;
ALTER TABLE `c_care_issue_issues` ADD COLUMN `order_number` VARCHAR(255) NULL AFTER `sku`;
ALTER TABLE `c_care_issue_issue_histories` ADD COLUMN `order_number` VARCHAR(255) NULL AFTER `sku`;
ALTER TABLE `listing_issue_issues` ADD COLUMN `order_number` VARCHAR(255) NULL AFTER `sku`;
ALTER TABLE `listing_issue_issue_histories` ADD COLUMN `order_number` VARCHAR(255) NULL AFTER `sku`;

-- If you applied this SQL manually, register the migration so `php artisan migrate` stays in sync:
--   php artisan migrate:status
--   Then insert one row into `migrations` with migration name
--   '2026_04_12_100000_add_order_number_to_label_issue_tables' and the next batch number,
--   or run: php artisan migrate --pretend (should show "Nothing to migrate" once recorded).
