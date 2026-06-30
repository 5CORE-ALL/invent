<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Attendance\AttendancePayrollService;
use App\Services\Attendance\AttendanceService;
use App\Support\AttendanceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendancePayrollController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly AttendancePayrollService $payrollService,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        abort_unless(AttendanceAccess::canMonitor(), 403);

        $timezone = $request->input('timezone', config('attendance.timeline_timezone', 'Asia/Kolkata'));
        $dayReset = $request->input('day_reset', config('attendance.timeline_day_reset', '04:00'));
        $team = $request->input('team', 'all');

        [$defaultFrom, $defaultTo] = $this->payrollService->defaultDateRange($timezone);
        $from = $request->input('from', $defaultFrom);
        $to = $request->input('to', $defaultTo);

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $viewableIds = AttendanceAccess::viewableUserIds();
        $employees = $this->attendanceService->monitorableEmployees($viewableIds);

        if ($team !== 'all') {
            $employees = $employees->filter(fn (User $u) => (string) $u->designation === $team)->values();
        }

        $teams = $this->attendanceService->monitorableEmployees($viewableIds)
            ->pluck('designation')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $rows = $this->payrollService->buildTeamPayroll($employees, $from, $to, $timezone);

        return view('attendance.payroll', [
            'title' => 'Generate Payroll',
            'from' => $from,
            'to' => $to,
            'team' => $team,
            'timezone' => $timezone,
            'day_reset' => $dayReset,
            'teams' => $teams,
            'rows' => $rows,
            'currencies' => ['USD', 'INR', 'EUR', 'GBP'],
        ]);
    }

    public function saveLine(Request $request, User $user): JsonResponse
    {
        abort_unless(AttendanceAccess::canMonitor(), 403);
        abort_unless(AttendanceAccess::canViewUser($user->id), 403);

        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
            'manual_time' => 'nullable|string|max:32',
            'hourly_rate' => 'nullable|numeric|min:0|max:999999',
            'currency' => 'nullable|string|size:3',
            'adjustment' => 'nullable|numeric|min:-999999|max:999999',
        ]);

        $manualSeconds = $this->payrollService->parseManualDuration((string) ($validated['manual_time'] ?? ''));

        $line = $this->payrollService->saveLine(
            $user,
            $validated['from'],
            $validated['to'],
            $manualSeconds,
            (float) ($validated['hourly_rate'] ?? 0),
            (string) ($validated['currency'] ?? 'USD'),
            (float) ($validated['adjustment'] ?? 0),
            $request->user()->id,
        );

        $rows = $this->payrollService->buildTeamPayroll(
            collect([$user]),
            $validated['from'],
            $validated['to'],
            $request->input('timezone', config('attendance.timeline_timezone', 'Asia/Kolkata')),
        );

        $row = $rows[0] ?? null;

        return response()->json([
            'ok' => true,
            'row' => $row,
            'manual_seconds' => $line->manual_seconds,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(AttendanceAccess::canMonitor(), 403);

        $timezone = $request->input('timezone', config('attendance.timeline_timezone', 'Asia/Kolkata'));
        $team = $request->input('team', 'all');
        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $viewableIds = AttendanceAccess::viewableUserIds();
        $employees = $this->attendanceService->monitorableEmployees($viewableIds);

        if ($team !== 'all') {
            $employees = $employees->filter(fn (User $u) => (string) $u->designation === $team)->values();
        }

        $rows = $this->payrollService->buildTeamPayroll($employees, $from, $to, $timezone);
        $csv = $this->payrollService->toCsv($rows, $from, $to, $team);
        $filename = 'attendance-payroll_'.$from.'_'.$to.'.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
