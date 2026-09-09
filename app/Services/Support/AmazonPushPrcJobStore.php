<?php

namespace App\Services\Support;

use App\Models\AmazonDatasheet;
use App\Services\AmazonSpApiService;

/**
 * File-backed Amazon Push Prc job state (survives page refresh).
 * Supports appending new SKUs while a job is already running.
 * One task per SKU — refresh must not grow the bar with already-pushed rows.
 */
class AmazonPushPrcJobStore
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
     * @param  list<array<string, mixed>>  $tasks
     */
    public function create(array $tasks): array
    {
        $previous = $this->load();
        $failedBlock = $this->mergeFailedBlock($previous);
        $normalized = $this->uniqueTasksBySku(
            $this->dropBlockedTasks($this->normalizeTasks($tasks), $failedBlock)
        );
        $queueMsg = 'Push Prc queued ('.count($normalized).' SKU(s)).';

        $state = array_merge($this->defaultState(), [
            'id' => date('YmdHis').'_'.bin2hex(random_bytes(4)),
            'status' => 'running',
            'failed_block' => $failedBlock,
            'tasks' => $normalized,
            'total' => count($normalized),
            'current_index' => 0,
            'current_sku' => null,
            'ok_count' => 0,
            'fail_count' => 0,
            'results' => [],
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
     * Append SKUs to a running job (or revive a completed job as running).
     *
     * @param  list<array<string, mixed>>  $tasks
     */
    public function append(array $tasks): array
    {
        $normalized = $this->normalizeTasks($tasks);
        if ($normalized === []) {
            return $this->load();
        }

        return $this->update(function (array $state) use ($normalized) {
            if (! is_array($state['tasks'] ?? null)) {
                $state['tasks'] = [];
            }

            $added = 0;
            $updated = 0;
            $skipped = 0;
            foreach ($normalized as $task) {
                $skuKey = strtoupper((string) ($task['sku'] ?? ''));
                $idx = $this->indexOfSku($state['tasks'], $skuKey);
                if ($idx === null) {
                    $state['tasks'][] = $task;
                    $added++;
                    continue;
                }

                $existing = $state['tasks'][$idx];
                $st = (string) ($existing['status'] ?? '');
                if ($st === 'pushing') {
                    $skipped++;
                    continue;
                }
                $sameTarget = $this->sameMoney($existing['effective'] ?? null, $task['effective'] ?? null);
                if ($st === 'ok' && $sameTarget) {
                    $skipped++;
                    continue;
                }
                if ($st === 'failed' && $sameTarget) {
                    $skipped++;
                    continue;
                }
                $block = $state['failed_block'][$skuKey] ?? null;
                if (is_array($block) && $this->sameMoney($block['effective'] ?? null, $task['effective'] ?? null)) {
                    $skipped++;
                    continue;
                }
                if (in_array($st, ['ok', 'failed'], true)) {
                    if ($st === 'ok') {
                        $state['ok_count'] = max(0, ((int) ($state['ok_count'] ?? 0)) - 1);
                    } else {
                        $state['fail_count'] = max(0, ((int) ($state['fail_count'] ?? 0)) - 1);
                    }
                }
                $state['tasks'][$idx] = array_merge($existing, $task, [
                    'status' => 'pending',
                    'error' => null,
                    'message' => 're-queued',
                ]);
                $updated++;
            }

            $state = $this->recount($state);
            $state['status'] = 'running';
            $state['finished_at'] = null;
            $msg = 'Queue +'.$added
                .($updated ? (', updated '.$updated) : '')
                .($skipped ? (', skipped '.$skipped.' already queued/pushed') : '')
                .' — '.$state['total'].' unique SKU(s).';
            $messages = $state['messages'] ?? [];
            $messages[] = [
                'time' => now()->format('H:i:s'),
                'ok' => true,
                'message' => $msg,
            ];
            $state['messages'] = array_slice($messages, -self::MAX_MESSAGES);
            $state['last_message'] = $msg;

            return $state;
        });
    }

    /**
     * Create a new job, or append when one is already running.
     *
     * @param  list<array<string, mixed>>  $tasks
     * @return array{state: array, mode: string}
     */
    public function createOrAppend(array $tasks): array
    {
        $current = $this->load();
        if ($this->isActive($current)) {
            if ($this->isStale($current, 180)) {
                $this->forceStop('Cleared a stale Push Prc job (no worker was processing it).');
                $state = $this->create($tasks);

                return ['state' => $state, 'mode' => 'create'];
            }
            $state = $this->append($tasks);
            $this->compactDuplicateSkus();
            $state = $this->markPendingAlreadyAtListingPrice();

            return ['state' => $state, 'mode' => 'append'];
        }

        $state = $this->create($tasks);
        $this->compactDuplicateSkus();
        $state = $this->markPendingAlreadyAtListingPrice();

        return ['state' => $state, 'mode' => 'create'];
    }

    /**
     * One row per SKU. Refresh used to append a second copy of every already-pushed SKU.
     */
    public function compactDuplicateSkus(): array
    {
        return $this->update(function (array $state) {
            $best = [];
            foreach ($state['tasks'] ?? [] as $task) {
                if (! is_array($task)) {
                    continue;
                }
                $key = strtoupper(trim((string) ($task['sku'] ?? '')));
                if ($key === '') {
                    continue;
                }
                $rank = $this->statusRank((string) ($task['status'] ?? 'pending'));
                if (! isset($best[$key]) || $rank > $this->statusRank((string) ($best[$key]['status'] ?? ''))) {
                    $best[$key] = $task;
                }
            }
            $state['tasks'] = array_values($best);

            return $this->recount($state);
        });
    }

    /**
     * Pending SKUs whose live listing Price already equals S PRC — mark skipped (blue triangle gone).
     */
    public function markPendingAlreadyAtListingPrice(): array
    {
        return $this->update(function (array $state) {
            $pendingSkus = [];
            foreach ($state['tasks'] ?? [] as $task) {
                if (! is_array($task)) {
                    continue;
                }
                if (! in_array((string) ($task['status'] ?? ''), ['pending', 'queued'], true)) {
                    continue;
                }
                $sku = strtoupper(trim((string) ($task['sku'] ?? '')));
                if ($sku !== '') {
                    $pendingSkus[] = $sku;
                }
            }
            if ($pendingSkus === []) {
                return $state;
            }

            $priceBySku = $this->listingPricesForSkus($pendingSkus);
            $skipped = 0;
            foreach ($state['tasks'] as $i => $task) {
                if (! is_array($task)) {
                    continue;
                }
                if (! in_array((string) ($task['status'] ?? ''), ['pending', 'queued'], true)) {
                    continue;
                }
                $sku = strtoupper(trim((string) ($task['sku'] ?? '')));
                $live = $priceBySku[$sku] ?? 0.0;
                $target = $task['effective'] ?? $task['sale'] ?? $task['std'] ?? 0;
                if (! AmazonSpApiService::listingPriceMatchesSprice($live, $target)) {
                    continue;
                }
                $state['tasks'][$i]['status'] = 'ok';
                $state['tasks'][$i]['error'] = null;
                $state['tasks'][$i]['message'] = 'skipped — Price already = S PRC';
                $state['ok_count'] = ((int) ($state['ok_count'] ?? 0)) + 1;
                $skipped++;
            }
            if ($skipped > 0) {
                $state['last_message'] = 'Skipped '.$skipped.' SKU(s) already at S PRC.';
            }

            return $this->recount($state);
        });
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

    public function isStale(array $state, int $seconds = 180): bool
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
            foreach ($state['tasks'] ?? [] as $i => $task) {
                if (! is_array($task)) {
                    continue;
                }
                $st = (string) ($task['status'] ?? 'pending');
                if (in_array($st, ['pending', 'pushing', 'queued'], true)) {
                    $state['tasks'][$i]['status'] = 'failed';
                    $state['tasks'][$i]['error'] = $message;
                    $state['tasks'][$i]['message'] = $message;
                    $state['fail_count'] = ((int) ($state['fail_count'] ?? 0)) + 1;
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

            return $state;
        });
    }

    public function toApiResponse(array $state): array
    {
        $total = (int) ($state['total'] ?? 0);
        $ok = (int) ($state['ok_count'] ?? 0);
        $fail = (int) ($state['fail_count'] ?? 0);
        $status = (string) ($state['status'] ?? 'idle');
        $done = in_array($status, ['completed', 'failed', 'stopped'], true);

        $pending = 0;
        $pushing = 0;
        $taskSummaries = [];
        foreach ($state['tasks'] ?? [] as $task) {
            if (! is_array($task)) {
                continue;
            }
            $st = (string) ($task['status'] ?? 'pending');
            if (in_array($st, ['pending', 'queued'], true)) {
                $pending++;
            } elseif ($st === 'pushing') {
                $pushing++;
            }
            $taskSummaries[] = [
                'sku' => $task['sku'] ?? null,
                'status' => $st,
                'effective' => $task['effective'] ?? null,
                'error' => $task['error'] ?? null,
            ];
        }

        $finishedCount = $ok + $fail;
        $pct = $total > 0 ? min(100, (int) round(($finishedCount / $total) * 100)) : 0;

        return [
            'success' => $done && $fail === 0,
            'queued' => true,
            'job' => [
                'id' => $state['id'] ?? null,
                'status' => $status,
                'current_sku' => $state['current_sku'] ?? null,
                'started_at' => $state['started_at'] ?? null,
                'finished_at' => $state['finished_at'] ?? null,
                'updated_at' => $state['updated_at'] ?? null,
                'last_message' => $state['last_message'] ?? null,
            ],
            'active' => $this->isActive($state),
            'total' => $total,
            'done_count' => $finishedCount,
            'ok_count' => $ok,
            'fail_count' => $fail,
            'pending_count' => $pending,
            'pushing_count' => $pushing,
            'pct' => $pct,
            'tasks' => $taskSummaries,
            'failed_block' => is_array($state['failed_block'] ?? null) ? $state['failed_block'] : [],
            'message' => $done
                ? "Push Prc done: {$ok} ok, {$fail} failed."
                : ($state['last_message'] ?? 'Push Prc in progress…'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return list<array<string, mixed>>
     */
    private function normalizeTasks(array $tasks): array
    {
        $normalized = [];
        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }
            $sku = trim((string) ($task['sku'] ?? ''));
            $std = isset($task['std']) ? round((float) $task['std'], 2) : 0.0;
            if ($sku === '' || ! ($std > 0)) {
                continue;
            }
            $sale = isset($task['sale']) && is_numeric($task['sale']) ? round((float) $task['sale'], 2) : null;
            $zeroSold = ! empty($task['zero_sold']);
            if ($sale !== null && $sale <= 0) {
                $sale = null;
            } elseif ($sale !== null && ! $zeroSold && $sale >= $std) {
                $sale = null;
            }
            $saleBase = $sale !== null ? $sale : $std;
            $max = isset($task['max']) && is_numeric($task['max'])
                ? round((float) $task['max'], 2)
                : round($std * 1.10, 2);
            $min = $saleBase;
            $business = $saleBase;
            $effective = isset($task['effective']) && is_numeric($task['effective'])
                ? round((float) $task['effective'], 2)
                : ($sale !== null ? $sale : $std);

            $normalized[] = [
                'sku' => $sku,
                'asin' => isset($task['asin']) && trim((string) $task['asin']) !== ''
                    ? trim((string) $task['asin'])
                    : null,
                'std' => $std,
                'sale' => $sale,
                'max' => $max,
                'min' => $min,
                'business' => $business,
                'effective' => $effective,
                'prmt' => isset($task['prmt']) ? max(0, round((float) $task['prmt'], 2)) : 0,
                'cpn' => isset($task['cpn']) ? max(0, round((float) $task['cpn'], 2)) : 0,
                'cvr_disc' => isset($task['cvr_disc']) ? max(0, round((float) $task['cvr_disc'], 2)) : 0,
                'cvr_up_dn' => isset($task['cvr_up_dn']) ? round((float) $task['cvr_up_dn'], 2) : 0,
                'zero_sold' => $zeroSold,
                'status' => 'pending',
                'attempts' => 0,
                'error' => null,
                'message' => null,
            ];
        }

        return $this->uniqueTasksBySku($normalized);
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return list<array<string, mixed>>
     */
    private function uniqueTasksBySku(array $tasks): array
    {
        $bySku = [];
        foreach ($tasks as $task) {
            $key = strtoupper(trim((string) ($task['sku'] ?? '')));
            if ($key === '') {
                continue;
            }
            $bySku[$key] = $task;
        }

        return array_values($bySku);
    }

    /**
     * @param  list<mixed>  $tasks
     */
    private function indexOfSku(array $tasks, string $skuKey): ?int
    {
        $skuKey = strtoupper(trim($skuKey));
        foreach ($tasks as $i => $existing) {
            if (! is_array($existing)) {
                continue;
            }
            if (strtoupper(trim((string) ($existing['sku'] ?? ''))) === $skuKey) {
                return (int) $i;
            }
        }

        return null;
    }

    private function sameMoney(mixed $a, mixed $b): bool
    {
        return AmazonSpApiService::moneyEquals(
            is_numeric($a) ? (float) $a : null,
            is_numeric($b) ? (float) $b : null
        );
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            'pushing' => 4,
            'pending', 'queued' => 3,
            'ok' => 2,
            'failed' => 1,
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function recount(array $state): array
    {
        $ok = 0;
        $fail = 0;
        $tasks = [];
        foreach ($state['tasks'] ?? [] as $task) {
            if (! is_array($task)) {
                continue;
            }
            $tasks[] = $task;
            $st = (string) ($task['status'] ?? '');
            if ($st === 'ok') {
                $ok++;
            } elseif ($st === 'failed') {
                $fail++;
            }
        }
        $state['tasks'] = $tasks;
        $state['total'] = count($tasks);
        $state['ok_count'] = $ok;
        $state['fail_count'] = $fail;

        return $state;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, float>
     */
    private function listingPricesForSkus(array $skus): array
    {
        $out = [];
        $skus = array_values(array_unique(array_filter($skus)));
        if ($skus === []) {
            return $out;
        }
        foreach (array_chunk($skus, 400) as $chunk) {
            foreach (AmazonDatasheet::query()->whereIn('sku', $chunk)->get(['sku', 'price']) as $row) {
                $key = strtoupper(trim((string) $row->sku));
                $price = (float) ($row->price ?? 0);
                if ($key !== '' && $price > 0) {
                    $out[$key] = $price;
                }
            }
        }

        return $out;
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
            'ok_count' => 0,
            'fail_count' => 0,
            'results' => [],
            'started_at' => null,
            'finished_at' => null,
            'updated_at' => null,
            'worker_spawned_at' => null,
            'last_message' => 'Ready',
            'messages' => [],
            'failed_block' => [],
        ];
    }

    /**
     * Keep Amazon-rejected SKUs out of the next refresh job (same S PRC).
     *
     * @param  array<string, mixed>  $state
     * @return array<string, array{effective: mixed, error: ?string}>
     */
    public function mergeFailedBlock(array $state): array
    {
        $block = is_array($state['failed_block'] ?? null) ? $state['failed_block'] : [];
        foreach ($state['tasks'] ?? [] as $task) {
            if (! is_array($task) || (string) ($task['status'] ?? '') !== 'failed') {
                continue;
            }
            $key = strtoupper(trim((string) ($task['sku'] ?? '')));
            if ($key === '') {
                continue;
            }
            $block[$key] = [
                'effective' => $task['effective'] ?? null,
                'error' => $task['error'] ?? $task['message'] ?? 'Push failed',
            ];
        }

        return $block;
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @param  array<string, array<string, mixed>>  $block
     * @return list<array<string, mixed>>
     */
    public function dropBlockedTasks(array $tasks, array $block): array
    {
        if ($block === []) {
            return $tasks;
        }
        $out = [];
        foreach ($tasks as $task) {
            $key = strtoupper(trim((string) ($task['sku'] ?? '')));
            $hit = $block[$key] ?? null;
            if (is_array($hit) && $this->sameMoney($hit['effective'] ?? null, $task['effective'] ?? null)) {
                continue;
            }
            $out[] = $task;
        }

        return $out;
    }

    /**
     * Manual retry — allow these SKUs back into the queue.
     *
     * @param  list<string>  $skus
     */
    public function forgetBlocked(array $skus): void
    {
        $keys = [];
        foreach ($skus as $sku) {
            $key = strtoupper(trim((string) $sku));
            if ($key !== '') {
                $keys[$key] = true;
            }
        }
        if ($keys === []) {
            return;
        }
        $this->update(function (array $state) use ($keys) {
            $block = is_array($state['failed_block'] ?? null) ? $state['failed_block'] : [];
            foreach (array_keys($keys) as $key) {
                unset($block[$key]);
            }
            $state['failed_block'] = $block;

            return $state;
        });
    }

    private function path(): string
    {
        return storage_path('app/amazon-push-prc/job.json');
    }

    private function ensureDirectory(): void
    {
        $dir = dirname($this->path());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
