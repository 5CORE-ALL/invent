<?php

namespace App\Support\Badges;

/**
 * Mirrors updateSummaryStats() in channels/all-marketplace-master.blade.php.
 */
class AllMarketplaceMasterBadgeAggregator
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $extras  inventory_value_amazon, inv_at_lp, etc.
     * @return array<string, int|float|string|null>
     */
    public static function aggregate(array $rows, array $extras = []): array
    {
        $totalChannels = count($rows);
        $totalL30Sales = 0.0;
        $totalYSales = 0.0;
        $totalYGross = 0.0;
        $totalYNet = 0.0;
        $totalTodaySales = 0.0;
        $totalPSales = 0.0;
        $totalPGross = 0.0;
        $totalL30Orders = 0.0;
        $totalQty = 0.0;
        $totalClicks = 0.0;
        $totalPft = 0.0;
        $totalCogs = 0.0;
        $totalAdSpend = 0.0;
        $totalViews = 0.0;
        $totalMap = 0.0;
        $totalMiss = 0.0;
        $totalNMap = 0.0;
        $ratingSum = 0.0;
        $reviewsSum = 0.0;
        $sellerRatingSum = 0.0;
        $sellerReviewsSum = 0.0;

        foreach ($rows as $row) {
            $l30Sales = self::rowNumber($row, 'L30 Sales');
            $ySales = self::rowNumber($row, 'Y Sales');
            $todaySales = self::rowNumber($row, 'Today Sales');
            $pSales = array_key_exists('P-Sales', $row) && $row['P-Sales'] !== null && $row['P-Sales'] !== ''
                ? self::parseNumber($row['P-Sales'])
                : (self::rowNumber($row, 'L7 Sales') / 7) * 30;
            $l30Orders = self::rowNumber($row, 'L30 Orders');
            $qty = self::rowNumber($row, 'Qty');
            $clicks = self::rowNumber($row, 'clicks', 'Clicks');
            $gprofitPercent = self::rowNumber($row, 'Gprofit%');
            $npftPercent = self::rowNumber($row, 'N PFT');
            $cogs = self::rowNumber($row, 'cogs');
            $mapCount = self::rowNumber($row, 'Map');
            $missCount = self::rowNumber($row, 'Miss');
            $nmapCount = self::rowNumber($row, 'NMap');
            $adSpend = self::rowNumber($row, 'Total Ad Spend');
            $channelName = strtolower(trim((string) ($row['Channel '] ?? $row['Channel'] ?? '')));
            if ($adSpend <= 0 && str_contains($channelName, 'reverb')) {
                $l30SalesForBump = self::rowNumber($row, 'L30 Sales');
                $bumpPct = self::rowNumber($row, 'Ads%', 'TACOS %', 'TACOS');
                if ($bumpPct > 0 && $l30SalesForBump > 0) {
                    $adSpend = ($bumpPct / 100) * $l30SalesForBump;
                }
            }
            $views = self::rowNumber($row, 'Total Views');

            $totalL30Sales += $l30Sales;
            $totalYSales += $ySales;
            $totalYGross += ($gprofitPercent / 100) * $ySales;
            $totalYNet += ($npftPercent / 100) * $ySales;
            $totalTodaySales += $todaySales;
            $totalPSales += $pSales;
            $totalPGross += ($gprofitPercent / 100) * $pSales;
            $totalL30Orders += $l30Orders;
            $totalQty += $qty;
            $totalClicks += $clicks;
            $totalAdSpend += $adSpend;
            $totalViews += $views;
            $totalCogs += $cogs;
            $totalMap += $mapCount;
            $totalMiss += $missCount;
            $totalNMap += $nmapCount;
            $totalPft += ($gprofitPercent / 100) * $l30Sales;

            $rating = self::rowNumber($row, 'Avg Rating');
            $reviews = self::rowNumber($row, 'Total Reviews');
            if ($reviews <= 0 && isset($row['Reviews']) && is_array($row['Reviews'])) {
                $rating = self::parseNumber($row['Reviews']['Avg Rating'] ?? $row['Reviews']['avg_rating'] ?? 0);
                $reviews = self::parseNumber($row['Reviews']['Total Reviews'] ?? $row['Reviews']['total_reviews'] ?? 0);
            }
            $sellerRating = self::rowNumber($row, 'Seller Avg Rating');
            $sellerReviews = self::rowNumber($row, 'Seller Total Reviews');
            if ($reviews > 0) {
                $ratingSum += $rating * $reviews;
                $reviewsSum += $reviews;
            }
            if ($sellerReviews > 0) {
                $sellerRatingSum += $sellerRating * $sellerReviews;
                $sellerReviewsSum += $sellerReviews;
            }
        }

        $avgGprofit = $totalL30Sales > 0 ? ($totalPft / $totalL30Sales) * 100 : 0.0;
        $avgGroi = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0.0;
        $avgAdsPercent = $totalL30Sales > 0 ? ($totalAdSpend / $totalL30Sales) * 100 : 0.0;
        $avgNpft = $avgGprofit - $avgAdsPercent;
        $avgPNpft = $totalPSales > 0 ? (($totalPGross - $totalAdSpend) / $totalPSales) * 100 : 0.0;
        $avgYNpft = $totalYSales > 0 ? ($totalYNet / $totalYSales) * 100 : 0.0;
        $netProfit = $totalPft - $totalAdSpend;
        $avgNroi = $totalCogs > 0 ? ($netProfit / $totalCogs) * 100 : 0.0;
        $cvrUnits = 0.0;
        $cvrViews = 0.0;
        foreach ($rows as $row) {
            $views = self::rowNumber($row, 'Total Views');
            if ($views <= 0) {
                continue;
            }
            if (array_key_exists('CVR', $row) && $row['CVR'] !== null && $row['CVR'] !== '') {
                $cvrUnits += (self::parseNumber($row['CVR']) / 100.0) * $views;
                $cvrViews += $views;
            } else {
                $cvrUnits += self::rowNumber($row, 'Qty');
                $cvrViews += $views;
            }
        }
        $cvrPct = $cvrViews > 0 ? ($cvrUnits / $cvrViews) * 100 : null;

        $inventoryValueAmazon = (float) ($extras['inventory_value_amazon'] ?? 0);
        $invAtLp = (float) ($extras['inv_at_lp'] ?? 0);
        $invAtSp = (float) ($extras['inv_at_sp'] ?? 0);
        $shopifyInvSum = (float) ($extras['shopify_inv_sum'] ?? 0);
        $shopifyWeightedAvgLp = (float) ($extras['shopify_weighted_avg_lp'] ?? 0);
        $tat = $totalL30Sales > 0 ? $inventoryValueAmazon / $totalL30Sales : 0.0;

        return [
            'channels' => $totalChannels,
            'l30_sales' => round($totalL30Sales, 2),
            'y_sales' => round($totalYSales, 2),
            'y_pft' => round($totalYGross, 2),
            'y_npft' => round($totalYNet, 2),
            'y_npft_amt' => round($totalYNet, 2),
            'y_npft_pct' => round($avgYNpft, 2),
            'today_sales' => round($totalTodaySales, 2),
            'p_sales' => round($totalPSales, 2),
            'l30_orders' => (int) round($totalL30Orders),
            'qty' => (int) round($totalQty),
            'gprofit_pct' => round($avgGprofit, 2),
            'gross_profit' => round($totalPft, 2),
            'g_roi' => round($avgGroi, 2),
            'ad_spend' => round($totalAdSpend, 2),
            'ads_pct' => round($avgAdsPercent, 2),
            'total_views' => (int) round($totalViews),
            'cvr_pct' => $cvrPct !== null ? round($cvrPct, 2) : null,
            'net_profit' => round($netProfit, 2),
            'npft_pct' => round($avgNpft, 2),
            'p_npft_pct' => round($avgPNpft, 2),
            'n_roi' => round($avgNroi, 2),
            'clicks' => (int) round($totalClicks),
            'map' => (int) round($totalMap),
            'nmap' => (int) round($totalNMap),
            'missing_l' => (int) round($totalMiss),
            'inventory_value_amazon' => round($inventoryValueAmazon, 2),
            'inv_at_lp' => round($invAtLp, 2),
            'inv_at_sp' => round($invAtSp, 2),
            'shopify_inv_sum' => round($shopifyInvSum, 2),
            'shopify_weighted_avg_lp' => round($shopifyWeightedAvgLp, 2),
            'tat' => round($tat, 2),
            'avg_rating' => $reviewsSum > 0 ? round($ratingSum / $reviewsSum, 1) : 0.0,
            'total_reviews' => (int) round($reviewsSum),
            'seller_avg_rating' => $sellerReviewsSum > 0 ? round($sellerRatingSum / $sellerReviewsSum, 1) : 0.0,
            'seller_total_reviews' => (int) round($sellerReviewsSum),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowNumber(array $row, string ...$keys): float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }

            return self::parseNumber($row[$key]);
        }

        return 0.0;
    }

    private static function parseNumber(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);

        if ($cleaned === '' || $cleaned === '-') {
            return 0.0;
        }

        return (float) $cleaned;
    }
}
