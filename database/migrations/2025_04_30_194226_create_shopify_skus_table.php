<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    // In the migration file
    public function up()
    {
        Schema::create('shopify_skus', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique(); // Assuming SKU is unique
            $table->integer('inv')->default(0);
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_skus');
    }
};
