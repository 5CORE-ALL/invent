<?php

namespace Tests\Unit;

use App\Support\SchedulerLease;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchedulerLeaseTest extends TestCase
{
    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();

        // A real file (not :memory:) so a second PHP process can share it in the crash test.
        $this->dbFile = sys_get_temp_dir().'/scheduler_lease_test_'.getmypid().'.sqlite';
        @unlink($this->dbFile);
        touch($this->dbFile);

        Config::set('database.default', 'lease_test');
        Config::set('database.connections.lease_test', [
            'driver' => 'sqlite', 'database' => $this->dbFile, 'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        DB::purge('lease_test');

        (require base_path('database/migrations/2026_10_31_020000_create_scheduler_leases_table.php'))->up();
    }

    protected function tearDown(): void
    {
        DB::disconnect('lease_test');
        @unlink($this->dbFile);
        parent::tearDown();
    }

    private function expire(string $name): void
    {
        DB::table('scheduler_leases')->where('name', $name)->update(['expires_at' => now()->subSeconds(5)->toDateTimeString()]);
    }

    public function test_only_one_run_holds_the_lease_and_it_is_free_again_after_release(): void
    {
        $a = SchedulerLease::acquire('job');
        $this->assertNotNull($a);
        $this->assertNull(SchedulerLease::acquire('job'), 'overlapping run must be refused');
        $this->assertNotNull(SchedulerLease::acquire('other-job'), 'leases are per job');

        $a->release('success');
        $row = DB::table('scheduler_leases')->where('name', 'job')->first();
        $this->assertNull($row->owner_token);
        $this->assertSame('success', $row->last_status);

        $b = SchedulerLease::acquire('job');
        $this->assertNotNull($b);
        $this->assertNull($b->recovered, 'a clean release is not a stale recovery');
        $this->assertNotSame($a->token, $b->token);
    }

    public function test_heartbeat_extends_the_lease_so_a_long_run_is_never_taken_over(): void
    {
        $a = SchedulerLease::acquire('job', 5, 1);
        $this->expire('job');                       // would be stale without a heartbeat
        $this->assertTrue($a->beat(true));          // still the owner: renews
        $this->assertGreaterThan(now()->toDateTimeString(), DB::table('scheduler_leases')->value('expires_at'));
        $this->assertNull(SchedulerLease::acquire('job'), 'a live, heart-beating run is not taken over');
    }

    public function test_expired_lease_is_recovered_automatically_by_the_next_run(): void
    {
        $dead = SchedulerLease::acquire('job', 5, 1); // process "dies": it never beats or releases again
        $this->expire('job');

        $next = SchedulerLease::acquire('job');
        $this->assertNotNull($next, 'the next scheduled run must be able to start without manual unlocking');
        $this->assertNotNull($next->recovered);
        $this->assertSame($dead->token, $next->recovered['execution']);
        $this->assertTrue($next->owns());
    }

    public function test_a_stale_process_cannot_renew_release_or_overwrite_the_new_owner(): void
    {
        $old = SchedulerLease::acquire('job', 5, 1);
        $this->expire('job');
        $new = SchedulerLease::acquire('job');
        $this->assertNotNull($new);

        $this->assertFalse($old->beat(true), 'old owner learns it lost the lease');
        $this->assertTrue($old->lost());

        $old->release('failed');                       // must be a no-op for the new owner
        $row = DB::table('scheduler_leases')->where('name', 'job')->first();
        $this->assertSame($new->token, $row->owner_token);
        $this->assertNull($row->last_status);

        $this->assertTrue($new->beat(true), 'the new owner is unaffected');
        $this->assertNull(SchedulerLease::acquire('job'));
    }

    public function test_heartbeat_is_throttled(): void
    {
        $a = SchedulerLease::acquire('job', 60, 30);
        $first = DB::table('scheduler_leases')->value('heartbeat_at');
        DB::table('scheduler_leases')->update(['heartbeat_at' => '2000-01-01 00:00:00']);
        $this->assertTrue($a->beat());              // within 30 s: no write
        $this->assertSame('2000-01-01 00:00:00', DB::table('scheduler_leases')->value('heartbeat_at'));
        $this->assertNotSame($first, '2000-01-01 00:00:00');
        $this->assertTrue($a->beat(true));          // forced: writes
        $this->assertNotSame('2000-01-01 00:00:00', DB::table('scheduler_leases')->value('heartbeat_at'));
    }

    public function test_real_process_crash_is_recovered_by_the_next_run(): void
    {
        if (! function_exists('posix_kill') || ! defined('SIGKILL')) {
            $this->markTestSkipped('posix required');
        }

        $script = tempnam(sys_get_temp_dir(), 'lease_child_').'.php';
        file_put_contents($script, '<?php
            require '.var_export(base_path('vendor/autoload.php'), true).';
            $app = require '.var_export(base_path('bootstrap/app.php'), true).';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            config(["database.default" => "lease_test", "database.connections.lease_test" => [
                "driver" => "sqlite", "database" => '.var_export($this->dbFile, true).', "prefix" => "", "foreign_key_constraints" => false]]);
            $lease = App\Support\SchedulerLease::acquire("crash-job", 5, 1);
            echo $lease ? "ACQUIRED" : "REFUSED";
            fflush(STDOUT);
            posix_kill(getmypid(), SIGKILL); // hard crash: no finally, no shutdown handler, no release
        ');
        $out = shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>/dev/null');
        @unlink($script);

        $this->assertSame('ACQUIRED', trim((string) $out));
        $this->assertNull(SchedulerLease::acquire('crash-job'), 'lease is still held right after the crash');
        $this->assertNotNull(DB::table('scheduler_leases')->where('name', 'crash-job')->value('owner_token'));

        sleep(6); // lease ttl (5 s) passes with no heartbeat

        $next = SchedulerLease::acquire('crash-job');
        $this->assertNotNull($next, 'next scheduled run reclaims the lease on its own');
        $this->assertNotNull($next->recovered);
        $next->release('success');
        $this->assertNull(DB::table('scheduler_leases')->where('name', 'crash-job')->value('owner_token'));
    }

    public function test_two_runs_started_at_the_same_time_cannot_both_win(): void
    {
        $winners = 0;
        $leases = [];
        for ($i = 0; $i < 10; $i++) {
            $l = SchedulerLease::acquire('race');
            if ($l !== null) {
                $winners++;
                $leases[] = $l;
            }
        }
        $this->assertSame(1, $winners);
    }
}
