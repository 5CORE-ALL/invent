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
 * Fast-path: push SKUs from live Shopify → one marketplace (runs on that channel's queue).
 * Use dispatchToEnabled() for webhooks so all enabled channels update in parallel.
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
        public string $marketplace = 'reverb',
    ) {
        $this->marketplace = strtolower(trim($this->marketplace));
        $this->onQueue(MarketplaceManagerRegistry::queueFor($this->marketplace));
        $this->skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ), static fn ($s) => $s !== '')));
    }

    /**
     * Fan-out to every marketplace that has inventory_sync enabled (parallel queues).
     *
     * @param  array<int, string>  $skus
     * @return int number of jobs dispatched
     */
    public static function dispatchToEnabled(
        array $skus,
        ?int $availableHint = null,
        ?string $inventoryItemId = null,
    ): int {
        $dispatched = 0;
        foreach (MarketplaceManagerRegistry::slugs() as $slug) {
            $settings = MarketplaceSyncSettings::getFor($slug);
            if (! ($settings['inventory']['inventory_sync'] ?? false)) {
                continue;
            }
            static::dispatch($skus, $availableHint, $inventoryItemId, $slug);
            $dispatched++;
        }

        return $dispatched;
    }

    public function uniqueId(): string
    {
        $normalized = $this->skus;
        sort($normalized);
        $key = strtoupper(implode('|', $normalized));
        if ($key === '' && $this->inventoryItemId) {
            $key = 'iid:'.$this->inventoryItemId;
        }

        return 'mm-push-inv-'.$this->marketplace.'-'.md5($key !== '' ? $key : 'empty');
    }

    public function handle(
        ReverbInventorySyncService $reverb,
        AliexpressInventorySyncService $aliexpress,
        AlibabaInventorySyncService $alibaba,
        NeweggInventorySyncService $newegg,
    ): void {
        if ($this->skus === []) {
            Log::info('PushLinkedSkuInventoryFromShopify: no SKUs resolved', [
                'marketplace' => $this->marketplace,
                'inventory_item_id' => $this->inventoryItemId,
            ]);

            return;
        }

        if (! $this->inventorySyncEnabled($this->marketplace)) {
            Log::info('PushLinkedSkuInventoryFromShopify: inventory_sync off — skip', [
                'marketplace' => $this->marketplace,
                'skus' => $this->skus,
            ]);

            return;
        }

        $service = match ($this->marketplace) {
            'reverb' => $reverb,
            'aliexpress' => $aliexpress,
            'alibaba' => $alibaba,
            'newegg' => $newegg,
            default => null,
        };

        if ($service === null) {
            Log::warning('PushLinkedSkuInventoryFromShopify: unknown marketplace', [
                'marketplace' => $this->marketplace,
            ]);

            return;
        }

        try {
            $result = $service->syncSkusFromShopify($this->skus);
        } catch (\Throwable $e) {
            Log::error('PushLinkedSkuInventoryFromShopify: marketplace push failed', [
                'marketplace' => $this->marketplace,
                'skus' => $this->skus,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        Log::info('PushLinkedSkuInventoryFromShopify: done', [
            'marketplace' => $this->marketplace,
            'skus' => $this->skus,
            'available_hint' => $this->availableHint,
            'inventory_item_id' => $this->inventoryItemId,
            'result' => $result,
        ]);
    }

    protected function inventorySyncEnabled(string $marketplace): bool
    {
        $settings = MarketplaceSyncSettings::getFor($marketplace);

        return (bool) ($settings['inventory']['inventory_sync'] ?? false);
    }
}
