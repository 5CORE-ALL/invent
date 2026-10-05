<?php

namespace App\Http\Controllers\Campaigns;

use App\Services\TikTokShopService;
use App\Support\Ads\MissingAdsCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TikTok 1 products with no campaign report and Shopify Inv > 0.
 */
class Tiktok1MissingAdsController extends ListingMissingAdsController
{
    protected function cacheKey(): string
    {
        return 'tiktok1_ads_missing_sidebar_count';
    }

    protected function pageTitle(): string
    {
        return 'TikTok 1 Missing Ads';
    }

    protected function pageSubtitle(): string
    {
        return 'TikTok 1 products with no campaign and Inv > 0.';
    }

    protected function adsUrl(): string
    {
        return route('tiktok1.ads.raw');
    }

    protected function adsLabel(): string
    {
        return 'TikTok 1 Sheet Ads';
    }

    protected function idField(): string
    {
        return 'product_id';
    }

    protected function idLabel(): string
    {
        return 'Product ID';
    }

    public static function dataRouteName(): string
    {
        return 'tiktok1.ads.missing.data';
    }

    protected function collectMissingRows(bool $withImages = true): Collection
    {
        return $this->productsMissingAds('tiktok_products', 'tiktok_campaign_reports', $withImages);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function productsMissingAds(string $productTable, string $adsTable, bool $withImages, bool $matchSku = false): Collection
    {
        if (! Schema::hasTable($productTable) || ! Schema::hasColumn($productTable, 'product_id')) {
            return collect();
        }

        $advertisedIds = [];
        $advertisedSkus = [];
        if (Schema::hasTable($adsTable)) {
            $idCol = Schema::hasColumn($adsTable, 'product_id') ? 'product_id' : null;
            $skuCol = $matchSku && Schema::hasColumn($adsTable, 'sku') ? 'sku' : null;
            $cols = array_values(array_filter([$idCol, $skuCol]));
            if ($cols !== []) {
                foreach (DB::table($adsTable)->get($cols) as $row) {
                    if ($idCol) {
                        $id = trim((string) ($row->{$idCol} ?? ''));
                        if ($id !== '') {
                            $advertisedIds[$id] = true;
                        }
                    }
                    if ($skuCol) {
                        $sku = strtoupper(trim((string) ($row->{$skuCol} ?? '')));
                        if ($sku !== '') {
                            $advertisedSkus[$sku] = true;
                        }
                    }
                }
            }
        }

        $hasStatus = Schema::hasColumn($productTable, 'listing_status');
        $cols = ['product_id', 'sku'];
        if ($hasStatus) {
            $cols[] = 'listing_status';
        }

        $candidates = collect();
        foreach (DB::table($productTable)->get($cols) as $product) {
            $productId = trim((string) ($product->product_id ?? ''));
            $sku = trim((string) ($product->sku ?? ''));
            if ($productId === '' || isset($candidates[$productId])) {
                continue;
            }
            if ($hasStatus) {
                $status = trim((string) ($product->listing_status ?? ''));
                if ($status !== '' && ! TikTokShopService::isLiveListingStatus($status)) {
                    continue;
                }
            }
            if (isset($advertisedIds[$productId])) {
                continue;
            }
            if ($sku !== '' && isset($advertisedSkus[strtoupper($sku)])) {
                continue;
            }
            $candidates[$productId] = [
                'product_id' => $productId,
                'sku' => $sku,
            ];
        }

        $rows = MissingAdsCatalog::requireShopifyInventory(collect($candidates), $withImages);

        return $rows->sortBy(fn (array $row) => [strtoupper((string) ($row['sku'] ?? '')), (string) ($row['product_id'] ?? '')])->values();
    }
}
