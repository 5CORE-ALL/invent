<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('newegg_items')) {
            return;
        }

        // Catalog of the seller's listed items (from the Item Basic Info Report).
        Schema::create('newegg_items', function (Blueprint $table) {
            $table->id();

            $table->string('seller_part_number', 100)->index();
            $table->string('newegg_item_number', 60)->nullable()->index();
            $table->string('title', 500)->nullable();
            $table->string('manufacturer_part_number', 100)->nullable();
            $table->string('upc', 60)->nullable();
            $table->string('status', 20)->nullable();
            $table->string('platform', 20)->nullable();
            $table->decimal('item_weight', 10, 2)->nullable();
            $table->string('date_created', 30)->nullable();
            $table->json('raw_json')->nullable();

            $table->timestamps();

            $table->unique('seller_part_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newegg_items');
    }
};
