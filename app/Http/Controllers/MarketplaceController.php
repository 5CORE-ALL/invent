<?php

namespace App\Http\Controllers;

use App\Http\Controllers\MarketPlace\AlibabaSyncController;
use App\Http\Controllers\MarketPlace\AliexpressSyncController;
use App\Http\Controllers\MarketPlace\FaireSyncController;
use App\Http\Controllers\MarketPlace\NeweggSyncController;
use App\Http\Controllers\MarketPlace\ReverbSyncController;
use App\Http\Controllers\MarketPlace\SheinSyncController;
use App\Http\Controllers\MarketPlace\Ebay3SyncController;
use App\Http\Controllers\MarketPlace\TopDawgSyncController;
use App\Services\MarketplaceManager\MarketplaceManagerQueueStatusService;
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
    public const SUPPORTED_MARKETPLACES = ['reverb', 'amazon', 'ebay', 'walmart', 'topdawg', 'aliexpress', 'alibaba', 'newegg', 'shein', 'ebay3', 'faire'];

    protected function getController(string $marketplace): ?object
    {
        return match (strtolower($marketplace)) {
            'reverb' => app(ReverbSyncController::class),
            'topdawg' => app(TopDawgSyncController::class),
            'aliexpress' => app(AliexpressSyncController::class),
            'alibaba' => app(AlibabaSyncController::class),
            'newegg' => app(NeweggSyncController::class),
            'shein' => app(SheinSyncController::class),
            'ebay3' => app(Ebay3SyncController::class),
            'faire' => app(FaireSyncController::class),
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
        if ($marketplace === 'aliexpress') {
            return app(AliexpressSyncController::class)->pullProductFromAliexpress($shopifySku);
        }
        if ($marketplace === 'alibaba') {
            return app(AlibabaSyncController::class)->pullProductFromAlibaba($shopifySku);
        }
        if ($marketplace === 'reverb') {
            return app(ReverbSyncController::class)->pullProductFromReverb($shopifySku);
        }
        if ($marketplace === 'newegg') {
            return app(NeweggSyncController::class)->pullProductFromNewegg($shopifySku);
        }
        if ($marketplace === 'shein') {
            return app(SheinSyncController::class)->pullProductFromShein($shopifySku);
        }
        if ($marketplace === 'ebay3') {
            return app(Ebay3SyncController::class)->pullProductFromEbay3($shopifySku);
        }
        if ($marketplace === 'faire') {
            return app(FaireSyncController::class)->pullProductFromFaire($shopifySku);
        }

        return response()->json(['success' => false, 'message' => 'Not supported for this marketplace.'], 404);
    }

    public function syncProductInventory(Request $request, string $marketplace, int $shopifySku): JsonResponse
    {
        $marketplace = strtolower($marketplace);
        if ($marketplace === 'aliexpress') {
            return app(AliexpressSyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'alibaba') {
            return app(AlibabaSyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'reverb') {
            return app(ReverbSyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'newegg') {
            return app(NeweggSyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'shein') {
            return app(SheinSyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'ebay3') {
            return app(Ebay3SyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'faire') {
            return app(FaireSyncController::class)->pushProductInventory($shopifySku);
        }

        return response()->json(['success' => false, 'message' => 'Not supported for this marketplace.'], 404);
    }

    public function pullOrder(Request $request, string $marketplace, int $order): JsonResponse
    {
        $marketplace = strtolower($marketplace);
        if ($marketplace === 'aliexpress') {
            return app(AliexpressSyncController::class)->pullOrderFromAliexpress($order);
        }
        if ($marketplace === 'alibaba') {
            return app(AlibabaSyncController::class)->pullOrderFromAlibaba($order);
        }
        if ($marketplace === 'reverb') {
            return app(ReverbSyncController::class)->pullOrderFromReverb($order);
        }
        if ($marketplace === 'newegg') {
            return app(NeweggSyncController::class)->pullOrderFromNewegg($order);
        }
        if ($marketplace === 'shein') {
            return app(SheinSyncController::class)->pullOrderFromShein($order);
        }
        if ($marketplace === 'ebay3') {
            return app(Ebay3SyncController::class)->pullOrderFromEbay3($order);
        }
        if ($marketplace === 'faire') {
            return app(FaireSyncController::class)->pullOrderFromFaire($order);
        }

        return response()->json(['success' => false, 'message' => 'Not supported for this marketplace.'], 404);
    }

    public function pushTracking(Request $request, string $marketplace, int $order): JsonResponse
    {
        $marketplace = strtolower($marketplace);
        if ($marketplace === 'aliexpress') {
            return app(AliexpressSyncController::class)->pushTrackingToAliexpress($order);
        }
        if ($marketplace === 'reverb') {
            return app(ReverbSyncController::class)->pushTrackingToReverb($order);
        }
        if ($marketplace === 'newegg') {
            return app(NeweggSyncController::class)->pushTrackingToNewegg($order);
        }
        if ($marketplace === 'shein') {
            return app(SheinSyncController::class)->pushTrackingToShein($order);
        }
        if ($marketplace === 'ebay3') {
            return app(Ebay3SyncController::class)->pushTrackingToEbay3($order);
        }
        if ($marketplace === 'faire') {
            return app(FaireSyncController::class)->pushTrackingToFaire($order);
        }

        return response()->json(['success' => false, 'message' => 'Tracking push not supported for this marketplace.'], 404);
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
        if ($marketplace === 'alibaba') {
            return app(AlibabaSyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'newegg') {
            return app(NeweggSyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'shein') {
            return app(SheinSyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'ebay3') {
            return app(Ebay3SyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'faire') {
            return app(FaireSyncController::class)->saveSettings($request);
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
        if (strtolower($marketplace) === 'alibaba') {
            return app(AlibabaSyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'newegg') {
            return app(NeweggSyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'shein') {
            return app(SheinSyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'ebay3') {
            return app(Ebay3SyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'faire') {
            return app(FaireSyncController::class)->pushOrderToShopify($request);
        }
        return response()->json(['success' => false], 404);
    }

    public function deleteReadyOrder(Request $request, string $marketplace): JsonResponse
    {
        if (strtolower($marketplace) === 'aliexpress') {
            return app(AliexpressSyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'alibaba') {
            return app(AlibabaSyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'reverb') {
            return app(ReverbSyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'newegg') {
            return app(NeweggSyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'shein') {
            return app(SheinSyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'ebay3') {
            return app(Ebay3SyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'faire') {
            return app(FaireSyncController::class)->deleteReadyOrder($request);
        }

        return response()->json(['success' => false, 'message' => 'Delete ready order is only available for AliExpress, Alibaba, Reverb, Newegg, Shein, eBay 3, and Faire.'], 404);
    }

    public function markOrderAlreadyImported(Request $request, string $marketplace): JsonResponse
    {
        return match (strtolower($marketplace)) {
            'aliexpress' => app(AliexpressSyncController::class)->markOrderAlreadyImported($request),
            'alibaba' => app(AlibabaSyncController::class)->markOrderAlreadyImported($request),
            'reverb' => app(ReverbSyncController::class)->markOrderAlreadyImported($request),
            'newegg' => app(NeweggSyncController::class)->markOrderAlreadyImported($request),
            'shein' => app(SheinSyncController::class)->markOrderAlreadyImported($request),
            'ebay3' => app(Ebay3SyncController::class)->markOrderAlreadyImported($request),
            'faire' => app(FaireSyncController::class)->markOrderAlreadyImported($request),
            default => response()->json(['success' => false, 'message' => 'Not supported for this marketplace.'], 404),
        };
    }

    public function queueStatus(string $marketplace): JsonResponse
    {
        $marketplace = strtolower($marketplace);
        if (! in_array($marketplace, ['reverb', 'aliexpress', 'alibaba', 'newegg', 'shein', 'ebay3', 'faire'], true)) {
            return response()->json(['success' => false, 'message' => 'Queue status not available for this marketplace.'], 404);
        }

        return response()->json([
            'success' => true,
            'status' => app(MarketplaceManagerQueueStatusService::class)->snapshot($marketplace),
        ]);
    }
}
