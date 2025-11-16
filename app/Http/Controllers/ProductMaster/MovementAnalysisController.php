<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\MovementAnalysis;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class MovementAnalysisController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }


    public function movementAnalysis(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $response = $this->getViewMovementAnalysisData($request);
        $processedData = json_decode($response->getContent(), true);

        $groupedData = collect($processedData)->groupBy('parent')->map(fn($group) => $group->values());

        return view('product-master.movementAnalysis1', [
            'mode' => $mode,
            'demo' => $demo,
            'groupedDataJson' => $groupedData,
        ]);
    }


    public function getViewMovementAnalysisData(Request $request)
    {
        $productData = DB::table('product_master')
            ->select('parent', 'sku', 'Values')
            ->get();

        $filteredData = $productData->filter(function ($item) {
            return !(empty(trim($item->sku ?? '')));
        });

        $skus = $filteredData->filter(function ($item) {
            return !empty($item->sku);
        })->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        $movementData = MovementAnalysis::all()->keyBy(function ($item) {
            return $item->parent . '||' . $item->sku;
        });

        $processedData = $filteredData->map(function ($item) use ($productData, $shopifyData, $movementData) {
            $childSku = trim($item->sku ?? '');
            $parent = trim($productData[$childSku]->parent ?? '');
            $key = $parent . '||' . $childSku;

            if (!empty($childSku) && stripos($childSku, 'PARENT') === false) {
                $item->INV = $shopifyData[$childSku]->inv ?? 0;
                $item->L30 = $shopifyData[$childSku]->quantity ?? 0;
            } else {
                $item->INV = null;
                $item->L30 = null;
            }


            $movementItem = $movementData[$key] ?? null;
            $months = (array) ($movementItem->months ?? []);
            $item->months = $months;
            $values = array_values($months);

            $total = array_sum($values) + ($item->L30 ?? 0);
            $total_months = count(array_filter($values));
            $monthly = $total_months > 0 ? round($total / $total_months, 2) : 0;

            $item->total = $total;
            $item->total_months = $total_months;
            $item->monthly_average = $monthly;
            $item->msl = $monthly * 4;
            $item->s_msl = $movementItem->s_msl ?? '0';

            $item->is_parent = strtoupper(trim($childSku)) === 'PARENT ' . strtoupper(trim($parent));

            $valuesJson = json_decode($item->Values ?? '{}', true);
            $item->lp = $valuesJson['lp'] ?? null;

            return $item;
        })->values();

        return response()->json($processedData);
    }


    public function updateSmsl(Request $request)
    {

        $sku = $request->input('sku');
        $parent = $request->input('parent');
        $column = $request->input('column');
        $value = $request->input('value');

        $allowedColumns = ['s_msl']; 

        if (!in_array($column, $allowedColumns)) {
            return response()->json(['success' => false, 'message' => 'Invalid column']);
        }

        $item = MovementAnalysis::where('sku', $sku)->where('parent', $parent)->first();

        if ($item) {
            $item->{$column} = $value;
            $item->save();

            return response()->json(['success' => true, 'message' => 'Updated successfully']);
        }

        return response()->json(['success' => false, 'message' => 'Item not found']);
    }

    // public function saveMonthlySales()
    // {
    //     $url = 'https://script.google.com/macros/s/AKfycbzyHUVly3lAaGqxz62xE1-PY6jrcIZJacRZ0Pips1CcWu0R3ro8CaewvPgzXREQDFv6DA/exec';

    //     try {
    //         $response = Http::get($url);
    //         $items = $response->json();

    //         foreach ($items as $item) {
    //             $parent = trim($item['Parent'] ?? '');
    //             $sku = trim($item['(Child) sku'] ?? '');
    //             if (empty($parent) || empty($sku)) continue;

    //             // Extract months (assuming: Jan, Feb, Mar, ..., Dec)
    //             $monthlyKeys = ['jan', 'feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    //             $newMonths = [];

    //             foreach ($monthlyKeys as $month) {
    //                 if (isset($item[$month])) {
    //                     $newMonths[$month] = (int) $item[$month];
    //                 }
    //             }

    //             // ✅ Update or Insert into movement_analysis
    //             $existing = DB::table('movement_analysis')
    //                 ->where('parent', $parent)
    //                 ->where('sku', $sku)
    //                 ->first();

    //             if ($existing) {
    //                 $existingMonths = json_decode($existing->months ?? '{}', true);
    //                 $mergedMonths = array_merge($existingMonths, $newMonths); // prefer new data

    //                 DB::table('movement_analysis')
    //                     ->where('parent', $parent)
    //                     ->where('sku', $sku)
    //                     ->update([
    //                         'months' => json_encode($mergedMonths),
    //                     ]);
    //             } else {
    //                 DB::table('movement_analysis')->insert([
    //                     'parent' => $parent,
    //                     'sku' => $sku,
    //                     'months' => json_encode($newMonths),
    //                 ]);
    //             }
    //         }

    //         return response()->json(['message' => 'Movement data saved/updated successfully.']);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }



}
