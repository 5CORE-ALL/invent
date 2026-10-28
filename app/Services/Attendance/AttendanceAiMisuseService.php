<?php

namespace App\Services\Attendance;

use App\Models\AttendanceAiFlag;
use App\Models\AttendanceDailySummary;
use App\Models\AttendancePolicy;
use App\Models\AttendanceScreenshot;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Support\OpenAiRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AttendanceAiMisuseService
{
    public function __construct(
        private readonly AttendanceAnalysisService $analysisService,
    ) {}

    public function analyzeUserDay(User $user, string $date, bool $useAi = true): array
    {
        $summary = $this->analysisService->buildDailySummary($user, $date);
        $policy = AttendancePolicy::resolveForUser($user);
        $flags = [];

        $flags = array_merge($flags, $this->checkLateStart($user, $date, $policy));
        $flags = array_merge($flags, $this->checkInsufficientHours($user, $date, $summary, $policy));
        $flags = array_merge($flags, $this->checkLowProductivity($user, $date, $summary, $policy));
        $flags = array_merge($flags, $this->checkSessionPatterns($user, $date, $policy));

        $flags = array_merge($flags, $this->checkUnproductiveApps($user, $date));

        $flags = array_merge($flags, $this->checkFraud($user, $date));

        if ($useAi && count($flags) > 0) {
            $aiFlag = $this->runAiAssessment($user, $date, $summary, $flags);
            if ($aiFlag) {
                $flags[] = $aiFlag;
            }
        }

        $riskScore = $this->computeRiskScore($flags);
        $summary->update(['ai_risk_score' => $riskScore]);

        return [
            'summary' => $summary->fresh(),
            'flags' => $flags,
            'risk_score' => $riskScore,
        ];
    }

    /**
     * @return AttendanceAiFlag[]
     */
    private function checkLateStart(User $user, string $date, ?AttendancePolicy $policy): array
    {
        if (! $policy) {
            return [];
        }

        $summary = AttendanceDailySummary::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $date)
            ->first();

        if (! $summary?->first_clock_in) {
            return [];
        }

        // Compared in the policy's own timezone (see AttendancePolicy::lateAfter), not as a bare
        // clock time against the server's Pacific time.
        $expected = $policy->lateAfter($summary->first_clock_in);

        if ($summary->first_clock_in->lte($expected)) {
            return [];
        }

        $lateMinutes = $summary->first_clock_in->diffInMinutes($expected);

        return [$this->upsertFlag($user, [
            'flag_date' => $date,
            'flag_type' => 'late_start',
            'severity' => $lateMinutes > 60 ? 'high' : ($lateMinutes > 30 ? 'medium' : 'low'),
            'title' => 'Late clock-in detected',
            'description' => "Employee clocked in {$lateMinutes} minutes after the allowed start time.",
            'evidence' => [
                'policy_timezone' => $policy->policyTimezone(),
                'expected_by' => $expected->toDateTimeString().' '.$policy->policyTimezone(),
                'actual_clock_in' => $summary->first_clock_in->copy()->timezone($policy->policyTimezone())->toDateTimeString().' '.$policy->policyTimezone(),
                'late_minutes' => $lateMinutes,
            ],
            'source' => 'rules',
        ])];
    }

    /**
     * @return AttendanceAiFlag[]
     */
    private function checkInsufficientHours(User $user, string $date, AttendanceDailySummary $summary, ?AttendancePolicy $policy): array
    {
        $minHours = (float) ($policy?->min_daily_hours ?? 8);
        $workHours = $summary->workHours();

        if ($workHours >= $minHours * 0.9) {
            return [];
        }

        return [$this->upsertFlag($user, [
            'flag_date' => $date,
            'flag_type' => 'insufficient_hours',
            'severity' => $workHours < $minHours * 0.5 ? 'high' : 'medium',
            'title' => 'Insufficient work hours',
            'description' => sprintf('Logged %.1f hours vs %.1f expected.', $workHours, $minHours),
            'evidence' => [
                'work_hours' => $workHours,
                'expected_hours' => $minHours,
                'team_logger_hours' => $summary->team_logger_hours,
            ],
            'source' => 'rules',
        ])];
    }

    /**
     * @return AttendanceAiFlag[]
     */
    private function checkLowProductivity(User $user, string $date, AttendanceDailySummary $summary, ?AttendancePolicy $policy): array
    {
        $minPct = $policy?->min_active_percent ?? 60;
        $activePct = $summary->activePercent();

        if ($activePct >= $minPct || $summary->total_work_seconds < 3600) {
            return [];
        }

        return [$this->upsertFlag($user, [
            'flag_date' => $date,
            'flag_type' => 'low_productivity',
            'severity' => $activePct < 40 ? 'high' : 'medium',
            'title' => 'Low active-time ratio',
            'description' => "Active time was {$activePct}% (minimum {$minPct}%). Possible idle misuse while WFH.",
            'evidence' => [
                'active_percent' => $activePct,
                'active_seconds' => $summary->active_seconds,
                'idle_seconds' => $summary->idle_seconds,
                'productivity_score' => $summary->productivity_score,
            ],
            'source' => 'rules',
        ])];
    }

    /**
     * @return AttendanceAiFlag[]
     */
    private function checkSessionPatterns(User $user, string $date, ?AttendancePolicy $policy): array
    {
        $flags = [];
        $maxIdlePerHour = $policy?->max_idle_minutes_per_hour ?? 15;

        $sessions = AttendanceSession::query()
            ->where('user_id', $user->id)
            ->whereDate('started_at', $date)
            ->with('activityLogs')
            ->get();

        foreach ($sessions as $session) {
            if ($session->missed_heartbeat_count > 2) {
                $flags[] = $this->upsertFlag($user, [
                    'attendance_session_id' => $session->id,
                    'flag_date' => $date,
                    'flag_type' => 'missed_heartbeats',
                    'severity' => 'medium',
                    'title' => 'Missed activity signals during session',
                    'description' => 'The work session had gaps with no activity heartbeats — possible tab minimization or leaving the desk.',
                    'evidence' => [
                        'session_id' => $session->id,
                        'missed_count' => $session->missed_heartbeat_count,
                        'status' => $session->status,
                    ],
                    'source' => 'rules',
                ]);
            }

            $hourlyIdleMinutes = [];
            foreach ($session->activityLogs as $log) {
                if (! $log->is_active) {
                    $hour = $log->recorded_at->format('H');
                    $hourlyIdleMinutes[$hour] = ($hourlyIdleMinutes[$hour] ?? 0) + 1;
                }
            }

            foreach ($hourlyIdleMinutes as $hour => $idleCount) {
                if ($idleCount > $maxIdlePerHour && $session->work_location === 'wfh') {
                    $flags[] = $this->upsertFlag($user, [
                        'attendance_session_id' => $session->id,
                        'flag_date' => $date,
                        'flag_type' => 'excessive_idle',
                        'severity' => $idleCount > $maxIdlePerHour * 2 ? 'high' : 'medium',
                        'title' => 'Excessive idle time in hour '.$hour.':00',
                        'description' => "Detected ~{$idleCount} idle minutes during the {$hour}:00 hour while working from home.",
                        'evidence' => [
                            'hour' => $hour,
                            'idle_minutes' => $idleCount,
                            'max_allowed' => $maxIdlePerHour,
                            'work_location' => $session->work_location,
                        ],
                        'source' => 'rules',
                    ]);
                }
            }

            $titles = $session->activityLogs->pluck('window_title')->filter()->countBy();
            $dominant = $titles->sortDesc()->first();
            $total = $titles->sum();
            if ($total > 10 && $dominant && ($dominant / $total) > 0.85) {
                $topTitle = $titles->sortDesc()->keys()->first();
                $flags[] = $this->upsertFlag($user, [
                    'attendance_session_id' => $session->id,
                    'flag_date' => $date,
                    'flag_type' => 'suspicious_pattern',
                    'severity' => 'low',
                    'title' => 'Repetitive window activity',
                    'description' => 'Activity logs show the same window title for most of the session — verify genuine work.',
                    'evidence' => [
                        'dominant_title' => $topTitle,
                        'dominance_ratio' => round($dominant / $total, 2),
                    ],
                    'source' => 'rules',
                ]);
            }
        }

        return $flags;
    }

    /**
     * @return AttendanceAiFlag[]
     */
    private function checkUnproductiveApps(User $user, string $date): array
    {
        $unproductive = array_map('strtolower', config('attendance.unproductive_apps', []));
        if ($unproductive === []) {
            return [];
        }

        $hits = \App\Models\AttendanceActivityLog::query()
            ->where('user_id', $user->id)
            ->whereDate('recorded_at', $date)
            ->where('source', 'desktop')
            ->whereNotNull('process_name')
            ->get()
            ->filter(fn ($log) => in_array(strtolower((string) $log->process_name), $unproductive, true))
            ->countBy(fn ($log) => strtolower((string) $log->process_name));

        if ($hits->isEmpty()) {
            return [];
        }

        $top = $hits->sortDesc()->keys()->first();
        $count = $hits->get($top, 0);

        if ($count < 3) {
            return [];
        }

        return [$this->upsertFlag($user, [
            'flag_date' => $date,
            'flag_type' => 'suspicious_pattern',
            'severity' => 'medium',
            'title' => 'Unproductive desktop app usage',
            'description' => "Detected {$count} samples of unproductive app \"{$top}\" during work hours.",
            'evidence' => ['app' => $top, 'samples' => $count, 'apps' => $hits->all()],
            'source' => 'rules',
        ])];
    }

    /**
     * Fraud checks only (no daily summary rebuild, no TeamLogger call, no AI), cheap enough for the
     * hourly checkup. Returns the flags raised or refreshed for that day.
     *
     * @return AttendanceAiFlag[]
     */
    public function checkupUserDay(User $user, string $date): array
    {
        return $this->checkFraud($user, $date);
    }

    /**
     * @return AttendanceAiFlag[]
     */
    private function checkFraud(User $user, string $date): array
    {
        $flags = array_merge(
            $this->checkFakedActivity($user, $date),
            $this->checkCaptureAndSessionIntegrity($user, $date),
        );

        $this->clearOutdatedFlags($user, $date, $flags);

        return $flags;
    }

    /**
     * Rules whose result depends only on stored heartbeat / session rows, so a re-check is a reliable
     * "does this still apply?". (The process check is left out on purpose: its evidence can age out,
     * and that must never silently clear an alert.)
     */
    private const SELF_CLEARING_TYPES = ['input_simulation', 'no_screenshots', 'marathon_session'];

    /**
     * An alert nobody has looked at yet is withdrawn when a fresh check of the same day no longer
     * raises it (e.g. the rule was tightened, or a manual entry is now recognised). Alerts a person
     * already reviewed or dismissed are never touched.
     *
     * @param  AttendanceAiFlag[]  $raised  flags this run raised or refreshed
     */
    private function clearOutdatedFlags(User $user, string $date, array $raised): void
    {
        $keep = array_map(fn (AttendanceAiFlag $f) => $f->id, $raised);

        AttendanceAiFlag::query()
            ->where('user_id', $user->id)
            ->whereDate('flag_date', $date)
            ->whereIn('flag_type', self::SELF_CLEARING_TYPES)
            ->where('source', 'rules')
            ->where('status', 'open')
            ->when($keep !== [], fn ($q) => $q->whereNotIn('id', $keep))
            ->update([
                'status' => 'dismissed',
                'reviewed_at' => now(),
                'review_notes' => 'Auto-cleared: a re-check of this day no longer raises this alert.',
            ]);
    }

    /**
     * Other ways time can be inflated or monitoring dodged: a session that never ends, screen capture
     * silently blocked while "working", or one account used from several machines.
     *
     * @return AttendanceAiFlag[]
     */
    private function checkCaptureAndSessionIntegrity(User $user, string $date): array
    {
        $cfg = (array) config('attendance.checkup', []);
        $flags = [];

        $sessions = AttendanceSession::query()
            ->where('user_id', $user->id)
            ->whereDate('started_at', $date)
            ->get();

        $marathonHours = (float) ($cfg['marathon_hours'] ?? 14);
        $minRatio = (float) ($cfg['min_screenshot_ratio'] ?? 0.4);
        $shotInterval = max(30, (int) config('attendance.screenshot_interval_seconds', 120));

        $heartbeatInterval = max(1, (int) config('attendance.heartbeat_interval_seconds', 15));

        foreach ($sessions as $session) {
            // Hours can be added or corrected by hand (manual sessions, corrected start / end / totals),
            // so those fields are never evidence of anything. Only what the desktop agent itself
            // reported counts: the time its heartbeats actually cover.
            $trackedByAgent = $session->clock_source !== 'manual' && (int) $session->heartbeat_count > 0;
            if (! $trackedByAgent) {
                continue;
            }
            $coveredSeconds = (int) $session->heartbeat_count * $heartbeatInterval;
            $coveredHours = $coveredSeconds / 3600;

            // The agent was sending heartbeats for the whole stretch, with no break.
            if ($coveredHours >= $marathonHours && (int) $session->total_break_seconds < 3600) {
                $flags[] = $this->upsertFlag($user, [
                    'attendance_session_id' => $session->id,
                    'flag_date' => $date,
                    'flag_type' => 'marathon_session',
                    'severity' => $coveredHours >= $marathonHours + 6 ? 'high' : 'medium',
                    'title' => 'Agent reported activity for '.round($coveredHours, 1).'h with almost no break',
                    'description' => 'The desktop agent sent heartbeats covering '.round($coveredHours, 1).'h in one unbroken session. The machine may have been left running or the person never clocked out, which inflates hours.',
                    'evidence' => [
                        'session_id' => $session->id,
                        'heartbeat_covered_hours' => round($coveredHours, 2),
                        'break_seconds' => (int) $session->total_break_seconds,
                    ],
                    'source' => 'rules',
                ]);
            }

            if (config('attendance.screenshots_enabled', true)) {
                // Expected captures follow the time the agent really tracked, not the recorded total,
                // which a manual correction can raise.
                $expected = intdiv(min((int) $session->total_active_seconds, $coveredSeconds), $shotInterval);
                if ($expected >= 20) {
                    $actual = AttendanceScreenshot::query()->where('attendance_session_id', $session->id)->count();
                    if ($actual < $expected * $minRatio) {
                        $flags[] = $this->upsertFlag($user, [
                            'attendance_session_id' => $session->id,
                            'flag_date' => $date,
                            'flag_type' => 'no_screenshots',
                            'severity' => $actual < $expected * 0.1 ? 'high' : 'medium',
                            'title' => 'Screen capture missing while counted active',
                            'description' => "Only {$actual} screenshots for about {$expected} expected. Screen capture may be blocked, denied or tampered with.",
                            'evidence' => [
                                'session_id' => $session->id,
                                'screenshots' => $actual,
                                'expected' => $expected,
                            ],
                            'source' => 'rules',
                        ]);
                    }
                }
            }
        }

        $windowDays = max(1, (int) ($cfg['multi_device_days'] ?? 7));
        $minDevices = max(2, (int) ($cfg['multi_device_min'] ?? 3));
        $deviceCount = AttendanceSession::query()
            ->where('user_id', $user->id)
            ->whereNotNull('attendance_device_id')
            ->whereDate('started_at', '<=', $date)
            ->whereDate('started_at', '>', Carbon::parse($date)->subDays($windowDays)->toDateString())
            ->distinct()
            ->count('attendance_device_id');

        // This signal spans several days, so a person's review of it covers the whole window
        // instead of being re-raised under tomorrow's date.
        $alreadyTriaged = AttendanceAiFlag::query()
            ->where('user_id', $user->id)
            ->where('flag_type', 'multi_device')
            ->where('status', '!=', 'open')
            ->whereDate('flag_date', '>', Carbon::parse($date)->subDays($windowDays)->toDateString())
            ->exists();

        if ($deviceCount >= $minDevices && $sessions->isNotEmpty() && ! $alreadyTriaged) {
            $flags[] = $this->upsertFlag($user, [
                'flag_date' => $date,
                'flag_type' => 'multi_device',
                'severity' => $deviceCount > $minDevices ? 'medium' : 'low',
                'title' => "Account used from {$deviceCount} machines in {$windowDays} days",
                'description' => "{$deviceCount} different computers clocked in on this account within {$windowDays} days — possible account sharing or someone else working under this login.",
                'evidence' => ['distinct_devices' => $deviceCount, 'window_days' => $windowDays],
                'source' => 'rules',
            ]);
        }

        return $flags;
    }

    /**
     * Detects "active" time that is probably produced by a mouse jiggler, auto-clicker, keep-awake
     * tool or remote control rather than a person: the existing idle rules cannot see this because
     * such a machine never goes idle.
     *
     * @return AttendanceAiFlag[]
     */
    private function checkFakedActivity(User $user, string $date): array
    {
        $sessions = AttendanceSession::query()
            ->where('user_id', $user->id)
            ->whereDate('started_at', $date)
            ->with('activityLogs')
            ->get();

        $flags = [];
        foreach ($sessions as $session) {
            $flags = array_merge(
                $flags,
                $this->checkInputSimulation($user, $date, $session),
            );
        }

        return $flags;
    }

    /**
     * @return AttendanceAiFlag[]
     */
    private function checkInputSimulation(User $user, string $date, AttendanceSession $session): array
    {
        $cfg = (array) config('attendance.simulation', []);
        $interval = max(1, (int) config('attendance.heartbeat_interval_seconds', 15));

        $logs = $session->activityLogs
            ->where('source', 'desktop')
            ->where('activity_state', '!=', 'break')
            ->sortBy('recorded_at')
            ->values();

        $hours = ($logs->count() * $interval) / 3600;
        if ($hours < (float) ($cfg['min_hours'] ?? 2)) {
            return [];
        }

        $idleShare = round(100 * $logs->where('activity_state', 'idle')->count() / $logs->count(), 2);
        $maxOsIdle = (int) $logs->max('idle_seconds');
        // "Never marked idle" alone proves nothing: most honest people never reach the idle threshold
        // either (checked on live data: 13 of 18 employees on one day). What a person does and an input
        // simulator does not is pause for a minute or more at some point in a multi-hour session, so
        // the longest OS-idle reading must also stay under a short ceiling.
        $ceiling = (int) ($cfg['max_os_idle_ceiling_seconds'] ?? 65);
        $lowIdle = $idleShare < (float) ($cfg['min_idle_share_percent'] ?? 2) && $maxOsIdle <= $ceiling;

        // Longest unbroken stretch on the same app + window while counted as working.
        $run = 0;
        $longestRun = 0;
        $runTitle = null;
        $longestTitle = null;
        foreach ($logs as $log) {
            $key = strtolower(trim((string) ($log->app_name ?: $log->process_name).'|'.(string) $log->window_title));
            if ($key !== '|' && $log->activity_state === 'working' && $key === $runTitle) {
                $run++;
            } else {
                $run = ($key !== '|' && $log->activity_state === 'working') ? 1 : 0;
                $runTitle = $run > 0 ? $key : null;
            }
            if ($run > $longestRun) {
                $longestRun = $run;
                $longestTitle = $runTitle;
            }
        }
        $staticTitleMinutes = (int) round(($longestRun * $interval) / 60);
        $staticTitle = $staticTitleMinutes >= (int) ($cfg['static_title_minutes'] ?? 90);

        // Real keyboard / click counts. Only heartbeats where the agent's input hook was verified
        // working are judged, so old agents, or a Mac without Accessibility permission, are never
        // mistaken for "typed nothing".
        $tracked = $logs->where('input_tracked', true)->where('activity_state', 'working');
        $trackedHours = ($tracked->count() * $interval) / 3600;
        $keystrokes = (int) $tracked->sum('keystroke_count');
        $clicks = (int) $tracked->sum('mouse_click_count');
        $inputsPerHour = $trackedHours > 0 ? round(($keystrokes + $clicks) / $trackedHours, 1) : null;
        $noRealInput = $trackedHours >= (float) ($cfg['min_hours'] ?? 2)
            && $inputsPerHour < (float) ($cfg['min_inputs_per_hour'] ?? 30);

        if (! $lowIdle && ! $noRealInput) {
            return [];
        }

        $signals = [];
        if ($lowIdle) {
            $signals[] = "only {$idleShare}% of ".round($hours, 1)."h of heartbeats were idle and the longest pause without input was {$maxOsIdle}s (a person normally pauses for more than a minute at some point)";
        }
        if ($noRealInput) {
            $signals[] = "{$keystrokes} keystrokes and {$clicks} clicks in ".round($trackedHours, 1)."h of 'working' time ({$inputsPerHour} inputs/hour)";
        }
        if ($staticTitle) {
            $signals[] = "same window for {$staticTitleMinutes} min";
        }

        return [$this->upsertFlag($user, [
            'attendance_session_id' => $session->id,
            'flag_date' => $date,
            'flag_type' => 'input_simulation',
            'severity' => count($signals) >= 2 ? 'high' : 'medium',
            'title' => 'Activity looks machine-generated',
            'description' => 'Counted as active for '.round($hours, 1).'h but: '.implode('; ', $signals)
                .'. A mouse jiggler, auto-clicker, keep-awake tool or remote control can cause this — review screenshots.',
            'evidence' => [
                'session_id' => $session->id,
                'hours_sampled' => round($hours, 2),
                'idle_share_percent' => $idleShare,
                'max_os_idle_seconds' => $maxOsIdle,
                'longest_same_window_minutes' => $staticTitleMinutes,
                'longest_same_window' => $longestTitle,
                'input_tracked_hours' => round($trackedHours, 2),
                'keystrokes' => $keystrokes,
                'mouse_clicks' => $clicks,
                'inputs_per_hour' => $inputsPerHour,
            ],
            'source' => 'rules',
        ])];
    }

    /**
     * @param  AttendanceAiFlag[]  $existingFlags
     */
    private function runAiAssessment(User $user, string $date, AttendanceDailySummary $summary, array $existingFlags): ?AttendanceAiFlag
    {
        $headers = OpenAiRequest::authHeaders();
        if ($headers === []) {
            return null;
        }

        $flagSummary = collect($existingFlags)->map(fn (AttendanceAiFlag $f) => [
            'type' => $f->flag_type,
            'severity' => $f->severity,
            'title' => $f->title,
        ])->values()->all();

        $payload = [
            'employee' => $user->name,
            'designation' => $user->designation,
            'date' => $date,
            'work_hours' => $summary->workHours(),
            'active_percent' => $summary->activePercent(),
            'productivity_score' => $summary->productivity_score,
            'team_logger_hours' => $summary->team_logger_hours,
            'top_activities' => $summary->top_activities,
            'rule_flags' => $flagSummary,
            'work_location' => 'wfh',
        ];

        $system = 'You are an HR analytics AI that detects work-from-home misuse and productivity risks. '
            .'Analyze the employee activity summary and rule-based flags. Return ONLY valid JSON: '
            .'{"risk_score":0-100,"severity":"low|medium|high","summary":"2-3 sentence assessment",'
            .'"misuse_indicators":["string"],"recommendations":["string"],"confidence":0.0-1.0}';

        try {
            $response = Http::withHeaders($headers)
                ->timeout(45)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('attendance.ai_model', 'gpt-4o-mini'),
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => json_encode($payload)],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Attendance AI assessment failed', ['status' => $response->status()]);

                return null;
            }

            $text = (string) ($response->json('choices.0.message.content') ?? '');
            $parsed = json_decode($text, true);
            if (! is_array($parsed)) {
                return null;
            }

            return $this->upsertFlag($user, [
                'flag_date' => $date,
                'flag_type' => 'ai_assessment',
                'severity' => in_array($parsed['severity'] ?? '', ['low', 'medium', 'high'], true)
                    ? $parsed['severity'] : 'medium',
                'title' => 'AI WFH risk assessment',
                'description' => (string) ($parsed['summary'] ?? 'AI completed a risk review.'),
                'evidence' => [
                    'misuse_indicators' => $parsed['misuse_indicators'] ?? [],
                    'recommendations' => $parsed['recommendations'] ?? [],
                    'risk_score' => $parsed['risk_score'] ?? null,
                ],
                'ai_confidence' => isset($parsed['confidence']) ? round((float) $parsed['confidence'] * 100, 2) : null,
                'source' => 'ai',
            ]);
        } catch (\Throwable $e) {
            Log::error('Attendance AI assessment exception', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  AttendanceAiFlag[]  $flags
     */
    private function computeRiskScore(array $flags): int
    {
        if ($flags === []) {
            return 0;
        }

        $score = 0;
        foreach ($flags as $flag) {
            $weight = match ($flag->severity) {
                'high' => 30,
                'medium' => 18,
                default => 8,
            };
            $score += $weight;
        }

        return min(100, $score);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertFlag(User $user, array $data): AttendanceAiFlag
    {
        $query = AttendanceAiFlag::query()
            ->where('user_id', $user->id)
            ->where('flag_type', $data['flag_type'])
            ->where('attendance_session_id', $data['attendance_session_id'] ?? null);

        // Compare by calendar date so the lookup is identical whatever the engine stores for the cast.
        if (! empty($data['flag_date'])) {
            $query->whereDate('flag_date', $data['flag_date']);
        } else {
            $query->whereNull('flag_date');
        }

        $flag = $query->orderBy('id')->first() ?? new AttendanceAiFlag([
            'user_id' => $user->id,
            'flag_date' => $data['flag_date'] ?? null,
            'flag_type' => $data['flag_type'],
            'attendance_session_id' => $data['attendance_session_id'] ?? null,
        ]);

        // A flag a person already reviewed or dismissed must stay closed when the analysis is
        // re-run (the checkup runs hourly); only its evidence is refreshed.
        if ($flag->exists && $flag->status !== 'open') {
            $flag->fill(array_diff_key($data, ['status' => true]))->save();

            return $flag;
        }

        $flag->fill(array_merge($data, ['status' => 'open']))->save();

        return $flag;
    }

    public function analyzeAllForDate(string $date): int
    {
        $count = 0;
        $users = User::query()
            ->where('is_active', true)
            ->where('email', 'like', '%'.config('attendance.internal_email_domain', '@5core.com'))
            ->get();

        foreach ($users as $user) {
            $this->analyzeUserDay($user, $date);
            $count++;
        }

        return $count;
    }
}
