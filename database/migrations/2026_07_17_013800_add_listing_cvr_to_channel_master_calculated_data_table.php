<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('channel_master_calculated_data')) {
            return;
        }

        if (! Schema::hasColumn('channel_master_calculated_data', 'listing_cvr')) {
            Schema::table('channel_master_calculated_data', function (Blueprint $table) {
                // Listing CVR (Qty/OV L30 ÷ Total Views) — distinct from ads `cvr` (Ads CVR).
                // Shopify: matches /shopify-b2c-pricing CVR% badge. Temu: temu_l30 ÷ product_clicks.
                $table->decimal('listing_cvr', 10, 2)->nullable()->after('total_views');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('channel_master_calculated_data')
            && Schema::hasColumn('channel_master_calculated_data', 'listing_cvr')) {
            Schema::table('channel_master_calculated_data', function (Blueprint $table) {
                $table->dropColumn('listing_cvr');
            });
        }
    }
};
