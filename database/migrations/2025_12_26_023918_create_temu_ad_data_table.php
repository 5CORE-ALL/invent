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
        Schema::create('temu_ad_data', function (Blueprint $table) {
            $table->id();
            $table->text('goods_name')->nullable();
            $table->string('goods_id')->nullable()->index();
            $table->decimal('spend', 10, 2)->nullable();
            $table->decimal('base_price_sales', 10, 2)->nullable();
            $table->decimal('roas', 10, 2)->nullable();
            $table->decimal('acos_ad', 10, 2)->nullable();
            $table->decimal('cost_per_transaction', 10, 2)->nullable();
            $table->integer('sub_orders')->nullable();
            $table->integer('items')->nullable();
            $table->decimal('net_total_cost', 10, 2)->nullable();
            $table->decimal('net_declared_sales', 10, 2)->nullable();
            $table->decimal('net_roas', 10, 2)->nullable();
            $table->decimal('net_acos_ad', 10, 2)->nullable();
            $table->decimal('net_cost_per_transaction', 10, 2)->nullable();
            $table->integer('net_orders')->nullable();
            $table->integer('net_number_pieces')->nullable();
            $table->integer('impressions')->nullable();
            $table->integer('clicks')->nullable();
            $table->decimal('ctr', 8, 2)->nullable();
            $table->decimal('cvr', 8, 2)->nullable();
            $table->integer('add_to_cart_number')->nullable();
            $table->decimal('weekly_roas', 10, 2)->nullable();
            $table->decimal('target', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temu_ad_data');
    }
};
