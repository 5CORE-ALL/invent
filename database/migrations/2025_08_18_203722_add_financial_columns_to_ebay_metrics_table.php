<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ebay_metrics')) {
            return;
        }

        if (! Schema::hasColumn('ebay_metrics', 'total_pft')) {
            Schema::table('ebay_metrics', function (Blueprint $table) {
                $table->decimal('total_pft', 12, 2)->nullable()->after('ebay_price');
            });
        }
        if (! Schema::hasColumn('ebay_metrics', 't_sale_l30')) {
            Schema::table('ebay_metrics', function (Blueprint $table) {
                $table->decimal('t_sale_l30', 12, 2)->nullable()->after('total_pft');
            });
        }
        if (! Schema::hasColumn('ebay_metrics', 'pft_percentage')) {
            Schema::table('ebay_metrics', function (Blueprint $table) {
                $table->decimal('pft_percentage', 8, 2)->nullable()->after('t_sale_l30');
            });
        }
        if (! Schema::hasColumn('ebay_metrics', 'roi_percentage')) {
            Schema::table('ebay_metrics', function (Blueprint $table) {
                $table->decimal('roi_percentage', 8, 2)->nullable()->after('pft_percentage');
            });
        }
        if (! Schema::hasColumn('ebay_metrics', 't_cogs')) {
            Schema::table('ebay_metrics', function (Blueprint $table) {
                $table->decimal('t_cogs', 12, 2)->nullable()->after('roi_percentage');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ebay_metrics')) {
            return;
        }

        $columns = ['total_pft', 't_sale_l30', 'pft_percentage', 'roi_percentage', 't_cogs'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('ebay_metrics', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('ebay_metrics', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
