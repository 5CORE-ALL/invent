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
        Schema::create('temu_sku_daily_data', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->date('record_date')->index();
            $table->decimal('base_price', 10, 2)->nullable();
            $table->integer('product_clicks')->default(0);
            $table->integer('temu_l30')->default(0);
            $table->decimal('cvr_percent', 10, 2)->default(0);
            $table->decimal('spend', 10, 2)->default(0);
            $table->timestamps();
            
            // Unique constraint on sku + record_date
            $table->unique(['sku', 'record_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temu_sku_daily_data');
    }
};
