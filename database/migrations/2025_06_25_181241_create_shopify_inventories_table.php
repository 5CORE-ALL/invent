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
        Schema::create('shopify_inventories', function (Blueprint $table) {
            $table->id();
            $table->string('parent')->nullable();
            $table->string('sku')->unique();
            $table->integer('on_hand')->nullable();
            $table->integer('committed')->nullable();
            $table->integer('available_to_sell')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_inventories');
    }
};
