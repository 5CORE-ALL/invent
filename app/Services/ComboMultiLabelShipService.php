<?php

namespace App\Services;

use App\Models\ProductMaster;

/**
 * Combo + Label Qty >= 2: persist Values.ship as the multi-package total.
 *
 * - "A + B" combos: sum of each package/component ship
 * - Named COMBO (no "+"): single-package ship × Label Qty (Label 2 = double)
 */
class ComboMultiLabelShipService
{
    public const MIN_LABEL_QTY = 2;

    public function shouldApply(string $sku, string $parent, mixed $labelQty): bool
    {
        if ($sku === '' || stripos($sku, 'PARENT') !== false) {
            return false;
        }
        if ($this->labelQty($labelQty) < self::MIN_LABEL_QTY) {
            return false;
        }

        return ProductMaster::isComboSku($sku, $parent);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  callable(string): (?array<string, mixed>)|null  $findComponent
     * @return array<string, mixed>
     */
    public function applyToValues(string $sku, string $parent, array $values, ?callable $findComponent = null): array
    {
        if (! $this->shouldApply($sku, $parent, $values['label_qty'] ?? null)) {
            return $this->restoreSinglePackageShip($sku, $parent, $values);
        }

        $qty = $this->labelQty($values['label_qty'] ?? null);
        $alreadyApplied = (int) ($values['combo_label_ship_qty'] ?? 0) === $qty;
        $calculated = $this->calculateShip($sku, $values, $qty, $findComponent, $alreadyApplied);
        if ($calculated === null) {
            return $values;
        }

        $values['ship'] = $calculated['ship'];
        if (array_key_exists('ship_base', $calculated)) {
            $values['ship_base'] = $calculated['ship_base'];
        }
        $values['combo_label_ship_qty'] = $qty;

        return $values;
    }

    /**
     * Write the combo total onto Values + validated so shipping history logs Ship.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $validated
     */
    public function syncValidated(ProductMaster $product, array &$values, array &$validated): void
    {
        $sku = (string) ($validated['sku'] ?? $product->sku ?? '');
        $parent = (string) ($validated['parent'] ?? $product->parent ?? '');
        $applied = $this->applyToValues($sku, $parent, $values);
        if (! $this->valuesChanged($values, $applied, ['ship', 'ship_base'])) {
            return;
        }

        if (array_key_exists('ship', $applied) && $this->numericOrNull($applied['ship'] ?? null) !== null) {
            $values['ship'] = $applied['ship'];
            $validated['ship'] = $applied['ship'];
        }
        if (array_key_exists('ship_base', $applied) && $this->numericOrNull($values['ship_base'] ?? null) === null) {
            $values['ship_base'] = $applied['ship_base'];
            $validated['ship_base'] = $applied['ship_base'];
        }
        if (array_key_exists('combo_label_ship_qty', $applied)) {
            $values['combo_label_ship_qty'] = $applied['combo_label_ship_qty'];
        } elseif (array_key_exists('combo_label_ship_qty', $values) && ! $this->shouldApply($sku, $parent, $values['label_qty'] ?? null)) {
            unset($values['combo_label_ship_qty']);
        }
    }

    public function persistForProduct(ProductMaster $product): bool
    {
        $sku = (string) $product->sku;
        $parent = (string) ($product->parent ?? '');
        $values = $this->decodeValues($product->Values);
        if (! $this->shouldApply($sku, $parent, $values['label_qty'] ?? null)
            && ! $this->shouldRestoreSingle($sku, $parent, $values)
        ) {
            return false;
        }

        $applied = $this->applyToValues($sku, $parent, $values);
        if (! $this->valuesChanged($values, $applied, ['ship', 'ship_base'])) {
            return false;
        }

        $product->Values = $applied;
        $product->save();

        return true;
    }

    /**
     * When a component SKU's ship changes, refresh every "A + B" combo that includes it.
     */
    public function persistCombosContainingSku(string $sku): void
    {
        $compact = ProductMaster::skuCompact($sku);
        if ($compact === '' || str_contains($sku, '+')) {
            return;
        }

        $candidates = ProductMaster::query()
            ->where('sku', 'like', '%+%')
            ->where('sku', 'like', '%'.$sku.'%')
            ->get(['id', 'sku', 'parent', 'Values']);

        foreach ($candidates as $product) {
            $parts = ProductMaster::parseComboComponentSkus((string) $product->sku);
            foreach ($parts as $part) {
                if (ProductMaster::skuCompact($part) === $compact) {
                    $this->persistForProduct($product);
                    break;
                }
            }
        }
    }

    /**
     * Recalculate combo ship on shipping-master rows and persist when it changed.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function applyAndPersistRows(array $rows): array
    {
        $byCompact = [];
        foreach ($rows as $i => $row) {
            $compact = ProductMaster::skuCompact((string) ($row['SKU'] ?? $row['sku'] ?? ''));
            if ($compact !== '') {
                $byCompact[$compact] = $i;
            }
        }

        $findComponent = function (string $componentSku) use ($rows, $byCompact): ?array {
            $idx = $byCompact[ProductMaster::skuCompact($componentSku)] ?? null;

            return $idx === null ? null : $rows[$idx];
        };

        $indexes = [];
        foreach ($rows as $i => $row) {
            $sku = (string) ($row['SKU'] ?? $row['sku'] ?? '');
            $parent = (string) ($row['Parent'] ?? $row['parent'] ?? '');
            if ($this->shouldApply($sku, $parent, $row['label_qty'] ?? null)) {
                $indexes[] = $i;
            }
        }

        // Named COMBO first so A + B sums see already-doubled leaf ships when needed.
        usort($indexes, static function (int $a, int $b) use ($rows): int {
            $skuA = (string) ($rows[$a]['SKU'] ?? '');
            $skuB = (string) ($rows[$b]['SKU'] ?? '');
            $plusA = str_contains($skuA, '+') ? 1 : 0;
            $plusB = str_contains($skuB, '+') ? 1 : 0;

            return $plusA <=> $plusB;
        });

        foreach ($indexes as $i) {
            $row = $rows[$i];
            $sku = (string) ($row['SKU'] ?? $row['sku'] ?? '');
            $parent = (string) ($row['Parent'] ?? $row['parent'] ?? '');
            $applied = $this->applyToValues($sku, $parent, $row, $findComponent);
            if (! $this->valuesChanged($row, $applied, ['ship', 'ship_base'])) {
                continue;
            }

            $rows[$i]['ship'] = $applied['ship'];
            if (array_key_exists('ship_base', $applied)) {
                $rows[$i]['ship_base'] = $applied['ship_base'];
            }

            $id = $row['id'] ?? null;
            if (! $id) {
                continue;
            }

            $product = ProductMaster::find($id);
            if (! $product) {
                continue;
            }

            $values = $this->decodeValues($product->Values);
            $values['ship'] = $applied['ship'];
            if (array_key_exists('ship_base', $applied) && $this->numericOrNull($values['ship_base'] ?? null) === null) {
                $values['ship_base'] = $applied['ship_base'];
            }
            if (array_key_exists('combo_label_ship_qty', $applied)) {
                $values['combo_label_ship_qty'] = $applied['combo_label_ship_qty'];
            }
            $product->Values = $values;
            $product->save();
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  callable(string): (?array<string, mixed>)|null  $findComponent
     * @return array{ship: float, ship_base?: float}|null
     */
    public function calculateShip(string $sku, array $values, int $labelQty, ?callable $findComponent = null, bool $alreadyApplied = false): ?array
    {
        $components = ProductMaster::parseComboComponentSkus($sku);
        if (count($components) >= 2) {
            $sum = 0.0;
            $found = 0;
            foreach (array_slice($components, 0, $labelQty) as $part) {
                $component = $findComponent
                    ? $findComponent($part)
                    : $this->loadComponentRow($part);
                if (! is_array($component)) {
                    continue;
                }
                $compShip = $this->numericOrNull($component['ship'] ?? null);
                if ($compShip === null) {
                    continue;
                }
                $sum += $compShip;
                $found++;
            }
            if ($found > 0) {
                return ['ship' => round($sum, 2)];
            }
        }

        $charges = $this->chargeTotal($values);
        $base = $this->numericOrNull($values['ship_base'] ?? null);
        if ($base === null) {
            if ($alreadyApplied) {
                return null;
            }
            $current = $this->numericOrNull($values['ship'] ?? null);
            if ($current === null) {
                return null;
            }
            $base = round($current - $charges, 2);
            if ($base < 0) {
                $base = $current;
            }
        }

        return [
            'ship_base' => $base,
            'ship' => round(($base + $charges) * $labelQty, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function restoreSinglePackageShip(string $sku, string $parent, array $values): array
    {
        if (! $this->shouldRestoreSingle($sku, $parent, $values)) {
            return $values;
        }

        $base = $this->numericOrNull($values['ship_base'] ?? null);
        if ($base === null) {
            return $values;
        }

        $values['ship'] = round($base + $this->chargeTotal($values), 2);
        unset($values['combo_label_ship_qty']);

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function shouldRestoreSingle(string $sku, string $parent, array $values): bool
    {
        if ($sku === '' || stripos($sku, 'PARENT') !== false) {
            return false;
        }
        if (! ProductMaster::isComboSku($sku, $parent)) {
            return false;
        }

        return $this->labelQty($values['label_qty'] ?? null) < self::MIN_LABEL_QTY
            && $this->numericOrNull($values['ship_base'] ?? null) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadComponentRow(string $sku): ?array
    {
        $product = ProductMaster::findByCompactSku($sku);
        if (! $product) {
            return null;
        }
        $values = $this->decodeValues($product->Values);

        return array_merge($values, ['SKU' => $product->sku, 'ship' => $values['ship'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  list<string>  $keys
     */
    private function valuesChanged(array $before, array $after, array $keys): bool
    {
        foreach ($keys as $key) {
            $old = $this->numericOrNull($before[$key] ?? null);
            $new = $this->numericOrNull($after[$key] ?? null);
            if ($new === null && $old === null) {
                continue;
            }
            if ($new === null || $old === null || abs($old - $new) >= 0.009) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function chargeTotal(array $values): float
    {
        return $this->chargeAmount($values, 'handling_charge')
            + $this->chargeAmount($values, 'o_size_charge')
            + $this->chargeAmount($values, 'pr_charge');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function chargeAmount(array $values, string $key): float
    {
        $raw = $values[$key] ?? null;
        if ($raw === null || $raw === '') {
            return 0.0;
        }

        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    private function labelQty(mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return 0;
        }

        return is_numeric($raw) ? (int) $raw : 0;
    }

    private function numericOrNull(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeValues(mixed $values): array
    {
        if (is_array($values)) {
            return $values;
        }
        if (is_string($values) && $values !== '') {
            $decoded = json_decode($values, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
