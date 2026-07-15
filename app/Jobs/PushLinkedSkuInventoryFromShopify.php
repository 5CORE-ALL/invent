<?php

namespace App\Jobs;

use App\Models\MarketplaceSyncSettings;
use App\Services\MarketplaceManager\AlibabaInventorySyncService;
use App\Services\MarketplaceManager\AliexpressInventorySyncService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\MarketplaceManager\NeweggInventorySyncService;
use App\Services\MarketplaceManager\ReverbInventorySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fast-path: push one (or a few) SKUs from live Shopify to every enabled marketplace
 * where the SKU is linked. Used by Shopify inventory webhooks.
 */
class PushLinkedSkuInventoryFromShopify implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 120;

    /**
     * @param  array<int, string>  $skus
     * @param  int|null  $availableHint  Available qty from Shopify webhook (optional; live API still authoritative)
     */
    public function __construct(
        public array $skus,
        public ?int $availableHint = null,
        public ?string $inventoryItemId = null,
    ) {
        $this->onQueue(MarketplaceManagerRegistry::QUEUE);
        $this->skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ), static fn ($s) => $s !== '')));
    }

    public function uniqueId(): string
    {
        // Deduplicate by sorted SKU set so page refresh / webhook storms don't flood the queue.
        $normalized = $this->skus;
        sort($normalized);
        $key = strtoupper(implode('|', $normalized));
        if ($key === '' && $this->inventoryItemId) {
            $key = 'iid:'.$this->inventoryItemId;
        }

        return 'mm-push-inv-'.md5($key !== '' ? $key : 'empty');
    }

    public function handle(
        ReverbInventorySyncService $reverb,
        AliexpressInventorySyncService $aliexpress,
        AlibabaInventorySyncService $alibaba,
        NeweggInventorySyncService $newegg,
    ): void {
        if ($this->skus === []) {
            Log::info('PushLinkedSkuInventoryFromShopify: no SKUs resolved', [
                'inventory_item_id' => $this->inventoryItemId,
            ]);

            return;
        }

        $results = [];

        foreach ([
            'reverb' => $reverb,
            'aliexpress' => $aliexpress,
            'alibaba' => $alibaba,
            'newegg' => $newegg,
        ] as $slug => $service) {
            if (! $this->inventorySyncEnabled($slug)) {
                continue;
            }
            try {
                $results[$slug] = $service->syncSkusFromShopify($this->skus);
            } catch (\Throwable $e) {
                // Never abort other marketplaces — wrong stock on one channel is better
                // than failing all channels mid-push.
                Log::error('PushLinkedSkuInventoryFromShopify: marketplace push failed', [
                    'marketplace' => $slug,
                    'skus' => $this->skus,
                    'error' => $e->getMessage(),
                ]);
                $results[$slug] = [
                    'updated' => 0,
                    'failed' => count($this->skus),
                    'message' => $e->getMessage(),
                ];
            }
        }

        Log::info('PushLinkedSkuInventoryFromShopify: done', [
            'skus' => $this->skus,
            'available_hint' => $this->availableHint,
            'inventory_item_id' => $this->inventoryItemId,
            'results' => $results,
        ]);
    }

    protected function inventorySyncEnabled(string $marketplace): bool
    {
        $settings = MarketplaceSyncSettings::getFor($marketplace);

        return (bool) ($settings['inventory']['inventory_sync'] ?? false);
    }
}
