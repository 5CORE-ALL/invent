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
        Schema::create('aliexpress_metric', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->decimal('price', 10, 2);
            $table->decimal('l30', 10, 2);
            $table->decimal('l60', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aliexpress_metric');
    }
};