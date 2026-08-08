<?php

namespace App\Jobs;

use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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
        $this->onQueue(MarketplaceManagerRegistry::queueFor($this->channel));
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

    /**
     * @param  array<string, mixed>  $patch
     */
    public static function putProgress(string $channel, array $patch): void
    {
        $key = self::cacheKey($channel);
        $current = Cache::get($key);
        $current = is_array($current) ? $current : [];
        Cache::put($key, array_merge($current, $patch, [
            'channel' => $channel,
            'updated_at' => now()->toDateTimeString(),
        ]), now()->addHours(2));
    }

    public static function getProgress(string $channel): array
    {
        $key = self::cacheKey($channel);
        $progress = Cache::get($key);

        return is_array($progress) ? $progress : [
            'status' => 'idle',
            'message' => 'No listing sync in progress.',
            'count' => 0,
        ];
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
            $params = ['--channel' => $channel];
            if ($this->productsOnly) {
                $params['--products-only'] = true;
            }

            $exit = Artisan::call('sync:tiktok-api-data', $params);
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
