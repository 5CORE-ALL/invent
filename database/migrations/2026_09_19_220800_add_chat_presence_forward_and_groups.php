<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_messages') && ! Schema::hasColumn('chat_messages', 'forwarded_from_id')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->unsignedBigInteger('forwarded_from_id')->nullable()->after('command');
                $table->index('forwarded_from_id');
            });
        }

        if (! Schema::hasTable('chat_presences')) {
            Schema::create('chat_presences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chat_messages') && Schema::hasColumn('chat_messages', 'forwarded_from_id')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->dropIndex(['forwarded_from_id']);
                $table->dropColumn('forwarded_from_id');
            });
        }
        Schema::dropIfExists('chat_presences');
    }
};
