<?php

namespace App\Services\Support;

use App\Http\Controllers\ProductMaster\ImageMasterController;
use Illuminate\Http\Request;

class ShopifyImagePullRunner
{
    /** Process this many SKUs, then take a longer pause (rate-limit / timeout safety). */
    private const CHUNK_SIZE = 25;

    /** Extra seconds to wait after each completed chunk before continuing. */
    private const CHUNK_PAUSE_SECONDS = 12;

    /** Retry transient Shopify/API failures per SKU. */
    private const MAX_ATTEMPTS_PER_SKU = 3;

    public function __construct(
        private readonly ShopifyImagePullJobStore $store,
    ) {}

    public function run(): int
    {
        @set_time_limit(0);

        $processedInChunk = 0;

        while (true) {
            $state = $this->store->load();
            $status = $state['status'] ?? 'idle';

            if ($status === 'stopping') {
                $this->store->update(function (array $state) {
                    $state['status'] = 'stopped';
                    $state['finished_at'] = now()->toDateTimeString();
                    $state['last_message'] = 'Stopped by user.';

                    return $state;
                });
                $this->store->appendMessage('Stopped by user.', false);

                return 0;
            }

            if ($status === 'paused') {
                sleep(2);
                continue;
            }

            if ($status !== 'running') {
                return 0;
            }

            $index = (int) ($state['current_index'] ?? 0);
            $skus = array_values($state['skus'] ?? []);
            $total = count($skus);

            if ($index >= $total) {
                $this->store->update(function (array $state) {
                    $state['status'] = 'completed';
                    $state['current_sku'] = null;
                    $state['finished_at'] = now()->toDateTimeString();
                    $state['last_message'] = "Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.";

                    return $state;
                });
                $state = $this->store->load();
                $this->store->appendMessage(
                    "Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.",
                    ((int) ($state['fail_count'] ?? 0)) === 0
                );

                return 0;
            }

            $sku = trim((string) ($skus[$index] ?? ''));
            if ($sku === '') {
                $this->advance(false, 'Blank SKU skipped.');
                $processedInChunk++;
                if ($this->maybePauseBetweenChunks($processedInChunk, $index + 1, $total)) {
                    $processedInChunk = 0;
                }
                continue;
            }

            $this->store->update(function (array $state) use ($sku, $index, $total) {
                $state['current_sku'] = $sku;
                $state['last_message'] = 'Pulling '.($index + 1)."/{$total}: {$sku}";

                return $state;
            });

            [$ok, $message] = $this->pullSkuWithRetries($sku);

            $this->advance($ok, $message);
            $processedInChunk++;
            $this->delayBeforeNextSku();

            if ($this->maybePauseBetweenChunks($processedInChunk, $index + 1, $total)) {
                $processedInChunk = 0;
            }
        }
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function pullSkuWithRetries(string $sku): array
    {
        $ok = false;
        $message = "{$sku}: failed - Unable to pull Shopify images.";

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS_PER_SKU; $attempt++) {
            if (($this->store->load()['status'] ?? 'idle') !== 'running') {
                return [$ok, $message];
            }

            try {
                $response = app(ImageMasterController::class)->pullShopifyImagesToMaster(new Request(['sku' => $sku]));
                $payload = method_exists($response, 'getData') ? $response->getData(true) : [];
                $ok = (bool) ($payload['success'] ?? false);
                $message = $ok
                    ? "{$sku}: {$payload['status']} - ".count($payload['shopify_images'] ?? []).' image(s) from '.($payload['source'] ?? 'shopify')
                    : "{$sku}: failed - ".($payload['message'] ?? 'Unable to pull Shopify images.');
            } catch (\Throwable $e) {
                $ok = false;
                $message = "{$sku}: failed - {$e->getMessage()}";
            }

            if ($ok || ! $this->isRetryablePullFailure($message)) {
                break;
            }

            if ($attempt < self::MAX_ATTEMPTS_PER_SKU) {
                $this->store->appendMessage(
                    "{$sku}: retry {$attempt}/".(self::MAX_ATTEMPTS_PER_SKU - 1).' after transient error…',
                    false
                );
                $this->interruptibleSleep(min(8, 2 * $attempt));
            }
        }

        return [$ok, $message];
    }

    private function isRetryablePullFailure(string $message): bool
    {
        $haystack = strtolower($message);

        foreach ([
            'timeout',
            'timed out',
            'rate limit',
            'too many requests',
            '429',
            '502',
            '503',
            '504',
            'connection',
            'curl error',
            'temporarily',
            'try again',
        ] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function maybePauseBetweenChunks(int $processedInChunk, int $doneCount, int $total): bool
    {
        if ($processedInChunk < self::CHUNK_SIZE || $doneCount >= $total) {
            return false;
        }
        if (($this->store->load()['status'] ?? 'idle') !== 'running') {
            return false;
        }

        $chunkNum = (int) ceil($doneCount / self::CHUNK_SIZE);
        $msg = "Chunk {$chunkNum} done ({$doneCount}/{$total}). Pausing ".self::CHUNK_PAUSE_SECONDS.'s before next chunk…';
        $this->store->update(function (array $state) use ($msg) {
            $state['last_message'] = $msg;

            return $state;
        });
        $this->store->appendMessage($msg, true);
        $this->interruptibleSleep(self::CHUNK_PAUSE_SECONDS);

        return true;
    }

    private function advance(bool $ok, string $message): void
    {
        $this->store->update(function (array $state) use ($ok, $message) {
            $state['current_index'] = ((int) ($state['current_index'] ?? 0)) + 1;
            $state[$ok ? 'ok_count' : 'fail_count'] = ((int) ($state[$ok ? 'ok_count' : 'fail_count'] ?? 0)) + 1;
            $state['last_message'] = $message;

            return $state;
        });
        $this->store->appendMessage($message, $ok);
    }

    private function delayBeforeNextSku(): void
    {
        $delay = max(1, (int) ($this->store->load()['delay_seconds'] ?? 6));
        $this->interruptibleSleep($delay);
    }

    private function interruptibleSleep(int $seconds): void
    {
        for ($i = 0; $i < max(0, $seconds); $i++) {
            $status = $this->store->load()['status'] ?? 'idle';
            if ($status !== 'running') {
                return;
            }
            sleep(1);
        }
    }
}
