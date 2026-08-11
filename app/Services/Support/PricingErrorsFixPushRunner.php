<?php

namespace App\Services\Support;

use App\Http\Controllers\MarketPlace\CvrMasterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Background PEF price push: calls CvrMasterController::pushPriceToAmazon per task,
 * retries transient API failures until success or max attempts.
 */
class PricingErrorsFixPushRunner
{
    public const MAX_ATTEMPTS = 30;

    public const BASE_DELAY_MS = 2000;

    public const MAX_DELAY_MS = 60000;

    /** Extra wait when MySQL is saturated (avoids retry death-spiral). */
    public const DB_SATURATION_DELAY_MS = 20000;

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
            $index = (int) ($state['current_index'] ?? 0);
            $total = count($tasks);

            if ($index >= $total) {
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
                    $remainingInMp++;
                }
                $this->store->appendMessage(
                    "Starting marketplace {$mp} ({$remainingInMp} row(s))…",
                    true
                );
            }

            $this->store->update(function (array $state) use ($sku, $mp, $index, $total, $attempts) {
                $state['current_sku'] = $sku;
                $state['current_marketplace'] = $mp;
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

            $transient = $this->isTransientFailure($message);
            if ($transient && $attempts < self::MAX_ATTEMPTS) {
                $delayMs = min(self::MAX_DELAY_MS, self::BASE_DELAY_MS * (2 ** min($attempts - 1, 5)));
                if ($this->isDbSaturationFailure($message)) {
                    $delayMs = max($delayMs, self::DB_SATURATION_DELAY_MS);
                }
                $retryMsg = "{$sku} → {$mp}: {$message} — retry {$attempts}/".self::MAX_ATTEMPTS." in ".round($delayMs / 1000)."s";
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
