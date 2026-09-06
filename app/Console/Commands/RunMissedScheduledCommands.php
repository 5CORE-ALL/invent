<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Re-runs Kernel artisan jobs that were due today (event TZ) but have no
 * success/running row after the last due slot. Not wrapped in $ist() — this
 * is how 01:20 / 08:55 / 20:05 slots and missed schedule:run minutes recover.
 */
class RunMissedScheduledCommands extends Command
{
    protected $signature = 'cron:run-missed
        {--dry-run : List missed jobs without running them}
        {--limit=0 : Max jobs to run (0 = no limit)}';

    protected $description = 'Run Kernel scheduled artisan jobs that were due today but never succeeded';

    public function handle(Schedule $schedule): int
    {
        $dry = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $skip = array_map('strtolower', config('cron-monitor.miss_catchup.skip_commands', []));
        $seen = [];
        $ran = 0;
        $missed = 0;
        $ok = 0;
        $skipped = 0;

        $this->info('cron:run-missed '.now('Asia/Kolkata')->toDateTimeString().' IST'.($dry ? ' (dry-run)' : ''));

        foreach ($schedule->events() as $event) {
            if (! $event instanceof Event || $event instanceof CallbackEvent) {
                continue;
            }

            $full = $this->artisanCommand($event);
            if ($full === null) {
                continue;
            }

            $base = strtolower(explode(' ', $full)[0]);
            if ($this->shouldSkip($base, $skip)) {
                $skipped++;
                continue;
            }

            if ($this->isHighFrequency((string) ($event->expression ?? ''))) {
                $skipped++;
                continue;
            }

            $dedupe = $base;
            if (isset($seen[$dedupe])) {
                continue;
            }

            $dueAt = $this->lastDueAt($event);
            if (! $dueAt) {
                continue;
            }

            $seen[$dedupe] = true;

            if ($this->alreadySucceeded($base, $full, $dueAt)) {
                $ok++;
                $this->line("OK    {$full}  (last due {$dueAt->toDateTimeString()})");
                continue;
            }

            $missed++;
            $this->warn("MISS  {$full}  (due {$dueAt->toDateTimeString()})");

            if ($dry) {
                continue;
            }

            if ($limit > 0 && $ran >= $limit) {
                $this->comment("limit {$limit} reached, stopping.");
                break;
            }

            $ec = $this->runArtisan($full);
            $ran++;
            $this->line("      exit={$ec}");
        }

        $this->newLine();
        $this->info("due-and-ok={$ok} missed={$missed} ran={$ran} skipped_hf={$skipped}");

        return self::SUCCESS;
    }

    protected function artisanCommand(Event $event): ?string
    {
        $raw = $event->command ?? '';
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        if (! preg_match('/\bartisan\b(?:\'|")?\s+(.+)$/s', $raw, $m)) {
            return null;
        }

        $rest = str_replace(['\'', '"'], '', $m[1]);
        $rest = trim(preg_replace('/\s+/', ' ', $rest) ?? $rest);

        return $rest !== '' ? $rest : null;
    }

    /**
     * @param  list<string>  $skip
     */
    protected function shouldSkip(string $base, array $skip): bool
    {
        foreach ($skip as $pattern) {
            if ($pattern !== '' && Str::is($pattern, $base)) {
                return true;
            }
        }

        return false;
    }

    protected function isHighFrequency(string $expression): bool
    {
        $parts = preg_split('/\s+/', trim($expression)) ?: [];
        $min = $parts[0] ?? '*';

        if ($min === '*' || $min === '*/1') {
            return true;
        }

        if (str_starts_with($min, '*/')) {
            $n = (int) substr($min, 2);

            return $n > 0 && $n <= 15;
        }

        return str_contains($min, ',');
    }

    protected function lastDueAt(Event $event): ?Carbon
    {
        $tz = $event->timezone ?: config('app.timezone', 'UTC');
        $expression = (string) ($event->expression ?? '');
        if ($expression === '') {
            return null;
        }

        try {
            $cron = new CronExpression($expression);
        } catch (Throwable) {
            return null;
        }

        $end = Carbon::now($tz);
        $cursor = $end->copy()->startOfDay()->subSecond();
        $lastPass = null;

        for ($i = 0; $i < 32; $i++) {
            try {
                $next = Carbon::instance($cron->getNextRunDate($cursor, 0, true, $tz))->timezone($tz);
            } catch (Throwable) {
                break;
            }

            if ($next->gt($end)) {
                break;
            }

            $prevNow = Carbon::hasTestNow() ? Carbon::now() : null;
            Carbon::setTestNow($next->copy());
            try {
                if ($event->filtersPass($this->laravel)) {
                    $lastPass = $next->copy();
                }
            } catch (Throwable) {
                // Ignore filter errors; still consider the cron slot.
                $lastPass = $next->copy();
            } finally {
                $prevNow ? Carbon::setTestNow($prevNow) : Carbon::setTestNow();
            }

            $cursor = $next->copy()->addMinute();
        }

        return $lastPass;
    }

    protected function alreadySucceeded(string $base, string $full, Carbon $dueAt): bool
    {
        try {
            if (! Schema::hasTable('cron_execution_logs')) {
                return false;
            }

            return DB::table('cron_execution_logs')
                ->where(function ($q) use ($base, $full) {
                    $q->where('command', $full)
                        ->orWhere('command', $base)
                        ->orWhere('command', 'like', $base.' %')
                        ->orWhere('job_name', $base);
                })
                ->where('started_at', '>=', $dueAt->copy()->timezone(config('app.timezone')))
                ->whereIn('status', ['success', 'recovered', 'partial_success', 'running'])
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    protected function runArtisan(string $full): int
    {
        $php = PHP_BINARY ?: 'php';
        $ec = 0;
        passthru($php.' '.escapeshellarg(base_path('artisan')).' '.$full, $ec);

        return (int) $ec;
    }
}
