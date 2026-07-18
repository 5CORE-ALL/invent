<?php

namespace App\Http\Controllers\CronMonitor;

use App\Http\Controllers\Controller;
use App\Models\CronExecutionFailure;
use App\Models\CronExecutionLog;
use App\Models\CronMonitorAlert;
use App\Repositories\CronExecutionLogRepository;
use App\Services\CronMonitor\ManualActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $latest = $this->repository->latestByJob();
        $jobNames = $this->repository->jobNames();
        $categories = $this->repository->failureCategories();

        $overview = [
            'total_jobs' => $latest->count(),
            'healthy' => $latest->whereIn('status', [
                CronExecutionLog::STATUS_SUCCESS,
                CronExecutionLog::STATUS_RECOVERED,
            ])->count(),
            'partial' => $latest->where('status', CronExecutionLog::STATUS_PARTIAL_SUCCESS)->count(),
            'failed' => $latest->whereIn('status', [
                CronExecutionLog::STATUS_FAILED,
                CronExecutionLog::STATUS_TIMED_OUT,
                CronExecutionLog::STATUS_MISSED,
                CronExecutionLog::STATUS_STUCK,
                CronExecutionLog::STATUS_CANCELLED,
            ])->count(),
            'running' => $latest->where('status', CronExecutionLog::STATUS_RUNNING)->count(),
            'stuck' => $latest->where('status', CronExecutionLog::STATUS_STUCK)->count(),
        ];

        $selectedJob = $jobName ?: $latest->first()?->job_name;
        $trend = $selectedJob
            ? $this->repository->trendSeries($selectedJob, 30)
            : collect();
        $avgRuntime = $selectedJob
            ? $this->repository->averageRuntime($selectedJob)
            : null;
        $lastSuccess = $selectedJob
            ? $this->repository->lastSuccess($selectedJob)
            : null;
        $recent = $this->repository->paginate(25, $jobName, $status, $category);
        $alerts = CronMonitorAlert::query()
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $enrichedLatest = $latest->map(function (CronExecutionLog $row) {
            $row->setAttribute('last_success_at', $this->repository->lastSuccess($row->job_name)?->finished_at);

            return $row;
        });

        return view('cron-monitor.dashboard', [
            'title' => 'Cron Monitor',
            'overview' => $overview,
            'latest' => $enrichedLatest,
            'jobNames' => $jobNames,
            'categories' => $categories,
            'selectedJob' => $selectedJob,
            'trend' => $trend,
            'avgRuntime' => $avgRuntime,
            'lastSuccess' => $lastSuccess,
            'recent' => $recent,
            'alerts' => $alerts,
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

        return view('cron-monitor.show', [
            'title' => 'Cron Run #' . $log->id,
            'log' => $log,
            'history' => $history,
            'avgRuntime' => $avgRuntime,
            'lastSuccess' => $lastSuccess,
            'retryQueue' => $retryQueue,
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
