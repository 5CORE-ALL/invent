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
        Schema::create('amazon_sku_competitors', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->string('asin')->index();
            $table->string('marketplace')->default('amazon');
            $table->text('product_title')->nullable();
            $table->text('product_link')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_sku_competitors');
    }
};
