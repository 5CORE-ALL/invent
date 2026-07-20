<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('shopifyb2b_data_view')) {
            return;
        }

        Schema::create('shopifyb2b_data_view', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopifyb2b_data_view');
    }
};
