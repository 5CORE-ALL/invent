<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_policies') && ! Schema::hasColumn('attendance_policies', 'timezone')) {
            Schema::table('attendance_policies', function (Blueprint $table) {
                // Timezone `expected_start` / `expected_end` are written in. NULL = config('attendance.policy_timezone').
                $table->string('timezone', 64)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_policies', 'timezone')) {
            Schema::table('attendance_policies', function (Blueprint $table) {
                $table->dropColumn('timezone');
            });
        }
    }
};
