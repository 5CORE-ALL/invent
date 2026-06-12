<?php

namespace App\Services\Support;

class ShopifyBulletPullJobStore
{
    private const MAX_MESSAGES = 120;

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

    public function create(array $skus, int $delaySeconds = 6): array
    {
        $cleanSkus = array_values(array_unique(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $skus
        ))));

        $state = array_merge($this->defaultState(), [
            'id' => date('YmdHis'),
            'status' => 'running',
            'skus' => $cleanSkus,
            'total' => count($cleanSkus),
            'delay_seconds' => max(1, $delaySeconds),
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
            'last_message' => 'Background Shopify pull started.',
            'messages' => [[
                'time' => now()->format('H:i:s'),
                'ok' => true,
                'message' => 'Background Shopify pull started.',
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

    public function appendMessage(string $message, bool $ok = true, array $extra = []): array
    {
        return $this->update(function (array $state) use ($message, $ok, $extra) {
            $messages = $state['messages'] ?? [];
            $messages[] = array_merge([
                'time' => now()->format('H:i:s'),
                'ok' => $ok,
                'message' => $message,
            ], $extra);

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
        return in_array($state['status'] ?? 'idle', ['running', 'paused', 'stopping'], true);
    }

    private function defaultState(): array
    {
        return [
            'id' => null,
            'status' => 'idle',
            'skus' => [],
            'total' => 0,
            'current_index' => 0,
            'current_sku' => null,
            'ok_count' => 0,
            'fail_count' => 0,
            'delay_seconds' => 6,
            'started_at' => null,
            'finished_at' => null,
            'updated_at' => null,
            'last_message' => 'Ready',
            'messages' => [],
        ];
    }

    private function path(): string
    {
        return storage_path('app/shopify-bullet-pull/job.json');
    }

    private function ensureDirectory(): void
    {
        $dir = dirname($this->path());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
