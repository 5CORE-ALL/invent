<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Meet integration for the existing Meeting Room section.
 *
 * ADDITIVE ONLY: nothing is dropped, renamed or rewritten. Existing meetings keep working because
 * every new column is nullable or has a default that describes the legacy behaviour
 * (provider = 'jitsi', priority = 'normal').
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meetings')) {
            Schema::table('meetings', function (Blueprint $table) {
                $add = fn (string $col) => ! Schema::hasColumn('meetings', $col);

                // jitsi (legacy in-app room) | google_meet
                if ($add('provider')) {
                    $table->string('provider', 16)->default('jitsi')->index();
                }
                // urgent | immediate | normal
                if ($add('priority')) {
                    $table->string('priority', 12)->default('normal')->index();
                }
                // Internal meeting room 1..4
                if ($add('room_slot')) {
                    $table->unsignedTinyInteger('room_slot')->nullable();
                }
                if ($add('scheduled_end_at')) {
                    $table->dateTime('scheduled_end_at')->nullable();
                }

                // Google Calendar / Meet
                if ($add('google_request_id')) {
                    // Idempotency key: used as conferenceData.createRequest.requestId and to derive the event id.
                    $table->string('google_request_id', 64)->nullable()->unique();
                }
                if ($add('google_event_id')) {
                    $table->string('google_event_id', 255)->nullable()->index();
                }
                if ($add('google_calendar_id')) {
                    $table->string('google_calendar_id', 255)->nullable();
                }
                if ($add('google_meet_url')) {
                    $table->string('google_meet_url', 500)->nullable();
                }
                if ($add('google_html_link')) {
                    $table->string('google_html_link', 500)->nullable();
                }
                // pending | synced | failed | cancelled
                if ($add('google_sync_status')) {
                    $table->string('google_sync_status', 16)->nullable()->index();
                }
                if ($add('google_sync_error')) {
                    $table->text('google_sync_error')->nullable();
                }
                if ($add('google_sync_attempts')) {
                    $table->unsignedSmallInteger('google_sync_attempts')->default(0);
                }
                if ($add('google_synced_at')) {
                    $table->dateTime('google_synced_at')->nullable();
                }
                if ($add('google_conference_synced_at')) {
                    $table->dateTime('google_conference_synced_at')->nullable();
                }

                // Actual time taken (kept separate from the scheduled start/end)
                if ($add('actual_started_at')) {
                    $table->dateTime('actual_started_at')->nullable();
                }
                if ($add('actual_ended_at')) {
                    $table->dateTime('actual_ended_at')->nullable();
                }
                if ($add('time_taken_seconds')) {
                    $table->unsignedInteger('time_taken_seconds')->nullable();
                }

                if ($add('cancelled_at')) {
                    $table->dateTime('cancelled_at')->nullable();
                }
                if ($add('cancelled_by')) {
                    $table->unsignedBigInteger('cancelled_by')->nullable();
                }
            });

            // Composite index for the room-conflict query.
            try {
                Schema::table('meetings', function (Blueprint $table) {
                    $table->index(['room_slot', 'scheduled_at', 'scheduled_end_at'], 'meetings_room_slot_time_idx');
                });
            } catch (\Throwable $e) {
                // index already exists
            }
        }

        if (Schema::hasTable('meeting_action_items')) {
            Schema::table('meeting_action_items', function (Blueprint $table) {
                // pending | in_progress | completed
                if (! Schema::hasColumn('meeting_action_items', 'status')) {
                    $table->string('status', 16)->default('pending')->index();
                }
                if (! Schema::hasColumn('meeting_action_items', 'completed_at')) {
                    $table->dateTime('completed_at')->nullable();
                }
                if (! Schema::hasColumn('meeting_action_items', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
            });
        }

        // Manually written Minutes of Meeting (one row per meeting). The AI-generated minutes of legacy
        // meetings stay in meetings.summary / meetings.minutes and are never overwritten.
        if (! Schema::hasTable('meeting_minutes')) {
            Schema::create('meeting_minutes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('meeting_id')->unique();
                $table->longText('discussion')->nullable();
                $table->longText('complete_minutes')->nullable();
                $table->longText('summary')->nullable();
                $table->longText('decisions')->nullable();
                $table->longText('issues')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        // Recordings that are not browser-audio sessions: uploaded files, pasted links and
        // recordings that Google Meet really generated (state FILE_GENERATED).
        if (! Schema::hasTable('meeting_external_recordings')) {
            Schema::create('meeting_external_recordings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('meeting_id')->index();
                // upload | link | google_meet
                $table->string('source', 16);
                $table->string('title')->nullable();
                $table->string('url', 2000)->nullable();
                $table->string('path')->nullable();
                $table->string('original_name')->nullable();
                $table->string('mime', 100)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                // Google resource name (conferenceRecords/x/recordings/y): makes the sync idempotent.
                $table->string('google_resource', 255)->nullable()->unique();
                $table->unsignedBigInteger('added_by')->nullable();
                $table->timestamps();
            });
        }

        // The Google account that owns the calendar events / Meet spaces. Tokens are encrypted.
        if (! Schema::hasTable('google_meet_connections')) {
            Schema::create('google_meet_connections', function (Blueprint $table) {
                $table->id();
                $table->string('email')->nullable();
                $table->text('refresh_token');
                $table->text('scopes')->nullable();
                $table->unsignedBigInteger('connected_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: this migration only ever added columns/tables that may now
        // hold business data (meeting minutes, recordings, Google event ids). Roll back by hand if needed.
    }
};
