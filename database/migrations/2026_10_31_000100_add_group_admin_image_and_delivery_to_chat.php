<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_channels') && ! Schema::hasColumn('chat_channels', 'image_path')) {
            Schema::table('chat_channels', function (Blueprint $table) {
                $table->string('image_path')->nullable();
            });
        }

        if (Schema::hasTable('chat_channel_members')) {
            Schema::table('chat_channel_members', function (Blueprint $table) {
                if (! Schema::hasColumn('chat_channel_members', 'is_admin')) {
                    $table->boolean('is_admin')->default(false);
                }
                if (! Schema::hasColumn('chat_channel_members', 'last_delivered_message_id')) {
                    $table->unsignedBigInteger('last_delivered_message_id')->nullable();
                }
            });

            // Existing groups: the creator becomes the first group admin.
            DB::table('chat_channel_members')
                ->whereIn('channel_id', function ($q) {
                    $q->select('id')->from('chat_channels')->where('type', 'group');
                })
                ->whereIn('user_id', function ($q) {
                    $q->select('created_by')->from('chat_channels')->whereColumn('chat_channels.id', 'chat_channel_members.channel_id');
                })
                ->update(['is_admin' => true]);

            // Do not report old messages as "not delivered": treat history as delivered.
            DB::statement(
                'UPDATE chat_channel_members m SET m.last_delivered_message_id = '
                .'(SELECT MAX(x.id) FROM chat_messages x WHERE x.channel_id = m.channel_id) '
                .'WHERE m.last_delivered_message_id IS NULL'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_channel_members')) {
            Schema::table('chat_channel_members', function (Blueprint $table) {
                foreach (['is_admin', 'last_delivered_message_id'] as $col) {
                    if (Schema::hasColumn('chat_channel_members', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('chat_channels') && Schema::hasColumn('chat_channels', 'image_path')) {
            Schema::table('chat_channels', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }
    }
};
