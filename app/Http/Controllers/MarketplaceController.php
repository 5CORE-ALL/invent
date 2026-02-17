<?php

namespace App\Http\Controllers;

use App\Http\Controllers\MarketPlace\ReverbSyncController;
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
    public const SUPPORTED_MARKETPLACES = ['reverb', 'amazon', 'ebay', 'walmart'];

    protected function getController(string $marketplace): ?object
    {
        return match (strtolower($marketplace)) {
            'reverb' => app(ReverbSyncController::class),
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
        if (strtolower($marketplace) !== 'reverb') {
            return response()->json(['success' => false], 404);
        }
        return app(ReverbSyncController::class)->saveSettings($request);
    }

    public function pushOrderToShopify(Request $request, string $marketplace): JsonResponse
    {
        if (strtolower($marketplace) !== 'reverb') {
            return response()->json(['success' => false], 404);
        }
        return app(ReverbSyncController::class)->pushOrderToShopify($request);
    }
}
