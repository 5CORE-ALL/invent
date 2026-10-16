<?php

namespace App\Support\Badges;

/**
 * Mirrors updateSummaryStats() in channels/all-marketplace-master.blade.php.
 */
class AllMarketplaceMasterBadgeAggregator
{
    /**
     * badges_data field names for the Today block badges (besides today_sales).
     * Same names are used as the chart metric keys on All Marketplace Master.
     *
     * @var list<string>
     */
    public const TODAY_BADGE_FIELDS = [
        't_orders',
        't_npft_pct',
        't_groi_pct',
        't_nroi_pct',
        't_ads_pct',
        't_views',
    ];

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
        $totalYCogs = 0.0;
        $totalYPctSales = 0.0;
        $totalTodaySales = 0.0;
        $totalPSales = 0.0;
        $totalPGross = 0.0;
        $totalPNet = 0.0;
        $totalPCogs = 0.0;
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
            $yMeasured = self::yMeasuredFromRow($row);
            if ($yMeasured !== null) {
                $totalYGross += $yMeasured['pft'];
                $totalYNet += $yMeasured['pft'] - $yMeasured['spend'];
                $totalYCogs += $yMeasured['cogs'];
                $totalYPctSales += $yMeasured['sales'];
            } else {
                $totalYGross += ($gprofitPercent / 100) * $ySales;
                $totalYNet += ($npftPercent / 100) * $ySales;
                $totalYCogs += self::yCogsFromParts($ySales, $l30Sales, $gprofitPercent, $cogs);
                $totalYPctSales += $ySales;
            }
            $totalTodaySales += $todaySales;
            $totalPSales += $pSales;
            $totalPGross += ($gprofitPercent / 100) * $pSales;
            if (self::rowNumber($row, 'L7 Sales') > 0) {
                $totalPNet += ($pSales * $gprofitPercent / 100) - $adSpend;
                $totalPCogs += self::yCogsFromParts($pSales, $l30Sales, $gprofitPercent, $cogs);
            }
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
        $avgYNpft = $totalYPctSales > 0 ? ($totalYNet / $totalYPctSales) * 100 : 0.0;
        $avgYGroi = $totalYCogs > 0 ? ($totalYGross / $totalYCogs) * 100 : 0.0;
        $avgYNroi = $totalYCogs > 0 ? ($totalYNet / $totalYCogs) * 100 : 0.0;
        $netProfit = $totalPft - $totalAdSpend;
        // Match the N ROI column on Active Channel (COGS-weighted).
        // Temu / Temu 2 / Temu 3 store GROI% − Ads%, which is not (PFT − spend) / COGS.
        $nroiWeighted = 0.0;
        $nroiWeight = 0.0;
        foreach ($rows as $row) {
            $cogs = self::rowNumber($row, 'cogs');
            if ($cogs <= 0) {
                continue;
            }
            $nroiWeighted += self::rowNumber($row, 'N ROI', 'NROI', 'n_roi') * $cogs;
            $nroiWeight += $cogs;
        }
        $avgNroi = $nroiWeight > 0 ? ($nroiWeighted / $nroiWeight) : 0.0;
        $avgPNroi = $totalPCogs > 0 ? ($totalPNet / $totalPCogs) * 100 : 0.0;
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
        $today = self::todayMetrics($rows);

        return [
            'channels' => $totalChannels,
            'l30_sales' => round($totalL30Sales, 2),
            'y_sales' => round($totalYSales, 2),
            'y_pft' => round($totalYGross, 2),
            'y_npft' => round($totalYNet, 2),
            'y_npft_amt' => round($totalYNet, 2),
            'y_npft_pct' => round($avgYNpft, 2),
            'y_groi_pct' => round($avgYGroi, 2),
            'y_nroi_pct' => round($avgYNroi, 2),
            'today_sales' => round($totalTodaySales, 2),
            't_orders' => $today['t_orders'],
            't_npft_pct' => $today['t_npft_pct'],
            't_groi_pct' => $today['t_groi_pct'],
            't_nroi_pct' => $today['t_nroi_pct'],
            't_ads_pct' => $today['t_ads_pct'],
            't_views' => $today['t_views'],
            'p_sales' => round($totalPSales, 2),
            'p_npft_amt' => round($totalPNet, 2),
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
            'p_nroi_pct' => round($avgPNroi, 2),
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
     * Others block Rating / Reviews badges (weighted avg rating, total review count).
     * Same math as the review loop in aggregate().
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{avg_rating: float, total_reviews: int}
     */
    public static function reviewsMetrics(array $rows): array
    {
        $ratingSum = 0.0;
        $reviewsSum = 0.0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rating = self::rowNumber($row, 'Avg Rating');
            $reviews = self::rowNumber($row, 'Total Reviews');
            if ($reviews <= 0 && isset($row['Reviews']) && is_array($row['Reviews'])) {
                $rating = self::parseNumber($row['Reviews']['Avg Rating'] ?? $row['Reviews']['avg_rating'] ?? 0);
                $reviews = self::parseNumber($row['Reviews']['Total Reviews'] ?? $row['Reviews']['total_reviews'] ?? 0);
            }
            if ($reviews > 0) {
                $ratingSum += $rating * $reviews;
                $reviewsSum += $reviews;
            }
        }

        return [
            'avg_rating' => $reviewsSum > 0 ? round($ratingSum / $reviewsSum, 1) : 0.0,
            'total_reviews' => (int) round($reviewsSum),
        ];
    }

    /**
     * Today block badges (Sales, Orders, Nprft%, Groi%, NROI%, Ads%, Views).
     * Mirrors todayPartsFromRow() + the Today block in updateSummaryStats() on
     * channels/all-marketplace-master.blade.php.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{today_sales: float, t_orders: int, t_npft_pct: float, t_groi_pct: float, t_nroi_pct: float, t_ads_pct: float, t_views: int}
     */
    public static function todayMetrics(array $rows): array
    {
        $totalSales = 0.0;
        $totalGross = 0.0;
        $totalNet = 0.0;
        $totalCogs = 0.0;
        $totalSpend = 0.0;
        $totalViews = 0.0;
        $totalOrders = 0.0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $sales = self::rowNumber($row, 'Today Sales');
            $totalSales += $sales;
            if ($sales <= 0) {
                continue;
            }

            $gp = self::rowNumber($row, 'Gprofit%');
            $npft = self::rowNumber($row, 'N PFT');
            $ads = self::rowNumber($row, 'Ads%', 'TACOS');
            $l30 = self::rowNumber($row, 'L30 Sales');
            $cogsL30 = self::rowNumber($row, 'cogs');
            $views = self::rowNumber($row, 'Total Views');
            $l30Orders = self::rowNumber($row, 'L30 Orders');

            $totalCogs += ($l30 > 0 && $cogsL30 > 0)
                ? $cogsL30 * ($sales / $l30)
                : $sales * max(0.0, 1 - $gp / 100);
            $totalGross += $sales * $gp / 100;
            $totalNet += $sales * $npft / 100;
            $totalSpend += $sales * $ads / 100;
            $totalViews += ($l30 > 0 && $views > 0) ? $views * ($sales / $l30) : 0.0;
            $totalOrders += ($l30 > 0 && $l30Orders > 0) ? $l30Orders * ($sales / $l30) : 0.0;
        }

        return [
            'today_sales' => round($totalSales, 2),
            't_orders' => (int) round($totalOrders),
            't_npft_pct' => $totalSales > 0 ? round(($totalNet / $totalSales) * 100, 2) : 0.0,
            't_groi_pct' => $totalCogs > 0 ? round(($totalGross / $totalCogs) * 100, 2) : 0.0,
            't_nroi_pct' => $totalCogs > 0 ? round(($totalNet / $totalCogs) * 100, 2) : 0.0,
            't_ads_pct' => $totalSales > 0 ? round(($totalSpend / $totalSales) * 100, 2) : 0.0,
            't_views' => (int) round($totalViews),
        ];
    }

    /**
     * Yesterday's own gross profit / COGS / sales when the nightly calculation
     * measured them, plus the spend the controller derived from the channel's Ads%.
     * Null means fall back to rescaling the L30 rates, which cancels the Y Sales
     * factor and reproduces the L30 percentages.
     *
     * @param  array<string, mixed>  $row
     * @return array{pft: float, cogs: float, spend: float, sales: float}|null
     */
    private static function yMeasuredFromRow(array $row): ?array
    {
        if (($row['Y GPFT $'] ?? null) === null || ($row['Y COGS'] ?? null) === null) {
            return null;
        }

        return [
            'pft' => self::rowNumber($row, 'Y GPFT $'),
            'cogs' => self::rowNumber($row, 'Y COGS'),
            'spend' => self::rowNumber($row, 'Y Spend'),
            'sales' => self::rowNumber($row, 'Y Day Sales'),
        ];
    }

    private static function yCogsFromParts(float $ySales, float $l30Sales, float $gprofitPercent, float $cogs): float
    {
        if ($ySales <= 0) {
            return 0.0;
        }
        if ($l30Sales > 0 && $cogs > 0) {
            return $cogs * ($ySales / $l30Sales);
        }

        return $ySales * max(0.0, 1.0 - ($gprofitPercent / 100.0));
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
