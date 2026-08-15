<?php

namespace App\Services\Support;

use App\Services\ChannelPromoPricingService;
use App\Services\ReverbApiService;
use Illuminate\Support\Facades\Log;

/**
 * Background worker: push S Bump% to live Reverb Bump bid.
 */
class ReverbPushBumpRunner
{
    private const CHUNK_SIZE = 5;

    public static function for(): self
    {
        return new self;
    }

    public function run(): int
    {
        @set_time_limit(0);

        $store = ReverbPushBumpJobStore::for();
        $logger = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/reverb-push-bump.log'),
            'level' => 'debug',
        ]);

        $lockPath = storage_path('app/reverb-push-bump/runner.lock');
        $lockDir = dirname($lockPath);
        if (! is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }
        $lockHandle = @fopen($lockPath, 'c+');
        if (! $lockHandle || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $logger->info('Reverb Push B% runner skipped — another process holds the lock');
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

    private function runLocked(ReverbPushBumpJobStore $store, \Psr\Log\LoggerInterface $logger): int
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
                $bump = max(0, (float) ($task['bump'] ?? 0));

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
                $display = null;
                try {
                    $res = $api->applyListingBumpBid($sku, $bump);
                    if (! empty($res['success'])) {
                        $ok = true;
                        $display = (string) ($res['display'] ?? '');
                        $this->recordSuccess($promo, $sku, (float) ($res['percent'] ?? $bump), $display);
                    } else {
                        $error = (string) ($res['message'] ?? 'Push B% failed');
                    }
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                    $logger->error('Reverb Push B% exception', [
                        'sku' => $sku,
                        'bump' => $bump,
                        'error' => $error,
                    ]);
                }

                $store->update(function (array $state) use ($index, $sku, $ok, $error, $display) {
                    if (! isset($state['tasks'][$index]) || ! is_array($state['tasks'][$index])) {
                        return $state;
                    }
                    if ($ok) {
                        $state['tasks'][$index]['status'] = 'ok';
                        $state['tasks'][$index]['error'] = null;
                        $state['tasks'][$index]['message'] = 'pushed';
                        if ($display !== null && $display !== '') {
                            $state['tasks'][$index]['display'] = $display;
                        }
                        $state['ok_count'] = ((int) ($state['ok_count'] ?? 0)) + 1;
                    } else {
                        $state['tasks'][$index]['status'] = 'failed';
                        $state['tasks'][$index]['error'] = $error ?: 'Push B% failed';
                        $state['tasks'][$index]['message'] = $error ?: 'Push B% failed';
                        $state['fail_count'] = ((int) ($state['fail_count'] ?? 0)) + 1;
                    }
                    $done = ((int) ($state['ok_count'] ?? 0)) + ((int) ($state['fail_count'] ?? 0));
                    $total = (int) ($state['total'] ?? 0);
                    $state['last_message'] = ($ok ? 'OK' : 'Fail')." {$sku} — {$done}/{$total}";
                    $state['current_sku'] = null;

                    return $state;
                });

                $store->appendMessage(($ok ? 'OK ' : 'Fail ').$sku.($error ? (': '.$error) : ''), $ok);
                usleep(150000);
            }

            usleep(250000);
        }
    }

    private function recordSuccess(ChannelPromoPricingService $promo, string $sku, float $bump, string $display): void
    {
        try {
            $promo->upsert('reverb', $sku, [
                'push_bump_status' => 'pushed',
                'push_bump_value' => $bump,
                'push_bump_pushed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Reverb Push B% recorded API ok but promo save failed', [
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
