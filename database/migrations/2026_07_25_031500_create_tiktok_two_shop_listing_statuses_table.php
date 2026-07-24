<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TikTok 2 listing status (buyer/seller links, listed flags)
     * — same role as tiktok_shop_listing_statuses for TikTok 1.
     */
    public function up(): void
    {
        if (Schema::hasTable('tiktok_two_shop_listing_statuses')) {
            return;
        }

        Schema::create('tiktok_two_shop_listing_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_two_shop_listing_statuses');
    }
};
