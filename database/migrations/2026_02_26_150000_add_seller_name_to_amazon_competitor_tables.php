<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amazon_competitor_asins', function (Blueprint $table) {
            if (!Schema::hasColumn('amazon_competitor_asins', 'seller_name')) {
                $table->string('seller_name', 255)->nullable()->after('title');
            }
        });

        Schema::table('amazon_sku_competitors', function (Blueprint $table) {
            if (!Schema::hasColumn('amazon_sku_competitors', 'seller_name')) {
                $table->string('seller_name', 255)->nullable()->after('product_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('amazon_competitor_asins', function (Blueprint $table) {
            if (Schema::hasColumn('amazon_competitor_asins', 'seller_name')) {
                $table->dropColumn('seller_name');
            }
        });
        Schema::table('amazon_sku_competitors', function (Blueprint $table) {
            if (Schema::hasColumn('amazon_sku_competitors', 'seller_name')) {
                $table->dropColumn('seller_name');
            }
        });
    }
};
