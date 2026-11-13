<?php

namespace App\Support\Marketplace\Settle;

use App\Models\SettleTaskRun;
use App\Models\SettleVerification;
use App\Support\Marketplace\Settle\Verification\VerificationResult;
use App\Support\Marketplace\YesterdaySettleLedger as Ledger;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Per-marketplace-task tracking on top of the JSON ledger.
 *
 * The ledger (storage/app/settle_yesterday/<date>.json) stays the source of truth for which pulls
 * ran, how often they failed and when they finished; this class only READS it. Everything extra
 * (last error text, command output markers, verification results, retry estimates) lives in the
 * new settle_task_runs / settle_verifications tables.
 *
 * Task states:
 *   not_due                 no pass of this task is due yet
 *   pending                 due, not run yet
 *   retry_wait              failed, will be retried by a later orchestrator tick
 *   failed                  3 failed attempts, or still not done after 09:00 PT
 *   awaiting_verification   pulled OK (exit 0), data not verified yet
 *   verified                data checked and OK (pass / warn)
 *   limited                 data checked as far as possible (no dated source)
 *   verification_failed     pulled with exit 0 but the data is missing / incomplete / suspicious
 */
final class SettleTracker
{
    public const VERIFIED_STATES = ['verified', 'limited'];

    /**
     * Live per-task detail for $date plus the verification summary used by Ledger::status().
     * Never throws: when the tracking tables are missing, `available` is false.
     *
     * @param  array<string, mixed>  $ledger  Ledger::read($date)
     * @return array{available: bool, tasks: array<string, array<string, mixed>>, summary: array<string, mixed>}
     */
    public static function describe(string $date, array $ledger, int $hi, bool $deadlinePassed, ?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now(Ledger::TZ))->copy()->setTimezone(Ledger::TZ);
        $rows = self::loadRows($date);
        $available = $rows !== null;
        $rows ??= collect();

        $tasks = [];
        foreach (Ledger::tasks() as $key => $task) {
            $tasks[$key] = self::describeTask($date, $key, $task, $ledger['tasks'][$key] ?? ['done' => [], 'attempts' => []], $hi, $deadlinePassed, $now, $rows->get($key));
        }

        $verified = [];
        $failedVerification = [];
        $unverified = [];
        foreach ($tasks as $key => $t) {
            if (in_array($t['state'], self::VERIFIED_STATES, true)) {
                $verified[] = $key;

                continue;
            }
            $unverified[] = $key;
            if ($t['state'] === 'verification_failed') {
                $failedVerification[] = $key;
            }
        }

        return [
            'available' => $available,
            'tasks' => $tasks,
            'summary' => [
                'total' => count($tasks),
                'verified' => count($verified),
                'all_verified' => $available && $unverified === [],
                'verification_failed' => $failedVerification,
                'unverified' => $unverified,
            ],
        ];
    }

    /**
     * @param  array{command: string, lane: string, passes: list<int>, timeout: int}  $task
     * @param  array<string, mixed>  $state  ledger state of this task
     * @return array<string, mixed>
     */
    public static function describeTask(string $date, string $key, array $task, array $state, int $hi, bool $deadlinePassed, Carbon $now, ?SettleTaskRun $row): array
    {
        $needed = array_values(array_filter($task['passes'], fn ($p) => $p <= $hi));
        $done = $state['done'] ?? [];
        $attempts = $state['attempts'] ?? [];
        $max = Ledger::MAX_ATTEMPTS;

        $notDone = [];
        $failedPasses = [];
        $maxAttempts = 0;
        foreach ($needed as $p) {
            $maxAttempts = max($maxAttempts, (int) ($attempts[(string) $p] ?? 0));
            if (isset($done[(string) $p])) {
                continue;
            }
            $notDone[] = $p;
            if ((int) ($attempts[(string) $p] ?? 0) >= $max || $deadlinePassed) {
                $failedPasses[] = $p;
            }
        }

        $doneAt = null;
        foreach ($done as $ts) {
            if ($doneAt === null || strcmp((string) $ts, $doneAt) > 0) {
                $doneAt = (string) $ts;
            }
        }

        $verification = null;
        $nextRetryAt = null;
        $nextRetryNote = null;
        $lastError = null;

        if ($needed === []) {
            $stateName = 'not_due';
        } elseif ($failedPasses !== []) {
            $stateName = 'failed';
            $lastError = (string) ($state['error'] ?? '') ?: ($row?->last_error);
            $exhausted = $maxAttempts >= $max;
            $nextRetryNote = $exhausted
                ? "No more retries ({$max}/{$max} attempts used)"
                : 'No retry: past the 09:00 PT deadline';
        } elseif ($notDone !== []) {
            $stateName = $maxAttempts > 0 ? 'retry_wait' : 'pending';
            $lastError = (string) ($state['error'] ?? '') ?: ($row?->last_error);
            if ($stateName === 'retry_wait') {
                if (Ledger::pastStartCutoff($date, $now)) {
                    $nextRetryNote = 'No retry: past the 08:30 PT cutoff';
                } else {
                    $t = $now->copy();
                    $nextRetryAt = $t->addMinutes(10 - ($t->minute % 10))->setSecond(0);
                    $nextRetryNote = 'Earliest: next 10-minute tick (a running settle pass can delay it)';
                }
            } else {
                $nextRetryNote = 'Queued for the next settle tick';
            }
        } else {
            // Pulled for every due pass: the verification decides.
            $stateName = 'awaiting_verification';
            $valid = $row !== null && $row->verification_status !== null && $row->verified_for_done_at === $doneAt;
            if ($valid) {
                $verification = [
                    'status' => $row->verification_status,
                    'checks' => self::problemChecks($row->verification_checks ?? []),
                    'checked_at' => $row->verified_at?->setTimezone(Ledger::TZ)->toIso8601String(),
                ];
                $stateName = match ($row->verification_status) {
                    VerificationResult::PASS, VerificationResult::WARN => 'verified',
                    VerificationResult::LIMITED => 'limited',
                    VerificationResult::FAIL, VerificationResult::ERROR => 'verification_failed',
                    default => 'awaiting_verification',
                };
            } elseif ($row !== null && $row->verification_status !== null) {
                $verification = ['status' => 'stale', 'checks' => [], 'checked_at' => $row->verified_at?->setTimezone(Ledger::TZ)->toIso8601String()];
            }
        }

        return [
            'key' => $key,
            'marketplace' => SettleCatalog::marketplace($key),
            'kind' => SettleCatalog::kind($key),
            'lane' => $task['lane'],
            'command' => $task['command'],
            'state' => $stateName,
            'passes_needed' => $needed,
            'passes_pending' => $notDone,
            'attempts' => $maxAttempts,
            'max_attempts' => $max,
            'last_error' => $lastError !== null && $lastError !== '' ? $lastError : null,
            'error_class' => $row?->error_class,
            'last_run_at' => $row?->last_run_finished_at?->setTimezone(Ledger::TZ)->toIso8601String(),
            'duration_seconds' => $row?->duration_seconds,
            'ledger_done_at' => $doneAt,
            'next_retry_at' => $nextRetryAt?->toIso8601String(),
            'next_retry_note' => $nextRetryNote,
            'verification' => $verification,
        ];
    }

    /**
     * Mirrors the live per-task state into settle_task_runs (new table only).
     *
     * @param  array<string, array<string, mixed>>  $tasks  describe()['tasks']
     */
    public static function sync(string $date, array $tasks): void
    {
        foreach ($tasks as $key => $t) {
            $row = SettleTaskRun::query()->firstOrNew(['settle_date' => $date, 'task_key' => $key]);
            $row->fill([
                'marketplace' => $t['marketplace'],
                'kind' => $t['kind'],
                'lane' => $t['lane'],
                'command' => mb_substr((string) $t['command'], 0, 255),
                'state' => $t['state'],
                'attempts' => $t['attempts'],
                'max_attempts' => $t['max_attempts'],
                'next_retry_at' => $t['next_retry_at'] ? Carbon::parse($t['next_retry_at']) : null,
                'next_retry_note' => $t['next_retry_note'],
                'ledger_done_at' => $t['ledger_done_at'],
            ]);
            if (in_array($t['state'], ['failed', 'retry_wait'], true)) {
                $row->last_error = $t['last_error'] !== null ? mb_substr((string) $t['last_error'], 0, 2000) : $row->last_error;
            } elseif (in_array($t['state'], self::VERIFIED_STATES, true) || $t['state'] === 'awaiting_verification') {
                $row->last_error = null;
                $row->error_class = null;
            }
            $row->save();
        }
    }

    /**
     * Called by the orchestrator after every task run (ok or failed). Stores the last error, the
     * command output markers (auth / pagination / rate-limit text that did not change the exit
     * code) and timings. Failures here must never affect the settle run, so callers wrap it.
     *
     * @param  array{command: string, lane: string, passes: list<int>, timeout: int}|null  $task
     */
    public static function recordRun(string $date, string $key, ?array $task, bool $ok, string $error, string $output, int $seconds, ?int $exitCode, ?Carbon $startedAt): void
    {
        $insp = SettleOutputInspector::inspect($output, $ok ? '' : $error);
        $class = null;
        if (! $ok) {
            $class = $insp['error_class'];
            if ($class === null) {
                $class = match (true) {
                    str_contains($error, 'timed out') => 'timeout',
                    str_contains($error, 'deadline') => 'deadline',
                    default => 'exit',
                };
            }
        }

        $row = SettleTaskRun::query()->firstOrNew(['settle_date' => $date, 'task_key' => $key]);
        if (! $row->exists) {
            $row->fill([
                'marketplace' => SettleCatalog::marketplace($key),
                'kind' => SettleCatalog::kind($key),
                'lane' => $task['lane'] ?? null,
                'command' => isset($task['command']) ? mb_substr((string) $task['command'], 0, 255) : null,
                'state' => $ok ? 'awaiting_verification' : 'retry_wait',
            ]);
        }
        $row->fill([
            'last_exit_code' => $exitCode,
            'last_run_started_at' => $startedAt,
            'last_run_finished_at' => Carbon::now(Ledger::TZ),
            'duration_seconds' => $seconds,
            'last_output_tail' => $output === '' ? null : mb_substr($output, -3000),
            'output_markers' => $insp['markers'],
            'error_class' => $class,
            'last_error' => $ok ? null : mb_substr($error.($insp['markers'] !== [] ? ' — '.$insp['markers'][0]['line'] : ''), 0, 2000),
        ]);
        $row->save();
    }

    public static function saveVerification(string $date, string $key, VerificationResult $result, ?string $doneAt, int $durationMs): void
    {
        SettleVerification::query()->create([
            'settle_date' => $date,
            'task_key' => $key,
            'status' => $result->status,
            'checks' => $result->checks,
            'metrics' => $result->metrics,
            'ledger_done_at' => $doneAt,
            'duration_ms' => $durationMs,
        ]);

        $row = SettleTaskRun::query()->firstOrNew(['settle_date' => $date, 'task_key' => $key]);
        if (! $row->exists) {
            $row->fill([
                'marketplace' => SettleCatalog::marketplace($key),
                'kind' => SettleCatalog::kind($key),
                'state' => 'awaiting_verification',
            ]);
        }
        $row->fill([
            'verification_status' => $result->status,
            'verification_checks' => $result->checks,
            'verified_at' => Carbon::now(Ledger::TZ),
            'verified_for_done_at' => $doneAt,
            'ledger_done_at' => $doneAt,
            'state' => match ($result->status) {
                VerificationResult::PASS, VerificationResult::WARN => 'verified',
                VerificationResult::LIMITED => 'limited',
                VerificationResult::FAIL, VerificationResult::ERROR => 'verification_failed',
                default => 'awaiting_verification',
            },
        ]);
        $row->save();
    }

    /** Output markers stored by the latest run of the task (null when no run was recorded). */
    public static function lastMarkers(string $date, string $key): ?array
    {
        $row = SettleTaskRun::query()->where('settle_date', $date)->where('task_key', $key)->first(['output_markers']);

        return $row?->output_markers;
    }

    /**
     * @return Collection<string, SettleTaskRun>|null null when the tracking table cannot be read
     */
    private static function loadRows(string $date): ?Collection
    {
        try {
            return SettleTaskRun::query()->where('settle_date', $date)->get()->keyBy('task_key');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Only the checks worth showing (not plain passes), trimmed for the status payload.
     *
     * @param  list<array<string, mixed>>  $checks
     * @return list<array{code: string, status: string, message: string}>
     */
    private static function problemChecks(array $checks): array
    {
        $out = [];
        foreach ($checks as $c) {
            if (($c['status'] ?? 'pass') !== 'pass') {
                $out[] = ['code' => (string) $c['code'], 'status' => (string) $c['status'], 'message' => mb_substr((string) $c['message'], 0, 300)];
            }
        }

        return $out;
    }
}
