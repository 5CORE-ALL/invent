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
        Schema::create('wayfair_daily_data', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->index();
            $table->date('po_date')->nullable()->index();
            $table->string('period')->nullable()->index();
            $table->string('status')->nullable();
            $table->string('sku')->nullable()->index();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('total_price', 10, 2)->nullable();
            $table->date('estimated_ship_date')->nullable();
            
            // Customer info
            $table->string('customer_name')->nullable();
            $table->text('customer_address1')->nullable();
            $table->text('customer_address2')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_state')->nullable();
            $table->string('customer_postal_code')->nullable();
            $table->string('customer_country')->nullable();
            $table->string('customer_phone')->nullable();
            
            // Shipping info
            $table->string('ship_speed')->nullable();
            $table->string('carrier_code')->nullable();
            
            // Warehouse info
            $table->string('warehouse_id')->nullable();
            $table->string('warehouse_name')->nullable();
            
            // Event info
            $table->string('event_id')->nullable();
            $table->string('event_type')->nullable();
            $table->string('event_name')->nullable();
            
            $table->text('packing_slip_url')->nullable();
            $table->timestamps();
            
            $table->index(['po_date', 'sku']);
            $table->unique(['po_number', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wayfair_daily_data');
    }
};
