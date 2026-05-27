<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReverbProduct extends Model
{
    use HasFactory;

    protected $table = 'reverb_products';

    protected $fillable = [
        'sku',
        'reverb_listing_id',
        'listing_state',
        'product_title',
        'description',
        'last_synced_at',
        'last_shopify_qty',
        'r_l30',
        'r_l60',
        'price',
        'views',
        'remaining_inventory',
        'bump_bid',
        'recommended_bid',
        'status',
    ];

    /**
     * Normalize SKU for cross-table joins (Unicode spaces, case — same rules as ShopifySku).
     */
    public static function normalizeSkuForLookup(?string $sku): string
    {
        $normalized = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($normalized === '') {
            return '';
        }

        // 2 Pcs / 2PCS / 2Pc → 2PC (matches common PM vs marketplace spelling drift)
        $normalized = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $normalized);

        return preg_replace('/\s+/', ' ', trim($normalized));
    }

    /**
     * Normalized SKU => reverb_products row (case / spacing tolerant + chunk fallback).
     *
     * @param  array<int, string>  $productSkus
     * @return array<string, self>
     */
    public static function buildLookupByNormalizedSku(array $productSkus): array
    {
        return self::buildModelLookupByNormalizedSku(self::class, $productSkus);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $productSkus
     * @return array<string, Model>
     */
    public static function buildModelLookupByNormalizedSku(string $modelClass, array $productSkus): array
    {
        $productSkus = array_values(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $productSkus
        )));

        if ($productSkus === []) {
            return [];
        }

        $lookup = [];
        foreach ($modelClass::whereIn('sku', $productSkus)->get() as $row) {
            $key = self::normalizeSkuForLookup($row->sku ?? '');
            if ($key !== '' && ! isset($lookup[$key])) {
                $lookup[$key] = $row;
            }
        }

        $missing = [];
        foreach ($productSkus as $pmSku) {
            $key = self::normalizeSkuForLookup($pmSku);
            if ($key !== '' && ! isset($lookup[$key])) {
                $missing[$key] = true;
            }
        }

        if ($missing === []) {
            return $lookup;
        }

        // No-space fallback map: A-542PC ↔ A-54 2PC when spacing differs beyond normalizeSkuForLookup
        $noSpaceToKey = [];
        foreach (array_keys($missing) as $nk) {
            $ns = str_replace(' ', '', $nk);
            if ($ns !== '') {
                $noSpaceToKey[$ns] = $nk;
            }
        }

        $modelClass::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$lookup, &$missing, &$noSpaceToKey) {
                foreach ($rows as $row) {
                    $key = self::normalizeSkuForLookup($row->sku ?? '');
                    if ($key !== '' && isset($missing[$key]) && ! isset($lookup[$key])) {
                        $lookup[$key] = $row;
                        unset($missing[$key]);
                        continue;
                    }

                    $ns = str_replace(' ', '', $key);
                    if ($ns !== '' && isset($noSpaceToKey[$ns])) {
                        $targetKey = $noSpaceToKey[$ns];
                        if (isset($missing[$targetKey]) && ! isset($lookup[$targetKey])) {
                            $lookup[$targetKey] = $row;
                            unset($missing[$targetKey]);
                        }
                    }
                }

                return count($missing) > 0;
            });

        return $lookup;
    }
}
