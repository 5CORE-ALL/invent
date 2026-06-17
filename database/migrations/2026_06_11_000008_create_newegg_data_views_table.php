<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('newegg_data_views')) {
            return;
        }

        // Stores user-entered pricing overlay (SPRICE/SPFT/SROI) per SKU as JSON.
        Schema::create('newegg_data_views', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->index();
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newegg_data_views');
    }
};
