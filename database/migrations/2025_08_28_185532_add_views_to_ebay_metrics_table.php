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
        if (! Schema::hasTable('ebay_metrics') || Schema::hasColumn('ebay_metrics', 'views')) {
            return;
        }

        $afterEbayPrice = Schema::hasColumn('ebay_metrics', 'ebay_price');

        Schema::table('ebay_metrics', function (Blueprint $table) use ($afterEbayPrice): void {
            if ($afterEbayPrice) {
                $table->integer('views')->default(0)->after('ebay_price');
            } else {
                $table->integer('views')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ebay_metrics') || ! Schema::hasColumn('ebay_metrics', 'views')) {
            return;
        }

        Schema::table('ebay_metrics', function (Blueprint $table) {
            $table->dropColumn('views');
        });
    }
};
