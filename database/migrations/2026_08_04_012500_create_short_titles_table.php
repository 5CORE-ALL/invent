<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_titles', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->unique();
            $table->text('short_title')->nullable();
            $table->text('source_amazon_title')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_titles');
    }
};
