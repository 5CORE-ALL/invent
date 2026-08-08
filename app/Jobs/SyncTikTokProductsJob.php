<?php

namespace App\Jobs;

use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\TikTokLinkMapSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncTikTokProductsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public int $uniqueFor = 1900;

    public function __construct(
        public string $channel = 'tiktok',
        public bool $productsOnly = true,
    ) {
        $channel = strtolower(trim($channel));
        $this->channel = in_array($channel, ['tiktok', 'tiktok2'], true) ? $channel : 'tiktok';
        // Dedicated listings queue so this does not wait behind 200+ order/inventory jobs.
        $this->onQueue(self::listingsQueueFor($this->channel));
    }

    public static function listingsQueueFor(string $channel): string
    {
        $channel = in_array($channel, ['tiktok', 'tiktok2'], true) ? $channel : 'tiktok';

        return MarketplaceManagerRegistry::queueFor($channel).'-listings';
    }

    public function uniqueId(): string
    {
        return 'mm-'.$this->channel.'-products-sync';
    }

    public static function cacheKey(string $channel): string
    {
        $channel = in_array($channel, ['tiktok', 'tiktok2'], true) ? $channel : 'tiktok';

        return 'mm:'.$channel.':products-sync-progress';
    }

    public static function getProgress(string $channel): array
    {
        $channel = in_array($channel, ['tiktok', 'tiktok2'], true) ? $channel : 'tiktok';
        $key = self::cacheKey($channel);
        $progress = Cache::get($key);
        if (! is_array($progress) || $progress === []) {
            $progress = self::readProgressFile($channel) ?: [
                'status' => 'idle',
                'message' => 'No listing sync in progress.',
                'count' => 0,
            ];
        }

        $status = (string) ($progress['status'] ?? 'idle');
        $pending = self::pendingListingsJobCount($channel);
        $progress['queue'] = self::listingsQueueFor($channel);
        $progress['queued_jobs'] = $pending;

        // Deploy/cache clear can wipe progress while the job is still waiting/running.
        if (in_array($status, ['idle', 'done', 'failed'], true) && $pending > 0) {
            $progress['status'] = 'queued';
            $progress['message'] = 'Listing sync is waiting on queue '.$progress['queue']
                .' ('.$pending.' job(s) pending).';
        }

        return $progress;
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    public static function putProgress(string $channel, array $patch): void
    {
        $key = self::cacheKey($channel);
        $current = Cache::get($key);
        if (! is_array($current) || $current === []) {
            $current = self::readProgressFile($channel) ?: [];
        }
        $merged = array_merge($current, $patch, [
            'channel' => $channel,
            'updated_at' => now()->toDateTimeString(),
        ]);
        Cache::put($key, $merged, now()->addHours(2));
        self::writeProgressFile($channel, $merged);
    }

    /** Clear stuck UI / unique-job progress after a hung listings sync. */
    public static function clearStuck(string $channel): void
    {
        $channel = in_array($channel, ['tiktok', 'tiktok2'], true) ? $channel : 'tiktok';
        Cache::forget(self::cacheKey($channel));
        $path = self::progressFilePath($channel);
        if (is_file($path)) {
            @unlink($path);
        }
        // Laravel unique job lock (database / cache driver).
        try {
            Cache::lock('laravel_unique_job:App\Jobs\SyncTikTokProductsJob:mm-'.$channel.'-products-sync')->forceRelease();
        } catch (\Throwable) {
            // Best-effort.
        }
        self::putProgress($channel, [
            'status' => 'idle',
            'message' => 'Listing sync cleared.',
            'count' => 0,
            'finished_at' => now()->toDateTimeString(),
        ]);
    }

    public static function pendingListingsJobCount(string $channel): int
    {
        if (! Schema::hasTable('jobs')) {
            return 0;
        }

        $queue = self::listingsQueueFor($channel);

        try {
            return (int) DB::table('jobs')->where('queue', $queue)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * File backup survives `cache:clear` / deploy so the UI does not false-fail mid-sync.
     *
     * @return array<string, mixed>|null
     */
    protected static function readProgressFile(string $channel): ?array
    {
        $path = self::progressFilePath($channel);
        if (! is_file($path)) {
            return null;
        }
        try {
            $decoded = json_decode((string) file_get_contents($path), true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    protected static function writeProgressFile(string $channel, array $progress): void
    {
        try {
            $path = self::progressFilePath($channel);
            $dir = dirname($path);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($path, json_encode($progress, JSON_PRETTY_PRINT));
        } catch (\Throwable) {
            // Best-effort only.
        }
    }

    protected static function progressFilePath(string $channel): string
    {
        return storage_path('app/mm-listings-sync-'.$channel.'.json');
    }

    public function handle(): void
    {
        $channel = $this->channel;
        $label = $channel === 'tiktok2' ? 'TikTok 2' : 'TikTok Shop';

        self::putProgress($channel, [
            'status' => 'running',
            'message' => "Fetching products from {$label} API…",
            'started_at' => now()->toDateTimeString(),
            'finished_at' => null,
            'exit_code' => null,
        ]);

        try {
            // Products-only / link-map path: auto quick-or-full paged sync (no mega hang).
            if ($this->productsOnly) {
                self::putProgress($channel, [
                    'status' => 'running',
                    'message' => "{$label} auto link-map sync (quick or full)…",
                ]);

                $result = TikTokLinkMapSyncService::for($channel)->syncAll('auto', 50);
                $count = $this->skuCount($channel);
                $mode = (string) ($result['mode'] ?? 'auto');

                if (empty($result['success'])) {
                    self::putProgress($channel, [
                        'status' => 'failed',
                        'message' => $result['message'] ?? "{$label} link-map sync failed.",
                        'count' => $count,
                        'mode' => $mode,
                        'exit_code' => 1,
                        'finished_at' => now()->toDateTimeString(),
                    ]);
                    Log::error('SyncTikTokProductsJob link-map failed', [
                        'channel' => $channel,
                        'mode' => $mode,
                        'message' => $result['message'] ?? '',
                    ]);

                    return;
                }

                self::putProgress($channel, [
                    'status' => 'done',
                    'message' => $result['message'] ?: "{$label} link-map synced ({$count} SKUs, mode={$mode}).",
                    'count' => $count,
                    'mode' => $mode,
                    'upserted' => (int) ($result['upserted'] ?? 0),
                    'exit_code' => 0,
                    'finished_at' => now()->toDateTimeString(),
                ]);

                Log::info('SyncTikTokProductsJob link-map completed', [
                    'channel' => $channel,
                    'mode' => $mode,
                    'upserted' => $result['upserted'] ?? 0,
                    'count' => $count,
                ]);

                return;
            }

            $exit = Artisan::call('sync:tiktok-api-data', ['--channel' => $channel]);
            $output = trim(Artisan::output());
            $count = $this->skuCount($channel);

            if ($exit !== 0) {
                self::putProgress($channel, [
                    'status' => 'failed',
                    'message' => "{$label} product sync failed.",
                    'output' => mb_substr($output, -2000),
                    'count' => $count,
                    'exit_code' => $exit,
                    'finished_at' => now()->toDateTimeString(),
                ]);
                Log::error('SyncTikTokProductsJob failed', [
                    'channel' => $channel,
                    'exit' => $exit,
                    'output' => mb_substr($output, -1000),
                ]);

                return;
            }

            if ($channel === 'tiktok2') {
                WarmTikTok2LiveListingsCache::dispatch();
            } else {
                WarmTikTokLiveListingsCache::dispatch();
            }

            self::putProgress($channel, [
                'status' => 'done',
                'message' => "{$label} products synced ({$count} SKUs).",
                'output' => mb_substr($output, -2000),
                'count' => $count,
                'exit_code' => 0,
                'finished_at' => now()->toDateTimeString(),
            ]);

            Log::info('SyncTikTokProductsJob completed', [
                'channel' => $channel,
                'count' => $count,
            ]);
        } catch (\Throwable $e) {
            self::putProgress($channel, [
                'status' => 'failed',
                'message' => 'Product sync error: '.$e->getMessage(),
                'finished_at' => now()->toDateTimeString(),
                'exit_code' => 1,
            ]);
            Log::error('SyncTikTokProductsJob exception', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function skuCount(string $channel): int
    {
        if ($channel === 'tiktok2') {
            if (! Schema::hasTable('tiktok_products_two')) {
                return 0;
            }

            return (int) TikTokProductTwo::query()->whereNotNull('sku')->where('sku', '!=', '')->count();
        }

        if (! Schema::hasTable('tiktok_products')) {
            return 0;
        }

        return (int) TikTokProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->count();
    }
}
