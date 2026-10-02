<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_channels') || Schema::hasColumn('chat_channels', 'task_id')) {
            return;
        }

        Schema::table('chat_channels', function (Blueprint $table) {
            $table->unsignedBigInteger('task_id')->nullable()->after('dm_key');
            $table->unique('task_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chat_channels') || ! Schema::hasColumn('chat_channels', 'task_id')) {
            return;
        }

        Schema::table('chat_channels', function (Blueprint $table) {
            $table->dropUnique(['task_id']);
            $table->dropColumn('task_id');
        });
    }
};
