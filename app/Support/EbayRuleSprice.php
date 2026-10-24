<?php

namespace App\Support;

/**
 * eBay 1 / 2 / 3 S PRC — the same rule chain as /amazon-tabulator-view with the Std prc vs dil rule ON:
 *   1. S PRC = Std Prc × (1 − (Age + Dil + B Disc + 0 Sold + CVR + Review) / 100), Std under $15 halves rule discs
 *   2. clearance SKUs stay on the discount price
 *   3. LMP cap: S PRC ≥ LMP and SGROI at LMP ≥ 20% → S PRC = LMP (Std prc vs dil switch ON)
 *      or LMP × 0.98 (Sprc Dil switch ON, AmazonDilGroiRule::DIL_LMP_REDUCE_PCT)
 *   4. Std Prc is the maximum
 * Inputs always come from the eBay channel's own tabulator row: Shopify INV / OV L30, eBay L30,
 * eBay CVR 30 vs CVR 45, eBay take-home %, eBay channel Ads %, eBay LMP. Std Prc is the shared master price.
 */
class EbayRuleSprice
{
    public const CHANNELS = ['ebay1', 'ebay2', 'ebay3'];

    /**
     * Amazon Std rule trend: CVR L30 vs blended L45, ±0.1, CVR 0 is Down.
     * eBay's counterpart of Amazon's blended L45 is the row's CVR 45 (eBay L45 ÷ views).
     */
    public static function cvrTrend(float $cvr30, float $cvr45, float $tol = 0.1): string
    {
        if (! is_finite($cvr30) || $cvr30 <= 0 || $cvr30 < $cvr45 - $tol) {
            return 'down';
        }
        if ($cvr30 > $cvr45 + $tol) {
            return 'up';
        }

        return 'flat';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function cvr45(array $row): float
    {
        if (isset($row['CVR_45']) && is_numeric($row['CVR_45'])) {
            return (float) $row['CVR_45'];
        }

        return ((float) ($row['SCVR'] ?? 0) + (float) ($row['CVR_60'] ?? 0)) / 2;
    }

    /**
     * Same as ebayEffectiveLmp(): lowest non-ignored competitor, else the stored lmp_price.
     *
     * @param  array<string, mixed>  $row
     */
    public static function effectiveLmp(array $row): float
    {
        $l1 = null;
        foreach (is_array($row['lmp_entries'] ?? null) ? $row['lmp_entries'] : [] as $entry) {
            $entry = (array) $entry;
            if (self::isIgnored($entry['ignored'] ?? null)) {
                continue;
            }
            $price = (float) ($entry['landed_price'] ?? 0) ?: ((float) ($entry['total_price'] ?? 0) ?: (float) ($entry['price'] ?? 0));
            if ($price > 0 && ($l1 === null || $price < $l1)) {
                $l1 = $price;
            }
        }
        if ($l1 !== null) {
            return $l1;
        }
        $stored = (float) ($row['lmp_price'] ?? 0);

        return $stored > 0 ? $stored : 0.0;
    }

    /**
     * Pricer input from an /ebay-data-json, /ebay2-data-json or /ebay3-data-json child row.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null  null for parent / summary rows
     */
    public static function inputFromTabulatorRow(array $row, ?float $pageAdsPct = null): ?array
    {
        $sku = trim((string) ($row['(Child) sku'] ?? $row['sku'] ?? ''));
        if ($sku === '' || stripos($sku, 'PARENT') !== false
            || ! empty($row['is_parent_summary']) || ! empty($row['is_parent']) || ! empty($row['is_parent_row'])) {
            return null;
        }
        $inv = (float) ($row['INV'] ?? 0);
        $ov = (float) ($row['L30'] ?? 0);
        $cvr30 = (float) ($row['SCVR'] ?? 0);
        $cvr45 = self::cvr45($row);
        // The SNROI% column on the page uses the page's Ads% (Total Ad Spend ÷ real-orders L30 sales), which is not
        // always the row's AD% (Channel Master TCOS). Use the page's number so the server cap lands where the page does.
        $ads = $pageAdsPct !== null ? $pageAdsPct : (float) ($row['AD%'] ?? 0);
        $input = [
            'sku' => $sku,
            'inv' => $inv,
            'std' => round((float) ($row['STANDARD_PRICE'] ?? 0), 2),
            'price' => (float) ($row['eBay Price'] ?? 0),
            'dil' => $inv > 0 ? ($ov / $inv) * 100 : 0.0,
            'cvr' => $cvr30,
            'cvr_60' => $cvr45,
            'cvr_trend' => self::cvrTrend($cvr30, $cvr45),
            'ebay_l30' => (float) ($row['eBay L30'] ?? 0),
            'lp' => (float) ($row['LP_productmaster'] ?? 0),
            'ship' => (float) ($row['Ship_productmaster'] ?? 0),
            'percentage' => (float) ($row['percentage'] ?? 0),
            'ads_frac' => $ads > 0 ? $ads / 100 : 0.0,
            // SNROI Cap: the pricer judges SNROI with the SAME take-home % and channel Ads % as the page's SNROI%
            // column. Without these it fell back to 80% take-home and 0 Ads, so the server price (saved by the
            // 10-minute / daily job) sat above the cap the page shows.
            'margin' => (float) ($row['percentage'] ?? 0),
            'ads' => $ads > 0 ? $ads / 100 : 0.0,
            'lmp' => self::effectiveLmp($row),
        ];
        foreach (['age_days', 'clearance', 'review_count'] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                $input[$key] = $row[$key];
            }
        }

        return $input;
    }

    /**
     * Ads% the channel's tabulator page uses in its SNROI% column (EBAY*_CHANNEL_ADS_PCT). Null if it can't be read.
     */
    public static function pageAdsPercent(string $channel): ?float
    {
        try {
            $controller = match ($channel) {
                'ebay1' => app(\App\Http\Controllers\MarketPlace\EbayController::class),
                'ebay2' => app(\App\Http\Controllers\MarketPlace\EbayTwoController::class),
                'ebay3' => app(\App\Http\Controllers\MarketPlace\EbayThreeController::class),
                default => null,
            };
            if ($controller === null) {
                return null;
            }
            $pct = (float) $controller->tabulatorChannelAdsPercent();

            return is_finite($pct) && $pct >= 0 ? $pct : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Which S PRC rule the eBay page switch has ON, plus the Dil slabs when it is Sprc Dil.
     * std = Std prc vs dil (LMP cap = LMP). dil = Sprc Dil (Target NROI by Dil, LMP cap = LMP × 0.98).
     *
     * @return array{rule: string, dil: array{rules: array, cvr_adj: array}|null}
     */
    public static function context(string $channel): array
    {
        $channel = strtolower(trim($channel));
        if (SpriceActiveRule::forChannel($channel) !== 'dil') {
            return ['rule' => 'std', 'dil' => null];
        }
        $row = \App\Models\ChannelTabulatorColumnSetting::query()->where('channel_name', $channel.'_dil_vs_groi')->first();
        $unpacked = AmazonDilGroiRule::unpackStored(is_array($row?->visibility) ? $row->visibility : null);
        $rules = $unpacked['rules'] === []
            ? AmazonDilGroiRule::defaultsForChannel($channel)
            : AmazonDilGroiRule::ensureZeroToZero($unpacked['rules']);

        return ['rule' => 'dil', 'dil' => [
            'rules' => $rules,
            'cvr_adj' => $unpacked['cvr_adj'],
            'lmp_cap' => $unpacked['lmp_cap'],
            // eBay 1 only: Diff = (Std Prc − Price) / Price above diff_adj.gt_pct adds diff_adj.points to the Target NROI
            // (both saved in the Sprc Dil modal; default 20% and +10). Null = rule off for this channel.
            'diff_adj' => $channel === 'ebay1' ? $unpacked['diff_adj'] : null,
        ]];
    }

    /** Diff% = (Std Prc − Price) / Price × 100; null when either is missing. */
    public static function stdPriceDiffPct(float $std, float $price): ?float
    {
        if (! ($std > 0) || ! ($price > 0)) {
            return null;
        }

        return (($std - $price) / $price) * 100;
    }

    public const DIFF_ADJ_GT_PCT = 20.0;

    public const DIFF_ADJ_POINTS = 10.0;

    /**
     * Sprc Dil price: the Dil slab (nearest slab outside them) gives a Target NROI%, the CVR overlay adds or takes off
     * points, and S PRC solves (LP × (1 + target%) + Ship) ÷ (take-home − Ads%). Null with no INV, LP or slab.
     *
     * @param  array<string, mixed>  $input  from inputFromTabulatorRow()
     * @param  array{rules: array, cvr_adj: array}  $dil
     */
    public static function dilPrice(array $input, array $dil): ?float
    {
        if (! ((float) ($input['inv'] ?? 0) > 0)) {
            return null;
        }
        $slab = AmazonDilGroiRule::matchOrNearest((float) ($input['dil'] ?? 0), $dil['rules']);
        if ($slab === null) {
            return null;
        }
        $nroi = AmazonDilGroiRule::adjustGroiForCvrArrow(
            (float) $slab['groi'],
            (float) ($input['cvr'] ?? 0),
            (float) ($input['cvr_60'] ?? 0),
            $dil['cvr_adj']
        );
        if (! empty($dil['diff_adj'])) {
            $diffPct = self::stdPriceDiffPct((float) ($input['std'] ?? 0), (float) ($input['price'] ?? 0));
            // true = default 20% / +10; an array is the saved Sprc Dil setting.
            $nroi += AmazonDilGroiRule::diffAdjPoints($diffPct, is_array($dil['diff_adj']) ? $dil['diff_adj'] : null);
        }
        $margin = (float) ($input['percentage'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0)) {
            $margin = AmazonDilGroiRule::TAKE_HOME;
        }
        $price = AmazonDilGroiRule::suggestedPrice(
            (float) ($input['lp'] ?? 0),
            (float) ($input['ship'] ?? 0),
            max(0.0, $nroi),
            max(0.0, (float) ($input['ads'] ?? 0)) * 100,
            $margin
        );

        return ($price !== null && $price >= 0.01) ? $price : null;
    }

    /**
     * Full chain for one SKU.
     *
     * @param  array<string, mixed>  $input  from inputFromTabulatorRow()
     * @param  array{rule: string, dil: array|null}|null  $context  from context(); null = Std prc vs dil
     * @return array{sprice:float,rule_price:float,lmp_capped:bool,std_capped:bool}|null
     */
    public static function price(StdPrcVsDilPricer $pricer, array $input, ?array $context = null): ?array
    {
        $dilOn = is_array($context) && ($context['rule'] ?? 'std') === 'dil' && is_array($context['dil'] ?? null);
        $rulePrice = $dilOn ? self::dilPrice($input, $context['dil']) : $pricer->priceFromRow($input);
        if ($rulePrice === null || ! ($rulePrice > 0)) {
            return null;
        }
        $lmp = (float) ($input['lmp'] ?? 0);
        $std = (float) ($input['std'] ?? 0);
        $margin = (float) ($input['percentage'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0)) {
            $margin = AmazonDilGroiRule::TAKE_HOME;
        }
        // Sprc Dil (switch ON): S PRC at LMP lands on LMP × 0.98. Std prc vs dil: S PRC = LMP.
        // The reduce % follows the SKU's Dil band (below / between / above) saved in the Sprc Dil modal.
        $lmpReducePct = $dilOn
            ? AmazonDilGroiRule::lmpCapReducePct((float) ($input['dil'] ?? 0), $context['dil']['lmp_cap'] ?? null)
            : 0.0;
        $afterLmp = AmazonDilGroiRule::capSpriceToLmp(
            $rulePrice, $lmp, (float) ($input['lp'] ?? 0), (float) ($input['ship'] ?? 0), $margin, 0.0,
            $lmpReducePct
        );
        // Std Prc is the maximum. Std prc vs dil can price above Std on purpose (a negative discount), so its ceiling is
        // the larger of the two. Sprc Dil (switch ON) solves a price from a Target NROI that knows nothing about Std,
        // so Std Prc must cap it, the same as the page and Amazon do.
        $stdCeiling = $dilOn ? $std : max($std, $rulePrice);
        $final = AmazonDilGroiRule::capToStdWhenNoLmp($afterLmp, $lmp, $stdCeiling);

        return [
            'sprice' => round($final, 2),
            'rule_price' => round($rulePrice, 2),
            'lmp_capped' => abs($afterLmp - $rulePrice) > 0.009,
            'std_capped' => abs($final - $afterLmp) > 0.009,
        ];
    }

    private static function isIgnored(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        return is_string($value) && in_array(strtolower(trim($value)), ['true', 'yes', 'on'], true);
    }
}
