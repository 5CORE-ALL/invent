<?php

namespace App\Services\Support;

/**
 * File-backed Reverb Push B% job state (S Bump% → live Reverb Bump bid).
 */
class ReverbPushBumpJobStore
{
    private const MAX_MESSAGES = 200;

    public static function for(): self
    {
        return new self;
    }

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
        $normalized = $this->normalizeTasks($tasks);
        $queueMsg = 'Push B% queued ('.count($normalized).' SKU(s)).';

        $state = array_merge($this->defaultState(), [
            'id' => date('YmdHis').'_'.bin2hex(random_bytes(4)),
            'channel' => 'reverb',
            'status' => 'running',
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
            foreach ($normalized as $task) {
                $skuKey = strtoupper((string) ($task['sku'] ?? ''));
                $pendingIdx = null;
                foreach ($state['tasks'] as $i => $existing) {
                    if (! is_array($existing)) {
                        continue;
                    }
                    $st = (string) ($existing['status'] ?? '');
                    if (
                        strtoupper((string) ($existing['sku'] ?? '')) === $skuKey
                        && in_array($st, ['pending', 'queued'], true)
                    ) {
                        $pendingIdx = $i;
                        break;
                    }
                }

                if ($pendingIdx !== null) {
                    $state['tasks'][$pendingIdx] = array_merge($state['tasks'][$pendingIdx], $task, [
                        'status' => 'pending',
                        'error' => null,
                        'message' => 're-queued',
                    ]);
                    $updated++;
                } else {
                    $state['tasks'][] = $task;
                    $added++;
                }
            }

            $state['total'] = count($state['tasks']);
            $state['status'] = 'running';
            $state['finished_at'] = null;
            $msg = 'Added '.$added.' SKU(s)'
                .($updated ? (', updated '.$updated.' pending') : '')
                .' — queue now '.$state['total'].'.';
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
     * @param  list<array<string, mixed>>  $tasks
     * @return array{state: array, mode: string}
     */
    public function createOrAppend(array $tasks): array
    {
        $current = $this->load();
        if ($this->isActive($current)) {
            if ($this->isStale($current, 180)) {
                $this->forceStop('Cleared a stale Push B% job (no worker was processing it).');
                $state = $this->create($tasks);

                return ['state' => $state, 'mode' => 'create'];
            }
            $state = $this->append($tasks);

            return ['state' => $state, 'mode' => 'append'];
        }

        $state = $this->create($tasks);

        return ['state' => $state, 'mode' => 'create'];
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
        $state['channel'] = 'reverb';

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
                'bump' => $task['bump'] ?? null,
                'error' => $task['error'] ?? null,
            ];
        }

        $finishedCount = $ok + $fail;
        $pct = $total > 0
            ? min($done ? 100 : 99, (int) round(($finishedCount / $total) * 100))
            : 0;
        if ($done) {
            $pct = 100;
        }

        return [
            'success' => $done && $fail === 0,
            'queued' => true,
            'channel' => 'reverb',
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
            'message' => $done
                ? "Push B% done: {$ok} ok, {$fail} failed."
                : ($state['last_message'] ?? 'Push B% in progress…'),
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
            $bump = max(0, (float) ($task['bump'] ?? $task['recommended_bid'] ?? 0));
            if ($sku === '') {
                continue;
            }
            $normalized[] = [
                'sku' => $sku,
                'bump' => $bump,
                'status' => 'pending',
                'attempts' => 0,
                'error' => null,
                'message' => null,
            ];
        }

        return $normalized;
    }

    private function defaultState(): array
    {
        return [
            'id' => null,
            'channel' => 'reverb',
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
        ];
    }

    private function path(): string
    {
        return storage_path('app/reverb-push-bump/job.json');
    }

    private function ensureDirectory(): void
    {
        $dir = dirname($this->path());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
