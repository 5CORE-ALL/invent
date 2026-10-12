<?php

namespace App\Support;

/**
 * eBay 1 / 2 / 3 S PRC — the same rule chain as /amazon-tabulator-view with the Std prc vs dil rule ON:
 *   1. S PRC = Std Prc × (1 − (Age + Dil + B Disc + 0 Sold + CVR + Review) / 100), Std under $15 halves rule discs
 *   2. raised to the channel's Min NPFT % (clearance SKUs stay on the discount price)
 *   3. LMP cap: S PRC ≥ LMP and SGROI at LMP ≥ 20% → S PRC = LMP
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
    public static function inputFromTabulatorRow(array $row): ?array
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
        $ads = (float) ($row['AD%'] ?? 0);
        $input = [
            'sku' => $sku,
            'inv' => $inv,
            'std' => round((float) ($row['STANDARD_PRICE'] ?? 0), 2),
            'dil' => $inv > 0 ? ($ov / $inv) * 100 : 0.0,
            'cvr' => $cvr30,
            'cvr_60' => $cvr45,
            'cvr_trend' => self::cvrTrend($cvr30, $cvr45),
            'ebay_l30' => (float) ($row['eBay L30'] ?? 0),
            'lp' => (float) ($row['LP_productmaster'] ?? 0),
            'ship' => (float) ($row['Ship_productmaster'] ?? 0),
            'percentage' => (float) ($row['percentage'] ?? 0),
            'ads_frac' => $ads > 0 ? $ads / 100 : 0.0,
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
     * Full chain for one SKU.
     *
     * @param  array<string, mixed>  $input  from inputFromTabulatorRow()
     * @return array{sprice:float,rule_price:float,lmp_capped:bool,std_capped:bool}|null
     */
    public static function price(StdPrcVsDilPricer $pricer, array $input): ?array
    {
        $rulePrice = $pricer->priceFromRow($input);
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
        $afterLmp = AmazonDilGroiRule::capSpriceToLmp(
            $rulePrice, $lmp, (float) ($input['lp'] ?? 0), (float) ($input['ship'] ?? 0), $margin, 0.0
        );
        $final = AmazonDilGroiRule::capToStdWhenNoLmp($afterLmp, $lmp, $std);

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
