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
        // Add discount columns to Shopify B2C daily data
        Schema::table('shopify_b2c_daily_data', function (Blueprint $table) {
            $table->decimal('original_price', 10, 2)->default(0)->after('price');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('original_price');
        });

        // Add discount columns to Shopify B2B daily data
        Schema::table('shopify_b2b_daily_data', function (Blueprint $table) {
            $table->decimal('original_price', 10, 2)->default(0)->after('price');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('original_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopify_b2c_daily_data', function (Blueprint $table) {
            $table->dropColumn(['original_price', 'discount_amount']);
        });

        Schema::table('shopify_b2b_daily_data', function (Blueprint $table) {
            $table->dropColumn(['original_price', 'discount_amount']);
        });
    }
};
