<?php

namespace App\Support;

/**
 * Recreate storage dirs wiped by optimize:clear / cache:clear so file cache
 * and sessions never 500 the layout mid-request.
 */
final class StoragePathGuard
{
    public static function ensure(): void
    {
        foreach ([
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ] as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
    }

    public static function ensureCacheShardForPath(string $path): void
    {
        self::ensure();

        $directory = dirname($path);
        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
    }
}
