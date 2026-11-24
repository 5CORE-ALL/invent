<?php

namespace App\Support\Marketplace;

use App\Models\Ebay2VariationTypePush;
use App\Models\Ebay3VariationTypePush;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps Variation Master (product_master.Values variation_type / variation_name) and the eBay 2 Variation Type page
 * (ebay2_variation_type_pushes) in step:
 *  - a type or value name changed on eBay from an eBay 2 / eBay 3 Variation Type page is written to Variation Master;
 *  - a type changed in Variation Master is put in the New Type cell of that parent's eBay 2 listing.
 * Variation Master names need nothing stored on the page side: the page reads them live.
 */
class VariationTypeSync
{
    public static function parentKey(string $parent): string
    {
        $parent = preg_replace('/^PARENT\s+/i', '', trim($parent)) ?? $parent;

        return ListingManagerFamily::normalizeParentKey($parent);
    }

    /** @return list<ProductMaster> the child SKUs of a parent and its PARENT row */
    private static function family(string $parentKey): array
    {
        $parentKey = self::parentKey($parentKey);
        if ($parentKey === '') {
            return [];
        }
        $up = strtoupper($parentKey);
        $rows = ProductMaster::query()
            ->where(function ($q) use ($up) {
                $q->whereRaw('UPPER(TRIM(parent)) = ?', [$up])
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', ['PARENT '.$up]);
            })
            ->get();

        $out = [];
        foreach ($rows as $row) {
            if (self::parentKey((string) $row->parent) === $parentKey || strtoupper(trim((string) $row->sku)) === 'PARENT '.$up) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /** eBay -> Variation Master: every SKU of the parent (and its PARENT row) gets the new variation type. */
    public static function typeToMaster(string $parentKey, string $type): int
    {
        $type = trim((string) preg_replace('/\s+/', ' ', $type));
        if ($type === '') {
            return 0;
        }
        $changed = 0;
        try {
            foreach (self::family($parentKey) as $row) {
                $values = is_array($row->Values) ? $row->Values : [];
                if (trim((string) ($values['variation_type'] ?? '')) === $type) {
                    continue;
                }
                $values['variation_type'] = $type;
                $row->Values = $values;
                $row->save();
                $changed++;
            }
        } catch (\Throwable $e) {
            Log::warning('Variation type sync to Variation Master failed', ['parent' => $parentKey, 'error' => $e->getMessage()]);
        }

        return $changed;
    }

    /**
     * eBay -> Variation Master: value names that were renamed on eBay.
     *
     * @param  array<string, string>  $namesBySku  exact SKU => new value name
     * @param  list<array{old: string, new: string}>  $renames  used for SKUs whose live value is unknown: a SKU whose Variation Name is the old value gets the new one
     */
    public static function namesToMaster(string $parentKey, array $namesBySku, array $renames = []): int
    {
        $byKey = [];
        foreach ($namesBySku as $sku => $name) {
            $byKey[self::skuKey((string) $sku)] = trim((string) $name);
        }
        $changed = 0;
        try {
            foreach (self::family($parentKey) as $row) {
                if (preg_match('/^PARENT/i', trim((string) $row->sku))) {
                    continue;
                }
                $values = is_array($row->Values) ? $row->Values : [];
                $current = trim((string) ($values['variation_name'] ?? ''));
                $new = $byKey[self::skuKey((string) $row->sku)] ?? null;
                if ($new === null && $byKey === []) {
                    foreach ($renames as $r) {
                        if ($current !== '' && mb_strtolower($current) === mb_strtolower(trim((string) ($r['old'] ?? '')))) {
                            $new = trim((string) ($r['new'] ?? ''));
                        }
                    }
                }
                if ($new === null || $new === '' || $new === $current) {
                    continue;
                }
                $values['variation_name'] = $new;
                $row->Values = $values;
                $row->save();
                $changed++;
            }
        } catch (\Throwable $e) {
            Log::warning('Variation name sync to Variation Master failed', ['parent' => $parentKey, 'error' => $e->getMessage()]);
        }

        return $changed;
    }

    /**
     * Variation Master -> eBay 2 / eBay 3 Variation Type pages: the New Type cell of the parent's listing follows the type.
     * Nothing is sent to eBay; the Add button still does that.
     */
    public static function typeToEbayPage(string $parentKey, string $type): int
    {
        $parentKey = self::parentKey($parentKey);
        $type = trim((string) preg_replace('/\s+/', ' ', $type));
        if ($parentKey === '') {
            return 0;
        }
        try {
            $changed = 0;
            // SKUs of the Variation Master family: a listing whose parent name is spelled differently is still found by them.
            $familySkus = [];
            foreach (self::family($parentKey) as $row) {
                if (! preg_match('/^PARENT/i', trim((string) $row->sku))) {
                    $familySkus[self::skuKey((string) $row->sku)] = true;
                }
            }
            // The eBay 2 and eBay 3 Variation Type pages.
            foreach ([Ebay2VariationTypePush::class, Ebay3VariationTypePush::class] as $model) {
                if (! Schema::hasTable((new $model)->getTable())) {
                    continue;
                }
                foreach ($model::query()->whereNotNull('parent_sku')->get() as $rec) {
                    $sameName = self::parentKey((string) $rec->parent_sku) === $parentKey;
                    $sameSkus = false;
                    if (! $sameName && $familySkus !== []) {
                        foreach ((array) ($rec->live_variations ?? []) as $v) {
                            if (isset($familySkus[self::skuKey((string) ($v['sku'] ?? ''))])) {
                                $sameSkus = true;
                                break;
                            }
                        }
                    }
                    if (! $sameName && ! $sameSkus) {
                        continue;
                    }
                    $new = $type === '' ? null : $type;
                    if ((string) $rec->new_type === (string) $new) {
                        continue;
                    }
                    $rec->new_type = $new;
                    $rec->save();
                    $changed++;
                }
            }

            return $changed;
        } catch (\Throwable $e) {
            Log::warning('Variation type sync to the Variation Type pages failed', ['parent' => $parentKey, 'error' => $e->getMessage()]);

            return 0;
        }
    }

    private static function skuKey(string $sku): string
    {
        $sku = trim($sku);

        return ShopifySku::normalizeSkuForShopifyLookup($sku) ?: strtoupper($sku);
    }
}
