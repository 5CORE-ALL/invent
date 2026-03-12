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
        Schema::create('temu_badge_daily_data', function (Blueprint $table) {
            $table->id();
            $table->date('record_date')->unique();
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->integer('total_quantity')->default(0);
            $table->integer('sku_count')->default(0);
            $table->integer('total_views')->default(0);
            $table->decimal('avg_views', 12, 2)->default(0);
            $table->decimal('total_spend', 12, 2)->default(0);
            $table->decimal('avg_cvr_pct', 10, 2)->default(0);
            $table->json('extra_data')->nullable();
            $table->timestamps();

            $table->index('record_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temu_badge_daily_data');
    }
};
