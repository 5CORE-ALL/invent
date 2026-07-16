<?php

namespace App\Support;

/**
 * Recreate / repair storage dirs wiped by optimize:clear or broken by host
 * security scanners that rewrite 0777 → 0665 (dirs lose +x and become unusable).
 *
 * Never use 0777 here — many hosts auto-strip it and leave directories non-traversable.
 */
final class StoragePathGuard
{
    /** @var list<string> */
    private static function criticalDirs(): array
    {
        return [
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];
    }

    public static function ensure(): void
    {
        foreach (self::criticalDirs() as $dir) {
            self::ensureDirectory($dir);
        }
    }

    public static function ensureCacheShardForPath(string $path): void
    {
        self::ensure();

        $dataRoot = storage_path('framework/cache/data');
        $directory = dirname($path);

        // Create/repair each segment from cache/data down to the shard dir.
        $segments = [];
        $current = $directory;
        for ($i = 0; $i < 8; $i++) {
            $segments[] = $current;
            if ($current === $dataRoot || ! str_starts_with($current, $dataRoot)) {
                break;
            }
            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = $parent;
        }

        foreach (array_reverse($segments) as $dir) {
            self::ensureDirectory($dir);
        }
    }

    /**
     * Repair directory modes under cache/data (host may chmod -R 665 and strip +x).
     * Intended for artisan storage:ensure --fix / cron, not every web request.
     */
    public static function repairCacheTree(): void
    {
        self::ensure();

        $root = storage_path('framework/cache/data');
        if (! is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                self::ensureDirectory($item->getPathname());
            }
        }
    }

    private static function ensureDirectory(string $directory): void
    {
        clearstatcache(true, $directory);

        if (! is_dir($directory)) {
            // 02775 = rwxrwsr-x (setgid so new shards inherit the group). Never 0777.
            if (! @mkdir($directory, 02775, true) && ! is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
            clearstatcache(true, $directory);
        }

        if (! is_dir($directory)) {
            return;
        }

        // 0665 on a directory removes owner/group +x → cannot traverse or create children.
        if (! is_writable($directory) || ! self::directoryHasExecute($directory)) {
            @chmod($directory, 02775);
            clearstatcache(true, $directory);
        }
    }

    private static function directoryHasExecute(string $directory): bool
    {
        $perms = @fileperms($directory);
        if ($perms === false) {
            return false;
        }

        // Owner or group execute bit must be set for the process to enter the dir.
        return (bool) ($perms & 0110);
    }
}
