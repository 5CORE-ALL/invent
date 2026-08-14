<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 1-day (L1) and 7-day (L7) view columns on marketplace tables that already store views.
     *
     * @return list<array{0: string, 1: list<string>}>
     */
    private function columns(): array
    {
        return [
            ['amazon_datsheets', ['sessions_l1']],
            ['ebay_metrics', ['l1_views']],
            ['ebay_2_metrics', ['l1_views']],
            ['ebay_3_metrics', ['l1_views']],
            ['shopify_skus', ['views_l1', 'views_l7']],
            ['temu_metrics', ['product_clicks_l1', 'product_clicks_l7']],
            ['temu2_metrics', ['product_clicks_l1', 'product_clicks_l7']],
            ['tiktok_products', ['views_l1', 'views_l7']],
            ['tiktok_products_two', ['views_l1', 'views_l7']],
            ['shein_metrics', ['views_l1', 'views_l7']],
            ['reverb_products', ['views_l1', 'views_l7']],
            ['reverb_listings', ['views_l1', 'views_l7']],
            ['walmart_pricing_sales', ['page_views_l1', 'page_views_l7']],
            ['walmart_product_sheets', ['views_l1', 'views_l7']],
            ['wayfair_product_sheets', ['views_l1', 'views_l7']],
            ['doba_sheet_data', ['views_l1', 'views_l7']],
            ['faire_products_sheets', ['views_l1', 'views_l7']],
            ['mercari_w_ship_sheet_data', ['views_l1', 'views_l7']],
            ['mercari_wo_ship_sheet_data', ['views_l1', 'views_l7']],
            ['fb_marketplace_sheet_data', ['views_l1', 'views_l7']],
            ['fb_shop_sheet_data', ['views_l1', 'views_l7']],
            ['instagram_shop_sheet_data', ['views_l1', 'views_l7']],
            ['top_dawg_sheet_data', ['views_l1', 'views_l7']],
            ['topdawg_products', ['views_l1', 'views_l7']],
            ['business_five_core_sheet_data', ['views_l1', 'views_l7']],
            ['aliexpress_sheet_data', ['views_l1', 'views_l7']],
            ['channel_master_calculated_data', ['yesterday_views', 'l7_views']],
            ['channel_yesterday_views', ['l7_views']],
        ];
    }

    public function up(): void
    {
        foreach ($this->columns() as [$table, $cols]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($table, $cols) {
                    foreach ($cols as $col) {
                        if (! Schema::hasColumn($table, $col)) {
                            $blueprint->unsignedInteger($col)->nullable();
                        }
                    }
                });
            } catch (\Throwable $e) {
                // Some older sheet tables have invalid timestamp defaults and reject ALTER.
            }
        }
    }

    public function down(): void
    {
        foreach ($this->columns() as [$table, $cols]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $drop = array_values(array_filter($cols, fn ($col) => Schema::hasColumn($table, $col)));
            if ($drop === []) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($drop) {
                $blueprint->dropColumn($drop);
            });
        }
    }
};
