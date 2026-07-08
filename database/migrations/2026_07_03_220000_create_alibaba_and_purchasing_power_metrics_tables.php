<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['alibaba_metrics', 'purchasing_power_metrics'] as $tableName) {
            if (Schema::hasTable($tableName)) {
                continue;
            }

            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('sku')->nullable()->index();
                $table->string('product_id')->nullable();
                $table->string('title')->nullable();
                $table->text('bullet_points')->nullable();
                $table->text('description_master')->nullable();
                $table->longText('image_urls')->nullable();
                $table->longText('image_master_json')->nullable();
                $table->longText('video_master_json')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('alibaba_metrics');
        Schema::dropIfExists('purchasing_power_metrics');
    }
};
