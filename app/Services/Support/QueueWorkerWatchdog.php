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
        return config('queue_workers.watchdog_queues', []);
    }

    /**
     * @return array<string, array{timeout: int, max_time: int}>
     */
    public static function allConfiguredQueues(): array
    {
        return self::watchdogQueues();
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
        return self::findWorkerPids($queue) !== [];
    }

    /**
     * Kill stuck workers and start a fresh one when needed.
     */
    public static function ensureRunning(string $queue, ?int $timeout = null, ?int $maxTime = null): bool
    {
        $configured = self::allConfiguredQueues();
        $defaults = $configured[$queue] ?? ['timeout' => 3600, 'max_time' => 3600];
        $timeout = $timeout ?? $defaults['timeout'];
        $maxTime = $maxTime ?? $defaults['max_time'];

        if (self::isRunning($queue)) {
            if (! self::isStale($queue, $timeout, $maxTime)) {
                return false;
            }

            $reason = self::staleReason($queue, $timeout, $maxTime) ?? 'stale';
            $killed = self::terminateWorkers($queue);

            Log::warning('Queue worker watchdog terminated stale worker(s)', [
                'queue' => $queue,
                'reason' => $reason,
                'killed' => $killed,
            ]);

            usleep(500000);
        }

        if (self::isRunning($queue)) {
            return false;
        }

        return self::spawnWorker($queue, $timeout, $maxTime);
    }

    public static function isStale(string $queue, int $timeout, int $maxTime): bool
    {
        if (! self::isRunning($queue)) {
            return false;
        }

        return self::staleReason($queue, $timeout, $maxTime) !== null;
    }

    public static function terminateWorkers(string $queue): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 0;
        }

        $killed = 0;

        foreach (self::findWorkerPids($queue) as $pid) {
            exec(sprintf('kill -9 %d 2>/dev/null', $pid), $output, $exitCode);

            if ($exitCode === 0) {
                $killed++;
            }
        }

        return $killed;
    }

    /**
     * @return list<int>
     */
    public static function findWorkerPids(string $queue): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return self::isRunningOnWindows($queue) ? [0] : [];
        }

        $pattern = sprintf('artisan queue:work.*--queue=%s', preg_quote($queue, '/'));
        $command = sprintf('pgrep -f %s', escapeshellarg($pattern));

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $output), fn (int $pid) => $pid > 0));
    }

    private static function spawnWorker(string $queue, int $timeout, int $maxTime): bool
    {
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

    private static function staleReason(string $queue, int $timeout, int $maxTime): ?string
    {
        $processGrace = (int) config('queue_workers.stale_process_grace_seconds', 300);
        $logGrace = (int) config('queue_workers.stale_log_grace_seconds', 600);
        $logStaleAfter = max($timeout + $logGrace, 900);

        if ($maxTime > 0) {
            $logStaleAfter = min($logStaleAfter, $maxTime);
        }

        foreach (self::findWorkerPids($queue) as $pid) {
            $age = self::getProcessAgeSeconds($pid);

            if ($age !== null && $maxTime > 0 && $age > ($maxTime + $processGrace)) {
                return "process_age_exceeded:{$age}s";
            }
        }

        $logFile = storage_path('logs/' . $queue . '-worker.log');

        if (! is_file($logFile)) {
            return null;
        }

        $logAge = time() - (int) filemtime($logFile);

        if ($logAge > $logStaleAfter) {
            return "log_stale:{$logAge}s";
        }

        $tail = self::readWorkerLogTail($logFile);

        if ($tail !== null && str_contains($tail, 'RUNNING') && $logAge > ($timeout + $logGrace)) {
            return "job_running_log_stale:{$logAge}s";
        }

        return null;
    }

    private static function getProcessAgeSeconds(int $pid): ?int
    {
        if ($pid <= 0 || PHP_OS_FAMILY === 'Windows') {
            return null;
        }

        exec(sprintf('ps -o etimes= -p %d 2>/dev/null', $pid), $output, $exitCode);

        if ($exitCode !== 0 || ! isset($output[0])) {
            return null;
        }

        $age = trim((string) $output[0]);

        return is_numeric($age) ? (int) $age : null;
    }

    private static function readWorkerLogTail(string $logFile): ?string
    {
        $handle = @fopen($logFile, 'rb');

        if ($handle === false) {
            return null;
        }

        $size = filesize($logFile);

        if ($size === false || $size === 0) {
            fclose($handle);

            return null;
        }

        $readSize = (int) min(4096, $size);
        fseek($handle, -$readSize, SEEK_END);
        $tail = fread($handle, $readSize);
        fclose($handle);

        return is_string($tail) ? $tail : null;
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
