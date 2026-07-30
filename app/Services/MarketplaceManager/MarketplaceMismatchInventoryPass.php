<?php

namespace App\Services\MarketplaceManager;

use App\Models\AlibabaMetric;
use App\Models\AliexpressMetric;
use App\Models\FaireMetric;
use App\Models\MarketplaceSyncSettings;
use App\Models\NeweggMetric;
use App\Models\ReverbMetric;
use App\Models\SheinMmMetric;
use App\Models\Ebay3Metric;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Second pass after a full marketplace inventory sync: detect remaining
 * Shopify↔marketplace qty mismatches and push those SKUs once more.
 */
final class MarketplaceMismatchInventoryPass
{
    /**
     * @return array{attempted: int, updated: int, failed: int, skipped: int, message: string}
     */
    public function run(string $channel): array
    {
        $channel = strtolower(trim($channel));
        $empty = [
            'attempted' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'message' => 'Mismatch pass skipped.',
        ];

        if (! in_array($channel, ['newegg', 'shein', 'ebay3', 'reverb', 'aliexpress', 'alibaba', 'faire'], true)) {
            return $empty;
        }

        $settings = MarketplaceSyncSettings::getFor($channel);
        if (! ($settings['inventory']['inventory_sync'] ?? false)) {
            return array_merge($empty, ['message' => 'Mismatch pass skipped (inventory sync off).']);
        }

        $linked = $this->linkedSkus($channel);
        if ($linked === []) {
            return array_merge($empty, ['message' => 'Mismatch pass: no linked SKUs.']);
        }

        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        if (! $catalog->tablesReady() || ! $catalog->hasAnyActive()) {
            return array_merge($empty, ['message' => 'Mismatch pass: Shopify live catalog empty.']);
        }

        $mpStock = $this->stockMap($channel, $catalog->filterLinkedToVerified($linked));
        $classified = $catalog->classifyLinkedInventoryMatch($linked, $mpStock);
        $mismatch = $classified['mismatch'] ?? [];

        if ($mismatch === []) {
            return array_merge($empty, ['message' => 'Mismatch pass: no qty-mismatch SKUs remaining.']);
        }

        Log::info('MarketplaceMismatchInventoryPass: pushing mismatch SKUs', [
            'channel' => $channel,
            'count' => count($mismatch),
        ]);

        $result = match ($channel) {
            'newegg' => app(NeweggInventorySyncService::class)->syncSkusFromShopify($mismatch),
            'shein' => app(SheinInventorySyncService::class)->syncSkusFromShopify($mismatch),
            'ebay3' => app(Ebay3InventorySyncService::class)->syncSkusFromShopify($mismatch),
            'reverb' => app(ReverbInventorySyncService::class)->syncSkusFromShopify($mismatch),
            'aliexpress' => app(AliexpressInventorySyncService::class)->syncSkusFromShopify($mismatch),
            'alibaba' => app(AlibabaInventorySyncService::class)->syncSkusFromShopify($mismatch),
            'faire' => app(FaireInventorySyncService::class)->syncSkusFromShopify($mismatch),
            default => $empty,
        };

        return [
            'attempted' => count($mismatch),
            'updated' => (int) ($result['updated'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'message' => sprintf(
                'Mismatch pass: attempted %d, updated %d, failed %d, skipped %d.',
                count($mismatch),
                (int) ($result['updated'] ?? 0),
                (int) ($result['failed'] ?? 0),
                (int) ($result['skipped'] ?? 0)
            ),
        ];
    }

    /**
     * @return list<string>
     */
    protected function linkedSkus(string $channel): array
    {
        $table = match ($channel) {
            'newegg' => 'newegg_metric',
            'shein' => 'shein_metric',
            'ebay3' => 'ebay_3_metrics',
            'reverb' => 'reverb_metric',
            'aliexpress' => 'aliexpress_metric',
            'alibaba' => 'alibaba_metric',
            'faire' => 'faire_metric',
            default => null,
        };
        if ($table === null || ! Schema::hasTable($table)) {
            return [];
        }

        if ($channel === 'ebay3') {
            return Ebay3Metric::query()
                ->whereNotNull('sku')
                ->whereNotNull('item_id')
                ->where('sku', '!=', '')
                ->where('item_id', '!=', '')
                ->whereColumn('sku', '!=', 'item_id')
                ->pluck('sku')
                ->map(static fn ($sku) => trim((string) $sku))
                ->filter(static fn (string $sku) => $sku !== '')
                ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
                ->values()
                ->all();
        }

        $query = match ($channel) {
            'newegg' => NeweggMetric::query(),
            'shein' => SheinMmMetric::query(),
            'reverb' => ReverbMetric::query(),
            'aliexpress' => AliexpressMetric::query(),
            'alibaba' => AlibabaMetric::query(),
            'faire' => FaireMetric::query(),
            default => null,
        };
        if ($query === null) {
            return [];
        }

        return $query
            ->whereNotNull('sku')
            ->whereNotNull('product_id')
            ->where('sku', '!=', '')
            ->where('product_id', '!=', '')
            ->whereColumn('sku', '!=', 'product_id')
            ->pluck('sku')
            ->map(static fn ($sku) => trim((string) $sku))
            ->filter(static fn (string $sku) => $sku !== '')
            ->unique(static fn (string $sku) => ShopifySku::normalizeSkuForShopifyLookup($sku))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    protected function stockMap(string $channel, array $skus): array
    {
        if ($channel === 'alibaba') {
            return $this->alibabaLocalStockMap($skus);
        }

        $resolverChannel = match ($channel) {
            'newegg' => MarketplaceListingStockResolver::CHANNEL_NEWEGG,
            'shein' => MarketplaceListingStockResolver::CHANNEL_SHEIN,
            'ebay3' => MarketplaceListingStockResolver::CHANNEL_EBAY3,
            'reverb' => MarketplaceListingStockResolver::CHANNEL_REVERB,
            'aliexpress' => MarketplaceListingStockResolver::CHANNEL_ALIEXPRESS,
            'faire' => MarketplaceListingStockResolver::CHANNEL_FAIRE,
            default => $channel,
        };

        $local = MarketplaceListingStockResolver::stockMapForSkus($resolverChannel, $skus);

        $liveRows = match ($channel) {
            'newegg' => app(NeweggLiveListingsService::class)->peekCached(),
            'shein' => app(SheinLiveListingsService::class)->peekCached(),
            'ebay3' => app(Ebay3LiveListingsService::class)->peekCached(),
            'reverb' => app(ReverbLiveListingsService::class)->peekCached(),
            'aliexpress' => app(AliexpressLiveListingsService::class)->peekCached(),
            'faire' => app(FaireLiveListingsService::class)->peekCached(),
            default => null,
        };

        return MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal($liveRows, $local);
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    protected function alibabaLocalStockMap(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('alibaba_pricing_prices')) {
            return [];
        }

        $keys = [];
        foreach ($skus as $sku) {
            $trim = trim((string) $sku);
            if ($trim === '') {
                continue;
            }
            $keys[] = $trim;
            $keys[] = strtoupper($trim);
        }
        $keys = array_values(array_unique($keys));
        if ($keys === []) {
            return [];
        }

        $map = [];
        \App\Models\AlibabaPricingPrice::query()
            ->whereIn('sku', $keys)
            ->get(['sku', 'ab_stock'])
            ->each(function ($row) use (&$map) {
                $key = strtoupper(trim((string) $row->sku));
                if ($key !== '' && $row->ab_stock !== null) {
                    $map[$key] = (int) $row->ab_stock;
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
                    if ($norm !== '') {
                        $map[$norm] = (int) $row->ab_stock;
                    }
                }
            });

        return $map;
    }
}
