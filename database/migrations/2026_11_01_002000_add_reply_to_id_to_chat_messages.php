<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_messages') || Schema::hasColumn('chat_messages', 'reply_to_id')) {
            return;
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            // WhatsApp-style quoted reply. Separate from parent_id, which is the Slack-style thread.
            $table->unsignedBigInteger('reply_to_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_messages') && Schema::hasColumn('chat_messages', 'reply_to_id')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->dropColumn('reply_to_id');
            });
        }
    }
};