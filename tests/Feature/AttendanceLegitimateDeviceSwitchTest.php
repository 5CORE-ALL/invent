<?php

namespace Tests\Feature;

use App\Models\AttendanceAiFlag;
use App\Models\AttendanceDevice;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\Attendance\AttendanceAiMisuseService;
use App\Services\Attendance\AttendanceDeviceService;
use App\Services\Attendance\AttendanceService;
use App\Support\AttendanceFraudAlerts;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Using a different laptop / browser / IP is never a finding by itself (damaged or replaced laptop).
 * Runs on in-memory sqlite.
 */
class AttendanceLegitimateDeviceSwitchTest extends TestCase
{
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

    private function device(User $u, string $machine, string $name, string $os): AttendanceDevice
    {
        return AttendanceDevice::create([
            'user_id' => $u->id, 'machine_id' => $machine, 'device_name' => $name, 'os_name' => $os,
            'os_version' => '1', 'agent_version' => '1.0', 'last_seen_at' => now(), 'is_active' => true,
        ]);
    }

    /** A normal, clean 3h agent session on the given day, on the given device / IP / browser. */
    private function cleanSession(User $u, string $day, AttendanceDevice $d, string $ip, string $ua): AttendanceSession
    {
        $s = AttendanceSession::create([
            'user_id' => $u->id, 'attendance_device_id' => $d->id, 'started_at' => $day.' 08:00:00',
            'ended_at' => $day.' 11:00:00', 'status' => 'completed', 'clock_source' => 'desktop',
            'total_active_seconds' => 720 * 15, 'heartbeat_count' => 720, 'work_location' => 'wfh',
            'ip_address' => $ip, 'user_agent' => $ua,
        ]);

        $rows = [];
        for ($i = 0; $i < 720; $i++) {
            $rows[] = [
                'attendance_session_id' => $s->id, 'user_id' => $u->id, 'attendance_device_id' => $d->id,
                'recorded_at' => date('Y-m-d H:i:s', strtotime($day.' 08:00:00') + $i * 15),
                'is_active' => 1, 'activity_state' => 'working', 'idle_seconds' => $i % 40 === 0 ? 90 : 3,
                'source' => 'desktop', 'app_name' => 'chrome', 'window_title' => 'Seller Central '.($i % 9),
                'keystroke_count' => 8, 'mouse_click_count' => 4, 'input_tracked' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('attendance_activity_logs')->insert($chunk);
        }
        for ($i = 0; $i < 90; $i++) {
            DB::table('attendance_screenshots')->insert([
                'user_id' => $u->id, 'attendance_session_id' => $s->id, 'attendance_device_id' => $d->id,
                'captured_at' => date('Y-m-d H:i:s', strtotime($day.' 08:00:00') + $i * 120),
                'storage_path' => 'x/'.$s->id.'-'.$i.'.jpg', 'file_size' => 100,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $s;
    }

    private function checkup(User $u, string $day): array
    {
        app(AttendanceAiMisuseService::class)->checkupUserDay($u, $day);

        return AttendanceAiFlag::where('user_id', $u->id)->where('status', 'open')->pluck('flag_type')->all();
    }

    public function test_switching_between_several_laptops_in_a_week_raises_nothing(): void
    {
        $u = $this->user();
        // Damaged laptop -> loaner -> repaired original -> replacement, each with its own machine id,
        // OS, IP address and browser.
        $uas = ['Chrome/118 Windows', 'Firefox/119 Linux', 'Safari/17 macOS', 'Edge/118 Windows'];
        $ips = ['103.10.1.5', '49.36.2.9', '182.7.7.7', '2.2.2.2'];
        $days = ['2026-10-22', '2026-10-23', '2026-10-24', '2026-10-25'];
        foreach ($days as $i => $day) {
            $d = $this->device($u, 'machine-'.$i, 'Laptop '.$i, ['Windows', 'Linux', 'macOS', 'Windows'][$i]);
            $this->cleanSession($u, $day, $d, $ips[$i], $uas[$i]);
        }
        $this->assertSame(4, AttendanceDevice::where('user_id', $u->id)->count());

        foreach ($days as $day) {
            $this->assertSame([], $this->checkup($u, $day), "no flag on $day");
        }
        $this->assertSame(0, AttendanceAiFlag::where('user_id', $u->id)->count());
        $this->assertSame([], AttendanceFraudAlerts::forUsers([$u->id]));
        $this->assertNull(AttendanceFraudAlerts::detailForUser($u->id));
    }

    public function test_same_day_on_two_laptops_with_a_different_browser_and_ip_raises_nothing(): void
    {
        $u = $this->user();
        $day = '2026-10-26';
        $this->cleanSession($u, $day, $this->device($u, 'old', 'Broken laptop', 'Windows'), '103.10.1.5', 'Chrome');
        // second, separate morning session from another machine
        $s = $this->cleanSession($u, $day, $this->device($u, 'new', 'Replacement', 'macOS'), '49.36.2.9', 'Safari');
        $this->assertNotNull($s);

        $this->assertSame([], $this->checkup($u, $day));
        $this->assertSame([], AttendanceFraudAlerts::forUsers([$u->id]));
    }

    public function test_registering_a_new_device_is_allowed_and_does_not_deactivate_or_flag_anything(): void
    {
        $u = $this->user();
        $svc = app(AttendanceDeviceService::class);

        $old = $svc->registerOrUpdate($u, ['machine_id' => 'old-laptop', 'device_name' => 'Old', 'os_name' => 'Windows']);
        $new = $svc->registerOrUpdate($u, ['machine_id' => 'new-laptop', 'device_name' => 'New', 'os_name' => 'macOS']);

        $this->assertNotSame($old->id, $new->id);
        $this->assertTrue((bool) $new->fresh()->is_active);
        $this->assertSame(0, AttendanceAiFlag::where('user_id', $u->id)->count());
    }

    public function test_session_is_still_bound_to_its_own_device(): void
    {
        // Existing access control, unchanged: a session belongs to the machine it was started on.
        $u = $this->user();
        $a = $this->device($u, 'a', 'A', 'Windows');
        $b = $this->device($u, 'b', 'B', 'macOS');
        $svc = app(AttendanceService::class);

        $svc->clockIn($u, 'wfh', '1.1.1.1', 'Chrome', $a->id);
        $this->assertNotNull($svc->onDutySession($u, $a));
        $this->assertNull($svc->onDutySession($u, $b));
    }

    public function test_old_multi_device_flags_cannot_show_a_red_alert(): void
    {
        $u = $this->user();
        AttendanceAiFlag::create([
            'user_id' => $u->id, 'flag_date' => now()->toDateString(), 'flag_type' => 'multi_device',
            'severity' => 'high', 'title' => 'Account used from 5 machines in 7 days', 'description' => 'Historical',
            'source' => 'rules', 'status' => 'open', 'evidence' => [],
        ]);

        $this->assertNotContains('multi_device', AttendanceAiFlag::FRAUD_TYPES);
        $this->assertSame([], AttendanceFraudAlerts::forUsers([$u->id]));
        $this->assertNull(AttendanceFraudAlerts::detailForUser($u->id));
        // The historical row itself is untouched.
        $this->assertSame(1, AttendanceAiFlag::where('user_id', $u->id)->where('status', 'open')->count());
    }

    public function test_genuine_inactivity_on_a_new_laptop_still_auto_logs_out_after_ten_minutes(): void
    {
        $this->assertSame(600, AttendanceService::autoLogoutIdleSeconds());

        $u = $this->user();
        $new = $this->device($u, 'replacement', 'Replacement', 'macOS');
        $svc = app(AttendanceService::class);
        $svc->clockIn($u, 'wfh', '9.9.9.9', 'Safari', $new->id);

        $nine = $svc->recordHeartbeat($u, ['is_active' => false, 'idle_seconds' => 540, 'elapsed_seconds' => 15, 'activity_state' => 'idle', 'device_id' => $new->id]);
        $this->assertArrayNotHasKey('auto_logout', $nine);

        $ten = $svc->recordHeartbeat($u, ['is_active' => false, 'idle_seconds' => 600, 'elapsed_seconds' => 15, 'activity_state' => 'idle', 'device_id' => $new->id]);
        $this->assertTrue($ten['auto_logout'] ?? false);
        $this->assertNull($svc->activeSession($u));
        $this->assertSame([], AttendanceFraudAlerts::forUsers([$u->id]));
    }

    public function test_real_fraud_signals_are_still_detected_on_a_new_device(): void
    {
        // A new laptop gives no free pass: a jiggler there is still caught (evidence, not the device).
        $u = $this->user();
        $day = '2026-10-26';
        $d = $this->device($u, 'new', 'New', 'Windows');
        $s = $this->cleanSession($u, $day, $d, '1.1.1.1', 'Chrome');
        DB::table('attendance_activity_logs')->where('attendance_session_id', $s->id)
            ->update(['idle_seconds' => 5, 'keystroke_count' => 0, 'mouse_click_count' => 0]);

        $this->assertContains('input_simulation', $this->checkup($u, $day));

        $v = $this->user();
        $s2 = $this->cleanSession($v, $day, $this->device($v, 'n2', 'N2', 'Windows'), '1.1.1.1', 'Chrome');
        DB::table('attendance_screenshots')->where('attendance_session_id', $s2->id)->delete();
        $this->assertSame(['no_screenshots'], $this->checkup($v, $day));
    }
}
