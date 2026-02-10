<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure ai_questions table exists (for feedback tracking).
     * Safe to run if table was already created by create_ai_questions_table_for_chat.
     */
    public function up(): void
    {
        if (Schema::hasTable('ai_questions')) {
            return;
        }

        Schema::create('ai_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->text('ai_answer');
            $table->boolean('helpful')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_questions');
    }
};
