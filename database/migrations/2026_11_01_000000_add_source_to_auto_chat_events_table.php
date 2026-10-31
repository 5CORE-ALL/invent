<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto Task Events on analytics / active-channels pages read their rows from that page's own
 * JSON data endpoint (`source_url`, a relative GET path). `page_label` is the page title shown
 * in the rule list.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('auto_chat_events')) {
            return;
        }
        Schema::table('auto_chat_events', function (Blueprint $table) {
            if (! Schema::hasColumn('auto_chat_events', 'source_url')) {
                $table->string('source_url', 1000)->nullable()->after('page');
            }
            if (! Schema::hasColumn('auto_chat_events', 'page_label')) {
                $table->string('page_label', 160)->nullable()->after('source_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('auto_chat_events')) {
            return;
        }
        Schema::table('auto_chat_events', function (Blueprint $table) {
            foreach (['page_label', 'source_url'] as $col) {
                if (Schema::hasColumn('auto_chat_events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
