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
        Schema::create('amazon_daily_syncs', function (Blueprint $table) {
            $table->id();
            $table->date('sync_date')->unique()->comment('The date to sync orders for (California/Pacific Time)');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed', 'skipped'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_page_at')->nullable();
            $table->text('next_token')->nullable()->comment('Amazon NextToken for resuming pagination');
            $table->integer('orders_fetched')->default(0);
            $table->integer('pages_fetched')->default(0);
            $table->integer('items_fetched')->default(0)->comment('Number of order items (line items) fetched');
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();
            
            // Indexes for performance
            $table->index('sync_date');
            $table->index('status');
            $table->index(['sync_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_daily_syncs');
    }
};
