<?php

namespace App\Support\Ads;

use App\Models\ShopifySku;
use App\Models\Temu3AdsApiReport;
use App\Models\Temu3Metric;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Missing ads for channels whose ads screen is only a title page.
 * Temu 3 uses Status "No ad" + Inv > 0. Every other channel lists
 * in-stock SKUs that are on the marketplace and have no ad.
 */
class ChannelListingMissingAds
{
    /**
     * @return list<array{slug: string, title: string, keys: list<string>, registry: ?string, mode: string}>
     */
    public static function channels(): array
    {
        return [
            ['slug' => 'temu3', 'title' => 'Temu 3', 'keys' => ['temu3', 'temuthree'], 'registry' => null, 'mode' => 'temu3'],
            ['slug' => 'topdawg', 'title' => 'TopDawg', 'keys' => ['topdawg'], 'registry' => 'topdawg', 'mode' => 'listed'],
            ['slug' => 'vinted', 'title' => 'Vinted', 'keys' => ['vinted'], 'registry' => 'vinted', 'mode' => 'listed'],
            ['slug' => 'wayfair', 'title' => 'Wayfair', 'keys' => ['wayfair'], 'registry' => 'wayfair', 'mode' => 'listed'],
            ['slug' => 'faire', 'title' => 'Faire', 'keys' => ['faire'], 'registry' => 'faire', 'mode' => 'listed'],
            ['slug' => 'fbmarketplace', 'title' => 'FB Marketplace', 'keys' => ['fbmarketplace', 'facebookmarketplace'], 'registry' => 'fbmarketplace', 'mode' => 'listed'],
            ['slug' => 'pls', 'title' => 'PLS', 'keys' => ['pls'], 'registry' => 'pls', 'mode' => 'listed'],
            ['slug' => 'purchasingpower', 'title' => 'Purchasing Power', 'keys' => ['purchasingpower'], 'registry' => 'purchasingpower', 'mode' => 'listed'],
            ['slug' => 'reverb', 'title' => 'Reverb', 'keys' => ['reverb'], 'registry' => 'reverb', 'mode' => 'listed'],
            ['slug' => 'shein', 'title' => 'Shein', 'keys' => ['shein'], 'registry' => 'shein', 'mode' => 'listed'],
            ['slug' => 'macys', 'title' => "Macy's", 'keys' => ['macys', 'macy'], 'registry' => 'macys', 'mode' => 'listed'],
            ['slug' => 'depop', 'title' => 'Depop', 'keys' => ['depop'], 'registry' => 'depop', 'mode' => 'listed'],
            ['slug' => 'doba', 'title' => 'Doba', 'keys' => ['doba'], 'registry' => 'doba', 'mode' => 'listed'],
            ['slug' => 'aliexpress', 'title' => 'AliExpress', 'keys' => ['aliexpress'], 'registry' => 'aliexpress', 'mode' => 'listed'],
            ['slug' => 'alibaba', 'title' => 'Alibaba', 'keys' => ['alibaba'], 'registry' => 'alibaba', 'mode' => 'listed'],
            ['slug' => 'mercariwship', 'title' => 'Mercari w Ship', 'keys' => ['mercariwship', 'mercariwithship'], 'registry' => 'mercariwship', 'mode' => 'listed'],
            ['slug' => 'mercariwoship', 'title' => 'Mercari w/o Ship', 'keys' => ['mercariwoship', 'mercariwithoutship'], 'registry' => 'mercariwoship', 'mode' => 'listed'],
            ['slug' => 'fbshop', 'title' => 'FB Shop', 'keys' => ['fbshop'], 'registry' => 'fbshop', 'mode' => 'listed'],
            ['slug' => 'instagramshop', 'title' => 'Instagram Shop', 'keys' => ['instagramshop'], 'registry' => 'instagramshop', 'mode' => 'listed'],
            ['slug' => 'bestbuy', 'title' => 'Best Buy', 'keys' => ['bestbuy', 'bestbuyusa'], 'registry' => 'bestbuyusa', 'mode' => 'listed'],
            ['slug' => 'newegg', 'title' => 'Newegg', 'keys' => ['newegg', 'neweggb2c'], 'registry' => 'neweggb2c', 'mode' => 'listed'],
        ];
    }

    /**
     * @return array{slug: string, title: string, keys: list<string>, registry: ?string, mode: string}|null
     */
    public static function find(string $slug): ?array
    {
        $slug = strtolower(trim($slug));
        foreach (self::channels() as $channel) {
            if ($channel['slug'] === $slug || in_array($slug, $channel['keys'], true)) {
                return $channel;
            }
        }

        return null;
    }

    public static function canonical(string $normalizedKey): ?string
    {
        $channel = self::find($normalizedKey);

        return $channel['slug'] ?? null;
    }

    /**
     * @return array<string, int>
     */
    public static function countsBySlug(): array
    {
        try {
            $cached = Cache::get('channel_listing_missing_ads_counts');
            if (is_array($cached)) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $counts = [];
        $skuSets = [];
        $allSkus = [];
        foreach (self::channels() as $channel) {
            if ($channel['mode'] === 'temu3') {
                continue;
            }
            try {
                $set = [];
                foreach (self::listedRows((string) $channel['registry']) as $row) {
                    $sku = trim((string) ($row['sku'] ?? ''));
                    if ($sku === '') {
                        continue;
                    }
                    $set[strtolower($sku)] = $sku;
                    $allSkus[$sku] = $sku;
                }
                $skuSets[$channel['slug']] = $set;
            } catch (\Throwable $e) {
                $counts[$channel['slug']] = 0;
            }
        }

        $shopify = $allSkus === []
            ? []
            : ShopifySku::buildShopifySkuLookupByNormalizedSku(array_values($allSkus));
        foreach ($skuSets as $slug => $set) {
            $n = 0;
            foreach ($set as $sku) {
                $key = ShopifySku::normalizeSkuForShopifyLookup($sku);
                $shopifyRow = $key !== '' ? ($shopify[$key] ?? null) : null;
                if ($shopifyRow && (int) ($shopifyRow->inv ?? 0) > 0) {
                    $n++;
                }
            }
            $counts[$slug] = $n;
        }

        try {
            $counts['temu3'] = self::rows('temu3', false)->count();
        } catch (\Throwable $e) {
            $counts['temu3'] = 0;
        }

        try {
            Cache::put('channel_listing_missing_ads_counts', $counts, now()->addMinutes(5));
        } catch (\Throwable $e) {
            // ignore
        }

        return $counts;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function rows(string $slug, bool $withImages = true): Collection
    {
        $channel = self::find($slug);
        if ($channel === null) {
            return collect();
        }

        $rows = $channel['mode'] === 'temu3'
            ? self::temu3Rows()
            : self::listedRows((string) $channel['registry']);

        $rows = MissingAdsCatalog::requireShopifyInventory($rows, $withImages);

        return $rows
            ->sortBy(fn (array $row) => [strtoupper((string) ($row['sku'] ?? '')), (string) ($row['listing_id'] ?? '')])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function listedRows(string $registryKey): Collection
    {
        $cfg = ChannelListingRegistry::get($registryKey);
        if ($cfg === null) {
            return collect();
        }

        $skus = ListingCountsEngine::productSkus();
        $listed = ChannelListingRegistry::loadListedIds($cfg, $skus);
        $bySku = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            $key = strtolower($sku);
            if ($key === '') {
                continue;
            }
            $id = trim((string) ($listed[$key] ?? ''));
            if ($id === '') {
                continue;
            }
            $bySku[$key] = [
                'sku' => $sku,
                'listing_id' => $id,
            ];
        }

        return collect(array_values($bySku));
    }

    /**
     * Temu 3 goods with Status No ad (or no ads row yet).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function temu3Rows(): Collection
    {
        $candidates = [];
        $reported = [];

        if (Schema::hasTable('temu3_ads_api_reports')) {
            foreach (Temu3AdsApiReport::query()
                ->whereNotNull('goods_id')
                ->where('goods_id', '!=', '')
                ->orderByDesc('id')
                ->get(['goods_id', 'sku', 'ad_status']) as $report
            ) {
                $gid = trim((string) $report->goods_id);
                if ($gid === '') {
                    continue;
                }
                $reported[$gid] = true;
                if (isset($candidates[$gid])) {
                    continue;
                }
                if (strcasecmp(trim((string) $report->ad_status), 'No ad') !== 0) {
                    continue;
                }
                $candidates[$gid] = [
                    'goods_id' => $gid,
                    'listing_id' => $gid,
                    'sku' => trim((string) $report->sku),
                ];
            }
        }

        if (Schema::hasTable('temu3_metrics')) {
            foreach (Temu3Metric::query()
                ->whereNotNull('goods_id')
                ->where('goods_id', '!=', '')
                ->get(['goods_id', 'sku']) as $metric
            ) {
                $gid = trim((string) $metric->goods_id);
                if ($gid === '' || isset($candidates[$gid]) || isset($reported[$gid])) {
                    continue;
                }
                $candidates[$gid] = [
                    'goods_id' => $gid,
                    'listing_id' => $gid,
                    'sku' => trim((string) $metric->sku),
                ];
            }
        }

        return collect(array_values($candidates));
    }
}
