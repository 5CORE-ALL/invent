<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_notification_prefs')) {
            return;
        }
        if (! Schema::hasColumn('chat_notification_prefs', 'tone')) {
            Schema::table('chat_notification_prefs', function (Blueprint $table) {
                $table->string('tone', 16)->default('default')->after('mode');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_notification_prefs') && Schema::hasColumn('chat_notification_prefs', 'tone')) {
            Schema::table('chat_notification_prefs', function (Blueprint $table) {
                $table->dropColumn('tone');
            });
        }
    }
};
