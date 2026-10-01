<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Start an artisan command as a detached background process from a web request.
 *
 * The production queue workers only serve explicitly configured queues, so work that must
 * outlive the HTTP response (long marketplace publishes, snapshot rebuilds) is spawned as its
 * own CLI process instead of being dispatched to the default queue.
 */
class DetachedArtisan
{
    /**
     * @param  array<int|string, scalar|null>  $arguments  positional values or --option => value pairs
     */
    public static function spawn(string $command, array $arguments = []): bool
    {
        if (! self::available()) {
            return false;
        }
        $php = self::phpCliBinary();
        if ($php === null) {
            return false;
        }

        $parts = [escapeshellarg($php), escapeshellarg(base_path('artisan')), escapeshellarg($command)];
        foreach ($arguments as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if (is_int($key)) {
                $parts[] = escapeshellarg((string) $value);
            } elseif ($value === true) {
                $parts[] = escapeshellarg('--'.ltrim((string) $key, '-'));
            } else {
                $parts[] = escapeshellarg('--'.ltrim((string) $key, '-').'='.$value);
            }
        }
        // Keep stdout/stderr: a fatal error in the detached process is otherwise invisible.
        // `echo $!` makes the shell exit immediately so PHP's exec() does not wait for the artisan process
        // (that wait is what turns a slow marketplace update into an HTTP 504).
        $shell = 'nohup '.implode(' ', $parts).' >>'.escapeshellarg(self::logFile()).' 2>&1 < /dev/null & echo $!';

        try {
            $exitCode = 1;
            exec($shell, $output, $exitCode);
        } catch (\Throwable $e) {
            Log::warning('DetachedArtisan: could not spawn background command', ['command' => $command, 'error' => $e->getMessage()]);

            return false;
        }

        return $exitCode === 0;
    }

    /**
     * Where detached commands append their console output (PHP fatals included).
     */
    public static function logFile(): string
    {
        return storage_path('logs/detached-artisan.log');
    }

    public static function available(): bool
    {
        if (PHP_OS_FAMILY === 'Windows' || ! function_exists('exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return ! in_array('exec', $disabled, true) && self::phpCliBinary() !== null;
    }

    /**
     * CLI php matching the running version (PHP_BINARY is php-fpm / apache in web requests).
     */
    public static function phpCliBinary(): ?string
    {
        static $resolved = false;
        static $binary = null;
        if ($resolved) {
            return $binary;
        }
        $resolved = true;

        $candidates = [];
        if (PHP_SAPI === 'cli' && PHP_BINARY !== '') {
            $candidates[] = PHP_BINARY;
        }
        $bindir = rtrim((string) PHP_BINDIR, '/');
        $version = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
        foreach ([$bindir, '/usr/bin', '/usr/local/bin'] as $dir) {
            if ($dir !== '') {
                $candidates[] = $dir.'/php'.$version;
                $candidates[] = $dir.'/php';
            }
        }
        try {
            $found = (new PhpExecutableFinder())->find(false);
            if (is_string($found) && $found !== '') {
                $candidates[] = $found;
            }
        } catch (\Throwable) {
            // ignore
        }

        foreach (array_unique($candidates) as $candidate) {
            $base = basename($candidate);
            if (str_contains($base, 'fpm') || str_contains($base, 'cgi') || str_contains($base, 'apache') || str_contains($base, 'httpd')) {
                continue;
            }
            if (@is_file($candidate) && @is_executable($candidate)) {
                $binary = $candidate;
                break;
            }
        }

        return $binary;
    }
}
