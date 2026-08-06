<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Channel-specific L30 sales qty (SKU × marketplace), separate from overall Shopify ov_l30.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_errors_fix_calculated_data', function (Blueprint $table) {
            if (! Schema::hasColumn('pricing_errors_fix_calculated_data', 'l30')) {
                $table->decimal('l30', 12, 2)->default(0)->after('ov_l30');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pricing_errors_fix_calculated_data', function (Blueprint $table) {
            if (Schema::hasColumn('pricing_errors_fix_calculated_data', 'l30')) {
                $table->dropColumn('l30');
            }
        });
    }
};
