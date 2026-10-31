<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ACE rules can have several column conditions: `conditions` is a JSON list of
 * {column_key, operator, value}; `match_mode` is "all" (AND) or "any" (OR).
 * The old single column_key/operator/value columns still hold the first condition.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('auto_chat_events')) {
            return;
        }

        Schema::table('auto_chat_events', function (Blueprint $table) {
            if (! Schema::hasColumn('auto_chat_events', 'conditions')) {
                $table->json('conditions')->nullable()->after('value');
            }
            if (! Schema::hasColumn('auto_chat_events', 'match_mode')) {
                $table->string('match_mode', 3)->default('all')->after('conditions'); // all | any
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('auto_chat_events')) {
            return;
        }

        Schema::table('auto_chat_events', function (Blueprint $table) {
            foreach (['conditions', 'match_mode'] as $col) {
                if (Schema::hasColumn('auto_chat_events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
