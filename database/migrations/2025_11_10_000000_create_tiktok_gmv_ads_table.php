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
        Schema::create('tiktok_gmv_ads', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->index();
            $table->integer('ad_sold')->default(0);
            $table->decimal('ad_sales', 15, 2)->default(0);
            $table->decimal('spend', 15, 2)->default(0);
            $table->decimal('budget', 15, 2)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_gmv_ads');
    }
};
