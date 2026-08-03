<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amazon_sku_competitors', function (Blueprint $table) {
            if (! Schema::hasColumn('amazon_sku_competitors', 'stock')) {
                $table->string('stock', 255)->nullable()->after('delivery');
            }
            if (! Schema::hasColumn('amazon_sku_competitors', 'stock_quantity')) {
                $table->unsignedInteger('stock_quantity')->nullable()->after('stock');
            }
        });

        Schema::table('amazon_competitor_asins', function (Blueprint $table) {
            if (! Schema::hasColumn('amazon_competitor_asins', 'stock')) {
                $table->string('stock', 255)->nullable()->after('delivery');
            }
            if (! Schema::hasColumn('amazon_competitor_asins', 'stock_quantity')) {
                $table->unsignedInteger('stock_quantity')->nullable()->after('stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('amazon_sku_competitors', function (Blueprint $table) {
            foreach (['stock_quantity', 'stock'] as $col) {
                if (Schema::hasColumn('amazon_sku_competitors', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('amazon_competitor_asins', function (Blueprint $table) {
            foreach (['stock_quantity', 'stock'] as $col) {
                if (Schema::hasColumn('amazon_competitor_asins', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
