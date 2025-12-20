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
        Schema::create('views_pull_data', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('parent')->nullable();
            $table->integer('temu')->default(0);
            $table->integer('wayfair')->default(0);
            $table->integer('tiktok')->default(0);
            $table->integer('walmart')->default(0);
            $table->integer('aliexpress')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('views_pull_data');
    }
};
