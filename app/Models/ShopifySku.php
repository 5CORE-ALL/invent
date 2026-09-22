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
        'views', // L30 product landing-page sessions (ShopifyQL)
        'views_l1',
        'views_l7',
        'price',
        'b2b_price',
        'b2c_price',
        'price_updated_manually_at',
        'image_src',
        'shopify_l30',
        'available_to_sell',
        'committed',
        'on_hand',
        'unavailable',
        'incoming',
    ];

    protected $dates = [
        'price_updated_manually_at',
    ];

    /**
     * Match product_master.sku to shopify_skus.sku when strings differ only by:
     *  - Unicode whitespace (NBSP, NNBSP, FIGURE SPACE, ZWSP) vs a normal space
     *    (common on variants like "DS CH YLW REST-LVR" where Shopify ships NBSPs), or
     *  - hyphen / en-dash / em-dash / underscore vs a space
     *    (e.g. PM "ND 58" vs Shopify "ND-58" — both refer to the same listing).
     *
     * Hyphen/underscore unification is intentional and audited: across product_master
     * the only collision it produces today is the legitimate "ND 58" ↔ "ND-58" pair,
     * so unifying them is the right behaviour for marketplace price/inventory lookups.
     */
    public static function normalizeSkuForShopifyLookup(?string $sku): string
    {
        if ($sku === null || $sku === '') {
            return '';
        }
        $s = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xE2\x80\x87", "\xE2\x80\x8B"], ' ', $sku);
        $s = preg_replace('/[-\x{2010}-\x{2015}_]+/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', trim($s));

        return strtoupper($s);
    }

    /**
     * Alphanumeric-only SKU key so "LS 180-6" and "LS180-6" match.
     */
    public static function compactSkuForLookup(?string $sku): string
    {
        if ($sku === null || $sku === '') {
            return '';
        }

        return strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', (string) $sku));
    }

    /**
     * True when two SKU strings are the same after NBSP / hyphen / space cleanup.
     */
    public static function skusMatch(?string $a, ?string $b): bool
    {
        $na = self::normalizeSkuForShopifyLookup($a);
        $nb = self::normalizeSkuForShopifyLookup($b);
        if ($na !== '' && $na === $nb) {
            return true;
        }
        $ca = self::compactSkuForLookup($a);
        $cb = self::compactSkuForLookup($b);

        return $ca !== '' && $ca === $cb;
    }

    private static function rowHasVariantId(self $row): bool
    {
        return trim((string) ($row->variant_id ?? '')) !== '';
    }

    /**
     * Columns needed by verification-adjustment and listing INV (must include live qty fields).
     *
     * @var list<string>
     */
    public const LOOKUP_COLUMNS = [
        'id',
        'sku',
        'variant_id',
        'inv',
        'quantity',
        'shopify_l30',
        'price',
        'b2c_price',
        'image_src',
        'available_to_sell',
        'committed',
        'on_hand',
        'unavailable',
        'incoming',
    ];

    /**
     * @param  array<int, string>  $productSkus
     * @return array<string, self> normalized key => row (row with a variant id wins over a blank stub)
     */
    public static function buildShopifySkuLookupByNormalizedSku(array $productSkus, bool $scanMissing = true): array
    {
        $shopifyByNorm = [];
        $indexRow = static function ($row) use (&$shopifyByNorm): void {
            $k = self::normalizeSkuForShopifyLookup($row->sku);
            if ($k !== '') {
                $existing = $shopifyByNorm[$k] ?? null;
                if ($existing === null || (! self::rowHasVariantId($existing) && self::rowHasVariantId($row))) {
                    $shopifyByNorm[$k] = $row;
                }
            }
            $c = self::compactSkuForLookup($row->sku);
            if ($c !== '') {
                $ck = 'c:'.$c;
                $existing = $shopifyByNorm[$ck] ?? null;
                if ($existing === null || (! self::rowHasVariantId($existing) && self::rowHasVariantId($row))) {
                    $shopifyByNorm[$ck] = $row;
                }
            }
        };
        $resolved = static function (string $k, string $c) use (&$shopifyByNorm): bool {
            if ($k !== '' && isset($shopifyByNorm[$k]) && self::rowHasVariantId($shopifyByNorm[$k])) {
                return true;
            }

            return $c !== '' && isset($shopifyByNorm['c:'.$c]) && self::rowHasVariantId($shopifyByNorm['c:'.$c]);
        };

        if ($productSkus !== []) {
            foreach (self::query()->whereIn('sku', $productSkus)->get(self::LOOKUP_COLUMNS) as $row) {
                $indexRow($row);
            }
        }

        $missingFlip = [];
        foreach ($productSkus as $pmSku) {
            $k = self::normalizeSkuForShopifyLookup((string) $pmSku);
            $c = self::compactSkuForLookup((string) $pmSku);
            if (! $resolved($k, $c)) {
                if ($k !== '') {
                    $missingFlip[$k] = true;
                }
                if ($c !== '') {
                    $missingFlip['c:'.$c] = true;
                }
            }
        }

        if ($missingFlip === [] || ! $scanMissing) {
            return $shopifyByNorm;
        }

        self::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(3000, function ($rows) use ($indexRow, &$shopifyByNorm, &$missingFlip) {
                foreach ($rows as $row) {
                    $k = self::normalizeSkuForShopifyLookup($row->sku);
                    $c = self::compactSkuForLookup($row->sku);
                    $hit = ($k !== '' && isset($missingFlip[$k]))
                        || ($c !== '' && isset($missingFlip['c:'.$c]));
                    if (! $hit) {
                        continue;
                    }
                    $indexRow($row);
                    if ($k !== '' && isset($shopifyByNorm[$k]) && self::rowHasVariantId($shopifyByNorm[$k])) {
                        unset($missingFlip[$k]);
                    }
                    if ($c !== '' && isset($shopifyByNorm['c:'.$c]) && self::rowHasVariantId($shopifyByNorm['c:'.$c])) {
                        unset($missingFlip['c:'.$c]);
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
            $row = ($k !== '' && isset($byNorm[$k])) ? $byNorm[$k] : null;
            if ($row === null) {
                $c = self::compactSkuForLookup($pmSku);
                $row = ($c !== '' && isset($byNorm['c:'.$c])) ? $byNorm['c:'.$c] : null;
            }
            if ($row !== null) {
                $out[$pmSku] = $row;
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
        if ($k !== '' && isset($map[$k])) {
            return $map[$k];
        }
        $c = self::compactSkuForLookup((string) $sku);

        return ($c !== '' && isset($map['c:'.$c])) ? $map['c:'.$c] : null;
    }

    /**
     * Main-store catalog variant when shopify_skus has no id for this listing.
     */
    public static function mainCatalogVariantId(?string $sku): ?string
    {
        $wantN = self::normalizeSkuForShopifyLookup($sku);
        $wantC = self::compactSkuForLookup($sku);
        if (($wantN === '' && $wantC === '') || ! \Illuminate\Support\Facades\Schema::hasTable('shopify_catalog_variants')) {
            return null;
        }

        $compactHit = null;
        foreach (\Illuminate\Support\Facades\DB::table('shopify_catalog_variants')
            ->where('store', 'main')
            ->whereNotNull('shopify_variant_id')
            ->where('sku', '!=', '')
            ->get(['sku', 'shopify_variant_id']) as $row) {
            $vid = trim((string) ($row->shopify_variant_id ?? ''));
            if ($vid === '') {
                continue;
            }
            if ($wantN !== '' && self::normalizeSkuForShopifyLookup((string) $row->sku) === $wantN) {
                return $vid;
            }
            if ($compactHit === null && $wantC !== '' && self::compactSkuForLookup((string) $row->sku) === $wantC) {
                $compactHit = $vid;
            }
        }

        return $compactHit;
    }

    public static function variantIdForProductSku(?string $sku): ?string
    {
        $row = self::firstForProductSku($sku);
        $vid = trim((string) ($row->variant_id ?? ''));
        if ($vid !== '') {
            return $vid;
        }

        return self::mainCatalogVariantId($sku);
    }
}
