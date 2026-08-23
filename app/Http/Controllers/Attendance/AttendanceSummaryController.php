<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Attendance\AttendanceSummaryService;
use App\Services\Attendance\AttendanceTimelineService;
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

        $timezone = $request->input('timezone', AttendanceTimelineService::defaultTimezone());
        $dayReset = $request->input('day_reset', AttendanceTimelineService::defaultDayReset($timezone));
        $payload = $this->buildSummary($request, $timezone);

        return view('attendance.summary', [
            'title' => 'Team Monitoring',
            'from' => $payload['from'],
            'to' => $payload['to'],
            'range_key' => $payload['range_key'],
            'team' => $payload['team'],
            'executive' => $payload['executive'],
            'timezone' => $timezone,
            'day_reset' => $dayReset,
            'teams' => $payload['teams'],
            'executives' => $payload['executives'],
            'rows' => $payload['rows'],
            'totals' => $payload['l30_totals'],
            'l30_from' => $payload['l30_from'],
            'l30_to' => $payload['l30_to'],
            'not_logged' => $payload['not_logged'],
            'total_employees' => $payload['total_employees'],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless(AttendanceAccess::canMonitor(), 403);

        $timezone = $request->input('timezone', AttendanceTimelineService::defaultTimezone());

        return response()->json($this->buildSummary($request, $timezone));
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(AttendanceAccess::canMonitor(), 403);

        $timezone = $request->input('timezone', AttendanceTimelineService::defaultTimezone());
        $payload = $this->buildSummary($request, $timezone);
        $csv = $this->summaryService->toCsv($payload['rows'], $payload['from'], $payload['to']);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'employee-summary_'.$payload['from'].'_'.$payload['to'].'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Table follows the chosen day/range. Top badges are always L30 for the
     * selected executive (or the current team when none is selected).
     *
     * @return array<string, mixed>
     */
    private function buildSummary(Request $request, string $timezone): array
    {
        $team = (string) $request->input('team', 'all');
        $executiveId = (int) $request->input('executive', 0);
        [$from, $to, $rangeKey] = $this->resolveRange($request, $timezone);
        [$employees, $teams] = $this->filteredEmployees($team);

        $executive = $employees->first(fn (User $u) => (int) $u->id === $executiveId);
        $executiveId = $executive?->id ?? 0;
        $tableEmployees = $executive
            ? collect([$executive])
            : $employees;

        [$l30From, $l30To] = $this->summaryService->l30DateRange($timezone, $to);
        $kpiOnly = $request->boolean('kpi_only');

        $summary = $kpiOnly
            ? ['rows' => [], 'totals' => null, 'not_logged' => 0, 'total_employees' => $tableEmployees->count()]
            : $this->summaryService->teamSummary($tableEmployees, $from, $to, $timezone);

        if (! $kpiOnly && $from === $l30From && $to === $l30To) {
            $l30Totals = $summary['totals'];
        } else {
            $l30Totals = $this->summaryService->kpiTotals($tableEmployees, $l30From, $l30To, $timezone);
        }

        return [
            'rows' => $summary['rows'],
            'totals' => $summary['totals'] ?? $l30Totals,
            'l30_totals' => $l30Totals,
            'l30_from' => $l30From,
            'l30_to' => $l30To,
            'not_logged' => $summary['not_logged'],
            'total_employees' => $summary['total_employees'],
            'from' => $from,
            'to' => $to,
            'range_key' => $rangeKey,
            'team' => $team,
            'executive' => $executiveId,
            'teams' => $teams,
            'executives' => $employees->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
            ])->values(),
        ];
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
        $range = $request->input('range', 'l30');

        if ($range === 'l30') {
            [$from, $to] = $this->summaryService->l30DateRange($timezone);

            return [$from, $to, 'l30'];
        }
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
