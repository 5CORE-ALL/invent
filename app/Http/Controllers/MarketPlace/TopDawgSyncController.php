<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\TopDawgOrderMetric;
use App\Models\TopDawgProduct;
use App\Models\TopDawgSyncSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TopDawgSyncController extends Controller
{
    /**
     * Products: TopDawg products with state tabs and search (no Shopify).
     */
    public function syncProducts(Request $request): View
    {
        if (!Schema::hasTable('topdawg_products')) {
            return view('marketplace.topdawg.products', [
                'products' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50),
                'title' => 'TopDawg - Products',
                'counts' => ['all' => 0, 'drafts' => 0, 'active' => 0, 'ended' => 0, 'sold' => 0],
                'stateTab' => 'all',
                'searchName' => null,
                'searchSku' => null,
            ])->with('message', 'Run migrations and topdawg:fetch to load products.');
        }

        $searchName = $request->input('search_name');
        $searchSku = $request->input('search_sku');
        $stateTab = $request->input('state', 'all');

        $baseQuery = TopDawgProduct::query()->whereNotNull('sku');

        if ($stateTab === 'drafts') {
            $baseQuery->where('listing_state', 'draft');
        } elseif ($stateTab === 'active') {
            $baseQuery->whereIn('listing_state', ['live', 'active']);
        } elseif ($stateTab === 'ended') {
            $baseQuery->where('listing_state', 'ended');
        } elseif ($stateTab === 'sold') {
            $baseQuery->where('listing_state', 'sold');
        }

        if ($searchSku !== null && $searchSku !== '') {
            $baseQuery->where('sku', 'like', '%' . trim($searchSku) . '%');
        }
        if ($searchName !== null && $searchName !== '') {
            $baseQuery->where(function ($q) use ($searchName) {
                $q->where('product_title', 'like', '%' . trim($searchName) . '%')
                    ->orWhere('sku', 'like', '%' . trim($searchName) . '%');
            });
        }

        $products = $baseQuery->orderBy('sku')->paginate(50)->withQueryString();

        $countBase = TopDawgProduct::query()->whereNotNull('sku');
        $counts = [
            'all' => (clone $countBase)->count(),
            'drafts' => (clone $countBase)->where('listing_state', 'draft')->count(),
            'active' => (clone $countBase)->whereIn('listing_state', ['live', 'active'])->count(),
            'ended' => (clone $countBase)->where('listing_state', 'ended')->count(),
            'sold' => (clone $countBase)->where('listing_state', 'sold')->count(),
        ];

        return view('marketplace.topdawg.products', [
            'products' => $products,
            'title' => 'TopDawg - Products',
            'counts' => $counts,
            'stateTab' => $stateTab,
            'searchName' => $searchName,
            'searchSku' => $searchSku,
        ]);
    }

    /**
     * Orders: all TopDawg orders (no Shopify push).
     */
    public function syncOrders(Request $request): View
    {
        if (!Schema::hasTable('topdawg_order_metrics')) {
            return view('marketplace.topdawg.orders', [
                'orders' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50),
                'title' => 'TopDawg - Orders',
            ]);
        }

        $orders = TopDawgOrderMetric::query()
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(50);

        return view('marketplace.topdawg.orders', [
            'orders' => $orders,
            'title' => 'TopDawg - Orders',
        ]);
    }

    /**
     * Settings page (no Shopify options).
     */
    public function syncSettings(Request $request): View
    {
        $settings = TopDawgSyncSettings::getForTopDawg();

        return view('marketplace.topdawg.settings', [
            'settings' => $settings,
            'title' => 'TopDawg - Settings',
        ]);
    }

    /**
     * Save settings (POST).
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'general' => 'sometimes|array',
            'general.sync_enabled' => 'sometimes',
        ]);

        $current = TopDawgSyncSettings::getForTopDawg();
        $merged = array_merge($current, $payload);
        if (isset($payload['general']['sync_enabled'])) {
            $merged['general']['sync_enabled'] = filter_var($payload['general']['sync_enabled'], FILTER_VALIDATE_BOOLEAN);
        }
        TopDawgSyncSettings::setForTopDawg($merged);

        return response()->json([
            'success' => true,
            'message' => 'Settings saved.',
            'saved_settings' => TopDawgSyncSettings::getForTopDawg(),
        ]);
    }

    /**
     * Sales dashboard view (Tabulator + badges from topdawg_order_metrics, margin 0.95, no ship).
     */
    public function salesDashboard(Request $request): View
    {
        return view('market-places.topdawg_sales_dashboard');
    }

    /**
     * JSON data for TopDawg sales dashboard.
     * PFT formula: (price * percentage - lp) * quantity; percentage = 0.95, no ship.
     */
    public function getSalesData(Request $request): JsonResponse
    {
        if (!Schema::hasTable('topdawg_order_metrics')) {
            return response()->json([]);
        }

        $normalizeSku = function ($sku) {
            $sku = strtoupper(trim((string) $sku));
            $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
            $sku = preg_replace('/\s+/', ' ', $sku);
            return $sku;
        };

        $orders = TopDawgOrderMetric::query()
            ->orderByDesc('order_date')
            ->orderByDesc('order_paid_at')
            ->orderByDesc('id')
            ->get();

        $skus = $orders->pluck('sku')->filter()->unique()->values()->all();
        $productMasters = ProductMaster::whereIn('sku', $skus)->get();
        $pmBySku = $productMasters->keyBy('sku');
        $pmByNormalized = $productMasters->keyBy(function ($pm) use ($normalizeSku) {
            return $normalizeSku($pm->sku ?? '');
        });

        $result = [];
        foreach ($orders as $row) {
            $sku = $row->sku ?? '';
            $pm = $pmBySku[$sku] ?? $pmByNormalized[$normalizeSku($sku)] ?? null;
            $lp = 0;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values ?? null) ? json_decode($pm->Values, true) : []);
                if (is_array($values)) {
                    foreach ($values as $k => $v) {
                        if (strtolower((string) $k) === 'lp') {
                            $lp = (float) $v;
                            break;
                        }
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = (float) $pm->lp;
                }
            }

            $amount = (float) ($row->amount ?? 0);
            $quantity = (int) ($row->quantity ?? 1);
            $quantity = $quantity >= 1 ? $quantity : 1;
            $unitPrice = $quantity > 0 ? $amount / $quantity : 0;
            $cogs = $lp * $quantity;
            // PFT = (price * 0.95 - lp) * quantity (margin 0.95, no ship)
            $pft = ($unitPrice * 0.95 - $lp) * $quantity;

            $result[] = [
                'id' => $row->id,
                'order_number' => $row->order_number ?? '',
                'order_date' => $row->order_date ? (\Carbon\Carbon::parse($row->order_date)->format('Y-m-d')) : null,
                'order_paid_at' => $row->order_paid_at ? $row->order_paid_at->format('Y-m-d H:i:s') : null,
                'status' => $row->status ?? '',
                'amount' => round($amount, 2),
                'display_sku' => $row->display_sku ?? '',
                'sku' => $sku,
                'quantity' => $quantity,
                'lp' => round($lp, 2),
                'cogs' => round($cogs, 2),
                'pft' => round($pft, 2),
                'created_at' => $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : null,
            ];
        }

        return response()->json($result);
    }
}
