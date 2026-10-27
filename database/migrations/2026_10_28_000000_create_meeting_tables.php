<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meetings')) {
            Schema::create('meetings', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('agenda')->nullable();
                // Unguessable room id used on the video provider (never shown as a title).
                $table->string('room_name', 64)->unique();
                $table->unsignedBigInteger('host_user_id')->index();
                $table->dateTime('scheduled_at')->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('ended_at')->nullable();
                // scheduled | live | ended
                $table->string('status', 16)->default('scheduled')->index();

                // AI minutes pipeline: pending | processing | ready | failed | no_recording
                $table->string('minutes_status', 16)->default('pending')->index();
                $table->text('minutes_error')->nullable();
                $table->dateTime('minutes_started_at')->nullable();
                $table->dateTime('minutes_generated_at')->nullable();
                $table->longText('transcript')->nullable();
                $table->text('summary')->nullable();
                // topics, decisions, open_questions
                $table->json('minutes')->nullable();
                $table->string('ai_provider', 32)->nullable();
                $table->dateTime('tasks_generated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('meeting_participants')) {
            Schema::create('meeting_participants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('meeting_id');
                $table->unsignedBigInteger('user_id');
                $table->boolean('is_invited')->default(false);
                $table->dateTime('first_joined_at')->nullable();
                $table->dateTime('joined_at')->nullable();
                $table->dateTime('left_at')->nullable();
                $table->dateTime('last_seen_at')->nullable();
                // none | recording | stopped
                $table->string('recording_state', 16)->default('none');
                // Number of heartbeats where the browser reported recording was NOT running.
                $table->unsignedInteger('recording_gaps')->default(0);
                $table->timestamps();

                $table->unique(['meeting_id', 'user_id']);
                $table->index('meeting_id');
            });
        }

        if (! Schema::hasTable('meeting_recordings')) {
            Schema::create('meeting_recordings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('meeting_id');
                $table->unsignedBigInteger('user_id');
                // One recording per browser session (a person may leave and rejoin).
                $table->string('session_token', 64);
                $table->string('path')->nullable();
                $table->string('mime', 64)->nullable();
                $table->unsignedInteger('chunks_received')->default(0);
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->dateTime('started_at', 3)->nullable();
                $table->dateTime('last_chunk_at')->nullable();
                $table->dateTime('finalized_at')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                // pending | done | empty | failed
                $table->string('transcript_status', 16)->default('pending');
                $table->longText('transcript_segments')->nullable();
                $table->text('transcript_error')->nullable();
                $table->timestamps();

                $table->unique(['meeting_id', 'user_id', 'session_token'], 'meeting_rec_session_unique');
                $table->index('meeting_id');
            });
        }

        if (! Schema::hasTable('meeting_action_items')) {
            Schema::create('meeting_action_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('meeting_id')->index();
                $table->string('title', 1000);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('assignee_user_id')->nullable();
                // Name exactly as the AI wrote it (kept even when it could not be matched to a user).
                $table->string('assignee_name')->nullable();
                $table->boolean('assignee_matched')->default(false);
                $table->string('priority', 16)->default('normal');
                $table->date('due_date')->nullable();
                // tasks.id of the task created from this item.
                $table->unsignedBigInteger('task_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_action_items');
        Schema::dropIfExists('meeting_recordings');
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
    }
};
