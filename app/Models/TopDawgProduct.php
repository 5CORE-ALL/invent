<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopDawgProduct extends Model
{
    use HasFactory;

    protected $table = 'topdawg_products';

    protected $fillable = [
        'sku',
        'topdawg_listing_id',
        'tdid',
        'image_src',
        'listing_state',
        'product_title',
        'r_l30',
        'r_l60',
        'price',
        'msrp',
        'views',
        'remaining_inventory',
    ];

    /**
     * Normalized SKU => row (case / Unicode space tolerant, same rules as ShopifySku).
     *
     * @param  array<int, string>  $productSkus
     * @return array<string, self>
     */
    public static function buildLookupByNormalizedSku(array $productSkus): array
    {
        $productSkus = array_values(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $productSkus
        )));

        if ($productSkus === []) {
            return [];
        }

        $lookup = [];
        foreach (self::whereIn('sku', $productSkus)->get() as $row) {
            $key = ShopifySku::normalizeSkuForShopifyLookup($row->sku);
            if ($key !== '' && ! isset($lookup[$key])) {
                $lookup[$key] = $row;
            }
        }

        $missing = [];
        foreach ($productSkus as $pmSku) {
            $key = ShopifySku::normalizeSkuForShopifyLookup($pmSku);
            if ($key !== '' && ! isset($lookup[$key])) {
                $missing[$key] = true;
            }
        }

        if ($missing === []) {
            return $lookup;
        }

        self::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$lookup, &$missing) {
                foreach ($rows as $row) {
                    $key = ShopifySku::normalizeSkuForShopifyLookup($row->sku);
                    if ($key !== '' && isset($missing[$key]) && ! isset($lookup[$key])) {
                        $lookup[$key] = $row;
                        unset($missing[$key]);
                    }
                }

                return count($missing) > 0;
            });

        return $lookup;
    }
}
