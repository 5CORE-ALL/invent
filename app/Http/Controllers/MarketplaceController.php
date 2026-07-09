<?php

namespace App\Http\Controllers;

use App\Http\Controllers\MarketPlace\AliexpressSyncController;
use App\Http\Controllers\MarketPlace\ReverbSyncController;
use App\Http\Controllers\MarketPlace\TopDawgSyncController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Unified controller for Marketplace Sync (Reverb, Amazon, eBay, Walmart, etc.).
 * Loads marketplace-specific behaviour via resolver; add new marketplaces in getController().
 */
class MarketplaceController extends Controller
{
    /** Supported marketplace slugs (lowercase). */
    public const SUPPORTED_MARKETPLACES = ['reverb', 'amazon', 'ebay', 'walmart', 'topdawg', 'aliexpress'];

    protected function getController(string $marketplace): ?object
    {
        return match (strtolower($marketplace)) {
            'reverb' => app(ReverbSyncController::class),
            'topdawg' => app(TopDawgSyncController::class),
            'aliexpress' => app(AliexpressSyncController::class),
            'amazon', 'ebay', 'walmart' => null,
            default => null,
        };
    }

    public function products(Request $request, string $marketplace): View|\Illuminate\Http\RedirectResponse
    {
        $marketplace = strtolower($marketplace);
        if (!in_array($marketplace, self::SUPPORTED_MARKETPLACES, true)) {
            abort(404, 'Marketplace not found');
        }
        $controller = $this->getController($marketplace);
        if ($controller && method_exists($controller, 'syncProducts')) {
            return $controller->syncProducts($request);
        }
        return view('marketplace.sync', [
            'marketplace' => $marketplace,
            'page' => 'products',
            'title' => ucfirst($marketplace) . ' - Products',
        ]);
    }

    public function showProduct(Request $request, string $marketplace, int $shopifySku): View
    {
        $marketplace = strtolower($marketplace);
        if (!in_array($marketplace, self::SUPPORTED_MARKETPLACES, true)) {
            abort(404, 'Marketplace not found');
        }
        $controller = $this->getController($marketplace);
        if ($controller && method_exists($controller, 'showProduct')) {
            return $controller->showProduct($shopifySku);
        }
        abort(404, 'Product detail not available for this marketplace');
    }

    public function pullProduct(Request $request, string $marketplace, int $shopifySku): JsonResponse
    {
        $marketplace = strtolower($marketplace);
        if ($marketplace !== 'aliexpress') {
            return response()->json(['success' => false, 'message' => 'Not supported for this marketplace.'], 404);
        }

        return app(AliexpressSyncController::class)->pullProductFromAliexpress($shopifySku);
    }

    public function pullOrder(Request $request, string $marketplace, int $order): JsonResponse
    {
        $marketplace = strtolower($marketplace);
        if ($marketplace !== 'aliexpress') {
            return response()->json(['success' => false, 'message' => 'Not supported for this marketplace.'], 404);
        }

        return app(AliexpressSyncController::class)->pullOrderFromAliexpress($order);
    }

    public function orders(Request $request, string $marketplace): View
    {
        $marketplace = strtolower($marketplace);
        if (!in_array($marketplace, self::SUPPORTED_MARKETPLACES, true)) {
            abort(404, 'Marketplace not found');
        }
        $controller = $this->getController($marketplace);
        if ($controller && method_exists($controller, 'syncOrders')) {
            return $controller->syncOrders($request);
        }
        return view('marketplace.sync', [
            'marketplace' => $marketplace,
            'page' => 'orders',
            'title' => ucfirst($marketplace) . ' - Orders',
        ]);
    }

    public function showOrder(Request $request, string $marketplace, int $order): View
    {
        $marketplace = strtolower($marketplace);
        if (!in_array($marketplace, self::SUPPORTED_MARKETPLACES, true)) {
            abort(404, 'Marketplace not found');
        }
        $controller = $this->getController($marketplace);
        if ($controller && method_exists($controller, 'showOrder')) {
            return $controller->showOrder($order);
        }
        abort(404, 'Order detail not available for this marketplace');
    }

    public function settings(Request $request, string $marketplace): View
    {
        $marketplace = strtolower($marketplace);
        if (!in_array($marketplace, self::SUPPORTED_MARKETPLACES, true)) {
            abort(404, 'Marketplace not found');
        }
        $controller = $this->getController($marketplace);
        if ($controller && method_exists($controller, 'syncSettings')) {
            return $controller->syncSettings($request);
        }
        return view('marketplace.sync', [
            'marketplace' => $marketplace,
            'page' => 'settings',
            'title' => ucfirst($marketplace) . ' - Settings',
        ]);
    }

    public function saveSettings(Request $request, string $marketplace): JsonResponse
    {
        $marketplace = strtolower($marketplace);
        if ($marketplace === 'reverb') {
            return app(ReverbSyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'topdawg') {
            return app(TopDawgSyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'aliexpress') {
            return app(AliexpressSyncController::class)->saveSettings($request);
        }
        return response()->json(['success' => false], 404);
    }

    public function pushOrderToShopify(Request $request, string $marketplace): JsonResponse
    {
        if (strtolower($marketplace) === 'reverb') {
            return app(ReverbSyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'aliexpress') {
            return app(AliexpressSyncController::class)->pushOrderToShopify($request);
        }
        return response()->json(['success' => false], 404);
    }
}
