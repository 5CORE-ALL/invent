<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('topdawg_data_views')) {
            return;
        }

        Schema::create('topdawg_data_views', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topdawg_data_views');
    }
};
