<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\AmazonDataView;
use App\Models\MovementAnalysis;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $productData = ProductMaster::query()
            ->select('parent', 'sku', 'Values')
            ->orderBy('parent')
            ->orderBy('sku')
            ->get();

        $filteredData = $productData->filter(function ($item) {
            return trim((string) ($item->sku ?? '')) !== '';
        })->values();

        $skus = $filteredData->map(function ($item) {
            return trim((string) $item->sku);
        })->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::mapByProductSkus($skus);

        $movementBySku = [];
        foreach (MovementAnalysis::all() as $row) {
            $skuKey = strtoupper(trim((string) ($row->sku ?? '')));
            if ($skuKey === '') {
                continue;
            }
            $movementBySku[$skuKey] = $row;
            $compact = str_replace(' ', '', $skuKey);
            if ($compact !== '' && ! isset($movementBySku[$compact])) {
                $movementBySku[$compact] = $row;
            }
        }

        $amzPriceBySku = [];
        try {
            foreach (AmazonDataView::whereIn('sku', $skus)->get(['sku', 'value']) as $adv) {
                $val = is_array($adv->value)
                    ? $adv->value
                    : (json_decode((string) ($adv->value ?? ''), true) ?: []);
                $std = $val['STANDARD_PRICE'] ?? $val['standard_price'] ?? $val['AMAZON_PRICE'] ?? $val['amazon_price'] ?? $val['price'] ?? null;
                if (! is_numeric($std) || (float) $std <= 0) {
                    continue;
                }
                $k = strtoupper(trim((string) $adv->sku));
                if ($k === '') {
                    continue;
                }
                $amzPriceBySku[$k] = (float) $std;
                $amzPriceBySku[str_replace(' ', '', $k)] = (float) $std;
            }
        } catch (\Throwable $e) {
            // ignore missing amazon_data_view
        }
        try {
            foreach (DB::table('amazon_datsheets')->whereIn('sku', $skus)->whereNotNull('price')->get(['sku', 'price']) as $ar) {
                $k = strtoupper(trim((string) ($ar->sku ?? '')));
                if ($k === '' || isset($amzPriceBySku[$k])) {
                    continue;
                }
                if (! is_numeric($ar->price) || (float) $ar->price <= 0) {
                    continue;
                }
                $amzPriceBySku[$k] = (float) $ar->price;
                $amzPriceBySku[str_replace(' ', '', $k)] = (float) $ar->price;
            }
        } catch (\Throwable $e) {
            // ignore missing amazon_datsheets
        }

        $processedData = $filteredData->map(function ($item) use ($shopifyData, $movementBySku, $amzPriceBySku) {
            $childSku = trim((string) ($item->sku ?? ''));
            $parent = trim((string) ($item->parent ?? ''));
            $item->sku = $childSku;
            $item->parent = $parent;

            $skuUpper = strtoupper(preg_replace('/\s+/', ' ', $childSku));
            $item->is_parent = str_contains($skuUpper, 'PARENT');

            if ($item->is_parent) {
                $item->INV = null;
                $item->L30 = null;
                $item->months = [];
                $item->total = null;
                $item->total_months = null;
                $item->monthly_average = null;
                $item->msl = null;
                $item->s_msl = null;
                $item->moq = null;
                $item->dil = null;
                $item->amz_price = 0;
                $item->amz_value = null;
                $item->lp = 0;

                return $item;
            }

            $shopify = $shopifyData[$childSku] ?? null;
            $item->INV = $shopify ? (int) round((float) ($shopify->inv ?? 0)) : 0;
            $item->L30 = $shopify ? (int) round((float) ($shopify->quantity ?? 0)) : 0;

            $movementItem = $movementBySku[$skuUpper] ?? $movementBySku[str_replace(' ', '', $skuUpper)] ?? null;
            $months = [];
            foreach ((array) ($movementItem->months ?? []) as $month => $qty) {
                $months[$month] = (int) round((float) $qty);
            }
            $item->months = $months;
            $values = array_values($months);

            $total = (int) round(array_sum($values) + (float) ($item->L30 ?? 0));
            $total_months = count(array_filter($values));
            $monthly = $total_months > 0 ? (int) round($total / $total_months) : 0;

            $item->total = $total;
            $item->total_months = $total_months;
            $item->monthly_average = $monthly;
            $item->msl = $monthly * 4;
            $item->s_msl = isset($movementItem->s_msl) ? (int) round((float) $movementItem->s_msl) : 0;

            $rawValues = $item->Values ?? [];
            $valuesJson = is_array($rawValues)
                ? $rawValues
                : (json_decode((string) $rawValues, true) ?: []);
            $item->lp = (isset($valuesJson['lp']) && is_numeric($valuesJson['lp'])) ? (float) $valuesJson['lp'] : 0;
            $item->moq = (isset($valuesJson['moq']) && is_numeric($valuesJson['moq'])) ? (int) round((float) $valuesJson['moq']) : 0;

            $inv = (float) ($item->INV ?? 0);
            $l30 = (float) ($item->L30 ?? 0);
            $item->dil = ($inv > 0) ? round(($l30 / $inv) * 100, 2) : 0;
            $skuKey = strtoupper($childSku);
            $item->amz_price = $amzPriceBySku[$skuKey] ?? $amzPriceBySku[str_replace(' ', '', $skuKey)] ?? 0;
            $item->amz_value = (int) round($inv * (float) $item->amz_price);

            return $item;
        })->values();

        $this->rememberMovementDilHistory($processedData);

        return response()->json($processedData);
    }

    public function movementDilHistory()
    {
        return response()->json([
            'count' => $this->movementDilHistoryRows('movement_analysis_dil_count_hist'),
            'amz' => $this->movementDilHistoryRows('movement_analysis_dil_amz_hist'),
            'lp' => $this->movementDilHistoryRows('movement_analysis_dil_lp_hist'),
        ]);
    }

    private function rememberMovementDilHistory($rows): void
    {
        $bands = ['0', '0.1-25', '25-50', '50-100', 'gt-100'];
        $count = array_fill_keys($bands, 0);
        $amz = array_fill_keys($bands, 0);
        $lp = array_fill_keys($bands, 0);

        foreach ($rows as $item) {
            $sku = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($item->sku ?? ''))));
            if ($sku === '' || str_contains($sku, 'PARENT') || ! empty($item->is_parent)) {
                continue;
            }
            $inv = (float) ($item->INV ?? 0);
            if ($inv <= 0) {
                continue;
            }
            $band = $this->movementDilBand((float) ($item->dil ?? 0));
            $count[$band]++;
            $amz[$band] += (float) ($item->amz_value ?? 0);
            $lpPrice = (float) ($item->lp ?? 0);
            if ($lpPrice > 0) {
                $lp[$band] += $inv * $lpPrice;
            }
        }

        $today = now('America/Los_Angeles')->toDateString();
        foreach ([
            'movement_analysis_dil_count_hist' => $count,
            'movement_analysis_dil_amz_hist' => $amz,
            'movement_analysis_dil_lp_hist' => $lp,
        ] as $key => $day) {
            $hist = Cache::get($key, []);
            if (! is_array($hist)) {
                $hist = [];
            }
            $hist[$today] = $day;
            ksort($hist);
            $dates = array_keys($hist);
            while (count($dates) > 90) {
                unset($hist[$dates[0]]);
                array_shift($dates);
            }
            Cache::put($key, $hist, now()->addDays(120));
        }
    }

    private function movementDilBand(float $dil): string
    {
        if (round($dil, 2) == 0.0) {
            return '0';
        }
        if ($dil > 100) {
            return 'gt-100';
        }
        if ($dil >= 50) {
            return '50-100';
        }
        if ($dil > 25) {
            return '25-50';
        }

        return '0.1-25';
    }

    private function movementDilHistoryRows(string $cacheKey): array
    {
        $hist = Cache::get($cacheKey, []);
        if (! is_array($hist)) {
            return [];
        }
        $out = [];
        foreach ($hist as $date => $row) {
            if (! is_array($row)) {
                continue;
            }
            $date = (string) $date;
            $out[] = array_merge($row, [
                'date' => $date,
                'label' => strlen($date) >= 10 ? substr($date, 5) : $date,
            ]);
        }

        return $out;
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
