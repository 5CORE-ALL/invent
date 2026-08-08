<?php

namespace App\Http\Controllers;

use App\Http\Controllers\MarketPlace\AlibabaSyncController;
use App\Http\Controllers\MarketPlace\AliexpressSyncController;
use App\Http\Controllers\MarketPlace\FaireSyncController;
use App\Http\Controllers\MarketPlace\NeweggSyncController;
use App\Http\Controllers\MarketPlace\ReverbSyncController;
use App\Http\Controllers\MarketPlace\SheinSyncController;
use App\Http\Controllers\MarketPlace\AmazonSyncController;
use App\Http\Controllers\MarketPlace\Ebay1SyncController;
use App\Http\Controllers\MarketPlace\Ebay2SyncController;
use App\Http\Controllers\MarketPlace\Ebay3SyncController;
use App\Http\Controllers\MarketPlace\TopDawgSyncController;
use App\Http\Controllers\MarketPlace\PurchasingPowerSyncController;
use App\Http\Controllers\MarketPlace\WayfairSyncController;
use App\Http\Controllers\MarketPlace\BestBuySyncController;
use App\Http\Controllers\MarketPlace\MacySyncController;
use App\Http\Controllers\MarketPlace\DobaSyncController;
use App\Http\Controllers\MarketPlace\TemuSyncController;
use App\Http\Controllers\MarketPlace\Temu2SyncController;
use App\Http\Controllers\MarketPlace\TikTokSyncController;
use App\Http\Controllers\MarketPlace\TikTok2SyncController;
use App\Http\Controllers\MarketPlace\PlsSyncController;
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
    public const SUPPORTED_MARKETPLACES = ['reverb', 'amazon', 'ebay', 'walmart', 'topdawg', 'temu', 'temu2', 'purchasingpower', 'wayfair', 'bestbuy', 'macy', 'doba', 'aliexpress', 'alibaba', 'newegg', 'shein', 'ebay1', 'ebay2', 'ebay3', 'faire', 'tiktok', 'tiktok2', 'pls'];

    protected function getController(string $marketplace): ?object
    {
        return match (strtolower($marketplace)) {
            'reverb' => app(ReverbSyncController::class),
            'topdawg' => app(TopDawgSyncController::class),
            'temu' => app(TemuSyncController::class),
            'temu2' => app(Temu2SyncController::class),
            'purchasingpower' => app(PurchasingPowerSyncController::class),
            'wayfair' => app(WayfairSyncController::class),
            'bestbuy' => app(BestBuySyncController::class),
            'macy' => app(MacySyncController::class),
            'doba' => app(DobaSyncController::class),
            'aliexpress' => app(AliexpressSyncController::class),
            'alibaba' => app(AlibabaSyncController::class),
            'newegg' => app(NeweggSyncController::class),
            'shein' => app(SheinSyncController::class),
            'amazon' => app(AmazonSyncController::class),
            'ebay1' => app(Ebay1SyncController::class),
            'ebay2' => app(Ebay2SyncController::class),
            'ebay3' => app(Ebay3SyncController::class),
            'faire' => app(FaireSyncController::class),
            'tiktok2' => app(TikTok2SyncController::class),
            'tiktok' => app(TikTokSyncController::class),
            'pls' => app(PlsSyncController::class),
            'ebay', 'walmart' => null,
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
        if ($marketplace === 'topdawg') {
            return app(TopDawgSyncController::class)->pullProductFromTopDawg($shopifySku);
        }
        if ($marketplace === 'temu') {
            return app(TemuSyncController::class)->pullProductFromTemu($shopifySku);
        }
        if ($marketplace === 'temu2') {
            return app(Temu2SyncController::class)->pullProductFromTemu($shopifySku);
        }
        if ($marketplace === 'purchasingpower') {
            return app(PurchasingPowerSyncController::class)->pullProductFromPurchasingPower($shopifySku);
        }
        if ($marketplace === 'wayfair') {
            return app(WayfairSyncController::class)->pullProductFromWayfair($shopifySku);
        }
        if ($marketplace === 'bestbuy') {
            return app(BestBuySyncController::class)->pullProductFromBestBuy($shopifySku);
        }
        if ($marketplace === 'macy') {
            return app(MacySyncController::class)->pullProductFromMacy($shopifySku);
        if ($marketplace === 'doba') {
            return app(DobaSyncController::class)->pullProductFromDoba($shopifySku);
        }
        }
        if ($marketplace === 'amazon') {
            return app(AmazonSyncController::class)->pullProductFromAmazon($shopifySku);
        }
        if ($marketplace === 'ebay1') {
            return app(Ebay1SyncController::class)->pullProductFromEbay1($shopifySku);
        }
        if ($marketplace === 'ebay2') {
            return app(Ebay2SyncController::class)->pullProductFromEbay2($shopifySku);
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
        if ($marketplace === 'topdawg') {
            return app(TopDawgSyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'temu') {
            return app(TemuSyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'temu2') {
            return app(Temu2SyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'purchasingpower') {
            return app(PurchasingPowerSyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'wayfair') {
            return app(WayfairSyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'bestbuy') {
            return app(BestBuySyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'macy') {
            return app(MacySyncController::class)->pushProductInventory($shopifySku);
        if ($marketplace === 'doba') {
            return app(DobaSyncController::class)->pushProductInventory($shopifySku);
        }
        }
        if ($marketplace === 'amazon') {
            return app(AmazonSyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'ebay1') {
            return app(Ebay1SyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'ebay2') {
            return app(Ebay2SyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'ebay3') {
            return app(Ebay3SyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'faire') {
            return app(FaireSyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'tiktok') {
            return app(TikTokSyncController::class)->pushProductInventory($shopifySku);
        }
        if ($marketplace === 'tiktok2') {
            return app(TikTok2SyncController::class)->pushProductInventory($shopifySku);
        }

        return response()->json(['success' => false, 'message' => 'Not supported for this marketplace.'], 404);
    }

    public function acceptOrder(Request $request, string $marketplace, int $order): JsonResponse
    {
        $marketplace = strtolower($marketplace);
        if ($marketplace === 'shein') {
            return app(SheinSyncController::class)->acceptOrderOnShein($order);
        }

        return response()->json(['success' => false, 'message' => 'Accept order is not supported for this marketplace.'], 404);
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
        if ($marketplace === 'topdawg') {
            return app(TopDawgSyncController::class)->pullOrderFromTopDawg($order);
        }
        if ($marketplace === 'temu') {
            return app(TemuSyncController::class)->pullOrderFromTemu($order);
        }
        if ($marketplace === 'temu2') {
            return app(Temu2SyncController::class)->pullOrderFromTemu($order);
        }
        if ($marketplace === 'purchasingpower') {
            return app(PurchasingPowerSyncController::class)->pullOrderFromPurchasingPower($order);
        }
        if ($marketplace === 'wayfair') {
            return app(WayfairSyncController::class)->pullOrderFromWayfair($order);
        }
        if ($marketplace === 'bestbuy') {
            return app(BestBuySyncController::class)->pullOrderFromBestBuy($order);
        }
        if ($marketplace === 'macy') {
            return app(MacySyncController::class)->pullOrderFromMacy($order);
        if ($marketplace === 'doba') {
            return app(DobaSyncController::class)->pullOrderFromDoba($order);
        }
        }
        if ($marketplace === 'amazon') {
            return app(AmazonSyncController::class)->pullOrderFromAmazon($order);
        }
        if ($marketplace === 'ebay1') {
            return app(Ebay1SyncController::class)->pullOrderFromEbay1($order);
        }
        if ($marketplace === 'ebay2') {
            return app(Ebay2SyncController::class)->pullOrderFromEbay2($order);
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
        if ($marketplace === 'alibaba') {
            return app(AlibabaSyncController::class)->pushTrackingToAlibaba($order);
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
        if ($marketplace === 'topdawg') {
            return app(TopDawgSyncController::class)->pushTrackingToTopDawg($order);
        }
        if ($marketplace === 'temu') {
            return app(TemuSyncController::class)->pushTrackingToTemu($order);
        }
        if ($marketplace === 'temu2') {
            return app(Temu2SyncController::class)->pushTrackingToTemu($order);
        }
        if ($marketplace === 'purchasingpower') {
            return app(PurchasingPowerSyncController::class)->pushTrackingToPurchasingPower($order);
        }
        if ($marketplace === 'wayfair') {
            return app(WayfairSyncController::class)->pushTrackingToWayfair($order);
        }
        if ($marketplace === 'bestbuy') {
            return app(BestBuySyncController::class)->pushTrackingToBestBuy($order);
        }
        if ($marketplace === 'macy') {
            return app(MacySyncController::class)->pushTrackingToMacy($order);
        if ($marketplace === 'doba') {
            return app(DobaSyncController::class)->pushTrackingToDoba($order);
        }
        }
        if ($marketplace === 'amazon') {
            return app(AmazonSyncController::class)->pushTrackingToAmazon($order);
        }
        if ($marketplace === 'ebay1') {
            return app(Ebay1SyncController::class)->pushTrackingToEbay1($order);
        }
        if ($marketplace === 'ebay2') {
            return app(Ebay2SyncController::class)->pushTrackingToEbay2($order);
        }
        if ($marketplace === 'ebay3') {
            return app(Ebay3SyncController::class)->pushTrackingToEbay3($order);
        }
        if ($marketplace === 'faire') {
            return app(FaireSyncController::class)->pushTrackingToFaire($order);
        }
        if ($marketplace === 'tiktok') {
            return app(TikTokSyncController::class)->pushTrackingToTikTok($order);
        }
        if ($marketplace === 'tiktok2') {
            return app(TikTok2SyncController::class)->pushTrackingToTikTok2($order);
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
        if ($marketplace === 'temu') {
            return app(TemuSyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'temu2') {
            return app(Temu2SyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'purchasingpower') {
            return app(PurchasingPowerSyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'wayfair') {
            return app(WayfairSyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'bestbuy') {
            return app(BestBuySyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'macy') {
            return app(MacySyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'doba') {
            return app(DobaSyncController::class)->saveSettings($request);
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
        if ($marketplace === 'amazon') {
            return app(AmazonSyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'ebay1') {
            return app(Ebay1SyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'ebay2') {
            return app(Ebay2SyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'ebay3') {
            return app(Ebay3SyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'faire') {
            return app(FaireSyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'tiktok2') {
            return app(TikTok2SyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'tiktok') {
            return app(TikTokSyncController::class)->saveSettings($request);
        }
        if ($marketplace === 'pls') {
            return app(PlsSyncController::class)->saveSettings($request);
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
        if (strtolower($marketplace) === 'topdawg') {
            return app(TopDawgSyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'temu') {
            return app(TemuSyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'temu2') {
            return app(Temu2SyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'purchasingpower') {
            return app(PurchasingPowerSyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'wayfair') {
            return app(WayfairSyncController::class)->pushOrderToShopify($request);
        }
        if ($marketplace === 'bestbuy') {
            return app(BestBuySyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'macy') {
            return app(MacySyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'doba') {
            return app(DobaSyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'amazon') {
            return app(AmazonSyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'ebay1') {
            return app(Ebay1SyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'ebay2') {
            return app(Ebay2SyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'ebay3') {
            return app(Ebay3SyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'faire') {
            return app(FaireSyncController::class)->pushOrderToShopify($request);
        }
        if (strtolower($marketplace) === 'tiktok2') {
            $id = (int) $request->input('order_id', $request->input('id', 0));
            if ($id <= 0) {
                return response()->json(['success' => false, 'message' => 'order_id required.'], 422);
            }

            return app(TikTok2SyncController::class)->pushOrderToShopify($request, $id);
        }
        if (strtolower($marketplace) === 'tiktok') {
            $id = (int) $request->input('order_id', $request->input('id', 0));
            if ($id <= 0) {
                return response()->json(['success' => false, 'message' => 'order_id required.'], 422);
            }

            return app(TikTokSyncController::class)->pushOrderToShopify($request, $id);
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
        if (strtolower($marketplace) === 'topdawg') {
            return app(TopDawgSyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'temu') {
            return app(TemuSyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'temu2') {
            return app(Temu2SyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'purchasingpower') {
            return app(PurchasingPowerSyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'wayfair') {
            return app(WayfairSyncController::class)->deleteReadyOrder($request);
        }
        if ($marketplace === 'bestbuy') {
            return app(BestBuySyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'macy') {
            return app(MacySyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'doba') {
            return app(DobaSyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'amazon') {
            return app(AmazonSyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'ebay1') {
            return app(Ebay1SyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'ebay2') {
            return app(Ebay2SyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'ebay3') {
            return app(Ebay3SyncController::class)->deleteReadyOrder($request);
        }
        if (strtolower($marketplace) === 'faire') {
            return app(FaireSyncController::class)->deleteReadyOrder($request);
        }

        return response()->json(['success' => false, 'message' => 'Delete ready order is only available for AliExpress, Alibaba, Reverb, Newegg, Shein, eBay 2, eBay 3, and Faire.'], 404);
    }

    public function markOrderAlreadyImported(Request $request, string $marketplace): JsonResponse
    {
        return match (strtolower($marketplace)) {
            'aliexpress' => app(AliexpressSyncController::class)->markOrderAlreadyImported($request),
            'alibaba' => app(AlibabaSyncController::class)->markOrderAlreadyImported($request),
            'reverb' => app(ReverbSyncController::class)->markOrderAlreadyImported($request),
            'newegg' => app(NeweggSyncController::class)->markOrderAlreadyImported($request),
            'shein' => app(SheinSyncController::class)->markOrderAlreadyImported($request),
            'topdawg' => app(TopDawgSyncController::class)->markOrderAlreadyImported($request),
            'temu' => app(TemuSyncController::class)->markOrderAlreadyImported($request),
            'temu2' => app(Temu2SyncController::class)->markOrderAlreadyImported($request),
            'purchasingpower' => app(PurchasingPowerSyncController::class)->markOrderAlreadyImported($request),
            'wayfair' => app(WayfairSyncController::class)->markOrderAlreadyImported($request),
            'bestbuy' => app(BestBuySyncController::class)->markOrderAlreadyImported($request),
            'macy' => app(MacySyncController::class)->markOrderAlreadyImported($request),
            'doba' => app(DobaSyncController::class)->markOrderAlreadyImported($request),
            'amazon' => app(AmazonSyncController::class)->markOrderAlreadyImported($request),
            'ebay1' => app(Ebay1SyncController::class)->markOrderAlreadyImported($request),
            'ebay2' => app(Ebay2SyncController::class)->markOrderAlreadyImported($request),
            'ebay3' => app(Ebay3SyncController::class)->markOrderAlreadyImported($request),
            'faire' => app(FaireSyncController::class)->markOrderAlreadyImported($request),
            default => response()->json(['success' => false, 'message' => 'Not supported for this marketplace.'], 404),
        };
    }

    public function queueStatus(string $marketplace): JsonResponse
    {
        $marketplace = strtolower($marketplace);
        if (! in_array($marketplace, ['reverb', 'aliexpress', 'alibaba', 'newegg', 'shein', 'amazon', 'topdawg', 'temu', 'temu2', 'purchasingpower', 'wayfair', 'bestbuy', 'macy', 'doba', 'ebay1', 'ebay2', 'ebay3', 'faire', 'tiktok', 'tiktok2'], true)) {
            return response()->json(['success' => false, 'message' => 'Queue status not available for this marketplace.'], 404);
        }

        return response()->json([
            'success' => true,
            'status' => app(MarketplaceManagerQueueStatusService::class)->snapshot($marketplace),
        ]);
    }
}
