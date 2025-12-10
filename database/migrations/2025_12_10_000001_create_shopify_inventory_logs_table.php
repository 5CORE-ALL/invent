<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->integer('quantity_adjustment');
            $table->string('inventory_item_id')->nullable();
            $table->string('location_id')->nullable();
            $table->string('status')->default('pending'); // pending, processing, success, failed
            $table->text('error_message')->nullable();
            $table->integer('attempt')->default(0);
            $table->integer('max_attempts')->default(5);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'attempt']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_inventory_logs');
    }
};
