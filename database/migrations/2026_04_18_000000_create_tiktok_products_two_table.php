<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tiktok_products_two')) {
            return;
        }

        Schema::create('tiktok_products_two', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->nullable()->index();
            $table->string('sku', 191)->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->integer('sold')->default(0);
            $table->decimal('views', 12, 2)->nullable();
            $table->unsignedBigInteger('video_views')->default(0);
            $table->unsignedBigInteger('ads_views')->default(0);
            $table->unsignedBigInteger('affl_views')->default(0);
            $table->unsignedInteger('reviews')->nullable();
            $table->decimal('rating', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_products_two');
    }
};
