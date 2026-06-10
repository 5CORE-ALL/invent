<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('newegg_order_items')) {
            return;
        }

        Schema::create('newegg_order_items', function (Blueprint $table) {
            $table->id();

            $table->string('order_number', 30)->index();
            $table->string('seller_part_number', 100)->nullable()->index();
            $table->string('newegg_item_number', 60)->nullable()->index();
            $table->string('mfr_part_number', 100)->nullable();
            $table->string('upc_code', 50)->nullable();
            $table->string('description', 500)->nullable();

            $table->integer('ordered_qty')->nullable();
            $table->integer('shipped_qty')->nullable();

            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('extend_unit_price', 12, 2)->nullable();
            $table->decimal('extend_shipping_charge', 12, 2)->nullable();

            $table->tinyInteger('status')->nullable();
            $table->string('status_description', 50)->nullable();

            $table->json('raw_json')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newegg_order_items');
    }
};
