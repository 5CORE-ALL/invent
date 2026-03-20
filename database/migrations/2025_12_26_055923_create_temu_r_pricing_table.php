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
        Schema::create('temu_r_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('pricing_opportunity_type')->nullable();
            $table->text('product_name')->nullable();
            $table->string('goods_id')->nullable();
            $table->string('sku_id')->nullable()->index();
            $table->string('variation')->nullable();
            $table->string('product_status')->nullable();
            $table->string('category')->nullable();
            $table->decimal('current_base_price', 12, 2)->nullable();
            $table->decimal('recommended_base_price', 12, 2)->nullable();
            $table->timestamp('date_created')->nullable();
            $table->string('action')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temu_r_pricing');
    }
};
