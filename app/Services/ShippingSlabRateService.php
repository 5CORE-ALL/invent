<?php

namespace App\Services;

use App\Models\ProductMaster;
use Illuminate\Support\Facades\Cache;

/**
 * Weight-slab carrier rates — mirrors shipping-master.blade.php slab logic.
 */
class ShippingSlabRateService
{
    private const KG_TO_LB = 2.2046226218;

    private const WT_ACT_OZ_LB_UPPER = [0.06, 0.13, 0.19, 0.25, 0.31, 0.38, 0.44, 0.50, 0.56, 0.63, 0.69, 0.75, 0.81, 0.88, 0.94];

    private const WT_ACT_OZ_FILTER_OPTIONS = [2, 4, 6, 12];

    private const WT_ACT_OZ_FILTER_SLABS = [
        2 => ['ozMin' => 0.01, 'ozMax' => 2, 'label' => '0.01–2 oz (0.01 – 0.125 lb)'],
        4 => ['ozMin' => 2.01, 'ozMax' => 4, 'label' => '2.01–4 oz (0.126 – 0.25 lb)'],
        6 => ['ozMin' => 4.01, 'ozMax' => 8, 'label' => '4.01–8 oz (0.251 – 0.5 lb)'],
        12 => ['ozMin' => 8.01, 'ozMax' => 12, 'label' => '8.01–12 oz (0.51 – 0.75 lb)'],
    ];

    private const WT_ACT_OZ_1599_SLAB = [
        'ozMin' => 12.01,
        'ozMax' => 15.99,
        'label' => '12.01–15.99 oz (0.751 – 1 lb)',
    ];

    private const WT_ACT_UPWARD_LB_BANDS = [
        ['key' => 'lb_101_2', 'lbMin' => 1, 'lbMax' => 2, 'label' => '1 lb – 2 lb'],
        ['key' => 'lb_201_3', 'lbMin' => 2.01, 'lbMax' => 3, 'label' => '2.01 lb – 3 lb'],
        ['key' => 'lb_301_4', 'lbMin' => 3.01, 'lbMax' => 4, 'label' => '3.01 lb – 4 lb'],
        ['key' => 'lb_401_5', 'lbMin' => 4.01, 'lbMax' => 5, 'label' => '4.01 lb – 5 lb'],
        ['key' => 'lb_501_6', 'lbMin' => 5.01, 'lbMax' => 6, 'label' => '5.01 lb – 6 lb'],
        ['key' => 'lb_601_7', 'lbMin' => 6.01, 'lbMax' => 7, 'label' => '6.01 lb – 7 lb'],
        ['key' => 'lb_701_8', 'lbMin' => 7.01, 'lbMax' => 8, 'label' => '7.01 lb – 8 lb'],
        ['key' => 'lb_801_9', 'lbMin' => 8.01, 'lbMax' => 9, 'label' => '8.01 lb – 9 lb'],
        ['key' => 'lb_901_10', 'lbMin' => 9.01, 'lbMax' => 10, 'label' => '9.01 lb – 10 lb'],
        ['key' => 'lb_1001_11', 'lbMin' => 10.01, 'lbMax' => 11, 'label' => '10.01 lb – 11 lb'],
        ['key' => 'lb_1101_12', 'lbMin' => 11.01, 'lbMax' => 12, 'label' => '11.01 lb – 12 lb'],
        ['key' => 'lb_1201_13', 'lbMin' => 12.01, 'lbMax' => 13, 'label' => '12.01 lb – 13 lb'],
        ['key' => 'lb_1301_14', 'lbMin' => 13.01, 'lbMax' => 14, 'label' => '13.01 lb – 14 lb'],
        ['key' => 'lb_1401_20', 'lbMin' => 14.01, 'lbMax' => 20, 'label' => '14.01 lb – 20 lb'],
        ['key' => 'lb_20_30', 'lbMin' => 20.01, 'lbMax' => 25, 'label' => '20.01 lb – 25 lb'],
        ['key' => 'lb_2501_30', 'lbMin' => 25.01, 'lbMax' => 30, 'label' => '25.01 lb – 30 lb'],
        ['key' => 'lb_30_40', 'lbMin' => 30.01, 'lbMax' => 40, 'label' => '30.01 lb – 40 lb'],
        ['key' => 'lb_40_50', 'lbMin' => 40.01, 'lbMax' => 50, 'label' => '40.01 lb – 50 lb'],
        ['key' => 'lb_gt50', 'lbMin' => 50.01, 'lbMax' => null, 'label' => '> 50.01 lb'],
    ];

    public function getCarrierRateForWeight(?float $weightLb, string $carrier = 'ship', ?string $sku = null): array
    {
        $slabKey = $this->resolveSlabKeyForWeight($weightLb);
        if ($slabKey === null) {
            return [
                'success' => false,
                'rate' => null,
                'slab_key' => null,
                'slab_label' => null,
                'mixed' => false,
                'message' => 'Could not resolve weight slab.',
            ];
        }

        $slabRates = $this->getAllSlabCarrierRates($carrier);
        $slabInfo = $slabRates[$slabKey] ?? null;
        $rate = $slabInfo['rate'] ?? null;
        $mixed = (bool) ($slabInfo['mixed'] ?? false);

        if ($rate === null && $sku) {
            $skuRate = $this->getSkuCarrierRate($sku, $carrier);
            if ($skuRate !== null) {
                $rate = $skuRate;
            }
        }

        return [
            'success' => true,
            'rate' => $rate,
            'slab_key' => $slabKey,
            'slab_label' => $slabInfo['slab_label'] ?? $slabKey,
            'mixed' => $mixed,
        ];
    }

    /** @return array<string, array{slab_key: string, slab_label: string, rate: ?float, mixed: bool}> */
    public function getAllSlabCarrierRates(string $carrier = 'ship'): array
    {
        return Cache::remember("shipping_slab_rates:{$carrier}", 300, function () use ($carrier) {
            $products = $this->loadShippingMasterRows();
            $result = [];

            foreach ($this->getSlabDefinitions() as $slab) {
                $items = array_values(array_filter(
                    $products,
                    fn (array $item) => ! $this->isParentSku($item) && $this->matchesWtActLbBand($item, $slab['key'])
                ));
                $summary = $this->computeSlabCarrierSummary($items, $carrier);

                $result[$slab['key']] = [
                    'slab_key' => $slab['key'],
                    'slab_label' => $slab['label'],
                    'rate' => $summary['uniformValue'],
                    'mixed' => $summary['uniformValue'] === null && count($summary['distinctValues']) > 0,
                ];
            }

            return $result;
        });
    }

    public function resolveSlabKeyForWeight(?float $weightLb): ?string
    {
        if ($weightLb === null || ! is_finite($weightLb)) {
            return 'lb_0';
        }

        if ($weightLb === 0.0) {
            return 'lb_0';
        }

        foreach (self::WT_ACT_OZ_FILTER_OPTIONS as $oz) {
            if ($this->matchesWtActOzLbBand($weightLb, "oz_{$oz}")) {
                return "oz_{$oz}";
            }
        }

        if ($this->matchesWtActOzLbBand($weightLb, 'oz_1599')) {
            return 'oz_1599';
        }

        foreach (self::WT_ACT_UPWARD_LB_BANDS as $band) {
            if ($this->matchesWtActUpwardLbBand($weightLb, $band['key'])) {
                return $band['key'];
            }
        }

        return null;
    }

    /** @return list<array{key: string, label: string}> */
    private function getSlabDefinitions(): array
    {
        $slabs = [['key' => 'lb_0', 'label' => '0 lb']];

        foreach (self::WT_ACT_OZ_FILTER_OPTIONS as $oz) {
            $slabs[] = [
                'key' => "oz_{$oz}",
                'label' => $this->wtActOzFilterSlabLabel($oz),
            ];
        }

        $slabs[] = [
            'key' => 'oz_1599',
            'label' => self::WT_ACT_OZ_1599_SLAB['label'],
        ];

        foreach (self::WT_ACT_UPWARD_LB_BANDS as $index => $band) {
            $slabs[] = [
                'key' => $band['key'],
                'label' => $this->wtActUpwardBandLabel($band, $index),
            ];
        }

        return $slabs;
    }

    /** @return list<array<string, mixed>> */
    private function loadShippingMasterRows(): array
    {
        $rows = [];

        foreach (ProductMaster::query()->orderBy('parent')->orderBy('sku')->get(['sku', 'Values']) as $product) {
            $row = ['SKU' => $product->sku];
            $values = is_array($product->Values)
                ? $product->Values
                : (is_string($product->Values) ? json_decode($product->Values, true) : []);

            if (is_array($values)) {
                $row = array_merge($row, $values);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function getSkuCarrierRate(string $sku, string $carrier): ?float
    {
        $product = ProductMaster::query()->where('sku', $sku)->first(['Values']);
        if (! $product) {
            return null;
        }

        $values = is_array($product->Values)
            ? $product->Values
            : (is_string($product->Values) ? json_decode($product->Values, true) : []);

        if (! is_array($values) || ! array_key_exists($carrier, $values)) {
            return null;
        }

        $value = $values[$carrier];
        if ($value === null || $value === '') {
            return null;
        }

        $num = is_numeric($value) ? (float) $value : null;

        return ($num !== null && is_finite($num)) ? $this->normalizeSlabRate($num) : null;
    }

    /** @param array<string, mixed> $item */
    private function isParentSku(array $item): bool
    {
        $sku = strtoupper(trim((string) ($item['SKU'] ?? '')));

        return $sku !== '' && str_contains($sku, 'PARENT');
    }

    /** @param array<string, mixed> $item */
    private function itemWeightActLbResolved(array $item): ?float
    {
        $lbRaw = $item['wt_act'] ?? null;
        if ($lbRaw !== null && $lbRaw !== '') {
            $lb = (float) $lbRaw;
            if (is_finite($lb) && $lb > 0) {
                return round($lb, 2);
            }
        }

        $kgRaw = $item['wt_act_kg'] ?? null;
        if ($kgRaw !== null && $kgRaw !== '') {
            $kg = (float) $kgRaw;
            if (is_finite($kg) && $kg > 0) {
                return round($kg * self::KG_TO_LB, 2);
            }
        }

        return null;
    }

    /** @param array<string, mixed> $item */
    private function itemWeightActMissing(array $item): bool
    {
        $lb = $item['wt_act'] ?? null;
        $kg = $item['wt_act_kg'] ?? null;

        return ($lb === null || $lb === '') && ($kg === null || $kg === '');
    }

    /** @param array<string, mixed> $item */
    private function matchesWtActLbBand(array $item, string $band): bool
    {
        if ($band === 'lb_0') {
            $w = $this->itemWeightActLbResolved($item);

            return $w === null || $w === 0.0;
        }

        $w = $this->itemWeightActLbResolved($item);
        if ($w === null || ! is_finite($w)) {
            return false;
        }

        if ($band === 'oz_1599' || preg_match('/^oz_\d+$/', $band)) {
            return $this->matchesWtActOzLbBand($w, $band);
        }

        foreach (self::WT_ACT_UPWARD_LB_BANDS as $def) {
            if ($def['key'] === $band) {
                return $this->matchesWtActUpwardLbBand($w, $band);
            }
        }

        return false;
    }

    private function matchesWtActOzLbBand(float $w, string $band): bool
    {
        if ($band === 'oz_1599') {
            $s = self::WT_ACT_OZ_1599_SLAB;

            return $w >= ($s['ozMin'] / 16) && $w <= ($s['ozMax'] / 16);
        }

        if (! preg_match('/^oz_(\d+)$/', $band, $matches)) {
            return false;
        }

        $oz = (int) $matches[1];
        if ($oz < 1 || $oz > 15) {
            return false;
        }

        $bounds = $this->wtActOzFilterSlabBounds($oz);
        if (isset(self::WT_ACT_OZ_FILTER_SLABS[$oz])) {
            return $w >= $bounds['lbMin'] && $w <= $bounds['lbMax'];
        }

        if ($oz === 1) {
            return $w >= 0.01 && $w <= $bounds['lbMax'];
        }

        return $w > $bounds['lbMin'] && $w <= $bounds['lbMax'];
    }

    private function matchesWtActUpwardLbBand(float $w, string $band): bool
    {
        $index = null;
        $def = null;
        foreach (self::WT_ACT_UPWARD_LB_BANDS as $i => $candidate) {
            if ($candidate['key'] === $band) {
                $index = $i;
                $def = $candidate;
                break;
            }
        }

        if ($def === null || $index === null) {
            return false;
        }

        if ($def['lbMax'] === null) {
            $lower = $def['lbMin'] ?? $this->wtActUpwardBandPrevMaxLb($index);

            return $def['lbMin'] !== null ? $w >= $lower : $w > $lower;
        }

        if ($def['lbMin'] !== null) {
            return $w >= $def['lbMin'] && $w <= $def['lbMax'];
        }

        $lowerExclusive = $this->wtActUpwardBandPrevMaxLb($index);

        return $w > $lowerExclusive && $w <= $def['lbMax'];
    }

    /** @return array{lbMin: float, lbMax: float, ozMin: float, ozMax: float} */
    private function wtActOzFilterSlabBounds(int $oz): array
    {
        $custom = self::WT_ACT_OZ_FILTER_SLABS[$oz] ?? null;
        if ($custom) {
            return [
                'ozMin' => $custom['ozMin'],
                'ozMax' => $custom['ozMax'],
                'lbMin' => $custom['ozMin'] === 0.01 ? 0.01 : ($custom['ozMin'] / 16),
                'lbMax' => $custom['ozMax'] / 16,
            ];
        }

        return [
            'ozMin' => $oz - 1,
            'ozMax' => $oz,
            'lbMin' => self::WT_ACT_OZ_LB_UPPER[$oz - 2],
            'lbMax' => self::WT_ACT_OZ_LB_UPPER[$oz - 1],
        ];
    }

    private function wtActOzFilterSlabLabel(int $oz): string
    {
        $custom = self::WT_ACT_OZ_FILTER_SLABS[$oz] ?? null;
        if ($custom && ! empty($custom['label'])) {
            return $custom['label'];
        }

        $bounds = $this->wtActOzFilterSlabBounds($oz);

        return sprintf(
            '%s–%s oz (%s – %s lb)',
            $bounds['ozMin'],
            $bounds['ozMax'],
            $this->wtActOzToLb($bounds['ozMin']),
            $this->wtActOzToLb($bounds['ozMax'])
        );
    }

    /** @param array{key: string, lbMin: ?float, lbMax: ?float, label?: string} $band */
    private function wtActUpwardBandLabel(array $band, int $index): string
    {
        if (! empty($band['label'])) {
            return $band['label'];
        }

        $ozMin = $this->wtActLbBandOzMin($band['lbMin'] ?? $this->wtActUpwardBandPrevMaxLb($index));
        $ozMax = $band['lbMax'] !== null ? $this->wtActLbBandOzMax($band['lbMax']) : null;

        if ($ozMax !== null) {
            return sprintf(
                '%s–%s oz (%s – %s lb)',
                $ozMin,
                $ozMax,
                $this->formatNumber($band['lbMin'] ?? $this->wtActUpwardBandPrevMaxLb($index), 2),
                $this->formatNumber($band['lbMax'], 2)
            );
        }

        return sprintf('> %s oz (> %s lb)', $ozMin, $this->formatNumber($band['lbMin'] ?? 0, 2));
    }

    private function wtActUpwardBandPrevMaxLb(int $index): float
    {
        return $index === 0 ? 1.0 : (float) self::WT_ACT_UPWARD_LB_BANDS[$index - 1]['lbMax'];
    }

    private function wtActLbBandOzMin(float $lb): int
    {
        return (int) ceil($lb * 16 - 1e-9);
    }

    private function wtActLbBandOzMax(float $lb): int
    {
        return (int) floor($lb * 16 + 1e-9);
    }

    private function wtActOzToLb(float $oz): string
    {
        return $this->formatNumber(round(($oz / 16) * 100) / 100, 2);
    }

    private function formatNumber(float $value, int $decimals): string
    {
        return number_format($value, $decimals, '.', '');
    }

    /** @param list<array<string, mixed>> $items */
    private function computeSlabCarrierSummary(array $items, string $carrierKey): array
    {
        $distinct = [];
        $filled = 0;
        $missing = 0;

        foreach ($items as $item) {
            $raw = $item[$carrierKey] ?? null;
            if ($raw === null || $raw === '') {
                $missing++;
                continue;
            }

            $num = is_numeric($raw) ? (float) $raw : null;
            if ($num === null || ! is_finite($num)) {
                $missing++;
                continue;
            }

            $filled++;
            $rounded = $this->normalizeSlabRate($num);
            $distinct[(string) $rounded] = $rounded;
        }

        $distinctValues = array_values($distinct);
        sort($distinctValues);

        $uniformValue = ($filled > 0 && count($distinctValues) === 1 && $missing === 0)
            ? $distinctValues[0]
            : null;

        return [
            'uniformValue' => $uniformValue,
            'distinctValues' => $distinctValues,
        ];
    }

    private function normalizeSlabRate(float $value): float
    {
        return round($value, 2);
    }
}
