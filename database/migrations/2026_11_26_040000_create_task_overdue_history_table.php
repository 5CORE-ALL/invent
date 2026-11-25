<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (team member, day): how many overdue tasks that member had.
 *
 * Overdue is a live number (it depends on today's date), so it cannot be rebuilt for past days.
 * This table keeps a snapshot so the O-Due column on /tasks/summary can draw a rolling graph.
 * The row of the current day is overwritten on every snapshot, so a day ends with its last value.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_overdue_history')) {
            return;
        }

        Schema::create('task_overdue_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('snap_date');
            $table->unsignedInteger('overdue')->default(0);
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'snap_date'], 'task_overdue_history_user_day_unique');
            $table->index('snap_date', 'task_overdue_history_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_overdue_history');
    }
};
