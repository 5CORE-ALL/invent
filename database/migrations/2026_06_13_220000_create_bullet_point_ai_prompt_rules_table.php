<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bullet_point_ai_prompt_rules')) {
            return;
        }

        Schema::create('bullet_point_ai_prompt_rules', function (Blueprint $table) {
            $table->id();
            $table->longText('rules');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bullet_point_ai_prompt_rules');
    }
};
