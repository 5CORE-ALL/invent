<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('temu2_metrics')) {
            return;
        }

        Schema::create('temu2_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->nullable()->index();
            $table->string('sku_id')->nullable()->index();
            $table->string('goods_id')->nullable()->index();
            $table->longText('bullet_points')->nullable();
            $table->longText('goods_summary')->nullable();
            $table->longText('goods_desc')->nullable();
            $table->longText('description_master')->nullable();
            $table->longText('image_urls')->nullable();
            $table->longText('image_master_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temu2_metrics');
    }
};
