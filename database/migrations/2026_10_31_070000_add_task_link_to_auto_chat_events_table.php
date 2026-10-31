<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link stored on the one-time task an ACE rule creates (task manager "L1" / tasks.link1).
 * The create form pre-fills it with the page the rule is created on.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auto_chat_events') && ! Schema::hasColumn('auto_chat_events', 'task_link')) {
            Schema::table('auto_chat_events', function (Blueprint $table) {
                $table->string('task_link', 2048)->nullable()->after('task_group');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('auto_chat_events') && Schema::hasColumn('auto_chat_events', 'task_link')) {
            Schema::table('auto_chat_events', function (Blueprint $table) {
                $table->dropColumn('task_link');
            });
        }
    }
};
