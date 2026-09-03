<?php

namespace App\Support;

/**
 * Apply Google-only BGT part columns and recompute SBGT as their sum.
 * Does not read Amazon rule tables.
 */
final class GoogleShoppingBgtParts
{
    /**
     * @param  array<string, mixed>  $arr
     * @param  array{views_l7?: float|null, views_l30?: float|null, price?: float|null, rating?: float|null, inv?: float|null, ovl30?: float|null, dil?: float|null}  $skuMetrics
     * @param  array{sbgt: array<string, mixed>, sbid: array<string, float>}|null  $rawRule
     */
    public static function applyToRow(array &$arr, array $skuMetrics, ?array $rawRule = null): void
    {
        $acos = isset($arr['acos_l30']) && is_numeric($arr['acos_l30'])
            ? (float) $arr['acos_l30']
            : 0.0;
        $bgtAcos = GoogleShoppingCampaignsRawRule::sbgtFromAcos($acos, $rawRule);

        $viewsL7 = isset($skuMetrics['views_l7']) && is_numeric($skuMetrics['views_l7'])
            ? (float) $skuMetrics['views_l7']
            : 0.0;
        $hitViews = GoogleShoppingBgtViewsRule::apply($viewsL7);

        $cvr = isset($arr['cvr_l30']) && is_numeric($arr['cvr_l30'])
            ? (float) $arr['cvr_l30']
            : 0.0;
        $hitCvr = GoogleShoppingBgtCvrRule::apply($cvr);

        $price = isset($skuMetrics['price']) && is_numeric($skuMetrics['price'])
            ? (float) $skuMetrics['price']
            : null;
        $hitPrc = GoogleShoppingBgtPrcRule::apply($price);

        $rating = isset($skuMetrics['rating']) && is_numeric($skuMetrics['rating'])
            ? (float) $skuMetrics['rating']
            : null;
        $hitRev = GoogleShoppingBgtReviewsRule::apply($rating);

        $arr['bgt_acos'] = $bgtAcos;
        $arr['bgt_views'] = $hitViews['bgt'];
        $arr['bgt_views_color'] = $hitViews['color'];
        $arr['bgt_views_label'] = $hitViews['label'];
        $arr['views_l7'] = $viewsL7;
        $arr['views_l30'] = isset($skuMetrics['views_l30']) && is_numeric($skuMetrics['views_l30'])
            ? (float) $skuMetrics['views_l30']
            : 0.0;

        $arr['bgt_cvr'] = $hitCvr['bgt'];
        $arr['bgt_cvr_color'] = $hitCvr['color'];
        $arr['bgt_cvr_label'] = $hitCvr['label'];
        $arr['bgt_cvr_page_cvr'] = $cvr;

        $inv = isset($skuMetrics['inv']) && is_numeric($skuMetrics['inv'])
            ? (float) $skuMetrics['inv']
            : null;
        $ovl30 = isset($skuMetrics['ovl30']) && is_numeric($skuMetrics['ovl30'])
            ? (float) $skuMetrics['ovl30']
            : null;
        $dil = isset($skuMetrics['dil']) && is_numeric($skuMetrics['dil'])
            ? (float) $skuMetrics['dil']
            : AmazonAdsCampaignSkuMetrics::tabulatorDil($inv, $ovl30);
        $arr['ovl30'] = $ovl30 ?? 0.0;
        $arr['dil'] = $dil;

        $arr['price'] = $price;
        $arr['bgt_prc'] = $hitPrc['bgt'];
        $arr['bgt_prc_color'] = $hitPrc['color'];
        $arr['bgt_prc_label'] = $hitPrc['label'];
        $arr['bgt_prc_price'] = $price;

        $arr['bgt_reviews'] = $hitRev['bgt'];
        $arr['bgt_reviews_color'] = $hitRev['color'];
        $arr['bgt_reviews_label'] = $hitRev['label'];
        $arr['bgt_reviews_rating'] = $rating;

        $arr['sbgt'] = GoogleShoppingSbgt::sumFromParts(
            $arr['bgt_views'],
            $arr['bgt_cvr'],
            $arr['bgt_acos'],
            $arr['bgt_prc'],
            $arr['bgt_reviews']
        );
    }

    public static function suggestedDailyBudget(
        float $acos,
        float $cvrL30,
        ?float $viewsL7,
        ?float $price,
        ?float $rating,
        ?array $rawRule = null
    ): int {
        $row = [
            'acos_l30' => $acos,
            'cvr_l30' => $cvrL30,
        ];
        self::applyToRow($row, [
            'views_l7' => $viewsL7,
            'price' => $price,
            'rating' => $rating,
        ], $rawRule);

        $sbgt = $row['sbgt'] ?? null;

        return is_numeric($sbgt) ? (int) $sbgt : (int) GoogleShoppingCampaignsRawRule::sbgtFromAcos($acos, $rawRule);
    }
}
