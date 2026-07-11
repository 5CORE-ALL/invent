<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Amazon product titles (and image CDN URLs) regularly exceed the default
 * VARCHAR(255) that `title`/`image` were created with, causing
 * SQLSTATE[22001] "Data too long for column 'title'". Widen both to TEXT to
 * match the Shein competitor table. Uses raw MySQL statements so we don't
 * depend on doctrine/dbal for ->change() on Laravel 10.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('amazon_competitor_asins')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasColumn('amazon_competitor_asins', 'title')) {
            DB::statement('ALTER TABLE `amazon_competitor_asins` MODIFY COLUMN `title` TEXT NULL');
        }
        if (Schema::hasColumn('amazon_competitor_asins', 'image')) {
            DB::statement('ALTER TABLE `amazon_competitor_asins` MODIFY COLUMN `image` TEXT NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('amazon_competitor_asins')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasColumn('amazon_competitor_asins', 'title')) {
            DB::statement('ALTER TABLE `amazon_competitor_asins` MODIFY COLUMN `title` VARCHAR(255) NULL');
        }
        if (Schema::hasColumn('amazon_competitor_asins', 'image')) {
            DB::statement('ALTER TABLE `amazon_competitor_asins` MODIFY COLUMN `image` VARCHAR(255) NULL');
        }
    }
};
