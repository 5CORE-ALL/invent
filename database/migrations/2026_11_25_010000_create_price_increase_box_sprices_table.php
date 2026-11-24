<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('price_increase_box_sprices')) {
            return;
        }

        // The "SPRICE $" box of the /price-increase SKU modal: the last price fed in that box, per SKU.
        // Its own store - it must NOT follow the Amazon analytics page's SPRICE (that one is just one of the channels it feeds).
        Schema::create('price_increase_box_sprices', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->unique();
            $table->decimal('sprice', 10, 2);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_increase_box_sprices');
    }
};
