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
        Schema::create('temu_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('category')->nullable();
            $table->string('category_id')->nullable();
            $table->text('product_name')->nullable();
            $table->string('contribution_goods')->nullable();
            $table->string('sku')->index();
            $table->string('goods_id')->nullable();
            $table->string('sku_id')->nullable();
            $table->string('variation')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('base_price', 10, 2)->nullable();
            $table->string('external_product_id_type')->nullable();
            $table->string('external_product_id')->nullable();
            $table->string('status')->nullable();
            $table->string('detail_status')->nullable();
            $table->timestamp('date_created')->nullable();
            $table->text('incomplete_product_information')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temu_pricing');
    }
};
