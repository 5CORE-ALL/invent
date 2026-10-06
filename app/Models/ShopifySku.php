<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ShopifySku extends Model
{
    use HasFactory;

    /** @var array<string, int>|null */
    private static ?array $ovL30SoldCache = null;

    private static bool $ovL30SoldResolved = false;

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
                $shopifyByNorm[$k] = self::preferShopifyRow($shopifyByNorm[$k] ?? null, $row);
            }
            $c = self::compactSkuForLookup($row->sku);
            if ($c !== '') {
                $ck = 'c:'.$c;
                $shopifyByNorm[$ck] = self::preferShopifyRow($shopifyByNorm[$ck] ?? null, $row);
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

        $mapped = collect($out);
        self::overlayOvL30FromOrders($mapped);

        return $mapped;
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

    /**
     * Same 30-day window the Shopify inventory sync uses for shopify_skus.quantity.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    public static function ovL30Window(): array
    {
        $end = \Illuminate\Support\Carbon::now()->endOfDay();
        $start = \Illuminate\Support\Carbon::now()->subDays(30)->startOfDay();

        return [$start, $end];
    }

    /**
     * Units sold in a date window from shopify_raw_orders (Shopify orders, including
     * Amazon and other channels that were pushed into Shopify).
     * Keyed by compactSkuForLookup so "DM E9 PRPL" and "DME9PRPL" are one product.
     * Null when that table is missing.
     *
     * shopify_skus.quantity is a cache. A line whose SKU only matches after spaces
     * or hyphens are removed used to miss that cache and leave OV L30 at 0.
     *
     * @return array<string, int>|null
     */
    public static function soldUnitsByNormalizedSku(\DateTimeInterface $start, \DateTimeInterface $end): ?array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('shopify_raw_orders')) {
            return null;
        }

        $rows = \Illuminate\Support\Facades\DB::table('shopify_raw_orders')
            ->whereBetween('order_date', [
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
            ])
            ->where(function ($query) {
                $query->whereNull('financial_status')
                    ->orWhereNotIn('financial_status', ['refunded', 'voided']);
            })
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->selectRaw('sku, SUM(COALESCE(quantity, 0)) as qty')
            ->get();

        return self::indexSoldQuantitiesByCompact($rows);
    }

    /**
     * @param  iterable<int, object|array<string, mixed>>  $rows  sku + qty (or quantity)
     * @return array<string, int>
     */
    public static function indexSoldQuantitiesByCompact(iterable $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $sku = is_object($row) ? (string) ($row->sku ?? '') : (string) ($row['sku'] ?? '');
            $qty = is_object($row)
                ? (int) ($row->qty ?? $row->quantity ?? 0)
                : (int) ($row['qty'] ?? $row['quantity'] ?? 0);
            $key = self::compactSkuForLookup($sku);
            if ($key === '' || $qty === 0) {
                continue;
            }
            $map[$key] = ($map[$key] ?? 0) + $qty;
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $soldByCompact
     */
    public static function soldUnitsForSku(?string $sku, array $soldByCompact): int
    {
        $key = self::compactSkuForLookup($sku);

        return $key === '' ? 0 : (int) ($soldByCompact[$key] ?? 0);
    }

    /**
     * shopify_skus ids that share a compact SKU, so a sold count written for
     * "DM E9 PRPL" also lands on "DME9PRPL" instead of leaving that row at 0.
     *
     * @return array<string, list<int>>
     */
    public static function idsByCompactSku(): array
    {
        $map = [];
        foreach (self::query()->select(['id', 'sku'])->get() as $row) {
            $key = self::compactSkuForLookup((string) $row->sku);
            if ($key === '') {
                continue;
            }
            $map[$key][] = (int) $row->id;
        }

        return $map;
    }

    /**
     * The variant row wins for INV, but sold units may have been stored on the
     * other spelling. Keep the higher quantity on the row the page will read.
     */
    private static function preferShopifyRow(?self $existing, self $row): self
    {
        if ($existing === null) {
            return $row;
        }

        $winner = (! self::rowHasVariantId($existing) && self::rowHasVariantId($row)) ? $row : $existing;
        $other = $winner === $row ? $existing : $row;
        $sold = max((int) ($winner->quantity ?? 0), (int) ($other->quantity ?? 0));
        if ($sold > (int) ($winner->quantity ?? 0)) {
            $winner->quantity = $sold;
            $winner->syncOriginalAttribute('quantity');
        }

        return $winner;
    }

    /**
     * Sold units for one SKU in the OV L30 window. Compact-matched, so a line
     * stored as "DME9PRPL" counts for "DM E9 PRPL". Zero when the order table
     * is missing. The lookup is cached for this process after the first call.
     */
    public static function ovL30SoldForSku(?string $sku): int
    {
        $sold = self::rememberOvL30Sold();
        if ($sold === null) {
            return 0;
        }

        return self::soldUnitsForSku($sku, $sold);
    }

    /**
     * @return array<string, int>|null
     */
    private static function rememberOvL30Sold(): ?array
    {
        if (self::$ovL30SoldResolved) {
            return self::$ovL30SoldCache;
        }

        self::$ovL30SoldResolved = true;
        try {
            [$start, $end] = self::ovL30Window();
            self::$ovL30SoldCache = self::soldUnitsByNormalizedSku($start, $end);
        } catch (\Throwable $e) {
            self::$ovL30SoldCache = null;
        }

        return self::$ovL30SoldCache;
    }

    /**
     * Raise a cached quantity of 0 when shopify_raw_orders already has the sale.
     * Does not lower a cached count: a short order sync must not wipe OV L30.
     *
     * @param  \Illuminate\Support\Collection<string, self>  $rows  keyed by product SKU
     */
    public static function overlayOvL30FromOrders(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $sold = self::rememberOvL30Sold();
        if ($sold === null || $sold === []) {
            return;
        }

        foreach ($rows as $productSku => $row) {
            if (! $row instanceof self) {
                continue;
            }
            $fromOrders = max(
                self::soldUnitsForSku((string) $productSku, $sold),
                self::soldUnitsForSku((string) $row->sku, $sold)
            );
            if ($fromOrders > (int) ($row->quantity ?? 0)) {
                $row->quantity = $fromOrders;
                $row->syncOriginalAttribute('quantity');
            }
        }
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
