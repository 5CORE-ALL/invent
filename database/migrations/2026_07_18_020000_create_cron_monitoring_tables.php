<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name')->index();
            $table->string('command')->nullable()->index();
            $table->string('status', 32)->default('running')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('expected_records')->nullable();
            $table->unsignedBigInteger('fetched_records')->default(0);
            $table->unsignedBigInteger('processed_records')->default(0);
            $table->unsignedBigInteger('updated_records')->default(0);
            $table->unsignedBigInteger('inserted_records')->default(0);
            $table->unsignedBigInteger('skipped_records')->default(0);
            $table->unsignedBigInteger('failed_records')->default(0);
            $table->unsignedInteger('api_calls')->default(0);
            $table->boolean('api_connected')->default(false);
            $table->unsignedInteger('retry_count')->default(0);
            $table->decimal('success_percentage', 6, 2)->nullable();
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->string('health_label', 32)->nullable();
            $table->text('validation_message')->nullable();
            $table->text('error_message')->nullable();
            $table->longText('exception')->nullable();
            $table->json('meta')->nullable();
            $table->json('anomalies')->nullable();
            $table->string('memory_usage')->nullable();
            $table->string('execution_server')->nullable();
            $table->timestamps();

            $table->index(['job_name', 'started_at']);
            $table->index(['status', 'started_at']);
        });

        Schema::create('cron_execution_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_log_id')
                ->constrained('cron_execution_logs')
                ->cascadeOnDelete();
            $table->string('sku')->nullable()->index();
            $table->string('marketplace')->nullable()->index();
            $table->text('failure_reason')->nullable();
            $table->longText('api_response')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->boolean('resolved')->default(false)->index();
            $table->timestamp('resolved_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['execution_log_id', 'resolved']);
        });

        Schema::create('cron_monitor_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_log_id')
                ->nullable()
                ->constrained('cron_execution_logs')
                ->nullOnDelete();
            $table->string('job_name')->nullable()->index();
            $table->string('alert_type', 64)->index();
            $table->string('severity', 32)->default('warning');
            $table->string('title');
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('notified')->default(false);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_monitor_alerts');
        Schema::dropIfExists('cron_execution_failures');
        Schema::dropIfExists('cron_execution_logs');
    }
};
