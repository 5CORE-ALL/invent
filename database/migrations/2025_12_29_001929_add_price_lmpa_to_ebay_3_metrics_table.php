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

        if (! Schema::hasColumn('ebay_3_metrics', 'price_lmpa')) {
            Schema::table('ebay_3_metrics', function (Blueprint $table) {
                $table->decimal('price_lmpa', 10, 2)->nullable()->after('views');
            });
        }
        if (! Schema::hasColumn('ebay_3_metrics', 'lmp_link')) {
            Schema::table('ebay_3_metrics', function (Blueprint $table) {
                $table->string('lmp_link', 500)->nullable()->after('price_lmpa');
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

        $columns = ['price_lmpa', 'lmp_link'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('ebay_3_metrics', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('ebay_3_metrics', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
