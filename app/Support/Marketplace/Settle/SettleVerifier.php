<?php

namespace App\Support\Marketplace\Settle;

use App\Models\SettleVerification;
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

    /**
     * Failures that come from the command output / schema and cannot be a transient race with a
     * sibling task, so they are alerted at once. Every other (data-based) failure is only alerted
     * after it was seen in alert_confirm_failures consecutive verifications and the pull is older
     * than alert_grace_minutes.
     */
    private const DETERMINISTIC_CODES = ['auth_failure', 'incomplete_pagination', 'command_timeout', 'table_missing', 'column_missing'];

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
            SettleTracker::saveVerification($date, $key, $result, $t['ledger_done_at'], (int) round((microtime(true) - $started) * 1000), $now);
            $results[$key] = $result;
        }

        $final = SettleTracker::describe($date, $ledger, $hi, $deadline, $now);
        $events = $this->events($date, $final['tasks'], $results, $now);
        $sent = ($alerts && config('settle.alerts_enabled', true)) ? $this->alerter->dispatch($date, $events, $now) : [];

        return [
            'date' => $date,
            'available' => true,
            'summary' => $final['summary'],
            'results' => array_map(fn (VerificationResult $r) => $r->toArray(), $results),
            'tasks' => $final['tasks'],
            'alerts' => $sent,
            'events' => count($events),
            'awaiting_confirmation' => $this->unconfirmed,
        ];
    }

    /**
     * Which tasks to (re-)verify now:
     *  - never verified for the current pull ................... always
     *  - verification failed ................................... every reverify_after_minutes
     *  - verified / warning / limited .......................... every reverify_verified_after_minutes,
     *    so deletes / prunes / data changes made AFTER the first check invalidate it
     *  - limited by design (nothing to compare with) ........... never
     *
     * @param  array<string, mixed>  $t
     */
    private function needsVerification(array $t, Carbon $now, int $reverifyMinutes): bool
    {
        $at = $t['verification']['checked_at'] ?? null;
        switch ($t['state']) {
            case 'awaiting_verification':
                return true;
            case 'verification_failed':
                return $at === null || Carbon::parse($at)->lte($now->copy()->subMinutes($reverifyMinutes));
            case 'verified':
            case 'verified_warning':
            case 'limited':
                $every = (int) config('settle.reverify_verified_after_minutes', 60);

                return $every > 0 && ($at === null || Carbon::parse($at)->lte($now->copy()->subMinutes($every)));
            default:
                return false;
        }
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

    /** @var list<string> tasks whose failure is waiting for confirmation (not alerted yet) */
    private array $unconfirmed = [];

    /**
     * @param  array<string, mixed>  $t
     * @param  list<string>  $codes
     */
    private function confirmed(string $date, string $key, array $t, array $codes, Carbon $now): bool
    {
        if ($codes !== [] && array_diff($codes, self::DETERMINISTIC_CODES) === []) {
            return true;
        }
        $needed = max(1, (int) config('settle.alert_confirm_failures', 2));
        $grace = max(0, (int) config('settle.alert_grace_minutes', 10));
        $doneAt = $t['ledger_done_at'] ?? null;
        if ($doneAt !== null && $grace > 0 && Carbon::parse($doneAt)->addMinutes($grace)->gt($now)) {
            return false; // a sibling task may still be writing the same tables
        }

        return SettleTracker::consecutiveFailures($date, $key, $doneAt) >= $needed;
    }

    /**
     * Operator action (settle:verify --accept-baseline=TASK): records the CURRENT table size of a
     * pruned / rebuilt table as the new passing baseline, e.g. after a deliberate prune. Only writes
     * a row to settle_verifications; a failed verification never becomes a baseline by itself.
     *
     * @return array{ok: bool, message: string, table_rows?: int}
     */
    public function acceptBaseline(string $date, string $taskKey, ?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now(Ledger::TZ))->copy()->setTimezone(Ledger::TZ);
        if (! isset(Ledger::tasks()[$taskKey])) {
            return ['ok' => false, 'message' => "Unknown task {$taskKey}."];
        }
        $ctx = new VerificationContext($taskKey, Ledger::tasks()[$taskKey], true, null, $now, fn () => true);
        $result = VerifierRegistry::for($taskKey)->verify($date, $ctx);
        $rows = $result->metrics['table_rows'] ?? null;
        if ($rows === null) {
            return ['ok' => false, 'message' => "Task {$taskKey} has no table-size baseline (or its table could not be read)."];
        }
        SettleVerification::query()->create([
            'settle_date' => $date,
            'task_key' => $taskKey,
            'status' => 'baseline',
            'checks' => [],
            'metrics' => ['table_rows' => (int) $rows, 'accepted' => true],
            'ledger_done_at' => null,
            'duration_ms' => 0,
        ]);

        return ['ok' => true, 'message' => "Accepted {$rows} rows as the passing baseline for {$taskKey}.", 'table_rows' => (int) $rows];
    }

    /**
     * Alert events: failed tasks (retries exhausted / deadline) and failed verifications.
     *
     * @param  array<string, array<string, mixed>>  $tasks
     * @param  array<string, VerificationResult>  $results  results produced in this run
     * @return list<array<string, mixed>>
     */
    public function events(string $date, array $tasks, array $results, ?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now(Ledger::TZ))->copy();
        $this->unconfirmed = [];
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
            if (! $this->confirmed($date, $key, $t, $codes, $now)) {
                $this->unconfirmed[] = $key;

                continue;
            }
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
