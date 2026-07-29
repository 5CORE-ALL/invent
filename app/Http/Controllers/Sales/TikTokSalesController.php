<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\ProductMaster;
use App\Models\TiktokOrder;
use App\Models\TiktokSalesTwo;

class TikTokSalesController extends Controller
{
    /** TikTok 2 margin (same as /tiktok-two/daily-sales). */
    public const TWO_MARGIN = 0.80;

    /**
     * Live L30 / L60 / GPFT / ROI from tiktok_sales_two — shared by
     * /tiktok-two/daily-sales and /all-marketplace-master (TikTok 2 row).
     *
     * Window: 30 days ending on the latest order_date (same as getTikTokTwoChannelData).
     * Profit: (unit_price × 0.80) − LP − shipCost, × qty.
     *
     * @return array{
     *   l30_sales: float, l30_orders: int, qty: int,
     *   l60_sales: float, l60_orders: int,
     *   total_pft: float, total_cogs: float,
     *   gpft_percent: float, roi_percent: float,
     *   latest_order_date: string|null
     * }
     */
    public static function computeLiveMetricsTwo(): array
    {
        $defaults = [
            'l30_sales' => 0.0,
            'l30_orders' => 0,
            'qty' => 0,
            'l60_sales' => 0.0,
            'l60_orders' => 0,
            'total_pft' => 0.0,
            'total_cogs' => 0.0,
            'gpft_percent' => 0.0,
            'roi_percent' => 0.0,
            'latest_order_date' => null,
        ];

        try {
            $latestOrderDate = TiktokSalesTwo::whereNotNull('order_date')->max('order_date');
            if (! $latestOrderDate) {
                return $defaults;
            }

            $latestCarbon = Carbon::parse($latestOrderDate);
            $l60StartDate = $latestCarbon->copy()->subDays(59)->startOfDay();
            $l60EndDate = $latestCarbon->copy()->subDays(30)->endOfDay();
            $l30StartDate = $latestCarbon->copy()->subDays(29)->startOfDay();
            $l30EndDate = $latestCarbon->copy()->endOfDay();

            $l60Rows = TiktokSalesTwo::whereBetween('order_date', [$l60StartDate, $l60EndDate])->get();
            $l60Orders = $l60Rows->pluck('order_id')->unique()->filter()->count();
            $l60Sales = (float) $l60Rows->sum(fn ($r) => (float) $r->unit_price * (float) ($r->quantity ?: 1));

            $l30Rows = TiktokSalesTwo::whereBetween('order_date', [$l30StartDate, $l30EndDate])->get();
            $productMasters = ProductMaster::query()
                ->get(['sku', 'Values'])
                ->keyBy(fn ($item) => strtoupper((string) $item->sku));

            $margin = self::TWO_MARGIN;
            $l30Sales = 0.0;
            $totalQuantity = 0.0;
            $totalProfit = 0.0;
            $totalCogs = 0.0;
            $orderIds = [];

            foreach ($l30Rows as $row) {
                $orderId = trim((string) ($row->order_id ?? ''));
                if ($orderId !== '') {
                    $orderIds[$orderId] = true;
                }
                $quantity = (float) ($row->quantity ?: 1);
                if ($quantity <= 0) {
                    continue;
                }
                $unitPrice = (float) $row->unit_price;
                $l30Sales += $unitPrice * $quantity;

                $sku = strtoupper((string) ($row->seller_sku ?? ''));
                $lp = 0.0;
                $ship = 0.0;
                $weightAct = 0.0;
                $pm = $sku !== '' ? $productMasters->get($sku) : null;
                if ($pm) {
                    $values = is_array($pm->Values)
                        ? $pm->Values
                        : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);
                    if (is_array($values)) {
                        foreach ($values as $k => $v) {
                            if (strtolower((string) $k) === 'lp') {
                                $lp = (float) $v;
                                break;
                            }
                        }
                        if (isset($values['ship'])) {
                            $ship = (float) $values['ship'];
                        }
                        if (isset($values['wt_act'])) {
                            $weightAct = (float) $values['wt_act'];
                        }
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
                $totalQuantity += $quantity;
                $totalCogs += $cogs;
                $totalProfit += $pftEach * $quantity;
            }

            $gpft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0.0;
            $roi = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0.0;

            return [
                'l30_sales' => round($l30Sales, 2),
                'l30_orders' => count($orderIds),
                'qty' => (int) round($totalQuantity),
                'l60_sales' => round($l60Sales, 2),
                'l60_orders' => $l60Orders,
                'total_pft' => round($totalProfit, 2),
                'total_cogs' => round($totalCogs, 2),
                'gpft_percent' => round($gpft, 1),
                'roi_percent' => round($roi, 1),
                'latest_order_date' => $latestCarbon->toDateString(),
            ];
        } catch (\Throwable $e) {
            Log::warning('TikTok 2 computeLiveMetricsTwo failed: ' . $e->getMessage());

            return $defaults;
        }
    }

    /**
     * Display TikTok daily sales page
     */
    public function index()
    {
        return view('sales.tiktok_daily_sales_data');
    }

    /**
     * Get TikTok sales data from tiktok_orders — last 30 California calendar days.
     */
    public function getData(Request $request)
    {
        try {
            [$startDate, $endDate] = TiktokOrder::californiaDaysWindow(30);
            $orderItems = TiktokOrder::linesInWindow($startDate, $endDate);

            if ($orderItems->isEmpty()) {
                return response()->json([]);
            }

            $skus = $orderItems->pluck('seller_sku')->filter()->unique()->values()->toArray();
            $productMasters = ProductMaster::whereIn('sku', $skus)
                ->get()
                ->keyBy(fn ($item) => strtoupper($item->sku));

            $margin = 0.80;
            $data = [];

            foreach ($orderItems as $item) {
                $sku = strtoupper(trim((string) ($item->seller_sku ?? '')));
                $quantity = (float) ($item->quantity ?? 1);
                if ($quantity <= 0) {
                    continue;
                }

                $unitPrice = (float) ($item->sale_price ?? 0);
                $saleAmount = $unitPrice * $quantity;
                $orderAmount = (float) ($item->order_amount ?? $saleAmount);

                $lp = 0;
                $ship = 0;
                $weightAct = 0;

                if ($sku && isset($productMasters[$sku])) {
                    $pm = $productMasters[$sku];
                    $values = is_array($pm->Values) ? $pm->Values :
                            (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

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
                    'order_id' => $item->order_id,
                    'asin' => null,
                    'sku' => $item->seller_sku,
                    'quantity' => $item->quantity,
                    'sale_amount' => round($saleAmount, 2),
                    'price' => round($unitPrice, 2),
                    'total_amount' => round($orderAmount, 2),
                    'currency' => $item->currency,
                    'order_date' => $item->order_created_at
                        ? \Carbon\Carbon::parse($item->order_created_at, 'UTC')->timezone(TiktokOrder::TZ)->toDateTimeString()
                        : null,
                    'status' => $item->order_status,
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

            $margin = self::TWO_MARGIN; // 80% margin (same as TikTok)
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
