<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_policies')) {
            Schema::create('attendance_policies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
                $table->time('expected_start')->default('09:30:00');
                $table->time('expected_end')->default('18:30:00');
                $table->unsignedSmallInteger('grace_minutes')->default(15);
                $table->decimal('min_daily_hours', 4, 2)->default(8.0);
                $table->unsignedSmallInteger('max_idle_minutes_per_hour')->default(15);
                $table->unsignedSmallInteger('min_active_percent')->default(60);
                $table->boolean('wfh_allowed')->default(true);
                $table->boolean('monitoring_enabled')->default(true);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('attendance_sessions')) {
            Schema::create('attendance_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->string('status', 20)->default('active'); // active, paused, completed, auto_closed
                $table->string('work_location', 20)->default('wfh'); // wfh, office, hybrid
                $table->unsignedInteger('total_active_seconds')->default(0);
                $table->unsignedInteger('total_idle_seconds')->default(0);
                $table->unsignedInteger('heartbeat_count')->default(0);
                $table->unsignedInteger('missed_heartbeat_count')->default(0);
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'started_at']);
                $table->index(['status', 'started_at']);
            });
        }

        if (! Schema::hasTable('attendance_activity_logs')) {
            Schema::create('attendance_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('recorded_at');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('idle_seconds')->default(0);
                $table->string('window_title', 500)->nullable();
                $table->string('page_url', 1000)->nullable();
                $table->timestamps();

                $table->index(['user_id', 'recorded_at']);
                $table->index(['attendance_session_id', 'recorded_at']);
            });
        }

        if (! Schema::hasTable('attendance_daily_summaries')) {
            Schema::create('attendance_daily_summaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('work_date');
                $table->timestamp('first_clock_in')->nullable();
                $table->timestamp('last_clock_out')->nullable();
                $table->unsignedInteger('total_work_seconds')->default(0);
                $table->unsignedInteger('active_seconds')->default(0);
                $table->unsignedInteger('idle_seconds')->default(0);
                $table->unsignedSmallInteger('session_count')->default(0);
                $table->string('status', 20)->default('absent'); // present, absent, late, half_day, leave
                $table->decimal('team_logger_hours', 6, 2)->nullable();
                $table->unsignedTinyInteger('productivity_score')->nullable();
                $table->unsignedTinyInteger('ai_risk_score')->nullable();
                $table->json('top_activities')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'work_date']);
                $table->index('work_date');
            });
        }

        if (! Schema::hasTable('attendance_ai_flags')) {
            Schema::create('attendance_ai_flags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('attendance_session_id')->nullable()->constrained('attendance_sessions')->nullOnDelete();
                $table->date('flag_date')->nullable();
                $table->string('flag_type', 50);
                $table->string('severity', 10)->default('medium'); // low, medium, high
                $table->string('title');
                $table->text('description')->nullable();
                $table->json('evidence')->nullable();
                $table->decimal('ai_confidence', 5, 2)->nullable();
                $table->string('source', 20)->default('rules'); // rules, ai, hybrid
                $table->string('status', 20)->default('open'); // open, reviewed, dismissed
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'flag_date']);
                $table->index(['status', 'severity']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_ai_flags');
        Schema::dropIfExists('attendance_daily_summaries');
        Schema::dropIfExists('attendance_activity_logs');
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('attendance_policies');
    }
};
