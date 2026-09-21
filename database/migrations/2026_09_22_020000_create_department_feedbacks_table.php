<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('department_feedbacks')) {
            return;
        }

        Schema::create('department_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('department', 32);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comment')->nullable();
            $table->date('week_start');
            $table->boolean('skipped')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'department', 'week_start'], 'dept_feedback_user_week_unique');
            $table->index(['week_start', 'department']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_feedbacks');
    }
};
