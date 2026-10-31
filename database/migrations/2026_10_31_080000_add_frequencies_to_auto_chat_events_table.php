<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How often an Auto Task Event re-creates its task for a row that still matches:
 * JSON list of daily | weekly | monthly. NULL = legacy "once per row, ever".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auto_chat_events') && ! Schema::hasColumn('auto_chat_events', 'frequencies')) {
            Schema::table('auto_chat_events', function (Blueprint $table) {
                $table->json('frequencies')->nullable()->after('match_mode');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('auto_chat_events') && Schema::hasColumn('auto_chat_events', 'frequencies')) {
            Schema::table('auto_chat_events', function (Blueprint $table) {
                $table->dropColumn('frequencies');
            });
        }
    }
};
