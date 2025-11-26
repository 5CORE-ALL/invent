<?php

namespace App\Http\Controllers\Campaigns;

// Helper trait for Google Ads date-range calculations

trait GoogleAdsDateRangeTrait
{
    protected function calculateDateRanges()
    {
        $today = now();
        return [
            'L1' => [
                'start' => $today->copy()->subDay(1)->format('Y-m-d'),
                'end' => $today->copy()->subDay(1)->format('Y-m-d')
            ],
            'L7' => [
                'start' => $today->copy()->subDays(7)->format('Y-m-d'),
                'end' => $today->copy()->subDay(1)->format('Y-m-d')
            ],
            'L15' => [
                'start' => $today->copy()->subDays(15)->format('Y-m-d'),
                'end' => $today->copy()->subDay(1)->format('Y-m-d')
            ],
            'L30' => [
                'start' => $today->copy()->subDays(30)->format('Y-m-d'),
                'end' => $today->copy()->subDay(1)->format('Y-m-d')
            ],
            'L60' => [
                'start' => $today->copy()->subDays(60)->format('Y-m-d'),
                'end' => $today->copy()->subDay(1)->format('Y-m-d')
            ],
        ];
    }

    protected function aggregateMetricsByRange($googleCampaigns, $sku, $dateRange, $statusFilter = 'ENABLED')
    {
        $campaignRanges = $googleCampaigns->filter(function ($c) use ($sku, $dateRange, $statusFilter) {
            $campaign = strtoupper(trim($c->campaign_name));
            $skuTrimmed = strtoupper(trim($sku));
            
            $contains = strpos($campaign, $skuTrimmed) !== false;
            $parts = array_map('trim', explode(',', $campaign));
            $exactMatch = in_array($skuTrimmed, $parts);
            
            $matchesCampaign = $contains || $exactMatch;
            $matchesStatus = $statusFilter ? $c->campaign_status === $statusFilter : true;
            $matchesDate = $c->date >= $dateRange['start'] && $c->date <= $dateRange['end'];
            
            return $matchesCampaign && $matchesStatus && $matchesDate;
        });

        $totalCost = $campaignRanges->sum('metrics_cost_micros');
        $totalClicks = $campaignRanges->sum('metrics_clicks');
        $totalImpressions = $campaignRanges->sum('metrics_impressions');
        $totalGA4Sales = $campaignRanges->sum('ga4_ad_sales');
        $totalGA4Units = $campaignRanges->sum('ga4_sold_units');

        return [
            'spend' => $totalCost / 1000000,
            'clicks' => $totalClicks,
            'impressions' => $totalImpressions,
            'cpc' => $totalClicks ? ($totalCost / 1000000) / $totalClicks : 0,
            'ad_sales' => $totalGA4Sales,
            'ad_sold' => $totalGA4Units,
        ];
    }
}
