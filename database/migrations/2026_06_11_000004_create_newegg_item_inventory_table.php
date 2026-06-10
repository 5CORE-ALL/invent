<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('newegg_item_inventory')) {
            return;
        }

        Schema::create('newegg_item_inventory', function (Blueprint $table) {
            $table->id();

            $table->string('seller_part_number', 100)->index();
            $table->string('newegg_item_number', 60)->nullable()->index();
            $table->tinyInteger('active')->nullable();
            $table->string('fulfillment_option', 20)->nullable();
            $table->integer('available_quantity')->nullable();
            $table->json('warehouse_allocation')->nullable();
            $table->json('raw_json')->nullable();

            $table->timestamps();

            $table->unique('seller_part_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newegg_item_inventory');
    }
};
