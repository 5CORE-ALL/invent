<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ShopifySku extends Model
{
    use HasFactory;

    protected $table = 'shopify_skus';

    protected $fillable = [
        'variant_id',
        'sku',
        'product_title',
        'variant_title',
        'product_link',
        'inv', // stock on hand in this app
        'quantity', // sold units (not available inventory)
        'price',
        'b2b_price',
        'b2c_price',
        'price_updated_manually_at',
        'image_src',
        'shopify_l30',
        'available_to_sell',
        'committed',
        'on_hand',
    ];

    protected $dates = [
        'price_updated_manually_at',
    ];

    /**
     * Match product_master.sku to shopify_skus.sku when strings differ only by Unicode spaces
     * (e.g. NBSP in Shopify vs normal space in PM — common on variants like "DS CH YLW REST-LVR").
     */
    public static function normalizeSkuForShopifyLookup(?string $sku): string
    {
        if ($sku === null || $sku === '') {
            return '';
        }
        $s = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xE2\x80\x87", "\xE2\x80\x8B"], ' ', $sku);
        $s = preg_replace('/\s+/u', ' ', trim($s));

        return strtoupper($s);
    }

    /**
     * @param  array<int, string>  $productSkus
     * @return array<string, self> normalized key => row (first wins)
     */
    public static function buildShopifySkuLookupByNormalizedSku(array $productSkus): array
    {
        $shopifyByNorm = [];
        foreach (self::whereIn('sku', $productSkus)->get() as $row) {
            $k = self::normalizeSkuForShopifyLookup($row->sku);
            if ($k !== '' && ! isset($shopifyByNorm[$k])) {
                $shopifyByNorm[$k] = $row;
            }
        }

        $missingFlip = [];
        foreach ($productSkus as $pmSku) {
            $k = self::normalizeSkuForShopifyLookup((string) $pmSku);
            if ($k !== '' && ! isset($shopifyByNorm[$k])) {
                $missingFlip[$k] = true;
            }
        }

        if ($missingFlip === []) {
            return $shopifyByNorm;
        }

        self::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(3000, function ($rows) use (&$shopifyByNorm, &$missingFlip) {
                foreach ($rows as $row) {
                    $k = self::normalizeSkuForShopifyLookup($row->sku);
                    if ($k !== '' && isset($missingFlip[$k]) && ! isset($shopifyByNorm[$k])) {
                        $shopifyByNorm[$k] = $row;
                        unset($missingFlip[$k]);
                    }
                }

                return count($missingFlip) > 0;
            });

        return $shopifyByNorm;
    }

    /**
     * Collection keyed by the exact product SKU string you pass in (for isset / ->get($pm->sku)).
     *
     * @param  array<int, string>  $productSkus
     */
    public static function mapByProductSkus(array $productSkus): Collection
    {
        $byNorm = self::buildShopifySkuLookupByNormalizedSku($productSkus);
        $out = [];
        foreach ($productSkus as $pmSku) {
            if ($pmSku === null || $pmSku === '') {
                continue;
            }
            $pmSku = (string) $pmSku;
            $k = self::normalizeSkuForShopifyLookup($pmSku);
            if ($k !== '' && isset($byNorm[$k])) {
                $out[$pmSku] = $byNorm[$k];
            }
        }

        return collect($out);
    }

    public static function firstForProductSku(?string $sku): ?self
    {
        if ($sku === null || trim((string) $sku) === '') {
            return null;
        }
        $map = self::buildShopifySkuLookupByNormalizedSku([(string) $sku]);
        $k = self::normalizeSkuForShopifyLookup((string) $sku);

        return $k === '' ? null : ($map[$k] ?? null);
    }
}
