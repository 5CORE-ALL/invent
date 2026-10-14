<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Memo for Schema::hasTable / hasColumn / getColumnListing.
 *
 * Each of those is an information_schema query (tens of ms). The Amazon Ads grid asked the same
 * questions about the same tables dozens of times per request, which alone was ~40% of the load.
 *
 * Two layers:
 *  - per process: ask once per request;
 *  - shared cache: keep the answer between requests. The cache key carries the number of migration
 *    files, so deploying a migration (the only normal way a table or column appears) starts a fresh key.
 *
 * Disabled under unit tests (they create and drop tables between cases). Call flush() after changing
 * a schema by hand in a long-running process.
 */
final class SchemaMemo
{
    private const TTL_SECONDS = 3600;

    /** @var array<string, bool> */
    private static array $tables = [];

    /** @var array<string, list<string>> */
    private static array $columns = [];

    private static ?string $version = null;

    private static function on(): bool
    {
        return ! app()->runningUnitTests();
    }

    private static function version(): string
    {
        if (self::$version === null) {
            $files = glob(database_path('migrations/*.php'));
            self::$version = (string) (is_array($files) ? count($files) : 0);
        }

        return self::$version;
    }

    private static function key(string $kind, string $table): string
    {
        return 'amz_schema:'.self::version().':'.$kind.':'.$table;
    }

    private static function shared(string $key): mixed
    {
        try {
            return Cache::get($key);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function share(string $key, mixed $value): void
    {
        try {
            Cache::put($key, $value, self::TTL_SECONDS);
        } catch (\Throwable) {
        }
    }

    public static function hasTable(string $table): bool
    {
        if (! self::on()) {
            return Schema::hasTable($table);
        }
        if (isset(self::$tables[$table])) {
            return true;
        }
        $key = self::key('table', $table);
        if (self::shared($key) === true) {
            return self::$tables[$table] = true;
        }
        // Only a positive answer is kept, so a table created later is still found.
        if (Schema::hasTable($table)) {
            self::share($key, true);

            return self::$tables[$table] = true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function getColumnListing(string $table): array
    {
        if (! self::on()) {
            return Schema::getColumnListing($table);
        }
        if (isset(self::$columns[$table])) {
            return self::$columns[$table];
        }
        $key = self::key('columns', $table);
        $cached = self::shared($key);
        if (is_array($cached) && $cached !== []) {
            return self::$columns[$table] = array_values($cached);
        }
        $cols = Schema::getColumnListing($table);
        if ($cols !== []) {
            self::share($key, $cols);
            self::$columns[$table] = $cols;
        }

        return $cols;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        if (! self::on()) {
            return Schema::hasColumn($table, $column);
        }
        $want = strtolower($column);
        foreach (self::getColumnListing($table) as $c) {
            if (strtolower((string) $c) === $want) {
                return true;
            }
        }

        return false;
    }

    public static function flush(): void
    {
        try {
            foreach (array_keys(self::$tables) as $t) {
                Cache::forget(self::key('table', $t));
            }
            foreach (array_keys(self::$columns) as $t) {
                Cache::forget(self::key('columns', $t));
            }
        } catch (\Throwable) {
        }
        self::$tables = [];
        self::$columns = [];
        self::$version = null;
    }
}
