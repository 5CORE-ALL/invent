<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('alibaba_sheet_prices')) {
            return;
        }

        Schema::create('alibaba_sheet_prices', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 64)->unique();
            $table->string('sku', 191)->index();
            $table->string('status', 64)->nullable();
            $table->decimal('sku_price', 12, 2)->nullable();
            $table->integer('soh')->nullable();
            $table->string('inv_update', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alibaba_sheet_prices');
    }
};
