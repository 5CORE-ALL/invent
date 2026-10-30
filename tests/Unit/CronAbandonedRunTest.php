<?php

namespace Tests\Unit;

use App\Models\CronExecutionLog;
use App\Models\CronMonitorAlert;
use App\Services\CronMonitor\CronWatchdogService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** A `running` log must never stay Running after its process is gone, and never be closed while the process lives. */
class CronAbandonedRunTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'cron_reap');
        Config::set('database.connections.cron_reap', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
        DB::purge('cron_reap');

        Schema::create('cron_execution_logs', function ($t) {
            $t->id();
            $t->string('job_name');
            $t->string('command')->nullable();
            $t->string('status', 32)->default('running');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->text('error_message')->nullable();
            $t->text('root_cause')->nullable();
            $t->unsignedInteger('pid')->nullable();
            $t->string('execution_server')->nullable();
            $t->text('meta')->nullable();
            $t->timestamps();
        });
        Schema::create('cron_monitor_alerts', function ($t) {
            $t->id();
            $t->unsignedBigInteger('execution_log_id')->nullable();
            $t->string('job_name')->nullable();
            $t->string('alert_type', 64);
            $t->string('severity', 16);
            $t->string('title');
            $t->text('message')->nullable();
            $t->text('payload')->nullable();
            $t->boolean('notified')->default(false);
            $t->timestamp('notified_at')->nullable();
            $t->timestamps();
        });
    }

    private function runningLog(int $pid, ?string $host = null, int $ageSeconds = 600): CronExecutionLog
    {
        return CronExecutionLog::create([
            'job_name' => 'google-shopping-match-live-bid-bgt',
            'command' => 'google-shopping:match-live-bid-bgt',
            'status' => CronExecutionLog::STATUS_RUNNING,
            'started_at' => now()->subSeconds($ageSeconds),
            'pid' => $pid,
            'execution_server' => $host ?? (gethostname() ?: php_uname('n')),
        ]);
    }

    private function reap(): array
    {
        $svc = $this->app->make(CronWatchdogService::class);
        $m = new \ReflectionMethod($svc, 'reapAbandonedRunning');
        $m->setAccessible(true);

        return $m->invoke($svc);
    }

    private function deadPid(): int
    {
        // A pid that is certainly not running: spawn a short-lived process and wait for it to exit.
        $p = proc_open([PHP_BINARY, '-r', 'echo getmypid();'], [1 => ['pipe', 'w']], $pipes);
        $pid = (int) stream_get_contents($pipes[1]);
        proc_close($p);

        return $pid;
    }

    public function test_run_whose_process_is_gone_is_closed_as_interrupted(): void
    {
        if (! is_dir('/proc') && ! function_exists('posix_kill')) {
            $this->markTestSkipped('needs /proc or posix');
        }
        $log = $this->runningLog($this->deadPid());

        $alerts = $this->reap();

        $log->refresh();
        $this->assertSame(CronExecutionLog::STATUS_INTERRUPTED, $log->status);
        $this->assertNotNull($log->finished_at);
        $this->assertCount(1, $alerts);
        $this->assertSame('interrupted', CronMonitorAlert::first()->alert_type);
    }

    public function test_run_whose_process_is_still_alive_is_never_touched(): void
    {
        $log = $this->runningLog(getmypid());   // this very PHP process is alive and running

        $this->assertSame([], $this->reap());
        $this->assertSame(CronExecutionLog::STATUS_RUNNING, $log->refresh()->status);
    }

    public function test_fresh_runs_and_other_hosts_are_not_judged(): void
    {
        $fresh = $this->runningLog($this->deadPid(), null, 10);          // just started: inside the grace window
        $other = $this->runningLog($this->deadPid(), 'some-other-host'); // pid means nothing on this host

        $this->assertSame([], $this->reap());
        $this->assertSame(CronExecutionLog::STATUS_RUNNING, $fresh->refresh()->status);
        $this->assertSame(CronExecutionLog::STATUS_RUNNING, $other->refresh()->status);
    }

    public function test_a_run_that_finished_meanwhile_is_not_overwritten(): void
    {
        $log = $this->runningLog($this->deadPid());
        $log->update(['status' => CronExecutionLog::STATUS_SUCCESS]);

        $this->assertSame([], $this->reap());
        $this->assertSame(CronExecutionLog::STATUS_SUCCESS, $log->refresh()->status);
    }
}
