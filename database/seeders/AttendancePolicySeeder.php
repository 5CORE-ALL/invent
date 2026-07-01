<?php

namespace Database\Seeders;

use App\Models\AttendancePolicy;
use Illuminate\Database\Seeder;

class AttendancePolicySeeder extends Seeder
{
    public function run(): void
    {
        if (AttendancePolicy::query()->where('is_default', true)->exists()) {
            return;
        }

        AttendancePolicy::create([
            'name' => 'Default WFH Policy',
            'designation_id' => null,
            'expected_start' => '09:30:00',
            'expected_end' => '18:30:00',
            'grace_minutes' => 15,
            'min_daily_hours' => 8.0,
            'max_idle_minutes_per_hour' => 15,
            'min_active_percent' => 60,
            'wfh_allowed' => true,
            'monitoring_enabled' => true,
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
