<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/**
 * "Save and Apply" on every pricing page: the browser computes the S PRC values, hands them to the server
 * in ONE request, and a detached CLI process (php artisan sprice:background-save) writes them through the
 * channel's own save endpoint. The page can be closed right after the hand-off.
 *
 * Same spawn pattern as ChannelPushSpriceRunner::spawnWorker (no queue worker needed).
 */
class BackgroundSpriceSave
{
    public const MAX_UPDATES = 50000;

    public static function dir(): string
    {
        $dir = storage_path('app/background-sprice');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function payloadPath(string $id): string
    {
        return self::dir().'/'.$id.'.json';
    }

    public static function statusPath(string $id): string
    {
        return self::dir().'/'.$id.'.status.json';
    }

    public static function lockPath(string $path): string
    {
        return self::dir().'/'.md5($path).'.lock';
    }

    public static function isValidId(string $id): bool
    {
        return (bool) preg_match('/^[a-f0-9]{24}$/', $id);
    }

    /**
     * Reduce a URL to its path and make sure it is a POST route that saves S PRC.
     * Returns the path, or null when it is not an allowed target.
     */
    public static function allowedPath(string $url): ?string
    {
        $path = (string) parse_url(trim($url), PHP_URL_PATH);
        if ($path === '' || $path[0] !== '/') {
            return null;
        }
        if (stripos($path, 'background-sprice') !== false) {
            return null;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($path, 'POST'));
        } catch (\Throwable) {
            return null;
        }

        $uri = (string) $route->uri();
        if (! preg_match('/(sprice|save-status)/i', $uri)) {
            return null;
        }

        return $path;
    }

    /**
     * @param  array<int, array<string, mixed>>  $updates
     * @param  array<string, scalar|null>  $extra
     * @return array{id: string, spawned: bool}
     */
    public static function enqueue(int $userId, string $path, array $updates, array $extra, string $mode, string $label): array
    {
        $id = bin2hex(random_bytes(12));
        $payload = [
            'id' => $id,
            'user_id' => $userId,
            'path' => $path,
            'mode' => $mode === 'single' ? 'single' : 'batch',
            'label' => $label,
            'extra' => $extra,
            'updates' => array_values($updates),
            'created_at' => now()->toDateTimeString(),
        ];
        file_put_contents(self::payloadPath($id), json_encode($payload, JSON_UNESCAPED_UNICODE));
        self::writeStatus($id, [
            'id' => $id,
            'state' => 'queued',
            'label' => $label,
            'path' => $path,
            'total' => count($updates),
            'done' => 0,
            'failed' => 0,
            'message' => 'Queued',
            'updated_at' => now()->toDateTimeString(),
        ]);

        return ['id' => $id, 'spawned' => self::spawn($id)];
    }

    /** @param  array<string, mixed>  $status */
    public static function writeStatus(string $id, array $status): void
    {
        $status['updated_at'] = now()->toDateTimeString();
        @file_put_contents(self::statusPath($id), json_encode($status, JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed>|null */
    public static function readStatus(string $id): ?array
    {
        $file = self::statusPath($id);
        if (! is_file($file)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    public static function spawn(string $id): bool
    {
        try {
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $artisan = base_path('artisan');
            $log = storage_path('logs/background-sprice-save.log');
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' sprice:background-save '.escapeshellarg($id), 'r'));

                return true;
            }
            $cmd = 'nohup '.escapeshellarg($php).' '.escapeshellarg($artisan)
                .' sprice:background-save '.escapeshellarg($id)
                .' >> '.escapeshellarg($log).' 2>&1 &';
            pclose(popen($cmd, 'r'));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Background S PRC save spawn failed', ['id' => $id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
