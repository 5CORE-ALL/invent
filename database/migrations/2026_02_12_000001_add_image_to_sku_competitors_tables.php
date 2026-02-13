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
        if (Schema::hasTable('amazon_sku_competitors') && !Schema::hasColumn('amazon_sku_competitors', 'image')) {
            Schema::table('amazon_sku_competitors', function (Blueprint $table) {
                $table->text('image')->nullable()->after('product_link');
            });
        }

        if (Schema::hasTable('ebay_sku_competitors') && !Schema::hasColumn('ebay_sku_competitors', 'image')) {
            Schema::table('ebay_sku_competitors', function (Blueprint $table) {
                $table->text('image')->nullable()->after('product_link');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('amazon_sku_competitors') && Schema::hasColumn('amazon_sku_competitors', 'image')) {
            Schema::table('amazon_sku_competitors', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }

        if (Schema::hasTable('ebay_sku_competitors') && Schema::hasColumn('ebay_sku_competitors', 'image')) {
            Schema::table('ebay_sku_competitors', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }
    }
};
