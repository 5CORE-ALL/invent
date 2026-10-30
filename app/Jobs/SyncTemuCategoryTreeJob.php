<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\TemuCategoryTree;
use App\Services\Temu2ApiService;
use App\Services\TemuApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/** Downloads the Temu category tree (names for category IDs) in the background. */
class SyncTemuCategoryTreeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3300;

    public function __construct(public string $shopKey = 'temu')
    {
        $this->onQueue(RefreshListingChannelJob::QUEUE);
    }

    public static function lockKey(string $shopKey): string
    {
        return 'temu_category_tree_job:'.$shopKey;
    }

    /** Queue the download once (not again while one is waiting / running). */
    public static function dispatchOnce(string $shopKey): bool
    {
        if (TemuCategoryTree::isComplete($shopKey) || ! Cache::add(self::lockKey($shopKey), 1, now()->addMinutes(45))) {
            return false;
        }
        static::dispatch($shopKey);

        return true;
    }

    public function handle(): void
    {
        try {
            $api = $this->shopKey === 'temu2' ? app(Temu2ApiService::class) : app(TemuApiService::class);
            (new TemuCategoryTree($api, $this->shopKey))->crawl(3000);
        } finally {
            Cache::forget(self::lockKey($this->shopKey));
        }
    }

    public function failed(?\Throwable $e): void
    {
        Cache::forget(self::lockKey($this->shopKey));
    }
}
