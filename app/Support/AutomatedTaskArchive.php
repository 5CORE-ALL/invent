<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Archive store for automate_tasks templates.
 *
 * "Deleting" an automated task moves its row (and its subtask templates) into
 * archived_automate_tasks. Scheduling/execution jobs only read automate_tasks, so
 * archived templates stop generating tasks, but can be restored later.
 */
class AutomatedTaskArchive
{
    public const TABLE = 'archived_automate_tasks';

    public static function ensureTable(): void
    {
        if (! Schema::hasTable('automate_tasks') || Schema::hasTable(self::TABLE)) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('CREATE TABLE `'.self::TABLE.'` LIKE `automate_tasks`');
        } else {
            DB::statement('CREATE TABLE '.self::TABLE.' AS SELECT * FROM automate_tasks WHERE 1 = 0');
        }

        Schema::table(self::TABLE, function ($table) {
            $table->timestamp('archived_at')->nullable()->index();
            $table->string('archived_by', 255)->nullable();
        });
    }

    /**
     * Move templates (and all their subtask templates) into the archive.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, int> ids that were archived
     */
    public static function archive(array $ids, ?string $archivedBy = null): array
    {
        self::ensureTable();

        $all = self::withDescendants('automate_tasks', $ids);
        if ($all === []) {
            return [];
        }

        $archiveColumns = array_flip(Schema::getColumnListing(self::TABLE));
        $now = now();

        DB::transaction(function () use ($all, $archiveColumns, $now, $archivedBy) {
            $rows = DB::table('automate_tasks')->whereIn('id', $all)->get();

            DB::table(self::TABLE)->whereIn('id', $all)->delete();
            foreach ($rows as $row) {
                $data = array_intersect_key((array) $row, $archiveColumns);
                $data['archived_at'] = $now;
                $data['archived_by'] = $archivedBy;
                DB::table(self::TABLE)->insert($data);
            }

            DB::table('automate_tasks')->whereIn('id', $all)->delete();
        });

        return $all;
    }

    /**
     * Move archived templates (and their archived subtasks) back to automate_tasks.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, int> ids that were restored
     */
    public static function restore(array $ids): array
    {
        self::ensureTable();

        $all = self::withDescendants(self::TABLE, $ids);
        if ($all === []) {
            return [];
        }

        $activeColumns = array_flip(Schema::getColumnListing('automate_tasks'));

        DB::transaction(function () use ($all, $activeColumns) {
            $rows = DB::table(self::TABLE)->whereIn('id', $all)->get();

            DB::table('automate_tasks')->whereIn('id', $all)->delete();
            foreach ($rows as $row) {
                DB::table('automate_tasks')->insert(array_intersect_key((array) $row, $activeColumns));
            }

            DB::table(self::TABLE)->whereIn('id', $all)->delete();
        });

        return $all;
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    private static function withDescendants(string $table, array $ids): array
    {
        $all = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
        if ($all === []) {
            return [];
        }

        $frontier = $all;
        $guard = 0;
        while ($frontier !== [] && $guard < 25) {
            $guard++;
            $children = DB::table($table)
                ->whereIn('parent_task_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->diff($all)
                ->values()
                ->all();
            $all = array_merge($all, $children);
            $frontier = $children;
        }

        // Only ids that actually exist in the source table.
        return DB::table($table)->whereIn('id', $all)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
