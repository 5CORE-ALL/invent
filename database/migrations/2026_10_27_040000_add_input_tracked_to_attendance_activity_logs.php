<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_activity_logs') && ! Schema::hasColumn('attendance_activity_logs', 'input_tracked')) {
            Schema::table('attendance_activity_logs', function (Blueprint $table) {
                // keystroke_count / mouse_click_count default to 0, so 0 alone cannot tell
                // "typed nothing" from "this agent cannot count". True only when the agent's
                // global input hook was working for this heartbeat.
                $table->boolean('input_tracked')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_activity_logs', 'input_tracked')) {
            Schema::table('attendance_activity_logs', function (Blueprint $table) {
                $table->dropColumn('input_tracked');
            });
        }
    }
};
