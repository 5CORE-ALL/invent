<?php

namespace Tests\Unit;

use App\Console\Commands\GoogleShoppingMatchLiveBidBgtCommand;
use App\Listeners\CronMonitor\AutoMonitorScheduledCommand;
use App\Models\CronExecutionLog;
use App\Services\CronMonitor\CronMonitorService;
use App\Services\CronMonitor\CronWatchdogService;
use App\Services\GoogleShoppingLiveMatchService as LiveMatch;
use App\Support\SchedulerLease;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

/**
 * How a Google Shopping live match run ends: success / partial_success / failed / skipped (and interrupted for a
 * crashed process), from the service result, through the command's exit code, to the Cron Monitor log.
 */
class GoogleShoppingLiveMatchStatusTest extends TestCase
{
    private const CMD = 'google-shopping:match-live-bid-bgt';

    /** @var array<string, array<string, mixed>> */
    private array $grid = [];

    /** @var array<string, array<string, mixed>> */
    private array $live = [];

    /** @var list<string> field:id the push call rejects */
    private array $reject = [];

    /** @var list<string> field:id another push holds */
    private array $busy = [];

    /** @var list<string> field:id that every push call is asked about, in order */
    private array $asked = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('cache.default', 'array');
        Cache::flush();
        Config::set('database.default', 'status_test');
        Config::set('database.connections.status_test', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
        DB::purge('status_test');
        (require base_path('database/migrations/2026_10_31_020000_create_scheduler_leases_table.php'))->up();
    }

    private function campaigns(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            $id = (string) (2000 + $i);
            $this->grid[$id] = ['sbid' => 0.50, 'sbgt' => 10, 'status' => 'ENABLED'];
            $this->live[$id] = ['campaign_id' => $id, 'live_bid' => 0.20, 'live_bgt' => 4];
        }
    }

    private function service(): LiveMatch
    {
        $push = function (string $field) {
            return function (array $ids) use ($field): array {
                $lines = [];
                $errors = 0;
                foreach ($ids as $id) {
                    $key = $field.':'.$id;
                    $this->asked[] = $key;
                    if (in_array($key, $this->busy, true)) {
                        $lines[] = "[BUSY] {$id} ({$id}): another push for this campaign is running — skipped, nothing changed.";

                        continue;
                    }
                    if (in_array($key, $this->reject, true)) {
                        $lines[] = "[ERROR] C ({$id}): INVALID_ARGUMENT: value rejected";
                        $errors++;

                        continue;
                    }
                    $this->live[$id][$field === 'bid' ? 'live_bid' : 'live_bgt'] = $field === 'bid' ? $this->grid[$id]['sbid'] : $this->grid[$id]['sbgt'];
                    $lines[] = "[OK] C ({$id}): pushed";
                }

                return ['ok' => $errors === 0, 'message' => $errors ? "{$errors} error(s)" : 'ok', 'output' => implode("\n", $lines)];
            };
        };

        return new LiveMatch([
            'targets' => fn () => $this->grid,
            'pull' => fn () => null,
            'states' => fn (array $ids) => array_intersect_key($this->live, array_flip($ids)),
            'pushBid' => $push('bid'),
            'pushBgt' => $push('bgt'),
            'sleep' => fn () => null,
        ]);
    }

    // ---------- service: the status of one run ----------

    public function test_all_operations_succeed_is_success(): void
    {
        $this->campaigns(4);

        $s = $this->service()->run();

        $this->assertSame('success', $s['status']);
        $this->assertSame(
            ['checked' => 4, 'sbid_mismatch' => 4, 'sbgt_mismatch' => 4, 'pushed' => 8, 'confirmed' => 8, 'held' => 0, 'skipped' => 0, 'busy' => 0, 'failed' => 0],
            $s['summary']
        );
    }

    public function test_some_succeed_and_some_fail_is_partial_success_and_the_rest_are_still_processed(): void
    {
        $this->campaigns(5);
        $this->reject = ['bid:2002', 'bgt:2004'];

        $s = $this->service()->run();

        $this->assertSame('partial_success', $s['status']);
        $this->assertNull($s['error'], 'a campaign failure is not a run-level error');
        $this->assertSame(2, $s['summary']['failed']);
        $this->assertSame(8, $s['summary']['confirmed']);
        $this->assertSame(8, $s['summary']['pushed']);
        $this->assertContains('2005', $s['pushed_ids']['bid'], 'campaigns after a failure are still pushed');
        $this->assertContains('2005', $s['pushed_ids']['bgt']);
        $this->assertSame(['bid:2002', 'bgt:2004'], array_keys($s['failed']));
    }

    public function test_every_operation_failing_is_failed(): void
    {
        $this->campaigns(2);
        $this->reject = ['bid:2001', 'bid:2002', 'bgt:2001', 'bgt:2002'];

        $s = $this->service()->run();

        $this->assertSame('failed', $s['status']);
        $this->assertSame(4, $s['summary']['failed']);
        $this->assertSame(0, $s['summary']['confirmed']);
        $this->assertNull($s['error']);
    }

    public function test_run_level_error_is_failed(): void
    {
        $this->campaigns(2);
        $svc = new LiveMatch([
            'targets' => fn () => $this->grid,
            'pull' => function () {
                throw new \RuntimeException('Google Ads is down');
            },
        ]);

        $s = $svc->run();

        $this->assertSame('failed', $s['status']);
        $this->assertStringContainsString('Google Ads is down', (string) $s['error']);
        $this->assertSame(0, $s['summary']['pushed']);
    }

    public function test_losing_the_lease_mid_run_is_failed(): void
    {
        $this->campaigns(3);
        $old = SchedulerLease::acquire('lost', 5, 1);
        DB::table('scheduler_leases')->update(['owner_token' => 'someone-else']); // another run took over

        $s = $this->service()->run(false, null, false, $old);

        $this->assertSame('failed', $s['status']);
        $this->assertStringContainsString('Lease lost', (string) $s['error']);
        $this->assertSame([], $this->asked, 'nothing is pushed once the lease is gone');
    }

    public function test_held_busy_and_skipped_campaigns_are_counted_but_are_not_failures(): void
    {
        $this->campaigns(4);
        Cache::put('google-shopping:match-failed:bid:2001', 'x', now()->addHours(3)); // held after an earlier failed push
        $this->busy = ['bgt:2002'];                                                    // a manual push holds it
        $this->grid['2003']['sbgt'] = 0.5;                                              // SBGT < 1: never pushed, not a mismatch

        $s = $this->service()->run();

        $this->assertSame('success', $s['status'], 'nothing failed in this run');
        $this->assertSame(1, $s['summary']['held']);
        $this->assertSame(1, $s['summary']['busy']);
        $this->assertSame(0, $s['summary']['failed']);
        $this->assertSame(['bgt:2002' => 'busy: another push for this campaign is running — skipped, nothing changed.'], $s['skipped']);
        $this->assertNotContains('bid:2001', $this->asked, 'a held campaign is not pushed');
    }

    public function test_dry_run_is_success_and_pushes_nothing(): void
    {
        $this->campaigns(3);
        $this->reject = ['bid:2001'];

        $s = $this->service()->run(true);

        $this->assertSame('success', $s['status']);
        $this->assertSame([], $this->asked);
        $this->assertSame(3, $s['summary']['sbid_mismatch']);
    }

    public function test_status_rules_in_one_place(): void
    {
        $base = ['error' => null, 'summary' => ['failed' => 0, 'confirmed' => 0]];
        $this->assertSame('success', LiveMatch::resolveStatus($base));
        $this->assertSame('partial_success', LiveMatch::resolveStatus(['summary' => ['failed' => 1, 'confirmed' => 1]] + $base));
        $this->assertSame('failed', LiveMatch::resolveStatus(['summary' => ['failed' => 1, 'confirmed' => 0]] + $base));
        $this->assertSame('failed', LiveMatch::resolveStatus(['error' => 'x'] + $base));
        $this->assertSame('failed', LiveMatch::resolveStatus(['error' => 'x', 'summary' => ['failed' => 0, 'confirmed' => 9]]));
        $this->assertSame('success', LiveMatch::resolveStatus(['summary' => ['failed' => 3, 'confirmed' => 0]] + $base, true), 'dry-run never fails on pushes');
    }

    // ---------- command: exit code, summary line, lease status ----------

    private function bind(): void
    {
        $this->app->instance(LiveMatch::class, $this->service());
    }

    private function lease(): object
    {
        return DB::table('scheduler_leases')->where('name', GoogleShoppingMatchLiveBidBgtCommand::LEASE)->first();
    }

    public function test_command_success(): void
    {
        Config::set('cron-monitor.enabled', false);
        $this->campaigns(2);
        $this->bind();

        $this->assertSame(0, Artisan::call(self::CMD));

        $out = Artisan::output();
        $this->assertStringContainsString('status=success', $out);
        $this->assertStringContainsString('pushed 4 | confirmed 4 | held 0 | skipped 0 | busy 0 | failed 0', $out);
        $this->assertSame('success', $this->lease()->last_status);
        $this->assertNull($this->lease()->owner_token);
    }

    public function test_command_partial_success_exits_zero_and_reports_every_count(): void
    {
        Config::set('cron-monitor.enabled', false);
        $this->campaigns(3);
        $this->reject = ['bgt:2002'];
        Cache::put('google-shopping:match-failed:bid:2003', 'x', now()->addHours(3));
        $this->busy = ['bid:2001'];
        $this->bind();

        $this->assertSame(0, Artisan::call(self::CMD));

        $out = Artisan::output();
        $this->assertStringContainsString('status=partial_success', $out);
        $this->assertStringContainsString('checked 3 | mismatches SBID 3 SBGT 3 | pushed 3 | confirmed 3 | held 1 | skipped 0 | busy 1 | failed 1', $out);
        $this->assertStringContainsString('Failed bgt:2002: INVALID_ARGUMENT: value rejected', $out);
        $this->assertSame('partial_success', $this->lease()->last_status);
        $this->assertNull($this->lease()->owner_token, 'the lease is released');
    }

    public function test_command_failed_exits_non_zero(): void
    {
        Config::set('cron-monitor.enabled', false);
        $this->campaigns(1);
        $this->reject = ['bid:2001', 'bgt:2001'];
        $this->bind();

        $this->assertSame(1, Artisan::call(self::CMD));

        $this->assertStringContainsString('status=failed', Artisan::output());
        $this->assertSame('failed', $this->lease()->last_status);
    }

    public function test_command_run_level_error_exits_non_zero(): void
    {
        Config::set('cron-monitor.enabled', false);
        $this->campaigns(1);
        $this->app->instance(LiveMatch::class, new LiveMatch(['targets' => fn () => $this->grid, 'pull' => function () {
            throw new \RuntimeException('boom');
        }]));

        $this->assertSame(1, Artisan::call(self::CMD));

        $out = Artisan::output();
        $this->assertStringContainsString('status=failed', $out);
        $this->assertStringContainsString('Live pull failed: boom', $out);
        $this->assertSame('failed', $this->lease()->last_status);
    }

    public function test_command_skipped_when_another_run_holds_the_lock(): void
    {
        Config::set('cron-monitor.enabled', false);
        $this->campaigns(1);
        $this->bind();
        $holder = SchedulerLease::acquire(GoogleShoppingMatchLiveBidBgtCommand::LEASE);

        $this->assertSame(0, Artisan::call(self::CMD));

        $this->assertSame([], $this->asked, 'no work was done');
        $this->assertSame($holder->token, $this->lease()->owner_token, 'the running execution keeps its lease');
        $this->assertNull($this->lease()->last_status, 'and its state is not overwritten');
    }

    // ---------- Cron Monitor: what the dashboard reads ----------

    private function cronTables(): bool
    {
        try {
            foreach (['2026_07_18_020000_create_cron_monitoring_tables.php', '2026_07_18_220000_upgrade_cron_monitor_self_healing.php'] as $file) {
                (require base_path('database/migrations/'.$file))->up();
            }
        } catch (\Throwable $e) {
            return false;
        }

        return Schema::hasTable('cron_execution_logs');
    }

    /** Runs the command like the scheduler does: monitor starts, command runs, the listener finishes the log. */
    private function scheduledRun(): CronExecutionLog
    {
        Event::fake([\App\Events\CronMonitor\CronExecutionStarted::class, \App\Events\CronMonitor\CronExecutionFinished::class]);
        Config::set('cron-monitor.enabled', true);
        Config::set('cron-monitor.auto_monitor.enabled', true);

        $monitor = $this->app->make(CronMonitorService::class);
        $ctx = $monitor->start('google-shopping-match-live-bid-bgt', self::CMD);
        $ctx->mergeMeta(['auto' => true, 'mode' => 'schedule']);

        $exit = Artisan::call(self::CMD);

        $this->app->make(AutoMonitorScheduledCommand::class)->handleFinished(
            new CommandFinished(self::CMD, new ArrayInput([]), new NullOutput, $exit)
        );

        return CronExecutionLog::query()->latest('id')->firstOrFail();
    }

    public function test_cron_monitor_records_each_status(): void
    {
        if (! $this->cronTables()) {
            $this->markTestSkipped('The cron monitor tables cannot be built on sqlite here.');
        }
        $this->campaigns(3);
        $this->bind();

        // success
        $log = $this->scheduledRun();
        $this->assertSame(CronExecutionLog::STATUS_SUCCESS, $log->status);
        $this->assertSame(6, (int) $log->updated_records);
        $this->assertSame(0, (int) $log->failed_records);
        $this->assertSame(6, $log->meta['summary']['confirmed']);

        // partial_success: a campaign fails, the rest are pushed
        foreach ($this->grid as $id => $_) {
            $this->live[$id]['live_bid'] = 0.2;
            $this->live[$id]['live_bgt'] = 4;
        }
        $this->reject = ['bid:2002'];
        $log = $this->scheduledRun();
        $this->assertSame(CronExecutionLog::STATUS_PARTIAL_SUCCESS, $log->status);
        $this->assertSame(1, (int) $log->failed_records);
        $this->assertSame(5, (int) $log->updated_records);
        $this->assertEqualsWithDelta(83.33, (float) $log->success_percentage, 0.01);
        $this->assertSame(1, $log->meta['summary']['failed']);
        $this->assertSame('warning', $log->statusColor());

        // failed: every operation fails (the command exits 1)
        $this->reject = ['bid:2001', 'bid:2002', 'bid:2003', 'bgt:2001', 'bgt:2002', 'bgt:2003'];
        foreach ($this->grid as $id => $_) {
            $this->live[$id]['live_bid'] = 0.2;
            $this->live[$id]['live_bgt'] = 4;
        }
        Cache::flush();
        $log = $this->scheduledRun();
        $this->assertSame(CronExecutionLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('did not stick', (string) $log->root_cause);

        // skipped: another execution holds the lock
        $holder = SchedulerLease::acquire(GoogleShoppingMatchLiveBidBgtCommand::LEASE);
        $before = (int) CronExecutionLog::query()->where('status', CronExecutionLog::STATUS_FAILED)->value('consecutive_failures');
        $log = $this->scheduledRun();
        $this->assertSame(CronExecutionLog::STATUS_SKIPPED, $log->status);
        $this->assertTrue($log->meta['skipped']);
        $this->assertSame('lease held by a live run', $log->meta['skipped_reason']);
        $this->assertSame($before, (int) $log->consecutive_failures, 'a skipped run neither extends nor resets the failure streak');
        $this->assertSame('secondary', $log->statusColor());
        $holder->release('success');
    }

    public function test_interrupted_is_still_what_the_watchdog_records_for_a_dead_process(): void
    {
        if (! $this->cronTables()) {
            $this->markTestSkipped('The cron monitor tables cannot be built on sqlite here.');
        }
        $p = proc_open([PHP_BINARY, '-r', 'echo getmypid();'], [1 => ['pipe', 'w']], $pipes);
        $dead = (int) stream_get_contents($pipes[1]);
        proc_close($p);
        $log = CronExecutionLog::create([
            'job_name' => 'google-shopping-match-live-bid-bgt', 'command' => self::CMD,
            'status' => CronExecutionLog::STATUS_RUNNING, 'started_at' => now()->subMinutes(10),
            'pid' => $dead, 'execution_server' => gethostname() ?: php_uname('n'),
        ]);

        $svc = $this->app->make(CronWatchdogService::class);
        $m = new \ReflectionMethod($svc, 'reapAbandonedRunning');
        $m->setAccessible(true);
        $m->invoke($svc);

        $this->assertSame(CronExecutionLog::STATUS_INTERRUPTED, $log->fresh()->status);
    }

    public function test_every_status_is_known_to_the_model_and_the_dashboard(): void
    {
        $this->assertSame(1, CronExecutionLog::statusSortRank('failed'));
        $this->assertSame(1, CronExecutionLog::statusSortRank('interrupted'));
        $this->assertSame(2, CronExecutionLog::statusSortRank('partial_success'));
        $this->assertSame(3, CronExecutionLog::statusSortRank('running'));
        $this->assertSame(4, CronExecutionLog::statusSortRank('success'));
        $this->assertSame(4, CronExecutionLog::statusSortRank('skipped'));

        $dashboard = file_get_contents(base_path('resources/views/cron-monitor/dashboard.blade.php'));
        $show = file_get_contents(base_path('resources/views/cron-monitor/show.blade.php'));
        foreach (['failed', 'partial_success', 'interrupted', 'skipped', 'success', 'running'] as $status) {
            $this->assertStringContainsString("'{$status}'", $dashboard, "dashboard knows {$status}");
        }
        $this->assertStringContainsString("'skipped' => 'Skipped'", $dashboard, 'skipped has its own tab');
        $this->assertStringContainsString("cm-pill-skip", $dashboard);
        $this->assertStringContainsString("'skipped' => 'skip'", $show);
    }

    public function test_a_skipped_run_never_raises_an_alert(): void
    {
        $log = new CronExecutionLog(['job_name' => 'j', 'status' => CronExecutionLog::STATUS_SKIPPED, 'anomalies' => [['severity' => 'critical']]]);

        $this->assertFalse($this->app->make(\App\Services\CronMonitor\CronNotificationDispatcher::class)->shouldNotify($log));
    }
}
