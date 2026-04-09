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
        if (! Schema::hasTable('ebay_3_metrics') || Schema::hasColumn('ebay_3_metrics', 'views')) {
            return;
        }

        $afterEbayPrice = Schema::hasColumn('ebay_3_metrics', 'ebay_price');

        Schema::table('ebay_3_metrics', function (Blueprint $table) use ($afterEbayPrice): void {
            if ($afterEbayPrice) {
                $table->unsignedBigInteger('views')->nullable()->after('ebay_price');
            } else {
                $table->unsignedBigInteger('views')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ebay_3_metrics') || ! Schema::hasColumn('ebay_3_metrics', 'views')) {
            return;
        }

        Schema::table('ebay_3_metrics', function (Blueprint $table) {
            $table->dropColumn('views');
        });
    }
};
