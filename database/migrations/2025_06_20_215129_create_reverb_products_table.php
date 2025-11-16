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
        Schema::create('reverb_products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique()->nullable();
            $table->integer('r_l30')->nullable();
            $table->integer('r_l60')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('views')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reverb_products');
    }
};
