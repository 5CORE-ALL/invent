<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amz_comp_jungle_kws')) {
            return;
        }

        Schema::create('amz_comp_jungle_kws', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->text('search_kw')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amz_comp_jungle_kws');
    }
};
