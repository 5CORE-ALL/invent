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
        Schema::create('channels_reviews_data', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->json('values')->nullable();
            $table->text('amazon_reviews')->nullable();
            $table->text('ebay_one_reviews')->nullable();
            $table->text('ebay_two_reviews')->nullable();
            $table->text('ebay_three_reviews')->nullable();
            $table->text('temu_reviews')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channels_reviews_data');
    }
};
