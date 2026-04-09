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
        if (! Schema::hasTable('ebay_3_metrics')) {
            return;
        }

        if (! Schema::hasColumn('ebay_3_metrics', 'ebay_l7')) {
            Schema::table('ebay_3_metrics', function (Blueprint $table) {
                $table->integer('ebay_l7')->nullable()->after('ebay_l60');
            });
        }
        if (! Schema::hasColumn('ebay_3_metrics', 'l7_views')) {
            Schema::table('ebay_3_metrics', function (Blueprint $table) {
                $table->integer('l7_views')->nullable()->after('views');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ebay_3_metrics')) {
            return;
        }

        $columns = ['ebay_l7', 'l7_views'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('ebay_3_metrics', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('ebay_3_metrics', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
