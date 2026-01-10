<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('listing_mirror_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100); // Limit length for indexing
            $table->string('channel', 50); // 'shopify', 'ebay', 'walmart', etc.
            $table->string('sync_type', 50); // 'inventory', 'price', 'listing'
            $table->string('status', 50)->default('pending'); // 'pending', 'processing', 'completed', 'failed'
            $table->text('error_message')->nullable();
            $table->json('source_data')->nullable(); // Amazon data used for sync
            $table->json('target_data')->nullable(); // Data sent to target channel
            $table->json('response_data')->nullable(); // Response from target channel
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            // Indexes - define explicitly to avoid duplicates
            $table->index('sku');
            $table->index('channel');
            $table->index('sync_type');
            $table->index(['status', 'created_at']);
            // Composite index for common queries
            $table->index(['sku', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_mirror_syncs');
    }
};
