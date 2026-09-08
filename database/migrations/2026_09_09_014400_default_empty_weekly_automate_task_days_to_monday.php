<?php

use App\Support\AutomatedTaskSchedule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('automate_tasks')) {
            return;
        }

        DB::table('automate_tasks')
            ->whereRaw('LOWER(schedule_type) = ?', ['weekly'])
            ->where(function ($q) {
                $q->whereNull('schedule_days')->orWhere('schedule_days', '');
            })
            ->update([
                'schedule_days' => AutomatedTaskSchedule::DEFAULT_WEEKLY_DAYS,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Previous empty weekdays are not restorable safely.
    }
};
