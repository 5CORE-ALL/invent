<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Queue Shopify imports for marketplace orders that still have no shopify_order_id.
 * Does not re-fetch marketplace APIs. Skips channels where auto_import_to_shopify is Off.
 *
 * Runs every Marketplace Manager channel by default (including Reverb / Newegg / Shein).
 * Historical dumps are limited inside each OrderSyncService (date cutoffs / last 14 days).
 */
class DispatchUnpushedMarketplaceShopifyImports extends Command
{
    protected $signature = 'mm:dispatch-unpushed-shopify
        {--marketplace= : Only this MM slug (tiktok, amazon, …)}';

    protected $description = 'Dispatch Shopify import jobs for unpushed Marketplace Manager orders';

    public function handle(): int
    {
        $only = strtolower(trim((string) $this->option('marketplace')));
        $map = $this->services();

        if ($only !== '') {
            if (! isset($map[$only])) {
                $this->error('Unknown marketplace: '.$only);

                return self::FAILURE;
            }
            $map = [$only => $map[$only]];
        }

        $total = 0;
        foreach ($map as $slug => $class) {
            try {
                $n = (int) app($class)->dispatchImportsForNewOrders();
                $total += $n;
                if ($n > 0) {
                    $this->info("{$slug}: queued {$n} import job(s).");
                    Log::info('mm:dispatch-unpushed-shopify', ['marketplace' => $slug, 'dispatched' => $n]);
                } else {
                    $this->line("{$slug}: none.");
                }
            } catch (\Throwable $e) {
                $this->warn("{$slug}: ".$e->getMessage());
                Log::warning('mm:dispatch-unpushed-shopify failed', [
                    'marketplace' => $slug,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Total queued: {$total}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, class-string>
     */
    protected function services(): array
    {
        return [
            'amazon' => \App\Services\MarketplaceManager\AmazonOrderSyncService::class,
            'aliexpress' => \App\Services\MarketplaceManager\AliexpressOrderSyncService::class,
            'alibaba' => \App\Services\MarketplaceManager\AlibabaOrderSyncService::class,
            'reverb' => \App\Services\MarketplaceManager\ReverbOrderSyncService::class,
            'newegg' => \App\Services\MarketplaceManager\NeweggOrderSyncService::class,
            'shein' => \App\Services\MarketplaceManager\SheinOrderSyncService::class,
            'topdawg' => \App\Services\MarketplaceManager\TopDawgOrderSyncService::class,
            'temu' => \App\Services\MarketplaceManager\TemuOrderSyncService::class,
            'temu2' => \App\Services\MarketplaceManager\Temu2OrderSyncService::class,
            'purchasingpower' => \App\Services\MarketplaceManager\PurchasingPowerOrderSyncService::class,
            'wayfair' => \App\Services\MarketplaceManager\WayfairOrderSyncService::class,
            'bestbuy' => \App\Services\MarketplaceManager\BestBuyOrderSyncService::class,
            'macy' => \App\Services\MarketplaceManager\MacyOrderSyncService::class,
            'doba' => \App\Services\MarketplaceManager\DobaOrderSyncService::class,
            'ebay1' => \App\Services\MarketplaceManager\Ebay1OrderSyncService::class,
            'ebay2' => \App\Services\MarketplaceManager\Ebay2OrderSyncService::class,
            'ebay3' => \App\Services\MarketplaceManager\Ebay3OrderSyncService::class,
            'faire' => \App\Services\MarketplaceManager\FaireOrderSyncService::class,
            'tiktok' => \App\Services\MarketplaceManager\TikTokOrderSyncService::class,
            'tiktok2' => \App\Services\MarketplaceManager\TikTok2OrderSyncService::class,
        ];
    }
}
