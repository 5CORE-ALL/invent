<?php

namespace App\Console\Commands;

use App\Support\Marketplace\Settle\SettleTracker;
use App\Support\Marketplace\YesterdaySettleLedger as Ledger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Makes the "Yesterday" badge on /all-marketplace-master complete and stable — before 09:00 Pacific.
 *
 * Yesterday = the Pacific calendar day. Each marketplace's data must be re-pulled after that day
 * closes, and again later for late / pending orders. This command is scheduled often (every 10
 * minutes, 00:40–09:00 Pacific). Each run looks at the ledger for Pacific yesterday and runs every
 * pull that is due but not yet done, so a skipped cron minute, an overloaded scheduler, a server
 * restart, or the winter/summer clock change cannot lose a pull: the next tick simply runs it.
 *
 * Pulls are grouped into lanes (tasks that share an API run one after another); different lanes run
 * in parallel so the whole set fits before the deadline. No new pull starts after 08:30 PT and
 * anything still running at 09:00 PT is stopped.
 */
class SettleYesterdayOrders extends Command
{
    protected $signature = 'orders:settle-yesterday
        {--date= : Pacific date (Y-m-d). Defaults to Pacific yesterday}
        {--status : Only print the settle status}
        {--mark-done : Mark every due pull as done without running it (use after a manual run)}
        {--force : Run every task for the pass(es) due now, ignoring the ledger and the 09:00 PT deadline}
        {--ignore-deadline : Run past the 08:30 / 09:00 PT cutoffs (for a manual catch-up)}
        {--concurrency=4 : How many lanes (marketplaces) run at once}
        {--only= : Comma-separated task keys to run (see --status / Ledger::tasks())}';

    protected $description = 'Re-pull every marketplace for Pacific yesterday until it is settled, before 09:00 Pacific (catch-up safe)';

    /** @var array<string, mixed> */
    private array $ledger = [];

    private string $date = '';

    private bool $ignoreDeadline = false;

    private bool $force = false;

    /**
     * Tasks that failed during this run. They are not retried until the next scheduled tick (10 min
     * later), so an API outage does not burn all of a task's attempts within a few seconds.
     *
     * @var array<string, true>
     */
    private array $failedThisRun = [];

    public function handle(): int
    {
        $now = Carbon::now(Ledger::TZ);
        $this->date = (string) ($this->option('date') ?: Ledger::pacificYesterday($now));
        $this->force = (bool) $this->option('force');
        $this->ignoreDeadline = $this->force || (bool) $this->option('ignore-deadline');

        if ($this->option('status')) {
            $this->line(json_encode(Ledger::status($this->date, $now), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $hi = Ledger::highestDuePass($this->date, $now);
        if ($hi < 0) {
            $this->info("Pacific {$this->date}: not due yet (first pass 40 min after Pacific midnight).");

            return self::SUCCESS;
        }

        $tasks = Ledger::tasks();
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        if ($only !== []) {
            $tasks = array_intersect_key($tasks, array_flip($only));
        }

        $this->ledger = Ledger::read($this->date);

        if ($this->option('mark-done')) {
            $stamp = $now->toIso8601String();
            foreach ($tasks as $key => $task) {
                foreach ($task['passes'] as $p) {
                    if ($p <= $hi) {
                        $this->ledger['tasks'][$key]['done'][(string) $p] = $stamp;
                    }
                }
            }
            Ledger::write($this->date, $this->ledger);
            $this->info("Marked passes up to #{$hi} done for {$this->date}.");

            return self::SUCCESS;
        }

        if (! $this->ignoreDeadline && Ledger::pastStartCutoff($this->date, $now)) {
            $this->warn("Pacific {$this->date}: past 08:30 PT, not starting new pulls. Use --ignore-deadline for a manual catch-up.");
            $this->summary(0, 0);

            return self::SUCCESS;
        }

        $ran = 0;
        $failed = 0;

        // Re-evaluate between rounds: a later pass can become due while earlier lanes were running.
        for ($round = 0; $round < 6; $round++) {
            [$r, $f] = $this->runRound($tasks, max(1, (int) $this->option('concurrency')));
            $ran += $r;
            $failed += $f;
            if ($r === 0) {
                break;
            }
        }

        $this->summary($ran, $failed);

        return self::SUCCESS;
    }

    /**
     * Run everything currently due, lane by lane (lanes in parallel).
     *
     * @param  array<string, array{command: string, lane: string, passes: list<int>, timeout: int}>  $tasks
     * @return array{0: int, 1: int} [ran, failed]
     */
    private function runRound(array $tasks, int $concurrency): array
    {
        $queues = [];
        foreach ($tasks as $key => $task) {
            if ($this->pendingFor($key, $task) !== []) {
                $queues[$task['lane']][] = $key;
            }
        }
        $final = $queues[Ledger::FINAL_LANE] ?? [];
        unset($queues[Ledger::FINAL_LANE]);

        if ($queues === [] && $final === []) {
            return [0, 0];
        }

        /** @var array<string, array{proc: Process, key: string, passes: list<int>, started: float}> $running */
        $running = [];
        $ran = 0;
        $failed = 0;

        while (true) {
            // 1) Hard stop: nothing may still be running at 09:00 PT.
            if (! $this->ignoreDeadline && Ledger::pastHardStop($this->date)) {
                foreach ($running as $job) {
                    $job['proc']->stop(5);
                    $this->recordResult($job['key'], $job['passes'], false, 'stopped at the 09:00 PT deadline');
                    $this->trackRun($job, false, 'stopped at the 09:00 PT deadline', (int) round(microtime(true) - $job['started']));
                    $this->warn("[{$this->date}] {$job['key']}: stopped at the 09:00 PT deadline");
                    $failed++;
                }

                return [$ran, $failed];
            }

            // 2) Collect finished (or timed-out) jobs.
            foreach ($running as $lane => $job) {
                try {
                    $job['proc']->checkTimeout();
                } catch (ProcessTimedOutException $e) {
                    $this->finishJob($job, false, 'timed out');
                    $ran++;
                    $failed++;
                    unset($running[$lane]);

                    continue;
                }
                if ($job['proc']->isRunning()) {
                    continue;
                }
                $ok = $job['proc']->isSuccessful();
                $this->finishJob($job, $ok, $ok ? '' : 'exit '.$job['proc']->getExitCode());
                $ran++;
                $failed += $ok ? 0 : 1;
                unset($running[$lane]);
            }

            // 3) Start the next task in every idle lane, up to the concurrency limit.
            foreach (array_keys($queues) as $lane) {
                if (isset($running[$lane])) {
                    continue;
                }
                if (count($running) >= $concurrency) {
                    break;
                }
                $job = $this->startNext($queues[$lane], $tasks);
                if ($job === null) {
                    unset($queues[$lane]);

                    continue;
                }
                $running[$lane] = $job;
            }

            // 4) The final recalculation waits until every other lane is completely finished.
            if ($running === [] && $queues === [] && $final !== []) {
                $job = $this->startNext($final, $tasks);
                if ($job !== null) {
                    $running[Ledger::FINAL_LANE] = $job;
                }
            }

            if ($running === [] && $queues === [] && $final === []) {
                break;
            }

            usleep(500000);
        }

        return [$ran, $failed];
    }

    /**
     * Pop and start the next still-pending task of a lane. Pending is re-evaluated now, because a later
     * pass may have become due and another run may have finished the task.
     *
     * @param  list<string>  $queue
     * @param  array<string, array{command: string, lane: string, passes: list<int>, timeout: int}>  $tasks
     * @return array{proc: Process, key: string, passes: list<int>, started: float}|null
     */
    private function startNext(array &$queue, array $tasks): ?array
    {
        while ($queue !== []) {
            if (! $this->ignoreDeadline && Ledger::pastStartCutoff($this->date)) {
                $queue = [];

                return null;
            }

            $key = array_shift($queue);
            $task = $tasks[$key];
            $pending = $this->pendingFor($key, $task);
            if ($pending === []) {
                continue;
            }

            $this->info("[{$this->date}] start {$key}: {$task['command']} (passes ".implode(',', $pending).')');
            $proc = Process::fromShellCommandline(
                escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('artisan')).' '.$task['command'],
                base_path(),
                null,
                null,
                max(60, (int) $task['timeout'])
            );
            $proc->start();

            return ['proc' => $proc, 'key' => $key, 'passes' => $pending, 'started' => microtime(true)];
        }

        return null;
    }

    /**
     * @param  array{command: string, lane: string, passes: list<int>, timeout: int}  $task
     * @return list<int>
     */
    private function pendingFor(string $key, array $task): array
    {
        if (isset($this->failedThisRun[$key])) {
            return [];
        }

        $hi = Ledger::highestDuePass($this->date);
        if ($this->force) {
            return array_values(array_filter($task['passes'], fn ($p) => $p <= $hi));
        }

        return Ledger::pendingPasses($this->ledger, $key, $task, $hi);
    }

    /**
     * @param  array{proc: Process, key: string, passes: list<int>, started: float}  $job
     */
    private function finishJob(array $job, bool $ok, string $error): void
    {
        $seconds = (int) round(microtime(true) - $job['started']);
        $tail = trim(implode("\n", array_slice(preg_split('/\R/', trim($job['proc']->getOutput().$job['proc']->getErrorOutput())) ?: [], -12)));
        if ($tail !== '') {
            $this->line("--- {$job['key']} output (last lines) ---\n{$tail}");
        }
        if ($ok) {
            $this->info("[{$this->date}] done  {$job['key']} in {$seconds}s");
        } else {
            $this->warn("[{$this->date}] FAILED {$job['key']} after {$seconds}s: {$error}");
            if ($job['proc']->isRunning()) {
                $job['proc']->stop(5);
            }
        }
        $this->recordResult($job['key'], $job['passes'], $ok, $error);
        $this->trackRun($job, $ok, $error, $seconds);
    }

    /**
     * Phase 1/2 tracking: keep the last error, command output markers (auth / pagination text that
     * did not change the exit code) and timings in the new settle_task_runs table. Purely
     * observational: any failure here is logged and ignored, the ledger and the run are unaffected.
     *
     * @param  array{proc: Process, key: string, passes: list<int>, started: float}  $job
     */
    private function trackRun(array $job, bool $ok, string $error, int $seconds): void
    {
        try {
            $proc = $job['proc'];
            SettleTracker::recordRun(
                $this->date,
                $job['key'],
                Ledger::tasks()[$job['key']] ?? null,
                $ok,
                $error,
                trim($proc->getOutput()."\n".$proc->getErrorOutput()),
                $seconds,
                $proc->getExitCode(),
                Carbon::createFromTimestamp((int) $job['started'], Ledger::TZ)
            );
        } catch (\Throwable $e) {
            Log::warning('[settle] could not record task run for '.$job['key'].': '.$e->getMessage());
        }
    }

    /**
     * @param  list<int>  $passes
     */
    private function recordResult(string $key, array $passes, bool $ok, string $error): void
    {
        $state = $this->ledger['tasks'][$key] ?? ['done' => [], 'attempts' => []];
        if ($ok) {
            // One run satisfies every pass that was due: they pull the same rolling window.
            foreach ($passes as $p) {
                $state['done'][(string) $p] = Carbon::now(Ledger::TZ)->toIso8601String();
            }
            unset($state['error']);
        } else {
            foreach ($passes as $p) {
                $state['attempts'][(string) $p] = (int) ($state['attempts'][(string) $p] ?? 0) + 1;
            }
            $state['error'] = $error;
            $this->failedThisRun[$key] = true;
        }
        $this->ledger['tasks'][$key] = $state;
        // Persist after every task so a crash mid-run keeps the progress.
        Ledger::write($this->date, $this->ledger);
    }

    private function summary(int $ran, int $failed): void
    {
        $status = Ledger::status($this->date);
        $this->info(sprintf(
            '[%s] ran %d task(s), %d failed. Settled: %s (passes due %d/%d)%s%s',
            $this->date,
            $ran,
            $failed,
            $status['settled'] ? 'yes' : 'no',
            $status['passes_due'],
            $status['passes_total'],
            $status['pending'] ? ' — pending: '.implode(', ', $status['pending']) : '',
            $status['failed'] ? ' — missed/failed: '.implode(', ', $status['failed']) : ''
        ));
    }
}
