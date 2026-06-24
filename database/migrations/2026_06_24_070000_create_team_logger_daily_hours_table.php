<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores TeamLogger hours on a per-user, per-day basis.
 *
 * The companion `team_logger_hours` table aggregates the same data per month;
 * this table is the granular daily breakdown so we can drive day-level reports
 * (calendars, trend lines, daily payroll sanity checks, etc.) without re-hitting
 * the upstream API every time a page is loaded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_logger_daily_hours', function (Blueprint $table) {
            $table->id();
            $table->string('employee_email');
            $table->date('work_date');
            $table->decimal('total_hours', 8, 2)->default(0);   // raw on-computer hours
            $table->decimal('idle_hours', 8, 2)->default(0);    // idle within total
            $table->decimal('active_hours', 8, 2)->default(0);  // total - idle
            $table->integer('productive_hours')->default(0);    // rounded active hours
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            // One row per employee per day, and we index for fast lookups.
            $table->unique(['employee_email', 'work_date'], 'team_logger_daily_email_date_unique');
            $table->index('work_date');
            $table->index('employee_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_logger_daily_hours');
    }
};
