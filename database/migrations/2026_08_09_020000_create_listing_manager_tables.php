<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('listing_manager_enabled_channels')) {
            Schema::create('listing_manager_enabled_channels', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id')->unique();
                $table->boolean('is_enabled')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('channel_id')
                    ->references('id')
                    ->on('channel_master')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('listing_manager_channel_drafts')) {
            Schema::create('listing_manager_channel_drafts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id')->index();
                $table->string('seller_sku', 255)->index();
                $table->string('asin', 32)->nullable()->index();
                $table->string('title', 1000)->nullable();
                $table->string('thumbnail_image', 1000)->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->integer('quantity')->nullable();
                $table->string('status', 40)->default('draft')->index(); // draft|queued|listed|failed
                $table->json('amazon_snapshot')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('listed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['channel_id', 'seller_sku'], 'lm_drafts_channel_sku_unique');
                $table->foreign('channel_id')
                    ->references('id')
                    ->on('channel_master')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_manager_channel_drafts');
        Schema::dropIfExists('listing_manager_enabled_channels');
    }
};
