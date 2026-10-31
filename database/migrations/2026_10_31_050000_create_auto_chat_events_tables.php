<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ACE = Automated Chat Event.
 *
 * A rule watches a page's table (column condition) and/or a chat (keyword in a
 * channel) and creates a ONE-TIME task in the task manager. `auto_chat_event_runs`
 * remembers what each rule already fired for, so a task is never created twice
 * for the same row / message.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('auto_chat_events')) {
            Schema::create('auto_chat_events', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('page', 40)->index();            // users | active_channels
                $table->string('trigger_type', 20);             // column | chat | chat_column

                // Column condition (optional when trigger is chat-only)
                $table->string('column_key', 80)->nullable();
                $table->string('operator', 20)->nullable();
                $table->string('value', 500)->nullable();

                // Chat condition (optional when trigger is column-only)
                $table->unsignedBigInteger('chat_channel_id')->nullable()->index(); // null = any channel
                $table->string('chat_keyword', 255)->nullable(); // comma-separated, any-of

                // Task template (title/description support {placeholders})
                $table->string('task_title', 1000);
                $table->text('task_description')->nullable();
                $table->string('task_group', 255)->nullable();
                $table->string('priority', 10)->default('normal'); // low | normal | high
                $table->unsignedSmallInteger('due_in_days')->default(1);
                $table->string('assignee_mode', 20)->default('fixed'); // fixed | row_user | chat_sender
                $table->unsignedBigInteger('assignee_id')->nullable();

                $table->boolean('is_active')->default(true)->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('auto_chat_event_runs')) {
            Schema::create('auto_chat_event_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ace_id')->index();
                $table->string('subject_key', 80);               // row:{id} | msg:{id}
                $table->string('subject_label', 255)->nullable();
                $table->string('source', 10);                    // column | chat
                $table->unsignedBigInteger('task_id')->nullable();
                $table->string('status', 10)->default('created'); // created | failed
                $table->string('error', 500)->nullable();
                $table->timestamps();

                $table->unique(['ace_id', 'subject_key'], 'ace_runs_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_chat_event_runs');
        Schema::dropIfExists('auto_chat_events');
    }
};
