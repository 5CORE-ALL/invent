<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NeweggB2bPricingController extends Controller
{
    public function index()
    {
        return view('market-places.newegg_b2b_pricing_tabulator_view');
    }

    public function getData(Request $request)
    {
        try {
            $productMasterRows = ProductMaster::query()
                ->get(['sku', 'parent', 'Values', 'main_image', 'image1']);
            $skus = $productMasterRows->pluck('sku')->filter()->values()->all();
            $shopifyData = ShopifySku::mapByProductSkus($skus);

            $data = [];
            foreach ($productMasterRows as $pm) {
                $sku = $pm->sku;
                if ($sku === null || $sku === '' || stripos((string) $sku, 'PARENT') !== false) {
                    continue;
                }

                $shopify = $shopifyData[$sku] ?? null;
                $inv = (float) ($shopify->inv ?? 0);
                $ovl30 = (float) ($shopify->quantity ?? 0);
                $dil = $inv > 0 ? (int) round(($ovl30 / $inv) * 100, 0) : 0;

                $image = $pm->main_image
                    ?: ($pm->image1 ?? null)
                    ?: ($shopify->image_src ?? null);

                $data[] = [
                    'image' => $image ?: null,
                    'parent' => $this->parentLabel($pm),
                    'sku' => $sku,
                    'dil' => $dil,
                    'ovl30' => (int) $ovl30,
                    'inv' => (int) $inv,
                ];
            }

            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('Newegg B2B pricing getData failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch Newegg B2B data',
                'data' => [],
            ], 500);
        }
    }

    private function parentLabel(ProductMaster $pm): string
    {
        $parent = trim((string) ($pm->parent ?? ''));
        if ($parent !== '') {
            return $parent;
        }

        $values = is_array($pm->Values)
            ? $pm->Values
            : (json_decode((string) ($pm->Values ?? ''), true) ?: []);

        return trim((string) ($values['Parent'] ?? $values['parent'] ?? ''));
    }
}
