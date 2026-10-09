<?php

namespace App\Support;

use App\Models\ChannelTabulatorColumnSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S PRC from a channel's saved Std prc vs dil slabs.
 * S PRC = Std Prc × (1 − (Age + Dil + CVR up/down + Review + Buss + 0 Sold) / 100).
 * Std Prc under $15 uses half of each rule discount (0.5×). B Disc stays at the full Disc %.
 * Best Buy then raises S PRC so NPFT% stays at or above the saved Min NPFT % (default 10).
 * Clearance SKUs (inv days clearance = YES) keep the discount price.
 * Used by the unattended apply commands. The page does not have to be open.
 */
class StdPrcVsDilPricer
{
    /** Std Prc strictly below this uses {@see self::LOW_STD_FACTOR}. */
    public const LOW_STD_UNDER = 15.0;

    public const LOW_STD_FACTOR = 0.5;
    /** @var array<string, self> */
    private static array $cache = [];

    /** @var array<string, true>|null */
    private static ?array $clearanceKeys = null;

    /** @param array{dil:list<array<string,float>>,age:list<array<string,float>>,cvr:array<string,float>,reviews:list<array<string,float>>,review_max:int,buss?:list<array<string,float>>,zero_sold_disc?:float} $rules */
    public function __construct(private array $rules, private string $channel = '') {}

    public static function forChannel(string $channel): self
    {
        if (isset(self::$cache[$channel])) {
            return self::$cache[$channel];
        }
        $defaults = self::defaults();
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', $channel.'_std_prc_vs_dil')
            ->first();
        $saved = is_array($row?->visibility) ? $row->visibility : [];
        $rules = [
            'dil' => self::ranges($saved['dil'] ?? null, $defaults['dil']),
            'age' => self::ranges($saved['age'] ?? null, $defaults['age']),
            'cvr' => self::cvr($saved['cvr'] ?? null, $defaults['cvr']),
            'reviews' => self::ranges($saved['reviews'] ?? null, $defaults['reviews']),
            'review_max' => is_numeric($saved['review_max'] ?? null) ? max(1, (int) $saved['review_max']) : $defaults['review_max'],
            'buss' => self::ranges($saved['buss'] ?? null, $defaults['buss']),
            'zero_sold_disc' => self::discPercent($saved['zero_sold_disc'] ?? null),
            'min_npft' => self::minNpftPercent($saved['min_npft'] ?? null, $channel === 'bestbuy' ? 10.0 : 0.0),
        ];

        return self::$cache[$channel] = new self($rules, $channel);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function priceFromRow(array $row): ?float
    {
        $metric = $this->lookupAmazonMetric($row);
        $inv = $metric !== null ? $metric['inv'] : (float) ($row['inv'] ?? $row['INV'] ?? 0);
        if (! ($inv > 0)) {
            return null;
        }
        $std = (float) ($row['std_price'] ?? $row['std'] ?? $row['STANDARD_PRICE'] ?? $row['standard_price'] ?? 0);
        if (! ($std > 0)) {
            return null;
        }
        $dil = $metric !== null ? $metric['dil'] : (float) ($row['dil'] ?? 0);
        $age = is_numeric($row['age_days'] ?? null) ? (float) $row['age_days'] : $this->lookupAgeDays($row, $inv);
        $cvr = $metric !== null ? $metric['cvr'] : (float) ($row['cvr'] ?? 0);
        $cvr60 = $metric !== null ? $metric['cvr45'] : (float) ($row['cvr_60'] ?? 0);
        $reviews = (float) ($row['review_count'] ?? $row['reviews'] ?? $row['Reviews'] ?? 0);
        $ageDisc = self::scaleRuleDisc($this->ageDisc($age), $std);
        $dilDisc = self::scaleRuleDisc($this->rangeDisc($dil, $this->rules['dil']), $std);
        $cvrDisc = self::scaleRuleDisc($this->cvrDisc($cvr, $cvr60), $std);
        $reviewDisc = self::scaleRuleDisc($this->reviewDisc($reviews), $std);
        $bussDisc = $this->rangeDisc($std, $this->rules['buss'] ?? []);
        $zeroSoldDisc = $this->isZeroSold($row)
            ? self::scaleRuleDisc((float) ($this->rules['zero_sold_disc'] ?? 0), $std)
            : 0.0;
        $sum = min(99.99, max(0, $ageDisc + $dilDisc + $cvrDisc + $reviewDisc + $bussDisc + $zeroSoldDisc));
        $price = round($std * (1 - $sum / 100), 2);
        if (! ($price > 0)) {
            return null;
        }
        $price = $this->applyMinNpft($price, $row);

        return $price > 0 ? $price : null;
    }

    /**
     * Best Buy NPFT% is GPFT% (no ads): ((price × margin − ship − LP) / price) × 100.
     * Raise to the cent that keeps NPFT at or above the saved floor. Clearance SKUs are unchanged.
     *
     * @param  array<string, mixed>  $row
     */
    private function applyMinNpft(float $price, array $row): float
    {
        if ($this->channel !== 'bestbuy') {
            return $price;
        }
        $min = (float) ($this->rules['min_npft'] ?? 0);
        if (! ($min > 0) || $this->rowIsClearance($row)) {
            return $price;
        }
        $lp = (float) ($row['lp'] ?? $row['LP'] ?? $row['LP_productmaster'] ?? 0);
        $ship = (float) ($row['ship'] ?? $row['Ship'] ?? $row['Ship_productmaster'] ?? 0);
        $margin = $this->marginFor($row);
        if ($this->npftAt($price, $lp, $ship, $margin) >= $min - 0.001) {
            return $price;
        }
        $floor = $this->priceForMinNpft($lp, $ship, $margin, $min);
        if ($floor === null || $floor <= $price) {
            return $price;
        }

        return $floor;
    }

    private function npftAt(float $price, float $lp, float $ship, float $margin): float
    {
        if (! ($price > 0)) {
            return 0.0;
        }

        return (($price * $margin - $ship - $lp) / $price) * 100;
    }

    private function priceForMinNpft(float $lp, float $ship, float $margin, float $minNpft): ?float
    {
        $denom = $margin - ($minNpft / 100);
        if (! ($denom > 0.0001)) {
            return null;
        }
        $raw = ($lp + $ship) / $denom;
        if (! ($raw > 0)) {
            return null;
        }
        $cents = (int) ceil($raw * 100 - 1e-6);
        $floor = $cents / 100;
        if ($this->npftAt($floor, $lp, $ship, $margin) + 1e-6 < $minNpft) {
            $floor = round($floor + 0.01, 2);
        }

        return $floor > 0 ? $floor : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function marginFor(array $row): float
    {
        foreach (['margin', '_margin', 'percentage'] as $key) {
            if (! isset($row[$key]) || ! is_numeric($row[$key])) {
                continue;
            }
            $m = (float) $row[$key];
            if ($m > 1) {
                $m /= 100;
            }
            if ($m > 0 && $m <= 1) {
                return $m;
            }
        }

        return 0.80;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowIsClearance(array $row): bool
    {
        if (array_key_exists('clearance', $row)) {
            $value = $row['clearance'];
            if (is_bool($value)) {
                return $value;
            }
            if (is_numeric($value)) {
                return (int) $value === 1;
            }
            $flag = strtoupper(trim((string) $value));

            return in_array($flag, ['YES', 'Y', 'TRUE'], true);
        }
        $sku = trim((string) ($row['sku'] ?? $row['(Child) sku'] ?? ''));
        if ($sku === '') {
            return false;
        }

        return isset($this->clearanceYesKeys()[self::clearanceKey($sku)]);
    }

    /**
     * @return array<string, true>
     */
    private function clearanceYesKeys(): array
    {
        if (self::$clearanceKeys !== null) {
            return self::$clearanceKeys;
        }
        if (! function_exists('app')) {
            return [];
        }
        try {
            if (! app()->bound('db') || ! Schema::hasTable('inv_days_clearances')) {
                return self::$clearanceKeys = [];
            }
            $set = [];
            foreach (DB::table('inv_days_clearances')->where('value', 'YES')->get(['sku', 'sku_key']) as $hit) {
                foreach ([(string) ($hit->sku ?? ''), (string) ($hit->sku_key ?? '')] as $raw) {
                    $key = self::clearanceKey($raw);
                    if ($key !== '') {
                        $set[$key] = true;
                    }
                }
            }

            return self::$clearanceKeys = $set;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function clearanceKey(string $sku): string
    {
        $sku = str_replace("\u{00a0}", ' ', trim($sku));
        $sku = preg_replace('/\s+/u', ' ', $sku) ?? $sku;

        return strtolower($sku);
    }

    private static function minNpftPercent(mixed $value, float $fallback): float
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return round(min(99, max(0, (float) $value)), 2);
    }

    /** Std Prc under $15 → rule discount × 0.5. $15 and above keep the saved discount. */
    public static function scaleRuleDisc(float $disc, float $std): float
    {
        if (! ($std > 0) || $std >= self::LOW_STD_UNDER) {
            return $disc;
        }

        return round($disc * self::LOW_STD_FACTOR, 2);
    }

    /**
     * Amazon analytics sends age_days on the row. Other apply jobs do not,
     * so use the same Shopify push clock when the row omitted it.
     *
     * @param  array<string, mixed>  $row
     */
    private function lookupAgeDays(array $row, float $inv): ?float
    {
        $sku = trim((string) ($row['sku'] ?? $row['(Child) sku'] ?? ''));
        if ($sku === '' || ! function_exists('app')) {
            return null;
        }
        try {
            if (! app()->bound('db')) {
                return null;
            }
            $days = app(\App\Http\Controllers\ProductMaster\InvDaysController::class)->ageDaysFor($sku, $inv);
        } catch (\Throwable $e) {
            return null;
        }

        return is_int($days) ? (float) $days : null;
    }

    /**
     * Amazon Dil % and CVR L30/L45. Same inputs as /amazon-tabulator-view.
     *
     * @param  array<string, mixed>  $row
     * @return array{dil:float,cvr:float,cvr45:float,inv:float}|null
     */
    private function lookupAmazonMetric(array $row): ?array
    {
        $sku = trim((string) ($row['sku'] ?? $row['(Child) sku'] ?? ''));
        if ($sku === '' || ! function_exists('app')) {
            return null;
        }
        try {
            if (! app()->bound('db')) {
                return null;
            }
            $hit = app(\App\Http\Controllers\ProductMaster\InvDaysController::class)->amazonStdMetricFor($sku);
        } catch (\Throwable $e) {
            return null;
        }
        if (! is_array($hit)) {
            return null;
        }
        $s30 = (float) $hit['s30'];
        $s60 = (float) $hit['s60'];
        $a30 = (float) $hit['a30'];
        $a60 = (float) $hit['a60'];
        $inv = (float) $hit['inv'];
        $sess45 = ($s30 + $s60) / 2;

        return [
            'inv' => $inv,
            'dil' => $inv > 0 ? ((float) $hit['ov'] / $inv) * 100 : 0.0,
            'cvr' => $s30 > 0 ? ($a30 / $s30) * 100 : 0.0,
            'cvr45' => $sess45 > 0 ? ((($a30 + $a60) / 2) / $sess45) * 100 : 0.0,
        ];
    }

    private function ageDisc(?float $age): float
    {
        if ($age === null) {
            return 0.0;
        }

        return $this->rangeDisc($age, $this->rules['age']);
    }

    public static function excludesShip(string $channel): bool
    {
        return in_array($channel, [
            'shopify_b2b',
            'faire',
            'wayfair',
            'topdawg',
            'depop',
            'mercari_woship',
        ], true);
    }

    /**
     * @param  list<array<string, float>>  $rules
     */
    private function rangeDisc(float $value, array $rules, bool $allowNegative = false): float
    {
        if ($rules === [] || (! $allowNegative && $value < 0)) {
            return 0.0;
        }
        $last = count($rules) - 1;
        foreach ($rules as $i => $rule) {
            $min = (float) $rule['min'];
            $max = (float) $rule['max'];
            if ($max < $min) {
                [$min, $max] = [$max, $min];
            }
            $hit = abs($max - $min) < 0.00001
                ? abs($value - $min) < 0.00001
                : ($value >= $min && ($i === $last ? $value <= $max : $value < $max));
            if ($hit) {
                return max(0, (float) $rule['disc']);
            }
        }

        return 0.0;
    }

    private function cvrDisc(float $cvr, float $cvr60): float
    {
        $cfg = $this->rules['cvr'];
        $trend = 'flat';
        // Same as Amazon: CVR 0 (no sessions) is Down, then the down slabs.
        if ($cvr <= 0.0 || ($cvr60 > 0 && $cvr < $cvr60 - 0.1)) {
            $trend = 'down';
        } elseif ($cvr60 > 0 && $cvr > $cvr60 + 0.1) {
            $trend = 'up';
        }
        if ($trend === 'down') {
            $slabs = [];
            if (($cfg['down2_lt'] ?? 0) > 0) {
                $slabs[] = ['lt' => (float) $cfg['down2_lt'], 'disc' => (float) $cfg['down2_disc']];
            }
            if (($cfg['down_lt'] ?? 0) > 0) {
                $slabs[] = ['lt' => (float) $cfg['down_lt'], 'disc' => (float) $cfg['down_disc']];
            }
            usort($slabs, fn ($a, $b) => $a['lt'] <=> $b['lt']);
            foreach ($slabs as $slab) {
                if ($cvr < $slab['lt']) {
                    return max(0, $slab['disc']);
                }
            }
        }
        if ($trend === 'up') {
            $slabs = [
                ['gt' => (float) ($cfg['up_gt'] ?? 0), 'disc' => (float) ($cfg['up_disc'] ?? 0)],
            ];
            if (is_numeric($cfg['up2_gt'] ?? null)) {
                $slabs[] = ['gt' => (float) $cfg['up2_gt'], 'disc' => (float) ($cfg['up2_disc'] ?? 0)];
            }
            usort($slabs, fn ($a, $b) => $b['gt'] <=> $a['gt']);
            foreach ($slabs as $slab) {
                if ($cvr > $slab['gt']) {
                    return max(0, $slab['disc']);
                }
            }
        }

        return max(0, (float) ($cfg['flat_disc'] ?? 0));
    }

    private function reviewDisc(float $reviews): float
    {
        $max = (int) $this->rules['review_max'];
        if (! ($reviews > 0) || $reviews >= $max) {
            return 0.0;
        }
        foreach ($this->rules['reviews'] as $rule) {
            if ($reviews >= (float) $rule['min'] && $reviews <= (float) $rule['max']) {
                return max(0, (float) $rule['disc']);
            }
        }

        return 0.0;
    }

    /**
     * @param  mixed  $incoming
     * @param  list<array<string, float>>  $fallback
     * @return list<array<string, float>>
     */
    private static function ranges($incoming, array $fallback): array
    {
        if (! is_array($incoming)) {
            return $fallback;
        }
        $rules = [];
        foreach ($incoming as $item) {
            if (! is_array($item) || ! is_numeric($item['min'] ?? null) || ! is_numeric($item['max'] ?? null)) {
                continue;
            }
            $min = (float) $item['min'];
            $max = (float) $item['max'];
            if ($max < $min) {
                [$min, $max] = [$max, $min];
            }
            $disc = is_numeric($item['disc'] ?? null) ? (float) $item['disc'] : 0.0;
            $rules[] = ['min' => $min, 'max' => $max, 'disc' => min(100, max(0, $disc))];
        }

        return $rules !== [] ? $rules : $fallback;
    }

    /**
     * @param  mixed  $incoming
     * @param  array<string, float>  $defaults
     * @return array<string, float>
     */
    private static function cvr($incoming, array $defaults): array
    {
        $out = $defaults;
        if (! is_array($incoming)) {
            return $out;
        }
        foreach (['down2_lt', 'down_lt', 'up_gt', 'up2_gt', 'down2_disc', 'down_disc', 'up_disc', 'up2_disc', 'flat_disc'] as $key) {
            if (is_numeric($incoming[$key] ?? null) && (float) $incoming[$key] >= 0) {
                $out[$key] = (float) $incoming[$key];
            }
        }
        if (! is_numeric($incoming['down2_disc'] ?? null)) {
            $out['down2_disc'] = $out['down_disc'];
        }
        if (! is_numeric($incoming['up2_disc'] ?? null)) {
            $out['up2_disc'] = $out['up_disc'];
        }

        return $out;
    }

    /**
     * @return array{dil:list<array<string,float>>,age:list<array<string,float>>,cvr:array<string,float>,reviews:list<array<string,float>>,review_max:int,buss:list<array<string,float>>}
     */
    private static function defaults(): array
    {
        return [
            'dil' => [
                ['min' => 0, 'max' => 0, 'disc' => 0],
                ['min' => 0.1, 'max' => 10, 'disc' => 0],
                ['min' => 10, 'max' => 25, 'disc' => 0],
                ['min' => 25, 'max' => 50, 'disc' => 0],
                ['min' => 50, 'max' => 100, 'disc' => 0],
                ['min' => 100, 'max' => 9999, 'disc' => 0],
            ],
            'age' => [
                ['min' => 0, 'max' => 30, 'disc' => 0],
                ['min' => 31, 'max' => 60, 'disc' => 0],
                ['min' => 61, 'max' => 90, 'disc' => 0],
                ['min' => 91, 'max' => 180, 'disc' => 0],
                ['min' => 181, 'max' => 365, 'disc' => 0],
                ['min' => 366, 'max' => 9999, 'disc' => 0],
            ],
            'cvr' => [
                'down2_lt' => 4, 'down2_disc' => 0, 'down_lt' => 7, 'down_disc' => 0,
                'up_gt' => 10, 'up_disc' => 0, 'up2_gt' => 15, 'up2_disc' => 0, 'flat_disc' => 0,
            ],
            'reviews' => [
                ['min' => 1, 'max' => 2, 'disc' => 4],
                ['min' => 2, 'max' => 3, 'disc' => 4],
            ],
            'review_max' => 4,
            'buss' => [
                ['min' => 0, 'max' => 15, 'disc' => 0],
                ['min' => 15, 'max' => 50, 'disc' => 0],
                ['min' => 50, 'max' => 9999, 'disc' => 0],
            ],
            'zero_sold_disc' => 0,
        ];
    }

    private static function discPercent(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return round(min(100, max(0, (float) $value)), 2);
    }

    /**
     * Sold qty for this channel. Missing sold data does not treat the row as 0 sold.
     *
     * @param  array<string, mixed>  $row
     */
    private function isZeroSold(array $row): bool
    {
        $sold = $this->soldQty($row);
        if ($sold === null) {
            return false;
        }

        return ! ($sold > 0);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function soldQty(array $row): ?float
    {
        $key = match ($this->channel) {
            'ebay1', 'ebay2', 'ebay3', 'ebay2op' => 'ebay_l30',
            'shopify_b2c' => 'b2c_l30',
            'macys', 'macy' => 'mc_l30',
            'purchasing_power' => 'pp_l30',
            'temu', 'temu2', 'temu3' => 'temu_l30',
            'aliexpress', 'shein', 'faire' => 'al30',
            'amazon' => 'a_l30',
            default => null,
        };
        if ($key !== null && array_key_exists($key, $row) && is_numeric($row[$key])) {
            return (float) $row[$key];
        }
        foreach (['sold', 'a_l30', 'A_L30', 'ebay_l30', 'b2c_l30', 'mc_l30', 'pp_l30'] as $fallback) {
            if (array_key_exists($fallback, $row) && is_numeric($row[$fallback])) {
                return (float) $row[$fallback];
            }
        }

        return null;
    }
}
