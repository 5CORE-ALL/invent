<?php

namespace App\Http\Controllers\CronMonitor;

use App\Http\Controllers\Controller;
use App\Models\CronExecutionFailure;
use App\Models\CronExecutionLog;
use App\Models\CronMonitorAlert;
use App\Repositories\CronExecutionLogRepository;
use App\Services\CronMonitor\ManualActionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CronMonitorDashboardController extends Controller
{
    public function __construct(
        protected CronExecutionLogRepository $repository,
        protected ManualActionService $actions,
    ) {}

    public function index(Request $request): View
    {
        $jobName = $request->query('job');
        $status = $request->query('status');
        $category = $request->query('category');

        $allLatest = $this->repository->latestByJob();
        $jobNames = $this->repository->jobNames();
        $categories = $this->repository->failureCategories();

        $overview = [
            'total_jobs' => $allLatest->count(),
            'healthy' => $allLatest->whereIn('status', [
                CronExecutionLog::STATUS_SUCCESS,
                CronExecutionLog::STATUS_RECOVERED,
            ])->count(),
            'partial' => $allLatest->where('status', CronExecutionLog::STATUS_PARTIAL_SUCCESS)->count(),
            'failed' => $allLatest->whereIn('status', [
                CronExecutionLog::STATUS_FAILED,
                CronExecutionLog::STATUS_TIMED_OUT,
                CronExecutionLog::STATUS_MISSED,
                CronExecutionLog::STATUS_STUCK,
                CronExecutionLog::STATUS_CANCELLED,
            ])->count(),
            'running' => $allLatest->where('status', CronExecutionLog::STATUS_RUNNING)->count(),
            'stuck' => $allLatest->where('status', CronExecutionLog::STATUS_STUCK)->count(),
        ];

        $selectedJob = $jobName ?: null;
        $trend = $selectedJob
            ? $this->repository->trendSeries($selectedJob, 30)
            : collect();
        $avgRuntime = $selectedJob
            ? $this->repository->averageRuntime($selectedJob)
            : null;
        $lastSuccess = $selectedJob
            ? $this->repository->lastSuccess($selectedJob)
            : null;

        $filteredLatest = $allLatest
            ->when($jobName, fn (Collection $c) => $c->where('job_name', $jobName))
            ->when($status, fn (Collection $c) => $c->where('status', $status))
            ->values();

        $lastSuccessMap = $this->repository->lastSuccessAtByJobs(
            $filteredLatest->pluck('job_name')->all()
        );

        $jobsTableData = $filteredLatest->map(function (CronExecutionLog $row) use ($lastSuccessMap) {
            $cp = is_array($row->meta) ? ($row->meta['chunk_progress'] ?? null) : null;
            $lastOk = $lastSuccessMap->get($row->job_name);
            $lastOkAt = $lastOk ? Carbon::parse($lastOk) : null;

            return [
                'id' => $row->id,
                'job_name' => $row->job_name,
                'command' => $row->command,
                'status' => $row->status,
                'recovery_status' => $row->recovery_status,
                'root_cause' => $row->root_cause,
                'health_score' => $row->health_score,
                'health_label' => $row->health_label,
                'success_percentage' => $row->success_percentage,
                'updated_records' => (int) $row->updated_records,
                'failed_records' => (int) $row->failed_records,
                'duration_seconds' => $row->duration_seconds,
                'retry_count' => (int) $row->retry_count,
                'consecutive_failures' => (int) $row->consecutive_failures,
                'chunks' => is_array($cp)
                    ? ((int) ($cp['completed'] ?? 0) . '/' . ($cp['total_chunks'] ?? '—'))
                    : '—',
                'chunks_failed' => is_array($cp) ? (int) ($cp['failed'] ?? 0) : 0,
                'chunk_current' => is_array($cp) ? ($cp['current'] ?? null) : null,
                'chunk_eta' => is_array($cp) ? ($cp['eta_seconds'] ?? null) : null,
                'memory' => is_array($cp)
                    ? ($cp['memory_usage'] ?? $row->memory_usage)
                    : ($row->memory_usage ?: '—'),
                'last_success_at' => $lastOkAt?->format('Y-m-d H:i'),
                'last_success_human' => $lastOkAt?->diffForHumans(),
                'details_url' => route('cron-monitor.show', $row->id),
            ];
        })->values();

        $recentLogs = CronExecutionLog::query()
            ->when($jobName, fn ($q) => $q->where('job_name', $jobName))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($category, fn ($q) => $q->where('failure_category', $category))
            ->orderByDesc('started_at')
            ->limit(300)
            ->get();

        $logsTableData = $recentLogs->map(fn (CronExecutionLog $log) => [
            'id' => $log->id,
            'job_name' => $log->job_name,
            'status' => $log->status,
            'failure_category' => $log->failure_category,
            'started_at' => optional($log->started_at)->format('Y-m-d H:i:s'),
            'started_human' => optional($log->started_at)->diffForHumans(),
            'duration_seconds' => $log->duration_seconds,
            'retry_count' => (int) $log->retry_count,
            'updated_records' => (int) $log->updated_records,
            'failed_records' => (int) $log->failed_records,
            'success_percentage' => $log->success_percentage,
            'health_score' => $log->health_score,
            'api_latency_ms_avg' => $log->api_latency_ms_avg,
            'details_url' => route('cron-monitor.show', $log->id),
        ])->values();

        $alerts = CronMonitorAlert::query()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $alertsTableData = $alerts->map(fn (CronMonitorAlert $alert) => [
            'alert_type' => $alert->alert_type,
            'severity' => $alert->severity,
            'title' => $alert->title,
            'message' => $alert->message,
            'when' => optional($alert->created_at)->diffForHumans(),
            'created_at' => optional($alert->created_at)->format('Y-m-d H:i:s'),
        ])->values();

        return view('cron-monitor.dashboard', [
            'title' => 'Cron Monitor',
            'overview' => $overview,
            'jobNames' => $jobNames,
            'categories' => $categories,
            'selectedJob' => $selectedJob,
            'trend' => $trend,
            'avgRuntime' => $avgRuntime,
            'lastSuccess' => $lastSuccess,
            'jobsTableData' => $jobsTableData,
            'logsTableData' => $logsTableData,
            'alertsTableData' => $alertsTableData,
            'filters' => [
                'job' => $jobName,
                'status' => $status,
                'category' => $category,
            ],
        ]);
    }

    public function show(int $id): View
    {
        $log = CronExecutionLog::with([
            'failures' => fn ($q) => $q->orderByDesc('id')->limit(200),
            'checkpoints',
        ])->findOrFail($id);

        $history = $this->repository->recentForJob($log->job_name, 30);
        $avgRuntime = $this->repository->averageRuntime($log->job_name);
        $lastSuccess = $this->repository->lastSuccess($log->job_name);
        $retryQueue = $log->unresolvedFailures()->orderBy('id')->limit(100)->get();

        $retryTableData = $retryQueue->map(fn (CronExecutionFailure $f) => [
            'sku' => $f->sku,
            'failure_category' => $f->failure_category,
            'recoverable' => $f->recoverable ? 'Yes' : 'No',
            'root_cause' => $f->root_cause ?: $f->failure_reason,
            'retry_count' => (int) $f->retry_count,
            'http_status' => $f->http_status,
        ])->values();

        $failuresTableData = $log->failures->map(fn (CronExecutionFailure $f) => [
            'sku' => $f->sku,
            'marketplace' => $f->marketplace,
            'failure_category' => $f->failure_category,
            'failure_reason' => $f->failure_reason,
            'retry_count' => (int) $f->retry_count,
            'resolved' => $f->resolved ? 'Yes' : 'No',
            'created_at' => optional($f->created_at)->format('Y-m-d H:i:s'),
        ])->values();

        $historyTableData = $history->map(fn (CronExecutionLog $h) => [
            'id' => $h->id,
            'status' => $h->status,
            'started_at' => optional($h->started_at)->format('Y-m-d H:i:s'),
            'duration_seconds' => $h->duration_seconds,
            'updated_records' => (int) $h->updated_records,
            'failed_records' => (int) $h->failed_records,
            'success_percentage' => $h->success_percentage,
            'health_score' => $h->health_score,
            'is_current' => $h->id === $log->id,
            'details_url' => route('cron-monitor.show', $h->id),
        ])->values();

        return view('cron-monitor.show', [
            'title' => 'Cron Run #' . $log->id,
            'log' => $log,
            'history' => $history,
            'avgRuntime' => $avgRuntime,
            'lastSuccess' => $lastSuccess,
            'retryQueue' => $retryQueue,
            'retryTableData' => $retryTableData,
            'failuresTableData' => $failuresTableData,
            'historyTableData' => $historyTableData,
        ]);
    }

    public function trend(Request $request): JsonResponse
    {
        $jobName = (string) $request->query('job');
        if ($jobName === '') {
            return response()->json(['error' => 'job required'], 422);
        }

        $series = $this->repository->trendSeries($jobName, 30);

        return response()->json([
            'labels' => $series->map(fn ($r) => optional($r->started_at)->format('m/d H:i'))->values(),
            'success_percentage' => $series->pluck('success_percentage')->values(),
            'health_score' => $series->pluck('health_score')->values(),
            'updated_records' => $series->pluck('updated_records')->values(),
            'failed_records' => $series->pluck('failed_records')->values(),
            'duration_seconds' => $series->pluck('duration_seconds')->values(),
            'statuses' => $series->pluck('status')->values(),
        ]);
    }

    public function resolveFailure(int $id): JsonResponse
    {
        $failure = CronExecutionFailure::findOrFail($id);
        $failure->markResolved();

        return response()->json(['ok' => true]);
    }

    public function retryJob(int $id): RedirectResponse
    {
        $log = CronExecutionLog::findOrFail($id);
        $this->actions->retryJob($log);

        return back()->with('success', 'Job retry queued.');
    }

    public function resumeJob(int $id): RedirectResponse
    {
        $log = CronExecutionLog::findOrFail($id);
        $this->actions->resumeJob($log);

        return back()->with('success', 'Resume queued (checkpoint will be used).');
    }

    public function retryFailures(int $id): RedirectResponse
    {
        $log = CronExecutionLog::findOrFail($id);
        $this->actions->retryFailedRecords($log->job_name);

        return back()->with('success', 'Failed-record retry queued.');
    }

    public function cancelJob(int $id): RedirectResponse
    {
        $log = CronExecutionLog::findOrFail($id);
        $this->actions->cancelRunning($log);

        return back()->with('success', 'Execution cancelled / unlocked.');
    }

    public function unlock(Request $request): RedirectResponse
    {
        $command = (string) $request->input('command');
        if ($command === '') {
            return back()->with('error', 'Command required.');
        }
        $this->actions->unlock($command);

        return back()->with('success', "Lock released for {$command}.");
    }

    public function downloadLog(int $id): StreamedResponse
    {
        $log = CronExecutionLog::findOrFail($id);

        return $this->actions->downloadLog($log);
    }
}
