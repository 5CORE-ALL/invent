<?php

namespace App\Services\Attendance;

use App\Models\AttendanceAiFlag;
use App\Models\AttendanceDailySummary;
use App\Models\AttendancePolicy;
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

        $expected = Carbon::parse($date.' '.Carbon::parse($policy->expected_start)->format('H:i:s'))
            ->addMinutes($policy->grace_minutes);

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
                'expected_by' => $expected->toDateTimeString(),
                'actual_clock_in' => $summary->first_clock_in->toDateTimeString(),
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
        return AttendanceAiFlag::updateOrCreate(
            [
                'user_id' => $user->id,
                'flag_date' => $data['flag_date'] ?? null,
                'flag_type' => $data['flag_type'],
                'attendance_session_id' => $data['attendance_session_id'] ?? null,
            ],
            array_merge($data, ['status' => 'open'])
        );
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
