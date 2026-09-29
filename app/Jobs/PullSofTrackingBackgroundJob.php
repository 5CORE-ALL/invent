<?php

namespace App\Jobs;

use App\Http\Controllers\Channels\SalesOrderFulfillmentController;
use App\Services\MarketplaceManager\ChannelTrackingApiFallbackService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\Temu2OrderTrackingPullService;
use App\Services\MarketplaceManager\TemuOrderTrackingPullService;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Continues Sales Order Fulfillment Pull Tracking after the browser leaves the page.
 */
class PullSofTrackingBackgroundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_KEY = 'sof.pull.bg.queue';

    public const STATUS_KEY = 'sof.pull.bg.status';

    public const LOCK_KEY = 'sof.pull.bg.lock';

    public int $tries = 1;

    public int $timeout = 1700;

    public function __construct()
    {
        $this->onQueue(MarketplaceManagerRegistry::QUEUE_TRACKING);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function enqueue(array $rows): array
    {
        $added = 0;
        Cache::lock('sof.pull.bg.mutate', 15)->block(8, function () use ($rows, &$added): void {
            $queue = Cache::get(self::QUEUE_KEY, []);
            if (! is_array($queue)) {
                $queue = [];
            }
            $seen = [];
            foreach ($queue as $existing) {
                if (is_array($existing)) {
                    $seen[self::rowKey($existing)] = true;
                }
            }
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $key = self::rowKey($row);
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $queue[] = $row;
                $added++;
            }
            Cache::put(self::QUEUE_KEY, $queue, now()->addHours(6));
        });

        $status = Cache::get(self::STATUS_KEY, []);
        if (! is_array($status)) {
            $status = [];
        }
        $running = ($status['state'] ?? '') === 'running';
        if (! $running) {
            $status = [
                'state' => 'running',
                'total' => $added,
                'checked' => 0,
                'updated' => 0,
                'with_tracking' => 0,
                'empty' => 0,
                'message' => 'Pulling tracking in the background.',
                'started_at' => now()->toDateTimeString(),
            ];
        } else {
            $status['total'] = (int) ($status['total'] ?? 0) + $added;
            $status['state'] = 'running';
            $status['message'] = 'Pulling tracking in the background.';
        }
        $status['updated_at'] = now()->toDateTimeString();
        Cache::put(self::STATUS_KEY, $status, now()->addHours(6));

        if ($added > 0 || $running || self::pendingCount() > 0) {
            self::ensureRunning();
        }

        $status['queued'] = self::pendingCount();
        $status['added'] = $added;

        return $status;
    }

    public static function ensureRunning(): void
    {
        if (self::pendingCount() < 1) {
            return;
        }
        if (! Cache::add(self::LOCK_KEY, 1, now()->addMinutes(20))) {
            return;
        }
        self::dispatch();
    }

    public static function pendingCount(): int
    {
        $queue = Cache::get(self::QUEUE_KEY, []);

        return is_array($queue) ? count($queue) : 0;
    }

    /**
     * @return array<string, mixed>
     */
    public static function status(): array
    {
        if (self::pendingCount() > 0) {
            self::ensureRunning();
        }
        $status = Cache::get(self::STATUS_KEY, []);
        if (! is_array($status)) {
            $status = [];
        }
        $queued = self::pendingCount();
        $state = (string) ($status['state'] ?? 'idle');
        if ($state === 'running' && $queued === 0 && ! Cache::has(self::LOCK_KEY)) {
            $state = ((int) ($status['checked'] ?? 0) > 0) ? 'done' : 'idle';
            $status['state'] = $state;
        }

        return [
            'state' => $state,
            'total' => (int) ($status['total'] ?? 0),
            'checked' => (int) ($status['checked'] ?? 0),
            'updated' => (int) ($status['updated'] ?? 0),
            'with_tracking' => (int) ($status['with_tracking'] ?? 0),
            'empty' => (int) ($status['empty'] ?? 0),
            'queued' => $queued,
            'message' => (string) ($status['message'] ?? ''),
        ];
    }

    public function handle(): void
    {
        @set_time_limit(0);
        $this->refreshLock();
        $started = microtime(true);

        while ((microtime(true) - $started) < 1400) {
            $batch = $this->shift(8);
            if ($batch === []) {
                break;
            }
            $this->refreshLock();
            try {
                $response = app(SalesOrderFulfillmentController::class)->pullTrackingNumbers(
                    Request::create('/sales-order-fulfillment/pull-tracking-numbers', 'POST', [
                        'selected' => $batch,
                        'selected_only' => true,
                        'limit' => count($batch),
                        'background' => false,
                    ]),
                    app(TemuOrderTrackingPullService::class),
                    app(Temu2OrderTrackingPullService::class),
                    app(ChannelTrackingApiFallbackService::class),
                    app(VeeqoShopifyFulfillmentService::class),
                );
                $payload = $response->getData(true);
                if (! is_array($payload)) {
                    $payload = [];
                }
                $processed = [];
                foreach ((array) ($payload['processed_keys'] ?? []) as $key) {
                    $key = trim((string) $key);
                    if ($key !== '') {
                        $processed[$key] = true;
                    }
                }
                $leftover = [];
                if (! empty($payload['truncated'])) {
                    foreach ($batch as $row) {
                        if (isset($processed[self::rowKey($row)])) {
                            continue;
                        }
                        $attempts = (int) ($row['_attempts'] ?? 0) + 1;
                        if ($attempts >= 3) {
                            continue;
                        }
                        $row['_attempts'] = $attempts;
                        $leftover[] = $row;
                    }
                }
                if ($leftover !== []) {
                    $this->unshift($leftover);
                }
                $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
                $this->addProgress(
                    max((int) ($summary['checked'] ?? 0), count($batch) - count($leftover)),
                    (int) ($summary['updated'] ?? 0),
                    (int) ($summary['with_tracking'] ?? 0),
                    (int) ($summary['empty'] ?? 0),
                    (string) ($payload['message'] ?? '')
                );
            } catch (\Throwable $e) {
                Log::warning('PullSofTrackingBackgroundJob batch failed', ['error' => $e->getMessage()]);
                $this->addProgress(count($batch), 0, 0, count($batch), $e->getMessage());
            }
        }

        if (self::pendingCount() > 0) {
            $this->refreshLock();
            self::dispatch();

            return;
        }

        $status = Cache::get(self::STATUS_KEY, []);
        if (! is_array($status)) {
            $status = [];
        }
        $status['state'] = 'done';
        $status['message'] = 'Background pull finished. Checked '.(int) ($status['checked'] ?? 0)
            .', saved tracking on '.(int) ($status['updated'] ?? 0).'.';
        $status['updated_at'] = now()->toDateTimeString();
        Cache::put(self::STATUS_KEY, $status, now()->addMinutes(30));
        Cache::forget(self::LOCK_KEY);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function rowKey(array $row): string
    {
        $id = trim((string) ($row['id'] ?? ''));
        if ($id !== '') {
            return $id;
        }
        $slug = strtolower(trim((string) ($row['mm_slug'] ?? '')));
        $show = (int) ($row['show_id'] ?? $row['row_id'] ?? 0);
        $order = trim((string) (
            $row['order_id_api']
            ?? $row['order_id']
            ?? $row['order_number']
            ?? $row['shopify_order_id']
            ?? ''
        ));

        return $slug.'|'.$show.'|'.$order;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function shift(int $limit): array
    {
        $batch = [];
        Cache::lock('sof.pull.bg.mutate', 15)->block(8, function () use ($limit, &$batch): void {
            $queue = Cache::get(self::QUEUE_KEY, []);
            if (! is_array($queue) || $queue === []) {
                $batch = [];

                return;
            }
            $batch = array_splice($queue, 0, max(1, $limit));
            Cache::put(self::QUEUE_KEY, array_values($queue), now()->addHours(6));
        });

        return array_values(array_filter($batch, 'is_array'));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function unshift(array $rows): void
    {
        Cache::lock('sof.pull.bg.mutate', 15)->block(8, function () use ($rows): void {
            $queue = Cache::get(self::QUEUE_KEY, []);
            if (! is_array($queue)) {
                $queue = [];
            }
            Cache::put(self::QUEUE_KEY, array_values(array_merge($rows, $queue)), now()->addHours(6));
        });
    }

    protected function addProgress(int $checked, int $updated, int $withTracking, int $empty, string $message): void
    {
        $status = Cache::get(self::STATUS_KEY, []);
        if (! is_array($status)) {
            $status = [];
        }
        $status['state'] = 'running';
        $status['checked'] = (int) ($status['checked'] ?? 0) + $checked;
        $status['updated'] = (int) ($status['updated'] ?? 0) + $updated;
        $status['with_tracking'] = (int) ($status['with_tracking'] ?? 0) + $withTracking;
        $status['empty'] = (int) ($status['empty'] ?? 0) + $empty;
        if ($message !== '') {
            $status['message'] = $message;
        }
        $status['updated_at'] = now()->toDateTimeString();
        Cache::put(self::STATUS_KEY, $status, now()->addHours(6));
    }

    protected function refreshLock(): void
    {
        Cache::put(self::LOCK_KEY, 1, now()->addMinutes(20));
    }
}
