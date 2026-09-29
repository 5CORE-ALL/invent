<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_channel_members') || Schema::hasColumn('chat_channel_members', 'pinned_at')) {
            return;
        }

        Schema::table('chat_channel_members', function (Blueprint $table) {
            $table->timestamp('pinned_at')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_channel_members') && Schema::hasColumn('chat_channel_members', 'pinned_at')) {
            Schema::table('chat_channel_members', function (Blueprint $table) {
                $table->dropColumn('pinned_at');
            });
        }
    }
};
