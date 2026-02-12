<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('subcategory')->nullable();
            $table->text('question_pattern');
            $table->json('answer_steps');
            $table->string('video_link')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_base');
    }
};
