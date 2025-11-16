<?php

namespace App\Services;

use App\Models\EbayMetric;
use App\Models\EbayGeneralReport;
use App\Models\EbayPriorityReport;

class EbayDataProcessor
{
    public function calculateAndSave(EbayMetric $metric, float $lp, float $ship, float $percentage)
    {
        $price = $metric->ebay_price ?? 0;
        $units = $metric->ebay_l30 ?? 0;

        // ---------- Profit related calculations ----------
        $totalPft = ($price * $percentage - $lp - $ship) * $units;
        $tSale    = $price * $units;
        $pftPct   = $price > 0 ? (($price * $percentage - $lp - $ship) / $price) * 100 : 0;
        $roiPct   = $lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) * 100 : 0;

        $metric->total_pft      = round($totalPft, 2);
        $metric->t_sale_l30     = round($tSale, 2);
        $metric->pft_percentage = round($pftPct, 2);
        $metric->roi_percentage = round($roiPct, 2);

        // ---------- TACOS calculation ----------
        $itemIdToSku     = [$metric->item_id => $metric->sku];
        $campaignIdToSku = [$metric->campaign_id => $metric->sku];

        $generalReports = EbayGeneralReport::whereIn('listing_id', array_keys($itemIdToSku))
            ->where('report_range', 'L30')
            ->get();

        $priorityReports = EbayPriorityReport::whereIn('campaign_id', array_keys($campaignIdToSku))
            ->where('report_range', 'L30')
            ->get();

        $generalSpent  = $generalReports->sum('ad_fees');
        $prioritySpent = $priorityReports->sum('cpc_ad_fees_payout_currency');

        $denominator = $price * $units;
        $tacos = $denominator > 0 ? (($generalSpent + $prioritySpent) / $denominator) * 100 : 0;

        $metric->t_cogs = round($tacos, 2);

        // ---------- Save ----------
        logger()->info('Before update', $metric->toArray());
        $metric->save();
        logger()->info('After update', $metric->fresh()->toArray());
    }
}
