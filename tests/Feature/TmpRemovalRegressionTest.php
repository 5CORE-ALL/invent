<?php

namespace Tests\Feature;

use App\Models\AttendanceActivityLog;
use App\Models\AttendanceAiFlag;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\Attendance\AttendanceAiMisuseService;
use App\Services\Attendance\AttendanceFraudAlerts;
use App\Services\Attendance\AttendanceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** TEMPORARY regression test for the suspicious-software / second-screen removal. Deleted afterwards. */
class TmpRemovalRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Never touch a real database: force in-memory sqlite before anything runs.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        foreach ([
            '2014_10_12_000000_create_users_table',
            '2026_06_30_100000_create_attendance_monitoring_tables',
            '2026_06_30_200000_add_attendance_agent_tables',
            '2026_06_30_300000_add_attendance_break_tracking',
            '2026_06_30_400000_create_attendance_payroll_tables',
            '2026_10_27_030000_add_attendance_input_simulation_signals',
            '2026_10_27_040000_add_input_tracked_to_attendance_activity_logs',
            '2026_10_27_050000_add_timezone_to_attendance_policies',
            '2026_10_27_060000_add_second_screen_signals_to_attendance_activity_logs',
        ] as $m) {
            Artisan::call('migrate', ['--path' => "database/migrations/{$m}.php", '--realpath' => false, '--force' => true]);
        }
        \Illuminate\Support\Facades\Schema::create('designations', function ($t) { $t->id(); $t->string('name')->nullable(); $t->timestamps(); });
        config(['attendance.timezone' => 'UTC']);
    }

    private function user(): User
    {
        return User::forceCreate(['name' => 'T', 'email' => 't'.uniqid().'@x.com', 'password' => 'x']);
    }

    /** 3h desktop session of agent heartbeats. */
    private function makeSession(User $u, array $logOverrides = [], int $beats = 720, bool $shots = true): AttendanceSession
    {
        $s = AttendanceSession::create([
            'user_id' => $u->id, 'started_at' => '2026-10-26 08:00:00', 'ended_at' => '2026-10-26 11:00:00',
            'status' => 'completed', 'clock_source' => 'desktop', 'total_active_seconds' => $beats * 15,
            'heartbeat_count' => $beats, 'work_location' => 'wfh',
        ]);
        $rows = [];
        for ($i = 0; $i < $beats; $i++) {
            $rows[] = array_merge([
                'attendance_session_id' => $s->id, 'user_id' => $u->id,
                'recorded_at' => date('Y-m-d H:i:s', strtotime('2026-10-26 08:00:00') + $i * 15),
                'is_active' => 1, 'activity_state' => 'working', 'idle_seconds' => 3, 'source' => 'desktop',
                'app_name' => 'chrome', 'window_title' => 'Seller Central '.($i % 7),
                'keystroke_count' => 8, 'mouse_click_count' => 4, 'input_tracked' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ], $logOverrides);
        }
        foreach (array_chunk($rows, 200) as $c) {
            DB::table('attendance_activity_logs')->insert($c);
        }

        if ($shots) {
            for ($i = 0; $i < 90; $i++) {
                DB::table('attendance_screenshots')->insert([
                    'user_id' => $u->id, 'attendance_session_id' => $s->id,
                    'captured_at' => date('Y-m-d H:i:s', strtotime('2026-10-26 08:00:00') + $i * 120),
                    'storage_path' => 'x/'.$i.'.jpg', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return $s;
    }

    private function types(User $u): array
    {
        app(AttendanceAiMisuseService::class)->checkupUserDay($u, '2026-10-26');

        return AttendanceAiFlag::where('user_id', $u->id)->where('status', 'open')->pluck('flag_type')->all();
    }

    public function test_removed_types_are_gone_from_flag_model_and_config(): void
    {
        $this->assertArrayNotHasKey('suspicious_software', AttendanceAiFlag::TYPES);
        $this->assertArrayNotHasKey('second_screen_media', AttendanceAiFlag::TYPES);
        $this->assertNotContains('suspicious_software', AttendanceAiFlag::FRAUD_TYPES);
        $this->assertNotContains('second_screen_media', AttendanceAiFlag::FRAUD_TYPES);
        $this->assertNull(config('attendance.suspicious_process_patterns'));
        $this->assertNull(config('attendance.second_screen'));
        $this->assertContains('input_simulation', AttendanceAiFlag::FRAUD_TYPES);
        $this->assertFalse(method_exists(AttendanceService::class, 'secondScreenColumnsExist'));
        $this->assertFalse(method_exists(AttendanceService::class, 'flaggedProcessesColumnExists'));
    }

    public function test_legacy_signals_in_existing_db_columns_no_longer_raise_alerts(): void
    {
        // The DB columns still exist (migrations untouched). Old rows carrying remote-software and
        // second-screen movie data must NOT produce any flag now.
        $u = $this->user();
        $this->makeSession($u, [
            'flagged_processes' => 'anydesk,teamviewer',
            'display_count' => 2,
            'background_media' => json_encode(['Netflix - Chrome']),
            'background_motion' => 1,
            'idle_seconds' => 90,                       // realistic pauses so no jiggler signal
        ]);
        $this->assertSame([], $this->types($u));
        $this->assertFalse(AttendanceFraudAlerts::class === null);
    }

    public function test_normal_agent_session_is_clean(): void
    {
        $u = $this->user();
        $this->makeSession($u, ['idle_seconds' => 90]);
        $this->assertSame([], $this->types($u));
    }

    public function test_mouse_jiggler_detection_is_preserved(): void
    {
        $u = $this->user();
        // Never pauses beyond the ceiling, never idle, and almost no real keys/clicks.
        $this->makeSession($u, ['idle_seconds' => 5, 'keystroke_count' => 0, 'mouse_click_count' => 0]);
        $this->assertContains('input_simulation', $this->types($u));
    }

    public function test_missing_screenshots_rule_is_preserved(): void
    {
        $u = $this->user();
        $this->makeSession($u, ['idle_seconds' => 90], 720, false);
        $this->assertSame(['no_screenshots'], $this->types($u));
    }

    public function test_heartbeat_ignores_legacy_fields_and_stores_input_counts(): void
    {
        $u = $this->user();
        $svc = app(AttendanceService::class);
        $svc->clockIn($u, 'wfh', '127.0.0.1', 'UA');
        $r = $svc->recordHeartbeat($u, [
            'is_active' => true, 'idle_seconds' => 4, 'elapsed_seconds' => 15, 'activity_state' => 'working',
            'keystroke_count' => 7, 'mouse_click_count' => 3, 'input_tracked' => true,
            // sent by an agent build that still has the removed scanners:
            'flagged_processes' => ['anydesk'], 'display_count' => 3,
            'background_media' => ['YouTube'], 'background_motion' => true,
        ]);
        $this->assertTrue($r['ok']);
        $log = AttendanceActivityLog::latest('id')->first();
        $this->assertSame(7, (int) $log->keystroke_count);
        $this->assertSame(3, (int) $log->mouse_click_count);
        $this->assertTrue((bool) $log->input_tracked);
        $this->assertNull($log->flagged_processes);
        $this->assertNull($log->background_media);
        $this->assertNull($log->display_count);
    }

    public function test_ten_minute_auto_logout_is_preserved(): void
    {
        $u = $this->user();
        $svc = app(AttendanceService::class);
        $svc->clockIn($u, 'wfh', '127.0.0.1', 'UA');

        $short = $svc->recordHeartbeat($u, ['is_active' => false, 'idle_seconds' => 30, 'elapsed_seconds' => 15, 'activity_state' => 'idle']);
        $this->assertArrayNotHasKey('auto_logout', $short);

        $long = $svc->recordHeartbeat($u, ['is_active' => false, 'idle_seconds' => 11 * 60, 'elapsed_seconds' => 15, 'activity_state' => 'idle']);
        $this->assertTrue($long['auto_logout'] ?? false);
        $this->assertNull($svc->activeSession($u));
    }

    public function test_agent_config_no_longer_pushes_scan_lists_but_keeps_auto_logout(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Attendance/AttendanceAgentController.php'));
        $this->assertStringNotContainsString('suspicious_process_patterns', $src);
        $this->assertStringNotContainsString('entertainment_window_patterns', $src);
        $this->assertStringContainsString('auto_logout_idle_seconds', $src);
        $this->assertStringContainsString('auto_logout_warn_seconds', $src);
    }
}
