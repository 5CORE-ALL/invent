<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
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
}
