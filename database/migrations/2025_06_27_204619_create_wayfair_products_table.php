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
        Schema::create('wayfair_products', function (Blueprint $table) {
            $table->id();
            $table->json('supplier_data')->nullable();         // For users response
            $table->json('purchase_order_data')->nullable();    // For purchaseOrders response
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wayfair_products');
    }
};
