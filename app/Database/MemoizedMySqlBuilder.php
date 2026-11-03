<?php

namespace App\Database;

use Illuminate\Database\Schema\MySqlBuilder;

/**
 * MySQL schema builder that remembers table / column lookups for the life of
 * the process.
 *
 * Laravel 10's hasTable() lists every table with its size from
 * information_schema (~40ms on this DB) on *every* call, and hasColumn() does
 * a column lookup each time. Pages such as Advertisement Master call these
 * hundreds of times per request, so memoizing them removes seconds of pure
 * overhead. Memoization is only switched on for web requests (see
 * AppServiceProvider); CLI / migrations keep the stock behaviour, and any
 * schema change made through this builder flushes the memo.
 */
class MemoizedMySqlBuilder extends MySqlBuilder
{
    public static bool $enabled = false;

    /** @var array<string, array<string, true>>|null  connection => lowercase table names */
    private static ?array $tables = null;

    /** @var array<string, array<string, list<string>>> */
    private static array $columns = [];

    public static function flush(): void
    {
        self::$tables = null;
        self::$columns = [];
    }

    private function memoKey(): string
    {
        return (string) $this->connection->getName().'|'.$this->connection->getDatabaseName();
    }

    public function hasTable($table)
    {
        if (! self::$enabled) {
            return parent::hasTable($table);
        }

        $key = $this->memoKey();
        if (! isset(self::$tables[$key])) {
            $names = [];
            foreach (parent::getTables() as $row) {
                $names[strtolower($row['name'])] = true;
            }
            self::$tables[$key] = $names;
        }

        return isset(self::$tables[$key][strtolower($this->connection->getTablePrefix().$table)]);
    }

    public function getColumnListing($table)
    {
        if (! self::$enabled) {
            return parent::getColumnListing($table);
        }

        $key = $this->memoKey();
        $name = strtolower($table);

        return self::$columns[$key][$name] ??= parent::getColumnListing($table);
    }

    public function hasColumn($table, $column)
    {
        if (! self::$enabled) {
            return parent::hasColumn($table, $column);
        }

        return in_array(
            strtolower($column),
            array_map('strtolower', $this->getColumnListing($table)),
            true
        );
    }

    public function create($table, \Closure $callback)
    {
        self::flush();
        parent::create($table, $callback);
        self::flush();
    }

    public function table($table, \Closure $callback)
    {
        self::flush();
        parent::table($table, $callback);
        self::flush();
    }

    public function drop($table)
    {
        self::flush();
        parent::drop($table);
        self::flush();
    }

    public function dropIfExists($table)
    {
        self::flush();
        parent::dropIfExists($table);
        self::flush();
    }

    public function rename($from, $to)
    {
        self::flush();
        parent::rename($from, $to);
        self::flush();
    }

    public function dropAllTables()
    {
        self::flush();
        parent::dropAllTables();
        self::flush();
    }
}
