<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Models\NeweggOrder;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NeweggSalesController extends Controller
{
    public function index()
    {
        return view('sales.newegg_daily_sales_data');
    }

    public function getData(Request $request)
    {
        // Margin comes from marketplace_percentages (Neweggb2c): margin = percentage - ad_updates.
        // This margin (net % the seller keeps) drives the profit calculation.
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Neweggb2c')->first();
        $percentage = $marketplaceData ? (float) $marketplaceData->percentage : 80;
        $adUpdates  = $marketplaceData ? (float) $marketplaceData->ad_updates : 0;
        $margin     = $percentage - $adUpdates;
        $factor     = $margin > 0 ? $margin / 100 : 0.80;

        $orders = NeweggOrder::with('items')
            ->orderBy('order_date', 'desc')
            ->get();

        // Collect SKUs (Newegg seller part number) for ProductMaster lookup.
        $skus = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if ($item->seller_part_number) {
                    $skus[] = $item->seller_part_number;
                }
            }
        }
        $skus = array_values(array_unique($skus));
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

        $data = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $sku = $item->seller_part_number;
                $pm  = $sku ? ($productMasters[$sku] ?? null) : null;

                [$lp, $ship, $weightAct] = $this->extractCosts($pm);

                $quantity  = (float) ($item->ordered_qty ?? 0);
                $unitPrice = (float) ($item->unit_price ?? 0);
                $saleAmount = (float) ($item->extend_unit_price ?? ($unitPrice * $quantity));

                $tWeight = $weightAct * $quantity;

                if ($quantity == 1) {
                    $shipCost = $ship;
                } elseif ($quantity > 1 && $tWeight < 20) {
                    $shipCost = $quantity > 0 ? $ship / $quantity : $ship;
                } else {
                    $shipCost = $ship;
                }

                $cogs       = $lp * $quantity;
                $pftEach    = ($unitPrice * $factor) - $lp - $shipCost;
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;
                $pft        = $pftEach * $quantity;
                $roi        = $lp > 0 ? ($pft / $lp) * 100 : 0;

                $data[] = [
                    'order_id'     => $order->order_number,
                    'sku'          => $sku,
                    'description'  => $item->description,
                    'quantity'     => (int) $quantity,
                    'price'        => round($unitPrice, 2),
                    'sale_amount'  => round($saleAmount, 2),
                    'total_amount' => round((float) $order->order_total_amount, 2),
                    'currency'     => $order->currency_code ?: 'USD',
                    'order_date'   => optional($order->order_date)->format('Y-m-d H:i:s'),
                    'status'       => $order->order_status_description,
                    'customer'     => $order->customer_name,
                    'lp'           => round($lp, 2),
                    'ship'         => round($ship, 2),
                    't_weight'     => round($tWeight, 2),
                    'ship_cost'    => round($shipCost, 2),
                    'cogs'         => round($cogs, 2),
                    'pft_each'     => round($pftEach, 2),
                    'pft_each_pct' => round($pftEachPct, 2),
                    'pft'          => round($pft, 2),
                    'roi'          => round($roi, 2),
                ];
            }
        }

        return response()->json($data);
    }

    /**
     * Pull LP, Ship and Weight from a ProductMaster row (Values JSON or columns).
     *
     * @return array{0:float,1:float,2:float}
     */
    private function extractCosts(?ProductMaster $pm): array
    {
        if (!$pm) {
            return [0.0, 0.0, 0.0];
        }

        $values = is_array($pm->Values)
            ? $pm->Values
            : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);

        $lp = 0.0;
        foreach ($values as $k => $v) {
            if (strtolower((string) $k) === 'lp') {
                $lp = (float) $v;
                break;
            }
        }
        if ($lp === 0.0 && isset($pm->lp)) {
            $lp = (float) $pm->lp;
        }

        $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0.0);
        $weightAct = isset($values['wt_act']) ? (float) $values['wt_act'] : 0.0;

        return [$lp, $ship, $weightAct];
    }

    public function getColumnVisibility()
    {
        try {
            $filePath = storage_path('app/newegg_sales_column_visibility.json');

            $default = [
                'order_id' => true, 'sku' => true, 'description' => false, 'quantity' => true,
                'price' => true, 'sale_amount' => true, 'total_amount' => false, 'currency' => false,
                'order_date' => true, 'status' => true, 'customer' => false, 'lp' => true, 'ship' => true,
                't_weight' => false, 'ship_cost' => false, 'cogs' => true, 'pft_each' => true,
                'pft_each_pct' => true, 'pft' => true, 'roi' => true,
            ];

            if (file_exists($filePath)) {
                $saved = json_decode(file_get_contents($filePath), true);
                if (is_array($saved)) {
                    return response()->json($saved);
                }
            }

            return response()->json($default);
        } catch (\Exception $e) {
            Log::error('Error getting Newegg column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function saveColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/newegg_sales_column_visibility.json');
            file_put_contents($filePath, json_encode($request->input('visibility', []), JSON_PRETTY_PRINT));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving Newegg column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }
}
