<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * amazon.co.uk and similar values exceed VARCHAR(10) from create_amazon_sku_competitors.
     */
    public function up(): void
    {
        if (! Schema::hasTable('amazon_sku_competitors') || ! Schema::hasColumn('amazon_sku_competitors', 'marketplace')) {
            return;
        }

        DB::statement('ALTER TABLE `amazon_sku_competitors` MODIFY `marketplace` VARCHAR(64) NOT NULL DEFAULT \'amazon\'');
    }

    /**
     * Restore shorter column (may truncate values longer than 10 chars).
     */
    public function down(): void
    {
        if (! Schema::hasTable('amazon_sku_competitors') || ! Schema::hasColumn('amazon_sku_competitors', 'marketplace')) {
            return;
        }

        DB::statement('ALTER TABLE `amazon_sku_competitors` MODIFY `marketplace` VARCHAR(10) NOT NULL DEFAULT \'amazon\'');
    }
};
