<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Permanently removes tasks that have sat in the deleted/archived list (/tasks/deleted) longer
 * than the retention window, plus their soft-deleted rows in `tasks`, which only exist so the
 * task can be reverted. Deletes in small batches to keep table locks short.
 */
class PurgeArchivedTasks extends Command
{
    protected $signature = 'tasks:purge-archived
        {--days=90 : Archived for at least this many days}
        {--dry-run : Only count what would be deleted}';

    protected $description = 'Permanently delete tasks archived (deleted) for more than N days (default 90)';

    private const BATCH = 1000;

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $archiveQuery = fn () => DB::table('deleted_tasks')->where(function ($q) use ($cutoff) {
            $q->where('deleted_at', '<', $cutoff)
                ->orWhere(fn ($q) => $q->whereNull('deleted_at')->where('created_at', '<', $cutoff));
        });
        $trashedQuery = fn () => DB::table('tasks')->whereNotNull('deleted_at')->where('deleted_at', '<', $cutoff);

        $hasArchive = Schema::hasTable('deleted_tasks');
        $archiveCount = $hasArchive ? $archiveQuery()->count() : 0;
        $trashedCount = $trashedQuery()->count();

        $this->info("Cutoff: {$cutoff->toDateTimeString()} ({$days} days)");
        $this->info("Archived task records to delete: {$archiveCount}");
        $this->info("Soft-deleted task rows to delete: {$trashedCount}");

        if ($dryRun || ($archiveCount === 0 && $trashedCount === 0)) {
            return self::SUCCESS;
        }

        $archiveDeleted = $hasArchive ? $this->deleteInBatches($archiveQuery) : 0;

        $trashedDeleted = $this->deleteInBatches($trashedQuery, function (array $ids) {
            \App\Support\ChatWorkspace::deleteTaskChats($ids);
        });

        $this->info("Deleted {$archiveDeleted} archived record(s) and {$trashedDeleted} soft-deleted task row(s).");
        Log::info('tasks:purge-archived', [
            'days' => $days,
            'archive_deleted' => $archiveDeleted,
            'tasks_deleted' => $trashedDeleted,
        ]);

        return self::SUCCESS;
    }

    /**
     * @param  callable(): \Illuminate\Database\Query\Builder  $query
     * @param  (callable(list<int>): void)|null  $beforeDelete
     */
    private function deleteInBatches(callable $query, ?callable $beforeDelete = null): int
    {
        $total = 0;
        do {
            $ids = $query()->orderBy('id')->limit(self::BATCH)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($ids === []) {
                break;
            }
            if ($beforeDelete) {
                try {
                    $beforeDelete($ids);
                } catch (\Throwable $e) {
                    Log::warning('tasks:purge-archived pre-delete hook failed', ['error' => $e->getMessage()]);
                }
            }
            $total += $query()->whereIn('id', $ids)->delete();
        } while (count($ids) === self::BATCH);

        return $total;
    }
}
