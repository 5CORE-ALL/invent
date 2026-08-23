<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_raw_image_ai_prompts')) {
            return;
        }

        Schema::create('product_raw_image_ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32)->unique();
            $table->text('prompt');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_raw_image_ai_prompts');
    }
};
