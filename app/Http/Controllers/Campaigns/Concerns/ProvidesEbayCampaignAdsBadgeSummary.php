<?php

namespace App\Http\Controllers\Campaigns\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait ProvidesEbayCampaignAdsBadgeSummary
{
    /**
     * Rolled-up KW + PMT L30-style totals for the stat badge strip
     * (same source as /advertisement-master).
     *
     * @return array{spend: float, clicks: int, sold: int, sales: float}
     */
    abstract protected function advertisementMasterKwMetrics(): array;

    /**
     * @return array{spend: float, clicks: int, sold: int, sales: float}
     */
    abstract protected function advertisementMasterPmtMetrics(): array;

    abstract public static function advertisementMasterNetSales(): float;

    public function getBadgeSummary(): JsonResponse
    {
        $kw = $this->advertisementMasterKwMetrics();
        $pmt = $this->advertisementMasterPmtMetrics();

        $spend = round($kw['spend'] + $pmt['spend'], 2);
        $clicks = (int) ($kw['clicks'] + $pmt['clicks']);
        $sold = (int) ($kw['sold'] + $pmt['sold']);
        $sales = round($kw['sales'] + $pmt['sales'], 2);
        $cvr = $clicks > 0 ? round(($sold / $clicks) * 100, 1) : 0.0;
        $acos = $sales > 0
            ? round(($spend / $sales) * 100, 0)
            : ($spend > 0 ? 100 : 0);
        $netSales = static::advertisementMasterNetSales();
        $tcos = self::tcosPercent($spend, $netSales, $sales);

        return response()->json([
            'spend' => $spend,
            'clicks' => $clicks,
            'sold' => $sold,
            'sales' => $sales,
            'cvr' => $cvr,
            'acos' => $acos,
            'tcos' => $tcos,
            'net_sales' => $netSales,
            'cbid_null' => $this->cbidNullInStockCount(),
            'missing_ads' => $this->cbidNullInStockCount(),
        ]);
    }

    /**
     * Missing ads: not in a campaign, in stock, SKU matched, price set,
     * and the listing is not already enrolled in another campaign row.
     */
    public static function missingAdsTotalCount(): int
    {
        return (new static)->cbidNullInStockCount();
    }

    /**
     * Missing ads: not in a campaign, in stock, SKU matched, price set,
     * and the listing is not already enrolled in another campaign row.
     */
    protected function missingAdsCountFor(string $adsTable, string $metricsTable): int
    {
        if (! Schema::hasTable($adsTable)
            || ! Schema::hasTable($metricsTable)
            || ! Schema::hasTable('shopify_skus')) {
            return 0;
        }

        $skuExpr = Schema::hasColumn($adsTable, 'sku')
            ? 'COALESCE(em.sku, ca.sku)'
            : 'em.sku';
        $priceExpr = Schema::hasColumn($adsTable, 'price')
            ? 'COALESCE(em.ebay_price, ca.price)'
            : 'em.ebay_price';

        return (int) DB::table($adsTable.' as ca')
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
            ->whereRaw("(SELECT ss.inv FROM shopify_skus ss WHERE ss.sku = {$skuExpr} LIMIT 1) > 0")
            ->distinct()
            ->count('ca.listing_id');
    }

    /**
     * Missing ads: not in a campaign, in stock, SKU matched, price set.
     * Override per marketplace when the ads table differs.
     */
    protected function cbidNullInStockCount(): int
    {
        return 0;
    }

    /**
     * TCOS = spend / store S SALES — same as /ebay/campaign-ads.
     * When L30 campaign spend is larger than store S SALES (eBay 2 listing
     * reports vs daily-sales orders), use ads sales as the denominator so TCOS
     * stays on the same basis as ACOS instead of 100%+.
     */
    public static function tcosPercent(float $spend, float $netSales, float $adsSales): int
    {
        $denom = $netSales;
        if ($spend > 0 && $denom > 0 && $spend > $denom && $adsSales > $denom) {
            $denom = $adsSales;
        }
        if ($denom > 0) {
            return (int) round(($spend / $denom) * 100);
        }

        return $spend > 0 ? 100 : 0;
    }
}
