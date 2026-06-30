<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceSummaryService;
use App\Support\AttendanceAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceSummaryController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly AttendanceSummaryService $summaryService,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        abort_unless(AttendanceAccess::canMonitor(), 403);

        $timezone = $request->input('timezone', config('attendance.timeline_timezone', 'Asia/Kolkata'));
        $dayReset = $request->input('day_reset', config('attendance.timeline_day_reset', '04:00'));
        $team = $request->input('team', 'all');

        [$from, $to, $rangeKey] = $this->resolveRange($request, $timezone);

        [$employees, $teams] = $this->filteredEmployees($team);

        $summary = $this->summaryService->teamSummary($employees, $from, $to, $timezone);

        return view('attendance.summary', [
            'title' => 'Team Monitoring',
            'from' => $from,
            'to' => $to,
            'range_key' => $rangeKey,
            'team' => $team,
            'timezone' => $timezone,
            'day_reset' => $dayReset,
            'teams' => $teams,
            'rows' => $summary['rows'],
            'totals' => $summary['totals'],
            'not_logged' => $summary['not_logged'],
            'total_employees' => $summary['total_employees'],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless(AttendanceAccess::canMonitor(), 403);

        $timezone = $request->input('timezone', config('attendance.timeline_timezone', 'Asia/Kolkata'));
        $team = $request->input('team', 'all');
        [$from, $to] = $this->resolveRange($request, $timezone);

        [$employees] = $this->filteredEmployees($team);
        $summary = $this->summaryService->teamSummary($employees, $from, $to, $timezone);

        return response()->json($summary);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(AttendanceAccess::canMonitor(), 403);

        $timezone = $request->input('timezone', config('attendance.timeline_timezone', 'Asia/Kolkata'));
        $team = $request->input('team', 'all');
        [$from, $to] = $this->resolveRange($request, $timezone);

        [$employees] = $this->filteredEmployees($team);
        $summary = $this->summaryService->teamSummary($employees, $from, $to, $timezone);
        $csv = $this->summaryService->toCsv($summary['rows'], $from, $to);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'employee-summary_'.$from.'_'.$to.'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, User>, 1: \Illuminate\Support\Collection<int, mixed>}
     */
    private function filteredEmployees(string $team): array
    {
        $viewableIds = AttendanceAccess::viewableUserIds();
        $all = $this->attendanceService->monitorableEmployees($viewableIds);
        $teams = $all->pluck('designation')->filter()->unique()->sort()->values();

        $employees = $team === 'all'
            ? $all
            : $all->filter(fn (User $u) => (string) $u->designation === $team)->values();

        return [$employees, $teams];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveRange(Request $request, string $timezone): array
    {
        $now = now()->timezone($timezone);
        $today = $now->toDateString();
        $range = $request->input('range', 'custom');

        if ($range === 'today') {
            return [$today, $today, 'today'];
        }
        if ($range === 'week') {
            return [$now->copy()->startOfWeek()->toDateString(), $today, 'week'];
        }
        if ($range === 'month') {
            return [$now->copy()->startOfMonth()->toDateString(), $today, 'month'];
        }
        if ($range === 'prev_month') {
            $prev = $now->copy()->subMonth();

            return [
                $prev->copy()->startOfMonth()->toDateString(),
                $prev->copy()->endOfMonth()->toDateString(),
                'prev_month',
            ];
        }

        [$defaultFrom, $defaultTo] = $this->summaryService->defaultDateRange($timezone);
        $from = $request->input('from', $defaultFrom);
        $to = $request->input('to', $defaultTo);
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to, 'custom'];
    }
}
