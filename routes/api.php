<?php

use App\Http\Controllers\GoogleSheetsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\ProductMaster\ProductMasterController;
use App\Http\Controllers\PricingMaster\PricingMasterViewsController;
use App\Http\Controllers\PurchaseMaster\SupplierRFQController;
use App\Http\Controllers\MarketingMaster\ZeroVisibilityMasterController;
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

// Public API - No authentication required
Route::get('/product', [ProductMasterController::class, 'getProductBySku']);
Route::get('/test-doba-connection', [PricingMasterViewsController::class, 'testDobaConnection']); // Debug route
Route::get('/debug-doba-signature', [PricingMasterViewsController::class, 'debugDobaSignature']); // Signature debug
Route::get('/test-doba-item-validation', [PricingMasterViewsController::class, 'testDobaItemValidation']); // Test item validation
Route::get('/advanced-doba-debug', [PricingMasterViewsController::class, 'advancedDobaDebug']); // Advanced debug with multiple methods
Route::post('/update-doba-price', [PricingMasterViewsController::class, 'pushdobaPriceBySku']); // Doba price update API

// Test route to get Shein API data (single page to avoid rate limiting)
Route::get('/test-shein-api', function () {
    try {
        $endpoint = "/open-api/openapi-business-backend/product/query";
        $timestamp = round(microtime(true) * 1000);
        $random = \Illuminate\Support\Str::random(5);
        
        // Generate signature
        $openKeyId = env('SHEIN_OPEN_KEY_ID');
        $secretKey = env('SHEIN_SECRET_KEY');
        $value = $openKeyId . "&" . $timestamp . "&" . $endpoint;
        $key = $secretKey . $random;
        $hmacResult = hash_hmac('sha256', $value, $key, false);
        $base64Signature = base64_encode($hmacResult);
        $signature = $random . $base64Signature;
        
        $url = 'https://openapi.sheincorp.com' . $endpoint;
        
        // Fetch only first page with 10 items to avoid rate limiting
        $payload = [
            "pageNum" => 1,
            "pageSize" => 10,
            "insertTimeEnd" => "",
            "insertTimeStart" => "",
            "updateTimeEnd" => "",
            "updateTimeStart" => "",
        ];
        
        $response = \Illuminate\Support\Facades\Http::withoutVerifying()
            ->withHeaders([
                "Language" => "en-us",
                "x-lt-openKeyId" => $openKeyId,
                "x-lt-timestamp" => $timestamp,
                "x-lt-signature" => $signature,
                "Content-Type" => "application/json",
            ])
            ->post($url, $payload);
        
        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'error' => 'Shein API Error: ' . $response->body(),
                'status' => $response->status()
            ], $response->status());
        }
        
        $data = $response->json();
        $products = $data["info"]["data"] ?? [];
        
        return response()->json([
            'success' => true,
            'message' => 'Shein API test successful (1 page, 10 items)',
            'total_products' => count($products),
            'api_response' => $data,
            'products' => $products
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
    }
});

// Supplier open rfq form url
//please dont delete this section 🙏
Route::prefix('rfq-form')->group(function() {
    Route::post('/{slug}/submit', [SupplierRFQController::class, 'submitRfqForm'])->name('rfq-form.submit');
    Route::get('/{slug}', [SupplierRFQController::class, 'showRfqForm'])->name('rfq-form.show');
});

// api for task manager
Route::get('/l30-total-sales', [ChannelMasterController::class, 'getViewChannelData1']);

// Channel chart data for live pending trends
Route::get('/channel-chart-data', [ZeroVisibilityMasterController::class, 'getChannelChartData']);
Route::get('/all-channels-chart-data', [ZeroVisibilityMasterController::class, 'getAllChannelsChartData']);
Route::post('/save-channel-action', [ZeroVisibilityMasterController::class, 'saveChannelAction']);
Route::get('/test-channel-data', [ZeroVisibilityMasterController::class, 'testChannelData']);


// TikTok Shop Webhook
// Route::post('/webhooks/tiktok/orders', [App\Http\Controllers\Api\TiktokWebhookController::class, 'handleOrderWebhook']);
// Route::get('/webhooks/tiktok/test', [App\Http\Controllers\Api\TiktokWebhookController::class, 'testWebhook']);