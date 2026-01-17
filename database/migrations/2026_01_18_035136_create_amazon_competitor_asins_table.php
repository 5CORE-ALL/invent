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
        Schema::create('amazon_competitor_asins', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace')->default('amazon');
            $table->string('search_query')->index();
            $table->string('asin')->index();
            $table->string('title')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('reviews')->nullable();
            $table->integer('position')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_competitor_asins');
    }
};
