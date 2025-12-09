<?php

namespace App\Http\Controllers\Campaigns;

use Illuminate\Support\Facades\DB;

// Helper trait for Google Ads date-range calculations

trait GoogleAdsDateRangeTrait
{
    protected function calculateDateRanges()
    {
        $today = now();
        
        // Google Ads data is fetched daily at 12 PM via cron
        // If it's before 12 PM, yesterday's data won't be available yet
        // So we need to use day before yesterday (2 days ago) as the end date for all ranges
        $currentHour = (int) $today->format('H');
        $endDateDaysBack = ($currentHour < 12) ? 2 : 1; // Use 2 days ago if before 12 PM, otherwise yesterday
        $l1DaysBack = $endDateDaysBack; // L1 uses the same logic
        $endDate = $today->copy()->subDays($endDateDaysBack)->format('Y-m-d');
        
        return [
            'L1' => [
                'start' => $today->copy()->subDays($l1DaysBack)->format('Y-m-d'),
                'end' => $today->copy()->subDays($l1DaysBack)->format('Y-m-d')
            ],
            'L7' => [
                // L7 = last 7 days including end date (end date - 6 days = 7 days total)
                'start' => $today->copy()->subDays($endDateDaysBack + 6)->format('Y-m-d'),
                'end' => $endDate
            ],
            'L15' => [
                // L15 = last 15 days including end date (end date - 14 days = 15 days total)
                'start' => $today->copy()->subDays($endDateDaysBack + 14)->format('Y-m-d'),
                'end' => $endDate
            ],
            'L30' => [
                // L30 = last 30 days including end date (end date - 29 days = 30 days total)
                'start' => $today->copy()->subDays($endDateDaysBack + 29)->format('Y-m-d'),
                'end' => $endDate
            ],
            'L60' => [
                // L60 = last 60 days including end date (end date - 59 days = 60 days total)
                'start' => $today->copy()->subDays($endDateDaysBack + 59)->format('Y-m-d'),
                'end' => $endDate
            ],
        ];
    }

    protected function aggregateMetricsByRange($googleCampaigns, $sku, $dateRange, $statusFilter = 'ENABLED')
    {
        $campaignRanges = $googleCampaigns->filter(function ($c) use ($sku, $dateRange, $statusFilter) {
            $campaign = strtoupper(trim($c->campaign_name));
            $skuTrimmed = strtoupper(trim($sku));
            
            // Handle SEARCH campaigns (end with " SEARCH.")
            $isSearchCampaign = str_ends_with($campaign, ' SEARCH.');
            if ($isSearchCampaign) {
                // Remove ' SEARCH.' suffix for matching
                $campaignBase = str_replace(' SEARCH.', '', $campaign);
                
                // Check if SKU is in comma-separated list
                $parts = array_map('trim', explode(',', $campaignBase));
                $exactMatch = in_array($skuTrimmed, $parts);
                
                // If not in list, check if campaign base exactly equals SKU
                if (!$exactMatch) {
                    $exactMatch = $campaignBase === $skuTrimmed;
                }
            } else {
                // For non-SEARCH campaigns (SHOPPING), use standard matching
                // Check if SKU is in comma-separated list
                $parts = array_map('trim', explode(',', $campaign));
                $exactMatch = in_array($skuTrimmed, $parts);
                
                // If not in list, check if campaign name exactly equals SKU
                // This prevents partial matches like "MX 12CH" matching "MX 12CH XU"
                if (!$exactMatch) {
                    $exactMatch = $campaign === $skuTrimmed;
                }
            }
            
            $matchesCampaign = $exactMatch;
            $matchesStatus = $statusFilter ? $c->campaign_status === $statusFilter : true;
            
            // Fixed: Handle both string and Carbon date instances for proper comparison
            $campaignDate = is_string($c->date) ? $c->date : (is_object($c->date) && method_exists($c->date, 'format') ? $c->date->format('Y-m-d') : (string)$c->date);
            $matchesDate = $campaignDate >= $dateRange['start'] && $campaignDate <= $dateRange['end'];
            
            return $matchesCampaign && $matchesStatus && $matchesDate;
        });

        $totalCost = $campaignRanges->sum('metrics_cost_micros');
        $totalClicks = $campaignRanges->sum('metrics_clicks');
        $totalImpressions = $campaignRanges->sum('metrics_impressions');
        $totalGA4Sales = $campaignRanges->sum('ga4_ad_sales');
        $totalGA4Units = $campaignRanges->sum('ga4_sold_units');

        // Calculate CPC: cost per click
        // If there are no clicks, CPC is 0 (this is correct - you can't have a CPC without clicks)
        // Note: CPC L1 will be 0 if:
        // 1. No data exists for yesterday in the database
        // 2. There were no clicks yesterday
        // 3. Campaigns were not ENABLED status yesterday
        $cpc = $totalClicks > 0 ? ($totalCost / 1000000) / $totalClicks : 0;

        return [
            'spend' => $totalCost / 1000000,
            'clicks' => $totalClicks,
            'impressions' => $totalImpressions,
            'cpc' => $cpc,
            'ad_sales' => $totalGA4Sales,
            'ad_sold' => $totalGA4Units,
        ];
    }
}
