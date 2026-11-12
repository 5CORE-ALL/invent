<?php

namespace App\Console\Commands;

use App\Support\Marketplace\YesterdaySettleLedger as Ledger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Makes the "Yesterday" badge on /all-marketplace-master complete and stable.
 *
 * Yesterday = the Pacific calendar day. Each marketplace's order table must be re-pulled after that
 * day closes, and again later for late / pending orders. This command is scheduled often (every 10
 * minutes). Each run looks at the ledger for Pacific yesterday and runs every pull that is due but
 * not yet done — so a skipped cron minute, an overloaded scheduler, a server restart, or the
 * winter/summer clock change can no longer lose a pull: the next tick simply runs it.
 */
class SettleYesterdayOrders extends Command
{
    protected $signature = 'orders:settle-yesterday
        {--date= : Pacific date (Y-m-d). Defaults to Pacific yesterday}
        {--status : Only print the settle status}
        {--mark-done : Mark every due pull as done without running it (use after a manual run)}
        {--force : Ignore the ledger and run every task for the pass(es) due now}
        {--only= : Comma-separated task keys to run (see --status / Ledger::tasks())}';

    protected $description = 'Re-pull every marketplace\'s orders for Pacific yesterday until it is settled (catch-up safe)';

    public function handle(): int
    {
        $now = Carbon::now(Ledger::TZ);
        $date = (string) ($this->option('date') ?: Ledger::pacificYesterday($now));
        $hi = Ledger::highestDuePass($date, $now);

        if ($this->option('status')) {
            $this->line(json_encode(Ledger::status($date, $now), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if ($hi < 0) {
            $this->info("Pacific {$date}: not due yet (first pass 40 min after Pacific midnight).");

            return self::SUCCESS;
        }

        $tasks = Ledger::tasks();
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        if ($only !== []) {
            $tasks = array_intersect_key($tasks, array_flip($only));
        }

        $ledger = Ledger::read($date);

        if ($this->option('mark-done')) {
            $stamp = $now->toIso8601String();
            foreach ($tasks as $key => $task) {
                foreach ($task['passes'] as $p) {
                    if ($p <= $hi) {
                        $ledger['tasks'][$key]['done'][(string) $p] = $stamp;
                    }
                }
            }
            Ledger::write($date, $ledger);
            $this->info("Marked passes up to #{$hi} done for {$date}.");

            return self::SUCCESS;
        }

        $ran = 0;
        $failed = 0;

        foreach ($tasks as $key => $task) {
            $pending = $this->option('force')
                ? array_values(array_filter($task['passes'], fn ($p) => $p <= $hi))
                : Ledger::pendingPasses($ledger, $key, $task, $hi);

            if ($pending === []) {
                continue;
            }

            $this->info("[{$date}] {$key}: ".$task['command'].' (passes '.implode(',', $pending).')');
            [$ok, $error] = $this->runPull($task['command'], (int) $task['timeout']);
            $ran++;

            $state = $ledger['tasks'][$key] ?? ['done' => [], 'attempts' => []];
            if ($ok) {
                // One run satisfies every pass that was due: they pull the same rolling window.
                foreach ($pending as $p) {
                    $state['done'][(string) $p] = Carbon::now(Ledger::TZ)->toIso8601String();
                }
                unset($state['error']);
            } else {
                $failed++;
                foreach ($pending as $p) {
                    $state['attempts'][(string) $p] = (int) ($state['attempts'][(string) $p] ?? 0) + 1;
                }
                $state['error'] = $error;
                $this->warn("  failed: {$error}");
            }
            $ledger['tasks'][$key] = $state;
            // Persist after every task so a crash mid-run keeps the progress.
            Ledger::write($date, $ledger);
        }

        $status = Ledger::status($date, $now);
        $this->info(sprintf(
            '[%s] ran %d task(s), %d failed. Settled: %s (passes due %d/%d)%s',
            $date,
            $ran,
            $failed,
            $status['settled'] ? 'yes' : 'no',
            $status['passes_due'],
            $status['passes_total'],
            $status['failed'] ? ' — gave up on: '.implode(', ', $status['failed']) : ''
        ));

        return self::SUCCESS;
    }

    /**
     * Each pull runs in its own PHP process so one crash / exit() / memory spike cannot stop the rest.
     *
     * @return array{0: bool, 1: string}
     */
    private function runPull(string $command, int $timeout): array
    {
        $process = Process::fromShellCommandline(
            escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('artisan')).' '.$command,
            base_path(),
            null,
            null,
            max(60, $timeout)
        );

        try {
            $process->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });
        } catch (\Throwable $e) {
            return [false, substr($e->getMessage(), 0, 300)];
        }

        if ($process->isSuccessful()) {
            return [true, ''];
        }

        return [false, 'exit '.$process->getExitCode()];
    }
}
