<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_closeouts')) {
            return;
        }

        Schema::create('daily_closeouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->date('check_date')->index();
            $table->boolean('tasks_completed')->nullable();
            $table->text('incomplete_reason')->nullable();
            $table->timestamp('tasks_answered_at')->nullable();
            $table->timestamp('dar_nudge_5am_at')->nullable();
            $table->timestamp('dar_nudge_530am_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'check_date']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closeouts');
    }
};
