<?php

namespace App\Services\Attendance;

use App\Models\AttendanceActivityLog;
use App\Models\AttendanceDailySummary;
use App\Models\AttendanceSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceTimelineService
{
  private const WINDOW_SECONDS = 86400;

  /**
   * @param  Collection<int, User>  $employees
   * @return array{
   *   day_start: string,
   *   day_end: string,
   *   timezone: string,
   *   day_reset: string,
   *   axis_hours: array<int, string>,
   *   rows: array<int, array<string, mixed>>
   * }
   */
  public function teamTimeline(Collection $employees, string $date, ?string $timezone = null, ?string $dayReset = null): array
  {
    $timezone = $timezone ?: (string) config('attendance.timeline_timezone', 'Asia/Kolkata');
    $dayReset = $dayReset ?: (string) config('attendance.timeline_day_reset', '04:00');

    [$dayStart, $dayEnd] = $this->dayWindow($date, $timezone, $dayReset);
    [$segStart, $segEnd] = $this->calendarWindow($date, $timezone);
    $userIds = $employees->pluck('id')->all();

    if ($userIds === []) {
      return $this->emptyPayload($dayStart, $dayEnd, $timezone, $dayReset);
    }

    $sessionsByUser = $this->sessionsInWindow($userIds, $segStart, $segEnd)->groupBy('user_id');
    $sessionIds = $sessionsByUser->flatten(1)->pluck('id')->all();

    $logsByUser = collect();
    if ($sessionIds !== []) {
      $logsByUser = AttendanceActivityLog::query()
        ->whereIn('attendance_session_id', $sessionIds)
        ->whereBetween('recorded_at', [$segStart, $segEnd])
        ->orderBy('recorded_at')
        ->get()
        ->groupBy('user_id');
    }

    $summaries = AttendanceDailySummary::query()
      ->whereIn('user_id', $userIds)
      ->whereDate('work_date', $date)
      ->get()
      ->keyBy('user_id');

    $heartbeatInterval = max(1, (int) config('attendance.heartbeat_interval_seconds', 15));
    $now = now()->timezone($timezone);

    $rows = [];
    foreach ($employees as $employee) {
      $userSessions = $sessionsByUser->get($employee->id, collect());
      $userLogs = $logsByUser->get($employee->id, collect());
      $activeSession = $userSessions->first(fn (AttendanceSession $s) => $s->isActive());
      $summary = $summaries->get($employee->id);

      $stats = $this->rowStats($userSessions, $summary, $activeSession, $now);

      $segments = $this->buildDisplaySegments(
        $userSessions,
        $userLogs,
        $segStart,
        $segEnd,
        $activeSession,
        $now,
        $heartbeatInterval,
      );

      $rows[] = [
        'user_id' => $employee->id,
        'name' => $employee->name,
        'email' => $employee->email,
        'designation' => $employee->designation,
        'segments' => $segments,
        'stats' => $stats,
        'is_live' => (bool) $activeSession,
        'live_state' => $activeSession
          ? ($activeSession->last_activity_state ?? ($activeSession->status === 'paused' ? 'break' : 'working'))
          : null,
        'detail_url' => route('attendance.employee', $employee).'?date='.$date,
      ];
    }

    return [
      'day_start' => $dayStart->toIso8601String(),
      'day_end' => $dayEnd->toIso8601String(),
      'timezone' => $timezone,
      'day_reset' => $dayReset,
      'axis_hours' => $this->axisLabels($dayStart),
      'rows' => $rows,
    ];
  }

  /**
   * @return array{0: Carbon, 1: Carbon}
   */
  public function dayWindow(string $date, ?string $timezone = null, ?string $dayReset = null): array
  {
    $timezone = $timezone ?: (string) config('attendance.timeline_timezone', 'Asia/Kolkata');
    $dayReset = $dayReset ?: (string) config('attendance.timeline_day_reset', '04:00');

    $dayStart = Carbon::parse($date.' '.$dayReset, $timezone);
    $dayEnd = $dayStart->copy()->addDay();

    return [$dayStart, $dayEnd];
  }

  /**
   * @return array{0: Carbon, 1: Carbon}
   */
  public function calendarWindow(string $date, ?string $timezone = null): array
  {
    $timezone = $timezone ?: (string) config('attendance.timeline_timezone', 'Asia/Kolkata');
    $start = Carbon::parse($date, $timezone)->startOfDay();
    $end = $start->copy()->endOfDay();

    return [$start, $end];
  }

  /**
   * @param  array<int, int>  $userIds
   * @return Collection<int, AttendanceSession>
   */
  private function sessionsInWindow(array $userIds, Carbon $dayStart, Carbon $dayEnd): Collection
  {
    return AttendanceSession::query()
      ->whereIn('user_id', $userIds)
      ->where('started_at', '<', $dayEnd)
      ->where(function ($q) use ($dayStart) {
        $q->whereNull('ended_at')
          ->orWhere('ended_at', '>', $dayStart);
      })
      ->orderBy('started_at')
      ->get();
  }

  /**
   * Visible timeline: session blocks (working/break) plus idle overlays from heartbeats.
   *
   * @param  Collection<int, AttendanceSession>  $userSessions
   * @param  Collection<int, AttendanceActivityLog>  $userLogs
   * @return array<int, array<string, mixed>>
   */
  private function buildDisplaySegments(
    Collection $userSessions,
    Collection $userLogs,
    Carbon $dayStart,
    Carbon $dayEnd,
    ?AttendanceSession $activeSession,
    Carbon $now,
    int $heartbeatInterval,
  ): array {
    $base = $this->segmentsFromSessions($userSessions, $dayStart, $dayEnd, $now);

    if ($userLogs->isEmpty()) {
      return $base;
    }

    $fromLogs = $this->segmentsFromLogsBucketed(
      $userLogs,
      $dayStart,
      $dayEnd,
      $activeSession,
      $now,
      60,
    );

    $logWidth = array_sum(array_column($fromLogs, 'width_pct'));
    if ($logWidth >= 2.0) {
      return $fromLogs;
    }

    $overlays = array_values(array_filter(
      $fromLogs,
      fn (array $segment) => in_array($segment['state'], ['idle', 'break'], true),
    ));

    return array_merge($base, $this->mergeAdjacent($overlays));
  }

  /**
   * Bucket heartbeats into 1-minute slices so bars are wide enough to see.
   *
   * @param  Collection<int, AttendanceActivityLog>  $logs
   * @return array<int, array<string, mixed>>
   */
  private function segmentsFromLogsBucketed(
    Collection $logs,
    Carbon $dayStart,
    Carbon $dayEnd,
    ?AttendanceSession $activeSession,
    Carbon $now,
    int $bucketSeconds = 60,
  ): array {
    $cap = $now->lt($dayEnd) ? $now : $dayEnd;
    $buckets = [];

    foreach ($logs as $log) {
      $at = $log->recorded_at->copy();
      if ($at->lt($dayStart) || $at->gt($dayEnd)) {
        continue;
      }

      $offset = max(0, $at->getTimestamp() - $dayStart->getTimestamp());
      $key = intdiv($offset, $bucketSeconds);
      $buckets[$key] = $this->resolveState($log);
    }

    if ($activeSession && $logs->isNotEmpty()) {
      $lastLog = $logs->last()->recorded_at->copy();
      $cursor = $lastLog->copy()->addSeconds($bucketSeconds);
      $state = $activeSession->status === 'paused'
        ? 'break'
        : ($activeSession->last_activity_state ?? 'working');

      while ($cursor->lt($cap)) {
        $offset = max(0, $cursor->getTimestamp() - $dayStart->getTimestamp());
        $key = intdiv($offset, $bucketSeconds);
        $buckets[$key] = in_array($state, ['idle', 'break'], true) ? $state : 'working';
        $cursor->addSeconds($bucketSeconds);
      }
    }

    if ($buckets === []) {
      return [];
    }

    ksort($buckets);
    $segments = [];

    foreach ($buckets as $key => $state) {
      $start = $dayStart->copy()->addSeconds($key * $bucketSeconds);
      $end = $dayStart->copy()->addSeconds(($key + 1) * $bucketSeconds)->min($dayEnd)->min($cap);

      if ($end->lte($start)) {
        continue;
      }

      $segments[] = $this->makeSegment($start, $end, $state, $dayStart);
    }

    return $this->mergeAdjacent($segments);
  }

  /**
   * @param  Collection<int, AttendanceSession>  $sessions
   * @return array<int, array<string, mixed>>
   */
  private function segmentsFromSessions(Collection $sessions, Carbon $dayStart, Carbon $dayEnd, Carbon $now): array
  {
    $segments = [];
    $cap = $now->lt($dayEnd) ? $now : $dayEnd;

    foreach ($sessions as $session) {
      $start = $session->started_at->copy()->max($dayStart);
      $end = ($session->ended_at ?? ($session->isActive() ? $cap : $session->started_at))->copy()->min($dayEnd);

      if ($end->lte($start)) {
        continue;
      }

      $state = $session->status === 'paused' ? 'break' : 'working';
      $segments[] = $this->makeSegment($start, $end, $state, $dayStart);
    }

    return $this->mergeAdjacent($segments);
  }

  private function resolveState(AttendanceActivityLog $log): string
  {
    $state = $log->activity_state;
    if (in_array($state, ['working', 'idle', 'break'], true)) {
      return $state;
    }

    return $log->is_active ? 'working' : 'idle';
  }

  /**
   * @return array{state: string, start_pct: float, width_pct: float, start_label: string, end_label: string}
   */
  private function makeSegment(Carbon $start, Carbon $end, string $state, Carbon $dayStart): array
  {
    $start = $start->copy();
    $end = $end->copy();
    $dayStart = $dayStart->copy();

    $offset = max(0, $start->getTimestamp() - $dayStart->getTimestamp());
    $duration = max(1, $end->getTimestamp() - $start->getTimestamp());

    return [
      'state' => $state,
      'color' => $this->stateColor($state),
      'start_pct' => round(($offset / self::WINDOW_SECONDS) * 100, 3),
      'width_pct' => round(($duration / self::WINDOW_SECONDS) * 100, 3),
      'start_label' => $start->format('h:i A'),
      'end_label' => $end->format('h:i A'),
    ];
  }

  private function stateColor(string $state): string
  {
    return match ($state) {
      'idle' => '#ef4444',
      'break' => '#94a3b8',
      default => '#22c55e',
    };
  }

  /**
   * @param  array<int, array<string, mixed>>  $segments
   * @return array<int, array<string, mixed>>
   */
  private function mergeAdjacent(array $segments): array
  {
    if ($segments === []) {
      return [];
    }

    $merged = [$segments[0]];

    for ($i = 1; $i < count($segments); $i++) {
      $prev = &$merged[count($merged) - 1];
      $cur = $segments[$i];
      $gap = $cur['start_pct'] - ($prev['start_pct'] + $prev['width_pct']);

      if ($prev['state'] === $cur['state'] && $gap <= 0.15) {
        if ($gap > 0) {
          $prev['width_pct'] = round($prev['width_pct'] + $gap, 3);
        }
        $prev['width_pct'] = round($prev['width_pct'] + $cur['width_pct'], 3);
        $prev['end_label'] = $cur['end_label'];
      } else {
        $merged[] = $cur;
      }
    }

    return $merged;
  }

  /**
   * @param  Collection<int, AttendanceSession>  $sessions
   * @return array<string, int|float|null>
   */
  private function rowStats(
    Collection $sessions,
    ?AttendanceDailySummary $summary,
    ?AttendanceSession $activeSession,
    Carbon $now,
  ): array {
    $active = (int) $sessions->sum('total_active_seconds');
    $idle = (int) $sessions->sum('total_idle_seconds');
    $break = (int) $sessions->sum(fn (AttendanceSession $s) => (int) ($s->total_break_seconds ?? 0));

    if ($activeSession?->status === 'paused' && $activeSession->paused_at) {
      $break += max(0, $activeSession->paused_at->diffInSeconds($now));
    }

    if ($summary) {
      $active = max($active, (int) $summary->active_seconds);
      $idle = max($idle, (int) $summary->idle_seconds);
    }

    $worked = $active;
    $total = $worked + $idle + $break;
    $activePercent = ($worked + $idle) > 0 ? (int) round(($worked / ($worked + $idle)) * 100) : 0;

    return [
      'worked_seconds' => $worked,
      'idle_seconds' => $idle,
      'break_seconds' => $break,
      'total_seconds' => $total,
      'worked_label' => $this->formatDuration($worked),
      'idle_label' => $this->formatDuration($idle),
      'break_label' => $this->formatDuration($break),
      'total_label' => $this->formatDuration($total),
      'active_percent' => $activePercent,
      'productivity_score' => $summary?->productivity_score,
      'status' => $summary?->status,
    ];
  }

  private function formatDuration(int $seconds): string
  {
    $seconds = max(0, $seconds);
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);

    return sprintf('%dh %d', $h, $m);
  }

  /**
   * @return array<int, string>
   */
  private function axisLabels(Carbon $dayStart): array
  {
    $labels = [];
    for ($h = 0; $h < 24; $h += 4) {
      $labels[] = $dayStart->copy()->addHours($h)->format('H:i');
    }

    return $labels;
  }

  /**
   * @return array<string, mixed>
   */
  private function emptyPayload(Carbon $dayStart, Carbon $dayEnd, string $timezone, string $dayReset): array
  {
    return [
      'day_start' => $dayStart->toIso8601String(),
      'day_end' => $dayEnd->toIso8601String(),
      'timezone' => $timezone,
      'day_reset' => $dayReset,
      'axis_hours' => $this->axisLabels($dayStart),
      'rows' => [],
    ];
  }
}
