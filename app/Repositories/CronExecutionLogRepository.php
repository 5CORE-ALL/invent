<?php

namespace App\Repositories;

use App\Models\CronExecutionLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CronExecutionLogRepository
{
    public function createRunning(string $jobName, ?string $command = null): CronExecutionLog
    {
        $attrs = [
            'job_name' => $jobName,
            'command' => $command,
            'status' => CronExecutionLog::STATUS_RUNNING,
            'started_at' => now(),
            'execution_server' => gethostname() ?: php_uname('n'),
            'memory_usage' => $this->memoryUsage(),
        ];

        try {
            return CronExecutionLog::create($attrs);
        } catch (\Throwable $e) {
            // Local/prod schemas sometimes lack AUTO_INCREMENT on id — assign next id.
            if (! str_contains($e->getMessage(), "Field 'id' doesn't have a default value")) {
                throw $e;
            }

            return DB::transaction(function () use ($attrs) {
                $nextId = ((int) (DB::table('cron_execution_logs')->lockForUpdate()->max('id') ?? 0)) + 1;
                $log = new CronExecutionLog($attrs);
                $log->id = $nextId;
                $log->save();

                return $log;
            });
        }
    }

    public function find(int $id): ?CronExecutionLog
    {
        return CronExecutionLog::find($id);
    }

    public function latestByJob(): Collection
    {
        $latestIds = CronExecutionLog::query()
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('job_name')
            ->pluck('id');

        return CronExecutionLog::query()
            ->whereIn('id', $latestIds)
            ->orderBy('job_name')
            ->get();
    }

    /**
     * Paginate "latest row per job" for the dashboard (avoids loading 100+ jobs at once).
     */
    public function latestByJobPaginated(
        int $perPage = 50,
        ?string $jobName = null,
        ?string $status = null,
        string $pageName = 'jobs_page'
    ): LengthAwarePaginator {
        $latestIds = CronExecutionLog::query()
            ->select(DB::raw('MAX(id) as id'))
            ->when($jobName, fn ($q) => $q->where('job_name', $jobName))
            ->groupBy('job_name')
            ->pluck('id');

        return CronExecutionLog::query()
            ->whereIn('id', $latestIds)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('job_name')
            ->paginate($perPage, ['*'], $pageName);
    }

    /**
     * Batch-load last success timestamps for many jobs (no N+1).
     *
     * @param  list<string>  $jobNames
     * @return Collection<string, \Carbon\Carbon|string|null>  keyed by job_name
     */
    public function lastSuccessAtByJobs(array $jobNames): Collection
    {
        $jobNames = array_values(array_unique(array_filter($jobNames)));
        if ($jobNames === []) {
            return collect();
        }

        $successStatuses = [
            CronExecutionLog::STATUS_SUCCESS,
            CronExecutionLog::STATUS_RECOVERED,
        ];

        $rows = CronExecutionLog::query()
            ->select('job_name', DB::raw('MAX(finished_at) as last_success_at'))
            ->whereIn('job_name', $jobNames)
            ->whereIn('status', $successStatuses)
            ->groupBy('job_name')
            ->get();

        return $rows->pluck('last_success_at', 'job_name');
    }

    public function recentForJob(string $jobName, int $limit = 30): Collection
    {
        return CronExecutionLog::query()
            ->forJob($jobName)
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();
    }

    public function paginate(
        int $perPage = 25,
        ?string $jobName = null,
        ?string $status = null,
        ?string $category = null,
        string $pageName = 'logs_page'
    ): LengthAwarePaginator {
        return CronExecutionLog::query()
            ->when($jobName, fn ($q) => $q->where('job_name', $jobName))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($category, fn ($q) => $q->where('failure_category', $category))
            ->orderByDesc('started_at')
            ->paginate($perPage, ['*'], $pageName);
    }

    public function lastSuccess(string $jobName): ?CronExecutionLog
    {
        return CronExecutionLog::query()
            ->forJob($jobName)
            ->whereIn('status', [CronExecutionLog::STATUS_SUCCESS, CronExecutionLog::STATUS_RECOVERED])
            ->orderByDesc('finished_at')
            ->first();
    }

    public function failureCategories(): Collection
    {
        return CronExecutionLog::query()
            ->whereNotNull('failure_category')
            ->distinct()
            ->orderBy('failure_category')
            ->pluck('failure_category');
    }

    public function averageRuntime(string $jobName, int $sample = 30): ?float
    {
        $avg = CronExecutionLog::query()
            ->forJob($jobName)
            ->finished()
            ->whereNotNull('duration_seconds')
            ->orderByDesc('started_at')
            ->limit($sample)
            ->avg('duration_seconds');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    public function jobNames(): Collection
    {
        return CronExecutionLog::query()
            ->select('job_name')
            ->distinct()
            ->orderBy('job_name')
            ->pluck('job_name');
    }

    public function trendSeries(string $jobName, int $limit = 30): Collection
    {
        return CronExecutionLog::query()
            ->forJob($jobName)
            ->finished()
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get([
                'id',
                'started_at',
                'finished_at',
                'status',
                'duration_seconds',
                'success_percentage',
                'health_score',
                'updated_records',
                'failed_records',
                'fetched_records',
            ])
            ->sortBy('started_at')
            ->values();
    }

    public function purgeOlderThan(int $days): int
    {
        $cutoff = now()->subDays($days);

        return CronExecutionLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    public function memoryUsage(): string
    {
        return round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';
    }
}
