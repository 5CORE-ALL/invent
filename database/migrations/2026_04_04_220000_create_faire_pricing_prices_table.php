<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faire_pricing_prices', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedInteger('faire_stock')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faire_pricing_prices');
    }
};
