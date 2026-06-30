<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_sessions', 'total_break_seconds')) {
                $table->unsignedInteger('total_break_seconds')->default(0)->after('total_idle_seconds');
            }
            if (! Schema::hasColumn('attendance_sessions', 'paused_at')) {
                $table->timestamp('paused_at')->nullable()->after('total_break_seconds');
            }
            if (! Schema::hasColumn('attendance_sessions', 'last_activity_state')) {
                $table->string('last_activity_state', 20)->nullable()->after('paused_at');
            }
        });

        Schema::table('attendance_activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_activity_logs', 'activity_state')) {
                $table->string('activity_state', 20)->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_activity_logs', 'activity_state')) {
                $table->dropColumn('activity_state');
            }
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            foreach (['last_activity_state', 'paused_at', 'total_break_seconds'] as $col) {
                if (Schema::hasColumn('attendance_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
