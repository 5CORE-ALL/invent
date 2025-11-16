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
        Schema::create('amazon_datsheets', function (Blueprint $table) {
            $table->id();
            $table->integer('units_ordered_l30')->nullable();
            $table->integer('units_ordered_l60')->nullable();
            $table->integer('units_ordered_l90')->nullable();
            $table->integer('sessions_l30')->nullable();
            $table->integer('sessions_l60')->nullable();
            $table->integer('sessions_l90')->nullable();
            $table->string('asin')->unique(); 
            $table->decimal('price', 10, 2)->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_datsheets');
    }
};
