<?php

namespace App\Services;

use App\Models\DimWtSkuLink;
use App\Models\ProductMaster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DimWtSkuLinkService
{
    /** Dim/wt fields used to decide whether sibling SKUs match. */
    public const MATCH_KEYS = ['wt_act', 'l', 'w', 'h'];

    /** Dim/wt fields copied when a linked SKU's data changes. */
    public const SYNC_KEYS = [
        'wt_act', 'wt_act_kg', 'wt_decl',
        'l', 'w', 'h',
        'l_decl', 'w_decl', 'h_decl',
        'l_cm', 'w_cm', 'h_cm', 'cbm',
        'ctn_l', 'ctn_w', 'ctn_h', 'ctn_cbm', 'ctn_qty', 'ctn_cbm_each',
        'ctn_gwt', 'ctn_weight_kg', 'ctn_weight_lb', 'cbm_e',
    ];

    public function tableReady(): bool
    {
        try {
            return Schema::hasTable('dim_wt_sku_links');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function normalizeSku(string $sku): string
    {
        $sku = str_replace("\u{00a0}", ' ', $sku);
        $sku = preg_replace('/\s+/u', ' ', trim($sku)) ?? trim($sku);

        return strtoupper($sku);
    }

    public function isParentSku(?string $sku): bool
    {
        return $sku !== null && $sku !== '' && stripos($sku, 'PARENT') !== false;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function fingerprintFromValues(array $values): ?string
    {
        $parts = [];
        foreach (self::MATCH_KEYS as $key) {
            $n = $this->numericOrNull($values[$key] ?? null);
            if ($n === null || $n <= 0) {
                return null;
            }
            $parts[] = $key.'='.number_format($n, 4, '.', '');
        }

        return md5(implode('|', $parts));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{wt_act: ?float, l: ?float, w: ?float, h: ?float}
     */
    public function matchSnapshot(array $values): array
    {
        return [
            'wt_act' => $this->numericOrNull($values['wt_act'] ?? null),
            'l' => $this->numericOrNull($values['l'] ?? null),
            'w' => $this->numericOrNull($values['w'] ?? null),
            'h' => $this->numericOrNull($values['h'] ?? null),
        ];
    }

    /**
     * Sibling child SKUs under the same parent with matching wt_act / L / W / H.
     *
     * @return Collection<int, ProductMaster>
     */
    public function findMatchingSiblingProducts(ProductMaster $product): Collection
    {
        if ($this->isParentSku($product->sku)) {
            return collect();
        }

        $parent = trim((string) ($product->parent ?? ''));
        if ($parent === '') {
            return collect();
        }

        $sourceValues = $this->valuesArray($product);
        $fingerprint = $this->fingerprintFromValues($sourceValues);
        if ($fingerprint === null) {
            return collect();
        }

        $sourceNorm = $this->normalizeSku((string) $product->sku);

        return ProductMaster::query()
            ->whereRaw('UPPER(TRIM(parent)) = ?', [strtoupper($parent)])
            ->where('sku', 'NOT LIKE', 'PARENT %')
            ->where('id', '!=', $product->id)
            ->get()
            ->filter(function (ProductMaster $sib) use ($fingerprint, $sourceNorm) {
                if ($this->isParentSku($sib->sku)) {
                    return false;
                }
                if ($this->normalizeSku((string) $sib->sku) === $sourceNorm) {
                    return false;
                }

                return $this->fingerprintFromValues($this->valuesArray($sib)) === $fingerprint;
            })
            ->values();
    }

    /**
     * On verified change: match siblings by dim/wt, persist links, apply verified to matches.
     *
     * @return array{linked_skus: list<string>, updated_skus: list<string>, group_key: ?string}
     */
    public function onVerifiedChanged(ProductMaster $product, int $verified, ?string $user = null): array
    {
        $empty = ['linked_skus' => [], 'updated_skus' => [], 'group_key' => null];
        if (! $this->tableReady() || $this->isParentSku($product->sku)) {
            return $empty;
        }

        $matches = $this->findMatchingSiblingProducts($product);
        $group = collect([$product])->merge($matches)->values();
        $groupKey = $this->saveGroup($group, $user);

        $updated = [];
        foreach ($matches as $sib) {
            if ($this->setVerifiedOnProduct($sib, $verified)) {
                $updated[] = (string) $sib->sku;
            }
        }

        $linked = $matches->map(fn (ProductMaster $p) => (string) $p->sku)->values()->all();

        return [
            'linked_skus' => $linked,
            'updated_skus' => $updated,
            'group_key' => $groupKey,
        ];
    }

    /**
     * On dim/wt save: copy to linked SKUs only when "save also to Siblings" is checked.
     * Unchecking that box removes the Link SKU group.
     *
     * @param  array<string, mixed>  $changedFields
     * @return array{linked_skus: list<string>, updated_skus: list<string>, delinked?: bool, former_skus?: list<string>}
     */
    public function onDimWtChanged(ProductMaster $product, array $changedFields, ?string $user = null): array
    {
        $empty = ['linked_skus' => [], 'updated_skus' => [], 'delinked' => false];
        if (! $this->tableReady() || $this->isParentSku($product->sku)) {
            return $empty;
        }

        $optIn = filter_var($changedFields['save_also_to_siblings'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hasDimFields = false;
        foreach (self::SYNC_KEYS as $key) {
            if (array_key_exists($key, $changedFields)) {
                $hasDimFields = true;
                break;
            }
        }
        // Uncheck + save (form includes dim fields) removes the Link SKU group.
        // Checkbox-only family persist does not, so other sibling groups stay intact.
        if (array_key_exists('save_also_to_siblings', $changedFields) && ! $optIn && $hasDimFields) {
            return $this->unlinkGroupForProduct($product);
        }
        if (! $optIn) {
            return $empty;
        }

        $matches = $this->findMatchingSiblingProducts($product);
        $linkedProducts = $this->linkedProductsFor($product);
        $group = collect([$product])->merge($matches)->merge($linkedProducts)->unique('id')->values();

        $updated = [];
        if ($hasDimFields && $linkedProducts->isNotEmpty()) {
            $sourceValues = $this->valuesArray($product);
            $syncPayload = [];
            foreach (self::SYNC_KEYS as $key) {
                if (array_key_exists($key, $changedFields)) {
                    $syncPayload[$key] = $changedFields[$key];
                } elseif (array_key_exists($key, $sourceValues) && in_array($key, self::MATCH_KEYS, true)) {
                    $syncPayload[$key] = $sourceValues[$key];
                }
            }
            if ($syncPayload !== []) {
                foreach ($linkedProducts as $sib) {
                    if ($this->mergeValuesAndSave($sib, $syncPayload)) {
                        $updated[] = (string) $sib->sku;
                    }
                }
            }
        }

        if ($group->count() >= 2) {
            $this->saveGroup($group, $user);
        }

        $selfNorm = $this->normalizeSku((string) $product->sku);
        $linked = $group
            ->filter(fn (ProductMaster $p) => $this->normalizeSku((string) $p->sku) !== $selfNorm)
            ->map(fn (ProductMaster $p) => (string) $p->sku)
            ->values()
            ->all();

        return [
            'linked_skus' => $linked,
            'updated_skus' => $updated,
            'delinked' => false,
        ];
    }

    /**
     * Remove this SKU's Link SKU group so badges disappear on uncheck.
     *
     * @return array{linked_skus: list<string>, updated_skus: list<string>, delinked: bool, former_skus: list<string>}
     */
    public function unlinkGroupForProduct(ProductMaster $product): array
    {
        $empty = ['linked_skus' => [], 'updated_skus' => [], 'delinked' => true, 'former_skus' => []];
        if (! $this->tableReady() || $this->isParentSku($product->sku)) {
            return $empty;
        }

        $norm = $this->normalizeSku((string) $product->sku);
        $self = DimWtSkuLink::query()->where('sku_norm', $norm)->first();
        if (! $self && $norm !== '') {
            $compact = str_replace(' ', '', $norm);
            $self = DimWtSkuLink::query()
                ->get(['id', 'sku_norm', 'group_key', 'sku'])
                ->first(fn ($row) => str_replace(' ', '', (string) $row->sku_norm) === $compact);
        }
        if (! $self) {
            return $empty;
        }

        $former = DimWtSkuLink::query()
            ->where('group_key', $self->group_key)
            ->pluck('sku')
            ->map(fn ($s) => trim((string) $s))
            ->filter()
            ->values()
            ->all();

        DimWtSkuLink::query()->where('group_key', $self->group_key)->delete();

        return [
            'linked_skus' => [],
            'updated_skus' => [],
            'delinked' => true,
            'former_skus' => $former,
        ];
    }

    /**
     * Map sku_norm => list of other linked SKUs (display labels).
     *
     * @return array<string, list<string>>
     */
    public function linkedSkusMap(): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        $rows = DimWtSkuLink::query()->get(['sku', 'sku_norm', 'group_key']);
        if ($rows->isEmpty()) {
            return [];
        }

        $byGroup = [];
        foreach ($rows as $row) {
            $byGroup[$row->group_key][] = [
                'sku' => trim((string) $row->sku),
                'sku_norm' => (string) $row->sku_norm,
            ];
        }

        $map = [];
        foreach ($byGroup as $members) {
            if (count($members) < 2) {
                continue;
            }
            foreach ($members as $member) {
                $others = [];
                foreach ($members as $other) {
                    if ($other['sku_norm'] === $member['sku_norm']) {
                        continue;
                    }
                    $others[] = $other['sku'];
                }
                $map[$member['sku_norm']] = $others;
            }
        }

        return $map;
    }

    /**
     * Fill dim_wt_linked_skus from saved dim_wt_sku_links only (no live guess).
     *
     * @param  list<array<string, mixed>>  $result
     * @return list<array<string, mixed>>
     */
    public function attachLinkedSkusToRows(array $result): array
    {
        $linkMap = $this->linkedSkusMap();
        $compactMap = [];
        foreach ($linkMap as $key => $skus) {
            $compactMap[str_replace(' ', '', (string) $key)] = $skus;
        }

        foreach ($result as &$row) {
            $sku = trim((string) ($row['SKU'] ?? $row['sku'] ?? ''));
            $norm = $this->normalizeSku($sku);
            $linked = [];

            if ($norm !== '' && isset($linkMap[$norm])) {
                $linked = array_values($linkMap[$norm]);
            } elseif ($norm !== '') {
                $compact = str_replace(' ', '', $norm);
                if (isset($compactMap[$compact])) {
                    $linked = array_values($compactMap[$compact]);
                }
            }

            $row['dim_wt_linked_skus'] = array_values(array_unique(array_filter($linked)));
        }
        unset($row);

        return $result;
    }

    /**
     * @return Collection<int, ProductMaster>
     */
    public function linkedProductsFor(ProductMaster $product): Collection
    {
        if (! $this->tableReady()) {
            return collect();
        }

        $norm = $this->normalizeSku((string) $product->sku);
        $self = DimWtSkuLink::query()->where('sku_norm', $norm)->first();
        if (! $self) {
            return collect();
        }

        $otherRows = DimWtSkuLink::query()
            ->where('group_key', $self->group_key)
            ->where('sku_norm', '!=', $norm)
            ->get(['sku', 'sku_norm']);

        if ($otherRows->isEmpty()) {
            return collect();
        }

        $skus = $otherRows->pluck('sku')->map(fn ($s) => trim((string) $s))->filter()->values()->all();
        $norms = $otherRows->pluck('sku_norm')->all();

        return ProductMaster::query()
            ->whereIn('sku', $skus)
            ->get()
            ->filter(fn (ProductMaster $p) => in_array($this->normalizeSku((string) $p->sku), $norms, true))
            ->values();
    }

    /**
     * Persist a fully-connected match group. Replaces prior link rows for these SKUs.
     *
     * @param  Collection<int, ProductMaster>  $products
     */
    public function saveGroup(Collection $products, ?string $user = null): ?string
    {
        if (! $this->tableReady()) {
            return null;
        }

        $products = $products
            ->filter(fn ($p) => $p instanceof ProductMaster && ! $this->isParentSku($p->sku))
            ->values();

        if ($products->count() < 2) {
            // Solo / no match — drop any previous links for the source SKU(s)
            foreach ($products as $p) {
                DimWtSkuLink::query()
                    ->where('sku_norm', $this->normalizeSku((string) $p->sku))
                    ->delete();
            }

            return null;
        }

        $firstValues = $this->valuesArray($products->first());
        $fingerprint = $this->fingerprintFromValues($firstValues);
        if ($fingerprint === null) {
            return null;
        }

        $parent = trim((string) ($products->first()->parent ?? ''));
        $groupKey = md5(strtoupper($parent).'|'.$fingerprint);
        $snap = $this->matchSnapshot($firstValues);
        $norms = $products->map(fn (ProductMaster $p) => $this->normalizeSku((string) $p->sku))->all();

        try {
            DB::transaction(function () use ($products, $norms, $groupKey, $fingerprint, $parent, $snap, $user) {
                DimWtSkuLink::query()->whereIn('sku_norm', $norms)->delete();

                foreach ($products as $product) {
                    DimWtSkuLink::query()->updateOrCreate(
                        ['sku_norm' => $this->normalizeSku((string) $product->sku)],
                        [
                            'parent' => $parent !== '' ? $parent : null,
                            'group_key' => $groupKey,
                            'sku' => (string) $product->sku,
                            'fingerprint' => $fingerprint,
                            'wt_act' => $snap['wt_act'],
                            'l' => $snap['l'],
                            'w' => $snap['w'],
                            'h' => $snap['h'],
                            'updated_by' => $user,
                        ]
                    );
                }
            });
        } catch (\Throwable $e) {
            Log::warning('DimWtSkuLinkService::saveGroup failed: '.$e->getMessage());

            return null;
        }

        return $groupKey;
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function mergeValuesAndSave(ProductMaster $product, array $patch): bool
    {
        $values = $this->valuesArray($product);
        $changed = false;
        foreach ($patch as $key => $value) {
            $old = $values[$key] ?? null;
            if ($this->valuesDiffer($old, $value)) {
                $values[$key] = $value;
                $changed = true;
            }
        }
        if (! $changed) {
            return false;
        }
        $product->Values = $values;
        $product->save();

        return true;
    }

    private function setVerifiedOnProduct(ProductMaster $product, int $verified): bool
    {
        $values = $this->valuesArray($product);
        $current = $values['verified_data'] ?? null;
        $currentInt = ($current === true || $current === 1 || $current === '1') ? 1 : 0;
        if ($currentInt === $verified) {
            return false;
        }
        $values['verified_data'] = $verified;
        $product->Values = $values;
        $product->save();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function valuesArray(ProductMaster $product): array
    {
        $raw = $product->Values;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function numericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 4);
    }

    private function valuesDiffer(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) > 0.00001;
        }

        return (string) ($a ?? '') !== (string) ($b ?? '');
    }
}
