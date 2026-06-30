<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class QueueWorkerWatchdog
{
    /**
     * @return array<string, array{timeout: int, max_time: int}>
     */
    public static function watchdogQueues(): array
    {
        return config('queue_workers.watchdog_queues', [
            'google-maps-extractor' => ['timeout' => 3700, 'max_time' => 7200],
        ]);
    }

    /**
     * @return array<string, array{timeout: int, max_time: int}>
     */
    public static function allConfiguredQueues(): array
    {
        return array_merge(
            self::watchdogQueues(),
            config('queue_workers.optional_dedicated_queues', [])
        );
    }

    public static function isWatchdogDaemonRunning(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return self::isWatchdogDaemonRunningOnWindows();
        }

        $pattern = 'artisan queue:watchdog';
        $command = sprintf('pgrep -f %s', escapeshellarg($pattern));
        exec($command, $output, $exitCode);

        return $exitCode === 0;
    }

    public static function ensureWatchdogDaemonRunning(): bool
    {
        if (self::isWatchdogDaemonRunning()) {
            return false;
        }

        $logFile = storage_path('logs/queue-watchdog-daemon.log');
        $logDir = dirname($logFile);
        if (! is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $process = new Process([
                    PHP_BINARY,
                    base_path('artisan'),
                    'queue:watchdog',
                ], base_path(), null, null, null);

                $process->setOptions(['create_new_console' => true]);
                $process->start();

                sleep(1);

                return self::isWatchdogDaemonRunning();
            }

            $command = sprintf(
                'nohup %s %s queue:watchdog >>%s 2>&1 & echo $!',
                escapeshellarg(PHP_BINARY),
                escapeshellarg(base_path('artisan')),
                escapeshellarg($logFile)
            );

            exec($command, $output, $exitCode);

            $started = $exitCode === 0 && self::isWatchdogDaemonRunning();

            Log::info('Queue watchdog daemon spawn attempt', [
                'started' => $started,
                'pid' => $output[0] ?? null,
            ]);

            return $started;
        } catch (\Throwable $exception) {
            Log::warning('Queue watchdog daemon failed to start', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public static function isRunning(string $queue): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return self::isRunningOnWindows($queue);
        }

        $pattern = sprintf('artisan queue:work.*--queue=%s', preg_quote($queue, '/'));
        $command = sprintf('pgrep -f %s', escapeshellarg($pattern));

        exec($command, $output, $exitCode);

        return $exitCode === 0;
    }

    public static function ensureRunning(string $queue, ?int $timeout = null, ?int $maxTime = null): bool
    {
        if (self::isRunning($queue)) {
            return false;
        }

        $configured = self::allConfiguredQueues();
        $defaults = $configured[$queue] ?? ['timeout' => 3600, 'max_time' => 3600];
        $timeout = $timeout ?? $defaults['timeout'];
        $maxTime = $maxTime ?? $defaults['max_time'];

        $logFile = storage_path('logs/' . $queue . '-worker.log');
        $logDir = dirname($logFile);
        if (! is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $connection = (string) config('queue.default', 'database');

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                return self::startWorkerOnWindows($queue, $connection, $timeout, $maxTime);
            }

            // Explicit --queue only: never start a generic queue:work that could drain other jobs.
            $command = sprintf(
                'nohup %s %s queue:work %s --queue=%s --sleep=3 --tries=1 --timeout=%d --max-time=%d >>%s 2>&1 & echo $!',
                escapeshellarg(PHP_BINARY),
                escapeshellarg(base_path('artisan')),
                escapeshellarg($connection),
                escapeshellarg($queue),
                $timeout,
                $maxTime,
                escapeshellarg($logFile)
            );

            exec($command, $output, $exitCode);

            $started = $exitCode === 0 && self::isRunning($queue);

            Log::info('Queue worker watchdog spawn attempt', [
                'queue' => $queue,
                'started' => $started,
                'pid' => $output[0] ?? null,
            ]);

            return $started;
        } catch (\Throwable $exception) {
            Log::warning('Queue worker watchdog failed to spawn worker', [
                'queue' => $queue,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private static function isWatchdogDaemonRunningOnWindows(): bool
    {
        $command = 'wmic process where "name=\'php.exe\'" get CommandLine /FORMAT:LIST 2>nul';
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            return false;
        }

        return str_contains(implode("\n", $output), 'queue:watchdog');
    }

    private static function isRunningOnWindows(string $queue): bool
    {
        $needle = 'queue:work';
        $queueNeedle = '--queue=' . $queue;
        $command = 'wmic process where "name=\'php.exe\'" get CommandLine /FORMAT:LIST 2>nul';

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            return false;
        }

        $blob = implode("\n", $output);

        return str_contains($blob, $needle) && str_contains($blob, $queueNeedle);
    }

    private static function startWorkerOnWindows(string $queue, string $connection, int $timeout, int $maxTime): bool
    {
        $process = new Process([
            PHP_BINARY,
            base_path('artisan'),
            'queue:work',
            $connection,
            '--queue=' . $queue,
            '--sleep=3',
            '--tries=1',
            '--timeout=' . $timeout,
            '--max-time=' . $maxTime,
        ], base_path(), null, null, null);

        $process->setOptions(['create_new_console' => true]);
        $process->start();

        sleep(1);

        return self::isRunningOnWindows($queue);
    }
}
