<?php

namespace App\Http\Controllers\Campaigns\Concerns;

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
        $tcos = $netSales > 0
            ? round(($spend / $netSales) * 100, 0)
            : ($spend > 0 ? 100 : 0);

        return response()->json([
            'spend' => $spend,
            'clicks' => $clicks,
            'sold' => $sold,
            'sales' => $sales,
            'cvr' => $cvr,
            'acos' => $acos,
            'tcos' => $tcos,
            'net_sales' => $netSales,
        ]);
    }
}
