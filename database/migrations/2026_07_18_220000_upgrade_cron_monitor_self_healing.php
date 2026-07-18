<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cron_execution_logs', function (Blueprint $table) {
            $table->string('failure_category', 64)->nullable()->after('health_label')->index();
            $table->text('root_cause')->nullable()->after('failure_category');
            $table->string('recovery_status', 32)->default('none')->after('root_cause')->index();
            $table->json('checkpoint')->nullable()->after('recovery_status');
            $table->unsignedBigInteger('resume_from')->nullable()->after('checkpoint');
            $table->timestamp('last_retry_at')->nullable()->after('retry_count');
            $table->unsignedInteger('consecutive_failures')->default(0)->after('last_retry_at');
            $table->unsignedInteger('expected_runtime_seconds')->nullable()->after('duration_seconds');
            $table->unsignedInteger('api_latency_ms_avg')->nullable()->after('api_calls');
            $table->unsignedBigInteger('cpu_time_ms')->nullable()->after('memory_usage');
            $table->string('lock_key')->nullable()->after('execution_server');
            $table->unsignedInteger('pid')->nullable()->after('lock_key');
            $table->timestamp('cancelled_at')->nullable()->after('pid');
        });

        Schema::table('cron_execution_failures', function (Blueprint $table) {
            $table->string('failure_category', 64)->nullable()->after('failure_reason')->index();
            $table->unsignedSmallInteger('http_status')->nullable()->after('failure_category');
            $table->boolean('recoverable')->default(false)->after('http_status')->index();
            $table->text('root_cause')->nullable()->after('recoverable');
            $table->timestamp('last_retry_at')->nullable()->after('retry_count');
        });

        Schema::create('cron_execution_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_log_id')
                ->nullable()
                ->constrained('cron_execution_logs')
                ->nullOnDelete();
            $table->string('job_name')->index();
            $table->string('command')->nullable()->index();
            $table->text('cursor')->nullable();
            $table->unsignedBigInteger('processed_offset')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['job_name', 'updated_at']);
        });

        Schema::create('cron_alert_batches', function (Blueprint $table) {
            $table->id();
            $table->timestamp('window_started_at')->nullable()->index();
            $table->timestamp('window_ended_at')->nullable();
            $table->string('summary')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('notified')->default(false)->index();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_alert_batches');
        Schema::dropIfExists('cron_execution_checkpoints');

        Schema::table('cron_execution_failures', function (Blueprint $table) {
            $table->dropColumn([
                'failure_category',
                'http_status',
                'recoverable',
                'root_cause',
                'last_retry_at',
            ]);
        });

        Schema::table('cron_execution_logs', function (Blueprint $table) {
            $table->dropColumn([
                'failure_category',
                'root_cause',
                'recovery_status',
                'checkpoint',
                'resume_from',
                'last_retry_at',
                'consecutive_failures',
                'expected_runtime_seconds',
                'api_latency_ms_avg',
                'cpu_time_ms',
                'lock_key',
                'pid',
                'cancelled_at',
            ]);
        });
    }
};
