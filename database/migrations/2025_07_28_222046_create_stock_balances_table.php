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
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->string('from_parent_name')->nullable();
            $table->string('from_sku')->nullable();
            $table->decimal('from_dil_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('from_warehouse_id')->nullable();
            $table->integer('from_available_qty')->nullable();
            $table->integer('from_adjust_qty')->nullable();

            $table->string('to_parent_name')->nullable();
            $table->string('to_sku')->nullable();
            $table->decimal('to_dil_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('to_warehouse_id')->nullable();
            $table->integer('to_available_qty')->nullable();
            $table->integer('to_adjust_qty')->nullable();

            $table->string('transferred_by')->nullable();
            $table->timestamp('transferred_at')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('from_warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('to_warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
