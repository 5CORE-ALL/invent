<?php

namespace App\Support\Marketplace\Settle;

use App\Support\Marketplace\Settle\Verification\VerificationContext;
use App\Support\Marketplace\Settle\Verification\VerificationResult;
use App\Support\Marketplace\Settle\Verification\VerifierRegistry;
use App\Support\Marketplace\YesterdaySettleLedger as Ledger;
use Carbon\Carbon;

/**
 * Verifies, per marketplace task, that a pull which exited 0 really stored complete data, then
 * raises alerts. Strictly read-only for marketplace data: it reads the ledger and the existing
 * order / ads / views tables and writes only to the new settle_* tables.
 *
 * A task is verified for a given ledger "done" timestamp. A newer pull invalidates the old result
 * and the task is verified again. "Settled" (Ledger::status) needs every task verified.
 */
final class SettleVerifier
{
    /** Check codes that mean "data is missing" (alert type missing_data). */
    private const MISSING_CODES = ['records_present', 'ads_all_zero', 'table_not_empty', 'views_missing', 'calculated_rows', 'order_items', 'table_shrunk'];

    public function __construct(private ?SettleAlerter $alerter = null)
    {
        $this->alerter ??= new SettleAlerter;
    }

    /**
     * @param  list<string>  $only  limit to these task keys
     * @return array<string, mixed>
     */
    public function run(string $date, bool $force = false, array $only = [], bool $alerts = true, ?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now(Ledger::TZ))->copy()->setTimezone(Ledger::TZ);
        $ledger = Ledger::read($date);
        $hi = Ledger::highestDuePass($date, $now);
        $deadline = Ledger::pastHardStop($date, $now);
        $desc = SettleTracker::describe($date, $ledger, $hi, $deadline, $now);

        if (! $desc['available']) {
            return ['date' => $date, 'available' => false, 'verified' => [], 'results' => [], 'alerts' => [], 'message' => 'settle_* tracking tables are not available (run the migration).'];
        }

        SettleTracker::sync($date, $desc['tasks']);
        // Re-read the synced rows so state reflects what was just stored.
        $desc = SettleTracker::describe($date, $ledger, $hi, $deadline, $now);

        $allTasks = Ledger::tasks();
        $lastPass = count(Ledger::PASS_OFFSET_MINUTES) - 1;
        $finalPassDone = $hi === $lastPass && $this->allDone($desc['tasks'], fn ($k, $t) => in_array($lastPass, $allTasks[$k]['passes'], true));
        $taskDone = fn (string $k): bool => isset($desc['tasks'][$k]) && $desc['tasks'][$k]['passes_needed'] !== [] && $desc['tasks'][$k]['passes_pending'] === [];
        $reverifyMinutes = (int) config('settle.reverify_after_minutes', 10);

        $results = [];
        foreach ($desc['tasks'] as $key => $t) {
            if ($only !== [] && ! in_array($key, $only, true)) {
                continue;
            }
            if (! $taskDone($key)) {
                continue; // still pending / failed: nothing to verify yet
            }
            if (! $force && ! $this->needsVerification($t, $now, $reverifyMinutes)) {
                continue;
            }

            $ctx = new VerificationContext($key, $allTasks[$key], $finalPassDone, $t['ledger_done_at'], $now, $taskDone);
            $started = microtime(true);
            try {
                $result = VerifierRegistry::for($key)->verify($date, $ctx);
            } catch (\Throwable $e) {
                $result = VerificationResult::error('verifier_exception', 'Verification crashed: '.mb_substr($e->getMessage(), 0, 300));
            }
            $result = $result->withChecks(SettleOutputInspector::checks(SettleTracker::lastMarkers($date, $key)));
            SettleTracker::saveVerification($date, $key, $result, $t['ledger_done_at'], (int) round((microtime(true) - $started) * 1000));
            $results[$key] = $result;
        }

        $final = SettleTracker::describe($date, $ledger, $hi, $deadline, $now);
        $events = $this->events($date, $final['tasks'], $results);
        $sent = ($alerts && config('settle.alerts_enabled', true)) ? $this->alerter->dispatch($date, $events, $now) : [];

        return [
            'date' => $date,
            'available' => true,
            'summary' => $final['summary'],
            'results' => array_map(fn (VerificationResult $r) => $r->toArray(), $results),
            'tasks' => $final['tasks'],
            'alerts' => $sent,
            'events' => count($events),
        ];
    }

    /**
     * @param  array<string, mixed>  $t
     */
    private function needsVerification(array $t, Carbon $now, int $reverifyMinutes): bool
    {
        if ($t['state'] === 'awaiting_verification') {
            return true; // never verified for this pull (or only "pending" so far)
        }
        if ($t['state'] === 'verification_failed') {
            $at = $t['verification']['checked_at'] ?? null;

            return $at === null || Carbon::parse($at)->lte($now->copy()->subMinutes($reverifyMinutes));
        }

        return false; // verified / limited for the current pull
    }

    /**
     * @param  array<string, array<string, mixed>>  $tasks
     * @param  callable(string, array<string, mixed>): bool  $filter
     */
    private function allDone(array $tasks, callable $filter): bool
    {
        foreach ($tasks as $k => $t) {
            if ($filter($k, $t) && ($t['passes_needed'] === [] || $t['passes_pending'] !== [])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Alert events: failed tasks (retries exhausted / deadline) and failed verifications.
     *
     * @param  array<string, array<string, mixed>>  $tasks
     * @param  array<string, VerificationResult>  $results  results produced in this run
     * @return list<array<string, mixed>>
     */
    public function events(string $date, array $tasks, array $results): array
    {
        $events = [];
        foreach ($tasks as $key => $t) {
            $label = SettleCatalog::label($key);

            if ($t['state'] === 'failed') {
                $auth = $t['error_class'] === 'auth';
                $events[] = [
                    'type' => $auth ? 'auth_failure' : 'task_failed',
                    'task' => $key,
                    'codes' => [$t['error_class'] ?? 'failed'],
                    'title' => ($auth ? 'API authentication failure' : 'Task failed').": {$label}",
                    'message' => sprintf(
                        "%s\nCommand: %s\nAttempts: %d/%d. Error class: %s.\nLast error: %s\n%s",
                        $label,
                        $t['command'],
                        $t['attempts'],
                        $t['max_attempts'],
                        $t['error_class'] ?? 'unknown',
                        $t['last_error'] ?? '(none recorded)',
                        $t['next_retry_note'] ?? ''
                    ),
                ];

                continue;
            }

            $r = $results[$key] ?? null;
            if ($r === null || ! $r->isFailure()) {
                continue;
            }
            $codes = $r->failedCodes();
            $type = in_array('auth_failure', $codes, true)
                ? 'auth_failure'
                : (array_intersect($codes, self::MISSING_CODES) !== [] ? 'missing_data' : 'verification_failed');
            $failing = array_values(array_filter($r->checks, fn ($c) => in_array($c['status'], ['fail', 'error'], true)));
            $events[] = [
                'type' => $type,
                'task' => $key,
                'codes' => $codes,
                'title' => match ($type) {
                    'auth_failure' => "API authentication failure: {$label}",
                    'missing_data' => "Missing / incomplete data: {$label}",
                    default => "Verification failed: {$label}",
                },
                'message' => sprintf(
                    "%s\nCommand: %s\nThe command exited 0 but the stored data failed verification for Pacific day %s:\n- %s",
                    $label,
                    $t['command'],
                    $date,
                    implode("\n- ", array_map(fn ($c) => $c['code'].': '.$c['message'], $failing))
                ),
            ];
        }

        return $events;
    }
}
