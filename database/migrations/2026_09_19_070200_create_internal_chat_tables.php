<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_channels')) {
            Schema::create('chat_channels', function (Blueprint $table) {
                $table->id();
                $table->string('type', 16)->default('public'); // public, private, dm, bot
                $table->string('name')->nullable();
                $table->string('slug')->nullable()->unique();
                $table->string('topic')->nullable();
                $table->string('dm_key')->nullable()->unique();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->boolean('is_archived')->default(false);
                $table->timestamps();

                $table->index(['type', 'is_archived']);
            });
        }

        if (! Schema::hasTable('chat_channel_members')) {
            Schema::create('chat_channel_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('last_read_message_id')->nullable();
                $table->timestamp('last_read_at')->nullable();
                $table->boolean('muted')->default(false);
                $table->timestamps();

                $table->unique(['channel_id', 'user_id']);
                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->boolean('is_bot')->default(false);
                $table->string('bot_name')->nullable();
                $table->text('body')->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('attachment_name')->nullable();
                $table->json('mentions')->nullable();
                $table->string('command', 32)->nullable();
                $table->timestamps();

                $table->index(['channel_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_channel_members');
        Schema::dropIfExists('chat_channels');
    }
};
