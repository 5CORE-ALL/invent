<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop Temu 1 CSV/Google-sheet tables only (not Temu 2, not temu_lmp).
     * Ads kept: temu_ad_data, temu_ads_api_reports, temu_campaign_reports, temu2_campaign_reports.
     * Kept sheets: temu2_pricing, temu2_daily_data*, temu2_view_data, temu_lmp.
     */
    public function up(): void
    {
        $tables = [
            'temu_pricing',
            'temu_daily_data',
            'temu_daily_data_l60',
            'temu_daily_data_l7',
            'temu_daily_data_l70',
            'temu_view_data',
            'temu_view_data_l7',
            'temu_view_data_l7_to_l14',
            'temu_raw_data',
            'temu_product_sheets',
            'temu_sheet_data_total',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Temu 1 sheet sync removed — tables are not recreated.
    }
};
