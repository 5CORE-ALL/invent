<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\TikTokDailyData;
use App\Models\ProductMaster;
use App\Services\TikTokShopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TikTokSalesController extends Controller
{
    protected $tikTokService;

    public function __construct(TikTokShopService $tikTokService)
    {
        $this->tikTokService = $tikTokService;
    }

    public function index()
    {
        return view('sales.tiktok_daily_sales_data');
    }

    public function getData(Request $request)
    {
        Log::info('TikTokSalesController getData called');

        $orders = TikTokDailyData::where('period', 'l30')
            ->whereNotIn('order_status', ['CANCELLED', 'REFUNDED', 'CANCELED'])
            ->orderBy('order_created_at', 'desc')
            ->get();

        Log::info('Found ' . $orders->count() . ' TikTok orders');

        // Get unique SKUs
        $skus = $orders->pluck('sku')->unique()->toArray();

        // Fetch ProductMaster data for LP and Ship
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

        $data = [];
        foreach ($orders as $order) {
            $pm = $productMasters[$order->sku] ?? null;

            // Extract LP and Ship
            $lp = 0;
            $ship = 0;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
                $ship = isset($values["ship"]) ? floatval($values["ship"]) : (isset($pm->ship) ? floatval($pm->ship) : 0);
            }

            $quantity = (int) $order->quantity;
            $unitPrice = floatval($order->unit_price);
            $totalAmount = floatval($order->total_amount);

            // COGS = LP * quantity
            $cogs = $lp * $quantity;

            // TikTok uses 85% margin (15% platform fees approximately)
            // PFT Each = (unit_price * 0.85) - lp - ship
            $pftEach = ($unitPrice * 0.85) - $lp - $ship;

            // PFT Each % = (pft_each / unit_price) * 100
            $pftEachPct = $unitPrice > 0 ? ($pftEach / $unitPrice) * 100 : 0;

            // T PFT = pft_each * quantity
            $pft = $pftEach * $quantity;

            // ROI = (PFT / COGS) * 100
            $roi = $cogs > 0 ? ($pft / $cogs) * 100 : 0;

            // L30 Sales = total_amount
            $l30Sales = $totalAmount;

            $data[] = [
                'order_id' => $order->order_id,
                'sku' => $order->sku,
                'product_name' => $order->product_name,
                'quantity' => $quantity,
                'unit_price' => round($unitPrice, 2),
                'total_amount' => round($totalAmount, 2),
                'order_status' => $order->order_status,
                'lp' => round($lp, 2),
                'ship' => round($ship, 2),
                'cogs' => round($cogs, 2),
                'pft_each' => round($pftEach, 2),
                'pft_each_pct' => round($pftEachPct, 2),
                'pft' => round($pft, 2),
                'roi' => round($roi, 2),
                'l30_sales' => round($l30Sales, 2),
                'platform_commission' => round($order->platform_commission, 2),
                'shipping_fee' => round($order->shipping_fee, 2),
                'net_sales' => round($order->net_sales, 2),
                'order_date' => $order->order_created_at ? Carbon::parse($order->order_created_at)->format('Y-m-d H:i') : '',
                'tracking_number' => $order->tracking_number,
            ];
        }

        return response()->json($data);
    }

    /**
     * OAuth callback handler
     */
    public function callback(Request $request)
    {
        $authCode = $request->get('code');
        $state = $request->get('state');

        if (!$authCode) {
            return redirect()->route('tiktok.sales')->with('error', 'Authorization failed: No code received');
        }

        $tokens = $this->tikTokService->getAccessToken($authCode);

        if ($tokens) {
            return redirect()->route('tiktok.sales')->with('success', 'TikTok Shop connected successfully!');
        }

        return redirect()->route('tiktok.sales')->with('error', 'Failed to get access token');
    }

    /**
     * Start OAuth flow
     */
    public function startAuthorization()
    {
        $authUrl = $this->tikTokService->getAuthorizationUrl();
        return redirect($authUrl);
    }

    /**
     * Sync orders from TikTok API
     */
    public function syncOrders(Request $request)
    {
        $days = $request->get('days', 30);

        try {
            if (!$this->tikTokService->isAuthenticated()) {
                return response()->json([
                    'success' => false,
                    'message' => 'TikTok not authenticated. Please authorize first.',
                    'auth_url' => $this->tikTokService->getAuthorizationUrl()
                ], 401);
            }

            $orders = $this->tikTokService->getAllOrders($days);

            if (empty($orders)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No orders found',
                    'count' => 0
                ]);
            }

            $imported = 0;
            $updated = 0;

            foreach ($orders as $order) {
                // Get order details for line items
                $orderDetails = $this->tikTokService->getOrderDetail([$order['order_id']]);
                
                if (!$orderDetails || !isset($orderDetails['data']['orders'][0])) {
                    continue;
                }

                $orderData = $orderDetails['data']['orders'][0];
                $lineItems = $orderData['line_items'] ?? [];

                foreach ($lineItems as $item) {
                    $existingRecord = TikTokDailyData::where('order_id', $order['order_id'])
                        ->where('sku', $item['sku_id'] ?? $item['seller_sku'] ?? '')
                        ->first();

                    $recordData = [
                        'order_id' => $order['order_id'],
                        'order_status' => $order['order_status'] ?? '',
                        'sku' => $item['sku_id'] ?? $item['seller_sku'] ?? '',
                        'product_name' => $item['product_name'] ?? '',
                        'quantity' => $item['quantity'] ?? 1,
                        'unit_price' => ($item['sale_price'] ?? 0) / 100, // Convert cents to dollars
                        'total_amount' => (($item['sale_price'] ?? 0) * ($item['quantity'] ?? 1)) / 100,
                        'shipping_fee' => ($orderData['payment']['shipping_fee'] ?? 0) / 100,
                        'platform_discount' => ($orderData['payment']['platform_discount'] ?? 0) / 100,
                        'seller_discount' => ($orderData['payment']['seller_discount'] ?? 0) / 100,
                        'platform_commission' => ($orderData['payment']['platform_commission'] ?? 0) / 100,
                        'buyer_name' => $orderData['recipient_address']['name'] ?? '',
                        'tracking_number' => $item['package_id'] ?? '',
                        'order_created_at' => isset($order['create_time']) ? Carbon::createFromTimestamp($order['create_time']) : null,
                        'paid_at' => isset($order['paid_time']) ? Carbon::createFromTimestamp($order['paid_time']) : null,
                        'period' => 'l30',
                    ];

                    // Calculate net sales
                    $recordData['net_sales'] = $recordData['total_amount'] - $recordData['platform_commission'] - $recordData['platform_discount'];

                    if ($existingRecord) {
                        $existingRecord->update($recordData);
                        $updated++;
                    } else {
                        TikTokDailyData::create($recordData);
                        $imported++;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Sync complete. Imported: $imported, Updated: $updated",
                'imported' => $imported,
                'updated' => $updated
            ]);

        } catch (\Exception $e) {
            Log::error('TikTok sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get connection status
     */
    public function status()
    {
        $isAuthenticated = $this->tikTokService->isAuthenticated();

        if ($isAuthenticated) {
            $shopInfo = $this->tikTokService->getShopInfo();
            return response()->json([
                'connected' => true,
                'shop_info' => $shopInfo['data'] ?? null
            ]);
        }

        return response()->json([
            'connected' => false,
            'auth_url' => $this->tikTokService->getAuthorizationUrl()
        ]);
    }

    /**
     * Get summary statistics
     */
    public function getSummary()
    {
        $summary = TikTokDailyData::where('period', 'l30')
            ->whereNotIn('order_status', ['CANCELLED', 'REFUNDED', 'CANCELED'])
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(quantity) as total_quantity,
                SUM(total_amount) as total_sales,
                SUM(platform_commission) as total_commission,
                SUM(net_sales) as total_net_sales,
                AVG(unit_price) as avg_price
            ')
            ->first();

        return response()->json([
            'total_orders' => $summary->total_orders ?? 0,
            'total_quantity' => $summary->total_quantity ?? 0,
            'total_sales' => round($summary->total_sales ?? 0, 2),
            'total_commission' => round($summary->total_commission ?? 0, 2),
            'total_net_sales' => round($summary->total_net_sales ?? 0, 2),
            'avg_price' => round($summary->avg_price ?? 0, 2),
        ]);
    }
}
