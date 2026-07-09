<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('faire_metrics')) {
            return;
        }

        Schema::create('faire_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->string('product_id')->nullable()->index();
            $table->string('faire_product_id')->nullable();
            $table->text('bullet_points')->nullable();
            $table->longText('description_master')->nullable();
            $table->json('image_master_json')->nullable();
            $table->json('video_master_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faire_metrics');
    }
};
