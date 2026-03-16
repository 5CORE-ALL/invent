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
        Schema::create('doba_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('item_id')->nullable();
            $table->integer('quantity_l30')->nullable();
            $table->integer('quantity_l60')->nullable();
            $table->decimal('anticipated_income', 10, 2)->nullable();
            $table->integer('impressions')->nullable();
            $table->integer('clicks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doba_metrics');
    }
};
