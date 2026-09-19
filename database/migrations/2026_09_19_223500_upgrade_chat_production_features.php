<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_messages')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                if (! Schema::hasColumn('chat_messages', 'client_id')) {
                    $table->string('client_id', 64)->nullable()->after('id');
                }
                if (! Schema::hasColumn('chat_messages', 'parent_id')) {
                    $table->unsignedBigInteger('parent_id')->nullable()->after('channel_id');
                }
                if (! Schema::hasColumn('chat_messages', 'edited_at')) {
                    $table->timestamp('edited_at')->nullable();
                }
                if (! Schema::hasColumn('chat_messages', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable();
                }
                if (! Schema::hasColumn('chat_messages', 'pinned_at')) {
                    $table->timestamp('pinned_at')->nullable();
                }
                if (! Schema::hasColumn('chat_messages', 'pinned_by')) {
                    $table->unsignedBigInteger('pinned_by')->nullable();
                }
                if (! Schema::hasColumn('chat_messages', 'task_id')) {
                    $table->unsignedBigInteger('task_id')->nullable();
                }
                if (! Schema::hasColumn('chat_messages', 'attachment_mime')) {
                    $table->string('attachment_mime', 120)->nullable();
                }
                if (! Schema::hasColumn('chat_messages', 'attachment_size')) {
                    $table->unsignedInteger('attachment_size')->nullable();
                }
            });

            try {
                Schema::table('chat_messages', function (Blueprint $table) {
                    $table->unique('client_id');
                });
            } catch (\Throwable) {
            }
            try {
                Schema::table('chat_messages', function (Blueprint $table) {
                    $table->index(['channel_id', 'parent_id', 'id']);
                    $table->index(['channel_id', 'created_at']);
                    $table->index('user_id');
                    $table->index('task_id');
                    $table->index('pinned_at');
                });
            } catch (\Throwable) {
            }
        }

        if (Schema::hasTable('chat_channel_members')) {
            Schema::table('chat_channel_members', function (Blueprint $table) {
                if (! Schema::hasColumn('chat_channel_members', 'notify_pref')) {
                    $table->string('notify_pref', 16)->default('all');
                }
            });
            try {
                Schema::table('chat_channel_members', function (Blueprint $table) {
                    $table->index(['user_id', 'last_read_message_id']);
                    $table->index(['channel_id', 'last_read_message_id']);
                });
            } catch (\Throwable) {
            }
        }

        if (Schema::hasTable('chat_presences')) {
            Schema::table('chat_presences', function (Blueprint $table) {
                if (! Schema::hasColumn('chat_presences', 'status')) {
                    $table->string('status', 16)->default('active');
                }
                if (! Schema::hasColumn('chat_presences', 'typing_channel_id')) {
                    $table->unsignedBigInteger('typing_channel_id')->nullable();
                }
                if (! Schema::hasColumn('chat_presences', 'typing_at')) {
                    $table->timestamp('typing_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('chat_reactions')) {
            Schema::create('chat_reactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('message_id');
                $table->unsignedBigInteger('user_id');
                $table->string('emoji', 32);
                $table->timestamps();
                $table->unique(['message_id', 'user_id', 'emoji']);
                $table->index('message_id');
            });
        }

        if (! Schema::hasTable('chat_bookmarks')) {
            Schema::create('chat_bookmarks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('message_id');
                $table->timestamps();
                $table->unique(['user_id', 'message_id']);
            });
        }

        if (! Schema::hasTable('chat_notification_prefs')) {
            Schema::create('chat_notification_prefs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('mode', 16)->default('all');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_audits')) {
            Schema::create('chat_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action', 64);
                $table->string('target_type', 64)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->unsignedBigInteger('channel_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['action', 'created_at']);
                $table->index('channel_id');
            });
        }

        if (! Schema::hasTable('chat_health_events')) {
            Schema::create('chat_health_events', function (Blueprint $table) {
                $table->id();
                $table->string('type', 64);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('channel_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['type', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_health_events');
        Schema::dropIfExists('chat_audits');
        Schema::dropIfExists('chat_notification_prefs');
        Schema::dropIfExists('chat_bookmarks');
        Schema::dropIfExists('chat_reactions');
    }
};
