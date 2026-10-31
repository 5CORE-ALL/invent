<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_channel_members') || Schema::hasColumn('chat_channel_members', 'is_admin')) {
            return;
        }

        Schema::table('chat_channel_members', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_channel_members') && Schema::hasColumn('chat_channel_members', 'is_admin')) {
            Schema::table('chat_channel_members', function (Blueprint $table) {
                $table->dropColumn('is_admin');
            });
        }
    }
};