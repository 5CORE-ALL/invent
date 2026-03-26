<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('automate_tasks')) {
            return;
        }

        DB::table('automate_tasks')
            ->whereRaw('LOWER(schedule_type) = ?', ['daily'])
            ->update([
                'schedule_time' => '12:01:00',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op: previous per-task daily times are not restorable safely
    }
};
