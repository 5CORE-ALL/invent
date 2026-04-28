<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TiendamiaDataView;
use App\Models\TiendamiaProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TiendamiaPricingController extends Controller
{
    private static function normalizeTiendamiaSku(string $value): string
    {
        return strtoupper(str_replace("\u{00a0}", ' ', trim($value)));
    }

    public function tabulatorView()
    {
        return view('market-places.tiendamia_tabulator_view');
    }

    /**
     * Tabulator JSON: tiendamia_products joined to product_master + shopify_skus (image, INV, OV L30).
     * SKU normalization matches AliExpress pricing (NBSP → space, trim, uppercase).
     */
    public function getTabulatorData()
    {
        try {
            $normalizeSku = static fn ($value) => self::normalizeTiendamiaSku((string) $value);

            $productMastersBySku = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
                ->get()
                ->keyBy(fn ($row) => $normalizeSku($row->sku));

            $tiendamiaProducts = TiendamiaProduct::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('sku')
                ->get();

            $rawSkus = $tiendamiaProducts
                ->pluck('sku')
                ->map(fn ($s) => trim((string) $s))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $shopifyByNorm = $rawSkus === []
                ? []
                : ShopifySku::buildShopifySkuLookupByNormalizedSku($rawSkus);

            $marketplaceData = MarketplacePercentage::query()
                ->where('marketplace', 'Tiendamia')
                ->first();
            $percentage = $marketplaceData ? ((float) ($marketplaceData->percentage ?? 100)) : 100;
            $margin = $percentage / 100;

            $dataViewsByNorm = [];
            if ($rawSkus !== []) {
                foreach (TiendamiaDataView::query()->whereIn('sku', $rawSkus)->get() as $dv) {
                    $dataViewsByNorm[$normalizeSku($dv->sku)] = $dv;
                }
            }

            $rows = [];
            foreach ($tiendamiaProducts as $tp) {
                $sku = trim((string) ($tp->sku ?? ''));
                if ($sku === '') {
                    continue;
                }

                $normalizedSku = $normalizeSku($sku);
                $productMaster = $productMastersBySku->get($normalizedSku);
                $shopifyRow = $shopifyByNorm[$normalizedSku] ?? null;

                $inv = $shopifyRow ? (int) ($shopifyRow->inv ?? 0) : 0;
                $ovL30 = $shopifyRow ? (int) ($shopifyRow->quantity ?? 0) : 0;
                $imageSrc = $shopifyRow ? ($shopifyRow->image_src ?? null) : null;

                if (! $imageSrc && $productMaster) {
                    $values = is_array($productMaster->Values)
                        ? $productMaster->Values
                        : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                    $imageSrc = $values['image_path'] ?? $productMaster->image_path ?? null;
                }

                $parent = $productMaster ? trim((string) ($productMaster->parent ?? '')) : '';
                $tmStock = (int) ($tp->stock ?? 0);
                $price = (float) ($tp->price ?? 0);
                $isMissing = ! $productMaster || $price <= 0;

                if ($isMissing) {
                    $mapValue = '';
                } else {
                    $diff = abs($inv - $tmStock);
                    $mapValue = $diff <= 3 ? 'Map' : 'N Map|' . $diff;
                }

                $dilPercent = $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0;

                $lp = 0;
                $ship = 0;
                if ($productMaster) {
                    $values = is_array($productMaster->Values)
                        ? $productMaster->Values
                        : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                    $lp = isset($values['lp']) ? (float) $values['lp'] : (isset($productMaster->lp) ? (float) $productMaster->lp : 0);
                    $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($productMaster->ship) ? (float) $productMaster->ship : 0);
                }

                $mL30 = (int) ($tp->m_l30 ?? 0);
                $profitUnit = ($price * $margin) - $lp - $ship;
                $gpft = $price > 0 ? ($profitUnit / $price) * 100 : 0;
                $groi = $lp > 0 ? ($profitUnit / $lp) * 100 : 0;
                $sales = $price * $mL30;

                $viewRow = $dataViewsByNorm[$normalizedSku] ?? null;
                $meta = ($viewRow && is_array($viewRow->value)) ? $viewRow->value : [];
                $sprice = isset($meta['SPRICE']) ? (float) $meta['SPRICE'] : 0;
                $sgpft = $sprice > 0 ? (int) round((($sprice * $margin - $lp - $ship) / $sprice) * 100) : 0;
                $sroi = $lp > 0 ? (int) round((($sprice * $margin - $lp - $ship) / $lp) * 100) : 0;

                $rows[] = [
                    'id' => (int) $tp->id,
                    'sku' => $sku,
                    'parent' => $parent !== '' ? $parent : null,
                    'image' => $imageSrc,
                    'inv' => $inv,
                    'tm_stock' => $tmStock,
                    'ov_l30' => $ovL30,
                    'dil_percent' => $dilPercent,
                    'm_l30' => $mL30,
                    'm_l60' => (int) ($tp->m_l60 ?? 0),
                    'al30' => $mL30,
                    'price' => round($price, 2),
                    'lp' => round($lp, 2),
                    'ship' => round($ship, 2),
                    'profit' => round($profitUnit, 2),
                    'sales' => round($sales, 2),
                    'sprice' => round($sprice, 2),
                    'sgpft' => $sgpft,
                    'sroi' => $sroi,
                    'gpft' => (int) round($gpft),
                    'groi' => (int) round($groi),
                    'map' => $mapValue,
                    'lmp' => null,
                    'missing' => $isMissing ? 'M' : '',
                    'in_product_master' => (bool) $productMaster,
                    'is_parent' => false,
                    '_margin' => round($margin, 4),
                ];
            }

            usort($rows, static fn ($a, $b) => strnatcasecmp($a['sku'], $b['sku']));

            return response()->json($rows, 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            Log::error('Tiendamia tabulator data failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch Tiendamia products: ' . $e->getMessage(),
            ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }

    /**
     * Save SPRICE + SGPFT + SROI to tiendamia_data_views.value (same keys as AliExpress pricing).
     */
    public function saveSpriceUpdates(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            if ($updates === [] && $request->has('sku')) {
                $updates = [['sku' => $request->input('sku'), 'sprice' => $request->input('sprice')]];
            }

            $marketplaceData = MarketplacePercentage::query()
                ->where('marketplace', 'Tiendamia')
                ->first();
            $percentage = $marketplaceData ? ((float) ($marketplaceData->percentage ?? 100)) : 100;
            $margin = $percentage / 100;

            $productMastersBySku = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get()
                ->keyBy(fn ($row) => self::normalizeTiendamiaSku((string) $row->sku));

            $updatedCount = 0;
            foreach ($updates as $update) {
                $sku = isset($update['sku']) ? trim((string) $update['sku']) : '';
                $sprice = $update['sprice'] ?? null;
                if ($sku === '' || $sprice === null) {
                    continue;
                }

                $sprice = (float) $sprice;
                $productMaster = $productMastersBySku->get(self::normalizeTiendamiaSku($sku));

                $lp = 0;
                $ship = 0;
                if ($productMaster) {
                    $values = is_array($productMaster->Values)
                        ? $productMaster->Values
                        : (is_string($productMaster->Values) ? json_decode($productMaster->Values, true) : []);
                    $lp = isset($values['lp']) ? (float) $values['lp'] : (isset($productMaster->lp) ? (float) $productMaster->lp : 0);
                    $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($productMaster->ship) ? (float) $productMaster->ship : 0);
                }

                $sgpft = $sprice > 0 ? (int) round((($sprice * $margin - $lp - $ship) / $sprice) * 100) : 0;
                $sroi = $lp > 0 ? (int) round((($sprice * $margin - $lp - $ship) / $lp) * 100) : 0;

                $view = TiendamiaDataView::firstOrNew(['sku' => $sku]);
                $stored = is_array($view->value) ? $view->value : (json_decode($view->value, true) ?: []);

                $stored['SPRICE'] = $sprice;
                $stored['SGPFT'] = $sgpft;
                $stored['SPFT'] = $sgpft;
                $stored['SROI'] = $sroi;

                $view->value = $stored;
                $view->save();
                $updatedCount++;
            }

            return response()->json(['success' => true, 'updated' => $updatedCount]);
        } catch (\Throwable $e) {
            Log::error('Tiendamia SPRICE save failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
