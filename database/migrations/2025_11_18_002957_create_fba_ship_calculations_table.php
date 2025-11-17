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
        Schema::create('fba_ship_calculations', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('fba_sku')->nullable();
            $table->decimal('fulfillment_fee', 10, 2)->default(0)->comment('From FBA Reports');
            $table->decimal('fba_fee_manual', 10, 2)->default(0)->comment('From Manual Data');
            $table->decimal('send_cost', 10, 2)->default(0)->comment('From Manual Data');
            $table->decimal('in_charges', 10, 2)->default(0)->comment('From Manual Data');
            $table->decimal('fba_ship_calculation', 10, 2)->default(0)->comment('Final Calculated Value');
            $table->string('calculation_source')->nullable()->comment('fulfillment_fee or manual');
            $table->timestamps();
            
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fba_ship_calculations');
    }
};
