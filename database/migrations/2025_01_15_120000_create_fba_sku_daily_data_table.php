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
        if (!Schema::hasTable('fba_sku_daily_data')) {
            Schema::create('fba_sku_daily_data', function (Blueprint $table) {
                $table->id();
                $table->string('sku');
                $table->date('record_date');
                $table->json('daily_data');
                $table->timestamps();
            });
        }
    }
    

    /**
     * Reverse the migrations.
     */
    
    public function down(): void
    {
        Schema::dropIfExists('fba_sku_daily_data');
    }
};












