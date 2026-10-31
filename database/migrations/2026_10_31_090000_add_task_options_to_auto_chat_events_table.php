<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra task-manager inputs for an Auto Task Event (titles, assignees, assignor, ETC,
 * corrective action, frequency, L2..Process links) stored as one JSON object.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auto_chat_events') && ! Schema::hasColumn('auto_chat_events', 'task_options')) {
            Schema::table('auto_chat_events', function (Blueprint $table) {
                $table->json('task_options')->nullable()->after('task_link');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('auto_chat_events') && Schema::hasColumn('auto_chat_events', 'task_options')) {
            Schema::table('auto_chat_events', function (Blueprint $table) {
                $table->dropColumn('task_options');
            });
        }
    }
};
