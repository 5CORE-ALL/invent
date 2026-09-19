<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'chat_messages',
            'chat_channels',
            'chat_channel_members',
            'chat_reactions',
            'chat_bookmarks',
            'chat_notification_prefs',
            'chat_audits',
            'chat_health_events',
            'chat_presences',
        ] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }
            try {
                DB::statement("ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
            } catch (\Throwable) {
            }
        }
    }

    public function down(): void
    {
    }
};
