<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tiktok_two_shop_data_views')) {
            return;
        }

        Schema::create('tiktok_two_shop_data_views', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_two_shop_data_views');
    }
};
