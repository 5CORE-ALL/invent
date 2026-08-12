<?php

namespace App\Services\Support;

use App\Http\Controllers\MarketPlace\OverallAmazonController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Background Amazon Push Prc worker — processes queued SKUs one by one.
 * Survives browser refresh; new SKUs can be appended while running.
 */
class AmazonPushPrcRunner
{
    public function __construct(
        private readonly AmazonPushPrcJobStore $store,
    ) {}

    public function run(): int
    {
        @set_time_limit(0);

        $logger = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/amazon-push-prc.log'),
            'level' => 'debug',
        ]);

        $lockPath = storage_path('app/amazon-push-prc/runner.lock');
        $lockDir = dirname($lockPath);
        if (! is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }
        $lockHandle = @fopen($lockPath, 'c+');
        if (! $lockHandle || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $logger->info('Amazon Push Prc runner skipped — another process holds the lock');
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
        /** @var OverallAmazonController $controller */
        $controller = app(OverallAmazonController::class);

        while (true) {
            $state = $this->store->load();
            if (($state['status'] ?? 'idle') !== 'running') {
                return 0;
            }

            $index = $this->findNextPendingIndex($state['tasks'] ?? []);
            if ($index === null) {
                $this->store->update(function (array $state) {
                    // Append race: only complete if still no pending work
                    foreach ($state['tasks'] ?? [] as $task) {
                        if (! is_array($task)) {
                            continue;
                        }
                        if (in_array((string) ($task['status'] ?? ''), ['pending', 'queued', 'pushing'], true)) {
                            return $state;
                        }
                    }
                    $state['status'] = 'completed';
                    $state['current_sku'] = null;
                    $state['finished_at'] = now()->toDateTimeString();
                    $state['last_message'] = "Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.";

                    return $state;
                });
                $state = $this->store->load();
                if (($state['status'] ?? '') === 'completed') {
                    $this->store->appendMessage(
                        "Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.",
                        ((int) ($state['fail_count'] ?? 0)) === 0
                    );

                    return 0;
                }

                continue;
            }

            $task = $state['tasks'][$index] ?? null;
            if (! is_array($task)) {
                return 0;
            }
            $sku = (string) ($task['sku'] ?? '');

            $this->store->update(function (array $state) use ($index, $sku) {
                $state['current_index'] = $index;
                $state['current_sku'] = $sku;
                if (isset($state['tasks'][$index]) && is_array($state['tasks'][$index])) {
                    $state['tasks'][$index]['status'] = 'pushing';
                    $state['tasks'][$index]['attempts'] = ((int) ($state['tasks'][$index]['attempts'] ?? 0)) + 1;
                }
                $done = ((int) ($state['ok_count'] ?? 0)) + ((int) ($state['fail_count'] ?? 0));
                $total = (int) ($state['total'] ?? 0);
                $state['last_message'] = 'Pushing '.($done + 1).'/'.$total.': '.$sku;

                return $state;
            });

            $logger->info('Amazon Push Prc: pushing', [
                'sku' => $sku,
                'index' => $index,
                'std' => $task['std'] ?? null,
                'sale' => $task['sale'] ?? null,
            ]);

            $ok = false;
            $error = null;
            try {
                // 1) Persist S PRC + margins (+ Push Prc history) before Amazon call
                $saveReq = Request::create('/save-amazon-sprice', 'POST', [
                    'sku' => $sku,
                    'sprice' => $task['effective'] ?? $task['std'],
                    'prmt_pct' => $task['prmt'] ?? 0,
                    'cpn_pct' => $task['cpn'] ?? 0,
                    'record_push_prc' => 1,
                ]);
                $saveRes = $controller->saveSpriceToDatabase($saveReq);
                if (method_exists($saveRes, 'getStatusCode') && $saveRes->getStatusCode() >= 400) {
                    $payload = method_exists($saveRes, 'getData') ? $saveRes->getData(true) : [];
                    $logger->warning('Amazon Push Prc: local S PRC save failed (continuing to Amazon)', [
                        'sku' => $sku,
                        'response' => $payload,
                    ]);
                }

                // 2) Push Your / Sale / Min / Max / Business to Amazon
                $applyData = [
                    'sku' => $sku,
                    'price' => $task['std'],
                    'asin' => $task['asin'] ?? null,
                    'push_shopify' => false,
                    'update_amazon_min_price' => true,
                    'min_price' => $task['min'] ?? null,
                    'max_price' => $task['max'] ?? null,
                    'business_price' => $task['business'] ?? null,
                ];
                if (isset($task['sale']) && $task['sale'] !== null) {
                    $applyData['sale_price'] = $task['sale'];
                }
                $applyReq = Request::create('/apply-amazon-price', 'POST', $applyData);
                $applyRes = $controller->applyAmazonPrice($applyReq);
                $applyPayload = method_exists($applyRes, 'getData') ? $applyRes->getData(true) : [];
                if (is_array($applyPayload) && ! empty($applyPayload['errors'])) {
                    $error = (string) (($applyPayload['errors'][0]['message'] ?? null) ?: 'Amazon push failed');
                    $ok = false;
                } else {
                    $ok = true;
                }
            } catch (\Throwable $e) {
                $ok = false;
                $error = $e->getMessage();
                $logger->error('Amazon Push Prc exception', [
                    'sku' => $sku,
                    'error' => $error,
                ]);
            }

            $this->store->update(function (array $state) use ($index, $sku, $ok, $error) {
                if (! isset($state['tasks'][$index]) || ! is_array($state['tasks'][$index])) {
                    return $state;
                }
                if ($ok) {
                    $state['tasks'][$index]['status'] = 'ok';
                    $state['tasks'][$index]['error'] = null;
                    $state['tasks'][$index]['message'] = 'pushed';
                    $state['ok_count'] = ((int) ($state['ok_count'] ?? 0)) + 1;
                } else {
                    $state['tasks'][$index]['status'] = 'failed';
                    $state['tasks'][$index]['error'] = $error ?: 'Push failed';
                    $state['tasks'][$index]['message'] = $error ?: 'Push failed';
                    $state['fail_count'] = ((int) ($state['fail_count'] ?? 0)) + 1;
                }
                $state['results'][$sku] = [
                    'success' => $ok,
                    'sku' => $sku,
                    'error' => $ok ? null : ($error ?: 'Push failed'),
                ];
                $done = ((int) ($state['ok_count'] ?? 0)) + ((int) ($state['fail_count'] ?? 0));
                $total = (int) ($state['total'] ?? 0);
                $state['last_message'] = ($ok ? 'OK' : 'Fail')." {$sku} — {$done}/{$total}";
                $state['current_sku'] = null;

                return $state;
            });

            // Brief pause between Amazon API calls
            usleep(400000);
        }
    }

    /**
     * @param  list<mixed>  $tasks
     */
    private function findNextPendingIndex(array $tasks): ?int
    {
        foreach ($tasks as $i => $task) {
            if (! is_array($task)) {
                continue;
            }
            if (in_array((string) ($task['status'] ?? ''), ['pending', 'queued'], true)) {
                return (int) $i;
            }
        }

        return null;
    }
}
