<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }

        if (! Schema::hasTable('marketplaces')) {
            Schema::create('marketplaces', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sku_images')) {
            Schema::create('sku_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('product_master')->cascadeOnDelete();
                $table->string('file_name');
                $table->string('file_path');
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('mime_type')->nullable();
                $table->timestamps();

                $table->index('product_id');
            });
        }

        if (! Schema::hasTable('image_marketplace_map')) {
            Schema::create('image_marketplace_map', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sku_image_id')->constrained('sku_images')->cascadeOnDelete();
                $table->foreignId('marketplace_id')->constrained('marketplaces')->cascadeOnDelete();
                $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
                $table->json('response')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->unique(['sku_image_id', 'marketplace_id']);
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('image_marketplace_map');
        Schema::dropIfExists('sku_images');
        Schema::dropIfExists('marketplaces');
    }
};
