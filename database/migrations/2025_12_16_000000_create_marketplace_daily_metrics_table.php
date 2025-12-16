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
        Schema::create('marketplace_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 50); // Amazon, eBay, Temu, Shein, Mercari, AliExpress
            $table->date('date');
            $table->integer('total_orders')->default(0);
            $table->integer('total_quantity')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('total_cogs', 12, 2)->default(0);
            $table->decimal('total_pft', 12, 2)->default(0);
            $table->decimal('pft_percentage', 8, 2)->default(0);
            $table->decimal('roi_percentage', 8, 2)->default(0);
            $table->decimal('avg_price', 10, 2)->default(0);
            $table->decimal('l30_sales', 12, 2)->default(0);
            $table->decimal('tacos_percentage', 8, 2)->nullable(); // For Amazon/eBay
            $table->decimal('n_pft', 8, 2)->nullable(); // Net PFT after ads
            $table->decimal('kw_spent', 12, 2)->nullable(); // Keyword ads spent
            $table->decimal('pmt_spent', 12, 2)->nullable(); // Promoted listing spent
            $table->decimal('total_commission', 12, 2)->nullable(); // For Shein
            $table->decimal('total_fees', 12, 2)->nullable(); // For Mercari
            $table->decimal('net_proceeds', 12, 2)->nullable(); // For Mercari
            $table->json('extra_data')->nullable(); // Any additional metrics
            $table->timestamps();
            
            // Unique constraint: one record per channel per day
            $table->unique(['channel', 'date']);
            $table->index('channel');
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_daily_metrics');
    }
};
