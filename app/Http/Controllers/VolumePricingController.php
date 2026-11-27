<?php

namespace App\Http\Controllers;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\JsonResponse;

class VolumePricingController extends Controller
{
    public function index()
    {
        return view('pricing-master.volume_pricing_tabulator');
    }

    /**
     * Basic rows for the volume pricing table: Parent, SKU, INV, OV L30 (Dil is computed in the browser).
     */
    public function data(): JsonResponse
    {
        $productMasters = ProductMaster::query()
            ->whereRaw("UPPER(TRIM(sku)) NOT LIKE 'PARENT%'")
            ->orderBy('parent', 'asc')
            ->orderBy('sku', 'asc')
            ->get(['sku', 'parent']);

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = $skus === [] ? collect() : ShopifySku::mapByProductSkus($skus);

        $rows = [];
        foreach ($productMasters as $pm) {
            $shopify = $shopifyData->get($pm->sku);
            $inv = (int) ($shopify->inv ?? 0);
            $ovL30 = (int) ($shopify->quantity ?? 0);

            $rows[] = [
                'parent' => $pm->parent,
                'sku' => $pm->sku,
                'inv' => $inv,
                'ov_l30' => $ovL30,
                'dil' => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0,
            ];
        }

        return response()->json(['data' => $rows]);
    }
}
