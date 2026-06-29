<?php

namespace App\Services\Support;

class VideoMasterPushJobStore
{
    private const MAX_MESSAGES = 150;

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
     * @param  list<array{marketplace: string, videos: list<string>}>  $tasks
     */
    public function create(string $sku, string $mode, array $tasks, ?array $mainByMarketplace = null): array
    {
        $normalized = [];
        foreach ($tasks as $task) {
            $mp = strtolower(trim((string) ($task['marketplace'] ?? '')));
            if ($mp === '') {
                continue;
            }
            $videos = array_values(array_filter(array_map('trim', $task['videos'] ?? []), fn ($s) => $s !== ''));
            $normalized[] = [
                'marketplace' => $mp,
                'videos' => $videos,
                'status' => 'pending',
                'result' => null,
            ];
        }

        $state = array_merge($this->defaultState(), [
            'id' => date('YmdHis').'_'.bin2hex(random_bytes(4)),
            'sku' => trim($sku),
            'mode' => $mode === 'add' ? 'add' : 'replace',
            'main_by_marketplace' => $mainByMarketplace ?? [],
            'status' => 'running',
            'tasks' => $normalized,
            'total' => count($normalized),
            'current_index' => 0,
            'current_marketplace' => null,
            'ok_count' => 0,
            'fail_count' => 0,
            'metrics_fail_count' => 0,
            'results' => [],
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
            'last_message' => 'Video push queued.',
            'messages' => [[
                'time' => now()->format('H:i:s'),
                'ok' => true,
                'message' => 'Video push queued.',
            ]],
        ]);

        $this->save($state);

        return $state;
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

    /**
     * A "running" job whose worker has not updated it for a while is dead/stuck (worker crashed or
     * was never running) and must not block new pushes forever.
     */
    public function isStale(array $state, int $seconds = 300): bool
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

    /**
     * Force the job inactive regardless of current status (user Cancel, or clearing a stuck job).
     */
    public function forceStop(string $message = 'Stopped by user.'): array
    {
        return $this->update(function (array $state) use ($message) {
            $state['status'] = 'failed';
            $state['finished_at'] = now()->toDateTimeString();
            $state['last_message'] = $message;

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

            return $state;
        });
    }

    public function toApiResponse(array $state): array
    {
        $results = is_array($state['results'] ?? null) ? $state['results'] : [];
        $totalSuccess = (int) ($state['ok_count'] ?? 0);
        $totalFailed = (int) ($state['fail_count'] ?? 0);
        $totalMetricsFailed = (int) ($state['metrics_fail_count'] ?? 0);
        $status = $state['status'] ?? 'idle';
        $done = in_array($status, ['completed', 'failed', 'stopped'], true);

        return [
            'success' => $done && $totalFailed === 0,
            'queued' => true,
            'dry_run' => false,
            'job' => $state,
            'results' => $results,
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'total_metrics_failed' => $totalMetricsFailed,
            'message' => $done
                ? "Updated {$totalSuccess} marketplace(s).".($totalFailed > 0 ? " {$totalFailed} failed." : '').($totalMetricsFailed > 0 ? " {$totalMetricsFailed} metrics save failed." : '')
                : ($state['last_message'] ?? 'Push in progress…'),
        ];
    }

    private function defaultState(): array
    {
        return [
            'id' => null,
            'sku' => null,
            'mode' => 'replace',
            'main_by_marketplace' => [],
            'status' => 'idle',
            'tasks' => [],
            'total' => 0,
            'current_index' => 0,
            'current_marketplace' => null,
            'ok_count' => 0,
            'fail_count' => 0,
            'metrics_fail_count' => 0,
            'results' => [],
            'started_at' => null,
            'finished_at' => null,
            'updated_at' => null,
            'last_message' => 'Ready',
            'messages' => [],
        ];
    }

    private function path(): string
    {
        return storage_path('app/video-master-push/job.json');
    }

    private function ensureDirectory(): void
    {
        $dir = dirname($this->path());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
