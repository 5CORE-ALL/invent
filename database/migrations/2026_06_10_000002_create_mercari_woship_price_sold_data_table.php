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
        if (Schema::hasTable('mercari_woship_price_sold_data')) {
            return;
        }

        Schema::create('mercari_woship_price_sold_data', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('sold')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mercari_woship_price_sold_data');
    }
};
