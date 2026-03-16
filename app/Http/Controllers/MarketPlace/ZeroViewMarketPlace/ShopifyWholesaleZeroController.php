<?php

namespace App\Http\Controllers\MarketPlace\ZeroViewMarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\ShopifyWholesaleDataView;
use Illuminate\Http\Request;

class ShopifyWholesaleZeroController extends Controller
{
    public function shopifyWholesaleZeroview(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
        return view('market-places.zero-market-places.shopifyWholesaleZeroView', [
            'mode' => $mode,
            'demo' => $demo
        ]);
    }

    public function getViewShopifyWholesaleZeroData(Request $request)
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');
        $wholesaleDataViews = ShopifyWholesaleDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        $result = [];
        foreach ($productMasters as $pm) {
            $sku = $pm->sku;
            $parent = $pm->parent;
            $shopify = $shopifyData[$sku] ?? null;

            $inv = $shopify ? $shopify->inv : 0;
            $ov_l30 = $shopify ? $shopify->quantity : 0;
            $ov_dil = ($inv > 0) ? round($ov_l30 / $inv, 4) : 0;

            if ($inv > 0) {
                $wholesaleView = $wholesaleDataViews[$sku] ?? null;
                $value = $wholesaleView ? $wholesaleView->value : [];
                if (is_string($value)) {
                    $value = json_decode($value, true) ?: [];
                }

                $row = [
                    'parent' => $parent,
                    'sku' => $sku,
                    'inv' => $inv,
                    'ov_l30' => $ov_l30,
                    'ov_dil' => $ov_dil,
                    'NR' => isset($value['NR']) && in_array($value['NR'], ['REQ', 'NR']) ? $value['NR'] : 'REQ',
                    'A_Z_Reason' => $value['A_Z_Reason'] ?? '',
                    'A_Z_ActionRequired' => $value['A_Z_ActionRequired'] ?? '',
                    'A_Z_ActionTaken' => $value['A_Z_ActionTaken'] ?? '',
                ];
                $result[] = $row;
            }
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function updateReasonAction(Request $request)
    {
        $sku = $request->input('sku');
        $reason = $request->input('reason');
        $actionRequired = $request->input('action_required');
        $actionTaken = $request->input('action_taken');

        if (!$sku) {
            return response()->json([
                'status' => 400,
                'message' => 'SKU is required.'
            ], 400);
        }

        $row = ShopifyWholesaleDataView::firstOrCreate(
            ['sku' => $sku],
            ['value' => json_encode([])]
        );

        $value = $row->value;
        if (is_string($value)) {
            $value = json_decode($value, true) ?: [];
        }

        $value['A_Z_Reason'] = $reason;
        $value['A_Z_ActionRequired'] = $actionRequired;
        $value['A_Z_ActionTaken'] = $actionTaken;
        $row->value = $value;
        $row->save();

        return response()->json([
            'status' => 200,
            'message' => 'Reason and actions updated successfully.'
        ]);
    }

    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input("sku");
        if (!$sku) {
            return response()->json(["error" => "SKU is required."], 400);
        }

        $reverbDataView = ShopifyWholesaleDataView::firstOrNew(["sku" => $sku]);
        $values = is_array($reverbDataView->values)
            ? $reverbDataView->values
            : (json_decode($reverbDataView->values, true) ?:
            []);

        // Update values safely
        if ($request->has("nr")) {
            $values["NR"] = $request->input("nr");
        }
        if ($request->filled("sprice")) {
            $values["SPRICE"] = $request->input("sprice");
        }
        if ($request->filled("sprofit_percent")) {
            $values["SPFT"] = $request->input("sprofit_percent");
        }
        if ($request->filled("sroi_percent")) {
            $values["SROI"] = $request->input("sroi_percent");
        }

        $reverbDataView->value = $values;
        $reverbDataView->save();

        return response()->json(["success" => true, "data" => $reverbDataView]);
    }
}
