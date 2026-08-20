<?php

namespace App\Services\MarketplaceManager;

use App\Models\ShopifySku;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Qty mismatch used by Marketplace Manager listings tabs and /map-issues.
 * Missing Mapping = Active SKU Mismatch + Inactive SKU Mismatch
 * (equal qty, or gap ≤ max(3 units, 3% of Shopify)).
 */
final class MarketplaceListingQtyMatchService
{
    public const CACHE_PREFIX = 'mm_listing_mismatch_v3:';

    /**
     * /map-issues slug → Marketplace Manager channel.
     */
    public static function fromMapIssuesSlug(string $slug): ?string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '', trim($slug)) ?? '');

        return match ($slug) {
            'ebay', 'ebay1', 'ebayone' => 'ebay1',
            'ebay2', 'ebaytwo' => 'ebay2',
            'ebay3', 'ebaythree' => 'ebay3',
            'macys', 'macy' => 'macy',
            'bestbuy', 'bestbuyusa' => 'bestbuy',
            'tiktok', 'tiktokshop' => 'tiktok',
            'tiktok2', 'tiktokshop2' => 'tiktok2',
            'newegg', 'neweggb2c' => 'newegg',
            'temu', 'temu2', 'shein', 'aliexpress', 'pls', 'wayfair', 'faire',
            'topdawg', 'amazon', 'reverb' => $slug,
            default => null,
        };
    }

    public function mismatchCount(string $mmChannel): int
    {
        return count($this->mismatchSkus($mmChannel));
    }

    /**
     * Same number as the listings Inv SKU Mismatch tab.
     */
    public function activeMismatchCount(string $mmChannel, bool $fetchLiveIfCold = true): int
    {
        return count($this->activeMismatchSkus($mmChannel, $fetchLiveIfCold));
    }

    /**
     * @return list<string>
     */
    public function activeMismatchSkus(string $mmChannel, bool $fetchLiveIfCold = true): array
    {
        $mismatch = $this->mismatchSkus($mmChannel);
        if ($mismatch === []) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($mismatch as $sku) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $out[] = (string) $sku;
        }

        if ($out === []) {
            return [];
        }

        // Drop equal-qty rows (ND 58 288=288) that listings still had in mismatch.
        $shopify = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($out);
        $mp = $this->localStockMap($mmChannel, $out);
        $real = [];
        foreach ($out as $sku) {
            $shopifyQty = MarketplaceListingStockResolver::qtyFromMap($shopify, (string) $sku);
            $mpQty = MarketplaceListingStockResolver::qtyFromMap($mp, (string) $sku);
            if ($shopifyQty !== null && MarketplaceLiveInventoryRules::qtyWithinMismatchTolerance((int) $shopifyQty, $mpQty)) {
                continue;
            }
            $real[] = (string) $sku;
        }

        return $real;
    }

    /**
     * Inactive SKU tab = seller-portal inactive listings (not qty-matched Shopify SKUs).
     *
     * @return list<string>
     */
    public function inactiveListingSkus(string $mmChannel, bool $fetchLiveIfCold = true): array
    {
        $rows = $this->inactiveListingRows($mmChannel, $fetchLiveIfCold);

        return array_values(array_map(static fn (array $row): string => (string) $row['sku'], $rows));
    }

    public function inactiveListingCount(string $mmChannel, bool $fetchLiveIfCold = true): int
    {
        return count($this->inactiveListingSkus($mmChannel, $fetchLiveIfCold));
    }

    /**
     * @return list<array{sku: string, channel_sku: string, inv: float, channel_inv: float, diff: float, status: string, state: string}>
     */
    public function inactiveListingRows(string $mmChannel, bool $fetchLiveIfCold = true): array
    {
        return $this->portalInactiveRows($mmChannel, $fetchLiveIfCold);
    }

    /**
     * Inactive SKU = seller-portal inactive listings from live cache (not qty-matched Shopify SKUs).
     *
     * @return list<array{sku: string, channel_sku: string, inv: float, channel_inv: float, diff: float, status: string, state: string}>
     */
    protected function portalInactiveRows(string $mmChannel, bool $fetchLiveIfCold): array
    {
        $pass = app(MarketplaceMismatchInventoryPass::class);
        if ($mmChannel === 'temu2') {
            $live = app(Temu2LiveListingsService::class);
            $cached = $live->peekCached();
            if ((! is_array($cached) || $cached === []) && $fetchLiveIfCold) {
                $cached = $live->all(false);
            }
            $liveRows = is_array($cached) ? $cached : [];
        } else {
            $liveRows = $fetchLiveIfCold
                ? $pass->liveRowsForStateSplit($mmChannel, true)
                : $pass->localRowsForStateSplit($mmChannel, false);
        }
        if (! is_array($liveRows)) {
            $liveRows = [];
        }
        $liveRows = MarketplacePortalInactiveCount::applyToLiveRows($mmChannel, $liveRows);

        $skus = [];
        $states = [];
        $seen = [];
        foreach ($liveRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku) ?: strtoupper($sku);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $state = strtolower(trim((string) ($row['state'] ?? '')));
            if (MarketplacePortalStatusTabs::bucket($state) !== 'inactive') {
                continue;
            }
            $seen[$norm] = true;
            $skus[] = $sku;
            $states[$sku] = $state !== '' ? $state : 'inactive';
        }

        foreach (MarketplacePortalInactiveCount::skus($mmChannel) as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku) ?: strtoupper($sku);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $skus[] = $sku;
            $states[$sku] = 'inactive';
        }
        if ($skus === []) {
            return [];
        }

        $shopify = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($skus);
        $mp = $this->localStockMap($mmChannel, $skus);
        $out = [];
        foreach ($skus as $sku) {
            $inv = (int) (MarketplaceListingStockResolver::qtyFromMap($shopify, $sku) ?? 0);
            $channelInv = (int) (MarketplaceListingStockResolver::qtyFromMap($mp, $sku) ?? 0);
            $out[] = [
                'sku' => $sku,
                'channel_sku' => $sku,
                'inv' => $inv,
                'channel_inv' => $channelInv,
                'diff' => abs($inv - $channelInv),
                'status' => 'Inactive',
                'state' => $states[$sku] ?? 'inactive',
            ];
        }

        return $out;
    }

    /**
     * @return array{inactive: array<string, true>, pending: array<string, true>, state: array<string, string>}
     */
    protected function liveInactiveIndex(string $mmChannel, bool $fetchLiveIfCold): array
    {
        $empty = ['inactive' => [], 'pending' => [], 'state' => []];
        $liveRows = app(MarketplaceMismatchInventoryPass::class)->localRowsForStateSplit($mmChannel, $fetchLiveIfCold);
        if ($liveRows === []) {
            return $empty;
        }

        $inactive = [];
        $pending = [];
        $stateByNorm = [];
        foreach ($liveRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm === '') {
                continue;
            }
            $state = strtolower(trim((string) ($row['state'] ?? '')));
            $inv = $row['inventory'] ?? null;
            $stateByNorm[$norm] = $state !== '' ? $state : 'inactive';
            if ($this->listingStateIsPending($state)) {
                $pending[$norm] = true;
                $inactive[$norm] = true;
                continue;
            }
            if (! $this->listingStateIsActive($state, $inv)) {
                $inactive[$norm] = true;
            }
        }

        return ['inactive' => $inactive, 'pending' => $pending, 'state' => $stateByNorm];
    }

    protected function listingStateIsPending(string $state): bool
    {
        return in_array($state, [
            'pending',
            'draft',
            'unpublished',
            'review',
            'in_review',
            'awaiting',
            'awaiting_approval',
            'pending_review',
            'under_review',
            'pending_hub',
        ], true);
    }

    /**
     * Same Active vs Inactive split as Marketplace Manager listings tabs.
     */
    protected function listingStateIsActive(string $state, mixed $inv): bool
    {
        $isActive = in_array($state, ['active', '1', 'true', 'onselling', 'on_selling'], true)
            || ($state === '' && $inv !== 0 && $inv !== '0' && $inv !== null);
        if (in_array($state, ['inactive', '0', 'false', 'offline', 'ended', 'disabled', 'delisted', 'out_of_stock', 'deleted'], true)
            || $inv === 0 || $inv === '0') {
            $isActive = false;
        }

        return $isActive;
    }

    /**
     * @return list<string>
     */
    public function mismatchSkus(string $mmChannel): array
    {
        $classified = $this->classify($mmChannel);

        return $classified['mismatch'] ?? [];
    }

    /**
     * @return list<array{sku: string, channel_sku: string, inv: float, channel_inv: float, diff: float}>
     */
    public function mismatchRows(string $mmChannel): array
    {
        $skus = $this->mismatchSkus($mmChannel);
        if ($skus === []) {
            return [];
        }

        $shopify = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($skus);
        $mp = $this->localStockMap($mmChannel, $skus);
        $out = [];
        foreach ($skus as $sku) {
            $sku = (string) $sku;
            $inv = (int) (MarketplaceListingStockResolver::qtyFromMap($shopify, $sku) ?? 0);
            $channelInv = (int) (MarketplaceListingStockResolver::qtyFromMap($mp, $sku) ?? 0);
            $out[] = [
                'sku' => $sku,
                'channel_sku' => $sku,
                'inv' => $inv,
                'channel_inv' => $channelInv,
                'diff' => abs($inv - $channelInv),
            ];
        }

        return $out;
    }

    /**
     * @return array{matched: list<string>, mismatch: list<string>, zero: list<string>}
     */
    public function classify(string $mmChannel): array
    {
        $mmChannel = strtolower(trim($mmChannel));
        $empty = ['matched' => [], 'mismatch' => [], 'zero' => []];
        $cacheKey = self::CACHE_PREFIX.$mmChannel;
        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['mismatch'])) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $result = $this->classifyFresh($mmChannel);
        } catch (\Throwable $e) {
            Log::warning('MarketplaceListingQtyMatchService: classify failed', [
                'channel' => $mmChannel,
                'error' => $e->getMessage(),
            ]);

            return $empty;
        }

        try {
            Cache::put($cacheKey, $result, now()->addMinutes(3));
        } catch (\Throwable $e) {
            // ignore
        }

        return $result;
    }

    /**
     * @return array{matched: list<string>, mismatch: list<string>, zero: list<string>}
     */
    protected function classifyFresh(string $mmChannel): array
    {
        $empty = ['matched' => [], 'mismatch' => [], 'zero' => []];
        $catalog = app(ShopifyLiveVerifiedCatalogService::class);
        if (! $catalog->tablesReady() || ! $catalog->hasAnyActive()) {
            return $empty;
        }

        [$linked, $mpStock] = $this->linkedAndStock($mmChannel, $catalog);
        if ($linked === []) {
            return $empty;
        }

        $classified = $catalog->classifyLinkedInventoryMatch($linked, $mpStock) ?? $empty;
        $matched = $classified['matched'] ?? [];
        $mismatch = $classified['mismatch'] ?? [];
        $zero = $classified['zero'] ?? [];

        if ($mismatch === []) {
            return [
                'matched' => array_values($matched),
                'mismatch' => [],
                'zero' => array_values($zero),
            ];
        }

        $liveShopify = MarketplaceListingStockResolver::liveSkuShopifyQtyMapForSkus($mismatch);
        if ($liveShopify === []) {
            $liveShopify = MarketplaceListingStockResolver::catalogShopifyQtyMapForSkus($mismatch);
        }
        $localMp = $this->localStockMap($mmChannel, $mismatch);
        $reconciled = MarketplaceListingStockResolver::reconcileLinkedTabsWithLiveQty(
            $matched,
            $mismatch,
            $zero,
            $liveShopify,
            $localMp
        );

        return [
            'matched' => $reconciled['matched'],
            'mismatch' => $reconciled['mismatch'],
            'zero' => $reconciled['zero'],
        ];
    }

    /**
     * @return array{0: list<string>, 1: array<string, int>}
     */
    protected function linkedAndStock(string $mmChannel, ShopifyLiveVerifiedCatalogService $catalog): array
    {
        if ($mmChannel === 'pls') {
            $builder = app(PlsListingsPageBuilder::class);
            $linked = $builder->linkedSkus();
            $verified = $catalog->filterLinkedToVerified($linked);
            $mpStock = MarketplaceListingStockResolver::classifyStockMapFromLiveOrLocal(
                app(PlsLiveListingsService::class)->peekCached(),
                $builder->stockMapForSkus($verified)
            );

            return [$linked, $mpStock];
        }

        $pass = app(MarketplaceMismatchInventoryPass::class);
        $linked = $pass->linkedSkus($mmChannel);
        $verified = $catalog->filterLinkedToVerified($linked);
        $mpStock = $pass->stockMap($mmChannel, $verified);

        return [$linked, $mpStock];
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, int>
     */
    protected function localStockMap(string $mmChannel, array $skus): array
    {
        if ($mmChannel === 'pls') {
            return app(PlsListingsPageBuilder::class)->stockMapForSkus($skus);
        }

        $resolverChannel = match ($mmChannel) {
            'newegg' => MarketplaceListingStockResolver::CHANNEL_NEWEGG,
            'shein' => MarketplaceListingStockResolver::CHANNEL_SHEIN,
            'topdawg' => MarketplaceListingStockResolver::CHANNEL_TOPDAWG,
            'temu' => MarketplaceListingStockResolver::CHANNEL_TEMU,
            'temu2' => MarketplaceListingStockResolver::CHANNEL_TEMU2,
            'purchasingpower' => MarketplaceListingStockResolver::CHANNEL_PURCHASINGPOWER,
            'wayfair' => MarketplaceListingStockResolver::CHANNEL_WAYFAIR,
            'bestbuy' => MarketplaceListingStockResolver::CHANNEL_BESTBUY,
            'macy' => MarketplaceListingStockResolver::CHANNEL_MACY,
            'doba' => MarketplaceListingStockResolver::CHANNEL_DOBA,
            'ebay1' => MarketplaceListingStockResolver::CHANNEL_EBAY1,
            'ebay2' => MarketplaceListingStockResolver::CHANNEL_EBAY2,
            'ebay3' => MarketplaceListingStockResolver::CHANNEL_EBAY3,
            'reverb' => MarketplaceListingStockResolver::CHANNEL_REVERB,
            'aliexpress' => MarketplaceListingStockResolver::CHANNEL_ALIEXPRESS,
            'faire' => MarketplaceListingStockResolver::CHANNEL_FAIRE,
            'amazon' => MarketplaceListingStockResolver::CHANNEL_AMAZON,
            'tiktok' => MarketplaceListingStockResolver::CHANNEL_TIKTOK,
            'tiktok2' => MarketplaceListingStockResolver::CHANNEL_TIKTOK2,
            default => $mmChannel,
        };

        return MarketplaceListingStockResolver::stockMapForSkus($resolverChannel, $skus);
    }
}
