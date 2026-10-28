<?php

namespace Tests\Feature;

use App\Models\AttendanceAiFlag;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\Attendance\AttendanceAiMisuseService;
use App\Services\Attendance\AttendanceService;
use App\Support\AttendanceFraudAlerts;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * An unchanged screen / identical screenshots / one window for hours must never raise a Red Alert.
 * Genuine inactivity is handled only by the 10-minute auto-logout. Runs on in-memory sqlite.
 */
class AttendanceStaticScreenNeverAlertsTest extends TestCase
{
    private const DAY = '2026-10-26';

    protected function setUp(): void
    {
        // Booting the app loads a very large routes file.
        if ((int) ini_get('memory_limit') !== -1 && (int) ini_get('memory_limit') < 512) {
            ini_set('memory_limit', '1G');
        }
        parent::setUp();

        // Never touch a real database.
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
        ] as $m) {
            Artisan::call('migrate', ['--path' => "database/migrations/{$m}.php", '--force' => true]);
        }
        Schema::create('designations', function ($t) {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamps();
        });
    }

    private function user(): User
    {
        return User::forceCreate(['name' => 'T', 'email' => 't'.uniqid().'@x.com', 'password' => 'x']);
    }

    /**
     * 3h agent session, 720 heartbeats. The default is a genuinely working person whose screen never
     * changes: same window the whole time, identical screenshots, normal typing and normal pauses.
     */
    private function agentSession(User $u, array $log = [], bool $identicalShots = true): AttendanceSession
    {
        $s = AttendanceSession::create([
            'user_id' => $u->id, 'started_at' => self::DAY.' 08:00:00', 'ended_at' => self::DAY.' 11:00:00',
            'status' => 'completed', 'clock_source' => 'desktop', 'total_active_seconds' => 720 * 15,
            'heartbeat_count' => 720, 'work_location' => 'wfh',
        ]);

        $rows = [];
        for ($i = 0; $i < 720; $i++) {
            $rows[] = array_merge([
                'attendance_session_id' => $s->id, 'user_id' => $u->id,
                'recorded_at' => date('Y-m-d H:i:s', strtotime(self::DAY.' 08:00:00') + $i * 15),
                'is_active' => 1, 'activity_state' => 'working',
                // a short pause every so often, like a person reading
                'idle_seconds' => $i % 40 === 0 ? 90 : 3,
                'source' => 'desktop', 'app_name' => 'chrome', 'window_title' => 'Spreadsheet - Chrome',
                'keystroke_count' => 8, 'mouse_click_count' => 4, 'input_tracked' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ], $log);
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('attendance_activity_logs')->insert($chunk);
        }

        for ($i = 0; $i < 90; $i++) {
            DB::table('attendance_screenshots')->insert([
                'user_id' => $u->id, 'attendance_session_id' => $s->id,
                'captured_at' => date('Y-m-d H:i:s', strtotime(self::DAY.' 08:00:00') + $i * 120),
                // identical picture every time
                'storage_path' => $identicalShots ? 'same/screen.jpg' : 'x/'.$i.'.jpg',
                'file_size' => 12345, 'window_title' => 'Spreadsheet - Chrome',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $s;
    }

    private function openTypes(User $u): array
    {
        app(AttendanceAiMisuseService::class)->checkupUserDay($u, self::DAY);

        return AttendanceAiFlag::where('user_id', $u->id)->where('status', 'open')->pluck('flag_type')->all();
    }

    public function test_static_screen_for_hours_raises_no_flag_and_no_red_alert(): void
    {
        $u = $this->user();
        $this->agentSession($u);

        $this->assertSame([], $this->openTypes($u));
        $this->assertSame([], AttendanceFraudAlerts::forUsers([$u->id]));
        $this->assertNull(AttendanceFraudAlerts::detailForUser($u->id));
    }

    public function test_historical_static_screen_flags_cannot_show_a_red_alert(): void
    {
        $u = $this->user();
        foreach (['static_screen', 'totals_mismatch', 'suspicious_software', 'second_screen_media'] as $type) {
            AttendanceAiFlag::create([
                'user_id' => $u->id, 'flag_date' => now()->toDateString(), 'flag_type' => $type,
                'severity' => 'high', 'title' => 'Old flag', 'description' => 'Historical', 'source' => 'rules',
                'status' => 'open', 'evidence' => [],
            ]);
        }

        // Even a HIGH severity, open, in-window historical flag shows no triangle and no detail panel.
        $this->assertSame([], AttendanceFraudAlerts::forUsers([$u->id]));
        $this->assertNull(AttendanceFraudAlerts::detailForUser($u->id));
        foreach (['static_screen', 'totals_mismatch', 'suspicious_software', 'second_screen_media'] as $type) {
            $this->assertNotContains($type, AttendanceAiFlag::FRAUD_TYPES);
        }
    }

    public function test_window_or_screen_content_never_contributes_to_input_simulation(): void
    {
        $u = $this->user();
        // Machine-like input (never pauses, no keys or clicks) on top of an unchanged screen.
        $this->agentSession($u, ['idle_seconds' => 5, 'keystroke_count' => 0, 'mouse_click_count' => 0]);

        $this->assertContains('input_simulation', $this->openTypes($u));
        $flag = AttendanceAiFlag::where('user_id', $u->id)->where('flag_type', 'input_simulation')->first();
        $this->assertStringNotContainsStringIgnoringCase('same window', $flag->description);
        $this->assertArrayNotHasKey('longest_same_window', (array) $flag->evidence);
        $this->assertArrayNotHasKey('longest_same_window_minutes', (array) $flag->evidence);
        // Two input signals only (idle ceiling + no real input) -> high; a static screen adds nothing.
        $this->assertSame('high', $flag->severity);
    }

    public function test_static_screen_with_a_single_input_signal_stays_medium(): void
    {
        $u = $this->user();
        // Only the "never pauses" signal; real keys and clicks exist. Unchanged screen must not escalate it.
        $this->agentSession($u, ['idle_seconds' => 5]);

        $this->assertContains('input_simulation', $this->openTypes($u));
        $flag = AttendanceAiFlag::where('user_id', $u->id)->where('flag_type', 'input_simulation')->first();
        $this->assertSame('medium', $flag->severity);
    }

    public function test_no_screenshots_rule_is_unaffected(): void
    {
        $u = $this->user();
        $s = $this->agentSession($u);
        DB::table('attendance_screenshots')->where('attendance_session_id', $s->id)->delete();

        $this->assertSame(['no_screenshots'], $this->openTypes($u));
    }

    public function test_ten_minute_auto_logout_still_ends_a_genuinely_idle_session(): void
    {
        $this->assertSame(10, (int) config('attendance.auto_logout_idle_minutes'));
        $this->assertSame(600, AttendanceService::autoLogoutIdleSeconds());

        $svc = app(AttendanceService::class);

        // Nine minutes without input: still signed in, and no flag is created for it.
        $u = $this->user();
        $svc->clockIn($u, 'wfh', '127.0.0.1', 'UA');
        $nine = $svc->recordHeartbeat($u, ['is_active' => false, 'idle_seconds' => 540, 'elapsed_seconds' => 15, 'activity_state' => 'idle']);
        $this->assertArrayNotHasKey('auto_logout', $nine);
        $this->assertNotNull($svc->activeSession($u));

        // Ten minutes: signed out automatically.
        $ten = $svc->recordHeartbeat($u, ['is_active' => false, 'idle_seconds' => 600, 'elapsed_seconds' => 15, 'activity_state' => 'idle']);
        $this->assertTrue($ten['auto_logout'] ?? false);
        $this->assertNull($svc->activeSession($u));

        // An unchanged screen with the user still typing is NOT logged out.
        $v = $this->user();
        $svc->clockIn($v, 'wfh', '127.0.0.1', 'UA');
        $busy = $svc->recordHeartbeat($v, [
            'is_active' => true, 'idle_seconds' => 2, 'elapsed_seconds' => 15, 'activity_state' => 'working',
            'window_title' => 'Spreadsheet - Chrome', 'keystroke_count' => 20, 'mouse_click_count' => 5, 'input_tracked' => true,
        ]);
        $this->assertArrayNotHasKey('auto_logout', $busy);
        $this->assertNotNull($svc->activeSession($v));

        // Inactivity itself creates no suspicious-activity alert.
        $this->assertSame([], AttendanceFraudAlerts::forUsers([$u->id, $v->id]));
    }
}
