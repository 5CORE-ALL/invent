<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductMaster;
use App\Models\TiktokSalesTwo;

class TikTokSalesController extends Controller
{
    /**
     * Display TikTok daily sales page
     */
    public function index()
    {
        return view('sales.tiktok_daily_sales_data');
    }

    /**
     * Get TikTok sales data from ShipHub (33 days)
     */
    public function getData(Request $request)
    {
        try {
            // Get latest TikTok order date from ShipHub
            $latestDate = DB::connection('shiphub')
                ->table('orders')
                ->where('marketplace', '=', 'tiktok')
                ->max('order_date');

            if (!$latestDate) {
                return response()->json([]);
            }

            // Calculate date range: 30 days total (California time)
            $latestDateCarbon = \Carbon\Carbon::parse($latestDate, 'America/Los_Angeles');
            $startDate = $latestDateCarbon->copy()->subDays(29); // 30 days total (29 previous days + today)
            $startDateStr = $startDate->format('Y-m-d');
            $endDateStr = $latestDateCarbon->format('Y-m-d');

            // QUERY 1: Get all order items from ShipHub with JOIN (TikTok marketplace only)
            $orderItems = DB::connection('shiphub')
                ->table('orders as o')
                ->join('order_items as i', 'o.id', '=', 'i.order_id')
                ->whereBetween('o.order_date', [$startDate, $latestDateCarbon->endOfDay()])
                ->where('o.marketplace', '=', 'tiktok')
                ->where(function($query) {
                    $query->where('o.order_status', '!=', 'Canceled')
                          ->where('o.order_status', '!=', 'Cancelled')
                          ->where('o.order_status', '!=', 'canceled')
                          ->where('o.order_status', '!=', 'cancelled')
                          ->orWhereNull('o.order_status');
                })
                ->select([
                    DB::raw("COALESCE(o.marketplace_order_id, o.order_number, CONCAT('SH-', o.id)) as order_id"),
                    'o.id as internal_order_id', // For deduplication
                    'o.order_date',
                    'o.order_status as status',
                    'o.order_total as total_amount',
                    'i.currency',
                    DB::raw("'L30' as period"),
                    'i.asin',
                    'i.sku',
                    'i.product_name as title',
                    'i.quantity_ordered as quantity',
                    'i.unit_price as price', // This is TOTAL price for item line in ShipHub
                ])
                ->orderBy('o.order_date', 'desc')
                ->get();

            if ($orderItems->isEmpty()) {
                return response()->json([]);
            }

            // QUERY 2: Get ProductMaster data for LP, Ship, Weight
            $skus = $orderItems->pluck('sku')->filter()->unique()->values()->toArray();
            $productMasters = ProductMaster::whereIn('sku', $skus)
                ->get()
                ->keyBy(function ($item) {
                    return strtoupper($item->sku);
                });

            // QUERY 3: TikTok margin fixed at 80%
            $margin = 0.80; // 80% margin (20% TikTok fees)

            // QUERY 4: Get KW Spent (if TikTok has ads - currently 0)
            $kwSpent = 0; // TikTok doesn't have KW ads yet

            // QUERY 5: Get PT Spent (if TikTok has ads - currently 0)
            $ptSpent = 0; // TikTok doesn't have PT ads yet

            // Group items by order to handle multi-item orders correctly
            $orderGroups = [];
            foreach ($orderItems as $item) {
                $orderId = $item->internal_order_id;
                if (!isset($orderGroups[$orderId])) {
                    $orderGroups[$orderId] = [];
                }
                $orderGroups[$orderId][] = $item;
            }

            // Process data
            $data = [];
            foreach ($orderGroups as $orderId => $items) {
                $orderTotal = floatval($items[0]->total_amount);
                $itemCount = count($items);
                
                // Distribute order_total across all items in the order
                $pricePerItem = $itemCount > 0 ? $orderTotal / $itemCount : $orderTotal;
                
                foreach ($items as $item) {
                    $sku = strtoupper($item->sku ?? '');
                    $quantity = floatval($item->quantity);
                    
                    // TikTok FIX: unit_price is always 0 in ShipHub for TikTok
                    // Use distributed price per item from order_total
                    $totalPrice = $pricePerItem; // Distributed price
                    $unitPrice = $quantity > 0 ? $totalPrice / $quantity : 0;
                    $saleAmount = $totalPrice;

                // Get LP, Ship and wt_act from ProductMaster
                $lp = 0;
                $ship = 0;
                $weightAct = 0;

                if ($sku && isset($productMasters[$sku])) {
                    $pm = $productMasters[$sku];
                    $values = is_array($pm->Values) ? $pm->Values :
                            (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    
                    // Get LP
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === "lp") {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }
                    
                    // Get Ship
                    if (isset($values['ship'])) {
                        $ship = floatval($values['ship']);
                    } elseif (isset($pm->ship)) {
                        $ship = floatval($pm->ship);
                    }
                    
                    // Get Weight Act
                    if (isset($values['wt_act'])) {
                        $weightAct = floatval($values['wt_act']);
                    }
                }

                // T Weight = Weight Act * Quantity
                $tWeight = $weightAct * $quantity;

                // Ship Cost calculation (same as Amazon):
                // If quantity is 1: ship_cost = ship
                // If quantity > 1 and t_weight < 20: ship_cost = ship / quantity
                // Otherwise: ship_cost = ship
                if ($quantity == 1) {
                    $shipCost = $ship;
                } elseif ($quantity > 1 && $tWeight < 20) {
                    $shipCost = $ship / $quantity;
                } else {
                    $shipCost = $ship;
                }

                // COGS = LP * quantity (only LP, not Ship)
                $cogs = $lp * $quantity;

                // PFT Each = (unitPrice * margin) - lp - ship_cost
                $pftEach = ($unitPrice * $margin) - $lp - $shipCost;
                
                // PFT Each % = (PFT Each / Unit Price) * 100
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

                // T PFT = pft_each * quantity
                $pft = $pftEach * $quantity;

                // ROI = (PFT Each / LP) * 100
                $roi = $lp > 0 ? ($pftEach / $lp) * 100 : 0;

                $data[] = [
                    'order_id' => $item->order_id ?? $item->order_number,
                    'asin' => $item->asin,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'sale_amount' => round($saleAmount, 2),
                    'price' => round($unitPrice, 2), // Per-unit price
                    'total_amount' => round(floatval($item->total_amount), 2),
                    'currency' => $item->currency,
                    'order_date' => $item->order_date,
                    'status' => $item->status,
                    'period' => $item->period,
                    'lp' => round($lp, 2),
                    'ship' => round($ship, 2),
                    'ship_cost' => round($shipCost, 2),
                    'weight_act' => round($weightAct, 2),
                    't_weight' => round($tWeight, 2),
                    'cogs' => round($cogs, 2),
                    'pft_each' => round($pftEach, 2),
                    'pft_each_pct' => round($pftEachPct, 2),
                    't_pft' => round($pft, 2),
                    'roi' => round($roi, 2),
                    'margin' => round($margin * 100, 2), // Show as percentage
                ];
                } // End foreach items
            } // End foreach orderGroups

            return response()->json($data);

        } catch (\Exception $e) {
            Log::error('TikTok Sales Data Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getColumnVisibility()
    {
        $visibility = Cache::get('tiktok_sales_column_visibility', []);
        return response()->json($visibility);
    }

    public function saveColumnVisibility(Request $request)
    {
        $visibility = $request->input('visibility', []);
        Cache::put('tiktok_sales_column_visibility', $visibility, now()->addYears(1));
        return response()->json(['success' => true]);
    }

    // ---- TikTok Sales Two (upload-based, margin 0.80) ----

    /**
     * Display TikTok 2 daily sales page (upload-based data)
     */
    public function indexTwo()
    {
        $kwSpent = 0;
        $ptSpent = 0;
        $hlSpent = 0;
        return view('sales.tiktok_two_daily_sales_data', compact('kwSpent', 'ptSpent', 'hlSpent'));
    }

    /**
     * Get TikTok 2 sales data from tiktok_sales_two table (margin 0.80, same as TikTok)
     */
    public function getDataTwo(Request $request)
    {
        try {
            $rows = TiktokSalesTwo::orderBy('order_date', 'desc')->get();
            if ($rows->isEmpty()) {
                return response()->json([]);
            }

            $margin = 0.80; // 80% margin (same as TikTok)
            $skus = $rows->pluck('seller_sku')->filter()->unique()->map(function ($s) {
                return strtoupper($s);
            })->values()->toArray();
            $productMasters = ProductMaster::whereIn('sku', $skus)
                ->get()
                ->keyBy(function ($item) {
                    return strtoupper($item->sku);
                });

            $data = [];
            foreach ($rows as $row) {
                $sku = strtoupper($row->seller_sku ?? '');
                $quantity = floatval($row->quantity);
                $unitPrice = floatval($row->unit_price);
                $saleAmount = $unitPrice * $quantity;
                if ($quantity <= 0) {
                    continue;
                }

                $lp = 0;
                $ship = 0;
                $weightAct = 0;
                if ($sku && isset($productMasters[$sku])) {
                    $pm = $productMasters[$sku];
                    $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === 'lp') {
                            $lp = floatval($v);
                            break;
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }
                    if (isset($values['ship'])) {
                        $ship = floatval($values['ship']);
                    } elseif (isset($pm->ship)) {
                        $ship = floatval($pm->ship);
                    }
                    if (isset($values['wt_act'])) {
                        $weightAct = floatval($values['wt_act']);
                    }
                }

                $tWeight = $weightAct * $quantity;
                if ($quantity == 1) {
                    $shipCost = $ship;
                } elseif ($quantity > 1 && $tWeight < 20) {
                    $shipCost = $ship / $quantity;
                } else {
                    $shipCost = $ship;
                }
                $cogs = $lp * $quantity;
                $pftEach = ($unitPrice * $margin) - $lp - $shipCost;
                $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;
                $pft = $pftEach * $quantity;
                $roi = $lp > 0 ? ($pftEach / $lp) * 100 : 0;

                $data[] = [
                    'order_id' => $row->order_id,
                    'asin' => '',
                    'sku' => $row->seller_sku,
                    'quantity' => $row->quantity,
                    'sale_amount' => round($saleAmount, 2),
                    'price' => round($unitPrice, 2),
                    'total_amount' => round(floatval($row->order_amount), 2),
                    'currency' => 'USD',
                    'order_date' => $row->order_date?->toIso8601String(),
                    'status' => $row->order_status,
                    'period' => 'L30',
                    'lp' => round($lp, 2),
                    'ship' => round($ship, 2),
                    'ship_cost' => round($shipCost, 2),
                    'weight_act' => round($weightAct, 2),
                    't_weight' => round($tWeight, 2),
                    'cogs' => round($cogs, 2),
                    'pft_each' => round($pftEach, 2),
                    'pft_each_pct' => round($pftEachPct, 2),
                    't_pft' => round($pft, 2),
                    'roi' => round($roi, 2),
                    'margin' => round($margin * 100, 2),
                ];
            }
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('TikTok Sales Two Data Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload TikTok 2 sales file: truncate tiktok_sales_two and insert new rows (TSV/CSV TikTok order export).
     * Column positions are resolved from the header row — TikTok adds columns over time (e.g. Order Substatus),
     * so fixed indices break (old code used index 11 for price, which is "Sku Quantity of return" in the 2026 export).
     */
    public function uploadTwo(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200', // 50MB, any extension
        ]);

        try {
            $file = $request->file('file');
            $path = $file->getRealPath();
            $handle = fopen($path, 'r');
            if (!$handle) {
                return response()->json(['error' => 'Could not open file'], 400);
            }

            // Auto-detect delimiter from header: tab vs comma; strip BOM if present
            $headerLine = fgets($handle);
            if ($headerLine === false) {
                fclose($handle);
                return response()->json(['error' => 'Empty or invalid file'], 400);
            }
            $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine); // UTF-8 BOM
            $delimiter = (substr_count($headerLine, "\t") >= substr_count($headerLine, ',')) ? "\t" : ',';
            rewind($handle);
            $header = fgetcsv($handle, 0, $delimiter);
            if ($header !== false && isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            if ($header === false || count(array_filter($header, fn ($h) => trim((string) $h) !== '')) === 0) {
                fclose($handle);
                return response()->json(['error' => 'Empty or invalid file'], 400);
            }

            $cols = $this->resolveTikTokTwoColumnIndexes($header);
            $minCols = max(
                $cols['order_id'],
                $cols['seller_sku'],
                $cols['quantity'],
                $cols['unit_price'],
                $cols['product_name']
            ) + 1;

            DB::beginTransaction();
            // Use delete() instead of truncate() - truncate commits the transaction in MySQL
            TiktokSalesTwo::query()->delete();

            $inserted = 0;
            while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
                $numCols = count($cells);
                if ($numCols < $minCols) {
                    continue;
                }
                $c = static function (array $cells, int $idx): string {
                    return isset($cells[$idx]) ? trim((string) $cells[$idx], " \t\r\n\"") : '';
                };

                $orderId = $c($cells, $cols['order_id']);
                $sellerSku = $c($cells, $cols['seller_sku']);
                $qtyRaw = $c($cells, $cols['quantity']);
                $quantity = (int) preg_replace('/[^0-9-]/', '', $qtyRaw);

                // Skip empty rows
                if ($orderId === '' && $sellerSku === '' && $quantity === 0) {
                    continue;
                }

                $orderStatus = $c($cells, $cols['order_status']);
                $productName = $c($cells, $cols['product_name']);
                $unitPrice = (float) preg_replace('/[^0-9.\-]/', '', $c($cells, $cols['unit_price']));

                $oaIdx = $cols['order_amount'];
                $orderAmount = ($oaIdx !== null && $numCols > $oaIdx && $c($cells, $oaIdx) !== '')
                    ? (float) preg_replace('/[^0-9.\-]/', '', $c($cells, $oaIdx))
                    : ($unitPrice * ($quantity ?: 1));

                $ctIdx = $cols['created_time'];
                $createdTime = ($ctIdx !== null && $numCols > $ctIdx) ? $c($cells, $ctIdx) : null;
                if ($createdTime === '') {
                    $createdTime = null;
                }

                $orderDate = $this->parseTikTokTwoOrderDate($createdTime);

                TiktokSalesTwo::create([
                    'order_id' => $orderId,
                    'order_status' => $orderStatus,
                    'seller_sku' => $sellerSku,
                    'product_name' => $productName,
                    'quantity' => $quantity ?: 1,
                    'unit_price' => $unitPrice,
                    'order_amount' => $orderAmount,
                    'order_date' => $orderDate,
                ]);
                $inserted++;
            }
            fclose($handle);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Upload complete. {$inserted} rows imported.",
                'rows' => $inserted,
            ]);
        } catch (\Exception $e) {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('TikTok Sales Two Upload Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function normalizeTikTokTwoHeaderCell($h): string
    {
        $s = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);

        return strtolower(trim(preg_replace('/\s+/', ' ', $s)));
    }

    /**
     * Map TikTok export headers to column indexes. Current exports include extra columns (Order Substatus, etc.),
     * so fixed numeric positions are unreliable.
     *
     * @return array{order_id:int,order_status:int,seller_sku:int,product_name:int,quantity:int,unit_price:int,order_amount:?int,created_time:?int}
     */
    private function resolveTikTokTwoColumnIndexes(array $header): array
    {
        $norm = [];
        foreach ($header as $i => $h) {
            $norm[$i] = $this->normalizeTikTokTwoHeaderCell($h);
        }

        $firstExact = [];
        foreach ($norm as $i => $n) {
            if ($n !== '' && ! array_key_exists($n, $firstExact)) {
                $firstExact[$n] = $i;
            }
        }

        $get = static function (string $key) use ($firstExact): ?int {
            return $firstExact[$key] ?? null;
        };

        $orderId = $get('order id');
        $orderStatus = $get('order status');
        $sellerSku = $get('seller sku');
        $productName = $get('product name');
        // Line-item quantity — not "sku quantity of return"
        $quantity = $get('quantity');
        $unitPrice = $get('sku unit original price');
        $orderAmount = $get('order amount');
        $createdTime = $get('created time');

        if ($unitPrice === null) {
            foreach ($norm as $i => $n) {
                if ($n === 'sku unit original price' || preg_match('/^sku\s+unit\s+original\s+price$/', $n)) {
                    $unitPrice = $i;
                    break;
                }
            }
        }

        if ($orderId !== null && $sellerSku !== null && $quantity !== null && $unitPrice !== null) {
            return [
                'order_id' => $orderId,
                'order_status' => $orderStatus ?? 1,
                'seller_sku' => $sellerSku,
                'product_name' => $productName ?? ($sellerSku + 1),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'order_amount' => $orderAmount,
                'created_time' => $createdTime,
            ];
        }

        return $this->fallbackTikTokTwoColumnIndexesByLayout($norm);
    }

    /**
     * When header names differ (locale) but column layout matches a known TikTok template.
     *
     * @param  array<int, string>  $norm
     * @return array{order_id:int,order_status:int,seller_sku:int,product_name:int,quantity:int,unit_price:int,order_amount:?int,created_time:?int}
     */
    private function fallbackTikTokTwoColumnIndexesByLayout(array $norm): array
    {
        $joined = implode(' ', $norm);
        $newExport = str_contains($joined, 'order substatus')
            || str_contains($joined, 'cancelation')
            || str_contains($joined, 'sku quantity of return');

        if ($newExport) {
            return [
                'order_id' => 0,
                'order_status' => 1,
                'seller_sku' => 6,
                'product_name' => 7,
                'quantity' => 10,
                'unit_price' => 12,
                'order_amount' => 25,
                'created_time' => 27,
            ];
        }

        return [
            'order_id' => 0,
            'order_status' => 1,
            'seller_sku' => 6,
            'product_name' => 7,
            'quantity' => 9,
            'unit_price' => 11,
            'order_amount' => 24,
            'created_time' => 26,
        ];
    }

    /**
     * Parse TikTok export "Created Time". Carbon::parse('6') becomes 1970-01-01 00:00:06 which MySQL TIMESTAMP
     * rejects or stores as garbage; short integers must not be treated as dates.
     */
    private function parseTikTokTwoOrderDate(?string $createdTime): ?\Carbon\Carbon
    {
        if ($createdTime === null || trim($createdTime) === '') {
            return null;
        }
        $s = trim($createdTime, " \t\"");

        // Integer-only: Unix seconds (10) or ms (13+). Reject small values that parse as epoch seconds.
        if (preg_match('/^\d+$/', $s)) {
            $len = strlen($s);
            if ($len >= 13) {
                $sec = (int) floor((int) $s / 1000);

                return $sec >= 946684800 ? \Carbon\Carbon::createFromTimestamp($sec) : null;
            }
            if ($len === 10) {
                $sec = (int) $s;

                return $sec >= 946684800 ? \Carbon\Carbon::createFromTimestamp($sec) : null;
            }

            return null;
        }

        $formats = [
            'm/d/Y h:i:s A',
            'm/d/Y H:i:s',
            'm/d/Y g:i:s A',
            'Y-m-d H:i:s',
            'Y-m-d H:i:s.u',
        ];
        foreach ($formats as $fmt) {
            try {
                $dt = \Carbon\Carbon::createFromFormat($fmt, $s);
                if ($dt === false) {
                    continue;
                }
                if ($this->isTikTokTwoOrderDatePlausible($dt)) {
                    return $dt;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        try {
            $dt = \Carbon\Carbon::parse($s);
        } catch (\Throwable $e) {
            return null;
        }

        return $this->isTikTokTwoOrderDatePlausible($dt) ? $dt : null;
    }

    private function isTikTokTwoOrderDatePlausible(\Carbon\Carbon $dt): bool
    {
        $y = $dt->year;

        return $y >= 2000 && $y <= 2100;
    }

    public function getColumnVisibilityTwo()
    {
        $visibility = Cache::get('tiktok_two_sales_column_visibility', []);
        return response()->json($visibility);
    }

    public function saveColumnVisibilityTwo(Request $request)
    {
        $visibility = $request->input('visibility', []);
        Cache::put('tiktok_two_sales_column_visibility', $visibility, now()->addYears(1));
        return response()->json(['success' => true]);
    }
}
