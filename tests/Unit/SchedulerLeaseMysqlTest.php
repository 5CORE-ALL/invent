<?php

namespace Tests\Unit;

use App\Support\SchedulerLease;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The lease against a real MySQL/MariaDB server, with real concurrent PHP processes.
 * A throw-away database is created and dropped; no existing database is touched.
 * Skipped when no MySQL server is reachable with the credentials of config('database.connections.mysql').
 */
class SchedulerLeaseMysqlTest extends TestCase
{
    private string $scratch = '';

    /** @var array<string, mixed> */
    private array $conn = [];

    /** @var list<resource> */
    private array $procs = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_mysql') || ! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('pdo_mysql, pcntl and posix are required.');
        }

        $base = config('database.connections.mysql');
        try {
            $pdo = new \PDO(
                sprintf('mysql:host=%s;port=%s', $base['host'], $base['port'] ?? 3306),
                (string) $base['username'],
                (string) $base['password'],
                [\PDO::ATTR_TIMEOUT => 3, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped('No MySQL server reachable: '.$e->getMessage());
        }

        $this->scratch = 'lease_test_'.getmypid().'_'.bin2hex(random_bytes(3));
        $pdo->exec("CREATE DATABASE `{$this->scratch}` CHARACTER SET utf8mb4");
        $this->conn = array_merge($base, ['database' => $this->scratch]);

        Config::set('database.connections.lease_mysql', $this->conn);
        Config::set('database.default', 'lease_mysql');
        DB::purge('lease_mysql');

        try {
            (require base_path('database/migrations/2026_10_31_020000_create_scheduler_leases_table.php'))->up();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Laravel cannot use the MySQL connection here: '.$e->getMessage());
        }
    }

    public function test_migration_creates_an_innodb_table_that_works_under_strict_sql_mode_and_is_repeatable(): void
    {
        $migration = require base_path('database/migrations/2026_10_31_020000_create_scheduler_leases_table.php');

        $status = DB::selectOne("SHOW TABLE STATUS WHERE Name = 'scheduler_leases'");
        $this->assertSame('InnoDB', $status->Engine, 'row-level atomic UPDATE needs InnoDB');

        $unique = collect(DB::select("SHOW INDEX FROM scheduler_leases WHERE Column_name = 'name'"))->first();
        $this->assertSame(0, (int) $unique->Non_unique, 'name must be unique (one lease row per job)');

        $migration->up(); // running migrate again is harmless
        $this->assertSame(1, (int) DB::selectOne("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'scheduler_leases'")->c);

        // Strictest common session modes (MySQL 5.7 / 8 defaults): nullable timestamps and the atomic UPDATE still work.
        DB::statement("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $lease = SchedulerLease::acquire('strict-mode-job', 60, 5);
        $this->assertNotNull($lease);
        $this->assertTrue($lease->beat(true));
        $lease->release('success');
        $this->assertNull(DB::table('scheduler_leases')->where('name', 'strict-mode-job')->value('owner_token'));

        $migration->down();
        $this->assertSame(0, (int) DB::selectOne("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'scheduler_leases'")->c);
    }

    protected function tearDown(): void
    {
        foreach ($this->procs as $p) {
            @proc_terminate($p, 9);
            @proc_close($p);
        }
        if ($this->scratch !== '') {
            try {
                DB::disconnect('lease_mysql');
                $base = config('database.connections.mysql');
                $pdo = new \PDO(sprintf('mysql:host=%s;port=%s', $base['host'], $base['port'] ?? 3306), (string) $base['username'], (string) $base['password']);
                $pdo->exec("DROP DATABASE IF EXISTS `{$this->scratch}`");
            } catch (\Throwable) {
            }
        }
        parent::tearDown();
    }

    /** Start a separate PHP process running $body against the scratch database. Returns [proc, stdout]. */
    private function spawn(string $body): array
    {
        $script = tempnam(sys_get_temp_dir(), 'lease_mysql_').'.php';
        file_put_contents($script, '<?php
            require '.var_export(base_path('vendor/autoload.php'), true).';
            $app = require '.var_export(base_path('bootstrap/app.php'), true).';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            config(["database.default" => "lease_mysql", "database.connections.lease_mysql" => '.var_export($this->conn, true).']);
            $barrier = (float) ($argv[1] ?? 0);
            '.$body);

        return $this->start($script, [0.0]);
    }

    private function start(string $script, array $args): array
    {
        $proc = proc_open(array_merge([PHP_BINARY, $script], array_map('strval', $args)), [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        $this->procs[] = $proc;
        stream_set_blocking($pipes[1], false);

        return [$proc, $pipes[1], $script];
    }

    private function row(string $name): ?object
    {
        return DB::table('scheduler_leases')->where('name', $name)->first();
    }

    public function test_parallel_processes_cannot_both_acquire_the_lease(): void
    {
        $barrier = microtime(true) + 6; // generous: bootstrapping Laravel takes a moment in every process
        $children = [];
        for ($i = 0; $i < 8; $i++) {
            $script = tempnam(sys_get_temp_dir(), 'lease_race_').'.php';
            file_put_contents($script, '<?php
                require '.var_export(base_path('vendor/autoload.php'), true).';
                $app = require '.var_export(base_path('bootstrap/app.php'), true).';
                $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
                config(["database.default" => "lease_mysql", "database.connections.lease_mysql" => '.var_export($this->conn, true).']);
                DB::table("scheduler_leases")->count();                    // open the connection before the race
                while (microtime(true) < (float) $argv[1]) { usleep(200); } // everyone fires together
                $lease = App\Support\SchedulerLease::acquire("race-job", 60, 30);
                echo $lease ? "WON" : "LOST";
            ');
            $children[] = $this->start($script, [sprintf('%.6F', $barrier)]);
        }

        $results = [];
        foreach ($children as [$proc, $out, $script]) {
            $text = '';
            $deadline = microtime(true) + 40;
            while (microtime(true) < $deadline) {
                $text .= (string) stream_get_contents($out);
                $st = proc_get_status($proc);
                if (! $st['running']) {
                    $text .= (string) stream_get_contents($out);
                    break;
                }
                usleep(50_000);
            }
            $results[] = trim($text);
            @unlink($script);
        }

        $this->assertSame(1, count(array_filter($results, fn ($r) => $r === 'WON')), 'exactly one winner: '.json_encode($results));
        $this->assertSame(7, count(array_filter($results, fn ($r) => $r === 'LOST')), json_encode($results));
        $this->assertNotNull($this->row('race-job')->owner_token);
    }

    public function test_stale_lease_from_a_crashed_process_is_recovered(): void
    {
        [$proc, $out, $script] = $this->spawn('
            $l = App\Support\SchedulerLease::acquire("crash-job", 5, 1);
            echo $l ? "ACQUIRED" : "REFUSED"; fflush(STDOUT);
            posix_kill(getmypid(), SIGKILL);  // crash: nothing is released
        ');
        $this->waitFor($proc);
        $this->assertSame('ACQUIRED', trim((string) stream_get_contents($out)));
        @unlink($script);

        $this->assertNull(SchedulerLease::acquire('crash-job'), 'still held right after the crash');
        $this->assertNotNull($this->row('crash-job')->owner_token);

        sleep(6);

        $next = SchedulerLease::acquire('crash-job');
        $this->assertNotNull($next, 'the next run recovers by itself');
        $this->assertNotNull($next->recovered);
        $this->assertTrue($next->owns());
        $next->release('success');
        $this->assertNull($this->row('crash-job')->owner_token);
    }

    public function test_background_heartbeat_keeps_the_lease_during_a_call_longer_than_the_ttl(): void
    {
        // ttl 5 s; the "Google Ads call" blocks the whole process for 11 s: far past the ttl.
        [$proc, $out, $script] = $this->spawn('
            $l = App\Support\SchedulerLease::acquire("long-job", 5, 1);
            $started = $l->startBackgroundHeartbeat(120);
            echo $started ? "BEATING " : "NO-BEATER "; fflush(STDOUT);
            sleep(11);                                   // blocked call, no PHP code runs, no inline beat
            echo $l->owns() ? "OWNS" : "LOST"; fflush(STDOUT);
            $l->release("success");
        ');

        sleep(8); // 3 s beyond the 5 s ttl, while the process is still blocked
        $this->assertNull(SchedulerLease::acquire('long-job'), 'a legitimately working run must keep its lease');
        $this->assertGreaterThan(now()->toDateTimeString(), $this->row('long-job')->expires_at);

        $this->waitFor($proc, 30);
        $text = trim((string) stream_get_contents($out));
        @unlink($script);
        $this->assertSame('BEATING OWNS', $text);

        $after = $this->row('long-job');
        $this->assertNull($after->owner_token, 'released at the end');
        $this->assertNotNull(SchedulerLease::acquire('long-job'), 'and free for the next run');
    }

    public function test_background_heartbeat_dies_with_a_killed_run_so_the_lease_still_expires(): void
    {
        [$proc, $out, $script] = $this->spawn('
            $l = App\Support\SchedulerLease::acquire("killed-job", 5, 1);
            $l->startBackgroundHeartbeat(120);
            echo "READY"; fflush(STDOUT);
            sleep(3);
            posix_kill(getmypid(), SIGKILL);   // kill -9 of the run: its heartbeat process must not outlive it
        ');
        $this->waitFor($proc, 20);
        $this->assertSame('READY', trim((string) stream_get_contents($out)));
        @unlink($script);

        $this->assertNull(SchedulerLease::acquire('killed-job'), 'held right after the kill');
        sleep(8); // heartbeat process notices the dead parent within a second; lease then expires within the 5 s ttl

        $next = SchedulerLease::acquire('killed-job');
        $this->assertNotNull($next, 'a killed run cannot keep the lease alive through its heartbeat process');
        $this->assertNotNull($next->recovered);
    }

    public function test_old_owner_cannot_renew_or_release_after_a_takeover_on_mysql(): void
    {
        $old = SchedulerLease::acquire('takeover', 5, 1);
        DB::table('scheduler_leases')->where('name', 'takeover')->update(['expires_at' => now()->subSeconds(5)->toDateTimeString()]);
        $new = SchedulerLease::acquire('takeover');
        $this->assertNotNull($new);

        $this->assertFalse($old->beat(true));
        $old->release('failed');
        $row = $this->row('takeover');
        $this->assertSame($new->token, $row->owner_token);
        $this->assertTrue($new->beat(true));
    }

    private function waitFor($proc, int $seconds = 15): void
    {
        $deadline = microtime(true) + $seconds;
        while (microtime(true) < $deadline && proc_get_status($proc)['running']) {
            usleep(100_000);
        }
    }
}
