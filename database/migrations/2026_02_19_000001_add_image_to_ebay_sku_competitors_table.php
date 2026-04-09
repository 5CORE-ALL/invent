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
        if (! Schema::hasTable('ebay_sku_competitors')) {
            return;
        }
        if (Schema::hasColumn('ebay_sku_competitors', 'image')) {
            return;
        }

        Schema::table('ebay_sku_competitors', function (Blueprint $table) {
            $table->text('image')->nullable()->after('product_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ebay_sku_competitors')) {
            return;
        }
        if (! Schema::hasColumn('ebay_sku_competitors', 'image')) {
            return;
        }

        Schema::table('ebay_sku_competitors', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
