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
        Schema::table('tiktok_sheet_data', function (Blueprint $table) {
            $table->decimal('shopify_tiktok_price', 10, 2)->nullable()->after('views');
            $table->integer('shopify_tiktokl30')->nullable()->after('shopify_tiktok_price');
            $table->integer('shopify_tiktokl60')->nullable()->after('shopify_tiktokl30');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_sheet_data', function (Blueprint $table) {
            $table->dropColumn(['shopify_tiktok_price', 'shopify_tiktokl30', 'shopify_tiktokl60']);
        });
    }
};