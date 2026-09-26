<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Offer Sprice for /vinted/analytics lives here, separate from vinted_pricing.sprice.
     */
    public function up(): void
    {
        if (Schema::hasTable('vinted_data_views')) {
            return;
        }

        Schema::create('vinted_data_views', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vinted_data_views');
    }
};
