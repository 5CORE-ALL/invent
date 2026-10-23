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
     * @param  array{views_l7?: float|null, views_l30?: float|null, price?: float|null, inv?: float|null, ovl30?: float|null, dil?: float|null}  $skuMetrics
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

        // BGT DIL: Dil% slab → budget part (default slabs add 0).
        $hitDil = GoogleShoppingBgtDilRule::apply($dil);
        $arr['bgt_dil'] = $hitDil['bgt'];
        $arr['bgt_dil_color'] = $hitDil['color'];
        $arr['bgt_dil_label'] = $hitDil['label'];
        $arr['bgt_dil_value'] = $dil;

        $arr['sbgt'] = GoogleShoppingSbgt::sumFromParts(
            $arr['bgt_views'],
            $arr['bgt_cvr'],
            $arr['bgt_acos'],
            $arr['bgt_prc'],
            $arr['bgt_dil']
        );

        $cfg = ($rawRule ?? GoogleShoppingCampaignsRawRule::resolvedRule())['sbgt'] ?? [];
        self::applyPriceCap($arr, $price, (float) ($cfg['max_pct_of_price'] ?? 10.0));
        self::applyInventoryGate($arr, $inv, (bool) ($cfg['pause_if_inv_zero'] ?? true));
        self::applyPricePause($arr, $price, (float) ($cfg['pause_price_below'] ?? 20.0));
        self::applyDilPause($arr, $dil, (float) ($cfg['pause_dil_above'] ?? 50.0));
    }

    /**
     * Rule "Pause ads if Dil% is above X": a known Dil% over the threshold forces SBGT to 0 — Google cannot
     * push a $0 daily budget, so the campaign is paused instead. Threshold 0 = rule off; an unknown Dil never pauses.
     */
    public static function applyDilPause(array &$arr, ?float $dil, float $above): void
    {
        if ($above <= 0 || $dil === null || ! is_finite($dil)) {
            return;
        }
        if ($dil > $above) {
            $arr['sbgt'] = 0;
        }
    }

    /**
     * Rule "Pause ads if price is below $X": a known price under the threshold forces SBGT to 0 — Google cannot
     * push a $0 daily budget, so the campaign is paused instead. Threshold 0 = rule off; an unknown price never pauses.
     */
    public static function applyPricePause(array &$arr, ?float $price, float $below): void
    {
        if ($below <= 0 || $price === null || ! is_finite($price) || $price <= 0) {
            return;
        }
        if ($price < $below) {
            $arr['sbgt'] = 0;
        }
    }

    /**
     * Maximum BGT = $maxPct % of the price (whole dollars, at least $1 because a $0 budget cannot be pushed).
     * $maxPct 0 or no price = no cap.
     */
    public static function applyPriceCap(array &$arr, ?float $price, float $maxPct): void
    {
        $arr['sbgt_cap'] = null;
        if ($maxPct <= 0 || $price === null || $price <= 0 || ! isset($arr['sbgt']) || ! is_numeric($arr['sbgt'])) {
            return;
        }
        $cap = max(1, (int) floor($price * $maxPct / 100.0));
        $arr['sbgt_cap'] = $cap;
        if ((int) $arr['sbgt'] > $cap) {
            $arr['sbgt'] = $cap;
        }
    }

    /**
     * Rule "Pause ads if INV = 0": INV ≤ 0 forces SBGT to 0 — Google cannot push a $0 daily budget,
     * so the campaign is paused instead. With the rule off, INV does not change SBGT.
     */
    public static function applyInventoryGate(array &$arr, mixed $fallbackInv = null, bool $pauseIfInvZero = true): void
    {
        if (! $pauseIfInvZero) {
            return;
        }
        $inv = isset($arr['inventory']) && is_numeric($arr['inventory'])
            ? (float) $arr['inventory']
            : (is_numeric($fallbackInv) ? (float) $fallbackInv : null);
        if ($inv !== null && $inv <= 0) {
            $arr['sbgt'] = 0;
        }
    }

    public static function suggestedDailyBudget(
        float $acos,
        float $cvrL30,
        ?float $viewsL7,
        ?float $price,
        ?array $rawRule = null,
        ?float $inv = null,
        ?float $dil = null
    ): int {
        $row = [
            'acos_l30' => $acos,
            'cvr_l30' => $cvrL30,
        ];
        if ($inv !== null) {
            $row['inventory'] = $inv;
        }
        self::applyToRow($row, [
            'views_l7' => $viewsL7,
            'price' => $price,
            'inv' => $inv,
            'dil' => $dil,
        ], $rawRule);

        $sbgt = $row['sbgt'] ?? null;

        return is_numeric($sbgt) ? (int) $sbgt : (int) GoogleShoppingCampaignsRawRule::sbgtFromAcos($acos, $rawRule);
    }
}
