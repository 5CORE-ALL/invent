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
        if (Schema::hasTable('shopifysku_inventory_history')) {
            return;
        }

        Schema::create('shopifysku_inventory_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sku_id')->nullable();
            $table->string('sku')->index();
            $table->string('product_name')->nullable();
            $table->integer('opening_inventory')->default(0);
            $table->integer('closing_inventory')->default(0);
            $table->integer('sold_quantity')->default(0);
            $table->integer('restocked_quantity')->default(0);
            $table->date('snapshot_date')->index();
            $table->dateTime('pst_start_datetime')->nullable();
            $table->dateTime('pst_end_datetime')->nullable();
            $table->timestamps();

            $table->unique(['sku', 'snapshot_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopifysku_inventory_history');
    }
};
