<?php

namespace App\Services\CronMonitor;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;

/**
 * Discovers artisan commands registered in Kernel::schedule().
 */
class ScheduledJobRegistry
{
    /** @var array<string, array<string, mixed>>|null */
    protected ?array $jobs = null;

    /**
     * @return array<string, array<string, mixed>> keyed by artisan command name (no args)
     */
    public function jobs(): array
    {
        if ($this->jobs !== null) {
            return $this->jobs;
        }

        $schedule = app(Schedule::class);
        $jobs = [];

        foreach ($schedule->events() as $event) {
            if ($event instanceof CallbackEvent) {
                $name = $event->description ?: $event->mutexName();
                if ($this->shouldSkipName((string) $name)) {
                    continue;
                }
                $jobs['callback:' . $name] = $this->mapEvent($event, (string) $name, null);
                continue;
            }

            $command = $this->extractCommandName($event);
            if (! $command || $this->shouldSkipName($command)) {
                continue;
            }

            // Keep the first / richest schedule entry per command
            if (! isset($jobs[$command])) {
                $jobs[$command] = $this->mapEvent(
                    $event,
                    $event->description ?: $command,
                    $command
                );
            }
        }

        // Manual overrides from config win
        foreach (config('cron-monitor.watched_jobs', []) as $key => $cfg) {
            $command = is_string($key) ? $key : ($cfg['command'] ?? null);
            if (! $command) {
                continue;
            }
            $jobs[$command] = array_merge($jobs[$command] ?? [], $cfg, [
                'command' => $command,
                'job_name' => $cfg['job_name'] ?? ($jobs[$command]['job_name'] ?? $command),
            ]);
        }

        return $this->jobs = $jobs;
    }

    public function isScheduledCommand(string $commandName): bool
    {
        $commandName = $this->normalizeCommand($commandName);

        return isset($this->jobs()[$commandName]);
    }

    public function jobNameFor(string $commandName): string
    {
        $commandName = $this->normalizeCommand($commandName);
        $jobs = $this->jobs();

        return $jobs[$commandName]['job_name'] ?? $commandName;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function watchedJobsForWatchdog(): array
    {
        $out = [];
        foreach ($this->jobs() as $command => $cfg) {
            if (str_starts_with($command, 'callback:')) {
                continue;
            }
            if (! empty($cfg['watch']) || config('cron-monitor.auto_watch_scheduled', true)) {
                $out[$command] = $cfg;
            }
        }

        return $out;
    }

    protected function mapEvent(Event $event, string $jobName, ?string $command): array
    {
        $expression = $event->expression ?? '* * * * *';
        [$schedule, $expectedAt] = $this->inferSchedule($expression);

        return [
            'job_name' => $jobName,
            'command' => $command,
            'schedule' => $schedule,
            'expected_at' => $expectedAt,
            'timezone' => $event->timezone ?: config('app.timezone', 'UTC'),
            'expression' => $expression,
            'timeout_minutes' => $this->defaultTimeout($schedule),
            'grace_minutes' => $this->defaultGrace($schedule),
            'watch' => true,
        ];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected function inferSchedule(string $expression): array
    {
        $expression = trim(preg_replace('/\s+/', ' ', $expression) ?? $expression);
        $parts = explode(' ', $expression);
        if (count($parts) < 5) {
            return ['custom', null];
        }

        [$min, $hour, $dom, $mon, $dow] = $parts;

        if ($expression === '* * * * *') {
            return ['every_minute', null];
        }
        if ($min === '*/5' && $hour === '*' && $dom === '*' && $mon === '*' && $dow === '*') {
            return ['every_five_minutes', null];
        }
        if ($min === '*/10' && $hour === '*' && $dom === '*' && $mon === '*' && $dow === '*') {
            return ['every_ten_minutes', null];
        }
        if (
            in_array($min, ['0,30', '*/30'], true)
            && $hour === '*'
            && $dom === '*'
            && $mon === '*'
            && $dow === '*'
        ) {
            return ['every_thirty_minutes', null];
        }
        if (preg_match('/^\d+$/', $min) && $hour === '*' && $dom === '*' && $mon === '*' && $dow === '*') {
            return ['hourly', sprintf('%02d:%02d', 0, (int) $min)];
        }
        if (
            preg_match('/^\d+$/', $min)
            && preg_match('/^\d+$/', $hour)
            && $dom === '*'
            && $mon === '*'
            && preg_match('/^\d+$/', $dow)
        ) {
            return ['weekly', sprintf('%02d:%02d', (int) $hour, (int) $min)];
        }
        if (
            preg_match('/^\d+$/', $min)
            && preg_match('/^\d+$/', $hour)
            && $dom === '*'
            && $mon === '*'
            && ($dow === '*' || $dow === '?')
        ) {
            return ['daily', sprintf('%02d:%02d', (int) $hour, (int) $min)];
        }

        // Multi-hour daily (e.g. 0 9,18 * * *) — treat as custom with first hour
        if (
            preg_match('/^\d+$/', $min)
            && preg_match('/^[\d,]+$/', $hour)
            && $dom === '*'
            && $mon === '*'
            && ($dow === '*' || $dow === '?')
        ) {
            $firstHour = (int) explode(',', $hour)[0];

            return ['daily', sprintf('%02d:%02d', $firstHour, (int) $min)];
        }

        return ['custom', null];
    }

    protected function defaultTimeout(string $schedule): int
    {
        return match ($schedule) {
            'every_minute' => 5,
            'every_five_minutes' => 15,
            'every_ten_minutes' => 30,
            'every_thirty_minutes' => 40,
            'hourly' => 55,
            default => (int) config('cron-monitor.timeouts.default_minutes', 120),
        };
    }

    protected function defaultGrace(string $schedule): int
    {
        return match ($schedule) {
            'every_minute' => 2,
            'every_five_minutes' => 5,
            'every_ten_minutes' => 8,
            'every_thirty_minutes' => 15,
            'hourly' => 20,
            default => (int) config('cron-monitor.watchdog.grace_minutes', 30),
        };
    }

    protected function extractCommandName(Event $event): ?string
    {
        $command = $event->command ?? null;
        if (! is_string($command) || $command === '') {
            return null;
        }

        // "php artisan foo:bar --opt" or quoted paths
        if (preg_match('/artisan\s+([^\s\'"]+)/', $command, $m)) {
            return $this->normalizeCommand($m[1]);
        }

        // Sometimes stored as just the signature
        if (str_contains($command, ':') && ! str_contains($command, ' ')) {
            return $this->normalizeCommand($command);
        }

        if (preg_match('/([a-z0-9_-]+:[a-z0-9:_-]+)/i', $command, $m)) {
            return $this->normalizeCommand($m[1]);
        }

        return null;
    }

    protected function normalizeCommand(string $command): string
    {
        $command = trim($command);
        // Strip leading php/artisan noise
        $command = preg_replace('/^(php\s+)?artisan\s+/i', '', $command) ?? $command;

        return explode(' ', trim($command))[0];
    }

    protected function shouldSkipName(string $name): bool
    {
        $skip = config('cron-monitor.auto_monitor.skip', []);
        $haystack = strtolower($name);
        // Also match bare name without callback: prefix
        $bare = strtolower(str_replace('callback:', '', $name));

        foreach ($skip as $pattern) {
            $pattern = strtolower((string) $pattern);
            if (
                Str::is($pattern, $haystack)
                || Str::is($pattern, $bare)
                || ($pattern !== '' && ! str_contains($pattern, '*') && (
                    str_contains($haystack, $pattern) || str_contains($bare, $pattern)
                ))
            ) {
                return true;
            }
        }

        return false;
    }
}

