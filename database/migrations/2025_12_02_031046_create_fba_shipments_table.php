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
        Schema::create('fba_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_id')->unique();
            $table->string('sku')->index();
            $table->string('shipment_status')->nullable();
            $table->string('status_code')->nullable();
            $table->string('shipment_name')->nullable();
            $table->string('destination_fc')->nullable();
            $table->integer('quantity_shipped')->default(0);
            $table->integer('quantity_received')->default(0);
            $table->date('shipped_date')->nullable();
            $table->date('dispatch_date')->nullable();
            $table->boolean('fba_send')->default(false);
            $table->boolean('listed')->default(false);
            $table->boolean('live')->default(false);
            $table->boolean('done')->default(false);
            $table->timestamp('last_api_sync')->nullable();
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index(['sku', 'shipment_id']);
            $table->index('shipment_status');
            $table->index('last_api_sync');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fba_shipments');
    }
};
