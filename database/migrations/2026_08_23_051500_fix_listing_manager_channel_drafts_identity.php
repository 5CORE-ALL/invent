<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('listing_manager_channel_drafts')) {
            return;
        }

        $indexes = Schema::getIndexes('listing_manager_channel_drafts');
        $hasPrimary = collect($indexes)->contains(fn (array $idx) => ! empty($idx['primary']));
        $hasSkuUnique = collect($indexes)->contains(function (array $idx) {
            $cols = array_map('strtolower', $idx['columns'] ?? []);
            sort($cols);

            return ! empty($idx['unique']) && $cols === ['channel_id', 'seller_sku'];
        });

        if (! $hasPrimary) {
            DB::statement('ALTER TABLE listing_manager_channel_drafts MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY');
        } else {
            DB::statement('ALTER TABLE listing_manager_channel_drafts MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }

        if (! $hasSkuUnique) {
            Schema::table('listing_manager_channel_drafts', function (Blueprint $table) {
                $table->unique(['channel_id', 'seller_sku'], 'lm_drafts_channel_sku_unique');
            });
        }
    }

    public function down(): void
    {
        // Keep identity and uniqueness; they are required for drafts.
    }
};
