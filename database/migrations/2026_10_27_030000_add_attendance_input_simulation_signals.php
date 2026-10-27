<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_screenshots') && ! Schema::hasColumn('attendance_screenshots', 'phash')) {
            Schema::table('attendance_screenshots', function (Blueprint $table) {
                // 64-bit difference hash (16 hex chars) used to spot a screen that never changes.
                $table->string('phash', 16)->nullable()->after('idle_seconds');
            });
        }

        if (Schema::hasTable('attendance_activity_logs') && ! Schema::hasColumn('attendance_activity_logs', 'flagged_processes')) {
            Schema::table('attendance_activity_logs', function (Blueprint $table) {
                // Comma separated remote-control / input-simulator processes the agent saw running.
                $table->string('flagged_processes', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_screenshots', 'phash')) {
            Schema::table('attendance_screenshots', function (Blueprint $table) {
                $table->dropColumn('phash');
            });
        }

        if (Schema::hasColumn('attendance_activity_logs', 'flagged_processes')) {
            Schema::table('attendance_activity_logs', function (Blueprint $table) {
                $table->dropColumn('flagged_processes');
            });
        }
    }
};
