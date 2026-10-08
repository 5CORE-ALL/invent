<?php

namespace App\Http\Controllers\Campaigns\Concerns;

use App\Support\Ads\EbayMissingListingQuery;
use App\Support\EbayYesterdaySpend;
use Illuminate\Http\JsonResponse;

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

        $payload = [
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
        ];

        if ($this->includeYSpendBadges()) {
            $y = EbayYesterdaySpend::channelTotals();
            $payload['y_spend'] = $y['y_spend'];
            $payload['y_sales'] = $y['y_sales'];
            $payload['y_ads_percent'] = $y['y_ads_percent'];
        }

        return response()->json($payload);
    }

    /** eBay 1 badge strip shows Y Spend and Y Ads%. Other accounts leave them off. */
    protected function includeYSpendBadges(): bool
    {
        return false;
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
        return EbayMissingListingQuery::count($adsTable, $metricsTable);
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
