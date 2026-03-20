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
        Schema::create('temu_daily_avg_views', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('avg_views', 10, 2);
            $table->integer('total_products');
            $table->bigInteger('total_views');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temu_daily_avg_views');
    }
};
