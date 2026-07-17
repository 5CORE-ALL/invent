<?php

namespace App\Services\MarketplaceManager;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Resolves Shopify quantities for MM SKU fast-path pushes.
 * Ledger-first when MM_USE_INVENTORY_LEDGER=true; live API otherwise / for misses.
 */
class ShopifyQtySource
{
    public function __construct(
        protected InventoryLedgerService $ledger,
    ) {}

    public function useLedger(): bool
    {
        return (bool) config('marketplace_manager.use_inventory_ledger', false);
    }

    /**
     * @param  array<int, string>  $skus
     * @param  Closure(array<int, string>): array<string, int>  $liveFetcher
     * @return array<string, int>
     */
    public function fetchQuantitiesForPush(array $skus, Closure $liveFetcher): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ))));
        if ($skus === []) {
            return [];
        }

        if (! $this->useLedger()) {
            return $liveFetcher($skus);
        }

        $fromLedger = $this->ledger->qtyBySkus($skus);
        $missing = [];
        foreach ($skus as $sku) {
            if ($this->resolveQty($fromLedger, $sku) === null) {
                $missing[] = $sku;
            }
        }

        if ($missing === []) {
            return $fromLedger;
        }

        Log::info('ShopifyQtySource: ledger miss — live fallback', [
            'missing_count' => count($missing),
        ]);

        $live = $liveFetcher($missing);
        foreach ($missing as $sku) {
            $qty = $this->resolveQty($live, $sku);
            if ($qty === null) {
                continue;
            }
            $fromLedger[$sku] = $qty;
            try {
                $this->ledger->upsertMapping($sku, null, null, $qty, 'reconcile');
            } catch (\Throwable $e) {
                // non-fatal
            }
        }

        // Merge any extra keys from live map (alternate casing).
        foreach ($live as $key => $qty) {
            if (! array_key_exists($key, $fromLedger)) {
                $fromLedger[$key] = (int) $qty;
            }
        }

        return $fromLedger;
    }

    /**
     * @param  array<string, int>  $map
     */
    public function resolveQty(array $map, string $sku): ?int
    {
        if (array_key_exists($sku, $map)) {
            return (int) $map[$sku];
        }
        $upper = strtoupper(trim($sku));
        foreach ($map as $key => $qty) {
            if (strtoupper(trim((string) $key)) === $upper) {
                return (int) $qty;
            }
        }

        return null;
    }
}
