<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates shein_metrics when missing (e.g. if an older migration batch failed before
 * 2026_01_14_224830_create_shein_metrics_table ran). Safe to run multiple times.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shein_metrics')) {
            return;
        }

        Schema::create('shein_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique()->index();
            $table->string('product_name')->nullable();
            $table->string('spu_name')->nullable();
            $table->integer('inventory')->default(0);
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('retail_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->bigInteger('views')->default(0);
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('review_count')->default(0);
            $table->string('status')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('category')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Do not drop: table may have been created by 2026_01_14 migration.
    }
};
