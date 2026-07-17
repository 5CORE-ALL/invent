<?php

namespace App\Http\Controllers\CronMonitor;

use App\Http\Controllers\Controller;
use App\Models\CronExecutionFailure;
use App\Models\CronExecutionLog;
use App\Models\CronMonitorAlert;
use App\Repositories\CronExecutionLogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CronMonitorDashboardController extends Controller
{
    public function __construct(protected CronExecutionLogRepository $repository) {}

    public function index(Request $request): View
    {
        $jobName = $request->query('job');
        $status = $request->query('status');

        $latest = $this->repository->latestByJob();
        $jobNames = $this->repository->jobNames();

        $overview = [
            'total_jobs' => $latest->count(),
            'healthy' => $latest->where('status', CronExecutionLog::STATUS_SUCCESS)->count(),
            'partial' => $latest->where('status', CronExecutionLog::STATUS_PARTIAL_SUCCESS)->count(),
            'failed' => $latest->whereIn('status', [
                CronExecutionLog::STATUS_FAILED,
                CronExecutionLog::STATUS_TIMED_OUT,
                CronExecutionLog::STATUS_MISSED,
            ])->count(),
            'running' => $latest->where('status', CronExecutionLog::STATUS_RUNNING)->count(),
        ];

        $selectedJob = $jobName ?: $latest->first()?->job_name;
        $trend = $selectedJob
            ? $this->repository->trendSeries($selectedJob, 30)
            : collect();
        $avgRuntime = $selectedJob
            ? $this->repository->averageRuntime($selectedJob)
            : null;
        $recent = $this->repository->paginate(25, $jobName, $status);
        $alerts = CronMonitorAlert::query()
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        return view('cron-monitor.dashboard', [
            'title' => 'Cron Monitor',
            'overview' => $overview,
            'latest' => $latest,
            'jobNames' => $jobNames,
            'selectedJob' => $selectedJob,
            'trend' => $trend,
            'avgRuntime' => $avgRuntime,
            'recent' => $recent,
            'alerts' => $alerts,
            'filters' => [
                'job' => $jobName,
                'status' => $status,
            ],
        ]);
    }

    public function show(int $id): View
    {
        $log = CronExecutionLog::with(['failures' => fn ($q) => $q->orderByDesc('id')->limit(200)])
            ->findOrFail($id);

        $history = $this->repository->recentForJob($log->job_name, 30);
        $avgRuntime = $this->repository->averageRuntime($log->job_name);

        return view('cron-monitor.show', [
            'title' => 'Cron Run #' . $log->id,
            'log' => $log,
            'history' => $history,
            'avgRuntime' => $avgRuntime,
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
}
