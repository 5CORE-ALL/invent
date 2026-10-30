<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Log;

/**
 * Shopify Open Box Analytics — its own page and its own calculations.
 *
 * Rows are the Shopify (shopify_skus) SKUs whose SKU contains "OPEN BOX". Nothing here reads
 * eBay data and nothing here feeds the eBay 2 Open Box page.
 *
 * Cost (LP / Ship) comes from the BASE product (the SKU with "OPEN BOX" removed), because an
 * open box unit has no product-master row of its own.
 */
class ShopifyOpenBoxAnalyticsController extends Controller
{
    /** Shopify take-home share of the sale price (same constant the Shopify B2C analytics page uses). */
    public const TAKE_HOME = 0.95;

    public function view()
    {
        return view('market-places.shopify_openbox_analytics', [
            'takeHome' => self::TAKE_HOME,
        ]);
    }

    public function data()
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        return response()->json([
            'data' => self::buildRows(),
            'take_home' => self::TAKE_HOME,
        ])->withHeaders([
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public static function isOpenBoxSku(?string $sku): bool
    {
        return str_contains(ShopifySku::normalizeSkuForShopifyLookup($sku), 'OPEN BOX');
    }

    /**
     * "36L BLACK OPEN BOX" / "36L-BLACK-OPEN-BOX" => "36L BLACK" (normalized, upper-case).
     */
    public static function baseNormFromOpenBoxSku(?string $sku): string
    {
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);

        return trim((string) preg_replace('/\s+/', ' ', str_replace('OPEN BOX', ' ', $norm)));
    }

    /**
     * Unit economics for one open box SKU. Pure — no DB access.
     *
     * GPFT %  = (price × take-home − LP − ship) ÷ price
     * ROI %   = (price × take-home − LP − ship) ÷ LP
     * Profit  = unit profit × units sold (L30)
     *
     * @return array{unit_profit: float, gpft: float, roi: float, profit_l30: float, sales_l30: float, dil: float, cvr: float, stock_cost: float, stock_value: float, discount_vs_base: ?float}
     */
    public static function calc(
        float $price,
        float $lp,
        float $ship,
        float $inv,
        float $l30,
        float $views,
        ?float $basePrice = null,
        float $takeHome = self::TAKE_HOME
    ): array {
        $unitProfit = $price > 0 ? ($price * $takeHome) - $lp - $ship : 0.0;

        return [
            'unit_profit' => round($unitProfit, 2),
            'gpft' => $price > 0 ? round(($unitProfit / $price) * 100, 2) : 0.0,
            'roi' => ($price > 0 && $lp > 0) ? round(($unitProfit / $lp) * 100, 2) : 0.0,
            'profit_l30' => round($unitProfit * $l30, 2),
            'sales_l30' => round($price * $l30, 2),
            // open box units sold in L30 vs units on hand
            'dil' => $inv > 0 ? round(($l30 / $inv) * 100, 2) : 0.0,
            'cvr' => $views > 0 ? round(($l30 / $views) * 100, 2) : 0.0,
            'stock_cost' => round($lp * $inv, 2),
            'stock_value' => round($price * $inv, 2),
            'discount_vs_base' => ($basePrice !== null && $basePrice > 0 && $price > 0)
                ? round((1 - ($price / $basePrice)) * 100, 2)
                : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function buildRows(): array
    {
        // One pass over shopify_skus: index every SKU (for the base-product lookup) and collect open box SKUs.
        $byNorm = [];
        $openBox = [];
        ShopifySku::query()
            ->select(['id', 'sku', 'variant_id', 'inv', 'quantity', 'price', 'views', 'image_src', 'product_link'])
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(2500, function ($rows) use (&$byNorm, &$openBox) {
                foreach ($rows as $row) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup($row->sku);
                    if ($norm === '') {
                        continue;
                    }
                    $hasVariant = trim((string) ($row->variant_id ?? '')) !== '';
                    $existing = $byNorm[$norm] ?? null;
                    if ($existing === null
                        || ($hasVariant && trim((string) ($existing->variant_id ?? '')) === '')) {
                        $byNorm[$norm] = $row;
                    }
                    if (str_contains($norm, 'OPEN BOX')) {
                        $openBox[$norm] = $byNorm[$norm];
                    }
                }
            });

        if ($openBox === []) {
            return [];
        }

        $productMasters = self::loadProductMastersByNorm();

        $rows = [];
        foreach ($openBox as $norm => $shopify) {
            $baseNorm = self::baseNormFromOpenBoxSku($shopify->sku);
            $pm = $baseNorm !== '' ? ($productMasters[$baseNorm] ?? null) : null;
            $baseShopify = $baseNorm !== '' ? ($byNorm[$baseNorm] ?? null) : null;

            $lp = $pm ? (float) $pm['lp'] : 0.0;
            $ship = $pm ? (float) $pm['ship'] : 0.0;
            $price = (float) ($shopify->price ?? 0);
            $inv = (float) ($shopify->inv ?? 0);
            $l30 = (float) max((int) ($shopify->quantity ?? 0), ShopifySku::ovL30SoldForSku($shopify->sku));
            $views = (float) ($shopify->views ?? 0);
            $basePrice = $baseShopify ? (float) ($baseShopify->price ?? 0) : null;

            $calc = self::calc($price, $lp, $ship, $inv, $l30, $views, $basePrice);

            $rows[] = [
                'sku' => trim((string) $shopify->sku),
                'base_sku' => $pm['sku'] ?? '',
                'parent' => $pm['parent'] ?? '',
                'image_path' => $shopify->image_src ?: ($pm['image'] ?? null),
                'product_link' => $shopify->product_link,
                'inv' => $inv,
                'base_inv' => $baseShopify ? (float) ($baseShopify->inv ?? 0) : 0.0,
                'l30' => $l30,
                'views' => $views,
                'price' => $price,
                'base_price' => $basePrice,
                'lp' => $lp,
                'ship' => $ship,
                'base_missing' => $pm === null,
            ] + $calc;
        }

        usort($rows, static fn ($a, $b) => [$b['inv'] > 0 ? 1 : 0, $a['sku']] <=> [$a['inv'] > 0 ? 1 : 0, $b['sku']]);

        return $rows;
    }

    /**
     * @return array<string, array{sku: string, parent: ?string, lp: float, ship: float, image: ?string}>
     */
    private static function loadProductMastersByNorm(): array
    {
        try {
            $masters = ProductMaster::query()
                ->select(['id', 'sku', 'parent'])
                ->selectRaw("CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`Values`, '$.lp')), JSON_UNQUOTE(JSON_EXTRACT(`Values`, '$.LP'))) AS DECIMAL(12,4)) as lp_val")
                ->selectRaw("CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`Values`, '$.ship')), JSON_UNQUOTE(JSON_EXTRACT(`Values`, '$.Ship'))) AS DECIMAL(12,4)) as ship_val")
                ->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`Values`, '$.image_path')), JSON_UNQUOTE(JSON_EXTRACT(`Values`, '$.image_src'))) as image_path_val")
                ->where('sku', 'not like', '%PARENT%')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Shopify Open Box: lean product_master extract failed, loading Values: '.$e->getMessage());
            $masters = ProductMaster::query()
                ->select(['id', 'sku', 'parent', 'Values'])
                ->where('sku', 'not like', '%PARENT%')
                ->get();
        }

        $out = [];
        foreach ($masters as $pm) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup($pm->sku);
            if ($norm === '' || isset($out[$norm])) {
                continue;
            }
            $values = self::decodeValues($pm->Values ?? null);
            $lp = $pm->lp_val ?? ($values['lp'] ?? ($values['LP'] ?? 0));
            $ship = $pm->ship_val ?? ($values['ship'] ?? ($values['Ship'] ?? 0));
            $image = $pm->image_path_val ?? ($values['image_path'] ?? ($values['image_src'] ?? null));

            $out[$norm] = [
                'sku' => (string) $pm->sku,
                'parent' => $pm->parent,
                'lp' => is_numeric($lp) ? (float) $lp : 0.0,
                'ship' => is_numeric($ship) ? (float) $ship : 0.0,
                'image' => (is_string($image) && $image !== '' && strcasecmp($image, 'null') !== 0) ? $image : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeValues(mixed $values): array
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
