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
        Schema::create('auto_stock_balance', function (Blueprint $table) {
            $table->id();
              // From SKU details
            $table->string('from_sku');
            $table->string('from_parent_name')->nullable();
            $table->integer('from_available_qty')->nullable();
            $table->decimal('from_dil_percent', 8, 2)->nullable();
            $table->integer('from_adjust_qty')->nullable();
            $table->decimal('from_adj_dil', 8, 2)->nullable();

            // To SKU details
            $table->string('to_sku');
            $table->string('to_parent_name')->nullable();
            $table->integer('to_available_qty')->nullable();
            $table->decimal('to_dil_percent', 8, 2)->nullable();
            $table->integer('to_adjust_qty')->nullable();
            $table->decimal('to_adj_dil', 8, 2)->nullable();

            // Added Qty
            $table->integer('added_qty')->nullable();

            // Optional: record which user performed the adjustment
            $table->unsignedBigInteger('user_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_stock_balance');
    }
};
