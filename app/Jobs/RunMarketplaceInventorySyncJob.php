<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\AlibabaInventorySyncService;
use App\Services\MarketplaceManager\AliexpressInventorySyncService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\AmazonInventorySyncService;
use App\Services\MarketplaceManager\FaireInventorySyncService;
use App\Services\MarketplaceManager\NeweggInventorySyncService;
use App\Services\MarketplaceManager\ReverbInventorySyncService;
use App\Services\MarketplaceManager\TikTok2InventorySyncService;
use App\Services\MarketplaceManager\TikTokInventorySyncService;
use App\Services\MarketplaceManager\WayfairInventorySyncService;
use App\Services\MarketplaceManager\BestBuyInventorySyncService;
use App\Services\MarketplaceManager\MacyInventorySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Full marketplace inventory sync from live Shopify (button / manual).
 * Must not run inside an HTTP request — Reverb alone can take 5–15+ minutes.
 */
class RunMarketplaceInventorySyncJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public int $uniqueFor = 1800;

    public function __construct(
        public string $marketplace
    ) {
        $this->onQueue(MarketplaceManagerRegistry::queueFor($this->marketplace));
    }

    public function uniqueId(): string
    {
        return 'mm-inv-sync-'.$this->marketplace;
    }

    public function handle(): void
    {
        $slug = strtolower(trim($this->marketplace));
        Log::info('RunMarketplaceInventorySyncJob: start', ['marketplace' => $slug]);

        $result = match ($slug) {
            'reverb' => app(ReverbInventorySyncService::class)->syncFromShopify(false),
            'aliexpress' => app(AliexpressInventorySyncService::class)->syncFromShopify(false),
            'alibaba' => app(AlibabaInventorySyncService::class)->syncFromShopify(false),
            'newegg' => app(NeweggInventorySyncService::class)->syncFromShopify(false),
            'faire' => app(FaireInventorySyncService::class)->syncFromShopify(false),
            'amazon' => app(AmazonInventorySyncService::class)->syncFromShopify(false),
            'wayfair' => app(WayfairInventorySyncService::class)->syncFromShopify(false),
            'bestbuy' => app(BestBuyInventorySyncService::class)->syncFromShopify(false),
            'macy' => app(MacyInventorySyncService::class)->syncFromShopify(false),
            'doba' => app(DobaInventorySyncService::class)->syncFromShopify(false),
            'tiktok2' => app(TikTok2InventorySyncService::class)->syncFromShopify(false),
            'tiktok' => app(TikTokInventorySyncService::class)->syncFromShopify(false),
            default => ['updated' => 0, 'failed' => 0, 'message' => 'Unknown marketplace: '.$slug],
        };

        Log::info('RunMarketplaceInventorySyncJob: done', [
            'marketplace' => $slug,
            'updated' => $result['updated'] ?? null,
            'failed' => $result['failed'] ?? null,
            'message' => $result['message'] ?? null,
        ]);
    }
}
