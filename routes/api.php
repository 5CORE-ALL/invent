<?php

use App\Http\Controllers\GoogleSheetsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\ProductMaster\ProductMasterController;
use App\Http\Controllers\PurchaseMaster\SupplierRFQController;
use App\Http\Controllers\Channels\ChannelMasterController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/data', [ApiController::class, 'getData']);

Route::post('/data', [ApiController::class, 'storeData']);

// Test route to get Shein 30-day sales data from apicentral.shein_orders
Route::get('/test-shein-sales', function () {
    $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);
    
    $sheinSales = DB::connection('apicentral')
        ->table('shein_orders')
        ->select('seller_sku as sku', DB::raw('COUNT(*) as total_orders'))
        ->where('created_at', '>=', $thirtyDaysAgo)
        ->groupBy('seller_sku')
        ->orderBy('total_orders', 'desc')
        ->get();
    
    return response()->json([
        'success' => true,
        'date_range' => [
            'from' => $thirtyDaysAgo->toDateString(),
            'to' => \Carbon\Carbon::now()->toDateString()
        ],
        'total_skus' => $sheinSales->count(),
        'data' => $sheinSales
    ]);
});

Route::post('/update-amazon-column', [ApiController::class, 'updateAmazonColumn']);
Route::post('/update-amazon-fba-column', [ApiController::class, 'updateAmazonFBAColumn']);
Route::post('/update-ebay-column', [ApiController::class, 'updateEbayColumn']);
Route::post('/update-ebay2-column', [ApiController::class, 'updateEbay2Column']);
Route::post('/update-shopifyB2C-column', [ApiController::class, 'updateShopifyB2CColumn']);
Route::post('/update-macy-column', [ApiController::class, 'updateMacyColumn']);



Route::post('/junglescout', [\App\Http\Controllers\JungleScoutController::class, 'fetchProducts']);

Route::post('/sync-sheets', [GoogleSheetsController::class, 'syncAllSheets']);

Route::get('/sync-inv-l30-to-sheet', [ApiController::class, 'syncInvAndL30ToSheet']);

// Views Pull Data routes
Route::get('/views-pull-data', [ApiController::class, 'getViewsPullData']);
Route::get('/views-pull-data/sync', [ApiController::class, 'fetchAndStoreViewsPullData']);

// Public API - No authentication required
Route::get('/product', [ProductMasterController::class, 'getProductBySku']);

// Supplier open rfq form url
//please dont delete this section 🙏
Route::prefix('rfq-form')->group(function() {
    Route::post('/{slug}/submit', [SupplierRFQController::class, 'submitRfqForm'])->name('rfq-form.submit');
    Route::get('/{slug}', [SupplierRFQController::class, 'showRfqForm'])->name('rfq-form.show');
});

// api for task manager
Route::get('/l30-total-sales', [ChannelMasterController::class, 'getViewChannelData1']);

// TikTok Shop Webhook
// Route::post('/webhooks/tiktok/orders', [App\Http\Controllers\Api\TiktokWebhookController::class, 'handleOrderWebhook']);
// Route::get('/webhooks/tiktok/test', [App\Http\Controllers\Api\TiktokWebhookController::class, 'testWebhook']);

// Reverb Webhook (order.placed, order.shipped, listing.updated)
Route::post('/webhooks/reverb', [App\Http\Controllers\ReverbWebhookController::class, 'handle'])->name('webhooks.reverb');

// Shopify Inventory Webhook - sync updated inventory to Reverb when Shopify fires
Route::post('/webhooks/shopify/inventory-update', [App\Http\Controllers\ShopifyWebhookController::class, 'inventoryUpdate'])->name('webhooks.shopify.inventory-update');
