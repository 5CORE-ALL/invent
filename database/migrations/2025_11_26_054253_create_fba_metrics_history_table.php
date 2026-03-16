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
        Schema::create('fba_metrics_history', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 255);
            $table->date('record_date');
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('views')->nullable();
            $table->decimal('gprft', 10, 2)->nullable();
            $table->decimal('groi_percent', 10, 2)->nullable();
            $table->decimal('tacos', 10, 2)->nullable();
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
        Schema::dropIfExists('fba_metrics_history');
    }
};
