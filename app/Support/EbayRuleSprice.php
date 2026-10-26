<?php

namespace App\Support;

/**
 * eBay 1 / 2 / 3 S PRC — the same rule chain as /amazon-tabulator-view with the Std prc vs dil rule ON:
 *   1. S PRC = Std Prc × (1 − (Age + Dil + B Disc + 0 Sold + CVR + Review) / 100), Std under $15 halves rule discs
 *   2. clearance SKUs are priced only from the Clearance Target NROI% (Sprc Dil modal, last row), no cap
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
        $row = \App\Models\ChannelTabulatorColumnSetting::query()->where('channel_name', $channel.'_dil_vs_groi')->first();
        $unpacked = AmazonDilGroiRule::unpackStored(is_array($row?->visibility) ? $row->visibility : null);
        // Clearance Target NROI% (last row of the Sprc Dil modal). It prices clearance SKUs in BOTH modes.
        $clearanceNroi = is_numeric($unpacked['clearance_nroi'] ?? null) ? max(0.0, (float) $unpacked['clearance_nroi']) : 0.0;
        if (SpriceActiveRule::forChannel($channel) !== 'dil') {
            return ['rule' => 'std', 'dil' => null, 'clearance_nroi' => $clearanceNroi];
        }
        $rules = $unpacked['rules'] === []
            ? AmazonDilGroiRule::defaultsForChannel($channel)
            : AmazonDilGroiRule::ensureZeroToZero($unpacked['rules']);

        return ['rule' => 'dil', 'clearance_nroi' => $clearanceNroi, 'dil' => [
            'rules' => $rules,
            'cvr_adj' => $unpacked['cvr_adj'],
            'lmp_cap' => $unpacked['lmp_cap'],
            // eBay 1/2/3: Diff = (Std Prc − Price) / Price above diff_adj.gt_pct adds diff_adj.points to the Target NROI
            // (both saved in the Sprc Dil modal; default 20% and +10). Null = rule off for this channel.
            'diff_adj' => $unpacked['diff_adj'],
            // Ageing Rule (eBay 1): the SKU's age band adds its points to the Target NROI, after the CVR overlay and
            // before the Diff rule. Null / all-zero points = rule off.
            'age_adj' => in_array($channel, self::CHANNELS, true) ? ($unpacked['age_adj'] ?? null) : null,
        ]];
    }

    /** True when at least one saved age band has non-zero points (otherwise the age lookup is skipped). */
    private static function ageAdjActive(?array $rules): bool
    {
        foreach ($rules ?? [] as $band) {
            if (is_array($band) && is_numeric($band['points'] ?? null) && abs((float) $band['points']) > 0.00001) {
                return true;
            }
        }

        return false;
    }

    /**
     * SKU age in days for the Ageing Rule: the row's age_days when it has one, else the Shopify push clock
     * (same source as the Age Disc column / StdPrcVsDilPricer). Null when the SKU has no age.
     *
     * @param  array<string, mixed>  $input
     */
    private static function ageDays(array $input): ?float
    {
        if (is_numeric($input['age_days'] ?? null)) {
            return (float) $input['age_days'];
        }
        $sku = trim((string) ($input['sku'] ?? ''));
        if ($sku === '' || ! function_exists('app')) {
            return null;
        }
        try {
            if (! app()->bound('db')) {
                return null;
            }
            $days = app(\App\Http\Controllers\ProductMaster\InvDaysController::class)
                ->ageDaysFor($sku, (float) ($input['inv'] ?? 0));
        } catch (\Throwable $e) {
            return null;
        }

        return is_int($days) ? (float) $days : null;
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
    public static function dilPrice(array $input, array $dil, ?float $diffRefPrice = null): ?float
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
        if (self::ageAdjActive($dil['age_adj'] ?? null)) {
            $nroi = AmazonDilGroiRule::adjustGroiForAge($nroi, self::ageDays($input), $dil['age_adj']);
        }
        if (! empty($dil['diff_adj'])) {
            // $diffRefPrice overrides the live price as the Diff reference (see price(): keeps a boosted SKU stable).
            $diffPct = self::stdPriceDiffPct((float) ($input['std'] ?? 0), $diffRefPrice ?? (float) ($input['price'] ?? 0));
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
        // Clearance SKUs (Inv days clearance = YES) are priced ONLY from the Clearance Target NROI% (Sprc Dil modal, last row):
        // (LP × (1 + NROI) + Ship) ÷ (take-home − Ads%), in either mode. No slab, Diff, CVR, LMP or Std Prc rule applies.
        if ($pricer->isClearanceRow($input)) {
            return self::clearancePrice($input, $context);
        }
        $dilOn = is_array($context) && ($context['rule'] ?? 'std') === 'dil' && is_array($context['dil'] ?? null);
        $rulePrice = $dilOn ? self::stableDilPrice($input, $context) : $pricer->priceFromRow($input);
        if ($rulePrice === null || ! ($rulePrice > 0)) {
            return null;
        }
        [$afterLmp, $final] = self::capChain($rulePrice, $input, $context, $dilOn);

        return [
            'sprice' => round($final, 2),
            'rule_price' => round($rulePrice, 2),
            'lmp_capped' => abs($afterLmp - $rulePrice) > 0.009,
            'std_capped' => abs($final - $afterLmp) > 0.009,
        ];
    }

    /**
     * LMP cap, then the Std Prc maximum, for one rule price.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $context
     * @return array{0: float, 1: float}  [after LMP cap, final S PRC]
     */
    private static function capChain(float $rulePrice, array $input, ?array $context, bool $dilOn): array
    {
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

        return [$afterLmp, AmazonDilGroiRule::capToStdWhenNoLmp($afterLmp, $lmp, $stdCeiling)];
    }

    /**
     * Sprc Dil rule price that never flips, whatever the live Price is.
     *
     * The Diff rule adds target points when (Std − Price) / Price is above the threshold. Read against the live Price,
     * pushing the boosted price moves the live Price up, the Diff drops under the threshold, the next run removes the
     * boost and pushes the lower price, the Diff rises again, and so on every 10 minutes (the page, which computes the
     * same rule in JavaScript, saw the other price and its push was refused). So the boost is judged against the rule
     * price WITHOUT the boost, which does not depend on the live Price: the answer is the same before and after a push,
     * on the server and on the page. A listed SKU is still required (no live Price = no Diff, as before).
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $context  rule = dil
     */
    private static function stableDilPrice(array $input, array $context): ?float
    {
        $dil = $context['dil'];
        $rulePrice = self::dilPrice($input, $dil);
        $live = round((float) ($input['price'] ?? 0), 2);
        if ($rulePrice === null || empty($dil['diff_adj']) || ! ($live > 0)) {
            return $rulePrice;
        }
        $base = self::dilPrice($input, ['diff_adj' => false] + $dil);
        if ($base === null) {
            return $rulePrice;
        }

        // Diff judged against the unboosted price: base when Std is within the threshold of it, boosted when above.
        return self::dilPrice($input, $dil, $base) ?? $rulePrice;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $context
     * @return array{sprice:float,rule_price:float,lmp_capped:bool,std_capped:bool}|null
     */
    private static function clearancePrice(array $input, ?array $context): ?array
    {
        $nroi = $context['clearance_nroi'] ?? ($input['clearance_nroi'] ?? null);
        if (! is_numeric($nroi) || ! ((float) ($input['inv'] ?? 0) > 0) || ! ((float) ($input['lp'] ?? 0) > 0)) {
            return null;
        }
        $margin = (float) ($input['percentage'] ?? 0);
        if ($margin > 1) {
            $margin /= 100;
        }
        if (! ($margin > 0)) {
            $margin = AmazonDilGroiRule::TAKE_HOME;
        }
        $price = AmazonDilGroiRule::suggestedPrice(
            (float) $input['lp'],
            (float) ($input['ship'] ?? 0),
            max(0.0, (float) $nroi),
            max(0.0, (float) ($input['ads'] ?? 0)) * 100,
            $margin
        );
        if ($price === null || ! ($price >= 0.01)) {
            return null;
        }

        return ['sprice' => round($price, 2), 'rule_price' => round($price, 2), 'lmp_capped' => false, 'std_capped' => false];
    }

    private static function isIgnored(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        return is_string($value) && in_array(strtolower(trim($value)), ['true', 'yes', 'on'], true);
    }
}
