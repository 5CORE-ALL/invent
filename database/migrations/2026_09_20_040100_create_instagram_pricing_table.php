<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * instagram_pricing — editable overlay for Instagram Shop Analytics.
     * CSV template import upserts price / l30 / sprice by SKU.
     */
    public function up(): void
    {
        if (Schema::hasTable('instagram_pricing')) {
            return;
        }

        Schema::create('instagram_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('sprice', 12, 2)->nullable();
            $table->integer('l30')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_pricing');
    }
};
