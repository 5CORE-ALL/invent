<?php

namespace App\Services\Support;

/**
 * File-backed state for the Promotions Amazon coupon push (survives page refresh).
 * storage/app/amazon-dil-price-coupon-push/job.json, updated under an exclusive lock.
 */
class AmazonDilPriceCouponPushStore
{
    private const MAX_MESSAGES = 60;

    public static function path(): string
    {
        return storage_path('app/amazon-dil-price-coupon-push/job.json');
    }

    public static function lockPath(): string
    {
        return storage_path('app/amazon-dil-price-coupon-push/runner.lock');
    }

    /** @return array<string, mixed> */
    public function defaultState(): array
    {
        return [
            'status' => 'idle', // idle | running | completed | cancelled
            'tasks' => [],
            'total' => 0,
            'ok_count' => 0,
            'fail_count' => 0,
            'current_sku' => null,
            'started_at' => null,
            'finished_at' => null,
            'updated_at' => null,
            'last_message' => '',
            'messages' => [],
        ];
    }

    /** @return array<string, mixed> */
    public function load(): array
    {
        $path = self::path();
        if (! is_file($path)) {
            return $this->defaultState();
        }
        $state = json_decode((string) file_get_contents($path), true);

        return is_array($state) ? array_merge($this->defaultState(), $state) : $this->defaultState();
    }

    /** @param  array<string, mixed>  $state */
    public function isActive(array $state): bool
    {
        return ($state['status'] ?? 'idle') === 'running';
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return array<string, mixed>
     */
    public function start(array $tasks): array
    {
        $state = array_merge($this->defaultState(), [
            'status' => 'running',
            'tasks' => array_values($tasks),
            'total' => count($tasks),
            'started_at' => now()->toDateTimeString(),
            'last_message' => 'Queued '.count($tasks).' SKU(s).',
        ]);
        $this->write($state);

        return $state;
    }

    /**
     * Atomic read-modify-write.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutate
     * @return array<string, mixed>
     */
    public function update(callable $mutate): array
    {
        $path = self::path();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $h = fopen($path, 'c+');
        if (! $h) {
            return $this->load();
        }
        flock($h, LOCK_EX);
        try {
            $raw = stream_get_contents($h);
            $cur = json_decode((string) $raw, true);
            $state = is_array($cur) ? array_merge($this->defaultState(), $cur) : $this->defaultState();
            $state = $mutate($state);
            $state['updated_at'] = now()->toDateTimeString();
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, json_encode($state));
            fflush($h);
        } finally {
            flock($h, LOCK_UN);
            fclose($h);
        }

        return $state;
    }

    /** @param  array<string, mixed>  $state */
    private function write(array $state): void
    {
        $this->update(static fn () => $state);
    }

    /** @return array<string, mixed> */
    public function appendMessage(string $message, bool $ok = true): array
    {
        return $this->update(function (array $s) use ($message, $ok) {
            $msgs = is_array($s['messages']) ? $s['messages'] : [];
            $msgs[] = ['time' => now()->format('H:i:s'), 'ok' => $ok, 'message' => $message];
            $s['messages'] = array_slice($msgs, -self::MAX_MESSAGES);

            return $s;
        });
    }

    /** @return array<string, mixed> */
    public function cancel(): array
    {
        return $this->update(function (array $s) {
            if (($s['status'] ?? '') === 'running') {
                $s['status'] = 'cancelled';
                $s['finished_at'] = now()->toDateTimeString();
                $s['current_sku'] = null;
                $s['last_message'] = 'Cancelled by user. SKUs already pushed stay pushed.';
            }

            return $s;
        });
    }

    /** True when a worker process currently holds the runner lock. */
    public function runnerLockHeld(): bool
    {
        $lock = self::lockPath();
        if (! is_file($lock)) {
            return false;
        }
        $h = @fopen($lock, 'c+');
        if (! $h) {
            return false;
        }
        $got = flock($h, LOCK_EX | LOCK_NB);
        if ($got) {
            flock($h, LOCK_UN);
        }
        fclose($h);

        return ! $got;
    }

    /**
     * Compact payload for the page (no per-task detail beyond failures).
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function toApiResponse(array $state): array
    {
        $done = (int) $state['ok_count'] + (int) $state['fail_count'];
        $failed = [];
        foreach ((array) $state['tasks'] as $t) {
            if (is_array($t) && ($t['status'] ?? '') === 'failed') {
                $failed[] = ['sku' => $t['sku'] ?? '', 'error' => $t['error'] ?? ''];
            }
        }

        return [
            'success' => true,
            'status' => $state['status'],
            'active' => $this->isActive($state),
            'total' => (int) $state['total'],
            'done' => $done,
            'ok_count' => (int) $state['ok_count'],
            'fail_count' => (int) $state['fail_count'],
            'current_sku' => $state['current_sku'],
            'last_message' => $state['last_message'],
            'started_at' => $state['started_at'],
            'finished_at' => $state['finished_at'],
            'failed' => array_slice($failed, 0, 25),
            'messages' => array_slice((array) $state['messages'], -15),
        ];
    }
}
