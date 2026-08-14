<?php

namespace App\Services\Support;

use App\Services\Ebay1PromotionService;
use Illuminate\Support\Facades\Log;

/**
 * Background Push PRMT % worker.
 * Processes SKUs in chunks (default 10) via the file-backed queue.
 * Same markdown sale-event logic as eBay 1 (Ebay1PromotionService::for).
 */
class ChannelPushPrmtRunner
{
    private const CHUNK_SIZE = 10;

    private readonly string $channel;

    public function __construct(string $channel = 'ebay2')
    {
        $this->channel = strtolower(trim($channel)) ?: 'ebay2';
    }

    public static function for(string $channel): self
    {
        return new self($channel);
    }

    public function run(): int
    {
        @set_time_limit(0);

        $store = ChannelPushPrmtJobStore::for($this->channel);
        $logger = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/'.$this->channel.'-push-prmt.log'),
            'level' => 'debug',
        ]);

        $lockPath = storage_path('app/'.$this->channel.'-push-prmt/runner.lock');
        $lockDir = dirname($lockPath);
        if (! is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }
        $lockHandle = @fopen($lockPath, 'c+');
        if (! $lockHandle || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $logger->info('Channel Push PRMT% runner skipped — another process holds the lock', [
                'channel' => $this->channel,
            ]);
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

    private function runLocked(ChannelPushPrmtJobStore $store, \Psr\Log\LoggerInterface $logger): int
    {
        $promo = Ebay1PromotionService::for($this->channel);

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
                $prmt = (float) ($task['prmt'] ?? 0);

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
                $promoId = null;
                $appliedPct = null;
                try {
                    $res = $promo->syncSkuPromotionPercent($sku, $prmt);
                    if (! empty($res['success'])) {
                        $ok = true;
                        $promoId = $res['promotion_id'] ?? null;
                        $appliedPct = $res['percent'] ?? ($prmt > 0 ? $prmt : 0);
                    } else {
                        $error = (string) ($res['message'] ?? 'Push PRMT% failed');
                    }
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                    $logger->error('Channel Push PRMT% exception', [
                        'channel' => $this->channel,
                        'sku' => $sku,
                        'error' => $error,
                    ]);
                }

                $store->update(function (array $state) use ($index, $sku, $ok, $error, $promoId, $appliedPct) {
                    if (! isset($state['tasks'][$index]) || ! is_array($state['tasks'][$index])) {
                        return $state;
                    }
                    if ($ok) {
                        $state['tasks'][$index]['status'] = 'ok';
                        $state['tasks'][$index]['error'] = null;
                        $state['tasks'][$index]['message'] = 'pushed';
                        $state['tasks'][$index]['promotion_id'] = $promoId;
                        $state['tasks'][$index]['percent'] = $appliedPct;
                        $state['ok_count'] = ((int) ($state['ok_count'] ?? 0)) + 1;
                    } else {
                        $state['tasks'][$index]['status'] = 'failed';
                        $state['tasks'][$index]['error'] = $error ?: 'Push PRMT% failed';
                        $state['tasks'][$index]['message'] = $error ?: 'Push PRMT% failed';
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
            if (in_array((string) ($task['status'] ?? ''), ['pending', 'queued'], true)) {
                $out[] = (int) $i;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }

        return $out;
    }
}
