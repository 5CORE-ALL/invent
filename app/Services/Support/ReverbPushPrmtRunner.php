<?php

namespace App\Services\Support;

use App\Services\ChannelPromoPricingService;
use App\Services\ReverbApiService;
use Illuminate\Support\Facades\Log;

/**
 * Background worker: apply Reverb "Drop the Price By" at PRMT% (listing price / Std unchanged).
 */
class ReverbPushPrmtRunner
{
    private const CHUNK_SIZE = 5;

    public static function for(): self
    {
        return new self;
    }

    public function run(): int
    {
        @set_time_limit(0);

        $store = ReverbPushPrmtJobStore::for();
        $logger = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/reverb-push-prmt.log'),
            'level' => 'debug',
        ]);

        $lockPath = storage_path('app/reverb-push-prmt/runner.lock');
        $lockDir = dirname($lockPath);
        if (! is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }
        $lockHandle = @fopen($lockPath, 'c+');
        if (! $lockHandle || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $logger->info('Reverb Push Prmt% runner skipped — another process holds the lock');
            if ($lockHandle) {
                fclose($lockHandle);
            }

            return 0;
        }

        try {
            $store->update(function (array $state) {
                foreach ($state['tasks'] ?? [] as $i => $task) {
                    if (! is_array($task)) {
                        continue;
                    }
                    if ((string) ($task['status'] ?? '') === 'pushing') {
                        $state['tasks'][$i]['status'] = 'pending';
                        $state['tasks'][$i]['message'] = 'retry after worker restart';
                    }
                }

                return $state;
            });

            return $this->runLocked($store, $logger);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function runLocked(ReverbPushPrmtJobStore $store, \Psr\Log\LoggerInterface $logger): int
    {
        $api = app(ReverbApiService::class);
        $promo = app(ChannelPromoPricingService::class);

        while (true) {
            $state = $store->load();
            if (($state['status'] ?? 'idle') !== 'running') {
                return 0;
            }

            $indexes = $this->findNextPendingIndexes($state['tasks'] ?? [], self::CHUNK_SIZE);
            if ($indexes === []) {
                $store->update(function (array $state) {
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
                $state = $store->load();
                if (($state['status'] ?? '') === 'completed') {
                    $store->appendMessage(
                        "Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.",
                        ((int) ($state['fail_count'] ?? 0)) === 0
                    );

                    return 0;
                }

                continue;
            }

            $total = (int) ($state['total'] ?? 0);
            $doneBefore = ((int) ($state['ok_count'] ?? 0)) + ((int) ($state['fail_count'] ?? 0));
            $chunkNo = $total > 0 ? (int) floor($doneBefore / self::CHUNK_SIZE) + 1 : 1;
            $chunkTotal = $total > 0 ? (int) ceil($total / self::CHUNK_SIZE) : 1;
            $store->update(function (array $state) use ($chunkNo, $chunkTotal, $indexes) {
                $state['last_message'] = 'Chunk '.$chunkNo.'/'.$chunkTotal
                    .' — pushing '.count($indexes).' SKU(s)…';

                return $state;
            });

            foreach ($indexes as $index) {
                $state = $store->load();
                if (($state['status'] ?? 'idle') !== 'running') {
                    return 0;
                }
                $task = $state['tasks'][$index] ?? null;
                if (! is_array($task)) {
                    continue;
                }
                $sku = (string) ($task['sku'] ?? '');
                $std = round((float) ($task['std'] ?? 0), 2);
                $prmt = max(0, (float) ($task['prmt'] ?? 0));
                $price = round((float) ($task['price'] ?? 0), 2);

                $store->update(function (array $state) use ($index, $sku, $chunkNo, $chunkTotal) {
                    $state['current_index'] = $index;
                    $state['current_sku'] = $sku;
                    if (isset($state['tasks'][$index]) && is_array($state['tasks'][$index])) {
                        $state['tasks'][$index]['status'] = 'pushing';
                        $state['tasks'][$index]['attempts'] = ((int) ($state['tasks'][$index]['attempts'] ?? 0)) + 1;
                    }
                    $done = ((int) ($state['ok_count'] ?? 0)) + ((int) ($state['fail_count'] ?? 0));
                    $total = (int) ($state['total'] ?? 0);
                    $state['last_message'] = 'Chunk '.$chunkNo.'/'.$chunkTotal
                        .' · '.($done + 1).'/'.$total.': '.$sku;

                    return $state;
                });

                $ok = false;
                $error = null;
                try {
                    $res = $api->applyListingPriceDrop($sku, $prmt);
                    if (! empty($res['success'])) {
                        $ok = true;
                        $this->recordSuccess($promo, $sku, $prmt);
                    } else {
                        $error = (string) ($res['message'] ?? 'Push Prmt% failed');
                    }
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                    $logger->error('Reverb Push Prmt% exception', [
                        'sku' => $sku,
                        'std' => $std,
                        'prmt' => $prmt,
                        'price' => $price,
                        'error' => $error,
                    ]);
                }

                $store->update(function (array $state) use ($index, $sku, $ok, $error) {
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
                        $state['tasks'][$index]['error'] = $error ?: 'Push Prmt% failed';
                        $state['tasks'][$index]['message'] = $error ?: 'Push Prmt% failed';
                        $state['fail_count'] = ((int) ($state['fail_count'] ?? 0)) + 1;
                    }
                    $done = ((int) ($state['ok_count'] ?? 0)) + ((int) ($state['fail_count'] ?? 0));
                    $total = (int) ($state['total'] ?? 0);
                    $state['last_message'] = ($ok ? 'OK' : 'Fail')." {$sku} — {$done}/{$total}";
                    $state['current_sku'] = null;

                    return $state;
                });

                $store->appendMessage(($ok ? 'OK ' : 'Fail ').$sku.($error ? (': '.$error) : ''), $ok);
            }

            usleep(250000);
        }
    }

    private function recordSuccess(ChannelPromoPricingService $promo, string $sku, float $prmt): void
    {
        try {
            $promo->upsert('reverb', $sku, [
                'push_prc_status' => 'pushed',
                'push_prc_value' => $prmt,
                'push_prc_pushed_at' => now(),
                'prmt_pct' => $prmt,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Reverb Push Prmt% recorded API ok but promo save failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<mixed>  $tasks
     * @return list<int>
     */
    private function findNextPendingIndexes(array $tasks, int $limit): array
    {
        $out = [];
        foreach ($tasks as $i => $task) {
            if (! is_array($task)) {
                continue;
            }
            $st = (string) ($task['status'] ?? 'pending');
            if (in_array($st, ['pending', 'queued'], true)) {
                $out[] = (int) $i;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }

        return $out;
    }
}
