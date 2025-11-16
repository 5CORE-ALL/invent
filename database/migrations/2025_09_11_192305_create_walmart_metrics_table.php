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
        Schema::create('walmart_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->integer('l30')->nullable();
            $table->decimal('l30_amt', 12, 2)->nullable();
            $table->integer('l60')->nullable();
            $table->decimal('l60_amt', 12, 2)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->integer('stock')->nullable();
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('walmart_metrics');
    }
};
