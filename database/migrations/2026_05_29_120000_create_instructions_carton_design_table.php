<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carton design instructions — per product_master row (To Order Analysis).
     */
    public function up(): void
    {
        if (Schema::hasTable('instructions_carton_design')) {
            return;
        }

        Schema::create('instructions_carton_design', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_master_id')->unique()->constrained('product_master')->cascadeOnDelete();
            $table->text('instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructions_carton_design');
    }
};
