<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sync history: all inventory/order changes with source (manual|reverb|shopify) and timestamp.
     */
    public function up(): void
    {
        Schema::create('reverb_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->string('reverb_listing_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('shopify_order_id')->nullable();
            $table->string('reverb_order_number')->nullable();
            $table->string('action'); // inventory_change, order_created, price_change
            $table->string('source'); // shopify, reverb, manual
            $table->integer('old_quantity')->nullable();
            $table->integer('new_quantity')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['sku', 'created_at']);
            $table->index('source');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reverb_sync_logs');
    }
};
