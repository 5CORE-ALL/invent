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
        Schema::create('fba_sku_daily_data', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 255);
            $table->date('record_date');
            $table->json('daily_data'); // JSON format for all metrics
            $table->timestamps();
            
            $table->index('sku');
            $table->index('record_date');
            $table->unique(['sku', 'record_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fba_sku_daily_data');
    }
};






