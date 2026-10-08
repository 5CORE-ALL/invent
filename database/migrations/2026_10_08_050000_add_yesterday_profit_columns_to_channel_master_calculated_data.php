<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Y GROI% / YNPFT% / YNROI% were derived from the L30 percentages, so the Y Sales
 * factor cancelled and they always equalled G Roi / N PFT / N ROI. Store the real
 * one-day profit, COGS, ad spend and sales so those columns measure yesterday.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('channel_master_calculated_data')) {
            return;
        }

        Schema::table('channel_master_calculated_data', function (Blueprint $table) {
            if (! Schema::hasColumn('channel_master_calculated_data', 'y_day_sales')) {
                $table->decimal('y_day_sales', 15, 2)->nullable()->after('yesterday_sales');
            }
            if (! Schema::hasColumn('channel_master_calculated_data', 'y_pft')) {
                $table->decimal('y_pft', 15, 2)->nullable()->after('y_day_sales');
            }
            if (! Schema::hasColumn('channel_master_calculated_data', 'y_cogs')) {
                $table->decimal('y_cogs', 15, 2)->nullable()->after('y_pft');
            }
            if (! Schema::hasColumn('channel_master_calculated_data', 'y_ad_spend')) {
                $table->decimal('y_ad_spend', 15, 2)->nullable()->after('y_cogs');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('channel_master_calculated_data')) {
            return;
        }

        Schema::table('channel_master_calculated_data', function (Blueprint $table) {
            foreach (['y_ad_spend', 'y_cogs', 'y_pft', 'y_day_sales'] as $column) {
                if (Schema::hasColumn('channel_master_calculated_data', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
