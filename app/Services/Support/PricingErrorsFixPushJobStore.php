<?php

namespace App\Services\Support;

class PricingErrorsFixPushJobStore
{
    private const MAX_MESSAGES = 200;

    public function load(): array
    {
        $path = $this->path();
        if (! is_file($path)) {
            return $this->defaultState();
        }

        $json = file_get_contents($path);
        $state = is_string($json) ? json_decode($json, true) : null;

        return is_array($state) ? array_merge($this->defaultState(), $state) : $this->defaultState();
    }

    /**
     * Stable push order — finish one marketplace completely, then the next.
     *
     * @var list<string>
     */
    private const MARKETPLACE_ORDER = [
        'amazon',
        'ebay1', 'ebay', 'ebay2', 'ebay3',
        'temu', 'temu2',
        'doba',
        'tiktok', 'tiktok1', 'tiktok2',
        'bestbuy',
        'macy', 'macys',
        'reverb',
        'sb2c', 'shopify',
        'sb2b',
        'shein',
        'faire',
        'aliexpress',
        'ppower',
        'topdawg',
        'walmart',
        'pls',
        'wayfair',
    ];

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @param  array{skip_mp_on_call_limit?: bool}  $options
     */
    public function create(array $tasks, array $options = []): array
    {
        $normalized = [];
        foreach ($tasks as $task) {
            $sku = trim((string) ($task['sku'] ?? ''));
            $mp = strtolower(preg_replace('/\s+/', '', (string) ($task['marketplace'] ?? '')) ?? '');
            $price = isset($task['price']) ? round((float) $task['price'], 2) : 0.0;
            if ($sku === '' || $mp === '' || ! ($price > 0)) {
                continue;
            }
            $rowId = trim((string) ($task['row_id'] ?? ($mp.'|'.$sku)));
            $normalized[] = [
                'row_id' => $rowId,
                'sku' => $sku,
                'marketplace' => $mp,
                'channel' => (string) ($task['channel'] ?? $mp),
                'price' => $price,
                'sprice' => isset($task['sprice']) ? round((float) $task['sprice'], 2) : $price,
                'self_pick_price' => isset($task['self_pick_price']) ? round((float) $task['self_pick_price'], 2) : null,
                'goods_id' => isset($task['goods_id']) && $task['goods_id'] !== '' ? (string) $task['goods_id'] : null,
                'sku_id' => isset($task['sku_id']) && $task['sku_id'] !== '' ? (string) $task['sku_id'] : null,
                'product_id' => isset($task['product_id']) && $task['product_id'] !== '' ? (string) $task['product_id'] : null,
                'status' => 'pending',
                'attempts' => 0,
                'error' => null,
                'message' => null,
                'result' => null,
                'deferred_until' => null,
            ];
        }

        // Queue marketplace-wise: all Amazon → then eBay1 → … (never interleave channels)
        $normalized = $this->sortTasksMarketplaceWise($normalized);

        $skipMpOnCallLimit = ! empty($options['skip_mp_on_call_limit']);
        $mpCount = count(array_unique(array_column($normalized, 'marketplace')));
        $queueMsg = 'Price push queued ('.count($normalized).' row(s), '.$mpCount
            .' marketplace(s) — one channel at a time)'
            .($skipMpOnCallLimit ? '; call-limit → next marketplace, retry after revive' : '')
            .'.';

        $state = array_merge($this->defaultState(), [
            'id' => date('YmdHis').'_'.bin2hex(random_bytes(4)),
            'status' => 'running',
            'tasks' => $normalized,
            'total' => count($normalized),
            'current_index' => 0,
            'current_sku' => null,
            'current_marketplace' => null,
            'ok_count' => 0,
            'fail_count' => 0,
            'results' => [],
            'options' => [
                'skip_mp_on_call_limit' => $skipMpOnCallLimit,
            ],
            'mp_defer_rounds' => [],
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
            'last_message' => $queueMsg,
            'messages' => [[
                'time' => now()->format('H:i:s'),
                'ok' => true,
                'message' => $queueMsg,
            ]],
        ]);

        $this->save($state);

        return $state;
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return list<array<string, mixed>>
     */
    private function sortTasksMarketplaceWise(array $tasks): array
    {
        $rank = array_flip(self::MARKETPLACE_ORDER);

        usort($tasks, static function (array $a, array $b) use ($rank): int {
            $mpA = (string) ($a['marketplace'] ?? '');
            $mpB = (string) ($b['marketplace'] ?? '');
            $ra = $rank[$mpA] ?? 1000;
            $rb = $rank[$mpB] ?? 1000;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            // Same marketplace rank (or both unknown): group by marketplace name, then SKU
            $byMp = strcmp($mpA, $mpB);
            if ($byMp !== 0) {
                return $byMp;
            }

            return strcmp((string) ($a['sku'] ?? ''), (string) ($b['sku'] ?? ''));
        });

        return array_values($tasks);
    }

    public function update(callable $callback): array
    {
        $this->ensureDirectory();
        $handle = fopen($this->path(), 'c+');
        if (! $handle) {
            return $this->defaultState();
        }

        flock($handle, LOCK_EX);
        rewind($handle);
        $json = stream_get_contents($handle);
        $state = is_string($json) && $json !== '' ? json_decode($json, true) : null;
        $state = is_array($state) ? array_merge($this->defaultState(), $state) : $this->defaultState();

        $updated = $callback($state);
        $state = is_array($updated) ? array_merge($this->defaultState(), $updated) : $state;
        $state['updated_at'] = now()->toDateTimeString();

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return $state;
    }

    public function appendMessage(string $message, bool $ok = true): array
    {
        return $this->update(function (array $state) use ($message, $ok) {
            $messages = $state['messages'] ?? [];
            $messages[] = [
                'time' => now()->format('H:i:s'),
                'ok' => $ok,
                'message' => $message,
            ];
            $state['messages'] = array_slice($messages, -self::MAX_MESSAGES);
            $state['last_message'] = $message;

            return $state;
        });
    }

    public function save(array $state): void
    {
        $this->ensureDirectory();
        file_put_contents($this->path(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public function isActive(array $state): bool
    {
        return in_array($state['status'] ?? 'idle', ['running'], true);
    }

    public function isStale(array $state, int $seconds = 120): bool
    {
        if (! $this->isActive($state)) {
            return false;
        }
        $updatedAt = $state['updated_at'] ?? null;
        if (! is_string($updatedAt) || $updatedAt === '') {
            return true;
        }
        try {
            return abs(now()->diffInSeconds(\Illuminate\Support\Carbon::parse($updatedAt))) > $seconds;
        } catch (\Throwable) {
            return true;
        }
    }

    public function forceStop(string $message = 'Stopped by user.'): array
    {
        return $this->update(function (array $state) use ($message) {
            $state['status'] = 'failed';
            $state['finished_at'] = now()->toDateTimeString();
            $state['last_message'] = $message;
            $state['current_sku'] = null;
            $state['current_marketplace'] = null;
            foreach ($state['tasks'] ?? [] as $i => $task) {
                if (! is_array($task)) {
                    continue;
                }
                $st = (string) ($task['status'] ?? 'pending');
                if (in_array($st, ['pending', 'pushing', 'retrying', 'queued', 'deferred'], true)) {
                    $state['tasks'][$i]['status'] = 'failed';
                    $state['tasks'][$i]['error'] = $message;
                    $state['tasks'][$i]['message'] = $message;
                    $state['tasks'][$i]['deferred_until'] = null;
                    $state['fail_count'] = ((int) ($state['fail_count'] ?? 0)) + 1;
                    $rowId = (string) ($task['row_id'] ?? '');
                    if ($rowId !== '') {
                        $state['results'][$rowId] = [
                            'success' => false,
                            'sku' => $task['sku'] ?? null,
                            'marketplace' => $task['marketplace'] ?? null,
                            'price' => $task['price'] ?? null,
                            'sprice' => $task['sprice'] ?? null,
                            'message' => $message,
                            'error' => $message,
                        ];
                    }
                }
            }

            return $state;
        });
    }

    public function markFailed(string $message): array
    {
        return $this->update(function (array $state) use ($message) {
            if (($state['status'] ?? 'idle') !== 'running') {
                return $state;
            }
            $state['status'] = 'failed';
            $state['finished_at'] = now()->toDateTimeString();
            $state['last_message'] = 'Job failed: '.$message;
            $state['current_sku'] = null;
            $state['current_marketplace'] = null;

            return $state;
        });
    }

    public function toApiResponse(array $state): array
    {
        $total = (int) ($state['total'] ?? 0);
        $index = (int) ($state['current_index'] ?? 0);
        $ok = (int) ($state['ok_count'] ?? 0);
        $fail = (int) ($state['fail_count'] ?? 0);
        $status = (string) ($state['status'] ?? 'idle');
        $done = in_array($status, ['completed', 'failed', 'stopped'], true);

        $failedTasks = [];
        $deferredCount = 0;
        foreach ($state['tasks'] ?? [] as $task) {
            if (! is_array($task)) {
                continue;
            }
            if (($task['status'] ?? '') === 'deferred') {
                $deferredCount++;
            }
            if (($task['status'] ?? '') === 'failed') {
                $failedTasks[] = [
                    'row_id' => $task['row_id'] ?? null,
                    'sku' => $task['sku'] ?? null,
                    'marketplace' => $task['marketplace'] ?? null,
                    'channel' => $task['channel'] ?? null,
                    'error' => $task['error'] ?? $task['message'] ?? 'Push failed',
                    'attempts' => (int) ($task['attempts'] ?? 0),
                ];
            }
        }

        return [
            'success' => $done && $fail === 0,
            'queued' => true,
            'job' => $state,
            'total' => $total,
            'done_count' => min($index, $total),
            'ok_count' => $ok,
            'fail_count' => $fail,
            'deferred_count' => $deferredCount,
            'failed_tasks' => $failedTasks,
            'options' => $state['options'] ?? [],
            'message' => $done
                ? "Push done: {$ok} ok, {$fail} failed."
                : ($state['last_message'] ?? 'Push in progress…'),
        ];
    }

    private function defaultState(): array
    {
        return [
            'id' => null,
            'status' => 'idle',
            'tasks' => [],
            'total' => 0,
            'current_index' => 0,
            'current_sku' => null,
            'current_marketplace' => null,
            'ok_count' => 0,
            'fail_count' => 0,
            'results' => [],
            'options' => [
                'skip_mp_on_call_limit' => false,
            ],
            'mp_defer_rounds' => [],
            'started_at' => null,
            'finished_at' => null,
            'updated_at' => null,
            'last_message' => 'Ready',
            'messages' => [],
        ];
    }

    private function path(): string
    {
        return storage_path('app/pricing-errors-fix-push/job.json');
    }

    private function ensureDirectory(): void
    {
        $dir = dirname($this->path());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
