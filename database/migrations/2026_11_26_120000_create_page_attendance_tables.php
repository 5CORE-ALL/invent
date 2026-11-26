<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Page attendance: people responsible for a page check in after their work on it is done.
 *
 *  page_attendance_assignments : who must visit which page, and how often (1 = daily, 7, 30 days).
 *  page_attendance_events      : one row per person / frequency / period. 'done' when they checked in,
 *                                'missed' when the period ended without a check-in (a task is generated).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('page_attendance_assignments')) {
            Schema::create('page_attendance_assignments', function (Blueprint $table) {
                $table->id();
                $table->string('page_key', 100);
                $table->unsignedBigInteger('user_id');
                $table->unsignedSmallInteger('frequency_days');
                // First day of the first period; periods are start_date + k * frequency_days.
                $table->date('start_date');
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->string('assignor_email', 191)->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['page_key', 'user_id', 'frequency_days'], 'pa_assign_unique');
                $table->index(['page_key', 'active']);
            });
        }

        if (! Schema::hasTable('page_attendance_events')) {
            Schema::create('page_attendance_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('assignment_id');
                $table->string('page_key', 100);
                $table->unsignedBigInteger('user_id');
                $table->unsignedSmallInteger('frequency_days');
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 12); // done | missed
                $table->dateTime('visited_at')->nullable();
                $table->string('note', 500)->nullable();
                $table->unsignedBigInteger('task_id')->nullable();
                $table->timestamps();

                $table->unique(['assignment_id', 'period_start'], 'pa_event_unique');
                $table->index(['page_key', 'period_start']);
                $table->index(['user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_attendance_events');
        Schema::dropIfExists('page_attendance_assignments');
    }
};
