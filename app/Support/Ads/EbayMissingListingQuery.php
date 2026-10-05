<?php

namespace App\Support\Ads;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * In-stock eBay listings that are not enrolled in a campaign.
 * Same filter as the campaign-ads missing count.
 */
class EbayMissingListingQuery
{
    public static function count(string $adsTable, string $metricsTable): int
    {
        $query = self::filteredQuery($adsTable, $metricsTable);
        if ($query === null) {
            return 0;
        }

        return (int) $query['query']->distinct()->count('ca.listing_id');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function rows(string $adsTable, string $metricsTable, bool $withImages = true): Collection
    {
        $built = self::filteredQuery($adsTable, $metricsTable);
        if ($built === null) {
            return collect();
        }

        $skuExpr = $built['sku'];
        $priceExpr = $built['price'];

        $raw = $built['query']
            ->selectRaw('ca.listing_id as listing_id')
            ->selectRaw("MAX({$skuExpr}) as sku")
            ->selectRaw("MAX({$priceExpr}) as price")
            ->selectRaw("MAX((SELECT ss.inv FROM shopify_skus ss WHERE ss.sku = {$skuExpr} LIMIT 1)) as inv")
            ->selectRaw("MAX((SELECT ss.quantity FROM shopify_skus ss WHERE ss.sku = {$skuExpr} LIMIT 1)) as ovl30")
            ->groupBy('ca.listing_id')
            ->get();

        $rows = $raw->map(function ($row) {
            $inv = (int) ($row->inv ?? 0);
            $ovl30 = (int) round((float) ($row->ovl30 ?? 0));

            return [
                'listing_id' => (string) ($row->listing_id ?? ''),
                'sku' => trim((string) ($row->sku ?? '')),
                'price' => round((float) ($row->price ?? 0), 2),
                'inv' => $inv,
                'ovl30' => $ovl30,
                'dil_percent' => $inv > 0 ? round(($ovl30 / $inv) * 100, 2) : 0,
                'ad_status' => 'No ad',
                'image_path' => null,
            ];
        })->filter(fn (array $row) => $row['listing_id'] !== '' && (int) $row['inv'] > 0);

        if ($withImages) {
            $rows = MissingAdsCatalog::attachImages($rows);
        }

        return $rows
            ->sortBy(fn (array $row) => [strtoupper((string) ($row['sku'] ?? '')), (string) ($row['listing_id'] ?? '')])
            ->values();
    }

    /**
     * @return array{query: \Illuminate\Database\Query\Builder, sku: string, price: string}|null
     */
    private static function filteredQuery(string $adsTable, string $metricsTable): ?array
    {
        if (! Schema::hasTable($adsTable)
            || ! Schema::hasTable($metricsTable)
            || ! Schema::hasTable('shopify_skus')) {
            return null;
        }

        $skuExpr = Schema::hasColumn($adsTable, 'sku')
            ? 'COALESCE(em.sku, ca.sku)'
            : 'em.sku';
        $priceExpr = Schema::hasColumn($adsTable, 'price')
            ? 'COALESCE(em.ebay_price, ca.price)'
            : 'em.ebay_price';

        $query = DB::table($adsTable.' as ca')
            ->leftJoin($metricsTable.' as em', 'em.item_id', '=', 'ca.listing_id')
            ->where(function ($q) {
                $q->whereNull('ca.campaign_id')->orWhere('ca.campaign_id', '');
            })
            ->whereNotExists(function ($q) use ($adsTable) {
                $q->select(DB::raw(1))
                    ->from($adsTable.' as x')
                    ->whereColumn('x.listing_id', 'ca.listing_id')
                    ->whereNotNull('x.campaign_id')
                    ->where('x.campaign_id', '!=', '');
            })
            ->whereRaw("{$skuExpr} IS NOT NULL")
            ->whereRaw("{$skuExpr} != ''")
            ->whereRaw("{$priceExpr} > 0")
            ->whereRaw("(SELECT ss.inv FROM shopify_skus ss WHERE ss.sku = {$skuExpr} LIMIT 1) > 0");

        return ['query' => $query, 'sku' => $skuExpr, 'price' => $priceExpr];
    }
}
