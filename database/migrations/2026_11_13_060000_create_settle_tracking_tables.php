<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yesterday settle tracking (Phase 1 visibility + Phase 2 verification / alerts).
 *
 * Only NEW tables. No existing table or column is touched, and nothing here is ever read by
 * the sales / orders / ads / views calculations. The JSON ledger in storage/app/settle_yesterday
 * stays the source of truth for "which pulls ran"; these tables mirror it per marketplace task
 * and add verification results and an alert log.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settle_task_runs')) {
            Schema::create('settle_task_runs', function (Blueprint $table) {
                $table->id();
                $table->date('settle_date');
                $table->string('task_key', 64);
                $table->string('marketplace', 64);
                $table->string('kind', 16)->nullable();
                $table->string('lane', 32)->nullable();
                $table->string('command', 255)->nullable();
                // not_due | pending | retry_wait | failed | awaiting_verification | verification_failed
                // | verified | verified_warning | limited | limited_by_design
                $table->string('state', 32)->default('pending');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->unsignedSmallInteger('max_attempts')->default(3);
                $table->text('last_error')->nullable();
                $table->string('error_class', 24)->nullable();
                $table->integer('last_exit_code')->nullable();
                $table->dateTime('last_run_started_at')->nullable();
                $table->dateTime('last_run_finished_at')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->mediumText('last_output_tail')->nullable();
                $table->json('output_markers')->nullable();
                $table->dateTime('next_retry_at')->nullable();
                $table->string('next_retry_note', 120)->nullable();
                // Latest ledger "done" timestamp this row was last synced against.
                $table->string('ledger_done_at', 40)->nullable();
                // pass | warn | limited | limited_by_design | pending | error | fail
                $table->string('verification_status', 24)->nullable();
                $table->json('verification_checks')->nullable();
                $table->dateTime('verified_at')->nullable();
                // Ledger done timestamp that the stored verification belongs to. A newer pull
                // (different ledger_done_at) makes the old verification stale.
                $table->string('verified_for_done_at', 40)->nullable();
                $table->timestamps();

                $table->unique(['settle_date', 'task_key'], 'settle_task_runs_date_task_unique');
                $table->index('state');
            });
        }

        if (! Schema::hasTable('settle_verifications')) {
            // Insert-only history of every verification run.
            Schema::create('settle_verifications', function (Blueprint $table) {
                $table->id();
                $table->date('settle_date');
                $table->string('task_key', 64);
                // pass | warn | limited | limited_by_design | pending | error | fail | baseline (operator-accepted table size)
                $table->string('status', 24);
                $table->json('checks')->nullable();
                $table->json('metrics')->nullable();
                $table->string('ledger_done_at', 40)->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['settle_date', 'task_key'], 'settle_verifications_date_task_index');
                $table->index(['task_key', 'id'], 'settle_verifications_task_id_index');
            });
        }

        if (! Schema::hasTable('settle_alert_logs')) {
            Schema::create('settle_alert_logs', function (Blueprint $table) {
                $table->id();
                $table->date('settle_date');
                $table->string('task_key', 64)->nullable();
                $table->string('type', 32);
                $table->string('severity', 12)->default('error');
                $table->string('fingerprint', 191);
                $table->string('title', 255);
                $table->text('message');
                // One row per delivery attempt: `channels` = delivered (log / mail / webhook),
                // `failed_channels` = attempted but NOT delivered (e.g. "webhook:HTTP 500"). Only delivered
                // channels suppress a repeat, so a log-only or failed alert is delivered later.
                $table->json('channels')->nullable();
                $table->json('failed_channels')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['fingerprint', 'sent_at'], 'settle_alert_logs_fingerprint_index');
                $table->index('settle_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settle_alert_logs');
        Schema::dropIfExists('settle_verifications');
        Schema::dropIfExists('settle_task_runs');
    }
};
