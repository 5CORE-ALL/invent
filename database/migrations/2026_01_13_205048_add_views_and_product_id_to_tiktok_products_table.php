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
        Schema::table('tiktok_products', function (Blueprint $table) {
            $table->string('product_id')->nullable()->after('id')->comment('TikTok product ID');
            $table->decimal('views', 12, 2)->nullable()->after('stock')->comment('Product views from TikTok API');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_products', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropColumn(['product_id', 'views']);
        });
    }
};
