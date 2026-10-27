<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_activity_logs', 'display_count')) {
                // How many monitors the machine had at this heartbeat.
                $table->unsignedTinyInteger('display_count')->nullable()->after('input_tracked');
            }
            if (! Schema::hasColumn('attendance_activity_logs', 'background_media')) {
                // JSON list of entertainment / social window titles visible on another screen.
                $table->text('background_media')->nullable()->after('display_count');
            }
            if (! Schema::hasColumn('attendance_activity_logs', 'background_motion')) {
                // True when one of those windows was visibly changing (video playing).
                $table->boolean('background_motion')->default(false)->after('background_media');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_activity_logs', function (Blueprint $table) {
            foreach (['background_motion', 'background_media', 'display_count'] as $column) {
                if (Schema::hasColumn('attendance_activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
