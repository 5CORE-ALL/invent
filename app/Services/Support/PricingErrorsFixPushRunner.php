<?php

namespace App\Services\Support;

use App\Http\Controllers\MarketPlace\CvrMasterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Background PEF price push: calls CvrMasterController::pushPriceToAmazon per task,
 * retries transient API failures until success or max attempts.
 *
 * Optional: on call-usage limit (#518 etc.), defer the whole marketplace, continue
 * with the next channel, then revive deferred tasks after a wait window.
 */
class PricingErrorsFixPushRunner
{
    public const MAX_ATTEMPTS = 30;

    public const BASE_DELAY_MS = 2000;

    public const MAX_DELAY_MS = 60000;

    /** Extra wait when MySQL is saturated (avoids retry death-spiral). */
    public const DB_SATURATION_DELAY_MS = 20000;

    /** Wait before retrying a marketplace deferred for call/usage limit. */
    public const CALL_LIMIT_REVIVE_DELAY_SEC = 300;

    /** Max times one marketplace can be deferred for call limits in a job. */
    public const CALL_LIMIT_MAX_MP_ROUNDS = 24;

    public function __construct(
        private readonly PricingErrorsFixPushJobStore $store,
    ) {}

    public function run(): int
    {
        @set_time_limit(0);

        $logger = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/pricing-errors-fix-push.log'),
            'level' => 'debug',
        ]);

        // Only one runner process at a time (queue worker + sync spawn must not double-push).
        $lockPath = storage_path('app/pricing-errors-fix-push/runner.lock');
        $lockDir = dirname($lockPath);
        if (! is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }
        $lockHandle = @fopen($lockPath, 'c+');
        if (! $lockHandle || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $logger->info('PEF push runner skipped — another process already holds the lock');
            if ($lockHandle) {
                fclose($lockHandle);
            }

            return 0;
        }

        try {
            return $this->runLocked($logger);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function runLocked(\Psr\Log\LoggerInterface $logger): int
    {
        while (true) {
            $state = $this->store->load();
            if (($state['status'] ?? 'idle') !== 'running') {
                return 0;
            }

            $tasks = array_values($state['tasks'] ?? []);
            $total = count($tasks);
            $index = $this->findNextPushableIndex($tasks, (int) ($state['current_index'] ?? 0));

            if ($index === null) {
                // No ready work — wait for deferred call-limit marketplaces, or finish.
                if ($this->hasDeferredTasks($tasks)) {
                    if (! $this->waitAndReviveDeferred($logger)) {
                        return 0; // cancelled / not running
                    }

                    continue;
                }

                $this->store->update(function (array $state) {
                    $state['status'] = 'completed';
                    $state['current_sku'] = null;
                    $state['current_marketplace'] = null;
                    $state['finished_at'] = now()->toDateTimeString();
                    $state['last_message'] = "Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.";

                    return $state;
                });
                $state = $this->store->load();
                $this->store->appendMessage(
                    "Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.",
                    ((int) ($state['fail_count'] ?? 0)) === 0
                );

                return 0;
            }

            // Keep job pointer on the task we are about to push.
            if ((int) ($state['current_index'] ?? 0) !== $index) {
                $this->store->update(function (array $state) use ($index) {
                    $state['current_index'] = $index;

                    return $state;
                });
            }

            $task = $tasks[$index];
            if (! is_array($task)) {
                $this->advanceIndex($index, false, 'Invalid task payload', null);

                continue;
            }

            $sku = (string) ($task['sku'] ?? '');
            $mp = (string) ($task['marketplace'] ?? '');
            $price = (float) ($task['price'] ?? 0);
            $attempts = (int) ($task['attempts'] ?? 0);

            // Announce when queue moves to a new marketplace (marketplace-wise batching)
            $prevMp = $index > 0 && is_array($tasks[$index - 1] ?? null)
                ? (string) ($tasks[$index - 1]['marketplace'] ?? '')
                : '';
            if ($index === 0 || ($mp !== '' && $mp !== $prevMp)) {
                $remainingInMp = 0;
                for ($i = $index; $i < $total; $i++) {
                    if (! is_array($tasks[$i] ?? null)) {
                        break;
                    }
                    if ((string) ($tasks[$i]['marketplace'] ?? '') !== $mp) {
                        break;
                    }
                    $st = (string) ($tasks[$i]['status'] ?? 'pending');
                    if (in_array($st, ['pending', 'pushing', 'retrying', 'queued'], true)) {
                        $remainingInMp++;
                    }
                }
                $this->store->appendMessage(
                    "Starting marketplace {$mp} ({$remainingInMp} row(s))…",
                    true
                );
            }

            $this->store->update(function (array $state) use ($sku, $mp, $index, $total, $attempts) {
                $state['current_sku'] = $sku;
                $state['current_marketplace'] = $mp;
                $state['current_index'] = $index;
                $state['last_message'] = 'Pushing '.($index + 1)."/{$total}: {$sku} → {$mp}"
                    .($attempts > 0 ? " (retry {$attempts})" : '');
                if (isset($state['tasks'][$index]) && is_array($state['tasks'][$index])) {
                    $state['tasks'][$index]['status'] = 'pushing';
                }

                return $state;
            });

            $ok = false;
            $message = 'Push failed';
            $result = ['success' => false, 'message' => $message];

            try {
                $payload = [
                    'sku' => $sku,
                    'price' => $price,
                    'marketplace' => $mp,
                ];
                if (isset($task['self_pick_price']) && $task['self_pick_price'] !== null) {
                    $payload['self_pick_price'] = $task['self_pick_price'];
                }
                if (! empty($task['goods_id'])) {
                    $payload['goods_id'] = $task['goods_id'];
                }
                if (! empty($task['sku_id'])) {
                    $payload['sku_id'] = $task['sku_id'];
                }
                if (! empty($task['product_id'])) {
                    $payload['product_id'] = $task['product_id'];
                }

                // Free MySQL slots during long marketplace API calls (XAMPP max_connections is low).
                $this->releaseDbConnections();

                $req = Request::create('/cvr-master-push-price', 'POST', $payload);
                $response = app(CvrMasterController::class)->pushPriceToAmazon($req);
                $body = method_exists($response, 'getData') ? $response->getData(true) : [];
                if (! is_array($body)) {
                    $body = [];
                }
                $ok = ! empty($body['success']);
                $message = (string) ($body['message'] ?? ($ok ? 'Pushed' : 'Push failed'));
                $result = $body;
            } catch (\Throwable $e) {
                $ok = false;
                $message = $e->getMessage() ?: 'Push exception';
                $result = ['success' => false, 'message' => $message];
                $logger->warning('PEF push task exception', [
                    'sku' => $sku,
                    'marketplace' => $mp,
                    'error' => $message,
                ]);
            } finally {
                $this->releaseDbConnections();
            }

            $attempts++;
            $this->store->update(function (array $state) use ($index, $attempts) {
                if (isset($state['tasks'][$index]) && is_array($state['tasks'][$index])) {
                    $state['tasks'][$index]['attempts'] = $attempts;
                }

                return $state;
            });

            if ($ok) {
                $this->advanceIndex($index, true, $message, $result);
                $this->store->appendMessage("{$sku} → {$mp}: {$message}", true);
                $this->releaseDbConnections();
                usleep(600000);

                continue;
            }

            $skipMpOnCallLimit = ! empty(($this->store->load()['options'] ?? [])['skip_mp_on_call_limit']);
            if ($skipMpOnCallLimit && $this->isCallLimitFailure($message)) {
                $deferred = $this->deferMarketplaceForCallLimit($index, $mp, $message, $logger);
                if ($deferred) {
                    $this->releaseDbConnections();
                    usleep(400000);

                    continue;
                }
                // Fall through to normal retry / fail if defer was not possible (max rounds).
            }

            $transient = $this->isTransientFailure($message);
            if ($transient && $attempts < self::MAX_ATTEMPTS) {
                $delayMs = min(self::MAX_DELAY_MS, self::BASE_DELAY_MS * (2 ** min($attempts - 1, 5)));
                if ($this->isDbSaturationFailure($message)) {
                    $delayMs = max($delayMs, self::DB_SATURATION_DELAY_MS);
                }
                $retryMsg = "{$sku} → {$mp}: {$message} — retry {$attempts}/".self::MAX_ATTEMPTS.' in '.round($delayMs / 1000).'s';
                $this->store->update(function (array $state) use ($index, $message, $retryMsg) {
                    $state['last_message'] = $retryMsg;
                    if (isset($state['tasks'][$index]) && is_array($state['tasks'][$index])) {
                        $state['tasks'][$index]['status'] = 'retrying';
                        $state['tasks'][$index]['error'] = $message;
                        $state['tasks'][$index]['message'] = $retryMsg;
                    }

                    return $state;
                });
                $this->store->appendMessage($retryMsg, false);
                $logger->info('PEF push retrying', [
                    'sku' => $sku,
                    'marketplace' => $mp,
                    'attempt' => $attempts,
                    'delay_ms' => $delayMs,
                    'error' => $message,
                ]);
                $this->releaseDbConnections();
                usleep($delayMs * 1000);

                continue; // same index — do not advance
            }

            $failReason = $transient
                ? "Gave up after {$attempts} attempts: {$message}"
                : $message;
            $this->advanceIndex($index, false, $failReason, $result);
            $this->store->appendMessage("{$sku} → {$mp}: FAILED — {$failReason}", false);
            $this->releaseDbConnections();
            usleep(500000);
        }
    }

    /**
     * @param  list<mixed>  $tasks
     */
    private function findNextPushableIndex(array $tasks, int $from): ?int
    {
        $n = count($tasks);
        $from = max(0, $from);
        for ($i = $from; $i < $n; $i++) {
            if (! is_array($tasks[$i] ?? null)) {
                continue;
            }
            $st = (string) ($tasks[$i]['status'] ?? 'pending');
            if (in_array($st, ['pending', 'pushing', 'retrying', 'queued'], true)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $tasks
     */
    private function hasDeferredTasks(array $tasks): bool
    {
        foreach ($tasks as $task) {
            if (is_array($task) && ($task['status'] ?? '') === 'deferred') {
                return true;
            }
        }

        return false;
    }

    /**
     * Defer remaining tasks for this marketplace and jump to the next channel.
     */
    private function deferMarketplaceForCallLimit(int $index, string $mp, string $message, \Psr\Log\LoggerInterface $logger): bool
    {
        $state = $this->store->load();
        $rounds = (int) (($state['mp_defer_rounds'][$mp] ?? 0));
        if ($rounds >= self::CALL_LIMIT_MAX_MP_ROUNDS) {
            $this->store->appendMessage(
                "{$mp}: call limit still exceeded after {$rounds} defer round(s) — failing remaining rows.",
                false
            );

            return false;
        }

        $until = now()->addSeconds(self::CALL_LIMIT_REVIVE_DELAY_SEC)->toDateTimeString();
        $deferredCount = 0;
        $nextIndex = null;

        $this->store->update(function (array $state) use ($index, $mp, $message, $until, $rounds, &$deferredCount, &$nextIndex) {
            $tasks = array_values($state['tasks'] ?? []);
            $n = count($tasks);
            for ($i = $index; $i < $n; $i++) {
                if (! is_array($tasks[$i] ?? null)) {
                    continue;
                }
                if ((string) ($tasks[$i]['marketplace'] ?? '') !== $mp) {
                    if ($nextIndex === null) {
                        $st = (string) ($tasks[$i]['status'] ?? 'pending');
                        if (in_array($st, ['pending', 'pushing', 'retrying', 'queued'], true)) {
                            $nextIndex = $i;
                        }
                    }

                    continue;
                }
                $st = (string) ($tasks[$i]['status'] ?? 'pending');
                if (! in_array($st, ['pending', 'pushing', 'retrying', 'queued', 'deferred'], true)) {
                    continue;
                }
                $state['tasks'][$i]['status'] = 'deferred';
                $state['tasks'][$i]['deferred_until'] = $until;
                $state['tasks'][$i]['error'] = $message;
                $state['tasks'][$i]['message'] = "Call limit — deferred until {$until}; will retry after revive";
                $deferredCount++;
            }

            // If later same-marketplace pending rows exist before a different mp was found
            // (shouldn't with marketplace-wise sort), still scan for next pushable other mp.
            if ($nextIndex === null) {
                for ($i = 0; $i < $n; $i++) {
                    if (! is_array($state['tasks'][$i] ?? null)) {
                        continue;
                    }
                    if ((string) ($state['tasks'][$i]['marketplace'] ?? '') === $mp) {
                        continue;
                    }
                    $st = (string) ($state['tasks'][$i]['status'] ?? 'pending');
                    if (in_array($st, ['pending', 'pushing', 'retrying', 'queued'], true)) {
                        $nextIndex = $i;
                        break;
                    }
                }
            }

            $state['mp_defer_rounds'][$mp] = $rounds + 1;
            $state['current_index'] = $nextIndex ?? $n;
            $state['current_sku'] = null;
            $state['current_marketplace'] = null;
            $mins = (int) round(self::CALL_LIMIT_REVIVE_DELAY_SEC / 60);
            $state['last_message'] = "{$mp}: call/usage limit — deferred {$deferredCount} row(s), "
                .'moving to next marketplace. Retry after ~'.$mins.' min (token/call revive).';

            return $state;
        });

        $mins = (int) round(self::CALL_LIMIT_REVIVE_DELAY_SEC / 60);
        $msg = "{$mp}: call/usage limit — deferred {$deferredCount} row(s), moved to next marketplace. "
            ."Will retry after ~{$mins} min when call quota revives.";
        $this->store->appendMessage($msg, false);
        $logger->info('PEF push deferred marketplace for call limit', [
            'marketplace' => $mp,
            'deferred' => $deferredCount,
            'retry_after' => $until,
            'round' => $rounds + 1,
        ]);

        return $deferredCount > 0;
    }

    /**
     * Sleep until earliest deferred_until, then revive those tasks to pending.
     *
     * @return bool false if job cancelled while waiting
     */
    private function waitAndReviveDeferred(\Psr\Log\LoggerInterface $logger): bool
    {
        while (true) {
            $state = $this->store->load();
            if (($state['status'] ?? 'idle') !== 'running') {
                return false;
            }

            $tasks = array_values($state['tasks'] ?? []);
            $earliest = null;
            $readyNow = false;
            foreach ($tasks as $task) {
                if (! is_array($task) || ($task['status'] ?? '') !== 'deferred') {
                    continue;
                }
                $until = (string) ($task['deferred_until'] ?? '');
                if ($until === '') {
                    $readyNow = true;
                    break;
                }
                try {
                    $ts = \Illuminate\Support\Carbon::parse($until)->getTimestamp();
                } catch (\Throwable) {
                    $readyNow = true;
                    break;
                }
                if ($ts <= time()) {
                    $readyNow = true;
                    break;
                }
                if ($earliest === null || $ts < $earliest) {
                    $earliest = $ts;
                }
            }

            if (! $readyNow && $earliest === null) {
                return true; // nothing deferred
            }

            if (! $readyNow && $earliest !== null) {
                $waitSec = max(1, $earliest - time());
                $this->store->update(function (array $state) use ($waitSec) {
                    $state['last_message'] = 'Waiting for call/token revive — retry deferred marketplace(s) in '
                        .$waitSec.'s…';

                    return $state;
                });
                // Sleep in chunks so Cancel can stop the job.
                $chunk = min(15, $waitSec);
                $this->releaseDbConnections();
                usleep($chunk * 1000000);

                continue;
            }

            // Revive all deferred whose until has passed (or missing).
            $revived = 0;
            $firstIndex = null;
            $mps = [];
            $this->store->update(function (array $state) use (&$revived, &$firstIndex, &$mps) {
                foreach ($state['tasks'] ?? [] as $i => $task) {
                    if (! is_array($task) || ($task['status'] ?? '') !== 'deferred') {
                        continue;
                    }
                    $until = (string) ($task['deferred_until'] ?? '');
                    $due = true;
                    if ($until !== '') {
                        try {
                            $due = \Illuminate\Support\Carbon::parse($until)->getTimestamp() <= time();
                        } catch (\Throwable) {
                            $due = true;
                        }
                    }
                    if (! $due) {
                        continue;
                    }
                    $state['tasks'][$i]['status'] = 'pending';
                    $state['tasks'][$i]['deferred_until'] = null;
                    $state['tasks'][$i]['error'] = null;
                    $state['tasks'][$i]['message'] = 'Revived after call/token window — retrying push';
                    $revived++;
                    $mps[(string) ($task['marketplace'] ?? '')] = true;
                    if ($firstIndex === null) {
                        $firstIndex = (int) $i;
                    }
                }
                if ($firstIndex !== null) {
                    $state['current_index'] = $firstIndex;
                    $state['last_message'] = 'Call/token revived — retrying '.$revived
                        .' deferred row(s) ('.implode(', ', array_keys(array_filter($mps))).')…';
                }

                return $state;
            });

            if ($revived > 0) {
                $mpList = implode(', ', array_keys(array_filter($mps)));
                $this->store->appendMessage(
                    "Call/token revived — retrying {$revived} deferred row(s)".($mpList !== '' ? " [{$mpList}]" : '').'…',
                    true
                );
                $logger->info('PEF push revived deferred tasks', [
                    'revived' => $revived,
                    'marketplaces' => array_keys(array_filter($mps)),
                ]);
            }

            return true;
        }
    }

    /**
     * Drop idle PDO connections so bulk push does not exhaust MySQL max_connections
     * while waiting on Amazon/eBay/etc. HTTP.
     */
    private function releaseDbConnections(): void
    {
        try {
            foreach (array_keys(config('database.connections', [])) as $name) {
                try {
                    DB::connection((string) $name)->disconnect();
                } catch (\Throwable) {
                    // ignore per-connection errors
                }
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    private function isDbSaturationFailure(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'too many connections')
            || str_contains($m, 'error 1040')
            || str_contains($m, '[1040]')
            || (str_contains($m, 'hy000') && str_contains($m, '1040'));
    }

    private function advanceIndex(int $index, bool $ok, string $message, ?array $result): void
    {
        $this->store->update(function (array $state) use ($index, $ok, $message, $result) {
            $state['current_index'] = $index + 1;
            if ($ok) {
                $state['ok_count'] = ((int) ($state['ok_count'] ?? 0)) + 1;
            } else {
                $state['fail_count'] = ((int) ($state['fail_count'] ?? 0)) + 1;
            }
            if (isset($state['tasks'][$index]) && is_array($state['tasks'][$index])) {
                $task = $state['tasks'][$index];
                $rowId = (string) ($task['row_id'] ?? '');
                $state['tasks'][$index]['status'] = $ok ? 'done' : 'failed';
                $state['tasks'][$index]['error'] = $ok ? null : $message;
                $state['tasks'][$index]['message'] = $message;
                $state['tasks'][$index]['result'] = $result;
                $state['tasks'][$index]['deferred_until'] = null;
                if ($rowId !== '') {
                    $state['results'][$rowId] = [
                        'success' => $ok,
                        'sku' => $task['sku'] ?? null,
                        'marketplace' => $task['marketplace'] ?? null,
                        'price' => $task['price'] ?? null,
                        'sprice' => $task['sprice'] ?? null,
                        'message' => $message,
                        'error' => $ok ? null : $message,
                    ];
                }
            }
            $state['last_message'] = $message;

            return $state;
        });
    }

    private function isCallLimitFailure(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'usage limit')
            || str_contains($m, 'call usage')
            || str_contains($m, 'apiaccessrules')
            || str_contains($m, 'ebay #518')
            || (str_contains($m, 'exceeded') && str_contains($m, 'limit'))
            || preg_match('/\b518\b/', $m) === 1
            || str_contains($m, 'too many requests')
            || str_contains($m, '429')
            || str_contains($m, 'rate limit');
    }

    private function isTransientFailure(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'not_in_ip_white_list')
            || str_contains($m, 'ip_white')
            || str_contains($m, 'timeout')
            || str_contains($m, 'timed out')
            || str_contains($m, 'connection')
            || str_contains($m, 'curl error')
            || str_contains($m, 'rate limit')
            || str_contains($m, 'usage limit')
            || str_contains($m, 'call usage')
            || str_contains($m, 'apiaccessrules')
            || str_contains($m, 'too many requests')
            || str_contains($m, '429')
            || str_contains($m, '502')
            || str_contains($m, '503')
            || str_contains($m, '504')
            || str_contains($m, 'ebay #518')
            || preg_match('/\b518\b/', $m) === 1
            || str_contains($m, 'temporarily')
            || str_contains($m, 'try again')
            || str_contains($m, 'unavailable')
            // Temu token/proxy blips (same class of transient as IP whitelist)
            || str_contains($m, 'access_token not exists')
            || str_contains($m, 'access token');
    }
}
