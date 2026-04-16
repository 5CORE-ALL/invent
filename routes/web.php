
<?php

use App\Http\Controllers\AdsMaster\AdsMasterController;
use App\Http\Controllers\AdvertisementMaster\Demand_Gen_parent\GoogleNetworksController;
use App\Http\Controllers\AdvertisementMaster\Headline_Advt\HeadlineAmazonController;
use App\Http\Controllers\AdvertisementMaster\Kw_Advt\KwAmazonController;
use App\Http\Controllers\AdvertisementMaster\Kw_Advt\KwEbayController;
use App\Http\Controllers\AdvertisementMaster\Kw_Advt\WalmartController;
use App\Http\Controllers\AdvertisementMaster\MetaParent\ProductWiseMetaParentController;
use App\Http\Controllers\AdvertisementMaster\Prod_Target_Advt\ProdTargetAmazonController;
use App\Http\Controllers\AdvertisementMaster\Promoted_Advt\PromotedEbayController;
use App\Http\Controllers\AdvertisementMaster\Shopping_Advt\GoogleShoppingController;
use App\Http\Controllers\ArrivedContainerController;
use App\Http\Controllers\ChannelTabulatorColumnController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\AmazonAdsController;
use App\Http\Controllers\AmazonUtilizedKwSheetController;
use App\Http\Controllers\Campaigns\AmazonAdRunningController;
use App\Http\Controllers\Campaigns\AmazonCampaignReportsController;
use App\Http\Controllers\Campaigns\AmazonCPCZeroController;
use App\Http\Controllers\Campaigns\AmazonFbaAcosController;
use App\Http\Controllers\Campaigns\AmazonFbaAdsController;
use App\Http\Controllers\Campaigns\AmazonMissingAdsController;
use App\Http\Controllers\Campaigns\AmazonPinkDilAdController;
use App\Http\Controllers\Campaigns\AmazonSbBudgetController;
use App\Http\Controllers\Campaigns\AmazonSpBudgetController;
use App\Http\Controllers\Campaigns\AmzCorrectlyUtilizedController;
use App\Http\Controllers\Campaigns\AmzUnderUtilizedBgtController;
use App\Http\Controllers\Campaigns\CampaignImportController;
use App\Http\Controllers\Campaigns\Ebay2MissingAdsController;
use App\Http\Controllers\Campaigns\Ebay2PMTAdController;
use App\Http\Controllers\Campaigns\Ebay2RunningAdsController;
use App\Http\Controllers\Campaigns\Ebay2UtilizedAdsController;
use App\Http\Controllers\Campaigns\Ebay3AcosController;
use App\Http\Controllers\Campaigns\Ebay3KeywordAdsController;
use App\Http\Controllers\Campaigns\Ebay3MissingAdsController;
use App\Http\Controllers\Campaigns\Ebay3PinkDilAdController;
use App\Http\Controllers\Campaigns\Ebay3PmtAdsController;
use App\Http\Controllers\Campaigns\Ebay3RunningAdsController;
use App\Http\Controllers\Campaigns\Ebay3UtilizedAdsController;
use App\Http\Controllers\Campaigns\EbayKwAdsController;
use App\Http\Controllers\Campaigns\EbayMissingAdsController;
use App\Http\Controllers\Campaigns\EbayOverUtilizedBgtController;
use App\Http\Controllers\Campaigns\EbayPinkDilAdController;
use App\Http\Controllers\Campaigns\EbayPMPAdsController;
use App\Http\Controllers\Campaigns\EbayRunningAdsController;
use App\Http\Controllers\Campaigns\GoogleAdsController;
use App\Http\Controllers\Campaigns\TiktokAdsController;
use App\Http\Controllers\Campaigns\WalmartMissingAdsController;
use App\Http\Controllers\Campaigns\WalmartRunningAdsController;
use App\Http\Controllers\Campaigns\WalmartUtilisationController;
use App\Http\Controllers\Catalouge\CatalougeManagerController;
use App\Http\Controllers\Channels\AccountHealthMasterController;
use App\Http\Controllers\Channels\AccountHealthMasterDashboardController;
use App\Http\Controllers\Channels\AdsMasterController as ChannelAdsMasterController;
use App\Http\Controllers\Channels\ApprovalsChannelMasterController;
use App\Http\Controllers\Channels\ChannelMasterController;
use App\Http\Controllers\Channels\ChannelMovementAnalysisController;
use App\Http\Controllers\Channels\ChannelPromotionMasterController;
use App\Http\Controllers\Channels\ChannelwiseController;
use App\Http\Controllers\Channels\ExpensesController;
use App\Http\Controllers\Channels\HealthController;
use App\Http\Controllers\Channels\NewMarketplaceController;
use App\Http\Controllers\Channels\OpportunityController;
use App\Http\Controllers\Channels\ReturnController;
use App\Http\Controllers\Channels\SetupAccountChannelController;
use App\Http\Controllers\Channels\ShippingMasterController;
use App\Http\Controllers\Channels\TrafficMasterController;
use App\Http\Controllers\CustomerCare\CustomerFollowupController;
use App\Http\Controllers\CustomerCare\DARController;
use App\Http\Controllers\CustomerCare\ShippingController;
use App\Http\Controllers\EbayDataUpdateController;
use App\Http\Controllers\FacebookAdsController;
use App\Http\Controllers\FBAAnalysticsController;
use App\Http\Controllers\FbaDataController;
use App\Http\Controllers\InventoryManagement\AutoStockBalanceController;
use App\Http\Controllers\InventoryManagement\IncomingController;
use App\Http\Controllers\InventoryManagement\IssueController as SparePartsIssueController;
use App\Http\Controllers\InventoryManagement\OutgoingController;
use App\Http\Controllers\InventoryManagement\RefundController;
use App\Http\Controllers\InventoryManagement\RequisitionController as SparePartsRequisitionController;
use App\Http\Controllers\InventoryManagement\SpareController;
use App\Http\Controllers\InventoryManagement\SparePartPurchaseOrderController;
use App\Http\Controllers\InventoryManagement\StockAdjustmentController;
use App\Http\Controllers\InventoryManagement\StockBalanceController;
use App\Http\Controllers\InventoryManagement\StockTransferController;
use App\Http\Controllers\InventoryManagement\VerificationAdjustmentController;
use App\Http\Controllers\InventoryWarehouseController;
use App\Http\Controllers\ListingMaster\AmzListingController;
use App\Http\Controllers\MarketingMaster\EbayCvrLqsController;
use App\Http\Controllers\MarketingMaster\FacebookAddsManagerController;
use App\Http\Controllers\MarketingMaster\InstagramAdsManagerController;
use App\Http\Controllers\MarketingMaster\MovementPricingMaster;
use App\Http\Controllers\MarketingMaster\ShoppableVideoController;
use App\Http\Controllers\MarketingMaster\TiktokAdsManagerController;
use App\Http\Controllers\MarketingMaster\VideoAdsMasterController;
use App\Http\Controllers\MarketingMaster\VideoPostedController;
use App\Http\Controllers\MarketingMaster\YoutubeAdsManagerController;
use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Http\Controllers\MarketPlace\ACOSControl\EbayACOSController;
use App\Http\Controllers\MarketPlace\AliexpressController;
use App\Http\Controllers\MarketPlace\AmazonFbaInvController;
use App\Http\Controllers\MarketPlace\AmazonFbmTargetingCheckController;
use App\Http\Controllers\MarketPlace\AmazonLowVisibilityController;
use App\Http\Controllers\MarketPlace\AmazonZeroController;
use App\Http\Controllers\MarketPlace\Business5coreController;
use App\Http\Controllers\MarketPlace\CvrMasterController;
use App\Http\Controllers\MarketPlace\DobaController;
use App\Http\Controllers\MarketPlace\Ebay2LowVisibilityController;
use App\Http\Controllers\MarketPlace\Ebay3LowVisibilityController;
use App\Http\Controllers\MarketPlace\EbayController;
use App\Http\Controllers\MarketPlace\EbayLowVisibilityController;
use App\Http\Controllers\MarketPlace\EbayThreeController;
use App\Http\Controllers\MarketPlace\EbayTwoController;
use App\Http\Controllers\MarketPlace\EbayViewsController;
use App\Http\Controllers\MarketPlace\EbayZeroController;
use App\Http\Controllers\MarketPlace\FaireController;
use App\Http\Controllers\MarketPlace\FbmarketplaceController;
use App\Http\Controllers\MarketPlace\FbshopController;
use App\Http\Controllers\MarketPlace\InstagramController;
use App\Http\Controllers\MarketPlace\ListingAuditAmazonController;
use App\Http\Controllers\MarketPlace\ListingAuditEbayController;
use App\Http\Controllers\MarketPlace\ListingAuditMacyController;
use App\Http\Controllers\MarketPlace\ListingAuditNeweggb2cController;
use App\Http\Controllers\MarketPlace\ListingAuditReverbController;
use App\Http\Controllers\MarketPlace\ListingAuditShopifyb2cController;
use App\Http\Controllers\MarketPlace\ListingAuditTemuController;
use App\Http\Controllers\MarketPlace\ListingAuditWayfairController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAliexpressController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAmazonController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAppscenicController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAutoDSController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingBestbuyUSAController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingBusiness5CoreController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingDHGateController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingDobaController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayThreeController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayTwoController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayVariationController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingFaireController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingFBMarketplaceController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingFBShopController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingInstagramShopController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingMacysController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingMercariWoShipController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingMercariWShipController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingNeweggB2BController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingNeweggB2CController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingOfferupController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingPlsController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingPoshmarkController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingReverbController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSheinController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingShopifyB2CController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingShopifyWholesaleController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSpocketController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSWGearExchangeController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSynceeController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTemuController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTiendamiaController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTiktokShopController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingWalmartController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingWayfairController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingYamibuyController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingZendropController;
use App\Http\Controllers\MarketPlace\MacyController;
use App\Http\Controllers\MarketPlace\MacyLowVisibilityController;
use App\Http\Controllers\MarketPlace\MacyZeroController;
use App\Http\Controllers\MarketPlace\MercariWoShipController;
use App\Http\Controllers\MarketPlace\MercariWShipController;
use App\Http\Controllers\MarketPlace\Neweggb2cController;
use App\Http\Controllers\MarketPlace\Neweggb2cLowVisibilityController;
use App\Http\Controllers\MarketPlace\Neweggb2cZeroController;
use App\Http\Controllers\MarketPlace\OverallAmazonController;
use App\Http\Controllers\MarketPlace\OverallAmazonFbaController;
use App\Http\Controllers\MarketPlace\PlsController;
use App\Http\Controllers\MarketPlace\ReverbController;
use App\Http\Controllers\MarketPlace\ReverbLowVisibilityController;
use App\Http\Controllers\MarketPlace\ReverbZeroController;
use App\Http\Controllers\MarketPlace\SheinController;
use App\Http\Controllers\MarketPlace\Shopifyb2cController;
use App\Http\Controllers\MarketPlace\Shopifyb2cLowVisibilityController;
use App\Http\Controllers\MarketPlace\Shopifyb2cZeroController;
use App\Http\Controllers\MarketPlace\TemuController;
use App\Http\Controllers\MarketPlace\TemuLowVisibilityController;
use App\Http\Controllers\MarketPlace\TemuZeroController;
use App\Http\Controllers\MarketPlace\TiendamiaController;
use App\Http\Controllers\MarketPlace\TiktokController;
use App\Http\Controllers\MarketPlace\TiktokShopController;
use App\Http\Controllers\MarketPlace\WalmartControllerMarket;
use App\Http\Controllers\MarketPlace\WayfairController;
use App\Http\Controllers\MarketPlace\WayfairLowVisibilityController;
use App\Http\Controllers\MarketPlace\WayfairZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\AliexpressZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\AppscenicZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\AutoDSZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\BestbuyUSAZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\Business5CoreZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\DHGateZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\DobaZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\Ebay2ZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\Ebay3ZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\EbayVariationZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\FaireZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\FBMarketplaceZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\FBShopZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\InstagramShopZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\MercariWoShipZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\MercariWShipZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\NeweggB2BZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\OfferupZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\PLSZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\PoshmarkZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\SheinZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\ShopifyWholesaleZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\SpocketZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\SWGearExchangeZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\SynceeZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\TiendamiaZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\TiktokShopZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\WalmartZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\YamibuyZeroController;
use App\Http\Controllers\MarketPlace\ZeroViewMarketPlace\ZendropZeroController;
use App\Http\Controllers\ProductMaster\BulletPointMasterController;
use App\Http\Controllers\ProductMaster\CostpriceAnalysisController;
use App\Http\Controllers\ProductMaster\DescriptionMaster2Controller;
use App\Http\Controllers\ProductMaster\DescriptionMasterController;
use App\Http\Controllers\ProductMaster\ForecastAnalysisController;
use App\Http\Controllers\ProductMaster\ImageMasterController;
use App\Http\Controllers\ProductMaster\MovementAnalysisController;
use App\Http\Controllers\ProductMaster\PrAnalysisController;
use App\Http\Controllers\ProductMaster\PricingAnalysisController;
use App\Http\Controllers\ProductMaster\ProductMasterController;
use App\Http\Controllers\ProductMaster\ReturnAnalysisController;
use App\Http\Controllers\ProductMaster\ShortFallAnalysisController;
use App\Http\Controllers\ProductMaster\StockAnalysisController;
use App\Http\Controllers\ProductMaster\ToBeDCController;
use App\Http\Controllers\ProductMaster\ToOrderAnalysisController;
use App\Http\Controllers\PurchaseMaster\CategoryController;
use App\Http\Controllers\PurchaseMaster\ChinaLoadController;
use App\Http\Controllers\PurchaseMaster\ClaimReimbursementController;
use App\Http\Controllers\PurchaseMaster\ContainerPlanningController;
use App\Http\Controllers\PurchaseMaster\InstructionsItemPkgController;
use App\Http\Controllers\PurchaseMaster\LedgerMasterController;
use App\Http\Controllers\PurchaseMaster\MFRGInProgressController;
use App\Http\Controllers\PurchaseMaster\OnRoadTransitController;
use App\Http\Controllers\PurchaseMaster\OnSeaTransitController;
use App\Http\Controllers\PurchaseMaster\PurchaseController;
use App\Http\Controllers\PurchaseMaster\PurchaseOrderController;
use App\Http\Controllers\PurchaseMaster\QcImprovementReqBeforeItemPkgController;
use App\Http\Controllers\PurchaseMaster\QualityEnhanceController;
use App\Http\Controllers\PurchaseMaster\ReadyToShipController;
use App\Http\Controllers\PurchaseMaster\RFQController;
use App\Http\Controllers\PurchaseMaster\SourcingController;
use App\Http\Controllers\PurchaseMaster\SupplierController;
use App\Http\Controllers\PurchaseMaster\TransitContainerDetailsController;
use App\Http\Controllers\PurchaseMaster\UpComingContainerController;
use App\Http\Controllers\ResourcesMasterController;
use App\Http\Controllers\RoutingController;
use App\Http\Controllers\Sales\AmazonSalesController;
use App\Http\Controllers\Sales\AmazonSalesDataTestController;
use App\Http\Controllers\Sales\BestBuySalesController;
use App\Http\Controllers\Sales\DobaSalesController;
use App\Http\Controllers\Sales\EbaySalesController;
use App\Http\Controllers\Sales\MercariController;
use App\Http\Controllers\Sales\WayfairSalesController;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\SkuMatchController;
use App\Http\Controllers\TemuAdsController;
use App\Http\Controllers\UserRRPortfolioController;
use App\Http\Controllers\Warehouse\WarehouseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// =============================================================================
// STEP 1: AI ROUTES (with auth) – must be before any two-segment wildcard
// =============================================================================
Route::prefix('ai')->middleware(['auth'])->group(function () {
    Route::post('/chat', [\App\Http\Controllers\Api\AiChatController::class, 'chat'])->name('ai.chat');
    Route::post('/feedback', [\App\Http\Controllers\Api\AiChatController::class, 'feedback'])->name('ai.feedback');
    Route::post('/upload-knowledge', [\App\Http\Controllers\Api\AiChatController::class, 'uploadKnowledge'])->name('ai.upload');
    Route::get('/check-notifications', [\App\Http\Controllers\Api\AiChatController::class, 'checkNotifications'])->name('ai.check');
    Route::get('/pending-replies', [\App\Http\Controllers\Api\AiChatController::class, 'getPendingReplies'])->name('ai.pending');
    Route::post('/mark-replies-read', [\App\Http\Controllers\Api\AiChatController::class, 'markRepliesRead'])->name('ai.mark-read');
});

// STEP 2: PUBLIC AI ROUTES (no auth)
// Route::get('/ai/download-sample-csv', [App\Http\Controllers\Api\AiChatController::class, 'downloadSampleCsv'])->name('ai.download.sample'); // temporarily disabled

// CRITICAL: Escalation reply routes – no auth so senior can open link from email; must be before any wildcard
Route::get('/ai/escalation/{id}/reply', [App\Http\Controllers\Ai\AiEscalationController::class, 'showReplyForm'])->name('ai.escalation.reply');
Route::post('/ai/escalation/{id}/reply', [App\Http\Controllers\Ai\AiEscalationController::class, 'submitReply'])->name('ai.escalation.submit');

// STEP 3: EMERGENCY TEST ROUTE (temporary) – must be before any Route::get('{first}/{second}')
Route::get('/emergency-escalation/{id}', function ($id) {
    try {
        $escalation = App\Models\AiEscalation::find($id);
        if (! $escalation) {
            return 'Escalation not found! ID: '.$id;
        }

        return 'Found escalation: ID='.$escalation->id.', Status='.$escalation->status.', Question='.substr($escalation->original_question, 0, 100);
    } catch (\Exception $e) {
        return 'Error: '.$e->getMessage();
    }
});

Route::prefix('ai-admin')->middleware(['auth', 'isAdmin'])->name('ai.admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Ai\AiAdminController::class, 'index'])->name('index');
    Route::get('/escalations', [\App\Http\Controllers\Ai\AiAdminController::class, 'escalations'])->name('escalations');
    Route::get('/training', [\App\Http\Controllers\Ai\AiAdminController::class, 'trainingLogs'])->name('training');
    Route::post('/training/{id}/approve', [\App\Http\Controllers\Ai\AiAdminController::class, 'approveTraining'])->name('training.approve');
    Route::get('/files', [\App\Http\Controllers\Ai\AiAdminController::class, 'knowledgeFiles'])->name('files');
});

/** Start Cron Job Routes **/
// Consolidated Cron Job - Runs all cron jobs in sequence (Recommended)
Route::get('/channel/adv/master/cron/all', [ChannelAdsMasterController::class, 'runAllAdvMastersCronJobs'])->name('adv.masters.cron.all');

// Individual Cron Job Routes (for backward compatibility or specific use cases)
Route::get('/channel/adv/master/amazon/cron', [ChannelAdsMasterController::class, 'getChannelAdvMasterAmazonCronData']);
Route::get('/adv/master/missing/amazon/cron', [ChannelAdsMasterController::class, 'getChannelAdvMasterAmazonCronMissingData']);
Route::get('/adv/master/totalsale/amazon/cron', [ChannelAdsMasterController::class, 'getChannelAdvMasterAmazonCronTotalSaleData']);
Route::get('/channel/adv/master/ebay/cron', [ChannelAdsMasterController::class, 'getChannelAdvMasterEbayCronData']);
Route::get('/adv/master/missing/ebay/cron', [ChannelAdsMasterController::class, 'getChannelAdvMasterEbayCronMissingData']);
Route::get('/adv/master/totalsale/ebay/cron', [ChannelAdsMasterController::class, 'getChannelAdvMasterEbayCronTotalSaleData']);
/** End Cron Jobs Routes */
Route::prefix('auth')->group(function () {
    require __DIR__.'/auth.php';
});
Route::get('/auth/logout-page', function () {
    // Prevent access if user is still logged in
    if (Auth::check()) {
        return redirect('/home');
    }

    return view('auth.logout');
})->name('logout.page');

Route::get('/sku-match', [SkuMatchController::class, 'index']);
Route::post('/sku-match', [SkuMatchController::class, 'match'])->name('sku.match.process');
Route::post('/sku-match/update', [SkuMatchController::class, 'update'])->name('sku-match.update');

Route::group(['prefix' => '/', 'middleware' => 'auth'], function () {
    Route::post('/broadcasting/auth', function (Request $request) {
        $user = $request->user();
        $channel = $request->channel_name;
        $socketId = $request->socket_id;

        Log::info('🔐 Broadcasting auth attempt', [
            'user_id' => $user->id ?? null,
            'channel' => $channel,
            'socket_id' => $socketId,
        ]);

        // Validate socket ID format (Pusher format: digits.digits)
        if (! preg_match('/^\d+\.\d+$/', $socketId)) {
            Log::error('❌ Invalid socket ID format', ['socket_id' => $socketId]);

            return response()->json(['error' => 'Invalid socket ID format'], 403);
        }

        try {
            if (str_starts_with($channel, 'private-user.')) {
                $channelUserId = (int) substr($channel, strlen('private-user.'));

                if ($user && $user->id === $channelUserId) {
                    $pusher = new Pusher\Pusher(
                        config('broadcasting.connections.pusher.key'),
                        config('broadcasting.connections.pusher.secret'),
                        config('broadcasting.connections.pusher.app_id'),
                        ['cluster' => config('broadcasting.connections.pusher.options.cluster')]
                    );

                    $auth = $pusher->authorizeChannel($channel, $socketId);

                    Log::info('✅ Auth success', ['user_id' => $user->id]);

                    return response($auth, 200)->header('Content-Type', 'application/json');
                }
            }

            return response()->json(['error' => 'Unauthorized'], 403);

        } catch (\Exception $e) {
            Log::error('❌ Auth failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], 403);
        }
    });
    Route::get('/amazon-summary-data', [OverallAmazonController::class, 'getAmazonDataSummary']);
    Route::get('/ebay-data-view', [EbayController::class, 'getViewEbayData']);
    Route::get('/ebay2-data-view', [EbayTwoController::class, 'getViewEbayData']);
    Route::get('/shopifyB2C-data-view', [Shopifyb2cController::class, 'getViewShopifyB2CData']);
    Route::get('/macy-data-view', [MacyController::class, 'getViewMacyData']);
    Route::get('/macy-pricing-cvr', [MacyController::class, 'macyPricingCvr']);
    Route::get('/macy-pricing-increase-decrease', [MacyController::class, 'macyPricingIncreaseandDecrease']);

    Route::get('/product-master-data-view', [ProductMasterController::class, 'getViewProductData']);
    Route::get('/neweggB2C-data-view', [Neweggb2cController::class, 'getViewNeweggB2CData']);
    Route::get('/pricing-analysis-data-view', [PricingAnalysisController::class, 'getViewPricingAnalysisData']);
    Route::get('/pRoi-analysis-data-view', [PrAnalysisController::class, 'getViewPRoiAnalysisData']);
    Route::get('/return-analysis-data-view', [ReturnAnalysisController::class, 'getViewReturnAnalysisData']);
    Route::get('/stock-analysis-data-view', [StockAnalysisController::class, 'getViewStockAnalysisData']);
    Route::get('/shortfall-analysis-data-view', [ShortFallAnalysisController::class, 'getViewShortFallAnalysisData']);
    Route::get('/costprice-analysis-data-view', [CostpriceAnalysisController::class, 'getViewCostpriceAnalysisData']);
    Route::get('/movement-analysis-data-view', [MovementAnalysisController::class, 'getViewMovementAnalysisData']);
    Route::get('/forecast-analysis-data-view', [ForecastAnalysisController::class, 'getViewForecastAnalysisData']);
    Route::post('/updateForcastSheet', [ForecastAnalysisController::class, 'updateForcastSheet']);
    Route::get('/wayfair-data-view', [WayfairController::class, 'getViewWayfairData']);

    // channel master
    Route::post('/update-executive', [ChannelMasterController::class, 'updateExecutive']);
    Route::post('/update-checkbox', [ChannelMasterController::class, 'sendToGoogleSheet']);
    Route::get('/channels-master-data', [ChannelMasterController::class, 'getViewChannelData']);
    Route::get('/channel-master-history/{channel}', [ChannelMasterController::class, 'getChannelHistory']);
    Route::get('/channel-clicks-breakdown', [ChannelMasterController::class, 'getClicksBreakdown']);
    Route::get('/ad-breakdown-chart-data', [ChannelMasterController::class, 'getAdBreakdownChartData']);
    Route::get('/channel-metric-chart-data', [ChannelMasterController::class, 'getChannelMetricChartData']);
    Route::get('/channel-metric-dot-trends', [ChannelMasterController::class, 'getChannelMetricDotTrends']);
    Route::post('/channel-archive', [ChannelMasterController::class, 'archiveChannel'])->name('channel.archive');
    Route::get('/all-marketplace-master', [ChannelMasterController::class, 'allMarketplaceMaster'])->name('all.marketplace.master');

    // Listing Master > Amz Data (Amazon Listings raw from SP-API)
    Route::get('/listing-master/amz-data', [AmzListingController::class, 'index'])->name('listing.master.amz.data');
    Route::get('/listing-master/amz-data/data', [AmzListingController::class, 'data'])->name('listing.master.amz.data.data');
    Route::get('/listing-master/amz-data/images', [AmzListingController::class, 'images'])->name('listing.master.amz.data.images');
    Route::get('/listing-master/amz-data/media', [AmzListingController::class, 'media'])->name('listing.master.amz.data.media');
    Route::post('/listing-master/amz-data/import', [AmzListingController::class, 'import'])->name('listing.master.amz.data.import');
    Route::get('/listing-master/amz-data/import-debug', [AmzListingController::class, 'importDebug'])->name('listing.master.amz.data.import.debug');
    Route::post('/listing-master/amz-data/enrich-single', [AmzListingController::class, 'enrichSingle'])->name('listing.master.amz.data.enrich-single');
    Route::post('/listing-master/amz-data/extract-titles', [AmzListingController::class, 'extractTitlesToTitleMaster'])->name('listing.master.amz.data.extract-titles');
    Route::get('/listing-master/amz-data/analyze', [AmzListingController::class, 'analyzeAmazonData'])->name('listing.master.amz.data.analyze');
    Route::get('/listing-master/amz-data/debug/{sku}', [AmzListingController::class, 'debugSku'])->name('listing.master.amz.data.debug');
    Route::get('/listing-master/amz-data/check-raw/{sku}', [AmzListingController::class, 'checkRawData'])->name('listing.master.amz.data.check-raw');

    // Marketplace Sync: dynamic routes per marketplace (reverb, amazon, ebay, walmart)
    Route::prefix('marketplace/{marketplace}')->where(['marketplace' => 'reverb|amazon|ebay|walmart|topdawg'])->group(function () {
        Route::get('/products', [\App\Http\Controllers\MarketplaceController::class, 'products'])->name('marketplace.products');
        Route::get('/orders', [\App\Http\Controllers\MarketplaceController::class, 'orders'])->name('marketplace.orders');
        Route::get('/settings', [\App\Http\Controllers\MarketplaceController::class, 'settings'])->name('marketplace.settings');
        Route::post('/settings', [\App\Http\Controllers\MarketplaceController::class, 'saveSettings'])->name('marketplace.settings.save');
        Route::post('/orders/push', [\App\Http\Controllers\MarketplaceController::class, 'pushOrderToShopify'])->name('marketplace.orders.push');
    });

    // TopDawg Sales Dashboard (topdawg_order_metrics, margin 0.95, no ship)
    Route::get('/topdawg/sales-dashboard', [\App\Http\Controllers\MarketPlace\TopDawgSyncController::class, 'salesDashboard'])->name('topdawg.sales.dashboard');
    Route::get('/topdawg/sales-data', [\App\Http\Controllers\MarketPlace\TopDawgSyncController::class, 'getSalesData'])->name('topdawg.sales.data');

    // Route::get('/get-channel-sales-data', [ChannelMasterController::class, 'getChannelSalesData']);
    Route::get('/sales-trend-data', [ChannelMasterController::class, 'getSalesTrendData']);
    Route::get('/dashboard-metrics', [ChannelMasterController::class, 'getDashboardMetrics']);

    // Channel Ads Master
    Route::get('/channel/ads/master', [ChannelAdsMasterController::class, 'channelAdsMaster'])->name('channel.ads.master');
    Route::get('/channel/ads/data', [ChannelAdsMasterController::class, 'getAdsMasterData'])->name('channel.ads.data');
    Route::get('/channel/adv/master', [ChannelAdsMasterController::class, 'channelAdvMaster'])->name('channel.adv.master');
    Route::get('/amazon/adv/chart/data', [ChannelAdsMasterController::class, 'getAmazonAdvChartData'])->name('amazon.adv.chart.data');
    Route::get('/ebay/adv/chart/data', [ChannelAdsMasterController::class, 'getEbayAdvChartData'])->name('ebay.adv.chart.data');
    Route::get('/channel/adv/chart/data', [ChannelAdsMasterController::class, 'getChannelAdvChartData'])->name('channel.adv.chart.data');
    Route::get('/channel/adv/clicks/chart/data', [ChannelAdsMasterController::class, 'getChannelClicksChartData'])->name('channel.adv.clicks.chart.data');
    Route::get('/channel/adv/spend/chart/data', [ChannelAdsMasterController::class, 'getChannelSpendChartData'])->name('channel.adv.spend.chart.data');
    Route::get('/channel/adv/adsales/chart/data', [ChannelAdsMasterController::class, 'getChannelAdSalesChartData'])->name('channel.adv.adsales.chart.data');
    Route::get('/channel/adv/acos/chart/data', [ChannelAdsMasterController::class, 'getChannelAcosChartData'])->name('channel.adv.acos.chart.data');

    // Account Health Master
    // Route::get('/channel/account-health-test', [AccountHealthMasterController::class, 'test'])->name('account.health.master');
    Route::controller(AccountHealthMasterController::class)->group(function () {
        Route::get('/account-health-master/odr-rate', 'odrRateIndex')->name('odr.rate');
        Route::post('/odr-rate-save', 'saveOdrRate')->name('odr.rate.save');
        Route::get('/fetchOdrRates', 'fetchOdrRates');
        Route::post('/odr-rate/update', 'updateOdrRate');
        Route::post('/odr-health-link/update', 'updateOdrHealthLink')->name('odr-health.link.update');

        Route::get('/account-health-master/fullfillment-rate', 'fullfillmentRateIndex')->name('fullfillment.rate');
        Route::post('/fullfillment-rate-save', 'saveFullfillmentRate')->name('fullfillment.rate.save');
        Route::get('/fetchFullfillmentRates', 'fetchFullfillmentRates');
        Route::post('/fullfillment-rate/update', 'updateFullfillmentRate');
        Route::post('/fullfillment-health-link/update', 'updateFullfillmentHealthLink')->name('fullfillment-health.link.update');

        Route::get('/account-health-master/valid-tracking-rate', 'validTrackingRateIndex')->name('valid.tracking.rate');
        Route::post('/validtracking-rate-save', 'saveValidTrackingRate')->name('validtracking.rate.save');
        Route::get('/fetchValidTrackingRates', 'fetchValidTrackingRates');
        Route::post('/validtracking-rate/update', 'updateValidTrackingRate');
        Route::post('/validtracking-health-link/update', 'updateValidTrackingHealthLink')->name('validtracking-health.link.update');

        Route::get('/account-health-master/late-shipment', 'lateShipmentRateIndex')->name('late.shipment.rate');
        Route::post('/lateshipment-rate-save', 'saveLateShipmentRate')->name('lateshipment.rate.save');
        Route::get('/fetchLateShipmentRates', 'fetchLateShipmentRates');
        Route::post('/lateshipment-rate/update', 'updateLateShipmentRate');
        Route::post('/lateshipment-health-link/update', 'updateLateShipmentHealthLink')->name('lateshipment-health.link.update');

        Route::get('/account-health-master/on-time-delivery', 'onTimeDeliveryIndex')->name('on.time.delivery.rate');
        Route::post('/onTimeDelivery-rate-save', 'saveOnTimeDeliveryRate')->name('onTimeDelivery.rate.save');
        Route::get('/fetchOnTimeDeliveryRates', 'fetchOnTimeDeliveryRates');
        Route::post('/onTimeDelivery-rate/update', 'updateOnTimeDeliveryRate');
        Route::post('/onTimeDelivery-health-link/update', 'updateOnTimeDeliveryHealthLink')->name('onTimeDelivery-health.link.update');

        Route::get('/account-health-master/negative-seller', 'negativeSellerIndex')->name('negative.seller.rate');
        Route::post('/negativeSeller-rate-save', 'saveNegativeSellerRate')->name('negativeSeller.rate.save');
        Route::get('/fetchNegativeSellerRates', 'fetchNegativeSellerRates');
        Route::post('/negativeSeller-rate/update', 'updateNegativeSellerRate');
        Route::post('/negativeSeller-health-link/update', 'updateNegativeSellerHealthLink')->name('negativeSeller-health.link.update');

        Route::get('/account-health-master/a-z-claims', 'aTozClaimsIndex')->name('a_z.claims.rate');
        Route::post('/AtoZClaims-rate-save', 'saveAtoZClaimsRate')->name('AtoZClaims.rate.save');
        Route::get('/fetchAtoZClaimsRates', 'fetchAtoZClaimsRates');
        Route::post('/AtoZClaims-rate/update', 'updateAtoZClaimsRate');
        Route::post('/AtoZClaims-health-link/update', 'updateAtoZClaimsHealthLink')->name('AtoZClaims-health.link.update');

        Route::get('/account-health-master/voilation-compliance', 'voilationIndex')->name('voilation.rate');
        Route::post('/voilance-rate-save', 'saveVoilanceRate')->name('voilance.rate.save');
        Route::get('/fetchVoilanceRates', 'fetchVoilanceRates');
        Route::post('/voilance-rate/update', 'updateVoilanceRate');
        Route::post('/voilance-health-link/update', 'updateVoilanceHealthLink')->name('voilance-health.link.update');

        Route::get('/account-health-master/refund-return', 'refundIndex')->name('refund.rate');
        Route::post('/refund-rate-save', 'saveRefundRate')->name('refund.rate.save');
        Route::get('/fetchRefundRates', 'fetchRefundRates');
        Route::post('/refund-rate/update', 'updateRefundRate');
        Route::post('/refund-health-link/update', 'updateRefundHealthLink')->name('refund-health.link.update');
    });

    // Account Health Master Channel Dashboard
    Route::get('/channel/dashboard', [AccountHealthMasterDashboardController::class, 'dashboard'])->name('account.health.master.channel.dashboard');
    Route::get('/account-health-master/dashboard-data', [AccountHealthMasterDashboardController::class, 'getMasterChannelDataHealthDashboard'])->name('account.health.master.dashboard.data');
    Route::get('/account-health-master-data', [AccountHealthMasterDashboardController::class, 'getMasterChannelDataHealthDashboard'])->name('account.health.master.data');
    Route::get('/account-health-master/export', [AccountHealthMasterDashboardController::class, 'export'])->name('account-health-master.export');
    Route::post('/account-health-master/import', [AccountHealthMasterDashboardController::class, 'import'])->name('account-health-master.import');
    Route::get('/account-health-master/sample/{type?}', [AccountHealthMasterDashboardController::class, 'downloadSample'])->name('account-health-master.sample');

    Route::controller(OpportunityController::class)->group(function () {
        Route::get('/channerl-master-active/opportunity', 'index')->name('opportunity.index');
        Route::get('/opportunities/data', 'getOpportunitiesData');
        Route::post('/opportunities/save', 'saveOpportunity');
        Route::post('/import-opportunities', 'importOpportunities')->name('import.opportunities');
        Route::get('/opportunities/export', 'exportOpportunities')->name('opportunities.export');
        Route::post('/opportunities/delete', 'deleteOpportunities')->name('opportunities.export');
    });
    Route::controller(ApprovalsChannelMasterController::class)->group(function () {
        Route::get('/channerl-master-active/application-and-approvals', 'index')->name('application.approvals.index');
        Route::get('/approvals/data', 'fetchApprovalsData');
        Route::post('/approvals-channel-master/save', 'saveApprovalsData');
        Route::post('/approvals/delete', 'deleteApprovals')->name('approvals.export');
    });
    Route::controller(SetupAccountChannelController::class)->group(function () {
        Route::get('/channerl-master-active/setup-account-and-shop', 'index')->name('setup.account.index');
        Route::get('/setup-account/fetch-data', 'fetchSetupAccountData');
        Route::post('/setup-account-channel-master/save', 'saveSetupAccountData');
    });

    // Shipping Master
    Route::controller(ShippingMasterController::class)->group(function () {
        Route::get('/shipping-master/list', 'index')->name('shipping.master.list');
        Route::get('/fetch-shipping-rate/data', 'fetchShippingRate');
        Route::post('/update-shipping-rate', 'storeOrUpdateShippingRate');
    });

    Route::controller(TrafficMasterController::class)->group(function () {
        Route::get('/traffic-master/list', 'index')->name('traffic.master.list');
        Route::get('/fetch-traffic-rate/data', 'fetchTraficReport');
    });

    Route::get('/channel/account-health-master', [AccountHealthMasterController::class, 'index'])->name('account.health.master');
    Route::post('/channel/account-health-master/store', [AccountHealthMasterController::class, 'store'])->name('account.health.store');
    Route::post('/account-health/link/update', [AccountHealthMasterController::class, 'updateLink'])->name('account.health.link.update');
    Route::post('/account-health/update', [AccountHealthMasterController::class, 'update']);

    // verification & Adjustment
    Route::get('/verification-adjustment-data-view', [VerificationAdjustmentController::class, 'getViewVerificationAdjustmentData']);
    Route::get('/verification-adjustment-view', [VerificationAdjustmentController::class, 'index'])->name('verify-adjust');
    Route::get('/lost-gain', [VerificationAdjustmentController::class, 'lostGain'])->name('lost-gain');
    Route::post('/lost-gain-product-data', [VerificationAdjustmentController::class, 'getLostGainProductData']);
    Route::post('/lost-gain-update-ia', [VerificationAdjustmentController::class, 'updateIAStatus']);
    Route::post('/lost-gain-adjust-quantity', [VerificationAdjustmentController::class, 'adjustLostGainQuantities']);
    Route::get('/lost-gain-aq-history', [VerificationAdjustmentController::class, 'getLostGainAqHistory']);
    Route::post('/update-verified-stock', [VerificationAdjustmentController::class, 'updateVerifiedStock']);
    Route::post('/retry-verification-shopify-adjustment', [VerificationAdjustmentController::class, 'retryVerificationShopifyAdjustment']);
    Route::post('/save-remark', [VerificationAdjustmentController::class, 'saveRemark']);
    Route::get('/get-verified-stock', [VerificationAdjustmentController::class, 'getVerifiedStock']);
    Route::post('/update-to-adjust', [ShopifyController::class, 'updateToAdjust']);
    Route::post('/update-approved-by-ih', [VerificationAdjustmentController::class, 'updateApprovedByIH']);
    Route::post('/update-ra-status', [VerificationAdjustmentController::class, 'updateRAStatus']);
    Route::post('/update-verified-status', [VerificationAdjustmentController::class, 'updateVerifiedStatus']);
    Route::post('/update-doubtful-status', [VerificationAdjustmentController::class, 'updateDoubtfulStatus']);
    Route::get('/verified-stock-activity-log', [VerificationAdjustmentController::class, 'getVerifiedStockActivityLog']);
    Route::get('/view-inventory-data', [VerificationAdjustmentController::class, 'viewInventory'])->name('view-inventory-data');
    Route::get('/inventory-history', [VerificationAdjustmentController::class, 'getSkuWiseHistory']);
    Route::get('/shopify-inventory-history-url', [VerificationAdjustmentController::class, 'getShopifyInventoryHistoryUrl']);
    Route::post('/row-hide-toggle', [VerificationAdjustmentController::class, 'toggleHide']);
    Route::get('/get-hidden-rows', [VerificationAdjustmentController::class, 'getHiddenRows']);
    Route::post('/unhide-multiple-rows', [VerificationAdjustmentController::class, 'unhideMultipleRows']);
    // Shopify inventory update management
    Route::post('/shopify-update-retry', [VerificationAdjustmentController::class, 'retryShopifyUpdate']);
    Route::get('/shopify-update-status', [VerificationAdjustmentController::class, 'getShopifyUpdateStatus']);
    Route::get('/shopify-updates-pending', [VerificationAdjustmentController::class, 'getPendingShopifyUpdates']);
    // Google Sheets export
    Route::post('/export-to-google-sheets', [VerificationAdjustmentController::class, 'exportToGoogleSheets']);

    Route::get('/customer-care', function () {
        return view('customer-care.index');
    })->name('customer.care');
    Route::get('/customer-care/shipping', [ShippingController::class, 'index'])->name('customer.care.shipping');
    Route::get('/customer-care/shipping/overview', [ShippingController::class, 'overview'])->name('customer.care.shipping.overview');
    Route::get('/customer-care/shipping/report-state', [ShippingController::class, 'reportState'])->name('customer.care.shipping.report.state');
    Route::post('/customer-care/shipping/report-line', [ShippingController::class, 'reportSave'])->name('customer.care.shipping.report.save');
    Route::delete('/customer-care/shipping/report-issues/{shipping_report_issue}', [ShippingController::class, 'reportIssueDestroy'])->name('customer.care.shipping.report.issue.destroy');
    Route::get('/customer-care/shipping/platforms', [ShippingController::class, 'platformsList'])->name('customer.care.shipping.platforms');
    Route::get('/customer-care/shipping/cleared', [ShippingController::class, 'clearedList'])->name('customer.care.shipping.cleared.list');
    Route::post('/customer-care/shipping/cleared/{shipping_followup_archive}/restore', [ShippingController::class, 'clearedRestore'])->name('customer.care.shipping.cleared.restore');
    Route::get('/customer-care/shipping/followups', [ShippingController::class, 'followupsList'])->name('customer.care.shipping.followups.list');
    Route::post('/customer-care/shipping/followups', [ShippingController::class, 'followupsStore'])->name('customer.care.shipping.followups.store');
    Route::post('/customer-care/shipping/followups/{shipping_followup}/resolve', [ShippingController::class, 'followupsResolve'])->name('customer.care.shipping.followups.resolve');
    Route::get('/customer-care/refunds', [RefundController::class, 'index'])->name('customer.care.refunds');
    Route::get('/customer-care/orders-on-hold', function () {
        $marketplaces = \Illuminate\Support\Facades\DB::table('marketplace_percentages')
            ->whereNotNull('marketplace')
            ->where('marketplace', '!=', '')
            ->orderBy('marketplace')
            ->pluck('marketplace')
            ->map(fn ($m) => trim((string) $m))
            ->filter()
            ->unique()
            ->values();

        return view('customer-care.orders_on_hold', compact('marketplaces'));
    })->name('customer.care.orders.on.hold');
    Route::get('/customer-care/orders-on-hold/sku-details', function (\Illuminate\Http\Request $request) {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['found' => false, 'message' => 'SKU is required.'], 422);
        }

        $row = \Illuminate\Support\Facades\DB::table('product_master as pm')
            ->selectRaw('pm.sku, pm.parent, pm.Values as values_json, pm.main_image, pm.image1')
            ->whereRaw('LOWER(TRIM(pm.sku)) = ?', [strtolower($sku)])
            ->first();

        if (! $row) {
            return response()->json(['found' => false, 'message' => 'SKU not found.']);
        }

        $shopify = \Illuminate\Support\Facades\DB::table('shopify_skus')
            ->selectRaw('COALESCE(inv, 0) as qty, image_src')
            ->whereRaw('LOWER(TRIM(sku)) = ?', [strtolower((string) $row->sku)])
            ->first();

        $normalizeImage = static function ($path) {
            $p = trim((string) ($path ?? ''));
            if ($p === '') {
                return null;
            }
            if (preg_match('/^(https?:)?\/\//i', $p) || str_starts_with($p, 'data:')) {
                return $p;
            }

            return '/'.ltrim($p, '/');
        };

        $values = [];
        if (isset($row->values_json) && is_string($row->values_json) && trim($row->values_json) !== '') {
            $decoded = json_decode($row->values_json, true);
            if (is_array($decoded)) {
                $values = $decoded;
            }
        }

        $imageUrl = $normalizeImage($shopify?->image_src)
            ?? $normalizeImage($values['image_path'] ?? null)
            ?? $normalizeImage($row->main_image ?? null)
            ?? $normalizeImage($row->image1 ?? null);

        return response()->json([
            'found' => true,
            'sku' => $row->sku,
            'parent' => $row->parent,
            'qty' => (float) ($shopify?->qty ?? 0),
            'image_url' => $imageUrl,
        ]);
    })->name('customer.care.orders.on.hold.sku.details');
    Route::get('/customer-care/orders-on-hold/issues', function () {
        $rows = \Illuminate\Support\Facades\DB::table('orders_on_hold_issues')
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->orderByDesc('id')
            ->limit(1000)
            ->get([
                'id',
                'sku',
                'qty',
                'order_qty',
                'parent',
                'marketplace_1',
                'marketplace_2',
                'what_happened',
                'issue',
                'issue_remark',
                'action_1',
                'action_1_remark',
                'replacement_tracking',
                'c_action_1',
                'c_action_1_remark',
                'close_note',
                'created_by',
                'created_at',
            ]);

        $tz = config('app.timezone');
        $data = $rows->map(function ($row) use ($tz) {
            return [
                'id' => (int) $row->id,
                'sku' => $row->sku,
                'qty' => (float) $row->qty,
                'order_qty' => $row->order_qty !== null ? (float) $row->order_qty : null,
                'parent' => $row->parent,
                'marketplace_1' => $row->marketplace_1,
                'marketplace_2' => $row->marketplace_2,
                'what_happened' => $row->what_happened,
                'issue' => $row->issue,
                'issue_remark' => $row->issue_remark,
                'action_1' => $row->action_1,
                'action_1_remark' => $row->action_1_remark,
                'replacement_tracking' => $row->replacement_tracking,
                'c_action_1' => $row->c_action_1,
                'c_action_1_remark' => $row->c_action_1_remark,
                'close_note' => $row->close_note,
                'created_by' => $row->created_by,
                'created_at' => $row->created_at,
                'created_at_display' => $row->created_at
                    ? \Carbon\Carbon::parse($row->created_at)->timezone($tz)->format('d-m-Y H:i')
                    : '',
            ];
        })->values();

        return response()->json(['data' => $data]);
    })->name('customer.care.orders.on.hold.issues.index');
    Route::get('/customer-care/orders-on-hold/history', function () {
        $rows = \Illuminate\Support\Facades\DB::table('orders_on_hold_issue_histories')
            ->orderByDesc('id')
            ->limit(1000)
            ->get([
                'id',
                'orders_on_hold_issue_id',
                'event_type',
                'revision_no',
                'sku',
                'qty',
                'order_qty',
                'parent',
                'marketplace_1',
                'marketplace_2',
                'what_happened',
                'issue',
                'issue_remark',
                'action_1',
                'action_1_remark',
                'replacement_tracking',
                'c_action_1',
                'c_action_1_remark',
                'close_note',
                'created_by',
                'logged_at',
            ]);

        $tz = config('app.timezone');
        $data = $rows->map(function ($row) use ($tz) {
            return [
                'id' => (int) $row->id,
                'orders_on_hold_issue_id' => $row->orders_on_hold_issue_id ? (int) $row->orders_on_hold_issue_id : null,
                'event_type' => $row->event_type,
                'revision_no' => $row->revision_no !== null ? (int) $row->revision_no : null,
                'issue_ref' => ($row->orders_on_hold_issue_id
                    ? (
                        ((int) ($row->revision_no ?? 0) > 0)
                            ? ((string) $row->orders_on_hold_issue_id.'.'.(string) ((int) $row->revision_no))
                            : (string) $row->orders_on_hold_issue_id
                    )
                    : null),
                'sku' => $row->sku,
                'qty' => (float) $row->qty,
                'order_qty' => $row->order_qty !== null ? (float) $row->order_qty : null,
                'parent' => $row->parent,
                'marketplace_1' => $row->marketplace_1,
                'marketplace_2' => $row->marketplace_2,
                'what_happened' => $row->what_happened,
                'issue' => $row->issue,
                'issue_remark' => $row->issue_remark,
                'action_1' => $row->action_1,
                'action_1_remark' => $row->action_1_remark,
                'replacement_tracking' => $row->replacement_tracking,
                'c_action_1' => $row->c_action_1,
                'c_action_1_remark' => $row->c_action_1_remark,
                'close_note' => $row->close_note,
                'created_by' => $row->created_by,
                'logged_at' => $row->logged_at,
                'logged_at_display' => $row->logged_at
                    ? \Carbon\Carbon::parse($row->logged_at)->timezone($tz)->format('d-m-Y H:i')
                    : '',
            ];
        })->values();

        return response()->json(['data' => $data]);
    })->name('customer.care.orders.on.hold.history.index');
    Route::post('/customer-care/orders-on-hold/issues', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'sku' => 'required|string|max:128',
            'qty' => 'required|numeric|min:0',
            'order_qty' => 'nullable|numeric|min:0',
            'parent' => 'nullable|string|max:255',
            'marketplace_1' => 'nullable|string|max:255',
            'marketplace_2' => 'nullable|string|max:255',
            'what_happened' => 'nullable|string|max:50',
            'issue' => 'required|string|max:255',
            'issue_remark' => 'nullable|string|max:255',
            'action_1' => 'nullable|string|in:Offer Customer Alterntive / Updgrade,Upgraded + Stock Alternate,Alternate Sent + Stock Alternate,Sent Wrong Item + Stock Outgoing,Cancelled,Other|max:255',
            'action_1_remark' => 'nullable|string|max:255',
            'replacement_tracking' => 'nullable|string|max:50',
            'c_action_1' => 'nullable|string|max:255',
            'c_action_1_remark' => 'nullable|string|max:255',
            'close_note' => 'nullable|string|max:255',
        ]);
        $allowedRootCauses = [
            'Mapping',
            'Replacement Issued But not Entered',
            'FBA stock Issued But not Entered',
            'Alternate Issued But not Entered',
            'Stock Balance not Entered',
            'Reserve Stock Issue',
            'Other',
        ];
        if (! in_array((string) ($validated['issue'] ?? ''), $allowedRootCauses, true)) {
            return response()->json([
                'message' => 'Invalid Root Cause Found selection.',
                'errors' => ['issue' => ['Invalid Root Cause Found selection.']],
            ], 422);
        }
        if (($validated['issue'] ?? null) === 'Other' && trim((string) ($validated['issue_remark'] ?? '')) === '') {
            return response()->json([
                'message' => 'Root Cause Found remark is required when Other is selected.',
                'errors' => ['issue_remark' => ['Root Cause Found remark is required when Other is selected.']],
            ], 422);
        }
        if (($validated['action_1'] ?? null) === 'Other' && trim((string) ($validated['action_1_remark'] ?? '')) === '') {
            return response()->json([
                'message' => 'Action remark is required when Action is Other.',
                'errors' => ['action_1_remark' => ['Action remark is required when Action is Other.']],
            ], 422);
        }
        if (($validated['c_action_1'] ?? null) === 'Other' && trim((string) ($validated['c_action_1_remark'] ?? '')) === '') {
            return response()->json([
                'message' => 'Root Cause Fixed remark is required when Root Cause Fixed is Other.',
                'errors' => ['c_action_1_remark' => ['Root Cause Fixed remark is required when Root Cause Fixed is Other.']],
            ], 422);
        }

        $user = auth()->user();
        $createdBy = trim((string) ($user?->name ?? 'System'));
        if ($createdBy === '') {
            $createdBy = 'System';
        }

        $id = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $createdBy, $user) {
            $now = now();

            $payload = [
                'sku' => trim($validated['sku']),
                'qty' => (float) $validated['qty'],
                'order_qty' => isset($validated['order_qty']) ? (float) $validated['order_qty'] : null,
                'parent' => isset($validated['parent']) ? trim((string) $validated['parent']) : null,
                'marketplace_1' => isset($validated['marketplace_1']) ? trim((string) $validated['marketplace_1']) : null,
                'marketplace_2' => isset($validated['marketplace_2']) ? trim((string) $validated['marketplace_2']) : null,
                'what_happened' => isset($validated['what_happened']) ? trim((string) $validated['what_happened']) : null,
                'issue' => trim($validated['issue']),
                'issue_remark' => isset($validated['issue_remark']) ? trim((string) $validated['issue_remark']) : null,
                'action_1' => isset($validated['action_1']) ? trim((string) $validated['action_1']) : null,
                'action_1_remark' => isset($validated['action_1_remark']) ? trim((string) $validated['action_1_remark']) : null,
                'replacement_tracking' => isset($validated['replacement_tracking']) ? trim((string) $validated['replacement_tracking']) : null,
                'c_action_1' => isset($validated['c_action_1']) ? trim((string) $validated['c_action_1']) : null,
                'c_action_1_remark' => isset($validated['c_action_1_remark']) ? trim((string) $validated['c_action_1_remark']) : null,
                'close_note' => isset($validated['close_note']) ? trim((string) $validated['close_note']) : null,
                'created_by' => $createdBy,
                'created_by_user_id' => $user?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $issueId = \Illuminate\Support\Facades\DB::table('orders_on_hold_issues')->insertGetId($payload);

            \Illuminate\Support\Facades\DB::table('orders_on_hold_issue_histories')->insert([
                'orders_on_hold_issue_id' => $issueId,
                'event_type' => 'created',
                'revision_no' => 0,
                'sku' => $payload['sku'],
                'qty' => $payload['qty'],
                'order_qty' => $payload['order_qty'],
                'parent' => $payload['parent'],
                'marketplace_1' => $payload['marketplace_1'],
                'marketplace_2' => $payload['marketplace_2'],
                'what_happened' => $payload['what_happened'],
                'issue' => $payload['issue'],
                'issue_remark' => $payload['issue_remark'],
                'action_1' => $payload['action_1'],
                'action_1_remark' => $payload['action_1_remark'],
                'replacement_tracking' => $payload['replacement_tracking'],
                'c_action_1' => $payload['c_action_1'],
                'c_action_1_remark' => $payload['c_action_1_remark'],
                'close_note' => $payload['close_note'],
                'created_by' => $createdBy,
                'created_by_user_id' => $user?->id,
                'logged_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $issueId;
        });

        $row = \Illuminate\Support\Facades\DB::table('orders_on_hold_issues')
            ->where('id', $id)
            ->first([
                'id',
                'sku',
                'qty',
                'order_qty',
                'parent',
                'marketplace_1',
                'marketplace_2',
                'what_happened',
                'issue',
                'issue_remark',
                'action_1',
                'action_1_remark',
                'replacement_tracking',
                'c_action_1',
                'c_action_1_remark',
                'close_note',
                'created_by',
                'created_at',
            ]);

        $tz = config('app.timezone');

        return response()->json([
            'message' => 'Hold issue saved successfully.',
            'row' => [
                'id' => (int) $row->id,
                'sku' => $row->sku,
                'qty' => (float) $row->qty,
                'order_qty' => $row->order_qty !== null ? (float) $row->order_qty : null,
                'parent' => $row->parent,
                'marketplace_1' => $row->marketplace_1,
                'marketplace_2' => $row->marketplace_2,
                'what_happened' => $row->what_happened,
                'issue' => $row->issue,
                'issue_remark' => $row->issue_remark,
                'action_1' => $row->action_1,
                'action_1_remark' => $row->action_1_remark,
                'replacement_tracking' => $row->replacement_tracking,
                'c_action_1' => $row->c_action_1,
                'c_action_1_remark' => $row->c_action_1_remark,
                'close_note' => $row->close_note,
                'created_by' => $row->created_by,
                'created_at' => $row->created_at,
                'created_at_display' => $row->created_at
                    ? \Carbon\Carbon::parse($row->created_at)->timezone($tz)->format('d-m-Y H:i')
                    : '',
            ],
        ], 201);
    })->name('customer.care.orders.on.hold.issues.store');
    Route::put('/customer-care/orders-on-hold/issues/{id}', function (\Illuminate\Http\Request $request, int $id) {
        $validated = $request->validate([
            'sku' => 'required|string|max:128',
            'qty' => 'required|numeric|min:0',
            'order_qty' => 'nullable|numeric|min:0',
            'parent' => 'nullable|string|max:255',
            'marketplace_1' => 'nullable|string|max:255',
            'marketplace_2' => 'nullable|string|max:255',
            'what_happened' => 'nullable|string|max:50',
            'issue' => 'required|string|max:255',
            'issue_remark' => 'nullable|string|max:255',
            'action_1' => 'nullable|string|in:Offer Customer Alterntive / Updgrade,Upgraded + Stock Alternate,Alternate Sent + Stock Alternate,Sent Wrong Item + Stock Outgoing,Cancelled,Other|max:255',
            'action_1_remark' => 'nullable|string|max:255',
            'replacement_tracking' => 'nullable|string|max:50',
            'c_action_1' => 'nullable|string|max:255',
            'c_action_1_remark' => 'nullable|string|max:255',
            'close_note' => 'nullable|string|max:255',
        ]);
        $allowedRootCauses = [
            'Mapping',
            'Replacement Issued But not Entered',
            'FBA stock Issued But not Entered',
            'Alternate Issued But not Entered',
            'Stock Balance not Entered',
            'Reserve Stock Issue',
            'Other',
        ];
        if (! in_array((string) ($validated['issue'] ?? ''), $allowedRootCauses, true)) {
            return response()->json([
                'message' => 'Invalid Root Cause Found selection.',
                'errors' => ['issue' => ['Invalid Root Cause Found selection.']],
            ], 422);
        }
        if (($validated['issue'] ?? null) === 'Other' && trim((string) ($validated['issue_remark'] ?? '')) === '') {
            return response()->json([
                'message' => 'Root Cause Found remark is required when Other is selected.',
                'errors' => ['issue_remark' => ['Root Cause Found remark is required when Other is selected.']],
            ], 422);
        }
        if (($validated['action_1'] ?? null) === 'Other' && trim((string) ($validated['action_1_remark'] ?? '')) === '') {
            return response()->json([
                'message' => 'Action remark is required when Action is Other.',
                'errors' => ['action_1_remark' => ['Action remark is required when Action is Other.']],
            ], 422);
        }
        if (($validated['c_action_1'] ?? null) === 'Other' && trim((string) ($validated['c_action_1_remark'] ?? '')) === '') {
            return response()->json([
                'message' => 'Root Cause Fixed remark is required when Root Cause Fixed is Other.',
                'errors' => ['c_action_1_remark' => ['Root Cause Fixed remark is required when Root Cause Fixed is Other.']],
            ], 422);
        }

        $existing = \Illuminate\Support\Facades\DB::table('orders_on_hold_issues')->where('id', $id)->first();
        if (! $existing) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $user = auth()->user();
        $actorName = trim((string) ($user?->name ?? 'System'));
        if ($actorName === '') {
            $actorName = 'System';
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($id, $validated, $actorName, $user) {
            $now = now();
            $nextRevision = ((int) (\Illuminate\Support\Facades\DB::table('orders_on_hold_issue_histories')
                ->where('orders_on_hold_issue_id', $id)
                ->max('revision_no'))) + 1;
            $payload = [
                'sku' => trim($validated['sku']),
                'qty' => (float) $validated['qty'],
                'order_qty' => isset($validated['order_qty']) ? (float) $validated['order_qty'] : null,
                'parent' => isset($validated['parent']) ? trim((string) $validated['parent']) : null,
                'marketplace_1' => isset($validated['marketplace_1']) ? trim((string) $validated['marketplace_1']) : null,
                'marketplace_2' => isset($validated['marketplace_2']) ? trim((string) $validated['marketplace_2']) : null,
                'what_happened' => isset($validated['what_happened']) ? trim((string) $validated['what_happened']) : null,
                'issue' => trim($validated['issue']),
                'issue_remark' => isset($validated['issue_remark']) ? trim((string) $validated['issue_remark']) : null,
                'action_1' => isset($validated['action_1']) ? trim((string) $validated['action_1']) : null,
                'action_1_remark' => isset($validated['action_1_remark']) ? trim((string) $validated['action_1_remark']) : null,
                'replacement_tracking' => isset($validated['replacement_tracking']) ? trim((string) $validated['replacement_tracking']) : null,
                'c_action_1' => isset($validated['c_action_1']) ? trim((string) $validated['c_action_1']) : null,
                'c_action_1_remark' => isset($validated['c_action_1_remark']) ? trim((string) $validated['c_action_1_remark']) : null,
                'close_note' => isset($validated['close_note']) ? trim((string) $validated['close_note']) : null,
                'updated_at' => $now,
            ];

            \Illuminate\Support\Facades\DB::table('orders_on_hold_issues')->where('id', $id)->update($payload);

            \Illuminate\Support\Facades\DB::table('orders_on_hold_issue_histories')->insert([
                'orders_on_hold_issue_id' => $id,
                'event_type' => 'updated',
                'revision_no' => $nextRevision,
                'sku' => $payload['sku'],
                'qty' => $payload['qty'],
                'order_qty' => $payload['order_qty'],
                'parent' => $payload['parent'],
                'marketplace_1' => $payload['marketplace_1'],
                'marketplace_2' => $payload['marketplace_2'],
                'what_happened' => $payload['what_happened'],
                'issue' => $payload['issue'],
                'issue_remark' => $payload['issue_remark'],
                'action_1' => $payload['action_1'],
                'action_1_remark' => $payload['action_1_remark'],
                'replacement_tracking' => $payload['replacement_tracking'],
                'c_action_1' => $payload['c_action_1'],
                'c_action_1_remark' => $payload['c_action_1_remark'],
                'close_note' => $payload['close_note'],
                'created_by' => $actorName,
                'created_by_user_id' => $user?->id,
                'logged_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return response()->json(['message' => 'Hold issue updated successfully.']);
    })->name('customer.care.orders.on.hold.issues.update');
    Route::post('/customer-care/orders-on-hold/issues/{id}/archive', function (int $id) {
        $row = \Illuminate\Support\Facades\DB::table('orders_on_hold_issues')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $user = auth()->user();
        $actorName = trim((string) ($user?->name ?? 'System'));
        if ($actorName === '') {
            $actorName = 'System';
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($id, $row, $actorName, $user) {
            $now = now();
            $nextRevision = ((int) (\Illuminate\Support\Facades\DB::table('orders_on_hold_issue_histories')
                ->where('orders_on_hold_issue_id', $id)
                ->max('revision_no'))) + 1;
            \Illuminate\Support\Facades\DB::table('orders_on_hold_issues')
                ->where('id', $id)
                ->update([
                    'is_archived' => true,
                    'archived_at' => $now,
                    'archived_by' => $actorName,
                    'updated_at' => $now,
                ]);

            \Illuminate\Support\Facades\DB::table('orders_on_hold_issue_histories')->insert([
                'orders_on_hold_issue_id' => $id,
                'event_type' => 'archived',
                'revision_no' => $nextRevision,
                'sku' => $row->sku,
                'qty' => (float) $row->qty,
                'order_qty' => $row->order_qty !== null ? (float) $row->order_qty : null,
                'parent' => $row->parent,
                'marketplace_1' => $row->marketplace_1 ?? null,
                'marketplace_2' => $row->marketplace_2 ?? null,
                'what_happened' => $row->what_happened ?? null,
                'issue' => $row->issue,
                'issue_remark' => $row->issue_remark ?? null,
                'action_1' => $row->action_1 ?? null,
                'action_1_remark' => $row->action_1_remark ?? null,
                'replacement_tracking' => $row->replacement_tracking ?? null,
                'c_action_1' => $row->c_action_1 ?? null,
                'c_action_1_remark' => $row->c_action_1_remark ?? null,
                'close_note' => $row->close_note ?? null,
                'created_by' => $actorName,
                'created_by_user_id' => $user?->id,
                'logged_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return response()->json(['message' => 'Hold issue archived successfully.']);
    })->name('customer.care.orders.on.hold.issues.archive');

    Route::post('/customer-care/orders-on-hold/import-csv', function (\Illuminate\Http\Request $request) {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);
        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $headers = null;
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        $user = auth()->user();
        $createdBy = trim((string) ($user?->name ?? 'System')) ?: 'System';
        $map = ['sku' => ['sku'], 'qty' => ['qty', 'quantity'], 'order_qty' => ['order_qty', 'order qty'], 'parent' => ['parent'], 'marketplace_1' => ['marketplace_1', 'mkt1'], 'marketplace_2' => ['marketplace_2', 'mkt2'], 'what_happened' => ['what_happened', 'what?', 'what happened'], 'action_1' => ['action_1', 'action', 'action 1'], 'action_1_remark' => ['action_1_remark', 'action remark'], 'replacement_tracking' => ['replacement_tracking', 'replacement tracking'], 'issue' => ['issue', 'root_cause_found', 'root cause found'], 'issue_remark' => ['issue_remark', 'root cause remark'], 'c_action_1' => ['c_action_1', 'root_cause_fixed', 'root cause fixed'], 'c_action_1_remark' => ['c_action_1_remark', 'root cause fixed remark']];
        while (($row = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $row);

                continue;
            }
            if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }
            $data = array_combine($headers, array_pad($row, count($headers), ''));
            $get = function ($f) use ($data, $map) {
                foreach ($map[$f] ?? [$f] as $a) {
                    if (array_key_exists($a, $data) && trim((string) $data[$a]) !== '') {
                        return trim((string) $data[$a]);
                    }
                }

                return null;
            };
            $sku = $get('sku');
            if (! $sku) {
                $skipped++;
                $errors[] = 'Row skipped: SKU empty.';

                continue;
            }
            $qty = $get('qty');
            if ($qty === null || ! is_numeric($qty)) {
                $skipped++;
                $errors[] = "Row skipped (SKU=$sku): invalid QTY.";

                continue;
            }
            $issue = $get('issue');
            if (! $issue) {
                $skipped++;
                $errors[] = "Row skipped (SKU=$sku): Root Cause Found required.";

                continue;
            }
            try {
                $now = now();
                $payload = ['sku' => $sku, 'qty' => (float) $qty, 'order_qty' => $get('order_qty') !== null ? (float) $get('order_qty') : null, 'parent' => $get('parent'), 'marketplace_1' => $get('marketplace_1'), 'marketplace_2' => $get('marketplace_2'), 'what_happened' => $get('what_happened'), 'issue' => $issue, 'issue_remark' => $get('issue_remark'), 'action_1' => $get('action_1'), 'action_1_remark' => $get('action_1_remark'), 'replacement_tracking' => $get('replacement_tracking'), 'c_action_1' => $get('c_action_1'), 'c_action_1_remark' => $get('c_action_1_remark'), 'created_by' => $createdBy, 'created_by_user_id' => $user?->id, 'created_at' => $now, 'updated_at' => $now];
                \Illuminate\Support\Facades\DB::transaction(function () use ($payload, $now) {
                    $id = \Illuminate\Support\Facades\DB::table('orders_on_hold_issues')->insertGetId($payload);
                    \Illuminate\Support\Facades\DB::table('orders_on_hold_issue_histories')->insert(array_merge($payload, ['orders_on_hold_issue_id' => $id, 'event_type' => 'created', 'revision_no' => 0, 'logged_at' => $now]));
                });
                $inserted++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Row failed (SKU=$sku): ".$e->getMessage();
            }
        }
        fclose($handle);

        return response()->json(['message' => "{$inserted} record(s) imported, {$skipped} skipped.", 'inserted' => $inserted, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)]);
    })->name('customer.care.orders.on.hold.issues.import');
    Route::get('/customer-care/label-issues', [\App\Http\Controllers\CustomerCare\LabelIssuesController::class, 'index'])
        ->name('customer.care.label.issues');
    Route::get('/customer-care/label-issues/sku-details', [\App\Http\Controllers\CustomerCare\LabelIssuesController::class, 'skuDetails'])
        ->name('customer.care.label.issues.sku.details');
    Route::get('/customer-care/label-issues/issues', [\App\Http\Controllers\CustomerCare\LabelIssuesController::class, 'issuesIndex'])
        ->name('customer.care.label.issues.list.index');
    Route::get('/customer-care/label-issues/history', [\App\Http\Controllers\CustomerCare\LabelIssuesController::class, 'historyIndex'])
        ->name('customer.care.label.issues.history.index');
    Route::post('/customer-care/label-issues/issues', [\App\Http\Controllers\CustomerCare\LabelIssuesController::class, 'store'])
        ->name('customer.care.label.issues.list.store');
    Route::put('/customer-care/label-issues/issues/{id}', [\App\Http\Controllers\CustomerCare\LabelIssuesController::class, 'update'])
        ->name('customer.care.label.issues.list.update');
    Route::post('/customer-care/label-issues/issues/{id}/archive', [\App\Http\Controllers\CustomerCare\LabelIssuesController::class, 'archive'])
        ->name('customer.care.label.issues.list.archive');
    Route::get('/customer-care/label-issues/dropdown-options', [\App\Http\Controllers\CustomerCare\LabelIssuesController::class, 'dropdownOptionsIndex'])
        ->name('customer.care.label.issues.dropdown.options.index');
    Route::post('/customer-care/label-issues/dropdown-options', [\App\Http\Controllers\CustomerCare\LabelIssuesController::class, 'dropdownOptionsStore'])
        ->name('customer.care.label.issues.dropdown.options.store');
    Route::post('/customer-care/label-issues/dropdown-options/delete', [\App\Http\Controllers\CustomerCare\LabelIssuesController::class, 'dropdownOptionsDelete'])
        ->name('customer.care.label.issues.dropdown.options.delete');

    Route::get('/customer-care/all-issues', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'index'])
        ->name('customer.care.dispatch.issues');
    Route::get('/customer-care/dispatch', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'dispatchIssueBoard'])
        ->name('customer.care.dispatch.board');
    Route::get('/customer-care/carrier-and-claim', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'carrierAndClaimBoard'])
        ->name('customer.care.dispatch.carrier.and.claim');
    Route::get('/customer-care/carrier-issue', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'carrierIssueBoard'])
        ->name('customer.care.dispatch.carrier.issue');
    Route::permanentRedirect('/customer-care/dispatch-issue', '/customer-care/all-issues');
    Route::get('/customer-care/all-issues/sku-details', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'skuDetails'])
        ->name('customer.care.dispatch.issues.sku.details');
    Route::get('/customer-care/all-issues/issues', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'issuesIndex'])
        ->name('customer.care.dispatch.issues.list.index');
    Route::get('/customer-care/all-issues/l30-loss', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'l30Loss'])
        ->name('customer.care.dispatch.issues.l30.loss');
    Route::get('/customer-care/all-issues/l30-issues', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'l30Issues'])
        ->name('customer.care.dispatch.issues.l30.issues');
    Route::get('/customer-care/all-issues/history', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'historyIndex'])
        ->name('customer.care.dispatch.issues.history.index');
    Route::post('/customer-care/all-issues/issues', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'store'])
        ->name('customer.care.dispatch.issues.list.store');
    Route::put('/customer-care/all-issues/issues/{id}', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'update'])
        ->name('customer.care.dispatch.issues.list.update');
    Route::patch('/customer-care/all-issues/issues/{id}/claim-filed', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'updateClaimFiled'])
        ->name('customer.care.dispatch.issues.list.patch.claim.filed');
    Route::patch('/customer-care/all-issues/issues/{id}/amp-usd', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'updateAmpUsd'])
        ->name('customer.care.dispatch.issues.list.patch.amp.usd');
    Route::patch('/customer-care/all-issues/issues/{id}/claim-received', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'updateClaimReceived'])
        ->name('customer.care.dispatch.issues.list.patch.claim.received');
    Route::patch('/customer-care/all-issues/issues/{id}/issue-carrier', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'updateIssueCarrier'])
        ->name('customer.care.dispatch.issues.list.patch.issue.carrier');
    Route::get('/customer-care/all-issues/claims-stats', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'claimsStats'])
        ->name('customer.care.dispatch.issues.claims.stats');
    Route::post('/customer-care/all-issues/issues/{id}/archive', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'archive'])
        ->name('customer.care.dispatch.issues.list.archive');
    Route::get('/customer-care/all-issues/dropdown-options', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'dropdownOptionsIndex'])
        ->name('customer.care.dispatch.issues.dropdown.options.index');
    Route::post('/customer-care/all-issues/dropdown-options', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'dropdownOptionsStore'])
        ->name('customer.care.dispatch.issues.dropdown.options.store');
    Route::post('/customer-care/all-issues/dropdown-options/delete', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'dropdownOptionsDelete'])
        ->name('customer.care.dispatch.issues.dropdown.options.delete');
    /* Legacy /customer-care/dispatch-issues/* URLs → /customer-care/all-issues/* (308 preserves POST method) */
    Route::any('/customer-care/dispatch-issues/{path?}', function (?string $path = null) {
        $target = '/customer-care/all-issues';
        if ($path !== null && $path !== '') {
            $target .= '/'.$path;
        }

        return redirect($target, 308);
    })->where('path', '.*');
    Route::get('/customer-care/listing-issue', [\App\Http\Controllers\CustomerCare\ListingIssueController::class, 'index'])
        ->name('customer.care.listing.issue');
    Route::get('/customer-care/listing-issue/sku-details', [\App\Http\Controllers\CustomerCare\ListingIssueController::class, 'skuDetails'])
        ->name('customer.care.listing.issue.sku.details');
    Route::get('/customer-care/listing-issue/issues', [\App\Http\Controllers\CustomerCare\ListingIssueController::class, 'issuesIndex'])
        ->name('customer.care.listing.issue.issues.index');
    Route::get('/customer-care/listing-issue/history', [\App\Http\Controllers\CustomerCare\ListingIssueController::class, 'historyIndex'])
        ->name('customer.care.listing.issue.history.index');
    Route::post('/customer-care/listing-issue/issues', [\App\Http\Controllers\CustomerCare\ListingIssueController::class, 'store'])
        ->name('customer.care.listing.issue.issues.store');
    Route::put('/customer-care/listing-issue/issues/{id}', [\App\Http\Controllers\CustomerCare\ListingIssueController::class, 'update'])
        ->name('customer.care.listing.issue.issues.update');
    Route::post('/customer-care/listing-issue/issues/{id}/archive', [\App\Http\Controllers\CustomerCare\ListingIssueController::class, 'archive'])
        ->name('customer.care.listing.issue.issues.archive');
    Route::get('/customer-care/listing-issue/dropdown-options', [\App\Http\Controllers\CustomerCare\ListingIssueController::class, 'dropdownOptionsIndex'])
        ->name('customer.care.listing.issue.dropdown.options.index');
    Route::post('/customer-care/listing-issue/dropdown-options', [\App\Http\Controllers\CustomerCare\ListingIssueController::class, 'dropdownOptionsStore'])
        ->name('customer.care.listing.issue.dropdown.options.store');
    Route::post('/customer-care/listing-issue/dropdown-options/delete', [\App\Http\Controllers\CustomerCare\ListingIssueController::class, 'dropdownOptionsDelete'])
        ->name('customer.care.listing.issue.dropdown.options.delete');

    Route::get('/customer-care/c-care-issues', [\App\Http\Controllers\CustomerCare\CCareIssuesController::class, 'index'])
        ->name('customer.care.c.care.issues');
    Route::get('/customer-care/c-care-issues/sku-details', [\App\Http\Controllers\CustomerCare\CCareIssuesController::class, 'skuDetails'])
        ->name('customer.care.c.care.issues.sku.details');
    Route::get('/customer-care/c-care-issues/issues', [\App\Http\Controllers\CustomerCare\CCareIssuesController::class, 'issuesIndex'])
        ->name('customer.care.c.care.issues.list.index');
    Route::get('/customer-care/c-care-issues/history', [\App\Http\Controllers\CustomerCare\CCareIssuesController::class, 'historyIndex'])
        ->name('customer.care.c.care.issues.history.index');
    Route::post('/customer-care/c-care-issues/issues', [\App\Http\Controllers\CustomerCare\CCareIssuesController::class, 'store'])
        ->name('customer.care.c.care.issues.list.store');
    Route::put('/customer-care/c-care-issues/issues/{id}', [\App\Http\Controllers\CustomerCare\CCareIssuesController::class, 'update'])
        ->name('customer.care.c.care.issues.list.update');
    Route::post('/customer-care/c-care-issues/issues/{id}/archive', [\App\Http\Controllers\CustomerCare\CCareIssuesController::class, 'archive'])
        ->name('customer.care.c.care.issues.list.archive');
    Route::get('/customer-care/c-care-issues/dropdown-options', [\App\Http\Controllers\CustomerCare\CCareIssuesController::class, 'dropdownOptionsIndex'])
        ->name('customer.care.c.care.issues.dropdown.options.index');
    Route::post('/customer-care/c-care-issues/dropdown-options', [\App\Http\Controllers\CustomerCare\CCareIssuesController::class, 'dropdownOptionsStore'])
        ->name('customer.care.c.care.issues.dropdown.options.store');
    Route::post('/customer-care/c-care-issues/dropdown-options/delete', [\App\Http\Controllers\CustomerCare\CCareIssuesController::class, 'dropdownOptionsDelete'])
        ->name('customer.care.c.care.issues.dropdown.options.delete');

    Route::get('/customer-care/other-issues', [\App\Http\Controllers\CustomerCare\OtherIssuesController::class, 'index'])
        ->name('customer.care.other.issues');
    Route::get('/customer-care/other-issues/sku-details', [\App\Http\Controllers\CustomerCare\OtherIssuesController::class, 'skuDetails'])
        ->name('customer.care.other.issues.sku.details');
    Route::get('/customer-care/other-issues/issues', [\App\Http\Controllers\CustomerCare\OtherIssuesController::class, 'issuesIndex'])
        ->name('customer.care.other.issues.list.index');
    Route::get('/customer-care/other-issues/history', [\App\Http\Controllers\CustomerCare\OtherIssuesController::class, 'historyIndex'])
        ->name('customer.care.other.issues.history.index');
    Route::post('/customer-care/other-issues/issues', [\App\Http\Controllers\CustomerCare\OtherIssuesController::class, 'store'])
        ->name('customer.care.other.issues.list.store');
    Route::put('/customer-care/other-issues/issues/{id}', [\App\Http\Controllers\CustomerCare\OtherIssuesController::class, 'update'])
        ->name('customer.care.other.issues.list.update');
    Route::post('/customer-care/other-issues/issues/{id}/archive', [\App\Http\Controllers\CustomerCare\OtherIssuesController::class, 'archive'])
        ->name('customer.care.other.issues.list.archive');
    Route::get('/customer-care/other-issues/dropdown-options', [\App\Http\Controllers\CustomerCare\OtherIssuesController::class, 'dropdownOptionsIndex'])
        ->name('customer.care.other.issues.dropdown.options.index');
    Route::post('/customer-care/other-issues/dropdown-options', [\App\Http\Controllers\CustomerCare\OtherIssuesController::class, 'dropdownOptionsStore'])
        ->name('customer.care.other.issues.dropdown.options.store');
    Route::post('/customer-care/other-issues/dropdown-options/delete', [\App\Http\Controllers\CustomerCare\OtherIssuesController::class, 'dropdownOptionsDelete'])
        ->name('customer.care.other.issues.dropdown.options.delete');

    Route::get('/customer-care/qc-and-packing', function () {
        $marketplaces = \Illuminate\Support\Facades\DB::table('marketplace_percentages')
            ->whereNotNull('marketplace')
            ->where('marketplace', '!=', '')
            ->orderBy('marketplace')
            ->pluck('marketplace')
            ->map(fn ($m) => trim((string) $m))
            ->filter()
            ->unique()
            ->values();

        return view('customer-care.qc_and_packing', array_merge(compact('marketplaces'), [
            'importUrl' => route('customer.care.qc.and.packing.issues.import'),
        ]));
    })->name('customer.care.qc.and.packing');
    Route::get('/customer-care/qc-and-packing/sku-details', function (\Illuminate\Http\Request $request) {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['found' => false, 'message' => 'SKU is required.'], 422);
        }

        $row = \Illuminate\Support\Facades\DB::table('product_master as pm')
            ->selectRaw('pm.sku, pm.parent, pm.Values as values_json, pm.main_image, pm.image1')
            ->whereRaw('LOWER(TRIM(pm.sku)) = ?', [strtolower($sku)])
            ->first();

        if (! $row) {
            return response()->json(['found' => false, 'message' => 'SKU not found.']);
        }

        $shopify = \Illuminate\Support\Facades\DB::table('shopify_skus')
            ->selectRaw('COALESCE(inv, 0) as qty, image_src')
            ->whereRaw('LOWER(TRIM(sku)) = ?', [strtolower((string) $row->sku)])
            ->first();

        $normalizeImage = static function ($path) {
            $p = trim((string) ($path ?? ''));
            if ($p === '') {
                return null;
            }
            if (preg_match('/^(https?:)?\/\//i', $p) || str_starts_with($p, 'data:')) {
                return $p;
            }

            return '/'.ltrim($p, '/');
        };

        $values = [];
        if (isset($row->values_json) && is_string($row->values_json) && trim($row->values_json) !== '') {
            $decoded = json_decode($row->values_json, true);
            if (is_array($decoded)) {
                $values = $decoded;
            }
        }

        $imageUrl = $normalizeImage($shopify?->image_src)
            ?? $normalizeImage($values['image_path'] ?? null)
            ?? $normalizeImage($row->main_image ?? null)
            ?? $normalizeImage($row->image1 ?? null);

        return response()->json([
            'found' => true,
            'sku' => $row->sku,
            'parent' => $row->parent,
            'qty' => (float) ($shopify?->qty ?? 0),
            'image_url' => $imageUrl,
        ]);
    })->name('customer.care.qc.and.packing.sku.details');
    Route::get('/customer-care/qc-and-packing/issues', function () {
        $rows = \Illuminate\Support\Facades\DB::table('qc_and_packing_issues')
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->orderByDesc('id')
            ->limit(1000)
            ->get([
                'id',
                'sku',
                'qty',
                'order_qty',
                'parent',
                'marketplace_1',
                'marketplace_2',
                'what_happened',
                'issue',
                'issue_remark',
                'action_1',
                'action_1_remark',
                'replacement_tracking',
                'c_action_1',
                'c_action_1_remark',
                'close_note',
                'department',
                'created_by',
                'created_at',
            ]);

        $skus = $rows->map(fn ($r) => strtoupper(trim((string) ($r->sku ?? ''))))->filter()->unique()->values()->all();
        $productMap = [];
        if ($skus !== []) {
            $placeholders = implode(',', array_fill(0, count($skus), '?'));
            $pRows = \Illuminate\Support\Facades\DB::table('product_master')
                ->select('id', 'sku', 'Values')
                ->whereRaw("UPPER(TRIM(sku)) IN ({$placeholders})", $skus)
                ->get();
            foreach ($pRows as $p) {
                $productMap[strtoupper(trim((string) $p->sku))] = $p;
            }
        }

        $tz = config('app.timezone');
        $data = $rows->map(function ($row) use ($tz, $productMap) {
            $k = strtoupper(trim((string) ($row->sku ?? '')));
            $productMasterId = null;
            $ctnInstructions = '';
            if (isset($productMap[$k])) {
                $p = $productMap[$k];
                $productMasterId = (int) $p->id;
                $vals = json_decode($p->Values ?? '', true);
                if (is_array($vals) && isset($vals['ctn_instructions'])) {
                    $ctnInstructions = mb_substr((string) $vals['ctn_instructions'], 0, 100);
                }
            }

            return [
                'id' => (int) $row->id,
                'sku' => $row->sku,
                'qty' => (float) $row->qty,
                'order_qty' => $row->order_qty !== null ? (float) $row->order_qty : null,
                'parent' => $row->parent,
                'marketplace_1' => $row->marketplace_1,
                'marketplace_2' => $row->marketplace_2,
                'what_happened' => $row->what_happened,
                'issue' => $row->issue,
                'issue_remark' => $row->issue_remark,
                'action_1' => $row->action_1,
                'action_1_remark' => $row->action_1_remark,
                'replacement_tracking' => $row->replacement_tracking,
                'c_action_1' => $row->c_action_1,
                'c_action_1_remark' => $row->c_action_1_remark,
                'close_note' => $row->close_note,
                'department' => $row->department ?? null,
                'created_by' => $row->created_by,
                'created_at' => $row->created_at,
                'created_at_display' => $row->created_at
                    ? \Carbon\Carbon::parse($row->created_at)->timezone($tz)->format('d-m-Y H:i')
                    : '',
                'product_master_id' => $productMasterId,
                'ctn_instructions' => $ctnInstructions,
            ];
        })->values();

        return response()->json(['data' => $data]);
    })->name('customer.care.qc.and.packing.issues.index');
    Route::get('/customer-care/qc-and-packing/history', function () {
        $rows = \Illuminate\Support\Facades\DB::table('qc_and_packing_issue_histories')
            ->orderByDesc('id')
            ->limit(1000)
            ->get([
                'id',
                'orders_on_hold_issue_id',
                'event_type',
                'revision_no',
                'sku',
                'qty',
                'order_qty',
                'parent',
                'marketplace_1',
                'marketplace_2',
                'what_happened',
                'issue',
                'issue_remark',
                'action_1',
                'action_1_remark',
                'replacement_tracking',
                'c_action_1',
                'c_action_1_remark',
                'close_note',
                'department',
                'created_by',
                'logged_at',
            ]);

        $histSkus = $rows->map(fn ($r) => strtoupper(trim((string) ($r->sku ?? ''))))->filter()->unique()->values()->all();
        $histProductMap = [];
        if ($histSkus !== []) {
            $placeholders = implode(',', array_fill(0, count($histSkus), '?'));
            $pRows = \Illuminate\Support\Facades\DB::table('product_master')
                ->select('id', 'sku', 'Values')
                ->whereRaw("UPPER(TRIM(sku)) IN ({$placeholders})", $histSkus)
                ->get();
            foreach ($pRows as $p) {
                $histProductMap[strtoupper(trim((string) $p->sku))] = $p;
            }
        }

        $tz = config('app.timezone');
        $data = $rows->map(function ($row) use ($tz, $histProductMap) {
            $k = strtoupper(trim((string) ($row->sku ?? '')));
            $productMasterId = null;
            $ctnInstructions = '';
            if (isset($histProductMap[$k])) {
                $p = $histProductMap[$k];
                $productMasterId = (int) $p->id;
                $vals = json_decode($p->Values ?? '', true);
                if (is_array($vals) && isset($vals['ctn_instructions'])) {
                    $ctnInstructions = mb_substr((string) $vals['ctn_instructions'], 0, 100);
                }
            }

            return [
                'id' => (int) $row->id,
                'orders_on_hold_issue_id' => $row->orders_on_hold_issue_id ? (int) $row->orders_on_hold_issue_id : null,
                'event_type' => $row->event_type,
                'revision_no' => $row->revision_no !== null ? (int) $row->revision_no : null,
                'issue_ref' => ($row->orders_on_hold_issue_id
                    ? (
                        ((int) ($row->revision_no ?? 0) > 0)
                            ? ((string) $row->orders_on_hold_issue_id.'.'.(string) ((int) $row->revision_no))
                            : (string) $row->orders_on_hold_issue_id
                    )
                    : null),
                'sku' => $row->sku,
                'qty' => (float) $row->qty,
                'order_qty' => $row->order_qty !== null ? (float) $row->order_qty : null,
                'parent' => $row->parent,
                'marketplace_1' => $row->marketplace_1,
                'marketplace_2' => $row->marketplace_2,
                'what_happened' => $row->what_happened,
                'issue' => $row->issue,
                'issue_remark' => $row->issue_remark,
                'action_1' => $row->action_1,
                'action_1_remark' => $row->action_1_remark,
                'replacement_tracking' => $row->replacement_tracking,
                'c_action_1' => $row->c_action_1,
                'c_action_1_remark' => $row->c_action_1_remark,
                'close_note' => $row->close_note,
                'department' => $row->department ?? null,
                'created_by' => $row->created_by,
                'logged_at' => $row->logged_at,
                'logged_at_display' => $row->logged_at
                    ? \Carbon\Carbon::parse($row->logged_at)->timezone($tz)->format('d-m-Y H:i')
                    : '',
                'product_master_id' => $productMasterId,
                'ctn_instructions' => $ctnInstructions,
            ];
        })->values();

        return response()->json(['data' => $data]);
    })->name('customer.care.qc.and.packing.history.index');
    Route::post('/customer-care/qc-and-packing/issues', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'sku' => 'required|string|max:128',
            'qty' => 'required|numeric|min:0',
            'order_qty' => 'nullable|numeric|min:0',
            'parent' => 'nullable|string|max:255',
            'marketplace_1' => 'nullable|string|max:255',
            'marketplace_2' => 'nullable|string|max:255',
            'what_happened' => 'nullable|string|max:50',
            'issue' => 'required|string|max:255',
            'issue_remark' => 'nullable|string|max:255',
            'action_1' => 'nullable|string|in:Offer Customer Alterntive / Updgrade,Upgraded + Stock Alternate,Alternate Sent + Stock Alternate,Sent Wrong Item + Stock Outgoing,Cancelled,Other|max:255',
            'action_1_remark' => 'nullable|string|max:255',
            'replacement_tracking' => 'nullable|string|max:50',
            'c_action_1' => 'nullable|string|max:255',
            'c_action_1_remark' => 'nullable|string|max:255',
            'close_note' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:100',
        ]);
        if (($validated['action_1'] ?? null) === 'Other' && trim((string) ($validated['action_1_remark'] ?? '')) === '') {
            return response()->json([
                'message' => 'Action remark is required when Action is Other.',
                'errors' => ['action_1_remark' => ['Action remark is required when Action is Other.']],
            ], 422);
        }
        if (($validated['c_action_1'] ?? null) === 'Other' && trim((string) ($validated['c_action_1_remark'] ?? '')) === '') {
            return response()->json([
                'message' => 'Root Cause Fixed remark is required when Root Cause Fixed is Other.',
                'errors' => ['c_action_1_remark' => ['Root Cause Fixed remark is required when Root Cause Fixed is Other.']],
            ], 422);
        }

        $user = auth()->user();
        $createdBy = trim((string) ($user?->name ?? 'System'));
        if ($createdBy === '') {
            $createdBy = 'System';
        }

        $id = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $createdBy, $user) {
            $now = now();
            $department = isset($validated['department']) && trim((string) $validated['department']) !== ''
                ? trim((string) $validated['department'])
                : null;

            $payload = [
                'sku' => trim($validated['sku']),
                'qty' => (float) $validated['qty'],
                'order_qty' => isset($validated['order_qty']) ? (float) $validated['order_qty'] : null,
                'parent' => isset($validated['parent']) ? trim((string) $validated['parent']) : null,
                'marketplace_1' => isset($validated['marketplace_1']) ? trim((string) $validated['marketplace_1']) : null,
                'marketplace_2' => isset($validated['marketplace_2']) ? trim((string) $validated['marketplace_2']) : null,
                'what_happened' => isset($validated['what_happened']) ? trim((string) $validated['what_happened']) : null,
                'issue' => trim($validated['issue']),
                'issue_remark' => isset($validated['issue_remark']) ? trim((string) $validated['issue_remark']) : null,
                'action_1' => isset($validated['action_1']) ? trim((string) $validated['action_1']) : null,
                'action_1_remark' => isset($validated['action_1_remark']) ? trim((string) $validated['action_1_remark']) : null,
                'replacement_tracking' => isset($validated['replacement_tracking']) ? trim((string) $validated['replacement_tracking']) : null,
                'c_action_1' => isset($validated['c_action_1']) ? trim((string) $validated['c_action_1']) : null,
                'c_action_1_remark' => isset($validated['c_action_1_remark']) ? trim((string) $validated['c_action_1_remark']) : null,
                'close_note' => isset($validated['close_note']) ? trim((string) $validated['close_note']) : null,
                'department' => $department,
                'created_by' => $createdBy,
                'created_by_user_id' => $user?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $issueId = \Illuminate\Support\Facades\DB::table('qc_and_packing_issues')->insertGetId($payload);

            \Illuminate\Support\Facades\DB::table('qc_and_packing_issue_histories')->insert([
                'orders_on_hold_issue_id' => $issueId,
                'event_type' => 'created',
                'revision_no' => 0,
                'sku' => $payload['sku'],
                'qty' => $payload['qty'],
                'order_qty' => $payload['order_qty'],
                'parent' => $payload['parent'],
                'marketplace_1' => $payload['marketplace_1'],
                'marketplace_2' => $payload['marketplace_2'],
                'what_happened' => $payload['what_happened'],
                'issue' => $payload['issue'],
                'issue_remark' => $payload['issue_remark'],
                'action_1' => $payload['action_1'],
                'action_1_remark' => $payload['action_1_remark'],
                'replacement_tracking' => $payload['replacement_tracking'],
                'c_action_1' => $payload['c_action_1'],
                'c_action_1_remark' => $payload['c_action_1_remark'],
                'close_note' => $payload['close_note'],
                'department' => $department,
                'created_by' => $createdBy,
                'created_by_user_id' => $user?->id,
                'logged_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $issueId;
        });

        $row = \Illuminate\Support\Facades\DB::table('qc_and_packing_issues')
            ->where('id', $id)
            ->first([
                'id',
                'sku',
                'qty',
                'order_qty',
                'parent',
                'marketplace_1',
                'marketplace_2',
                'what_happened',
                'issue',
                'issue_remark',
                'action_1',
                'action_1_remark',
                'replacement_tracking',
                'c_action_1',
                'c_action_1_remark',
                'close_note',
                'department',
                'created_by',
                'created_at',
            ]);

        $tz = config('app.timezone');

        return response()->json([
            'message' => 'Hold issue saved successfully.',
            'row' => [
                'id' => (int) $row->id,
                'sku' => $row->sku,
                'qty' => (float) $row->qty,
                'order_qty' => $row->order_qty !== null ? (float) $row->order_qty : null,
                'parent' => $row->parent,
                'marketplace_1' => $row->marketplace_1,
                'marketplace_2' => $row->marketplace_2,
                'what_happened' => $row->what_happened,
                'issue' => $row->issue,
                'issue_remark' => $row->issue_remark,
                'action_1' => $row->action_1,
                'action_1_remark' => $row->action_1_remark,
                'replacement_tracking' => $row->replacement_tracking,
                'c_action_1' => $row->c_action_1,
                'c_action_1_remark' => $row->c_action_1_remark,
                'close_note' => $row->close_note,
                'department' => $row->department ?? null,
                'created_by' => $row->created_by,
                'created_at' => $row->created_at,
                'created_at_display' => $row->created_at
                    ? \Carbon\Carbon::parse($row->created_at)->timezone($tz)->format('d-m-Y H:i')
                    : '',
            ],
        ], 201);
    })->name('customer.care.qc.and.packing.issues.store');
    Route::put('/customer-care/qc-and-packing/issues/{id}', function (\Illuminate\Http\Request $request, int $id) {
        $validated = $request->validate([
            'sku' => 'required|string|max:128',
            'qty' => 'required|numeric|min:0',
            'order_qty' => 'nullable|numeric|min:0',
            'parent' => 'nullable|string|max:255',
            'marketplace_1' => 'nullable|string|max:255',
            'marketplace_2' => 'nullable|string|max:255',
            'what_happened' => 'nullable|string|max:50',
            'issue' => 'required|string|max:255',
            'issue_remark' => 'nullable|string|max:255',
            'action_1' => 'nullable|string|in:Offer Customer Alterntive / Updgrade,Upgraded + Stock Alternate,Alternate Sent + Stock Alternate,Sent Wrong Item + Stock Outgoing,Cancelled,Other|max:255',
            'action_1_remark' => 'nullable|string|max:255',
            'replacement_tracking' => 'nullable|string|max:50',
            'c_action_1' => 'nullable|string|max:255',
            'c_action_1_remark' => 'nullable|string|max:255',
            'close_note' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:100',
        ]);
        if (($validated['action_1'] ?? null) === 'Other' && trim((string) ($validated['action_1_remark'] ?? '')) === '') {
            return response()->json([
                'message' => 'Action remark is required when Action is Other.',
                'errors' => ['action_1_remark' => ['Action remark is required when Action is Other.']],
            ], 422);
        }
        if (($validated['c_action_1'] ?? null) === 'Other' && trim((string) ($validated['c_action_1_remark'] ?? '')) === '') {
            return response()->json([
                'message' => 'Root Cause Fixed remark is required when Root Cause Fixed is Other.',
                'errors' => ['c_action_1_remark' => ['Root Cause Fixed remark is required when Root Cause Fixed is Other.']],
            ], 422);
        }

        $existing = \Illuminate\Support\Facades\DB::table('qc_and_packing_issues')->where('id', $id)->first();
        if (! $existing) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $user = auth()->user();
        $actorName = trim((string) ($user?->name ?? 'System'));
        if ($actorName === '') {
            $actorName = 'System';
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($id, $validated, $actorName, $user) {
            $now = now();
            $nextRevision = ((int) (\Illuminate\Support\Facades\DB::table('qc_and_packing_issue_histories')
                ->where('orders_on_hold_issue_id', $id)
                ->max('revision_no'))) + 1;
            $department = isset($validated['department']) && trim((string) $validated['department']) !== ''
                ? trim((string) $validated['department'])
                : null;
            $payload = [
                'sku' => trim($validated['sku']),
                'qty' => (float) $validated['qty'],
                'order_qty' => isset($validated['order_qty']) ? (float) $validated['order_qty'] : null,
                'parent' => isset($validated['parent']) ? trim((string) $validated['parent']) : null,
                'marketplace_1' => isset($validated['marketplace_1']) ? trim((string) $validated['marketplace_1']) : null,
                'marketplace_2' => isset($validated['marketplace_2']) ? trim((string) $validated['marketplace_2']) : null,
                'what_happened' => isset($validated['what_happened']) ? trim((string) $validated['what_happened']) : null,
                'issue' => trim($validated['issue']),
                'issue_remark' => isset($validated['issue_remark']) ? trim((string) $validated['issue_remark']) : null,
                'action_1' => isset($validated['action_1']) ? trim((string) $validated['action_1']) : null,
                'action_1_remark' => isset($validated['action_1_remark']) ? trim((string) $validated['action_1_remark']) : null,
                'replacement_tracking' => isset($validated['replacement_tracking']) ? trim((string) $validated['replacement_tracking']) : null,
                'c_action_1' => isset($validated['c_action_1']) ? trim((string) $validated['c_action_1']) : null,
                'c_action_1_remark' => isset($validated['c_action_1_remark']) ? trim((string) $validated['c_action_1_remark']) : null,
                'close_note' => isset($validated['close_note']) ? trim((string) $validated['close_note']) : null,
                'department' => $department,
                'updated_at' => $now,
            ];

            \Illuminate\Support\Facades\DB::table('qc_and_packing_issues')->where('id', $id)->update($payload);

            \Illuminate\Support\Facades\DB::table('qc_and_packing_issue_histories')->insert([
                'orders_on_hold_issue_id' => $id,
                'event_type' => 'updated',
                'revision_no' => $nextRevision,
                'sku' => $payload['sku'],
                'qty' => $payload['qty'],
                'order_qty' => $payload['order_qty'],
                'parent' => $payload['parent'],
                'marketplace_1' => $payload['marketplace_1'],
                'marketplace_2' => $payload['marketplace_2'],
                'what_happened' => $payload['what_happened'],
                'issue' => $payload['issue'],
                'issue_remark' => $payload['issue_remark'],
                'action_1' => $payload['action_1'],
                'action_1_remark' => $payload['action_1_remark'],
                'replacement_tracking' => $payload['replacement_tracking'],
                'c_action_1' => $payload['c_action_1'],
                'c_action_1_remark' => $payload['c_action_1_remark'],
                'close_note' => $payload['close_note'],
                'department' => $department,
                'created_by' => $actorName,
                'created_by_user_id' => $user?->id,
                'logged_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return response()->json(['message' => 'Hold issue updated successfully.']);
    })->name('customer.care.qc.and.packing.issues.update');
    Route::post('/customer-care/qc-and-packing/issues/{id}/archive', function (int $id) {
        $row = \Illuminate\Support\Facades\DB::table('qc_and_packing_issues')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $user = auth()->user();
        $actorName = trim((string) ($user?->name ?? 'System'));
        if ($actorName === '') {
            $actorName = 'System';
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($id, $row, $actorName, $user) {
            $now = now();
            $nextRevision = ((int) (\Illuminate\Support\Facades\DB::table('qc_and_packing_issue_histories')
                ->where('orders_on_hold_issue_id', $id)
                ->max('revision_no'))) + 1;
            \Illuminate\Support\Facades\DB::table('qc_and_packing_issues')
                ->where('id', $id)
                ->update([
                    'is_archived' => true,
                    'archived_at' => $now,
                    'archived_by' => $actorName,
                    'updated_at' => $now,
                ]);

            \Illuminate\Support\Facades\DB::table('qc_and_packing_issue_histories')->insert([
                'orders_on_hold_issue_id' => $id,
                'event_type' => 'archived',
                'revision_no' => $nextRevision,
                'sku' => $row->sku,
                'qty' => (float) $row->qty,
                'order_qty' => $row->order_qty !== null ? (float) $row->order_qty : null,
                'parent' => $row->parent,
                'marketplace_1' => $row->marketplace_1 ?? null,
                'marketplace_2' => $row->marketplace_2 ?? null,
                'what_happened' => $row->what_happened ?? null,
                'issue' => $row->issue,
                'issue_remark' => $row->issue_remark ?? null,
                'action_1' => $row->action_1 ?? null,
                'action_1_remark' => $row->action_1_remark ?? null,
                'replacement_tracking' => $row->replacement_tracking ?? null,
                'c_action_1' => $row->c_action_1 ?? null,
                'c_action_1_remark' => $row->c_action_1_remark ?? null,
                'close_note' => $row->close_note ?? null,
                'department' => $row->department ?? null,
                'created_by' => $actorName,
                'created_by_user_id' => $user?->id,
                'logged_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return response()->json(['message' => 'Hold issue archived successfully.']);
    })->name('customer.care.qc.and.packing.issues.archive');
    Route::get('/customer-care/qc-and-packing/dropdown-options', function (\Illuminate\Http\Request $request) {
        $fieldType = trim((string) $request->query('field_type', ''));
        if (! in_array($fieldType, ['root_cause_found', 'root_cause_fixed'], true)) {
            return response()->json(['message' => 'Invalid field type.'], 422);
        }

        $options = \Illuminate\Support\Facades\DB::table('customer_care_issue_dropdown_options')
            ->where('module_key', 'qc_and_packing')
            ->where('field_type', $fieldType)
            ->orderBy('option_value')
            ->pluck('option_value')
            ->values();

        return response()->json(['data' => $options]);
    })->name('customer.care.qc.and.packing.dropdown.options.index');
    Route::post('/customer-care/qc-and-packing/dropdown-options', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'field_type' => 'required|string',
            'option_value' => 'required|string|max:255',
        ]);

        $fieldType = trim((string) $validated['field_type']);
        if (! in_array($fieldType, ['root_cause_found', 'root_cause_fixed'], true)) {
            return response()->json(['message' => 'Invalid field type.'], 422);
        }
        $optionValue = trim((string) $validated['option_value']);
        if ($optionValue === '') {
            return response()->json(['message' => 'Option value is required.'], 422);
        }

        $exists = \Illuminate\Support\Facades\DB::table('customer_care_issue_dropdown_options')
            ->where('module_key', 'qc_and_packing')
            ->where('field_type', $fieldType)
            ->whereRaw('LOWER(option_value) = ?', [strtolower($optionValue)])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Option already exists.'], 409);
        }

        \Illuminate\Support\Facades\DB::table('customer_care_issue_dropdown_options')->insert([
            'module_key' => 'qc_and_packing',
            'field_type' => $fieldType,
            'option_value' => $optionValue,
            'created_by_user_id' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Option added successfully.']);
    })->name('customer.care.qc.and.packing.dropdown.options.store');
    Route::post('/customer-care/qc-and-packing/dropdown-options/delete', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'field_type' => 'required|string',
            'option_value' => 'required|string|max:255',
        ]);

        $fieldType = trim((string) $validated['field_type']);
        if (! in_array($fieldType, ['root_cause_found', 'root_cause_fixed'], true)) {
            return response()->json(['message' => 'Invalid field type.'], 422);
        }
        $optionValue = trim((string) $validated['option_value']);

        \Illuminate\Support\Facades\DB::table('customer_care_issue_dropdown_options')
            ->where('module_key', 'qc_and_packing')
            ->where('field_type', $fieldType)
            ->whereRaw('LOWER(option_value) = ?', [strtolower($optionValue)])
            ->delete();

        return response()->json(['message' => 'Option deleted successfully.']);
    })->name('customer.care.qc.and.packing.dropdown.options.delete');

    Route::post('/customer-care/qc-and-packing/import-csv', function (\Illuminate\Http\Request $request) {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);
        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $headers = null;
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        $user = auth()->user();
        $createdBy = trim((string) ($user?->name ?? 'System')) ?: 'System';
        $map = ['sku' => ['sku'], 'qty' => ['qty', 'quantity'], 'order_qty' => ['order_qty', 'order qty'], 'parent' => ['parent'], 'marketplace_1' => ['marketplace_1', 'mkt1'], 'marketplace_2' => ['marketplace_2', 'mkt2'], 'what_happened' => ['what_happened', 'what?', 'what happened'], 'action_1' => ['action_1', 'action', 'action 1'], 'action_1_remark' => ['action_1_remark', 'action remark'], 'replacement_tracking' => ['replacement_tracking', 'replacement tracking'], 'issue' => ['issue', 'root_cause_found', 'root cause found'], 'issue_remark' => ['issue_remark', 'root cause remark'], 'c_action_1' => ['c_action_1', 'root_cause_fixed', 'root cause fixed'], 'c_action_1_remark' => ['c_action_1_remark', 'root cause fixed remark'], 'department' => ['department', 'dept']];
        while (($row = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $row);

                continue;
            }
            if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }
            $data = array_combine($headers, array_pad($row, count($headers), ''));
            $get = function ($f) use ($data, $map) {
                foreach ($map[$f] ?? [$f] as $a) {
                    if (array_key_exists($a, $data) && trim((string) $data[$a]) !== '') {
                        return trim((string) $data[$a]);
                    }
                }

                return null;
            };
            $sku = $get('sku');
            if (! $sku) {
                $skipped++;
                $errors[] = 'Row skipped: SKU empty.';

                continue;
            }
            $qty = $get('qty');
            if ($qty === null || ! is_numeric($qty)) {
                $skipped++;
                $errors[] = "Row skipped (SKU=$sku): invalid QTY.";

                continue;
            }
            $issue = $get('issue');
            if (! $issue) {
                $skipped++;
                $errors[] = "Row skipped (SKU=$sku): Root Cause Found required.";

                continue;
            }
            try {
                $now = now();
                $payload = ['sku' => $sku, 'qty' => (float) $qty, 'order_qty' => $get('order_qty') !== null ? (float) $get('order_qty') : null, 'parent' => $get('parent'), 'marketplace_1' => $get('marketplace_1'), 'marketplace_2' => $get('marketplace_2'), 'what_happened' => $get('what_happened'), 'issue' => $issue, 'issue_remark' => $get('issue_remark'), 'action_1' => $get('action_1'), 'action_1_remark' => $get('action_1_remark'), 'replacement_tracking' => $get('replacement_tracking'), 'c_action_1' => $get('c_action_1'), 'c_action_1_remark' => $get('c_action_1_remark'), 'department' => $get('department'), 'created_by' => $createdBy, 'created_by_user_id' => $user?->id, 'created_at' => $now, 'updated_at' => $now];
                \Illuminate\Support\Facades\DB::transaction(function () use ($payload, $now) {
                    $id = \Illuminate\Support\Facades\DB::table('qc_and_packing_issues')->insertGetId($payload);
                    \Illuminate\Support\Facades\DB::table('qc_and_packing_issue_histories')->insert(array_merge($payload, ['orders_on_hold_issue_id' => $id, 'event_type' => 'created', 'revision_no' => 0, 'logged_at' => $now]));
                });
                $inserted++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Row failed (SKU=$sku): ".$e->getMessage();
            }
        }
        fclose($handle);

        return response()->json(['message' => "{$inserted} record(s) imported, {$skipped} skipped.", 'inserted' => $inserted, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)]);
    })->name('customer.care.qc.and.packing.issues.import');

    Route::post('/customer-care/label-issues/import-csv', [\App\Http\Controllers\CustomerCare\LabelIssuesController::class, 'importCsv'])
        ->name('customer.care.label.issues.import');
    Route::post('/customer-care/all-issues/import-csv', [\App\Http\Controllers\CustomerCare\DispatchIssuesController::class, 'importCsv'])
        ->name('customer.care.dispatch.issues.import');
    Route::post('/customer-care/listing-issue/import-csv', [\App\Http\Controllers\CustomerCare\ListingIssueController::class, 'importCsv'])
        ->name('customer.care.listing.issue.import');
    Route::post('/customer-care/c-care-issues/import-csv', [\App\Http\Controllers\CustomerCare\CCareIssuesController::class, 'importCsv'])
        ->name('customer.care.c.care.issues.import');
    Route::post('/customer-care/other-issues/import-csv', [\App\Http\Controllers\CustomerCare\OtherIssuesController::class, 'importCsv'])
        ->name('customer.care.other.issues.import');

    Route::get('/customer-care/followups', [CustomerFollowupController::class, 'index'])->name('customer.care.followups');
    Route::get('/customer-care/followups/data', [CustomerFollowupController::class, 'data'])->name('customer.care.followups.data');
    Route::get('/customer-care/followups/skus', [CustomerFollowupController::class, 'searchProductSkus'])->name('customer.care.followups.skus');
    Route::post('/customer-care/followups', [CustomerFollowupController::class, 'store'])->name('customer.care.followups.store');
    Route::get('/customer-care/followups/{customer_followup}', [CustomerFollowupController::class, 'show'])->name('customer.care.followups.show');
    Route::put('/customer-care/followups/{customer_followup}', [CustomerFollowupController::class, 'update'])->name('customer.care.followups.update');
    Route::delete('/customer-care/followups/{customer_followup}', [CustomerFollowupController::class, 'destroy'])->name('customer.care.followups.destroy');

    require __DIR__.'/crm-web.php';

    Route::get('/dar', [DARController::class, 'index'])->name('dar.index');
    Route::post('/dar', [DARController::class, 'store'])->name('dar.store');
    Route::get('/dar/window-status', [DARController::class, 'windowStatus'])->name('dar.window');

    // incoming
    Route::get('/incoming-view', [IncomingController::class, 'index'])->name('incoming.view');
    Route::get('/incoming-sku-lookup', [IncomingController::class, 'lookupSku'])->name('incoming.sku.lookup');
    Route::post('/incoming-data-store', [IncomingController::class, 'store'])->name('incoming.store');
    Route::get('/incoming-data-list', [IncomingController::class, 'list']);
    Route::get('/incoming-return-merged-list', [IncomingController::class, 'listIncomingReturnViewMerged'])->name('incoming.return.merged.list');
    Route::get('/incoming-return-view', [IncomingController::class, 'incomingReturnIndex'])->name('incoming.return.view');
    Route::post('/incoming-return-store', [IncomingController::class, 'storeReturn'])->name('incoming.return.store');
    Route::patch('/incoming-return-row/{inventory}/restock', [IncomingController::class, 'updateIncomingReturnRowRestock'])->name('incoming.return.row.restock');
    Route::get('/incoming-return-history-list', [IncomingController::class, 'listReturnHistory']);
    Route::get('/incoming-return-sku-suggest', [IncomingController::class, 'suggestSkusForReturn'])->name('incoming.return.sku.suggest');
    Route::get('/incoming-return-grid-inventory', [IncomingController::class, 'getIncomingReturnGridInventory']);
    Route::get('/view-inventory-incoming-return-trash', [IncomingController::class, 'viewInventoryIncomingReturnTrash'])->name('view-inventory-incoming-return-trash');
    Route::get('/view-inventory-incoming-return-open-box', [IncomingController::class, 'viewInventoryIncomingReturnOpenBox'])->name('view-inventory-incoming-return-open-box');

    // incoming orders
    Route::get('/incoming-orders-view', [IncomingController::class, 'incomingOrderIndex'])->name('incoming.orders.view');
    Route::post('/incoming-orders-store', [IncomingController::class, 'incomingOrderStore'])->name('incoming.orders.store');
    Route::get('/incoming-orders-list', [IncomingController::class, 'incomingOrderList']);
    Route::get('/incoming-reasons', [IncomingController::class, 'getIncomingReasons']);
    Route::post('/incoming-reasons', [IncomingController::class, 'storeIncomingReason']);

    // outgoing
    Route::get('/outgoing-view', [OutgoingController::class, 'index'])->name('outgoing.view');
    Route::post('/outgoing-data-store', [OutgoingController::class, 'store'])->name('outgoing.store');
    Route::get('/outgoing-data-list', [OutgoingController::class, 'list']);
    Route::get('/outgoing-reasons', [OutgoingController::class, 'getReasons']);
    Route::post('/outgoing-reasons', [OutgoingController::class, 'storeReason']);
    Route::put('/outgoing-update-reason-comment', [OutgoingController::class, 'updateReasonAndComment']);
    Route::get('/outgoing-history/{id}', [OutgoingController::class, 'getHistory']);

    Route::get('/inventory/spare-parts/api/requisitions', [SparePartsRequisitionController::class, 'index'])->name('inventory.spare-parts.api.requisitions.index');
    Route::post('/inventory/spare-parts/api/requisitions', [SparePartsRequisitionController::class, 'store'])->name('inventory.spare-parts.api.requisitions.store');
    Route::post('/inventory/spare-parts/api/requisitions/{requisition}/submit', [SparePartsRequisitionController::class, 'submit'])->name('inventory.spare-parts.api.requisitions.submit');
    Route::post('/inventory/spare-parts/api/requisitions/{requisition}/approve', [SparePartsRequisitionController::class, 'approve'])->name('inventory.spare-parts.api.requisitions.approve');
    Route::post('/inventory/spare-parts/api/requisitions/{requisition}/close', [SparePartsRequisitionController::class, 'close'])->name('inventory.spare-parts.api.requisitions.close');

    Route::get('/inventory/spare-parts/api/issues/pending', [SparePartsIssueController::class, 'pending'])->name('inventory.spare-parts.api.issues.pending');
    Route::post('/inventory/spare-parts/api/issues', [SparePartsIssueController::class, 'store'])->name('inventory.spare-parts.api.issues.store');

    Route::get('/inventory/spare-parts/api/purchase-orders', [SparePartPurchaseOrderController::class, 'index'])->name('inventory.spare-parts.api.purchase-orders.index');
    Route::post('/inventory/spare-parts/api/purchase-orders', [SparePartPurchaseOrderController::class, 'store'])->name('inventory.spare-parts.api.purchase-orders.store');
    Route::post('/inventory/spare-parts/api/purchase-orders/{sparePartPurchaseOrder}/send', [SparePartPurchaseOrderController::class, 'send'])->name('inventory.spare-parts.api.purchase-orders.send');
    Route::post('/inventory/spare-parts/api/purchase-orders/{sparePartPurchaseOrder}/receive', [SparePartPurchaseOrderController::class, 'receive'])->name('inventory.spare-parts.api.purchase-orders.receive');

    Route::get('/inventory/spares/dashboard', [SpareController::class, 'index'])->name('inventory.spares.dashboard');
    Route::post('/inventory/spares/store', [SpareController::class, 'storeSpare'])->name('inventory.spares.store');
    Route::post('/inventory/spares/requisitions/create', [SpareController::class, 'createRequisition'])->name('inventory.spares.requisitions.create');
    Route::post('/inventory/spares/requisitions/{requisition}/status', [SpareController::class, 'updateRequisitionStatus'])->name('inventory.spares.requisitions.status');
    Route::post('/inventory/spares/issue', [SpareController::class, 'issueSpare'])->name('inventory.spares.issue');
    Route::post('/inventory/spares/purchase-orders/create', [SpareController::class, 'createPO'])->name('inventory.spares.purchase-orders.create');
    Route::post('/inventory/spares/purchase-orders/{purchaseOrder}/receive', [SpareController::class, 'receivePO'])->name('inventory.spares.purchase-orders.receive');
    Route::post('/outgoing-archive', [OutgoingController::class, 'archive']);

    Route::get('/refunds-view', [RefundController::class, 'index'])->name('refunds.view');
    Route::post('/refunds-data-store', [RefundController::class, 'store'])->name('refunds.store');
    Route::get('/refunds-data-list', [RefundController::class, 'list']);
    Route::get('/refunds-supplier-for-sku', [RefundController::class, 'supplierForSku'])->name('refunds.supplier-for-sku');
    Route::get('/refunds-stats-30d', [RefundController::class, 'refundStatsLast30Days'])->name('refunds.stats-30d');
    Route::get('/refunds-reasons', [RefundController::class, 'getReasons']);
    Route::post('/refunds-reasons', [RefundController::class, 'storeReason']);
    Route::put('/refunds-update-reason-comment', [RefundController::class, 'updateReasonAndComment']);
    Route::get('/refunds-history/{id}', [RefundController::class, 'getHistory']);
    Route::post('/refunds-archive', [RefundController::class, 'archive']);
    Route::post('/refunds-import-csv', [RefundController::class, 'importCsv'])->name('refunds.import.csv');

    // show updated qty

    // Stock Adjustment
    Route::get('/stock-adjustment-view', [StockAdjustmentController::class, 'index'])->name('stock.adjustment.view');
    Route::post('/stock-adjustment-store', [StockAdjustmentController::class, 'store'])->name('stock.adjustment.store');
    Route::post('/stock-adjustment-bulk-csv', [StockAdjustmentController::class, 'processBulkCSV']);
    Route::get('/stock-adjustment-data-list', [StockAdjustmentController::class, 'list']);

    // Stock Transfer
    Route::get('/stock-transfer-view', [StockTransferController::class, 'index'])->name('stock.transfer.view');
    Route::post('/stock-transfer-store', [StockTransferController::class, 'store'])->name('stock.transfer.store');
    Route::get('/stock-transfer-data-list', [StockTransferController::class, 'list']);

    // Stock Balance
    Route::get('/stock-balance-view', [StockBalanceController::class, 'index'])->name('stock.balance.view');
    Route::get('/stock-balance-tabulator', [StockBalanceController::class, 'tabulatorView'])->name('stock.balance.tabulator');
    Route::get('/stock-balance-alternate', [StockBalanceController::class, 'alternateView'])->name('stock.balance.alternate');
    Route::post('/stock-balance-store', [StockBalanceController::class, 'store'])->name('stock.balance.store');
    Route::get('/stock-balance-data-list', [StockBalanceController::class, 'list']);
    Route::get('/stock-balance-inventory-data', [StockBalanceController::class, 'getInventoryData']);
    Route::post('/stock-balance-update-action', [StockBalanceController::class, 'updateAction']);
    Route::get('/stock-balance-get-relationships', [StockBalanceController::class, 'getRelationships']);
    Route::post('/stock-balance-add-relationships', [StockBalanceController::class, 'addRelationships']);
    Route::post('/stock-balance-delete-relationship', [StockBalanceController::class, 'deleteRelationship']);
    Route::get('/stock-balance-get-skus-autocomplete', [StockBalanceController::class, 'getSkusForAutocomplete']);
    Route::get('/stock-balance-get-recent-history', [StockBalanceController::class, 'getRecentHistory']);
    Route::get('/stock-balance-search-history', [StockBalanceController::class, 'searchHistory']);
    Route::get('/stock-balance-transfer-preferences', [StockBalanceController::class, 'getTransferPreferences']);
    Route::post('/stock-balance-transfer-preferences', [StockBalanceController::class, 'saveTransferPreference']);
    Route::get('/combo-trf', [StockBalanceController::class, 'comboTrfView'])->name('combo.trf');
    Route::post('/combo-trf-store', [StockBalanceController::class, 'storeComboTrf'])->name('combo.trf.store');
    Route::get('/combo-trf-inventory-data', [StockBalanceController::class, 'getComboTrfInventoryData']);
    Route::post('/combo-trf-update-action', [StockBalanceController::class, 'updateComboTrfAction']);

    // Multi-SKU Stock Balance
    Route::get('/stock-balance-multi-sku', [StockBalanceController::class, 'multiSkuView'])->name('stock.balance.multi.sku');
    Route::post('/stock-balance-multi-sku-data', [StockBalanceController::class, 'getMultiSkuInventoryData']);

    // channel Movement Analysis
    Route::get('/channel-movement-analysis', [ChannelMovementAnalysisController::class, 'index'])->name('channel.movement.analysis');
    Route::get('/channel-analysis/{channel}', [ChannelMovementAnalysisController::class, 'show'])->name('channel.show');
    Route::post('/channel-analysis/update', [ChannelMovementAnalysisController::class, 'updateField'])->name('channel.updateField');
    Route::get('/channels/get-monthly-data/{channel}', [ChannelMovementAnalysisController::class, 'getMonthlyData'])->name('channels.getMonthlyData');

    // New Marketplaces
    Route::get('/new-marketplaces-dashboard', [NewMarketplaceController::class, 'index'])->name('new.marketplaces.dashboard');
    Route::get('/channels/fetch', [NewMarketplaceController::class, 'getChannelsFromGoogleSheet'])->name('channels.fetch');
    Route::post('/new-marketplaces-store', [NewMarketplaceController::class, 'store'])->name('new.marketplaces.store');
    Route::get('/new-marketplaces-by-status', [NewMarketplaceController::class, 'getMarketplacesByStatus'])->name('new.marketplaces.byStatus');
    Route::post('/new-marketplaces/import', [NewMarketplaceController::class, 'import'])->name('new-marketplaces.import');
    Route::get('/new-marketplaces/export', [NewMarketplaceController::class, 'export'])->name('new-marketplaces.export');
    Route::get('/edit-new-marketplaces/{id}', [NewMarketplaceController::class, 'edit']);
    Route::get('/new-marketplaces/{id}', [NewMarketplaceController::class, 'show']);
    Route::post('/new-marketplaces/{id}', [NewMarketplaceController::class, 'update']);
    Route::post('/marketplaces-update-status', [NewMarketplaceController::class, 'updateStatus'])->name('marketplaces.updateStatus');

    // Route::post('/new-marketplaces/update/{id}', [NewMarketplaceController::class, 'update']);

    // Warehouse
    Route::get('/list_all_warehouses', [WarehouseController::class, 'index'])->name('list_all_warehouses');
    Route::post('/warehouses/store', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::get('/warehouses/list', [WarehouseController::class, 'list']);
    Route::get('/warehouses/{id}/edit', [WarehouseController::class, 'edit']);
    Route::post('/warehouses/update/{id}', [WarehouseController::class, 'update'])->name('warehouses.update');
    Route::delete('/warehouses/{id}', [WarehouseController::class, 'destroy']);

    Route::get('/main-godown', [WarehouseController::class, 'mainGodown'])->name('main.godown');
    Route::get('/return-godown', [WarehouseController::class, 'returnGodown'])->name('returns.godown');
    Route::get('/openbox-godown', [WarehouseController::class, 'openBoxGodown'])->name('openbox.godown');
    Route::get('/showroom-godown', [WarehouseController::class, 'showroomGodown'])->name('showroom.godown');
    Route::get('/useditem-godown', [WarehouseController::class, 'usedItemGodown'])->name('useditem.godown');
    Route::get('/trash-godown', [WarehouseController::class, 'trashGodown'])->name('trash.godown');

    // Purchase Order
    Route::controller(PurchaseOrderController::class)->group(function () {
        Route::get('/list-all-purchase-orders', 'index')->name('list-all-purchase-orders');
        Route::post('/store-purchase-orders', 'store')->name('purchase-orders.store');
        Route::get('/purchase-orders/list', 'getPurchaseOrdersData')->name('purchase-orders.data');
        Route::get('/purchase-orders/convert', 'convert')->name('purchase-orders.convert');
        Route::get('/purchase-order/{id}/generate-pdf', 'generatePdf')->name('generate-pdf');
        Route::post('/purchase-orders/delete', 'deletePurchaseOrders');
        Route::post('/purchase-orders/{id}/archive', 'archivePurchaseOrder');
        Route::post('/purchase-orders/{id}/restore', 'restorePurchaseOrder');
        Route::get('/purchase-orders/{id}', 'showPurchaseOrders');
        Route::post('/purchase-orders/{id}', 'updatePurchaseOrder');
    });

    // Purchase
    Route::controller(PurchaseController::class)->group(function () {
        Route::get('/purchase/list', 'index')->name('purchase.index');
        Route::get('/purchase-orders/items-by-supplier/{supplier_id}', 'getItemsBySupplier');
        Route::get('/product-master/get-parent/{sku}', 'getParentBySku');
        Route::get('/purchase/search-sku', 'searchSku');
        Route::post('/purchase/save', 'store')->name('purchase.store');
        Route::get('/purchase-data/list', 'getPurchaseSummary');
        Route::post('/purchase/delete', 'deletePurchase');
    });

    // RFQ Form
    Route::controller(RFQController::class)->group(function () {
        Route::get('/rfq-form/list', 'index')->name('rfq-form.index');
        Route::get('rfq-form/data', 'getRfqFormsData');
        Route::post('/rfq-form/store', 'storeRFQForm')->name('rfq-form.store');
        Route::get('/rfq-form/edit/{id}', 'edit')->name('rfq-form.edit');
        Route::post('/rfq-form/update/{id}', 'update')->name('rfq-form.update');
        Route::delete('/rfq-form/delete/{id}', 'destroy')->name('rfq-form.destroy');

        // form reports
        Route::get('/rfq-form/reports/{id}', 'rfqReports')->name('rfq-form.reports');
        Route::get('/rfq-form/reports-data/{id}', 'getRfqReportsData')->name('rfq-form.reports.data');

        // supplier email
        Route::get('/rfq-form/suppliers/search', 'searchSuppliers')->name('rfq-form.suppliers.search');
        Route::post('/rfq-form/send-email', 'sendEmailToSuppliers')->name('rfq-form.send-email');
    });

    // Sourcingƒvies
    Route::controller(SourcingController::class)->group(function () {
        Route::get('/sourcing/list', 'index')->name('sourcing.index');
        Route::get('/sourcing-data/list', 'getSourcingData')->name('sourcing.list');
        Route::post('/sourcing/save', 'storeSourcing')->name('sourcing.save');
        Route::post('/sourcing/update/{id}', 'updateSourcing')->name('sourcing.update');
        Route::post('/sourcing/delete', 'deleteSourcing')->name('sourcing.delete');
        Route::get('/get-parent-by-sku/{sku}', 'getParentBySku')->name('getParentBySku');
    });

    // LedgerMaster
    Route::controller(LedgerMasterController::class)->group(function () {
        Route::get('/ledger-master/advance-and-payments/', 'advanceAndPayments')->name('ledger.advance.payments');
        Route::get('/ledger-master/supplier-ledger/', 'supplierLedger')->name('supplier.ledger');
        Route::post('/ledger-master/supplier-ledger-save', 'supplierStore')->name('supplier.ledger.save');
        Route::post('/supplier-ledger/update', 'updateSupplierLedger')->name('supplier.ledger.update');
        Route::get('/supplier-ledger/get-balance', 'getSupplierBalance')->name('supplier.ledger.get-balance');
        Route::get('/supplier-ledger/list', 'fetchSupplierLedgerData');
        Route::post('/advance-payments/save', 'saveAdvancePayments')->name('advance.payments.save');
        Route::put('/advance-payments/update', 'updateAdvancePayments')->name('advance.payments.update');
        Route::get('/advance-and-payments/data', 'getAdvancePaymentsData');
        Route::post('/advance-payments/delete', 'deleteAdvancePayments');
        Route::post('/supplier-ledger/delete', 'deleteSupplierLedger');
    });

    // Doba Routes
    Route::get('/zero-doba', [DobaZeroController::class, 'dobaZeroview'])->name('zero.doba');
    Route::get('/zero_doba/view-data', [DobaZeroController::class, 'getViewDobaZeroData']);
    Route::post('/zero_doba/reason-action/update-data', [DobaZeroController::class, 'updateReasonAction']);

    Route::get('/listing-doba', [ListingDobaController::class, 'listingDoba'])->name('listing.doba');
    Route::get('/listing_doba/view-data', [ListingDobaController::class, 'getViewListingDobaData']);
    Route::post('/listing_doba/save-status', [ListingDobaController::class, 'saveStatus']);
    Route::post('/listing_doba/import', [ListingDobaController::class, 'import'])->name('listing_doba.import');
    Route::get('/listing_doba/export', [ListingDobaController::class, 'export'])->name('listing_doba.export');

    Route::get('/doba-data-view', [DobaController::class, 'getViewDobaData']);
    Route::get('/doba', [DobaController::class, 'dobaView'])->name('doba');
    Route::post('/doba/save-nr', [DobaController::class, 'saveNrToDatabase']);
    Route::post('/doba/update-listed-live', [DobaController::class, 'updateListedLive']);
    Route::post('/doba/saveLowProfit', [DobaController::class, 'saveLowProfit']);
    Route::post('/update-doba-pricing', [DobaController::class, 'updatePrice']);
    Route::get('/doba-pricing-cvr', [DobaController::class, 'dobaPricingCVR']);
    Route::get('/doba-tabulator', [DobaController::class, 'dobaTabulatorView']);
    Route::get('/doba-data-view-withoutship', [DobaController::class, 'getViewDobaDataWithoutShip']);
    Route::get('/doba-tabulator-withoutship', [DobaController::class, 'dobaTabulatorViewWithoutShip'])->name('doba.withoutship.tabulator');
    Route::get('/doba_withoutship', [DobaController::class, 'dobaTabulatorViewWithoutShip'])->name('doba.withoutship');
    Route::get('/doba/summary-metrics', [DobaController::class, 'getDobaSummaryMetrics']);
    Route::post('/doba/save-sprice', [DobaController::class, 'saveSpriceToDatabase'])->name('doba.save-sprice');
    Route::post('/doba/push-price', [DobaController::class, 'pushPriceToDoba'])->name('doba.push-price');
    Route::post('/update-all-doba-skus', [DobaController::class, 'updateAllDobaSkus']);
    Route::post('/doba-analytics/import', [DobaController::class, 'importDobaAnalytics'])->name('doba.analytics.import');
    Route::get('/doba-analytics/export', [DobaController::class, 'exportDobaAnalytics'])->name('doba.analytics.export');
    Route::get('/doba-analytics/sample', [DobaController::class, 'downloadSample'])->name('doba.analytics.sample');

    // update sku inv and l30
    Route::post('/update-all-amazon-skus', [OverallAmazonController::class, 'updateAllAmazonSkus']);
    Route::post('/update-all-amazon-fba-skus', [OverallAmazonFbaController::class, 'updateAllAmazonfbaSkus']);
    Route::post('/update-all-ebay1-skus', [EbayController::class, 'updateAllEbaySkus']);
    Route::post('/update-all-ebay-skus', [EbayTwoController::class, 'updateAllEbay2Skus']);

    Route::post('/update-all-shopifyB2C-skus', [Shopifyb2cController::class, 'updateAllShopifyB2CSkus']);
    Route::post('/update-all-macy-skus', [MacyController::class, 'updateAllMacySkus']);
    Route::post('/update-all-neweggb2c-skus', [Neweggb2cController::class, 'updateAllNeweggB2CSkus']);
    Route::post('/update-all-reverb-skus', [ReverbController::class, 'updateAllAReverbSkus']);
    Route::post('/update-all-wayfair-skus', [WayfairController::class, 'updateAllWayfairSkus']);
    Route::post('/update-all-reverb-skus', [ReverbController::class, 'updateAllReverbSkus']);
    Route::post('/update-all-temu-skus', [TemuController::class, 'updateAllTemuSkus']);
    Route::post('/update-amazon-price', action: [OverallAmazonController::class, 'updatePrice'])->name('amazon.priceChange');

    Route::get('/amazon-ads/all', [AmazonAdsController::class, 'index'])->name('amazon.ads.all');
    Route::match(['get', 'post'], '/amazon-ads/raw-data/{source}', [AmazonAdsController::class, 'rawData'])->name('amazon.ads.raw-data');

    // ajax routes
    Route::get('/amazon/all-data', [OverallAmazonController::class, 'getAllData'])->name('amazon.allData');
    Route::get('/channel/all-data', [ChannelMasterController::class, 'getAllData'])->name('channel.allData');
    Route::get('/amazon/view-data', [OverallAmazonController::class, 'getViewAmazonData'])->name('amazon.viewData');
    Route::post('/update-fba-status', [OverallAmazonController::class, 'updateFbaStatus'])
        ->name('update.fba.status');
    Route::get('/listing_audit_amazon/view-data', [ListingAuditAmazonController::class, 'getViewListingAuditAmazonData']);
    Route::get('/listing_audit_ebay/view-data', [ListingAuditEbayController::class, 'getViewListingAuditEbayData']);
    Route::get('/listing_ebay/view-data', [ListingEbayController::class, 'getViewListingEbayData']);
    Route::get('/amazon/zero/view-data', [AmazonZeroController::class, 'getViewAmazonZeroData'])->name('amazon.zero.viewData');
    Route::get('/amazon/low-visibility/view-data', [AmazonLowVisibilityController::class, 'getViewAmazonLowVisibilityData']);
    Route::get('/amazon/low-visibility/view-data-fba', [AmazonLowVisibilityController::class, 'getViewAmazonLowVisibilityDataFba']);
    Route::get('/amazon/low-visibility/view-data-fbm', [AmazonLowVisibilityController::class, 'getViewAmazonLowVisibilityDataFbm']);
    Route::get('/amazon/low-visibility/view-data-both', [AmazonLowVisibilityController::class, 'getViewAmazonLowVisibilityDataBoth']);
    Route::get('/amazon/low-visibility/campaign-clicks', [AmazonLowVisibilityController::class, 'getCampaignClicksBySku']);
    Route::get('/amazon/low-visibility/daily-views-data', [AmazonLowVisibilityController::class, 'getDailyViewsData']);

    Route::get('/ad-cvr-ebay', action: [EbayZeroController::class, 'adcvrEbay'])->name('adcvr.ebay');
    Route::get('/ad-cvr-ebay-data', action: [EbayZeroController::class, 'adcvrEbayData'])->name('adcvr.ebay.data');
    Route::post('/update-ebay-price', [EbayZeroController::class, 'updateEbayPrice'])->name('update.ebay.price');

    Route::get('/ebay/zero/view-data', [EbayZeroController::class, 'getVieweBayZeroData'])->name('ebay.zero.viewData');
    Route::get('/ebay/low-visibility/view-data', [EbayLowVisibilityController::class, 'getVieweBayLowVisibilityData']);
    Route::get('/ebay2/low-visibility/view-data', [Ebay2LowVisibilityController::class, 'getVieweBay2LowVisibilityData']);
    Route::get('/ebay3/low-visibility/view-data', [Ebay3LowVisibilityController::class, 'getVieweBay3LowVisibilityData']);
    Route::get('/reverb/view-data', [ReverbController::class, 'getViewReverbData']);
    Route::get('/shopifyB2C/view-data', [Shopifyb2cZeroController::class, 'getViewShopifyB2CZeroData']);
    Route::get('/shopifyB2C/low-visibility/view-data', [Shopifyb2cLowVisibilityController::class, 'getViewShopifyB2CLowVisibilityData']);
    Route::get('/Macy/view-data', [MacyZeroController::class, 'getViewMacyZeroData']);
    Route::get('/Macy/low-visibility/view-data', [MacyLowVisibilityController::class, 'getViewMacyLowVisibilityData']);
    Route::get('/Neweggb2c/view-data', [Neweggb2cZeroController::class, 'getViewNeweggB2CZeroData']);
    Route::get('/Neweggb2c/low-visiblity/view-data', [Neweggb2cLowVisibilityController::class, 'getViewNeweggB2CLowVisibilityData']);
    Route::get('/Wayfaire/view-data', [WayfairZeroController::class, 'getViewWayfairZeroData']);
    Route::get('/Wayfaire/low-visibility/view-data', [WayfairLowVisibilityController::class, 'getViewWayfairLowVisibilityData']);
    Route::get('/Temu/view-data', [TemuZeroController::class, 'getViewTemuZeroData']);
    Route::get('/reverb/zero/view', [ReverbZeroController::class, 'index'])->name('reverb.zero.view');
    Route::get('/reverb/low-visibility/view', [ReverbLowVisibilityController::class, 'reverbLowVisibilityview'])->name('reverb.low.visibility.view');
    Route::get('/Temu/low-visibility/view-data', [TemuLowVisibilityController::class, 'getViewTemuLowVisibilityData']);
    Route::get('/reverb/zero/view-data', [ReverbZeroController::class, 'getZeroViewData']);
    Route::get('/zero-reverb/view-data', [ReverbZeroController::class, 'getViewReverbZeroData']);
    Route::get('/reverb/zero-low-visibility/view-data', [ReverbLowVisibilityController::class, 'getViewReverbLowVisibilityData']);
    Route::get('/temu/view-data', [TemuController::class, 'getViewTemuData']);
    Route::post('/temu/upload-daily-data-chunk', [TemuController::class, 'uploadDailyDataChunk']);
    Route::post('/temu/upload-daily-data-l60-chunk', [TemuController::class, 'uploadDailyDataL60Chunk']);
    Route::post('/temu/upload-daily-data-l7-chunk', [TemuController::class, 'uploadDailyDataL7Chunk']);
    Route::get('/temu/download-daily-data-sample', [TemuController::class, 'downloadDailyDataSample'])->name('temu.daily.sample');
    Route::get('/temu/daily-data', [TemuController::class, 'getDailyData'])->name('temu.daily.data');
    Route::get('/ebay/daily-sales-data', [EbaySalesController::class, 'getData'])->name('ebay.daily.sales.data');
    Route::get('/ebay/daily-sales', [EbaySalesController::class, 'index'])->name('ebay.daily.sales');
    Route::get('/ebay-daily-sales-column-visibility', [EbaySalesController::class, 'getColumnVisibility']);
    Route::post('/ebay-daily-sales-column-visibility', [EbaySalesController::class, 'saveColumnVisibility']);
    Route::get('/ebay/sku-sales-data', [EbaySalesController::class, 'getSkuSalesData'])->name('ebay.sku.sales.data');

    // Wayfair Sales Routes
    Route::get('/wayfair/daily-sales-data', [WayfairSalesController::class, 'getData'])->name('wayfair.daily.sales.data');
    Route::get('/wayfair/daily-sales', [WayfairSalesController::class, 'index'])->name('wayfair.daily.sales');
    Route::get('/wayfair-daily-sales-column-visibility', [WayfairSalesController::class, 'getColumnVisibility']);
    Route::post('/wayfair-daily-sales-column-visibility', [WayfairSalesController::class, 'saveColumnVisibility']);
    Route::get('/wayfair/sku-sales-data', [WayfairSalesController::class, 'getSkuSalesData'])->name('wayfair.sku.sales.data');
    Route::get('/wayfair/test-calculation', [WayfairSalesController::class, 'testCalculation'])->name('wayfair.test.calculation');

    Route::get('/wayfair-pricing', [WayfairController::class, 'wayfairPricingView'])->name('wayfair.pricing.view');
    Route::get('/wayfair/pricing-data', [WayfairController::class, 'getWayfairPricingData'])->name('wayfair.pricing.data');
    Route::get('/wayfair/pricing-price-sample', [WayfairController::class, 'downloadWayfairPricingPriceSample'])->name('wayfair.pricing.price.sample');
    Route::post('/wayfair/pricing-upload-price', [WayfairController::class, 'uploadWayfairPricingPriceSheet'])->name('wayfair.pricing.upload.price');
    Route::post('/wayfair/pricing-save-sprice', [WayfairController::class, 'saveWayfairSpriceUpdates'])->name('wayfair.pricing.save.sprice');
    Route::get('/wayfair/badge-chart-data', [WayfairController::class, 'wayfairBadgeChartData'])->name('wayfair.pricing.badge.chart');
    Route::get('/wayfair/pricing-column-visibility', [WayfairController::class, 'getWayfairPricingColumnVisibility'])->name('wayfair.pricing.column.get');
    Route::post('/wayfair/pricing-column-visibility', [WayfairController::class, 'setWayfairPricingColumnVisibility'])->name('wayfair.pricing.column.set');

    // Best Buy Sales Routes
    Route::get('/bestbuy/daily-sales-data', [BestBuySalesController::class, 'getData'])->name('bestbuy.daily.sales.data');
    Route::get('/bestbuy/daily-sales', [BestBuySalesController::class, 'index'])->name('bestbuy.daily.sales');
    Route::get('/bestbuy-daily-sales-column-visibility', [BestBuySalesController::class, 'getColumnVisibility']);
    Route::post('/bestbuy-daily-sales-column-visibility', [BestBuySalesController::class, 'saveColumnVisibility']);

    // Macy's Sales Routes
    Route::get('/macys/daily-sales-data', [\App\Http\Controllers\Sales\MacysSalesController::class, 'getData'])->name('macys.daily.sales.data');
    Route::get('/macys/daily-sales', [\App\Http\Controllers\Sales\MacysSalesController::class, 'index'])->name('macys.daily.sales');
    Route::get('/macys-daily-sales-column-visibility', [\App\Http\Controllers\Sales\MacysSalesController::class, 'getColumnVisibility']);
    Route::post('/macys-daily-sales-column-visibility', [\App\Http\Controllers\Sales\MacysSalesController::class, 'saveColumnVisibility']);

    // Tiendamia Sales Routes
    Route::get('/tiendamia/daily-sales-data', [\App\Http\Controllers\Sales\TiendamiaSalesController::class, 'getData'])->name('tiendamia.daily.sales.data');
    Route::get('/tiendamia/daily-sales', [\App\Http\Controllers\Sales\TiendamiaSalesController::class, 'index'])->name('tiendamia.daily.sales');
    Route::get('/tiendamia-daily-sales-column-visibility', [\App\Http\Controllers\Sales\TiendamiaSalesController::class, 'getColumnVisibility']);
    Route::post('/tiendamia-daily-sales-column-visibility', [\App\Http\Controllers\Sales\TiendamiaSalesController::class, 'saveColumnVisibility']);

    // Best Buy Pricing Routes
    Route::get('/bestbuy-pricing', [\App\Http\Controllers\MarketPlace\BestBuyPricingController::class, 'bestbuyPricingView'])->name('bestbuy.pricing');
    Route::get('/bestbuy-data-json', [\App\Http\Controllers\MarketPlace\BestBuyPricingController::class, 'bestbuyDataJson'])->name('bestbuy.data.json');
    Route::post('/bestbuy-save-nr', [\App\Http\Controllers\MarketPlace\BestBuyPricingController::class, 'saveNrToDatabase'])->name('bestbuy.save.nr');
    Route::post('/bestbuy-save-sprice', [\App\Http\Controllers\MarketPlace\BestBuyPricingController::class, 'saveSpriceToDatabase'])->name('bestbuy.save.sprice');
    Route::post('/bestbuy-update-listed-live', [\App\Http\Controllers\MarketPlace\BestBuyPricingController::class, 'updateListedLive'])->name('bestbuy.update.listed.live');
    Route::get('/bestbuy-pricing-column-visibility', [\App\Http\Controllers\MarketPlace\BestBuyPricingController::class, 'getColumnVisibility'])->name('bestbuy.pricing.column.get');
    Route::post('/bestbuy-pricing-column-visibility', [\App\Http\Controllers\MarketPlace\BestBuyPricingController::class, 'setColumnVisibility'])->name('bestbuy.pricing.column.set');
    Route::post('/bestbuy-upload-price', [\App\Http\Controllers\MarketPlace\BestBuyPricingController::class, 'uploadPriceData'])->name('bestbuy-upload-price');
    Route::post('/bestbuy-save-sprice', [\App\Http\Controllers\MarketPlace\BestBuyPricingController::class, 'saveSpriceUpdates'])->name('bestbuy-save-sprice');

    // Macy's Pricing Routes (Tabulator)
    Route::get('/macys-pricing', [\App\Http\Controllers\MarketPlace\MacyController::class, 'macysTabulatorView'])->name('macys.pricing');
    Route::get('/macys-data-json', [\App\Http\Controllers\MarketPlace\MacyController::class, 'macysDataJson'])->name('macys.data.json');
    Route::post('/macys-update-nr-req', [\App\Http\Controllers\MarketPlace\MacyController::class, 'updateNrReq'])->name('macys.update.nr.req');
    Route::post('/macys-save-sprice-tabulator', [\App\Http\Controllers\MarketPlace\MacyController::class, 'saveSpriceTabulator'])->name('macys.save.sprice.tabulator');
    Route::post('/macys-save-sprice-batch', [\App\Http\Controllers\MarketPlace\MacyController::class, 'saveSpriceUpdates'])->name('macys.save.sprice.batch');
    Route::post('/macys-upload-price', [\App\Http\Controllers\MarketPlace\MacyController::class, 'uploadPriceData'])->name('macys.upload.price');
    Route::get('/macys-pricing-column-visibility', [\App\Http\Controllers\MarketPlace\MacyController::class, 'getTabulatorColumnVisibility'])->name('macys.pricing.column.get');
    Route::post('/macys-pricing-column-visibility', [\App\Http\Controllers\MarketPlace\MacyController::class, 'setTabulatorColumnVisibility'])->name('macys.pricing.column.set');

    // Purchasing Power Pricing Routes (Tabulator)
    Route::get('/purchasing-power-pricing', [\App\Http\Controllers\MarketPlace\PurchasingPowerController::class, 'pricingView'])->name('purchasing.power.pricing');
    Route::get('/pp-data-json', [\App\Http\Controllers\MarketPlace\PurchasingPowerController::class, 'dataJson'])->name('pp.data.json');
    Route::post('/pp-update-nr-req', [\App\Http\Controllers\MarketPlace\PurchasingPowerController::class, 'updateNrReq'])->name('pp.update.nr.req');
    Route::post('/pp-save-sprice-tabulator', [\App\Http\Controllers\MarketPlace\PurchasingPowerController::class, 'saveSpriceTabulator'])->name('pp.save.sprice.tabulator');
    Route::post('/pp-save-sprice-batch', [\App\Http\Controllers\MarketPlace\PurchasingPowerController::class, 'saveSpriceUpdates'])->name('pp.save.sprice.batch');
    Route::get('/pp-pricing-column-visibility', [\App\Http\Controllers\MarketPlace\PurchasingPowerController::class, 'getColumnVisibility'])->name('pp.pricing.column.get');
    Route::post('/pp-pricing-column-visibility', [\App\Http\Controllers\MarketPlace\PurchasingPowerController::class, 'setColumnVisibility'])->name('pp.pricing.column.set');

    // Purchasing Power Sales Routes
    Route::get('/purchasing-power-sales', [\App\Http\Controllers\MarketPlace\PurchasingPowerController::class, 'salesView'])->name('purchasing.power.sales');
    Route::get('/pp-sales-data-json', [\App\Http\Controllers\MarketPlace\PurchasingPowerController::class, 'salesDataJson'])->name('pp.sales.data.json');
    Route::post('/pp-sales-upload', [\App\Http\Controllers\MarketPlace\PurchasingPowerController::class, 'uploadSales'])->name('pp.sales.upload');

    // Reverb Pricing Routes (Tabulator)
    Route::get('/reverb-pricing', [\App\Http\Controllers\MarketPlace\ReverbController::class, 'reverbTabulatorView'])->name('reverb.pricing');
    Route::get('/reverb-data-json', [\App\Http\Controllers\MarketPlace\ReverbController::class, 'reverbDataJson'])->name('reverb.data.json');
    Route::get('/reverb-daily-data-totals-json', [\App\Http\Controllers\MarketPlace\ReverbController::class, 'reverbDailyDataTotalsJson'])->name('reverb.daily.data.totals.json');
    Route::post('/reverb-update-listed-live', [\App\Http\Controllers\MarketPlace\ReverbController::class, 'updateReverbListedLive'])->name('reverb.update.listed.live');
    Route::post('/reverb-save-sprice', [\App\Http\Controllers\MarketPlace\ReverbController::class, 'saveSpriceUpdates'])->name('reverb.save.sprice');
    Route::post('/reverb-save-recommended-bid', [\App\Http\Controllers\MarketPlace\ReverbController::class, 'saveRecommendedBid'])->name('reverb.save.recommended.bid');
    Route::post('/reverb-save-bump-req', [\App\Http\Controllers\MarketPlace\ReverbController::class, 'saveBumpReq'])->name('reverb.save.bump.req');
    Route::get('/reverb-pricing-column-visibility', [\App\Http\Controllers\MarketPlace\ReverbController::class, 'getColumnVisibility'])->name('reverb.pricing.column.get');
    Route::post('/reverb-pricing-column-visibility', [\App\Http\Controllers\MarketPlace\ReverbController::class, 'setColumnVisibility'])->name('reverb.pricing.column.set');
    Route::get('/reverb/fallback-stats', [\App\Http\Controllers\MarketPlace\ReverbSyncController::class, 'fallbackStats'])->name('reverb.fallback.stats');
    Route::get('/admin/shopify-store/{store}', function ($store) {
        $allowed = ['prolightsounds', 'main', '5core', 'business'];
        if (in_array($store, $allowed)) {
            session(['shopify_active_store' => $store]);

            return "Switched to {$store} store. Current store: ".session('shopify_active_store');
        }

        return 'Invalid store. Allowed: '.implode(', ', $allowed);
    })->middleware('auth')->name('admin.shopify-store.switch');

    // CVR Master Routes (Tabulator)
    Route::get('/cvr-master', [CvrMasterController::class, 'index'])->name('cvr.master');
    Route::get('/cvr-master-data-json', [CvrMasterController::class, 'getCvrDataJson'])->name('cvr.master.data.json');
    Route::get('/cvr-master-breakdown', [CvrMasterController::class, 'getBreakdownData'])->name('cvr.master.breakdown');
    Route::get('/cvr-master-column-visibility', [CvrMasterController::class, 'getColumnVisibility'])->name('cvr.master.column.get');
    Route::post('/cvr-master-column-visibility', [CvrMasterController::class, 'saveColumnVisibility'])->name('cvr.master.column.set');
    Route::post('/cvr-master-remark', [CvrMasterController::class, 'saveRemark'])->name('cvr.master.remark.save');
    Route::get('/cvr-master-remark-history/{sku}', [CvrMasterController::class, 'getRemarkHistory'])->name('cvr.master.remark.history');
    Route::get('/cvr-master-remark-latest/{sku}', [CvrMasterController::class, 'getLatestRemark'])->name('cvr.master.remark.latest');
    Route::post('/cvr-master-remark-toggle/{id}', [CvrMasterController::class, 'toggleRemarkSolved'])->name('cvr.master.remark.toggle');
    Route::get('/cvr-master-amazon-sprice-table', [CvrMasterController::class, 'getAmazonSpriceTableData'])->name('cvr.master.amazon.sprice.table');
    Route::get('/cvr-master-chart-data', [CvrMasterController::class, 'getPricingMasterChartData'])->name('cvr.master.chart.data');
    Route::post('/cvr-master-save-suggested-data', [CvrMasterController::class, 'saveSuggestedData'])->name('cvr.master.save.suggested');
    Route::post('/cvr-master-push-price', [CvrMasterController::class, 'pushPriceToAmazon'])->name('cvr.master.push.price');
    Route::post('/cvr-master-bulk-change-price', [CvrMasterController::class, 'bulkChangePrice'])->name('cvr.master.bulk.change.price');

    // Pricing Master CVR Route (uses CVR Master controller)
    Route::get('/pricing-master-cvr', [CvrMasterController::class, 'pricingMasterCvrView'])->name('pricing.master.cvr');

    // TikTok Pricing Routes (Tabulator)
    Route::get('/tiktok-pricing', [\App\Http\Controllers\MarketPlace\TikTokPricingController::class, 'tiktokTabulatorView'])->name('tiktok.pricing');
    Route::get('/tiktok-data-json', [\App\Http\Controllers\MarketPlace\TikTokPricingController::class, 'tiktokDataJson'])->name('tiktok.data.json');
    Route::get('/tiktok-distinct-campaign-count', [\App\Http\Controllers\MarketPlace\TikTokPricingController::class, 'tiktokDistinctCampaignCount'])->name('tiktok.distinct.campaign.count');
    Route::get('/tiktok-badge-chart-data', [\App\Http\Controllers\MarketPlace\TikTokPricingController::class, 'tiktokBadgeChartData'])->name('tiktok.badge.chart.data');
    Route::post('/tiktok-upload-csv', [\App\Http\Controllers\MarketPlace\TikTokPricingController::class, 'uploadTikTokCsv'])->name('tiktok.upload.csv');
    Route::get('/tiktok-download-sample-csv', [\App\Http\Controllers\MarketPlace\TikTokPricingController::class, 'downloadSampleCsv'])->name('tiktok.download.sample');
    Route::post('/tiktok-save-sprice', [\App\Http\Controllers\MarketPlace\TikTokPricingController::class, 'saveSpriceUpdates'])->name('tiktok.save.sprice');
    Route::get('/tiktok-pricing-column-visibility', [\App\Http\Controllers\MarketPlace\TikTokPricingController::class, 'getColumnVisibility'])->name('tiktok.pricing.column.get');
    Route::post('/tiktok-pricing-column-visibility', [\App\Http\Controllers\MarketPlace\TikTokPricingController::class, 'setColumnVisibility'])->name('tiktok.pricing.column.set');

    // Shopify B2C Tabulator Routes
    Route::get('/shopify-b2c-pricing', [\App\Http\Controllers\MarketPlace\Shopifyb2cController::class, 'shopifyB2cTabulatorView'])->name('shopify.b2c.pricing');
    Route::get('/shopify-b2c-data-json', [\App\Http\Controllers\MarketPlace\Shopifyb2cController::class, 'shopifyB2cDataJson'])->name('shopify.b2c.data.json');
    Route::post('/shopify-b2c-update-listed-live', [\App\Http\Controllers\MarketPlace\Shopifyb2cController::class, 'updateShopifyB2cListedLive'])->name('shopify.b2c.update.listed.live');
    Route::get('/shopify-b2c-pricing-column-visibility', [\App\Http\Controllers\MarketPlace\Shopifyb2cController::class, 'getColumnVisibility'])->name('shopify.b2c.pricing.column.get');
    Route::post('/shopify-b2c-pricing-column-visibility', [\App\Http\Controllers\MarketPlace\Shopifyb2cController::class, 'setColumnVisibility'])->name('shopify.b2c.pricing.column.set');

    // eBay 2 Sales Routes
    Route::get('/ebay2/daily-sales-data', [\App\Http\Controllers\Sales\Ebay2SalesController::class, 'getData'])->name('ebay2.daily.sales.data');
    Route::get('/ebay2/daily-sales', [\App\Http\Controllers\Sales\Ebay2SalesController::class, 'index'])->name('ebay2.daily.sales');
    Route::get('/ebay2-daily-sales-column-visibility', [\App\Http\Controllers\Sales\Ebay2SalesController::class, 'getColumnVisibility']);
    Route::post('/ebay2-daily-sales-column-visibility', [\App\Http\Controllers\Sales\Ebay2SalesController::class, 'saveColumnVisibility']);

    // eBay 3 Sales Routes
    Route::get('/ebay3/daily-sales-data', [\App\Http\Controllers\Sales\Ebay3SalesController::class, 'getData'])->name('ebay3.daily.sales.data');
    Route::get('/ebay3/daily-sales', [\App\Http\Controllers\Sales\Ebay3SalesController::class, 'index'])->name('ebay3.daily.sales');
    Route::get('/ebay3-daily-sales-column-visibility', [\App\Http\Controllers\Sales\Ebay3SalesController::class, 'getColumnVisibility']);
    Route::post('/ebay3-daily-sales-column-visibility', [\App\Http\Controllers\Sales\Ebay3SalesController::class, 'saveColumnVisibility']);

    // Amazon Sales Routes
    Route::get('/amazon/daily-sales-data', [AmazonSalesController::class, 'getData'])->name('amazon.daily.sales.data');
    Route::get('/amazon/daily-sales-date-coverage', [AmazonSalesController::class, 'getDateCoverage'])->name('amazon.daily.sales.date.coverage');
    Route::get('/amazon/compare-datsheets-orders', [AmazonSalesController::class, 'compareDatsheetsWithOrders'])->name('amazon.compare.datsheets.orders');
    Route::get('/amazon/daily-sales', [AmazonSalesController::class, 'index'])->name('amazon.daily.sales');
    Route::get('/amazon-column-visibility', [AmazonSalesController::class, 'getColumnVisibility']);
    Route::post('/amazon-column-visibility', [AmazonSalesController::class, 'saveColumnVisibility']);
    Route::get('/amazon/debug-data', [AmazonSalesController::class, 'debugData'])->name('amazon.debug.data');
    Route::get('/amazon/test-data', [AmazonSalesDataTestController::class, 'testData'])->name('amazon.test.data');
    Route::get('/amazon/test-data/{date}', [AmazonSalesDataTestController::class, 'getDateDetails'])->name('amazon.test.data.date');

    // TikTok Sales Routes
    Route::get('/tiktok/daily-sales', [\App\Http\Controllers\Sales\TikTokSalesController::class, 'index'])->name('tiktok.daily.sales');
    Route::get('/tiktok/daily-sales-data', [\App\Http\Controllers\Sales\TikTokSalesController::class, 'getData'])->name('tiktok.daily.sales.data');
    Route::get('/tiktok-column-visibility', [\App\Http\Controllers\Sales\TikTokSalesController::class, 'getColumnVisibility']);
    Route::post('/tiktok-column-visibility', [\App\Http\Controllers\Sales\TikTokSalesController::class, 'saveColumnVisibility']);

    // TikTok 2 Sales Routes (upload-based, margin 0.80)
    Route::get('/tiktok-two/daily-sales', [\App\Http\Controllers\Sales\TikTokSalesController::class, 'indexTwo'])->name('tiktok.two.daily.sales');
    Route::get('/tiktok-two/daily-sales-data', [\App\Http\Controllers\Sales\TikTokSalesController::class, 'getDataTwo'])->name('tiktok.two.daily.sales.data');
    Route::post('/tiktok-two/upload', [\App\Http\Controllers\Sales\TikTokSalesController::class, 'uploadTwo'])->name('tiktok.two.upload');
    Route::get('/tiktok-two-column-visibility', [\App\Http\Controllers\Sales\TikTokSalesController::class, 'getColumnVisibilityTwo']);
    Route::post('/tiktok-two-column-visibility', [\App\Http\Controllers\Sales\TikTokSalesController::class, 'saveColumnVisibilityTwo']);

    // Depop Sheet Routes (margin 0.87)
    Route::get('/depop/sheet', [\App\Http\Controllers\Sales\DepopSalesController::class, 'index'])->name('depop.sheet');
    Route::get('/depop/sheet-data', [\App\Http\Controllers\Sales\DepopSalesController::class, 'getData'])->name('depop.sheet.data');
    Route::post('/depop/upload', [\App\Http\Controllers\Sales\DepopSalesController::class, 'upload'])->name('depop.upload');

    // Doba Sales Routes
    Route::get('/doba/daily-sales-data', [DobaSalesController::class, 'getData'])->name('doba.daily.sales.data');
    Route::get('/doba/daily-sales', [DobaSalesController::class, 'index'])->name('doba.daily.sales');
    Route::get('/doba-column-visibility', [DobaSalesController::class, 'getColumnVisibility']);
    Route::post('/doba-column-visibility', [DobaSalesController::class, 'saveColumnVisibility']);

    // Mercari Sales Routes (With Ship - buyer_shipping_fee = 0 or null)
    Route::post('/mercari/upload-daily-data', [MercariController::class, 'uploadDailyDataChunk'])->name('mercari.upload.daily.data');
    Route::get('/mercari/daily-data', [MercariController::class, 'getDailyData'])->name('mercari.get.daily.data');
    Route::get('/mercari/daily-data-with-ship', [MercariController::class, 'getDailyDataWithShip'])->name('mercari.get.daily.data.with.ship');
    Route::get('/mercari-with-ship', [MercariController::class, 'mercariTabulatorView'])->name('mercari.with.ship.view');
    Route::post('/mercari-column-visibility', [MercariController::class, 'saveMercariColumnVisibility'])->name('mercari.save.column.visibility');
    Route::get('/mercari-column-visibility', [MercariController::class, 'getMercariColumnVisibility'])->name('mercari.get.column.visibility');

    // Mercari Without Ship Sales Routes (buyer_shipping_fee > 0)
    Route::get('/mercari-without-ship', [MercariController::class, 'mercariWithoutShipView'])->name('mercari.without.ship.view');
    Route::get('/mercari/daily-data-without-ship', [MercariController::class, 'getDailyDataWithoutShip'])->name('mercari.get.daily.data.without.ship');
    Route::post('/mercari-without-ship-column-visibility', [MercariController::class, 'saveMercariWithoutShipColumnVisibility'])->name('mercari.save.without.ship.column.visibility');
    Route::get('/mercari-without-ship-column-visibility', [MercariController::class, 'getMercariWithoutShipColumnVisibility'])->name('mercari.get.without.ship.column.visibility');

    // Shopify B2C Sales Routes
    Route::get('/shopify-b2c/daily-sales-data', [\App\Http\Controllers\Sales\ShopifyB2CSalesController::class, 'getData'])->name('shopify-b2c.daily.sales.data');
    Route::get('/shopify-b2c/daily-sales', [\App\Http\Controllers\Sales\ShopifyB2CSalesController::class, 'index'])->name('shopify-b2c.daily.sales');
    Route::get('/shopify-b2c-column-visibility', [\App\Http\Controllers\Sales\ShopifyB2CSalesController::class, 'getColumnVisibility']);
    Route::post('/shopify-b2c-column-visibility', [\App\Http\Controllers\Sales\ShopifyB2CSalesController::class, 'saveColumnVisibility']);

    // Shopify B2B Sales Routes
    Route::get('/shopify-b2b/daily-sales-data', [\App\Http\Controllers\Sales\ShopifyB2BSalesController::class, 'getData'])->name('shopify-b2b.daily.sales.data');
    Route::get('/shopify-b2b/daily-sales', [\App\Http\Controllers\Sales\ShopifyB2BSalesController::class, 'index'])->name('shopify-b2b.daily.sales');
    Route::get('/shopify-b2b-column-visibility', [\App\Http\Controllers\Sales\ShopifyB2BSalesController::class, 'getColumnVisibility']);
    Route::post('/shopify-b2b-column-visibility', [\App\Http\Controllers\Sales\ShopifyB2BSalesController::class, 'saveColumnVisibility']);

    // Walmart Sales Routes
    Route::get('/walmart/daily-sales', [\App\Http\Controllers\Sales\WalmartSalesController::class, 'index'])->name('walmart.daily.sales');
    Route::get('/walmart/daily-sales-data', [\App\Http\Controllers\Sales\WalmartSalesController::class, 'getData'])->name('walmart.daily.sales.data');
    Route::get('/walmart-column-visibility', [\App\Http\Controllers\Sales\WalmartSalesController::class, 'getColumnVisibility']);
    Route::post('/walmart-column-visibility', [\App\Http\Controllers\Sales\WalmartSalesController::class, 'saveColumnVisibility']);

    Route::get('/amazonfba/view-data', [OverallAmazonFbaController::class, 'getViewAmazonFbaData'])->name('amazonfba.viewData');
    Route::get('/fbainv/view-data', [AmazonFbaInvController::class, 'getViewAmazonfbaInvData'])->name('fbainv.viewData');
    Route::get('/product-master-data', [ProductMasterController::class, 'product_master_data']);

    Route::get('/reverb-pricing-cvr', [ReverbController::class, 'reverbPricingCvr'])->name('reverb');
    Route::get('/reverb-pricing-increase-cvr', [ReverbController::class, 'reverbPricingIncreaseCvr'])->name('reverb');
    Route::get('/reverb-pricing-decrease-cvr', [ReverbController::class, 'reverbPricingDecreaseCvr'])->name('reverb');

    Route::post('/reverb/save-sprice', [ReverbController::class, 'saveSpriceToDatabase'])->name('reverb.save-sprice');

    // routes/web.php or routes/api.php
    Route::get('/channel-counts', [ChannelMasterController::class, 'getChannelCounts']);

    Route::get('/home', [\App\Http\Controllers\TaskController::class, 'homeDashboard'])->name('home');
    Route::get('/product-master', [ProductMasterController::class, 'product_master_index'])
        ->name('product.master');
    Route::get('/title-master', fn () => view('title-master'))->name('title.master');
    Route::get('/image-master', [ImageMasterController::class, 'index'])->name('image.master');
    Route::get('/image-master-data', [ImageMasterController::class, 'getData'])->name('image.master.data');
    Route::post('/image-master/push', [ImageMasterController::class, 'pushToMarketplace'])->name('image.master.push');
    Route::post('/image-master/save-pm', [ImageMasterController::class, 'saveProductMasterImages'])->name('image.master.save.pm');
    Route::post('/image-master/upload', [ImageMasterController::class, 'uploadImages'])->name('image.master.upload');
    Route::get('/image-master/amazon-images', [ImageMasterController::class, 'getAmazonImages'])->name('image.master.amazon.images');
    Route::get('/image-master/ebay-images', [ImageMasterController::class, 'getEbayImages'])->name('image.master.ebay.images');

    Route::get('/bullet-point-master', [BulletPointMasterController::class, 'index'])->name('bullet.point.master');
    Route::get('/bullet-point-master-data', [BulletPointMasterController::class, 'getData'])->name('bullet.point.master.data');
    Route::get('/bullet-point-master-combined-data', [BulletPointMasterController::class, 'getCombinedData'])->name('bullet.point.master.combined.data');
    Route::post('/bullet-point-master/update', [BulletPointMasterController::class, 'update'])->name('bullet.point.master.update');
    Route::post('/bullet-point-master/update-bulk', [BulletPointMasterController::class, 'updateBulk'])->name('bullet.point.master.update.bulk');
    Route::post('/bullet-point-master/generate', [BulletPointMasterController::class, 'generateBulletPoints'])->name('bullet.point.master.generate');
    Route::get('/title-master-data', [ProductMasterController::class, 'getTitleMasterData'])->name('title.master.data');
    Route::get('/title-master/sku-options', [ProductMasterController::class, 'getTitleMasterSkuOptions'])->name('title.master.sku.options');
    Route::post('/title-master/save', [ProductMasterController::class, 'saveTitleData'])->name('title.master.save');
    Route::post('/title-master/ai/generate-titles', [ProductMasterController::class, 'generateTitlesWithAI'])->name('title.master.ai.generate');
    Route::post('/title-master/ai/generate-title-150', [ProductMasterController::class, 'generateTitle150WithAI'])->name('title.master.ai.generate.title150');
    Route::post('/title-master/ai/generate-title-100', [ProductMasterController::class, 'generateTitle100WithAI'])->name('title.master.ai.generate.title100');
    Route::post('/title-master/ai/generate-title-80', [ProductMasterController::class, 'generateTitle80WithAI'])->name('title.master.ai.generate.title80');
    Route::post('/title-master/ai/generate-title-60', [ProductMasterController::class, 'generateTitle60WithAI'])->name('title.master.ai.generate.title60');
    Route::post('/title-master/ai/generate-ai-stack-drafts', [ProductMasterController::class, 'generateTitleMasterAiStackDrafts'])->name('title.master.ai.stack.drafts');
    Route::post('/api/amazon/push-title', [ProductMasterController::class, 'pushTitleToAmazon'])->name('amazon.push.title');
    Route::post('/api/amazon/push-bulk', [ProductMasterController::class, 'pushBulkToAmazon'])->name('amazon.push.bulk');
    Route::post('/api/marketplaces/push-title', [ProductMasterController::class, 'pushTitleToAllMarketplaces'])->name('marketplaces.push.title');
    Route::post('/api/marketplaces/push-bulk', [ProductMasterController::class, 'pushBulkToAllMarketplaces'])->name('marketplaces.push.bulk');
    Route::post('/api/marketplaces/push-single', [ProductMasterController::class, 'pushSingleMarketplace'])->name('marketplaces.push.single');
    Route::get('/api/marketplaces/push-status', [ProductMasterController::class, 'getMarketplacePushStatus'])->name('marketplaces.push.status');
    Route::post('/title-master/update-amazon', [ProductMasterController::class, 'updateTitlesToAmazon'])->name('title.master.update.amazon');
    Route::post('/title-master/update-platforms', [ProductMasterController::class, 'updateTitlesToPlatforms'])->name('title.master.update.platforms');
    Route::get('/videos-master', fn () => view('videos-master'))->name('videos.master');
    Route::post('/videos-master/save', [ProductMasterController::class, 'saveVideosData'])->name('videos.master.save');
    Route::get('/bullet-points', [BulletPointMasterController::class, 'index'])->name('bullet.points');
    Route::get('/bullet-points-data', [BulletPointMasterController::class, 'getData'])->name('bullet.points.data');
    Route::post('/bullet-points/update', [BulletPointMasterController::class, 'update'])->name('bullet.points.update');
    Route::post('/bullet-points/generate', [BulletPointMasterController::class, 'generate'])->name('bullet.points.generate');
    Route::post('/bullet-points/save', [ProductMasterController::class, 'saveBulletData'])->name('bullet.points.save');
    Route::get('/product-description', [DescriptionMasterController::class, 'index'])->name('product.description');
    Route::get('/product-description-data', [DescriptionMasterController::class, 'getDescriptionMasterData'])->name('product.description.data');
    Route::post('/product-description/save', [ProductMasterController::class, 'saveDescriptionData'])->name('product.description.save');
    Route::post('/product-description/update', [DescriptionMasterController::class, 'pushDescriptionToMarketplaces'])->name('product.description.update');
    Route::post('/product-description/reset-marketplace', [DescriptionMasterController::class, 'resetMarketplaceDescription'])->name('product.description.reset');
    Route::post('/product-description/generate', [DescriptionMasterController::class, 'generateDescriptionWithAI'])->name('product.description.generate');
    Route::post('/product-description/fetch-amazon-aplus', [DescriptionMasterController::class, 'fetchAmazonAplusContent'])->name('product.description.fetch.amazon.aplus');
    Route::post('/product-description/regenerate-marketplace', [DescriptionMasterController::class, 'regenerateDescriptionForMarketplace'])->name('product.description.regenerate.marketplace');
    Route::post('/product-description/with-images', [DescriptionMasterController::class, 'getDescriptionWithImages'])->name('product.description.with.images');
    Route::get('/product-description-2', [DescriptionMaster2Controller::class, 'index'])->name('product.description2');
    Route::get('/product-description-2/data', [DescriptionMaster2Controller::class, 'getData'])->name('product.description2.data');
    Route::post('/product-description-2/save', [DescriptionMaster2Controller::class, 'save'])->name('product.description2.save');
    Route::post('/product-description-2/push', [DescriptionMaster2Controller::class, 'push'])->name('product.description2.push');
    Route::post('/product-description-2/fetch/amazon', [DescriptionMaster2Controller::class, 'fetchAmazon'])->name('product.description2.fetch.amazon');
    Route::post('/product-description-2/fetch/ebay', [DescriptionMaster2Controller::class, 'fetchEbay'])->name('product.description2.fetch.ebay');
    Route::get('/features', fn () => view('features'))->name('features');
    Route::post('/features/save', [ProductMasterController::class, 'saveFeaturesData'])->name('features.save');
    Route::get('/product-images', fn () => view('images'))->name('images');
    Route::post('/product-images/save', [ProductMasterController::class, 'saveImagesData'])->name('images.save');
    Route::get('/catalogue/{first?}/{second?}', [CatalougeManagerController::class, 'catalouge_manager_index'])
        ->name('catalogue.manager');
    // channel index
    Route::get('/channel/promotion-master', [ChannelPromotionMasterController::class, 'channel_promotion_master_index'])
        ->name('promotion.master');

    Route::get('/channel/{firstChannel?}/{secondChannel?}', [ChannelMasterController::class, 'channel_master_index'])
        ->name('channel.master');
    Route::get('/channel-wise/{firstChannelWise?}/{secondChannelWise?}', [ChannelWiseController::class, 'channel_wise_index'])
        ->name('channel.wise');

    // Marketplace index view routes/
    Route::get('/ad-cvr-amazon', action: [OverallAmazonController::class, 'adcvrAmazon'])->name('adcvr.amazon');
    Route::get('/ad-cvr-amazon-data', action: [OverallAmazonController::class, 'adcvrAmazonData'])->name('adcvr.amazon.data');
    Route::post('/update-amz-price', [OverallAmazonController::class, 'updateAmzPrice'])->name('update.amz.price');

    Route::get('/ad-cvr-pt-amazon', action: [OverallAmazonController::class, 'adcvrPtAmazon'])->name('adcvrPt.amazon');
    Route::get('/ad-cvr-pt-amazon-data', action: [OverallAmazonController::class, 'adcvrPtAmazonData'])->name('adcvrPt.amazon.data');

    Route::get('/review-ratings-amazon', action: [OverallAmazonController::class, 'reviewRatingsAmazon'])->name('review-ratings.amazon');
    Route::get('/review-ratings-amazon-data', action: [OverallAmazonController::class, 'reviewRatingsAmazonData'])->name('review-ratings.amazon.data');

    Route::get('/targeting-amazon', action: [OverallAmazonController::class, 'targetingAmazon'])->name('targeting.amazon');
    Route::get('/targeting-amazon-data', action: [OverallAmazonController::class, 'targetingAmazonData'])->name('targeting.amazon.data');

    // Amz FBM Targeting Check - TARGET KW / TARGET PT
    Route::get('/amazon-fbm-targeting-check-kw', action: [AmazonFbmTargetingCheckController::class, 'targetKw'])->name('amazon.fbm.targeting.check.kw');
    Route::get('/amazon-fbm-targeting-check-pt', action: [AmazonFbmTargetingCheckController::class, 'targetPt'])->name('amazon.fbm.targeting.check.pt');
    Route::get('/amazon-fbm-targeting-check-kw-data', action: [AmazonFbmTargetingCheckController::class, 'targetKwData'])->name('amazon.fbm.targeting.check.kw.data');
    Route::get('/amazon-fbm-targeting-check-pt-data', action: [AmazonFbmTargetingCheckController::class, 'targetPtData'])->name('amazon.fbm.targeting.check.pt.data');
    Route::get('/amazon-fbm-targeting-check-history', action: [AmazonFbmTargetingCheckController::class, 'getHistory'])->name('amazon.fbm.targeting.check.history');
    Route::post('/amazon-fbm-targeting-check-save', action: [AmazonFbmTargetingCheckController::class, 'save'])->name('amazon.fbm.targeting.check.save');

    Route::get('/overall-amazon', action: [OverallAmazonController::class, 'overallAmazon'])->name('overall.amazon');
    Route::get('/adv-amazon/total-sales/save-data', action: [OverallAmazonController::class, 'getAmazonTotalSalesSaveData'])->name('adv-amazon.total-sales.save-data');

    Route::post('/overallAmazon/saveLowProfit', action: [OverallAmazonController::class, 'saveLowProfit']);
    Route::get('/amazon-pricing-cvr', action: [OverallAmazonController::class, 'amazonPricingCVR'])->name('amazon.pricing.cvr');
    Route::get('/amazon-tabulator-view', action: [OverallAmazonController::class, 'amazonTabulatorView'])->name('amazon.tabulator.view');
    Route::get('/amazonpricing-cvr-tabular', action: [OverallAmazonController::class, 'amazonPricingCvrTabular'])->name('amazon.pricing.cvr.tabular');
    Route::get('/amazon-column-visibility', [OverallAmazonController::class, 'getAmazonColumnVisibility'])->name('amazon.column.visibility');
    Route::post('/amazon-column-visibility', [OverallAmazonController::class, 'saveAmazonColumnVisibility'])->name('amazon.column.visibility.save');
    Route::post('/amazon-badge-stats-save', [OverallAmazonController::class, 'saveAmazonBadgeStats']);
    Route::get('/amazon-badge-chart-data', [OverallAmazonController::class, 'getAmazonBadgeChartData']);
    Route::get('/amazon-kw-last-sbid-chart-data', [OverallAmazonController::class, 'getAmazonKwLastSbidChartData']);
    Route::get('/amazon-data-json', action: [OverallAmazonController::class, 'amazonDataJson'])->name('amazon.data.json');
    Route::get('/amazon-campaign-data-by-sku', action: [OverallAmazonController::class, 'getCampaignDataBySku'])->name('amazon.campaign.data.by.sku');
    Route::post('/amazon/refresh-links', [OverallAmazonController::class, 'refreshAmazonLinks'])->name('amazon.refresh.links');
    Route::post('/save-amazon-nr', [OverallAmazonController::class, 'saveNrToDatabase']);
    Route::post('/save-amazon-variation', [OverallAmazonController::class, 'saveVariationToDatabase']);
    Route::post('/save-amazon-sprice', [OverallAmazonController::class, 'saveSpriceToDatabase']);
    Route::post('/amazon-clear-sprice', [OverallAmazonController::class, 'clearAmazonSprice']);
    Route::post('/apply-amazon-price', [OverallAmazonController::class, 'applyAmazonPrice']);
    Route::post('/update-sprice-status', [OverallAmazonController::class, 'updateSpriceStatus']);
    Route::post('/update-amazon-listed-live', [OverallAmazonController::class, 'updateListedLive']);
    Route::get('/amazon-export-pricing-cvr', [OverallAmazonController::class, 'exportAmazonPricingCVR'])->name('amazon.export.pricing.cvr');
    Route::get('/amazon-export-sprice-upload', [OverallAmazonController::class, 'exportAmazonSpriceUpload'])->name('amazon.export.sprice.upload');
    Route::get('/amazon-ratings-sample', [OverallAmazonController::class, 'downloadAmazonRatingsSample'])->name('amazon.ratings.sample');
    Route::get('/amazon-pricing-increase-decrease', action: [OverallAmazonController::class, 'amazonPriceIncreaseDecrease'])->name('amazon.pricing.increase');
    Route::post('/amazon/save-manual-link', [OverallAmazonController::class, 'saveManualLink'])->name('amazon.saveManualLink');
    Route::get('/amazon-pricing-increase', action: [OverallAmazonController::class, 'amazonPriceIncrease'])->name('amazon.pricing.inc');
    Route::post('/amazon/save-manual-link', [OverallAmazonController::class, 'saveManualLink'])->name('amazon.saveManualLink');
    Route::get('/getFilteredAmazonData', [OverallAmazonController::class, 'getFilteredAmazonData']);
    Route::post('/amazon-analytics/import', [OverallAmazonController::class, 'importAmazonAnalytics'])->name('amazon.analytics.import');
    Route::get('/amazon-analytics/export', [OverallAmazonController::class, 'exportAmazonAnalytics'])->name('amazon.analytics.export');
    Route::get('/amazon-analytics/sample', [OverallAmazonController::class, 'downloadSample'])->name('amazon.analytics.sample');
    Route::post('/import-amazon-ratings', [OverallAmazonController::class, 'importAmazonRatings']);
    Route::get('/amazon/competitors', [OverallAmazonController::class, 'getAmazonCompetitors'])->name('amazon.competitors.get');
    Route::post('/amazon/lmp/add', [OverallAmazonController::class, 'addAmazonLmp'])->name('amazon.lmp.add');
    Route::post('/amazon/lmp/delete', [OverallAmazonController::class, 'deleteAmazonLmp'])->name('amazon.lmp.delete.post');
    Route::delete('/amazon/lmp/delete', [OverallAmazonController::class, 'deleteAmazonLmp'])->name('amazon.lmp.delete');
    Route::post('/update-amazon-rating', [OverallAmazonController::class, 'updateAmazonRating']);
    Route::post('/save-amazon-checklist-to-history', [OverallAmazonController::class, 'saveAmazonChecklistToHistory']);
    Route::get('/get-amazon-seo-history', [OverallAmazonController::class, 'getAmazonSeoHistory']);
    Route::get('/amazon-metrics-history', [OverallAmazonController::class, 'getMetricsHistory'])->name('amazon.metrics.history');

    // ebay 2
    Route::get('/zero-ebay2', [Ebay2ZeroController::class, 'ebay2Zeroview'])->name('zero.ebay2');
    Route::get('/zero_ebay2/view-data', [Ebay2ZeroController::class, 'getViewEbay2ZeroData']);
    Route::post('/zero_ebay2/reason-action/update-data', [Ebay2ZeroController::class, 'updateReasonAction']);
    Route::post('/zero_ebay2/save-nr', [Ebay2ZeroController::class, 'saveEbayTwoZeroNR']);
    Route::get('/listing-ebaytwo', [ListingEbayTwoController::class, 'listingEbayTwo'])->name('listing.ebayTwo');
    Route::get('/listing_ebaytwo/view-data', [ListingEbayTwoController::class, 'getViewListingEbayTwoData']);
    Route::post('/listing_ebaytwo/save-status', [ListingEbayTwoController::class, 'saveStatus']);
    Route::post('/listing_ebaytwo/import', [ListingEbayTwoController::class, 'import'])->name('listing_ebaytwo.import');
    Route::get('/listing_ebaytwo/export', [ListingEbayTwoController::class, 'export'])->name('listing_ebaytwo.export');

    Route::get('ebayTwoAnalysis', action: [EbayTwoController::class, 'overallEbay']);
    Route::get('/adv-ebay2/total-sale/save-data', action: [EbayTwoController::class, 'getEbay2TotsalSaleDataSave'])->name('adv-ebay2.total-sale.save-data');
    Route::get('/ebay2/view-data', [EbayTwoController::class, 'getViewEbay2Data']);
    Route::get('ebayTwoPricingCVR', [EbayTwoController::class, 'ebayTwoPricingCVR'])->name('ebayTwo.pricing.cvr');
    Route::post('/update-all-ebay2-skus', [EbayTwoController::class, 'updateAllEbay2Skus']);
    Route::post('/ebay2/save-nr', [EbayTwoController::class, 'saveNrToDatabase']);
    Route::post('/ebay2/update-listed-live', [EbayTwoController::class, 'updateListedLive']);
    Route::post('/ebay2/save-low-profit-count', [EbayTwoController::class, 'saveLowProfit']);
    Route::post('/ebay2-analytics/import', [EbayTwoController::class, 'importEbayTwoAnalytics'])->name('ebay2.analytics.import');
    Route::get('/ebay2-analytics/export', [EbayTwoController::class, 'exportEbayTwoAnalytics'])->name('ebay2.analytics.export');
    Route::get('/ebay2-analytics/sample', [EbayTwoController::class, 'downloadSample'])->name('ebay2.analytics.sample');

    // ebay 3
    Route::get('/zero-ebay3', [Ebay3ZeroController::class, 'ebay3Zeroview'])->name('zero.ebay3');
    Route::get('/zero_ebay3/view-data', [Ebay3ZeroController::class, 'getViewEbay3ZeroData']);
    Route::post('/zero_ebay3/reason-action/update-data', [Ebay3ZeroController::class, 'updateReasonAction']);
    Route::post('/zero_ebay3/save-nr', [Ebay3ZeroController::class, 'saveEbayThreeZeroNR']);
    Route::get('/listing-ebaythree', [ListingEbayThreeController::class, 'listingEbayThree'])->name('listing.ebayThree');
    Route::get('/listing_ebaythree/view-data', [ListingEbayThreeController::class, 'getViewListingEbayThreeData']);
    Route::post('/listing_ebaythree/save-status', [ListingEbayThreeController::class, 'saveStatus']);
    Route::post('/listing_ebaythree/import', [ListingEbayThreeController::class, 'import'])->name('listing_ebaythree.import');
    Route::get('/listing_ebaythree/export', [ListingEbayThreeController::class, 'export'])->name('listing_ebaythree.export');

    Route::get('ebayThreeAnalysis', action: [EbayThreeController::class, 'overallthreeEbay']);
    Route::get('/adv-ebay3/total-sale/save-data', action: [EbayThreeController::class, 'getEbay3TotalSaleSaveData'])->name('adv-ebay3.total-sale.save-data');
    Route::get('/ebay3/view-data', [EbayThreeController::class, 'getViewEbay3Data']);
    Route::get('ebayThreePricingCVR', [EbayThreeController::class, 'ebayThreePricingCVR'])->name('ebayThree.pricing.cvr');
    Route::post('/update-all-ebay3-skus', [EbayThreeController::class, 'updateAllEbay3Skus']);
    Route::post('/ebay3/save-nr', [EbayThreeController::class, 'saveNrToDatabase']);
    Route::post('/ebay3/save-sprice', [EbayThreeController::class, 'saveSpriceToDatabase']);
    Route::post('/ebay3/update-listed-live', [EbayThreeController::class, 'updateListedLive']);
    Route::post('/ebay3-analytics/import', [EbayThreeController::class, 'importEbayThreeAnalytics'])->name('ebay3.analytics.import');
    Route::get('/ebay3-analytics/export', [EbayThreeController::class, 'exportEbayThreeAnalytics'])->name('ebay3.analytics.export');
    Route::get('/ebay3-analytics/sample', [EbayThreeController::class, 'downloadSample'])->name('ebay3.analytics.sample');

    // eBay3 Tabulator View Routes
    Route::get('/ebay3-tabulator-view', [EbayThreeController::class, 'ebay3TabulatorView'])->name('ebay3.tabulator.view');
    Route::get('/ebay3-data-json', [EbayThreeController::class, 'ebay3DataJson'])->name('ebay3.data.json');
    Route::get('/ebay3-column-visibility', [ChannelTabulatorColumnController::class, 'showEbay3'])->name('ebay3.column.visibility.get');
    Route::post('/ebay3-column-visibility', [ChannelTabulatorColumnController::class, 'storeEbay3'])->name('ebay3.column.visibility.set');
    Route::get('/tabulator-column-visibility', [ChannelTabulatorColumnController::class, 'show'])->name('tabulator.column.visibility.get');
    Route::post('/tabulator-column-visibility', [ChannelTabulatorColumnController::class, 'store'])->name('tabulator.column.visibility.set');
    Route::post('/push-ebay3-price-tabulator', [EbayThreeController::class, 'pushEbay3Price'])->name('ebay3.push.price.tabulator');
    Route::post('/update-ebay3-sprice-status', [EbayThreeController::class, 'updateEbay3SpriceStatus'])->name('ebay3.update.sprice.status');
    Route::post('/clear-all-sprice-ebay3', [EbayThreeController::class, 'clearAllSprice'])->name('ebay3.clear.all.sprice');

    // walmart
    Route::get('/zero-walmart', [WalmartZeroController::class, 'walmartZeroview'])->name('zero.walmart');
    Route::get('/zero_walmart/view-data', [WalmartZeroController::class, 'getViewWalmartZeroData']);
    Route::post('/zero_walmart/reason-action/update-data', [WalmartZeroController::class, 'updateReasonAction']);
    Route::get('/listing-walmart', [ListingWalmartController::class, 'listingWalmart'])->name('listing.walmart');
    Route::get('/listing_walmart/view-data', [ListingWalmartController::class, 'getViewListingWalmartData']);
    Route::post('/listing_walmart/save-status', [ListingWalmartController::class, 'saveStatus']);
    Route::post('/listing_walmart/import', [ListingWalmartController::class, 'import'])->name('listing_walmart.import');
    Route::get('/listing_walmart/export', [ListingWalmartController::class, 'export'])->name('listing_walmart.export');
    Route::get('/listing_walmart/sample', [ListingWalmartController::class, 'downloadSample'])->name('listing_walmart.sample');

    Route::get('/ad-cvr-walmart', action: [WalmartZeroController::class, 'adcvrWalmart'])->name('adcvr.walmart');
    Route::get('/ad-cvr-walmart-data', action: [WalmartZeroController::class, 'adcvrWalmartData'])->name('adcvr.walmart.data');
    Route::post('/update-walmart-price', [WalmartZeroController::class, 'updateWalmartPrice'])->name('update.walmart.price');

    Route::get('walmartAnalysis', action: [WalmartControllerMarket::class, 'overallWalmart']);
    Route::get('/walmart/view-data', [WalmartControllerMarket::class, 'getViewWalmartData']);
    Route::get('walmartPricingCVR', [WalmartControllerMarket::class, 'walmartPricingCVR']);
    Route::post('/update-all-walmart-skus', [WalmartControllerMarket::class, 'updateAllWalmartSkus']);
    Route::post('/walmart/save-nr', [WalmartControllerMarket::class, 'saveNrToDatabase']);
    Route::post('/walmart/save-nrl', [WalmartControllerMarket::class, 'saveNrlToDatabase']);
    Route::post('/walmart/save-nra', [WalmartControllerMarket::class, 'saveNraToDatabase']);
    Route::post('/walmart/update-listed-live', [WalmartControllerMarket::class, 'updateListedLive']);
    Route::post('/walmart-analytics/import', [WalmartControllerMarket::class, 'importWalmartAnalytics'])->name('walmart.analytics.import');
    Route::get('/walmart-analytics/export', [WalmartControllerMarket::class, 'exportWalmartAnalytics'])->name('walmart.analytics.export');
    Route::get('/walmart-analytics/sample', [WalmartControllerMarket::class, 'downloadSample'])->name('walmart.analytics.sample');

    // Walmart Tabulator View Routes
    Route::get('walmart-tabulator-view', [WalmartControllerMarket::class, 'walmartTabulatorView'])->name('walmart.tabulator.view');
    Route::get('/walmart-data-json', [WalmartControllerMarket::class, 'walmartDataJson']);
    Route::get('/walmart-column-visibility', [WalmartControllerMarket::class, 'getWalmartColumnVisibility']);
    Route::post('/walmart-column-visibility', [WalmartControllerMarket::class, 'setWalmartColumnVisibility']);
    Route::get('/walmart-export', [WalmartControllerMarket::class, 'exportWalmartTabulatorData'])->name('walmart.export');
    Route::post('/walmart-import', [WalmartControllerMarket::class, 'importWalmartAnalytics'])->name('walmart.import');
    Route::post('/save-walmart-sprice', [WalmartControllerMarket::class, 'saveSpriceToDatabase']);
    Route::post('/save-walmart-buybox-price', [WalmartControllerMarket::class, 'saveBuyboxPrice']);
    Route::post('/save-walmart-manual-data', [WalmartControllerMarket::class, 'saveManualData']);
    Route::get('/walmart-ratings-sample', [WalmartControllerMarket::class, 'downloadWalmartRatingsSample'])->name('walmart.ratings.sample');
    Route::post('/import-walmart-ratings', [WalmartControllerMarket::class, 'importWalmartRatings'])->name('walmart.ratings.import');
    Route::post('/update-walmart-rating', [WalmartControllerMarket::class, 'updateWalmartRating'])->name('walmart.rating.update');

    // Walmart Sheet Upload Routes (Separate Controller) - Like Temu with Truncate
    Route::get('/walmart-sheet-upload', [App\Http\Controllers\MarketPlace\WalmartSheetUploadController::class, 'index'])->name('walmart.sheet.upload');
    Route::post('/walmart-sheet-upload-price', [App\Http\Controllers\MarketPlace\WalmartSheetUploadController::class, 'uploadPriceData'])->name('walmart-sheet-upload-price');
    Route::post('/walmart-sheet-upload-listing-views', [App\Http\Controllers\MarketPlace\WalmartSheetUploadController::class, 'uploadListingViewsData'])->name('walmart-sheet-upload-listing-views');
    Route::post('/walmart-sheet-upload-order', [App\Http\Controllers\MarketPlace\WalmartSheetUploadController::class, 'uploadOrderData'])->name('walmart-sheet-upload-order');
    Route::get('/walmart-sheet-upload-data-json', [App\Http\Controllers\MarketPlace\WalmartSheetUploadController::class, 'getCombinedDataJson'])->name('walmart-sheet-upload-data-json');
    Route::get('/walmart-sheet-upload-summary', [App\Http\Controllers\MarketPlace\WalmartSheetUploadController::class, 'getSummaryStats'])->name('walmart-sheet-upload-summary');
    Route::post('/walmart-sheet-save-amazon-prices', [App\Http\Controllers\MarketPlace\WalmartSheetUploadController::class, 'saveAmazonPriceUpdates'])->name('walmart-sheet-save-amazon-prices');
    Route::post('/walmart-sheet-update-cell', [App\Http\Controllers\MarketPlace\WalmartSheetUploadController::class, 'updateCellData'])->name('walmart-sheet-update-cell');
    Route::get('/walmart-metrics-history', [App\Http\Controllers\MarketPlace\WalmartSheetUploadController::class, 'getMetricsHistory'])->name('walmart-metrics-history');
    Route::get('/walmart-campaign-data-by-sku', [App\Http\Controllers\MarketPlace\WalmartSheetUploadController::class, 'getCampaignDataBySku'])->name('walmart.campaign.data.by.sku');

    // Listing Audit amazon
    Route::get('/listing-audit-amazon', action: [ListingAuditAmazonController::class, 'listingAuditAmazon'])->name('listing.audit.amazon');
    Route::get('/listing-amazon', [ListingAmazonController::class, 'listingAmazon'])->name('listing.amazon');
    Route::get('/listing_amazon/view-data', [ListingAmazonController::class, 'getViewListingAmazonData']);
    Route::get('/listing_amazon/daily-metrics', [ListingAmazonController::class, 'getDailyMetrics'])->name('listing.amazon.daily.metrics');
    Route::post('/listing_amazon/save-status', [ListingAmazonController::class, 'saveStatus']);
    Route::post('/listing_amazon/fetch-links', [ListingAmazonController::class, 'fetchAndUpdateLinks'])->name('listing.amazon.fetch.links');
    Route::post('/listing_amazon/import', [ListingAmazonController::class, 'import'])->name('listing_amazon.import');
    Route::get('/listing_amazon/export', [ListingAmazonController::class, 'export'])->name('listing_amazon.export');

    // Listing Mirror Routes - Multi-channel listing sync
    Route::get('/listing-mirror', [\App\Http\Controllers\ListingMirrorController::class, 'index'])->name('listing-mirror.index');
    Route::post('/listing-mirror/sync-inventory', [\App\Http\Controllers\ListingMirrorController::class, 'syncInventory'])->name('listing-mirror.sync-inventory');
    Route::post('/listing-mirror/sync-price', [\App\Http\Controllers\ListingMirrorController::class, 'syncPrice'])->name('listing-mirror.sync-price');
    Route::post('/listing-mirror/bulk-sync', [\App\Http\Controllers\ListingMirrorController::class, 'bulkSync'])->name('listing-mirror.bulk-sync');
    Route::get('/listing-mirror/sync-history', [\App\Http\Controllers\ListingMirrorController::class, 'getSyncHistory'])->name('listing-mirror.sync-history');
    Route::get('/listing-audit-ebay', [ListingAuditEbayController::class, 'listingAuditEbay'])->name('listing.audit.ebay');
    Route::get('/listing-ebay', [ListingEbayController::class, 'listingEbay'])->name('listing.ebay');
    Route::post('/listing_ebay/import', [ListingEbayController::class, 'import'])->name('listing_ebay.import');
    Route::get('/listing_ebay/export', [ListingEbayController::class, 'export'])->name('listing_ebay.export');

    Route::get('/amazon-zero-view', action: [AmazonZeroController::class, 'amazonZero'])->name('amazon.zero.view');
    Route::get('/amazon-low-visibility-view', action: [AmazonLowVisibilityController::class, 'amazonLowVisibility'])->name('amazon.low.visibility.view');
    Route::get('/amazon-low-visibility-view-fba', action: [AmazonLowVisibilityController::class, 'amazonLowVisibilityFba'])->name('amazon.low.visibility.view.fba');
    Route::get('/amazon-low-visibility-view-fbm', action: [AmazonLowVisibilityController::class, 'amazonLowVisibilityFbm'])->name('amazon.low.visibility.view.fbm');
    Route::get('/amazon-low-visibility-view-both', action: [AmazonLowVisibilityController::class, 'amazonLowVisibilityBoth'])->name('amazon.low.visibility.view.both');

    Route::get('/overall-amazon-fba', action: [OverallAmazonFbaController::class, 'overallAmazonFBA'])->name('overall.amazon.fba');
    Route::get('/overall-amazon-fbainv', action: [AmazonFbaInvController::class, 'amazonFbaInv'])->name('overall.amazon.fbainv');

    // Listing Audit ebay
    Route::get('/ebay', [EbayController::class, 'ebayView'])->name('ebay');
    Route::get('/ebay-tabulator-view', [EbayController::class, 'ebayTabulatorView'])->name('ebay.tabulator.view');
    Route::get('/ebay-pricing-data', [EbayController::class, 'ebayViewData'])->name('ebay.pricing.data');
    Route::get('/ebay-data-json', [EbayController::class, 'ebayDataJson'])->name('ebay.data.json');
    Route::get('/ebay-campaign-data-by-sku', [EbayController::class, 'getCampaignDataBySku'])->name('ebay.campaign.data.by.sku');
    Route::get('/ebay2-campaign-data-by-sku', [EbayTwoController::class, 'getCampaignDataBySku'])->name('ebay2.campaign.data.by.sku');
    Route::get('/ebay3-campaign-data-by-sku', [EbayThreeController::class, 'getCampaignDataBySku'])->name('ebay3.campaign.data.by.sku');
    Route::get('/ebay-metrics-history', [EbayController::class, 'getMetricsHistory'])->name('ebay.metrics.history');
    Route::get('/ebay-ads-spend', [EbayController::class, 'getEbayAdsSpend'])->name('ebay.ads.spend');
    Route::get('/ebay-kw-pmt-spend-totals', [EbayController::class, 'getKwPmtSpendTotals'])->name('ebay.kw.pmt.spend.totals');
    Route::post('/update-ebay-rating', [EbayController::class, 'updateEbayRating']);
    Route::get('/ebay-ratings-sample', [EbayController::class, 'downloadEbayRatingsSample'])->name('ebay.ratings.sample');
    Route::post('/import-ebay-ratings', [EbayController::class, 'importEbayRatings']);
    Route::get('/ebay-column-visibility', [EbayController::class, 'getEbayColumnVisibility'])->name('ebay.column.visibility.get');
    Route::post('/ebay-column-visibility', [EbayController::class, 'setEbayColumnVisibility'])->name('ebay.column.visibility.set');
    Route::get('/ebay-export', [EbayController::class, 'exportEbayPricingData'])->name('ebay.export');
    Route::get('/adv-ebay/total-sales/save-data', [EbayController::class, 'getAdvEbayTotalSaveData'])->name('adv-ebay.total-sales.save-data');
    Route::post('/ebay/saveLowProfit', [EbayController::class, 'saveLowProfit']);
    Route::post('/ebay-analytics/import', [EbayController::class, 'importEbayAnalytics'])->name('ebay.analytics.import');
    Route::get('/ebay-analytics/export', [EbayController::class, 'exportEbayAnalytics'])->name('ebay.analytics.export');
    Route::get('/ebay-analytics/sample', [EbayController::class, 'downloadSample'])->name('ebay.analytics.sample');

    Route::any('/update-ebay-sku-pricing', [EbayController::class, 'updateEbayPricing'])->name('ebay.priceUpdate');
    Route::any('/update-ebay2-sku-pricing', [EbayTwoController::class, 'updateEbayPricing'])->name('ebay2.priceUpdate');
    // Route::post('/update-amazon-pricing', [OverallAmazonController::class, 'updatePrice'])->name('amazon.priceUpdate');
    Route::get('/check-amazon-auth', [OverallAmazonController::class, 'checkAmazonAuth']);

    Route::post('/update-fba-status-ebay', [EbayController::class, 'updateFbaStatusEbay'])
        ->name('update.fba.status-ebay');
    Route::get('/ebay-pricing-cvr', [EbayController::class, 'ebayPricingCVR'])->name('ebay.pricing.cvr');

    Route::get('/ebay-pricing-decrease', [EbayController::class, 'ebayPricingIncreaseDecrease'])->name('ebay.pricing.decrease');
    Route::get('/ebay-pricing-increase', action: [EbayController::class, 'ebayPricingIncrease'])->name('ebay.pricing.inc');
    Route::post('/save-nr-ebay', [EbayController::class, 'saveNrToDatabase'])->name('ebay.save.nr');
    Route::post('/save-sprice-ebay', [EbayTwoController::class, 'saveSpriceToDatabase'])->name('ebay.save.sprice');
    Route::post('/push-ebay-price-tabulator', [EbayController::class, 'pushEbayPrice'])->name('ebay.push.price.tabulator');
    Route::post('/update-ebay-sprice-status', [EbayController::class, 'updateEbaySpriceStatus'])->name('ebay.update.sprice.status');
    Route::post('/update-listed-live-ebay', [EbayController::class, 'updateListedLive'])->name('ebay.update.listed.live');

    // eBay LMP (Lowest Marketplace Price) routes
    Route::get('/ebay-lmp-data', [EbayController::class, 'getEbayLmpData'])->name('ebay.lmp.data');
    Route::post('/ebay-lmp-add', [EbayController::class, 'addEbayLmp'])->name('ebay.lmp.add');
    Route::post('/ebay-lmp-delete', [EbayController::class, 'deleteEbayLmp'])->name('ebay.lmp.delete');

    Route::get('/ebay-zero-view', action: [EbayZeroController::class, 'ebayZero'])->name('ebay.zero.view');
    Route::get('/ebay-low-visibility-view', action: [EbayLowVisibilityController::class, 'ebayLowVisibility'])->name('ebay.low.visibility.view');
    Route::get('/ebay2-low-visibility-view', action: [Ebay2LowVisibilityController::class, 'ebay2LowVisibility'])->name('ebay2.low.visibility.view');
    Route::get('/ebay3-low-visibility-view', action: [Ebay3LowVisibilityController::class, 'ebay3LowVisibility'])->name('ebay3.low.visibility.view');
    // Listing Audit ebay2
    Route::get('/ebay2-tabulator-view', [EbayTwoController::class, 'ebay2TabulatorView'])->name('ebay2.tabulator.view');
    Route::get('/ebay2op-tabulator-view', [EbayTwoController::class, 'ebay2opTabulatorView'])->name('ebay2op.tabulator.view');
    Route::get('/ebay2-data', [EbayTwoController::class, 'getViewEbayData'])->name('ebay2.data');
    Route::get('/ebay2op-data', [EbayTwoController::class, 'getViewEbayData'])->name('ebay2op.data');
    Route::get('/get-ebay2op-column-visibility', [EbayTwoController::class, 'getEbay2opColumnVisibility'])->name('ebay2op.column.visibility.get');
    Route::post('/set-ebay2op-column-visibility', [EbayTwoController::class, 'setEbay2opColumnVisibility'])->name('ebay2op.column.visibility.set');
    Route::get('/ebay2-metrics-history', [EbayTwoController::class, 'getMetricsHistory'])->name('ebay2.metrics.history');
    Route::get('/ebay2-ads-spend', [EbayTwoController::class, 'getEbay2AdsSpend'])->name('ebay2.ads.spend');
    Route::get('/get-ebay2-column-visibility', [EbayTwoController::class, 'getEbay2ColumnVisibility'])->name('ebay2.column.visibility.get');
    Route::post('/set-ebay2-column-visibility', [EbayTwoController::class, 'setEbay2ColumnVisibility'])->name('ebay2.column.visibility.set');
    Route::get('/export-ebay2-pricing-data', [EbayTwoController::class, 'exportEbay2PricingData'])->name('ebay2.export');
    Route::post('/save-ebay2-nr', [EbayTwoController::class, 'saveNrToDatabase'])->name('ebay2.save.nr');
    Route::post('/save-ebay2-sprice', [EbayTwoController::class, 'saveSpriceToDatabase'])->name('ebay2.save.sprice');
    Route::post('/push-ebay2-price', [EbayTwoController::class, 'pushEbay2Price'])->name('ebay2.push.price');
    Route::post('/update-ebay2-sprice-status', [EbayTwoController::class, 'updateEbay2SpriceStatus'])->name('ebay2.update.sprice.status');
    Route::post('/update-listed-live-ebay2', [EbayTwoController::class, 'updateListedLive'])->name('ebay2.update.listed.live');
    Route::get('/ebay2/total-sales/save-data', [EbayTwoController::class, 'getEbay2TotsalSaleDataSave'])->name('ebay2.total-sales.save-data');
    Route::post('/ebay2-analytics/import', [EbayTwoController::class, 'importEbayTwoAnalytics'])->name('ebay2.analytics.import');
    Route::get('/ebay2-analytics/export', [EbayTwoController::class, 'exportEbayTwoAnalytics'])->name('ebay2.analytics.export');
    Route::get('/ebay2-analytics/sample', [EbayTwoController::class, 'downloadSample'])->name('ebay2.analytics.sample');
    Route::get('/ebay2-pricing-cvr', [EbayTwoController::class, 'EbayTwoPricingCVR'])->name('ebay2.pricing.cvr');
    Route::get('/ebay2', [EbayTwoController::class, 'overallEbay'])->name('ebay2');

    // Listing Audit Macy
    Route::get('/listing-macys', [ListingMacysController::class, 'listingMacys'])->name('listing.macys');
    Route::get('/listing_macys/view-data', [ListingMacysController::class, 'getViewListingMacysData']);
    Route::post('/listing_macys/save-status', [ListingMacysController::class, 'saveStatus']);
    Route::post('/listing_macys/import', [ListingMacysController::class, 'import'])->name('listing_macys.import');
    Route::get('/listing_macys/export', [ListingMacysController::class, 'export'])->name('listing_macys.export');
    Route::get('/listing_macys/sample', [ListingMacysController::class, 'downloadSample'])->name('listing_macys.sample');
    Route::get('/macys', [MacyController::class, 'macyView'])->name('macys');
    Route::post('/macys/saveLowProfit', [MacyController::class, 'saveLowProfit']);
    Route::get('/macys-zero-view', action: [MacyZeroController::class, 'macyZeroView'])->name('macy.zero.view');
    Route::get('/macys-low-visibility-view', action: [MacyLowVisibilityController::class, 'macyLowVisibilityView'])->name('macy.low.visibility.view');
    Route::post('/macys-analytics/import', [MacyController::class, 'importMacysAnalytics'])->name('macys.analytics.import');
    Route::get('/macys-analytics/export', [MacyController::class, 'exportMacysAnalytics'])->name('macys.analytics.export');
    Route::get('/macys-analytics/sample', [MacyController::class, 'downloadSample'])->name('macys.analytics.sample');

    // Listing Audit shopifyB2C
    Route::get('/listing-shopifyb2c', [ListingShopifyB2CController::class, 'listingShopifyB2C'])->name('listing.shopifyb2c');
    Route::get('/listing_shopifyb2c/view-data', [ListingShopifyB2CController::class, 'getViewListingShopifyB2CData']);
    Route::post('/listing_shopifyb2c/save-status', [ListingShopifyB2CController::class, 'saveStatus']);
    Route::post('/listing_shopifyb2c/import', [ListingShopifyB2CController::class, 'import'])->name('listing_shopifyb2c.import');
    Route::get('/listing_shopifyb2c/export', [ListingShopifyB2CController::class, 'export'])->name('listing_shopifyb2c.export');
    Route::get('/listing_shopifyb2c/sample', [ListingShopifyB2CController::class, 'downloadSample'])->name('listing_shopifyb2c.sample');
    Route::get('/shopifyB2C-zero-view', action: [Shopifyb2cZeroController::class, 'shopifyb2cZeroView'])->name('shopifyB2C.zero.view');
    Route::get('/shopifyB2C-low-visibility-view', action: [Shopifyb2cLowVisibilityController::class, 'shopifyb2cLowVisibilityView'])->name('shopifyB2C.low.visibility.view');
    Route::get('/shopifyB2C', [Shopifyb2cController::class, 'shopifyb2cView'])->name('shopifyB2C');
    Route::post('/shopifyb2c/saveLowProfit', [Shopifyb2cController::class, 'saveLowProfit']);
    Route::post('/shopifyb2c-analytics/import', [Shopifyb2cController::class, 'importShopifyB2CAnalytics'])->name('shopifyb2c.analytics.import');
    Route::get('/shopifyb2c-analytics/export', [Shopifyb2cController::class, 'exportShopifyB2CAnalytics'])->name('shopifyb2c.analytics.export');
    Route::get('/shopifyb2c-analytics/sample', [Shopifyb2cController::class, 'downloadSample'])->name('shopifyb2c.analytics.sample');

    // listing Audit Wayfair
    Route::any('/update-wayfair-sku-pricing', [ListingWayfairController::class, 'updatePricing'])->name('wayfair.priceUpdate');
    Route::get('/listing-wayfair', [ListingWayfairController::class, 'listingWayfair'])->name('listing.wayfair');
    Route::get('/listing_wayfair/view-data', [ListingWayfairController::class, 'getViewListingWayfairData']);
    Route::post('/listing_wayfair/save-status', [ListingWayfairController::class, 'saveStatus']);
    Route::post('/listing_wayfair/import', [ListingWayfairController::class, 'import'])->name('listing_wayfair.import');
    Route::get('/listing_wayfair/export', [ListingWayfairController::class, 'export'])->name('listing_wayfair.export');
    Route::get('/listing_wayfair/sample', [ListingWayfairController::class, 'downloadSample'])->name('listing_wayfair.sample');
    Route::get('/Wayfair-zero-view', action: [WayfairZeroController::class, 'wayfairZeroView'])->name('wayfair.zero.view');
    Route::get('/Wayfair-low-visibility-view', action: [WayfairLowVisibilityController::class, 'wayfairLowVisibilityView'])->name('wayfair.low.visibility.view');
    Route::get('/Wayfair', [WayfairController::class, 'wayfairView'])->name('Wayfair');
    Route::post('/wayfair/saveLowProfit', [WayfairController::class, 'saveLowProfit']);
    Route::post('/wayfair-analytics/import', [WayfairController::class, 'importWayfairAnalytics'])->name('wayfair.analytics.import');
    Route::get('/wayfair-analytics/export', [WayfairController::class, 'exportWayfairAnalytics'])->name('wayfair.analytics.export');
    Route::get('/wayfair-analytics/sample', [WayfairController::class, 'downloadSample'])->name('wayfair.analytics.sample');

    // listing Audit Neweggb2c
    Route::get('/neweggB2C', [Neweggb2cController::class, 'neweggB2CView'])->name('neweggB2C');
    Route::post('/neweggB2C/saveLowProfit', [Neweggb2cController::class, 'saveLowProfit']);
    Route::get('/Neweggb2c-zero-view', action: [Neweggb2cZeroController::class, 'neweggB2CZeroView'])->name('neweggb2c.zero.view');
    Route::get('/Neweggb2c-low-visibility-view', action: [Neweggb2cLowVisibilityController::class, 'neweggB2CLowVisibilityView'])->name('neweggb2c.low.visibility.view');
    Route::get('/zero-neweggb2b', [NeweggB2BZeroController::class, 'neweggB2BZeroview'])->name('zero.neweggb2b');
    Route::get('/zero_neweggb2b/view-data', [NeweggB2BZeroController::class, 'getViewNeweggB2BZeroData']);
    Route::post('/zero_neweggb2b/reason-action/update-data', [NeweggB2BZeroController::class, 'updateReasonAction']);
    Route::get('/listing-neweggb2c', [ListingNeweggB2CController::class, 'listingNeweggB2C'])->name('listing.neweggb2c');
    Route::get('/listing_neweggb2c/view-data', [ListingNeweggB2CController::class, 'getViewListingNeweggB2CData']);
    Route::post('/listing_neweggb2c/save-status', [ListingNeweggB2CController::class, 'saveStatus']);

    // listing audit reverb
    Route::get('/listing-reverb', [ListingReverbController::class, 'listingReverb'])->name('listing.reverb');
    Route::get('/listing_reverb/view-data', [ListingReverbController::class, 'getViewListingReverbData']);
    Route::post('/listing_reverb/save-status', [ListingReverbController::class, 'saveStatus']);
    Route::get('/reverb', [ReverbController::class, 'reverbView'])->name('reverb');
    Route::post('/listing_reverb/import', [ListingReverbController::class, 'import'])->name('listing_reverb.import');
    Route::get('/listing_reverb/export', [ListingReverbController::class, 'export'])->name('listing_reverb.export');
    Route::get('/listing_reverb/sample', [ListingReverbController::class, 'downloadSample'])->name('listing_reverb.sample');
    Route::post('/reverb/saveLowProfit', [ReverbController::class, 'saveLowProfit']);
    Route::get('/reverb/zero/view', [ReverbZeroController::class, 'index'])->name('reverb.zero.view');
    Route::get('/reverb-low-visiblity-view', [ReverbLowVisibilityController::class, 'reverbLowVisibilityview'])->name('reverb.low.visibility.view');

    // listing temu
    Route::get('/listing-temu', [ListingTemuController::class, 'listingTemu'])->name('listing.temu');
    Route::get('/listing_temu/view-data', [ListingTemuController::class, 'getViewListingTemuData']);
    Route::post('/listing_temu/save-status', [ListingTemuController::class, 'saveStatus']);
    Route::post('/listing_temu/import', [ListingTemuController::class, 'import'])->name('listing_temu.import');
    Route::get('/listing_temu/export', [ListingTemuController::class, 'export'])->name('listing_temu.export');
    Route::get('/temu', [TemuController::class, 'temuView'])->name('temu');
    Route::get('/temu-pricing-cvr', [TemuController::class, 'temuPricingCVR'])->name('temu.pricing');
    Route::get('/temu-pricing-inc', [TemuController::class, 'temuPricingCVRinc'])->name('temu.pricing.inc');
    Route::get('/temu-pricing-dsc', [TemuController::class, 'temuPricingCVRdsc'])->name('temu.pricing.dsc');

    Route::post('/temu/save-sprice', [TemuController::class, 'saveSpriceToDatabase'])->name('temu.save-sprice');
    Route::post('/temu/save-ship', [TemuController::class, 'saveShipToDatabase']);

    Route::get('/temu-zero-view', [TemuZeroController::class, 'temuZeroView'])->name('temu.zero.view');
    Route::get('/temu-low-visiblity-view', [TemuLowVisibilityController::class, 'temuLowVisibilityView'])->name('temu.low.visibility.view');
    Route::post('/temu-analytics/import', [TemuController::class, 'importTemuAnalytics'])->name('temu.analytics.import');
    Route::get('/temu-analytics/export', [TemuController::class, 'exportTemuAnalytics'])->name('temu.analytics.export');
    Route::get('/temu-analytics/sample', [TemuController::class, 'downloadSample'])->name('temu.analytics.sample');

    // Temu Tabulator View
    Route::get('/temu-tabulator', [TemuController::class, 'temuTabulatorView'])->name('temu.tabulator');
    Route::post('/temu-column-visibility', [TemuController::class, 'saveTemuColumnVisibility']);
    Route::get('/temu-column-visibility', [TemuController::class, 'getTemuColumnVisibility']);

    // Temu 2 Tabulator View (separate tables: temu2_daily_data, temu2_daily_data_l60)
    Route::get('/temu2-tabulator', [TemuController::class, 'temu2TabulatorView'])->name('temu2.tabulator');
    Route::get('/temu2/daily-data', [TemuController::class, 'getTemu2DailyData'])->name('temu2.daily.data');
    Route::get('/temu2/daily-data-l7', [TemuController::class, 'getTemu2DailyDataL7'])->name('temu2.daily.data.l7');
    Route::post('/temu2/upload-daily-data-chunk', [TemuController::class, 'uploadDailyDataTemu2Chunk']);
    Route::post('/temu2/upload-daily-data-l60-chunk', [TemuController::class, 'uploadDailyDataTemu2L60Chunk']);
    Route::post('/temu2-column-visibility', [TemuController::class, 'saveTemu2ColumnVisibility']);
    Route::get('/temu2-column-visibility', [TemuController::class, 'getTemu2ColumnVisibility']);

    // Temu Pricing Upload
    Route::post('/temu-pricing/upload', [TemuController::class, 'uploadTemuPricing'])->name('temu.pricing.upload');
    Route::get('/temu-pricing/sample', [TemuController::class, 'downloadTemuPricingSample'])->name('temu.pricing.sample');

    // Temu View Data Upload
    Route::post('/temu-view-data/upload', [TemuController::class, 'uploadTemuViewData'])->name('temu.viewdata.upload');
    Route::get('/temu-view-data/sample', [TemuController::class, 'downloadTemuViewDataSample'])->name('temu.viewdata.sample');

    // Temu Ad Data Upload
    Route::post('/temu-ad-data/upload', [TemuController::class, 'uploadTemuAdData'])->name('temu.addata.upload');
    Route::get('/temu-ad-data/sample', [TemuController::class, 'downloadTemuAdDataSample'])->name('temu.addata.sample');

    // Temu R Pricing Upload
    Route::post('/temu-r-pricing/upload', [TemuController::class, 'uploadTemuRPricing'])->name('temu.rpricing.upload');
    Route::get('/temu-r-pricing/sample', [TemuController::class, 'downloadTemuRPricingSample'])->name('temu.rpricing.sample');

    // Temu Decrease Page
    Route::get('/temu-decrease', [TemuController::class, 'temuDecreaseView'])->name('temu.decrease');
    Route::get('/temu-decrease-data', [TemuController::class, 'getTemuDecreaseData']);
    Route::get('/temu-decrease-data-l7', [TemuController::class, 'getTemuDecreaseDataL7'])->name('temu.decrease.l7');
    Route::get('/temu-badge-history', [TemuController::class, 'getTemuBadgeHistory']);
    Route::post('/temu-pricing/update-price', [TemuController::class, 'updateTemuPrice']);
    Route::post('/temu-pricing/save-sprice', [TemuController::class, 'saveTemuSprice']);
    Route::post('/temu-decrease-column-visibility', [TemuController::class, 'saveTemuDecreaseColumnVisibility']);
    Route::get('/temu-decrease-column-visibility', [TemuController::class, 'getTemuDecreaseColumnVisibility']);
    Route::post('/temu-decrease/save-listing-status', [TemuController::class, 'saveListingStatus']);

    // Temu LMP (table + upload)
    Route::get('/temu-lmp', [TemuController::class, 'temuLmpPage'])->name('temu.lmp');
    Route::post('/temu-lmp/upload', [TemuController::class, 'uploadTemuLmp'])->name('temu.lmp.upload');
    Route::post('/temu-lmp/save', [TemuController::class, 'saveTemuLmp'])->name('temu.lmp.save');

    // Temu Metrics and Cell Update
    Route::get('/temu-metrics-history', [TemuController::class, 'getTemuMetricsHistory'])->name('temu.metrics.history');
    Route::post('/temu-update-cell', [TemuController::class, 'updateTemuCellData'])->name('temu.update.cell');
    Route::post('/temu-save-amazon-prices', [TemuController::class, 'saveTemuAmazonPriceUpdates'])->name('temu.save.amazon.prices');
    Route::post('/temu-save-r-prices', [TemuController::class, 'saveTemuRPriceUpdates'])->name('temu.save.r.prices');
    Route::post('/temu-clear-sprice', [TemuController::class, 'clearAllTemuSprice'])->name('temu.clear.sprice');
    Route::post('/temu-save-amazon-prices', [TemuController::class, 'saveTemuAmazonPriceUpdates'])->name('temu.save.amazon.prices');
    Route::post('/temu-store-daily-avg-views', [TemuController::class, 'storeDailyAvgViews'])->name('temu.store.daily.avg.views');
    Route::get('/temu-avg-views-history', [TemuController::class, 'getAvgViewsHistory'])->name('temu.avg.views.history');
    Route::get('/temu-latest-avg-views', [TemuController::class, 'getLatestAvgViews'])->name('temu.latest.avg.views');
    Route::post('/temu-pricing/save-starget', [TemuController::class, 'saveStarget'])->name('temu.save.starget');

    // Advertisement Master view routes
    Route::get('/kw-amazon', [KwAmazonController::class, 'Amazon'])->name('advertisment.kw.amazon');
    Route::post('/update-checkbox-flag', [KwAmazonController::class, 'updateCheckboxes']);
    Route::get('/kw-ebay', [KwEbayController::class, 'Ebay'])->name('advertisment.kw.eBay');
    Route::get('/kw-walmart', [WalmartController::class, 'Walmart'])->name('advertisment.kw.walmart');
    Route::get('/prod-target-amazon', [ProdTargetAmazonController::class, 'Amazon'])->name('advertisment.prod.target.Amazon');
    Route::post('/update-all-checkbox', [ProdTargetAmazonController::class, 'updateCheckbox']);
    Route::get('/headline-amazon', [HeadlineAmazonController::class, 'Amazon'])->name('advertisment.headline.Amazon');
    Route::post('/update-checkbox', [HeadlineAmazonController::class, 'update']);
    Route::get('/promoted-ebay', [PromotedEbayController::class, 'Ebay'])->name('advertisment.promoted.eBay');
    Route::get('/google-shopping', [GoogleShoppingController::class, 'GoogleShopping'])->name('advertisment.shopping.google');
    Route::get('/demand-gen-googleNetworks', [GoogleNetworksController::class, 'GoogleNetworks'])->name('advertisment.demand.gen.googleNetworks');
    Route::get('/productwise-fb-img', [ProductWiseMetaParentController::class, 'FacebookImage'])->name('advertisment.demand.productWise.metaParent.img.facebook');
    Route::get('/productwise-insta-img', [ProductWiseMetaParentController::class, 'InstagramImage'])->name('advertisment.demand.productWise.metaParent.img.instagram');
    Route::get('/productwise-fb-video', [ProductWiseMetaParentController::class, 'FacebookVideo'])->name('advertisment.demand.productWise.metaParent.video.facebook');
    Route::get('/productwise-insta-video', [ProductWiseMetaParentController::class, 'InstagramVideo'])->name('advertisment.demand.productWise.metaParent.video.instagram');

    // Ajax Advertisement Master view routes
    Route::get('/kw-ebay-get-data', [KwEbayController::class, 'getViewKwEbayData'])->name('kwEbay.getData');
    Route::post('/update-checkbox-flag', [KwEbayController::class, 'updateCheckboxes']);
    Route::get('/kw-walmart-get-data', [WalmartController::class, 'getViewKwWalmartData'])->name('kwWalmart.getData');
    Route::post('/update-checkbox-flag', [WalmartController::class, 'updateCheckboxes']);
    Route::get('/google-shopping-get-data', [GoogleShoppingController::class, 'getViewGoogleShoppingData'])->name('googleShopping.getData');

    // channel master index view routes
    Route::get('/return-analysis', [ReturnController::class, 'return_master_index'])->name('return.master');
    Route::get('/expenses-analysis', [ExpensesController::class, 'expenses_master_index'])->name('expenses.master');
    Route::get('/health-analysis', [HealthController::class, 'health_master_index'])->name('health.master');

    // product master index view routes
    Route::get('/pricing.analysis', action: [PricingAnalysisController::class, 'pricingAnalysis'])->name('pricing.analysis');
    Route::get('/pRoi.analysis', action: [PrAnalysisController::class, 'pRoiAnalysis'])->name('pRoi.analysis');
    Route::get('/return.analysis', action: [ReturnAnalysisController::class, 'returnAnalysis'])->name('return.analysis');
    Route::get('/stock.analysis', action: [StockAnalysisController::class, 'stockAnalysis'])->name('stock.analysis');
    Route::get('/shortfall.analysis', action: [ShortFallAnalysisController::class, 'shortFallAnalysis'])->name('shortfall.analysis');
    Route::get('/costprice.analysis', action: [CostpriceAnalysisController::class, 'costpriceAnalysis'])->name('costprice.analysis');
    Route::get('/forecast.analysis', action: [ForecastAnalysisController::class, 'forecastAnalysis'])->name('forecast.analysis');
    Route::get('/approval.required', action: [ForecastAnalysisController::class, 'approvalRequired'])->name('approval.required');
    Route::get('/transit', action: [ForecastAnalysisController::class, 'transit'])->name('transit');
    Route::get('/forecast-analysis/get-sku-quantity', action: [ForecastAnalysisController::class, 'getSkuQuantity'])->name('forecast.analysis.get.sku.quantity');

    // ebay lqs cvr
    Route::get('/ebaycvrLQS.master', action: [EbayCvrLqsController::class, 'cvrLQSMaster'])->name('ebaycvrLQS.master');
    Route::get('/ebaycvrLQS/view-data', [EbayCvrLqsController::class, 'getViewEbayCvrData'])->name('ebaycvrLQS.viewData');
    Route::post('/ebay-cvr-lqs/save-action', [EbayCvrLqsController::class, 'saveEbayAction']);

    Route::post('/import-ebay-cvr-data', [EbayCvrLqsController::class, 'importEbayCVRData'])->name('import.ebay.cvr');

    // To Be DC routes
    Route::get('/tobedc_list', [ToBeDCController::class, 'index'])->name('tobedc.list');

    // Supplier routes
    Route::get('/supplier.list', [SupplierController::class, 'supplierList'])->name('supplier.list');
    Route::get('/supplier.list.json', [SupplierController::class, 'getSuppliersJson'])->name('supplier.list.json');
    Route::get('/supplier/categories.json', [SupplierController::class, 'getCategoriesJson'])->name('supplier.categories.json');
    Route::post('/supplier.create', [SupplierController::class, 'postSupplier'])->name('supplier.create');
    Route::post('/supplier/{id}/approval-status', [SupplierController::class, 'updateApprovalStatus'])->name('supplier.approval-status');
    Route::delete('/supplier/delete/{id}', [SupplierController::class, 'deleteSupplier'])->name('supplier.delete');
    Route::post('/forecast-analysis/link-supplier-parent', [\App\Http\Controllers\ProductMaster\ForecastAnalysisController::class, 'linkSupplierToParent'])->name('forecast.link-supplier-parent');
    Route::post('/supplier/import', [SupplierController::class, 'bulkImport'])->name('supplier.import');
    Route::post('/supplier-rating', [SupplierController::class, 'storeRating'])->name('supplier.rating.save');

    // Catategory routes
    Route::get('/category.list', [CategoryController::class, 'categoryList'])->name('category.list');
    Route::get('/category-master', [CategoryController::class, 'categoryMaster'])->name('category.master');
    Route::get('/category-master-data-view', [CategoryController::class, 'getCategoryMasterData'])->name('category.master.data');
    Route::get('/id-master', [CategoryController::class, 'idMaster'])->name('id.master');
    Route::get('/id-master-data-view', [CategoryController::class, 'getIdMasterData'])->name('id.master.data');
    Route::get('/dim-wt-master', [CategoryController::class, 'dimWtMaster'])->name('dim.wt.master');
    Route::get('/qc-upgrade', [CategoryController::class, 'qcUpgrade'])->name('qc.upgrade');
    Route::get('/dim-wt-master-ctn', [CategoryController::class, 'dimWtMasterCtn'])->name('dim.wt.master.ctn');
    Route::get('/dim-wt-master-data-view', [CategoryController::class, 'getDimWtMasterData'])->name('dim.wt.master.data');
    Route::get('/dim-wt-master/skus', [CategoryController::class, 'getSkusForDimWtDropdown'])->name('dim.wt.master.skus');
    Route::post('/dim-wt-master/store', [CategoryController::class, 'storeDimWtMaster'])->name('dim.wt.master.store');
    Route::post('/dim-wt-master/update', [CategoryController::class, 'updateDimWtMaster'])->name('dim.wt.master.update');
    Route::post('/instructions-item-pkg/update', [InstructionsItemPkgController::class, 'update'])->name('instructions.item.pkg.update');
    Route::post('/qc-improvement-req-before-item-pkg/update', [QcImprovementReqBeforeItemPkgController::class, 'update'])->name('qc.improvement.req.before.item.pkg.update');
    Route::post('/dim-wt-master/import', [CategoryController::class, 'importDimWtMaster'])->name('dim.wt.master.import');
    Route::post('/dim-wt-master/push-data', [CategoryController::class, 'pushDimWtDataToPlatforms'])->name('dim.wt.master.push');
    Route::get('/shipping-master', [CategoryController::class, 'shippingMaster'])->name('shipping.master');
    Route::get('/shipping-master-data-view', [CategoryController::class, 'getShippingMasterData'])->name('shipping.master.data');
    Route::get('/shipping-master/statuses', [CategoryController::class, 'getShippingMasterStatuses'])->name('shipping.master.statuses');
    Route::get('/shipping-master/skus', [CategoryController::class, 'getSkusForShippingMaster'])->name('shipping.master.skus');
    Route::post('/shipping-master/store', [CategoryController::class, 'storeShippingMaster'])->name('shipping.master.store');
    Route::post('/shipping-master/update', [CategoryController::class, 'updateShippingMaster'])->name('shipping.master.update');
    Route::post('/shipping-master/import', [CategoryController::class, 'importShippingMaster'])->name('shipping.master.import');
    Route::get('/general-specific-master', [CategoryController::class, 'generalSpecificMaster'])->name('general.specific.master');
    Route::get('/general-specific-master-data-view', [CategoryController::class, 'getGeneralSpecificMasterData'])->name('general.specific.master.data');
    Route::get('/general-specific-master/skus', [CategoryController::class, 'getSkusForDropdown'])->name('general.specific.master.skus');
    Route::post('/general-specific-master/store', [CategoryController::class, 'storeGeneralSpecificMaster'])->name('general.specific.master.store');
    Route::post('/general-specific-master/update', [CategoryController::class, 'updateGeneralSpecificMaster'])->name('general.specific.master.update');
    Route::post('/general-specific-master/import', [CategoryController::class, 'importGeneralSpecificMaster'])->name('general.specific.master.import');
    Route::get('/compliance-master', [CategoryController::class, 'complianceMaster'])->name('compliance.master');
    Route::get('/compliance-master-data-view', [CategoryController::class, 'getComplianceMasterData'])->name('compliance.master.data');
    Route::post('/compliance-master/store', [CategoryController::class, 'storeComplianceMaster'])->name('compliance.master.store');
    Route::post('/compliance-master/update', [CategoryController::class, 'updateComplianceMaster'])->name('compliance.master.update');
    Route::post('/compliance-master/field-image', [CategoryController::class, 'uploadComplianceFieldImage'])->name('compliance.master.field.image');
    Route::post('/compliance-master/field-pdf', [CategoryController::class, 'uploadComplianceFieldPdf'])->name('compliance.master.field.pdf');
    Route::post('/compliance-master/import', [CategoryController::class, 'importComplianceMaster'])->name('compliance.master.import');
    Route::get('/packing-instructions-master', [CategoryController::class, 'packingInstructionsMaster'])->name('packing.instructions.master');
    Route::get('/packing-instructions-master-data-view', [CategoryController::class, 'getComplianceMasterData'])->name('packing.instructions.master.data');
    Route::post('/packing-instructions-master/store', [CategoryController::class, 'storePackingInstructionsMaster'])->name('packing.instructions.master.store');
    Route::post('/packing-instructions-master/update', [CategoryController::class, 'updatePackingInstructionsMaster'])->name('packing.instructions.master.update');
    Route::post('/packing-instructions-master/clear', [CategoryController::class, 'clearPackingInstructionsMaster'])->name('packing.instructions.master.clear');
    Route::get('/packing-instructions-master/images', [CategoryController::class, 'listPackingInstructionImages'])->name('packing.instructions.master.images');
    Route::post('/packing-instructions-master/upload-image', [CategoryController::class, 'uploadPackingInstructionImage'])->name('packing.instructions.master.upload.image');
    Route::post('/packing-instructions-master/upload-cdr', [CategoryController::class, 'uploadPackingInstructionCdr'])->name('packing.instructions.master.upload.cdr');
    Route::post('/packing-instructions-master/delete-cdr', [CategoryController::class, 'deletePackingInstructionCdr'])->name('packing.instructions.master.delete.cdr');
    Route::post('/packing-instructions-master/delete-image', [CategoryController::class, 'deletePackingInstructionImage'])->name('packing.instructions.master.delete.image');
    Route::post('/packing-instructions-master/ai-defect-scan', [CategoryController::class, 'aiDefectScanPackingImage'])->name('packing.instructions.master.ai.scan');
    Route::get('/extra-features-master', [CategoryController::class, 'extraFeaturesMaster'])->name('extra.features.master');
    Route::get('/extra-features-master-data-view', [CategoryController::class, 'getExtraFeaturesMasterData'])->name('extra.features.master.data');
    Route::post('/extra-features-master/store', [CategoryController::class, 'storeExtraFeaturesMaster'])->name('extra.features.master.store');
    Route::post('/extra-features-master/import', [CategoryController::class, 'importExtraFeaturesMaster'])->name('extra.features.master.import');
    Route::get('/a-plus-images-master', [CategoryController::class, 'aPlusImagesMaster'])->name('a.plus.images.master');
    Route::get('/a-plus-images-master-data-view', [CategoryController::class, 'getAPlusImagesMasterData'])->name('a.plus.images.master.data');
    Route::post('/a-plus-images-master/store', [CategoryController::class, 'storeAPlusImagesMaster'])->name('a.plus.images.master.store');
    Route::post('/a-plus-images-master/import', [CategoryController::class, 'importAPlusImagesMaster'])->name('a.plus.images.master.import');
    Route::get('/keywords-master', [CategoryController::class, 'keywordsMaster'])->name('keywords.master');
    Route::get('/keywords-master-data-view', [CategoryController::class, 'getKeywordsMasterData'])->name('keywords.master.data');
    Route::post('/keywords-master/store', [CategoryController::class, 'storeKeywordsMaster'])->name('keywords.master.store');
    Route::post('/keywords-master/import', [CategoryController::class, 'importKeywordsMaster'])->name('keywords.master.import');
    Route::get('/package-includes-master', [CategoryController::class, 'packageIncludesMaster'])->name('package.includes.master');
    Route::get('/package-includes-master-data-view', [CategoryController::class, 'getPackageIncludesMasterData'])->name('package.includes.master.data');
    Route::post('/package-includes-master/store', [CategoryController::class, 'storePackageIncludesMaster'])->name('package.includes.master.store');
    Route::post('/package-includes-master/import', [CategoryController::class, 'importPackageIncludesMaster'])->name('package.includes.master.import');
    Route::get('/qa-master', [CategoryController::class, 'qaMaster'])->name('qa.master');
    Route::get('/qa-master-data-view', [CategoryController::class, 'getQAMasterData'])->name('qa.master.data');
    Route::post('/qa-master/store', [CategoryController::class, 'storeQAMaster'])->name('qa.master.store');
    Route::post('/qa-master/import', [CategoryController::class, 'importQAMaster'])->name('qa.master.import');
    Route::get('/competitors-master', [CategoryController::class, 'competitorsMaster'])->name('competitors.master');
    Route::get('/competitors-master-data-view', [CategoryController::class, 'getCompetitorsMasterData'])->name('competitors.master.data');
    Route::post('/competitors-master/store', [CategoryController::class, 'storeCompetitorsMaster'])->name('competitors.master.store');
    Route::post('/competitors-master/import', [CategoryController::class, 'importCompetitorsMaster'])->name('competitors.master.import');
    Route::get('/target-keywords-master', [CategoryController::class, 'targetKeywordsMaster'])->name('target.keywords.master');
    Route::get('/target-keywords-master-data-view', [CategoryController::class, 'getTargetKeywordsMasterData'])->name('target.keywords.master.data');
    Route::get('/target-products-master', [CategoryController::class, 'targetProductsMaster'])->name('target.products.master');
    Route::get('/target-products-master-data-view', [CategoryController::class, 'getTargetProductsMasterData'])->name('target.products.master.data');
    Route::get('/tag-lines-master', [CategoryController::class, 'tagLinesMaster'])->name('tag.lines.master');
    Route::get('/tag-lines-master-data-view', [CategoryController::class, 'getTagLinesMasterData'])->name('tag.lines.master.data');
    Route::get('/group-master', [CategoryController::class, 'groupMaster'])->name('group.master');
    Route::get('/group-master-data-view', [CategoryController::class, 'getGroupMasterData'])->name('group.master.data');
    Route::get('/group-master-groups', [CategoryController::class, 'getProductGroups'])->name('group.master.groups');
    Route::get('/group-master-categories', [CategoryController::class, 'getProductCategories'])->name('group.master.categories');
    Route::post('/group-master-upload-excel', [CategoryController::class, 'uploadGroupMasterExcel'])->name('group.master.upload.excel');
    Route::post('/group-master-update-field', [CategoryController::class, 'updateProductField'])->name('group.master.update.field');
    Route::post('/group-master-store-group', [CategoryController::class, 'storeProductGroup'])->name('group.master.store.group');
    Route::post('/group-master-store-category', [CategoryController::class, 'storeProductCategory'])->name('group.master.store.category');
    Route::get('/seo-keywords-master', [CategoryController::class, 'seoKeywordsMaster'])->name('seo.keywords.master');
    Route::get('/seo-keywords-master-data-view', [CategoryController::class, 'getSeoKeywordsMasterData'])->name('seo.keywords.master.data');
    Route::post('/category.create', [CategoryController::class, 'postCategory'])->name('category.create');
    Route::delete('/category/delete/{id}', [CategoryController::class, 'destroy'])->name('category.delete');
    Route::post('/category/bulk-delete', [CategoryController::class, 'bulkDelete'])->name('category.bulk-delete');

    // To Order Analysis routes
    Route::controller(ToOrderAnalysisController::class)->group(function () {
        Route::get('/test', 'test')->name('test');
        Route::get('/to-order-analysis', 'toOrderAnalysisNew')->name('to.order.analysis');
        Route::get('/to-order-analysis-new', 'toOrderAnalysisNew')->name('to.order.analysis.new');
        Route::get('/to-order-analysis/data', 'getToOrderAnalysis')->name('to.order.analysis.data');
        Route::post('/update-link', 'updateLink')->name('update.rfq.link');
        Route::post('/mfrg-progresses/insert', 'storeMFRG')->name('mfrg.progresses.insert');
        Route::post('/save-to-order-review', 'storeToOrderReview')->name('save.to_order_review');
        Route::post('/to-order-analysis/delete', 'deleteToOrderAnalysis')->name('delete.to_order_analysis');
    });

    // Movement Analysis
    Route::get('/movement.analysis', action: [MovementAnalysisController::class, 'movementAnalysis'])->name('movement.analysis');
    Route::post('/update-smsl', [MovementAnalysisController::class, 'updateSmsl'])->name('update-smsl');

    // Update Forecast Sheet
    Route::post('/update-forecast-data', [ForecastAnalysisController::class, 'updateForcastSheet'])->name('update.forecast.data');

    // MFRG In Progress
    Route::controller(MFRGInProgressController::class)->group(function () {
        Route::get('/mfrg-in-progress', 'index')->name('mfrg.in.progress');
        Route::post('/mfrg-progresses/inline-update-by-sku', 'inlineUpdateBySku');
        Route::post('/mfrg-progresses/delete', 'deleteBySkus')->name('mfrg.progresses.delete');
        Route::post('/mfrg-progresses/restore', 'restoreBySkus')->name('mfrg.progresses.restore');
        Route::get('/mfrg-progresses/archived-count', 'archivedMfrgCount')->name('mfrg.progresses.archived-count');
        Route::get('/convert-currency', 'convert');
        Route::post('/ready-to-ship/insert', 'storeDataReadyToShip')->name('ready.to.ship.insert');

        Route::get('/mfrg-in-progress/new', 'newMfrgView')->name('mfrg.in.progress.new');
        Route::get('/mfrg-in-progress/data', 'getMfrgProgressData')->name('mfrg.in.progress.data');
    });

    // Ready To Ship
    Route::get('/ready-to-ship', [ReadyToShipController::class, 'index'])->name('ready.to.ship');
    Route::get('/ready-to-ship/packing-list-links', [ReadyToShipController::class, 'packingListLinksJson'])->name('ready.to.ship.packing.list.links');
    Route::get('/ready-to-ship/r2s-total', [ReadyToShipController::class, 'r2sTotal'])->name('ready.to.ship.r2s.total');
    Route::post('/ready-to-ship/inline-update-by-sku', [ReadyToShipController::class, 'inlineUpdateBySku']);
    Route::post('/ready-to-ship/revert-back-mfrg', [ReadyToShipController::class, 'revertBackMfrg']);
    Route::post('/ready-to-ship/move-to-transit', [ReadyToShipController::class, 'moveToTransit']);
    Route::post('/ready-to-ship/delete-items', [ReadyToShipController::class, 'deleteItems']);

    // China Load
    Route::get('/china-load', [ChinaLoadController::class, 'index'])->name('china.load');
    Route::post('/china-load/inline-update-by-sl', [ChinaLoadController::class, 'inlineUpdateBySl']);

    // On Sea Transit
    Route::get('/on-sea-transit', [OnSeaTransitController::class, 'index'])->name('on.sea.transit');
    Route::post('/on-sea-transit/inline-update-or-create', [OnSeaTransitController::class, 'inlineUpdateOrCreate']);

    // On Road Transit
    Route::get('/on-road-transit', [OnRoadTransitController::class, 'index'])->name('on.road.transit');
    Route::post('/on-road-transit/inline-update-or-create', [OnRoadTransitController::class, 'inlineUpdateOrCreate']);

    // Transit Container Details
    Route::get('/transit-container-details', [TransitContainerDetailsController::class, 'index'])->name('transit.container.details');
    Route::post('/transit-container/add-tab', [TransitContainerDetailsController::class, 'addTab']);
    Route::post('/transit-container/save-row', [TransitContainerDetailsController::class, 'saveRow']);
    Route::post('/upload-image', [TransitContainerDetailsController::class, 'uploadImage'])->name('transit.upload-image');
    Route::get('/transit-container-changes', [TransitContainerDetailsController::class, 'transitContainerChanges'])->name('transit.container.changes');
    Route::get('/transit-container-new', [TransitContainerDetailsController::class, 'transitContainerNew'])->name('transit.container.new');
    Route::post('/transit-container/save', [TransitContainerDetailsController::class, 'transitContainerStoreItems']);
    Route::post('/transit-container/delete', [TransitContainerDetailsController::class, 'deleteTransitItem']);
    Route::get('/transit-container/history', [TransitContainerDetailsController::class, 'getHistory']);

    Route::post('/inventory-warehouse/push', [InventoryWarehouseController::class, 'pushInventory'])->name('inventory.push');
    Route::post('/inventory-warehouse/push-single', [InventoryWarehouseController::class, 'pushSingleItem'])->name('inventory.push.single');
    Route::get('/inventory-warehouse', [InventoryWarehouseController::class, 'index'])->name('inventory.index');
    Route::get('/inventory-warehouse/check-pushed', [InventoryWarehouseController::class, 'checkPushed']);

    Route::controller(ArrivedContainerController::class)->group(function () {
        Route::get('/arrived/container', 'index')->name('arrived.container');
        Route::post('/arrived/container/push', 'pushArrivedContainer');
        Route::post('/arrived/container/save-row', 'saveArrivedRow');
        Route::get('/arrived/container/history', 'getHistory');
        Route::get('/arrived/container/summary', 'containerSummary')->name('container.summary');
    });

    Route::controller(QualityEnhanceController::class)->group(function () {
        Route::get('/quality-enhance/list', 'index')->name('quality.enhance');
        Route::post('/quality-enhance/get-parent', 'getParentFromSKU')->name('quality.enhance.getParent');
        Route::get('/quality-enhance/data', 'getData')->name('quality.enhance.data');
        Route::post('/quality-enhance/save', 'saveQualityEnhance')->name('quality.enhance.save');
        Route::post('/quality-enhance/update', 'update')->name('quality.enhance.update');
    });

    Route::controller(ContainerPlanningController::class)->group(function () {
        Route::get('/container-planning', 'index')->name('container.planning');
        Route::get('/container-planning/data', 'getContainerPlannings')->name('container.planning.data');
        Route::get('/container-planning/po-details/{id}', 'getPoDetails');
        Route::post('/container-planning/save', 'saveContainerPlanning')->name('container.planning.save');
        Route::post('/container-planning/delete', 'deleteContainerPlanning')->name('container.planning.delete');
    });

    Route::controller(UpComingContainerController::class)->group(function () {
        Route::get('/upcoming-containers', 'index')->name('upcoming.container');
        Route::get('/upcoming-container/data', 'getUpComingContainer')->name('upcoming.container.data');
        Route::post('/upcoming-container/save', 'saveUpComingContainer')->name('upcoming.container.save');
        Route::post('/upcoming-container/delete', 'deleteUpcomingContainer')->name('upcoming.container.delete');
    });

    // api data view routes
    Route::get('/shopify/products', [ShopifyController::class, 'getProducts']);

    // data save routes
    Route::post('/product_master/store', [ProductMasterController::class, 'store'])->name('product_master.store');
    Route::post('/product_master/duplicate', [ProductMasterController::class, 'duplicateSku'])->name('product_master.duplicate');
    Route::post('/product_master/update-field', [ProductMasterController::class, 'updateField'])->name('product_master.update-field');
    Route::post('/product_master/update-verified', [ProductMasterController::class, 'updateVerified'])->name('product_master.update-verified');
    Route::post('/product-master/import', [ProductMasterController::class, 'import'])->name('product_master.import');
    Route::post('/product-master/bulk-update-all', [ProductMasterController::class, 'bulkUpdateAll'])->name('product_master.bulk_update_all');
    Route::post('/product-master/restore-bulk-update', [ProductMasterController::class, 'restoreBulkUpdate'])->name('product_master.restore_bulk_update');
    Route::get('/product-master/download-template', [ProductMasterController::class, 'downloadTemplate'])->name('product_master.download_template');
    Route::post('/product-master/batch-update', [ProductMasterController::class, 'batchUpdate']);
    Route::post('/channel_master/store', [ChannelMasterController::class, 'store'])->name('channel_master.store');
    Route::post('/channel-master/update-sheet-link', [ChannelMasterController::class, 'updateSheetLink']);
    Route::post('/channels-master/toggle-flag', [ChannelMasterController::class, 'toggleCheckboxFlag']);
    Route::post('/update-channel-type', [ChannelMasterController::class, 'updateType']);
    Route::post('/update-channel-percentage', [ChannelMasterController::class, 'updatePercentage']);

    // data update routes
    Route::post('/channel_master/update', [ChannelMasterController::class, 'update']);
    Route::post('/channel_master/update-name-type', [ChannelMasterController::class, 'updateNameAndType'])->name('channel_master.update_name_type');

    // data delete routes
    Route::delete('/product_master/delete', [ProductMasterController::class, 'destroy'])->name('product_master.destroy');

    // data archive routes
    // Route::post('/product_master/archive', [ProductMasterController::class, 'archive']);
    Route::get('/product_master/archived', [ProductMasterController::class, 'getArchived']);
    Route::post('/product_master/restore', [ProductMasterController::class, 'restore']);

    // reverb update
    Route::post('/update-reverb-column', [ReverbController::class, 'updateReverbColumn']);

    Route::post('/product-master/import-from-sheet', [ProductMasterController::class, 'importFromSheet']);

    // amazon db save routes
    Route::post('/amazon/save-nr', [OverallAmazonController::class, 'saveNrToDatabase']);
    Route::post('/amazon/save-variation', [OverallAmazonController::class, 'saveVariationToDatabase']);
    Route::post('/amazon/update-listed-live', [OverallAmazonController::class, 'updateListedLive']);

    Route::post('/amazon/save-sprice', [OverallAmazonController::class, 'saveSpriceToDatabase'])->name('amazon.save-sprice');
    Route::post('/save-bid-cap', [OverallAmazonController::class, 'saveBidCap'])->name('amazon.save-bid-cap');

    Route::post('/listing_audit_amazon/save-na', [ListingAuditAmazonController::class, 'saveAuditToDatabase']);
    Route::post('/amazon-zero/reason-action/update', [AmazonZeroController::class, 'updateReasonAction']);
    Route::post('/amazon-low-visibility/reason-action/update', [AmazonLowVisibilityController::class, 'updateReasonAction']);

    Route::get('/movement-pricing-master', [MovementPricingMaster::class, 'MovementPricingMaster']);
    Route::get('/pricing-analysis-data-views', [MovementPricingMaster::class, 'getViewPricingAnalysisData']);
    Route::post('/pricing-master/save', [MovementPricingMaster::class, 'save']);

    Route::get('/ads-pricing-master', [AdsMasterController::class, 'adsMaster']);
    Route::get('/ads-pricing-analysis-data-views', [AdsMasterController::class, 'getViewPricingAnalysisData']);
    Route::post('/pricing-master/save', [AdsMasterController::class, 'save']);

    // ebay db save routes
    Route::post('/ebay/save-nr', [EbayController::class, 'saveNrToDatabase']);
    Route::post('/ebay/update-listed-live', [EbayController::class, 'updateListedLive']);
    Route::post('/ebay-one/save-sprice', [EbayController::class, 'saveSpriceToDatabase'])->name('ebay.save-sprice');
    Route::post('/ebay-clear-sprice', [EbayController::class, 'clearEbaySprice']);
    Route::post('/ebay/save-sprice', [EbayTwoController::class, 'saveSpriceToDatabase'])->name('ebay.save-sprice');

    Route::post('/listing_ebay/save-status', [ListingEbayController::class, 'updateStatus']);
    Route::post('/listing_ebay/update-status', [ListingEbayController::class, 'updateStatus']);
    Route::post('/listing_audit_ebay/save-na', [ListingAuditEbayController::class, 'saveAuditToDatabase']);
    Route::post('/ebay-zero/reason-action/update', [EbayZeroController::class, 'updateReasonAction']);
    Route::post('/ebay-low-visibility/reason-action/update', [EbayLowVisibilityController::class, 'updateReasonAction']);
    Route::post('/ebay2-low-visibility/reason-action/update', [Ebay2LowVisibilityController::class, 'updateReasonAction']);
    Route::post('/ebay3-low-visibility/reason-action/update', [Ebay3LowVisibilityController::class, 'updateReasonAction']);

    // Shopify B2C route
    Route::get('/listing-audit-shopifyb2c', [ListingAuditShopifyb2cController::class, 'listingAuditShopifyb2c'])->name('listing.audit.shopifyb2c');
    Route::get('/listing_audit_shopifyb2c/view-data', [ListingAuditShopifyb2cController::class, 'getViewListingAuditShopifyb2cData']);
    Route::post('/shopifyb2c/save-nr', [Shopifyb2cController::class, 'saveNrToDatabase']);
    Route::post('/shopifyb2c/update-listed-live', [Shopifyb2cController::class, 'updateListedLive']);
    Route::post('/listing_audit_shopifyb2c/save-na', [ListingAuditShopifyb2cController::class, 'saveAuditToDatabase']);
    Route::post('/shopify/save-sprice', [Shopifyb2cController::class, 'saveSpriceToDatabase']);
    Route::get('/shopify-pricing-cvr', [Shopifyb2cController::class, 'shopifyPricingCvr']);

    Route::get('/shopify-pricing-increase-decrease', [Shopifyb2cController::class, 'shopifyb2cViewPricingIncreaseDecrease']);

    Route::post('/shopifyb2c-zero/reason-action/update', [Shopifyb2cZeroController::class, 'updateReasonAction']);
    Route::post('/shopifyb2c-low-visibility/reason-action/update', [Shopifyb2cLowVisibilityController::class, 'updateReasonAction']);

    // Macy route
    Route::get('/listing-audit-macy', [ListingAuditMacyController::class, 'listingAuditMacy'])->name('listing.audit.macy');
    Route::get('/listing_audit_macy/view-data', [ListingAuditMacyController::class, 'getViewListingAuditMacyData']);
    Route::post('/macy/save-nr', [MacyController::class, 'saveNrToDatabase']);
    Route::post('/macys/save-sprice', [MacyController::class, 'saveSpriceToDatabase'])->name('macy.save-sprice');
    Route::post('/macy/update-listed-live', [MacyController::class, 'updateListedLive']);
    Route::post('/listing_audit_macy/save-na', [ListingAuditMacyController::class, 'saveAuditToDatabase']);
    Route::post('/macy-zero/reason-action/update', [MacyZeroController::class, 'updateReasonAction']);
    Route::post('/macy-low-visibility/reason-action/update', [MacyLowVisibilityController::class, 'updateReasonAction']);

    // Newegg B2C route
    Route::get('/listing-audit-neweggb2c', [ListingAuditNeweggb2cController::class, 'listingAuditNeweggb2c'])->name('listing.audit.neweggb2c');
    Route::get('/listing_audit_neweggb2c/view-data', [ListingAuditNeweggb2cController::class, 'getViewListingAuditNeweggb2cData']);
    Route::post('/neweggb2c/save-nr', [Neweggb2cController::class, 'saveNrToDatabase']);
    Route::post('/listing_audit_neweggb2c/save-na', [ListingAuditNeweggb2cController::class, 'saveAuditToDatabase']);
    Route::post('/neweggb2c-zero/reason-action/update', [Neweggb2cZeroController::class, 'updateReasonAction']);
    Route::post('/neweggb2c-low-visibility/reason-action/update', [Neweggb2cLowVisibilityController::class, 'updateReasonAction']);

    // Wayfaire route
    Route::get('/listing-audit-wayfair', [ListingAuditWayfairController::class, 'listingAuditWayfair'])->name('listing.audit.wayfair');
    Route::get('/listing_audit_wayfair/view-data', [ListingAuditWayfairController::class, 'getViewListingAuditWayfairData']);
    Route::post('/wayfair/save-nr', [WayfairController::class, 'saveNrToDatabase']);
    Route::post('/wayfair/update-listed-live', [WayfairController::class, 'updateListedLive']);
    Route::post('/listing_audit_wayfair/save-na', [ListingAuditWayfairController::class, 'saveAuditToDatabase']);
    Route::post('/wayfair-zero/reason-action/update', [WayfairZeroController::class, 'updateReasonAction']);
    Route::post('/wayfair-low-visibility/reason-action/update', [WayfairLowVisibilityController::class, 'updateReasonAction']);

    // Reverb route
    Route::get('/listing-audit-reverb', [ListingAuditReverbController::class, 'listingAuditReverb'])->name('listing.audit.reverb');
    Route::get('/listing_audit_reverb/view-data', [ListingAuditReverbController::class, 'getViewListingAuditReverbData']);
    Route::post('/reverb/save-nr', [ReverbController::class, 'saveNrToDatabase']);
    Route::post('/reverb/update-listed-live', [ReverbController::class, 'updateListedLive']);
    Route::post('/listing_audit_reverb/save-na', [ListingAuditReverbController::class, 'saveAuditToDatabase']);
    Route::post('/reverb-zero/reason-action/update', [ReverbZeroController::class, 'updateReasonAction']);
    Route::post('/reverb-low-visibility/reason-action/update', [ReverbLowVisibilityController::class, 'updateReasonAction']);
    Route::post('/reverb-data/import', [ReverbController::class, 'importReverbAnalytics'])->name('reverb.analytics.import');
    Route::get('/reverb-data/export', [ReverbController::class, 'exportReverbAnalytics'])->name('reverb.analytics.export');
    Route::get('/reverb-data/sample', [ReverbController::class, 'downloadSample'])->name('reverb.analytics.sample');

    // Temu route
    Route::get('/listing-audit-temu', [ListingAuditTemuController::class, 'listingAuditTemu'])->name('listing.audit.temu');
    Route::get('/listing_audit_temu/view-data', [ListingAuditTemuController::class, 'getViewListingAuditTemuData']);
    Route::post('/temu/save-nr', [TemuController::class, 'saveNrToDatabase']);
    Route::post('/temu/update-listed-live', [TemuController::class, 'updateListedLive']);
    Route::post('/listing_audit_temu/save-na', [ListingAuditTemuController::class, 'saveAuditToDatabase']);
    Route::post('/temu-zero/reason-action/update', [TemuZeroController::class, 'updateReasonAction']);
    Route::post('/temu-low-visibility/reason-action/update', [TemuLowVisibilityController::class, 'updateReasonAction']);

    // aliExpress route
    Route::get('/zero-aliexpress', [AliexpressZeroController::class, 'aliexpressZeroview'])->name('zero.aliexpress');
    Route::get('/zero_aliexpress/view-data', [AliexpressZeroController::class, 'getViewAliexpressZeroData']);
    Route::post('/zero_aliexpress/reason-action/update-data', [AliexpressZeroController::class, 'updateReasonAction']);
    Route::get('/listing-aliexpress', [ListingAliexpressController::class, 'listingAliexpress'])->name('listing.aliexpress');
    Route::get('/listing_aliexpress/view-data', [ListingAliexpressController::class, 'getViewListingAliexpressData']);
    Route::post('/listing_aliexpress/save-status', [ListingAliexpressController::class, 'saveStatus']);
    Route::post('/listing_aliexpress/import', [ListingAliexpressController::class, 'import'])->name('listing_aliexpress.import');
    Route::get('/listing_aliexpress/export', [ListingAliexpressController::class, 'export'])->name('listing_aliexpress.export');
    Route::get('/listing_aliexpress/sample', [ListingAliexpressController::class, 'downloadSample'])->name('listing_aliexpress.sample');

    Route::get('aliexpressAnalysis', action: [AliexpressController::class, 'overallAliexpress']);
    Route::get('/aliexpress/view-data', [AliexpressController::class, 'getViewAliexpressData']);
    // Route::post('/update-all-aliexpress-skus', [AliexpressController::class, 'updateAllaliexpressSkus']);
    Route::post('/aliexpress/save-nr', [AliexpressController::class, 'saveNrToDatabase']);
    Route::post('/aliexpress/update-listed-live', [AliexpressController::class, 'updateListedLive']);
    Route::post('/aliexpress-analytics/import', [AliexpressController::class, 'importAliexpressAnalytics'])->name('aliexpress.analytics.import');
    Route::get('/aliexpress-analytics/export', [AliexpressController::class, 'exportAliexpressAnalytics'])->name('aliexpress.analytics.export');
    Route::get('/aliexpress-analytics/sample', [AliexpressController::class, 'downloadSample'])->name('aliexpress.analytics.sample');

    // Aliexpress Daily Data routes
    Route::post('/aliexpress/upload-daily-data', [AliexpressController::class, 'uploadDailyDataChunk'])->name('aliexpress.upload.daily.data');
    Route::get('/aliexpress/daily-data', [AliexpressController::class, 'getDailyData'])->name('aliexpress.get.daily.data');
    Route::get('/aliexpress-tabulator', [AliexpressController::class, 'aliexpressTabulatorView'])->name('aliexpress.tabulator.view');
    Route::get('/aliexpress-lmp', [AliexpressController::class, 'aliexpressLmpPage'])->name('aliexpress.lmp');
    Route::post('/aliexpress-lmp/upload', [AliexpressController::class, 'uploadAliexpressLmp'])->name('aliexpress.lmp.upload');
    Route::post('/aliexpress-lmp/save', [AliexpressController::class, 'saveAliexpressLmp'])->name('aliexpress.lmp.save');
    Route::get('/aliexpress-lmp/sample', [AliexpressController::class, 'downloadAliexpressLmpSample'])->name('aliexpress.lmp.sample');
    Route::get('/aliexpress-pricing', [AliexpressController::class, 'aliexpressPricingView'])->name('aliexpress.pricing.view');
    Route::get('/aliexpress/pricing-data', [AliexpressController::class, 'getPricingData'])->name('aliexpress.pricing.data');
    Route::get('/aliexpress/pricing-price-sample', [AliexpressController::class, 'downloadPricingPriceSample'])->name('aliexpress.pricing.price.sample');
    Route::post('/aliexpress/pricing-upload-price', [AliexpressController::class, 'uploadPricingPriceSheet'])->name('aliexpress.pricing.upload.price');
    Route::post('/aliexpress/save-sprice', [AliexpressController::class, 'saveSpriceUpdates'])->name('aliexpress.pricing.save.sprice');
    Route::get('/aliexpress/badge-chart-data', [AliexpressController::class, 'badgeChartData'])->name('aliexpress.badge.chart');
    Route::post('/aliexpress-column-visibility', [AliexpressController::class, 'saveAliexpressColumnVisibility'])->name('aliexpress.save.column.visibility');
    Route::get('/aliexpress-column-visibility', [AliexpressController::class, 'getAliexpressColumnVisibility'])->name('aliexpress.get.column.visibility');

    // ebay variation
    Route::get('/zero-ebayvariation', [EbayVariationZeroController::class, 'ebayVariationZeroview'])->name('zero.ebayvariation');
    Route::get('/zero_ebayvariation/view-data', [EbayVariationZeroController::class, 'getViewEbayVariationZeroData']);
    Route::post('/zero_ebayvariation/reason-action/update-data', [EbayVariationZeroController::class, 'updateReasonAction']);
    Route::get('/listing-ebayvariation', [ListingEbayVariationController::class, 'listingEbayVariation'])->name('listing.ebayvariation');
    Route::get('/listing_ebayvariation/view-data', [ListingEbayVariationController::class, 'getViewListingEbayVariationData']);
    Route::post('/listing_ebayvariation/save-status', [ListingEbayVariationController::class, 'saveStatus']);
    Route::post('/listing_ebayvariation/import', [ListingEbayVariationController::class, 'import'])->name('listing_ebayvariation.import');
    Route::get('/listing_ebayvariation/export', [ListingEbayVariationController::class, 'export'])->name('listing_ebayvariation.export');

    // shopify wholesale
    Route::get('/zero-shopifywholesale', [ShopifyWholesaleZeroController::class, 'shopifyWholesaleZeroview'])->name('zero.shopifywholesale');
    Route::get('/zero_shopifywholesale/view-data', [ShopifyWholesaleZeroController::class, 'getViewShopifyWholesaleZeroData']);
    Route::post('/zero_shopifywholesale/reason-action/update-data', [ShopifyWholesaleZeroController::class, 'updateReasonAction']);
    Route::get('/listing-shopifywholesale', [ListingShopifyWholesaleController::class, 'listingShopifyWholesale'])->name('listing.shopifywholesale');
    Route::get('/listing_shopifywholesale/view-data', [ListingShopifyWholesaleController::class, 'getViewListingShopifyWholesaleData']);
    Route::post('/listing_shopifywholesale/save-status', [ListingShopifyWholesaleController::class, 'saveStatus']);
    Route::post('/listing_shopifywholesale/import', [ListingShopifyWholesaleController::class, 'import'])->name('listing_shopifywholesale.import');
    Route::get('/listing_shopifywholesale/export', [ListingShopifyWholesaleController::class, 'export'])->name('listing_shopifywholesale.export');

    Route::post('/shopifywholesale/save-nr', [ShopifyWholesaleZeroController::class, 'saveNrToDatabase'])->name('zero.shopifywholesale.save-nr');

    // listing Faire
    Route::get('/zero-faire', [FaireZeroController::class, 'faireZeroview'])->name('zero.faire');
    Route::get('/zero_faire/view-data', [FaireZeroController::class, 'getViewFaireZeroData']);
    Route::post('/zero_faire/reason-action/update-data', [FaireZeroController::class, 'updateReasonAction']);
    Route::get('/listing-faire', [ListingFaireController::class, 'listingFaire'])->name('listing.faire');
    Route::get('/listing_faire/view-data', [ListingFaireController::class, 'getViewListingFaireData']);
    Route::post('/listing_faire/save-status', [ListingFaireController::class, 'saveStatus']);
    Route::post('/listing_faire/import', [ListingFaireController::class, 'import'])->name('listing_faire.import');
    Route::get('/listing_faire/export', [ListingFaireController::class, 'export'])->name('listing_faire.export');

    // listing TiktokShop
    Route::get('/zero-tiktokshop', [TiktokShopZeroController::class, 'tiktokShopZeroview'])->name('zero.tiktokshop');
    Route::get('/zero_tiktokshop/view-data', [TiktokShopZeroController::class, 'getViewTiktokShopZeroData']);
    Route::post('/zero_tiktokshop/reason-action/update-data', [TiktokShopZeroController::class, 'updateReasonAction']);
    Route::get('/listing-tiktokshop', [ListingTiktokShopController::class, 'listingTiktokShop'])->name('listing.tiktokshop');
    Route::get('/listing_tiktokshop/view-data', [ListingTiktokShopController::class, 'getViewListingTiktokShopData']);
    Route::post('/listing_tiktokshop/save-status', [ListingTiktokShopController::class, 'saveStatus']);
    Route::post('/listing_tiktokshop/import', [ListingTiktokShopController::class, 'import'])->name('listing_tiktokshop.import');
    Route::get('/listing_tiktokshop/export', [ListingTiktokShopController::class, 'export'])->name('listing_tiktokshop.export');

    Route::get('tiktokAnalysis', action: [TiktokShopController::class, 'overallTiktok']);
    Route::get('/tiktok/view-data', [TiktokShopController::class, 'getViewTiktokData']);
    Route::get('walmartPricingCVR', [TiktokShopController::class, 'tiktokPricingCVR'])->name('tiktok.pricing.cvr');
    Route::post('/update-all-tiktok-skus', [TiktokShopController::class, 'updateAllTiktokSkus']);
    Route::post('/tiktok/save-nr', [TiktokShopController::class, 'saveNrToDatabase']);
    Route::post('/tiktok/update-listed-live', [TiktokShopController::class, 'updateListedLive']);
    Route::post('/tiktok-analytics/import', [TiktokShopController::class, 'importTiktokAnalytics'])->name('tiktok.analytics.import');
    Route::get('/tiktok-analytics/export', [TiktokShopController::class, 'exportTiktokAnalytics'])->name('tiktok.analytics.export');
    Route::get('/tiktok-analytics/sample', [TiktokShopController::class, 'downloadSample'])->name('tiktok.analytics.sample');

    // listing MercariWShip
    Route::get('/zero-mercariwship', [MercariWShipZeroController::class, 'mercariWShipZeroview'])->name('zero.mercariwship');
    Route::get('/zero_mercariwship/view-data', [MercariWShipZeroController::class, 'getViewMercariWShipZeroData']);
    Route::post('/zero_mercariwship/reason-action/update-data', [MercariWShipZeroController::class, 'updateReasonAction']);
    Route::get('/listing-mercariwship', [ListingMercariWShipController::class, 'listingMercariWShip'])->name('listing.mercariwship');
    Route::get('/listing_mercariwship/view-data', [ListingMercariWShipController::class, 'getViewListingMercariWShipData']);
    Route::post('/listing_mercariwship/save-status', [ListingMercariWShipController::class, 'saveStatus']);
    Route::post('/listing_mercariwship/import', [ListingMercariWShipController::class, 'import'])->name('listing_mercariwship.import');
    Route::get('/listing_mercariwship/export', [ListingMercariWShipController::class, 'export'])->name('listing_mercariwship.export');

    // FBMarketplace
    Route::get('/zero-fbmarketplace', [FBMarketplaceZeroController::class, 'fbMarketplaceZeroview'])->name('zero.fbmarketplace');
    Route::get('/zero_fbmarketplace/view-data', [FBMarketplaceZeroController::class, 'getViewFBMarketplaceZeroData']);
    Route::post('/zero_fbmarketplace/reason-action/update-data', [FBMarketplaceZeroController::class, 'updateReasonAction']);
    Route::get('/listing-fbmarketplace', [ListingFBMarketplaceController::class, 'listingFBMarketplace'])->name('listing.fbmarketplace');
    Route::get('/listing_fbmarketplace/view-data', [ListingFBMarketplaceController::class, 'getViewListingFBMarketplaceData']);
    Route::post('/listing_fbmarketplace/save-status', [ListingFBMarketplaceController::class, 'saveStatus']);
    Route::post('/listing_fbmarketplace/import', [ListingFBMarketplaceController::class, 'import'])->name('listing_fbmarketplace.import');
    Route::get('/listing_fbmarketplace/export', [ListingFBMarketplaceController::class, 'export'])->name('listing_fbmarketplace.export');

    // Business5Core
    Route::get('/zero-business5core', [Business5CoreZeroController::class, 'business5CoreZeroview'])->name('zero.business5core');
    Route::get('/zero_business5core/view-data', [Business5CoreZeroController::class, 'getViewBusiness5CoreZeroData']);
    Route::post('/zero_business5core/reason-action/update-data', [Business5CoreZeroController::class, 'updateReasonAction']);
    Route::get('/listing-business5core', [ListingBusiness5CoreController::class, 'listingBusiness5Core'])->name('listing.business5core');
    Route::get('/listing_business5core/view-data', [ListingBusiness5CoreController::class, 'getViewListingBusiness5CoreData']);
    Route::post('/listing_business5core/save-status', [ListingBusiness5CoreController::class, 'saveStatus']);
    Route::post('/listing_business5core/import', [ListingBusiness5CoreController::class, 'import'])->name('listing_business5core.import');
    Route::get('/listing_business5core/export', [ListingBusiness5CoreController::class, 'export'])->name('listing_business5core.export');

    //  Pls
    Route::get('/zero-pls', [PLSZeroController::class, 'plsZeroview'])->name('zero.pls');
    Route::get('/zero_pls/view-data', [PLSZeroController::class, 'getViewPLSZeroData']);
    Route::post('/zero_pls/reason-action/update-data', [PLSZeroController::class, 'updateReasonAction']);
    Route::get('/listing-pls', [ListingPlsController::class, 'listingPls'])->name('listing.pls');
    Route::get('/listing_pls/view-data', [ListingPlsController::class, 'getViewListingPlsData']);
    Route::post('/listing_pls/save-status', [ListingPlsController::class, 'saveStatus']);
    Route::post('/listing_pls/import', [ListingPlsController::class, 'import'])->name('listing_pls.import');
    Route::get('/listing_pls/export', [ListingPlsController::class, 'export'])->name('listing_pls.export');

    //  AutoDS
    Route::get('/zero-autods', [AutoDSZeroController::class, 'autoDSZeroview'])->name('zero.autods');
    Route::get('/zero_autods/view-data', [AutoDSZeroController::class, 'getViewAutoDSZeroData']);
    Route::post('/zero_autods/reason-action/update-data', [AutoDSZeroController::class, 'updateReasonAction']);
    Route::get('/listing-autods', [ListingAutoDSController::class, 'listingAutoDS'])->name('listing.autods');
    Route::get('/listing_autods/view-data', [ListingAutoDSController::class, 'getViewListingAutoDSData']);
    Route::post('/listing_autods/save-status', [ListingAutoDSController::class, 'saveStatus']);
    Route::post('/listing_autods/import', [ListingAutoDSController::class, 'import'])->name('listing_autods.import');
    Route::get('/listing_autods/export', [ListingAutoDSController::class, 'export'])->name('listing_autods.export');

    // MercariWoShip
    Route::get('/zero-mercariwoship', [MercariWoShipZeroController::class, 'mercariWoShipZeroview'])->name('zero.mercariwoship');
    Route::get('/zero_mercariwoship/view-data', [MercariWoShipZeroController::class, 'getViewMercariWoShipZeroData']);
    Route::post('/zero_mercariwoship/reason-action/update-data', [MercariWoShipZeroController::class, 'updateReasonAction']);
    Route::get('/listing-mercariwoship', [ListingMercariWoShipController::class, 'listingMercariWoShip'])->name('listing.mercariwoship');
    Route::get('/listing_mercariwoship/view-data', [ListingMercariWoShipController::class, 'getViewListingMercariWoShipData']);
    Route::post('/listing_mercariwoship/save-status', [ListingMercariWoShipController::class, 'saveStatus']);
    Route::post('/listing_mercariwoship/import', [ListingMercariWoShipController::class, 'import'])->name('listing_mercariwoship.import');
    Route::get('/listing_mercariwoship/export', [ListingMercariWoShipController::class, 'export'])->name('listing_mercariwoship.export');

    // Poshmark
    Route::get('/zero-poshmark', [PoshmarkZeroController::class, 'poshmarkZeroview'])->name('zero.poshmark');
    Route::get('/zero_poshmark/view-data', [PoshmarkZeroController::class, 'getViewPoshmarkZeroData']);
    Route::post('/zero_poshmark/reason-action/update-data', [PoshmarkZeroController::class, 'updateReasonAction']);
    Route::get('/listing-poshmark', [ListingPoshmarkController::class, 'listingPoshmark'])->name('listing.poshmark');
    Route::get('/listing_poshmark/view-data', [ListingPoshmarkController::class, 'getViewListingPoshmarkData']);
    Route::post('/listing_poshmark/save-status', [ListingPoshmarkController::class, 'saveStatus']);
    Route::post('/listing_poshmark/import', [ListingPoshmarkController::class, 'import'])->name('listing_poshmark.import');
    Route::get('/listing_poshmark/export', [ListingPoshmarkController::class, 'export'])->name('listing_poshmark.export');

    // Tiendamia
    Route::get('/zero-tiendamia', [TiendamiaZeroController::class, 'tiendamiaZeroview'])->name('zero.tiendamia');
    Route::get('/zero_tiendamia/view-data', [TiendamiaZeroController::class, 'getViewTiendamiaZeroData']);
    Route::post('/zero_tiendamia/reason-action/update-data', [TiendamiaZeroController::class, 'updateReasonAction']);
    Route::get('/listing-tiendamia', [ListingTiendamiaController::class, 'listingTiendamia'])->name('listing.tiendamia');
    Route::get('/listing_tiendamia/view-data', [ListingTiendamiaController::class, 'getViewListingTiendamiaData']);
    Route::post('/listing_tiendamia/save-status', [ListingTiendamiaController::class, 'saveStatus']);
    Route::post('/listing_tiendamia/import', [ListingTiendamiaController::class, 'import'])->name('listing_tiendamia.import');
    Route::get('/listing_tiendamia/export', [ListingTiendamiaController::class, 'export'])->name('listing_tiendamia.export');

    // Shein
    Route::get('/zero-shein', [SheinZeroController::class, 'sheinZeroview'])->name('zero.shein');
    Route::get('/zero_shein/view-data', [SheinZeroController::class, 'getViewSheinZeroData']);
    Route::post('/zero_shein/reason-action/update-data', [SheinZeroController::class, 'updateReasonAction']);
    Route::get('/listing-shein', [ListingSheinController::class, 'listingShein'])->name('listing.shein');
    Route::get('/listing_shein/view-data', [ListingSheinController::class, 'getViewListingSheinData']);
    Route::post('/listing_shein/save-status', [ListingSheinController::class, 'saveStatus']);
    Route::post('/listing_shein/import', [ListingSheinController::class, 'import'])->name('listing_shein.import');
    Route::get('/listing_shein/export', [ListingSheinController::class, 'export'])->name('listing_shein.export');

    Route::get('sheinAnalysis', action: [SheinController::class, 'overallShein']);
    Route::get('/shein/view-data', [SheinController::class, 'getViewSheinData']);
    Route::post('/update-all-shein-skus', [SheinController::class, 'updateAllSheinSkus']);
    Route::post('/shein/save-nr', [SheinController::class, 'saveNrToDatabase']);
    Route::post('/shein/update-listed-live', [SheinController::class, 'updateListedLive']);
    Route::post('/shein-analytics/import', [SheinController::class, 'importSheinAnalytics'])->name('shein.analytics.import');
    Route::get('/shein-analytics/export', [SheinController::class, 'exportSheinAnalytics'])->name('shein.analytics.export');
    Route::get('/shein-analytics/sample', [SheinController::class, 'downloadSample'])->name('shein.analytics.sample');

    // Shein Daily Data routes
    Route::post('/shein/upload-daily-data', [SheinController::class, 'uploadDailyDataChunk'])->name('shein.upload.daily.data');
    Route::get('/shein/daily-data', [SheinController::class, 'getDailyData'])->name('shein.get.daily.data');
    Route::get('/shein-tabulator', [SheinController::class, 'sheinTabulatorView'])->name('shein.tabulator.view');
    Route::post('/shein-column-visibility', [SheinController::class, 'saveSheinColumnVisibility'])->name('shein.save.column.visibility');
    Route::get('/shein-column-visibility', [SheinController::class, 'getSheinColumnVisibility'])->name('shein.get.column.visibility');

    // Shein Pricing Page
    Route::get('/shein-pricing', [SheinController::class, 'sheinPricingView'])->name('shein.pricing.view');
    Route::get('/shein/pricing-data', [SheinController::class, 'getSheinPricingData'])->name('shein.pricing.data');
    Route::get('/shein/pricing-sample', [SheinController::class, 'downloadSheinPricingSample'])->name('shein.pricing.sample');
    Route::post('/shein/pricing-upload-price', [SheinController::class, 'uploadSheinPriceSheet'])->name('shein.pricing.upload.price');
    Route::post('/shein/save-sprice', [SheinController::class, 'saveSheinSpriceUpdates'])->name('shein.pricing.save.sprice');
    Route::get('/shein/badge-chart-data', [SheinController::class, 'sheinBadgeChartData'])->name('shein.badge.chart');

    // faire
    Route::get('faireAnalysis', action: [FaireController::class, 'overallFaire']);
    Route::get('/faire/view-data', [FaireController::class, 'getViewFaireData']);
    Route::get('fairePricingCVR', [FaireController::class, 'fairePricingCVR'])->name('faire.pricing.cvr');
    Route::post('/update-all-faire-skus', [FaireController::class, 'updateAllFaireSkus']);
    Route::post('/faire/save-nr', [FaireController::class, 'saveNrToDatabase']);
    Route::post('/faire/update-listed-live', [FaireController::class, 'updateListedLive']);
    Route::post('/faire-analytics/import', [FaireController::class, 'importFaireAnalytics'])->name('faire.analytics.import');
    Route::get('/faire-analytics/export', [FaireController::class, 'exportFaireAnalytics'])->name('faire.analytics.export');
    Route::get('/faire-analytics/sample', [FaireController::class, 'downloadSample'])->name('faire.analytics.sample');

    // Faire Daily Data routes
    Route::post('/faire/upload-daily-data', [FaireController::class, 'uploadDailyDataChunk'])->name('faire.upload.daily.data');
    Route::get('/faire/daily-data', [FaireController::class, 'getDailyData'])->name('faire.get.daily.data');
    Route::get('/faire-tabulator', [FaireController::class, 'faireTabulatorView'])->name('faire.tabulator.view');
    Route::get('/faire-pricing', [FaireController::class, 'fairePricingView'])->name('faire.pricing.view');
    Route::get('/faire/pricing-data', [FaireController::class, 'getFairePricingData'])->name('faire.pricing.data');
    Route::get('/faire/pricing-price-sample', [FaireController::class, 'downloadFairePricingPriceSample'])->name('faire.pricing.price.sample');
    Route::post('/faire/pricing-upload-price', [FaireController::class, 'uploadFairePricingPriceSheet'])->name('faire.pricing.upload.price');
    Route::post('/faire/pricing-save-sprice', [FaireController::class, 'saveFaireSpriceUpdates'])->name('faire.pricing.save.sprice');
    Route::get('/faire/badge-chart-data', [FaireController::class, 'faireBadgeChartData'])->name('faire.pricing.badge.chart');
    Route::get('/faire/pricing-column-visibility', [FaireController::class, 'getFairePricingColumnVisibility'])->name('faire.pricing.column.get');
    Route::post('/faire/pricing-column-visibility', [FaireController::class, 'setFairePricingColumnVisibility'])->name('faire.pricing.column.set');

    // pls
    Route::get('plsAnalysis', action: [PlsController::class, 'overallPls']);
    Route::get('/pls/view-data', [PlsController::class, 'getViewPlsData']);
    Route::get('plsPricingCVR', [PlsController::class, 'plsPricingCVR'])->name('pls.pricing.cvr');
    Route::post('/update-all-pls-skus', [PlsController::class, 'updateAllPlsSkus']);
    Route::post('/pls/save-nr', [PlsController::class, 'saveNrToDatabase']);
    Route::post('/pls/update-listed-live', [PlsController::class, 'updateListedLive']);
    Route::post('/pls-analytics/import', [PlsController::class, 'importPlsAnalytics'])->name('pls.analytics.import');
    Route::get('/pls-analytics/export', [PlsController::class, 'exportPlsAnalytics'])->name('pls.analytics.export');
    Route::get('/pls-analytics/sample', [PlsController::class, 'downloadSample'])->name('pls.analytics.sample');

    // Business5Core
    Route::get('business5coreAnalysis', action: [Business5coreController::class, 'overallBusiness5Core']);
    Route::get('/business5core/view-data', [Business5coreController::class, 'getViewBusiness5CoreData']);
    Route::get('business5corePricingCVR', [Business5coreController::class, 'business5corePricingCVR'])->name('business5core.pricing.cvr');
    Route::post('/update-all-business5core-skus', [Business5coreController::class, 'updateAllBusiness5CoreSkus']);
    Route::post('/business5core/save-nr', [Business5coreController::class, 'saveNrToDatabase']);
    Route::post('/business5core/update-listed-live', [Business5coreController::class, 'updateListedLive']);
    Route::post('/business5core-analytics/import', [Business5coreController::class, 'importBusiness5CoreAnalytics'])->name('business5core.analytics.import');
    Route::get('/business5core-analytics/export', [Business5coreController::class, 'exportBusiness5CoreAnalytics'])->name('business5core.analytics.export');
    Route::get('/business5core-analytics/sample', [Business5coreController::class, 'downloadSample'])->name('business5core.analytics.sample');

    // instagram shop
    Route::get('instagramAnalysis', action: [InstagramController::class, 'overallInstagram']);
    Route::get('/instagram/view-data', [InstagramController::class, 'getViewInstagramData']);
    Route::get('instagramPricingCVR', [InstagramController::class, 'instagramPricingCVR'])->name('instagram.pricing.cvr');
    Route::post('/update-all-instagram-skus', [InstagramController::class, 'updateAllInstagramSkus']);
    Route::post('/instagram/save-nr', [InstagramController::class, 'saveNrToDatabase']);
    Route::post('/instagram/update-listed-live', [InstagramController::class, 'updateListedLive']);
    Route::post('/instagram-analytics/import', [InstagramController::class, 'importInstagramAnalytics'])->name('instagram.analytics.import');
    Route::get('/instagram-analytics/export', [InstagramController::class, 'exportInstagramAnalytics'])->name('instagram.analytics.export');
    Route::get('/instagram-analytics/sample', [InstagramController::class, 'downloadSample'])->name('instagram.analytics.sample');

    // tiendamia
    Route::get('tiendamiaAnalysis', action: [TiendamiaController::class, 'overallTiendamia']);
    Route::get('/tiendamia/view-data', [TiendamiaController::class, 'getViewTiendamiaData']);
    Route::get('plsPricingCVR', [TiendamiaController::class, 'tiendamiaPricingCVR'])->name('tiendamia.pricing.cvr');
    Route::post('/update-all-tiendamia-skus', [TiendamiaController::class, 'updateAllTiendamiaSkus']);
    Route::post('/tiendamia/save-nr', [TiendamiaController::class, 'saveNrToDatabase']);
    Route::post('/tiendamia/update-listed-live', [TiendamiaController::class, 'updateListedLive']);
    Route::post('/tiendamia-analytics/import', [TiendamiaController::class, 'importTiendamiaAnalytics'])->name('tiendamia.analytics.import');
    Route::get('/tiendamia-analytics/export', [TiendamiaController::class, 'exportTiendamiaAnalytics'])->name('tiendamia.analytics.export');
    Route::get('/tiendamia-analytics/sample', [TiendamiaController::class, 'downloadSample'])->name('tiendamia.analytics.sample');

    // fbshop
    Route::get('fbshopAnalysis', action: [FbshopController::class, 'overallFbshop']);
    Route::get('/fbshop/view-data', [FbshopController::class, 'getViewFbshopData']);
    Route::get('fbshopPricingCVR', [FbshopController::class, 'fbshopPricingCVR'])->name('fbshop.pricing.cvr');
    Route::post('/update-all-fbshop-skus', [FbshopController::class, 'updateAllFbshopSkus']);
    Route::post('/fbshop/save-nr', [FbshopController::class, 'saveNrToDatabase']);
    Route::post('/fbshop/update-listed-live', [FbshopController::class, 'updateListedLive']);
    Route::post('/fbshop-analytics/import', [FbshopController::class, 'importFbshopAnalytics'])->name('fbshop.analytics.import');
    Route::get('/fbshop-analytics/export', [FbshopController::class, 'exportFbshopAnalytics'])->name('fbshop.analytics.export');
    Route::get('/fbshop-analytics/sample', [FbshopController::class, 'downloadSample'])->name('fbshop.analytics.sample');

    // fb marketplace
    Route::get('fbmarketplaceAnalysis', action: [FbmarketplaceController::class, 'overallFbmarketplace']);
    Route::get('/fbmarketplace/view-data', [FbmarketplaceController::class, 'getViewFbmarketplaceData']);
    Route::get('fbmarketplacePricingCVR', [FbmarketplaceController::class, 'fbmarketplacePricingCVR'])->name('fbmarketplace.pricing.cvr');
    Route::post('/update-all-fbmarketplace-skus', [FbmarketplaceController::class, 'updateAllFbmarketplaceSkus']);
    Route::post('/fbmarketplace/save-nr', [FbmarketplaceController::class, 'saveNrToDatabase']);
    Route::post('/fbmarketplace/update-listed-live', [FbmarketplaceController::class, 'updateListedLive']);
    Route::post('/fbmarketplace-analytics/import', [FbmarketplaceController::class, 'importFbmarketplaceAnalytics'])->name('fbmarketplace.analytics.import');
    Route::get('/fbmarketplace-analytics/export', [FbmarketplaceController::class, 'exportFbmarketplaceAnalytics'])->name('fbmarketplace.analytics.export');
    Route::get('/fbmarketplace-analytics/sample', [FbmarketplaceController::class, 'downloadSample'])->name('fbmarketplace.analytics.sample');

    // mercari w ship
    Route::get('mercariAnalysis', action: [MercariWShipController::class, 'overallMercariWship']);
    Route::get('/mercariwship/view-data', [MercariWShipController::class, 'getViewMercariWshipData']);
    Route::get('mercariWshipPricingCVR', [MercariWShipController::class, 'mercariWshipPricingCVR'])->name('mercariwship.pricing.cvr');
    Route::post('/update-all-mercariwship-skus', [MercariWShipController::class, 'updateAllMercariWshipSkus']);
    Route::post('/mercariwship/save-nr', [MercariWShipController::class, 'saveNrToDatabase']);
    Route::post('/mercariwship/update-listed-live', [MercariWShipController::class, 'updateListedLive']);
    Route::post('/mercariwship-analytics/import', [MercariWShipController::class, 'importMercariWshipAnalytics'])->name('mercariwship.analytics.import');
    Route::get('/mercariwship-analytics/export', [MercariWShipController::class, 'exportMercariWshipAnalytics'])->name('mercariwship.analytics.export');
    Route::get('/mercariwship-analytics/sample', [MercariWShipController::class, 'downloadSample'])->name('mercariwship.analytics.sample');

    // tiktok
    Route::get('tiktokAnalysis', action: [TiktokController::class, 'overallTiktok']);
    Route::get('/tiktok/view-data', [TiktokController::class, 'getViewTiktokData']);
    Route::get('fbshopPricingCVR', [TiktokController::class, 'TiktokPricingCVR'])->name('tiktok.pricing.cvr');
    Route::post('/update-all-tiktok-skus', [TiktokController::class, 'updateAllTiktokSkus']);
    Route::post('/tiktok/save-nr', [TiktokController::class, 'saveNrToDatabase']);
    Route::post('/tiktok/update-listed-live', [TiktokController::class, 'updateListedLive']);
    Route::post('/tiktok-analytics/import', [TiktokController::class, 'importTiktokAnalytics'])->name('tiktok.analytics.import');
    Route::get('/tiktok-analytics/export', [TiktokController::class, 'exportTiktokAnalytics'])->name('tiktok.analytics.export');
    Route::get('/tiktok-analytics/sample', [TiktokController::class, 'downloadSample'])->name('tiktok.analytics.sample');

    // mercari wo ship
    Route::get('mercariwoshipAnalysis', action: [MercariWoShipController::class, 'overallMercariWoShip']);
    Route::get('/mercariwoship/view-data', [MercariWoShipController::class, 'getViewMercariWoShipData']);
    Route::get('mercariwoshipPricingCVR', [MercariWoShipController::class, 'MercariWoShipPricingCVR'])->name('mercariwoship.pricing.cvr');
    Route::post('/update-all-mercariwoship-skus', [MercariWoShipController::class, 'updateAllMercariWoShipSkus']);
    Route::post('/mercariwoship/save-nr', [MercariWoShipController::class, 'saveNrToDatabase']);
    Route::post('/mercariwoship/update-listed-live', [MercariWoShipController::class, 'updateListedLive']);
    Route::post('/mercariwoship-analytics/import', [MercariWoShipController::class, 'importMercariWoShipAnalytics'])->name('mercariwoship.analytics.import');
    Route::get('/mercariwoship-analytics/export', [MercariWoShipController::class, 'exportMercariWoShipAnalytics'])->name('mercariwoship.analytics.export');
    Route::get('/mercariwoship-analytics/sample', [MercariWoShipController::class, 'downloadSample'])->name('mercariwoship.analytics.sample');

    //  Spocket
    Route::get('/zero-spocket', [SpocketZeroController::class, 'spocketZeroview'])->name('zero.spocket');
    Route::get('/zero_spocket/view-data', [SpocketZeroController::class, 'getViewSpocketZeroData']);
    Route::post('/zero_spocket/reason-action/update-data', [SpocketZeroController::class, 'updateReasonAction']);
    Route::get('/listing-spocket', [ListingSpocketController::class, 'listingSpocket'])->name('listing.spocket');
    Route::get('/listing_spocket/view-data', [ListingSpocketController::class, 'getViewListingSpocketData']);
    Route::post('/listing_spocket/save-status', [ListingSpocketController::class, 'saveStatus']);

    // Zendrop
    Route::get('/zero-zendrop', [ZendropZeroController::class, 'zendropZeroview'])->name('zero.zendrop');
    Route::get('/zero_zendrop/view-data', [ZendropZeroController::class, 'getViewZendropZeroData']);
    Route::post('/zero_zendrop/reason-action/update-data', [ZendropZeroController::class, 'updateReasonAction']);
    Route::get('/listing-zendrop', [ListingZendropController::class, 'listingZendrop'])->name('listing.zendrop');
    Route::get('/listing_zendrop/view-data', [ListingZendropController::class, 'getViewListingZendropData']);
    Route::post('/listing_zendrop/save-status', [ListingZendropController::class, 'saveStatus']);

    // Syncee
    Route::get('/zero-syncee', [SynceeZeroController::class, 'synceeZeroview'])->name('zero.syncee');
    Route::get('/zero_syncee/view-data', [SynceeZeroController::class, 'getViewSynceeZeroData']);
    Route::post('/zero_syncee/reason-action/update-data', [SynceeZeroController::class, 'updateReasonAction']);
    Route::get('/listing-syncee', [ListingSynceeController::class, 'listingSyncee'])->name('listing.syncee');
    Route::get('/listing_syncee/view-data', [ListingSynceeController::class, 'getViewListingSynceeData']);
    Route::post('/listing_syncee/save-status', [ListingSynceeController::class, 'saveStatus']);
    Route::post('/listing_syncee/import', [ListingSynceeController::class, 'import'])->name('listing_syncee.import');
    Route::get('/listing_syncee/export', [ListingSynceeController::class, 'export'])->name('listing_syncee.export');

    // Offerup
    Route::get('/zero-offerup', [OfferupZeroController::class, 'offerupZeroview'])->name('zero.offerup');
    Route::get('/zero_offerup/view-data', [OfferupZeroController::class, 'getViewOfferupZeroData']);
    Route::post('/zero_offerup/reason-action/update-data', [OfferupZeroController::class, 'updateReasonAction']);
    Route::get('/listing-offerup', [ListingOfferupController::class, 'listingOfferup'])->name('listing.offerup');
    Route::get('/listing_offerup/view-data', [ListingOfferupController::class, 'getViewListingOfferupData']);
    Route::post('/listing_offerup/save-status', [ListingOfferupController::class, 'saveStatus']);

    // listing Newegg B2B
    Route::get('/listing-neweggb2b', [ListingNeweggB2BController::class, 'listingNeweggB2B'])->name('listing.neweggb2b');
    Route::get('/listing_neweggb2b/view-data', [ListingNeweggB2BController::class, 'getViewListingNeweggB2BData']);
    Route::post('/listing_neweggb2b/save-status', [ListingNeweggB2BController::class, 'saveStatus']);

    // Appscenic
    Route::get('/zero-appscenic', [AppscenicZeroController::class, 'appscenicZeroview'])->name('zero.appscenic');
    Route::get('/zero_appscenic/view-data', [AppscenicZeroController::class, 'getViewAppscenicZeroData']);
    Route::post('/zero_appscenic/reason-action/update-data', [AppscenicZeroController::class, 'updateReasonAction']);
    Route::get('/listing-appscenic', [ListingAppscenicController::class, 'listingAppscenic'])->name('listing.appscenic');
    Route::get('/listing_appscenic/view-data', [ListingAppscenicController::class, 'getViewListingAppscenicData']);
    Route::post('/listing_appscenic/save-status', [ListingAppscenicController::class, 'saveStatus']);

    // listing fbshop
    Route::get('/zero-fbshop', [FBShopZeroController::class, 'fbShopZeroview'])->name('zero.fbshop');
    Route::get('/zero_fbshop/view-data', [FBShopZeroController::class, 'getViewFBShopZeroData']);
    Route::post('/zero_fbshop/reason-action/update-data', [FBShopZeroController::class, 'updateReasonAction']);
    Route::get('/listing-fbshop', [ListingFBShopController::class, 'listingFBShop'])->name('listing.fbshop');
    Route::get('/listing_fbshop/view-data', [ListingFBShopController::class, 'getViewListingFBShopData']);
    Route::post('/listing_fbshop/save-status', [ListingFBShopController::class, 'saveStatus']);
    Route::post('/listing_fbshop/import', [ListingFBShopController::class, 'import'])->name('listing_fbshop.import');
    Route::get('/listing_fbshop/export', [ListingFBShopController::class, 'export'])->name('listing_fbshop.export');

    // Instagram Shop
    Route::get('/zero-instagramshop', [InstagramShopZeroController::class, 'instagramShopZeroview'])->name('zero.instagramshop');
    Route::get('/zero_instagramshop/view-data', [InstagramShopZeroController::class, 'getViewInstagramShopZeroData']);
    Route::post('/zero_instagramshop/reason-action/update-data', [InstagramShopZeroController::class, 'updateReasonAction']);
    Route::get('/listing-instagramshop', [ListingInstagramShopController::class, 'listingInstagramShop'])->name('listing.instagramshop');
    Route::get('/listing_instagramshop/view-data', [ListingInstagramShopController::class, 'getViewListingInstagramShopData']);
    Route::post('/listing_instagramshop/save-status', [ListingInstagramShopController::class, 'saveStatus']);
    Route::post('/listing_instagramshop/import', [ListingInstagramShopController::class, 'import'])->name('listing_instagramshop.import');
    Route::get('/listing_instagramshop/export', [ListingInstagramShopController::class, 'export'])->name('listing_instagramshop.export');

    // listing Yamibuy
    Route::get('/zero-yamibuy', [YamibuyZeroController::class, 'yamibuyZeroview'])->name('zero.yamibuy');
    Route::get('/zero_yamibuy/view-data', [YamibuyZeroController::class, 'getViewYamibuyZeroData']);
    Route::post('/zero_yamibuy/reason-action/update-data', [YamibuyZeroController::class, 'updateReasonAction']);
    Route::get('/listing-yamibuy', [ListingYamibuyController::class, 'listingYamibuy'])->name('listing.yamibuy');
    Route::get('/listing_yamibuy/view-data', [ListingYamibuyController::class, 'getViewListingYamibuyData']);
    Route::post('/listing_yamibuy/save-status', [ListingYamibuyController::class, 'saveStatus']);
    Route::post('/listing_yamibuy/import', [ListingYamibuyController::class, 'import'])->name('listing_yamibuy.import');
    Route::get('/listing_yamibuy/export', [ListingYamibuyController::class, 'export'])->name('listing_yamibuy.export');

    // listing DHGate
    Route::get('/zero-dhgate', [DHGateZeroController::class, 'dhgateZeroview'])->name('zero.dhgate');
    Route::get('/zero_dhgate/view-data', [DHGateZeroController::class, 'getViewDHGateZeroData']);
    Route::post('/zero_dhgate/reason-action/update-data', [DHGateZeroController::class, 'updateReasonAction']);
    Route::get('/listing-dhgate', [ListingDHGateController::class, 'listingDHGate'])->name('listing.dhgate');
    Route::get('/listing_dhgate/view-data', [ListingDHGateController::class, 'getViewListingDHGateData']);
    Route::post('/listing_dhgate/save-status', [ListingDHGateController::class, 'saveStatus']);
    Route::post('/listing_dhgate/import', [ListingDHGateController::class, 'import'])->name('listing_dhgate.import');
    Route::get('/listing_dhgate/export', [ListingDHGateController::class, 'export'])->name('listing_dhgate.export');

    // listing Walmart Canada
    Route::get('/zero-swgearexchange', [SWGearExchangeZeroController::class, 'swGearExchangeZeroview'])->name('zero.swgearexchange');
    Route::get('/zero_swgearexchange/view-data', [SWGearExchangeZeroController::class, 'getViewSWGearExchangeZeroData']);
    Route::post('/zero_swgearexchange/reason-action/update-data', [SWGearExchangeZeroController::class, 'updateReasonAction']);
    Route::get('/listing-swgearexchange', [ListingSWGearExchangeController::class, 'listingSWGearExchange'])->name('listing.swgearexchange');
    Route::get('/listing_swgearexchange/view-data', [ListingSWGearExchangeController::class, 'getViewListingSWGearExchangeData']);
    Route::post('/listing_swgearexchange/save-status', [ListingSWGearExchangeController::class, 'saveStatus']);
    Route::post('/listing_swgearexchange/import', [ListingSWGearExchangeController::class, 'import'])->name('listing_swgearexchange.import');
    Route::get('/listing_swgearexchange/export', [ListingSWGearExchangeController::class, 'export'])->name('listing_swgearexchange.export');

    // Permissions
    // Route::get('/permissions', [NewPermissionController::class, 'index'])->name('permissions');
    // Route::post('/permissions/store', [NewPermissionController::class, 'store'])->name('permissions.store');

    // listing Bestbuy USA
    Route::get('/zero-bestbuyusa', [BestbuyUSAZeroController::class, 'bestbuyUSAZeroview'])->name('zero.bestbuyusa');
    Route::get('/bestbuyusa-analytics', [BestbuyUSAZeroController::class, 'bestbuyUSAZeroAnalytics'])->name('zero.bestbuyusa.analytics');
    Route::get('/zero_bestbuyusa/view-data', [BestbuyUSAZeroController::class, 'getViewBestbuyUSAZeroData']);
    Route::post('/zero_bestbuyusa/update-listed-live', [BestbuyUSAZeroController::class, 'updateListedLive']);
    Route::post('/zero_bestbuyusa/reason-action/update-data', [BestbuyUSAZeroController::class, 'updateReasonAction']);
    Route::get('/listing-bestbuyusa', [ListingBestbuyUSAController::class, 'listingBestbuyUSA'])->name('listing.bestbuyusa');
    Route::get('/listing_bestbuyusa/view-data', [ListingBestbuyUSAController::class, 'getViewListingBestbuyUSAData']);
    Route::post('/listing_bestbuyusa/save-status', [ListingBestbuyUSAController::class, 'saveStatus']);
    Route::post('/listing_bestbuyusa/import', [ListingBestbuyUSAController::class, 'import'])->name('listing_bestbuyusa.import');
    Route::get('/listing_bestbuyusa/export', [ListingBestbuyUSAController::class, 'export'])->name('listing_bestbuyusa.export');
    Route::post('/bestbuyusa-analytics/import', [BestbuyUSAZeroController::class, 'importBestBuyUsaAnalytics'])->name('bestbuyusa.analytics.import');
    Route::get('/bestbuyusa-analytics/export', [BestbuyUSAZeroController::class, 'exportBestBuyUsaAnalytics'])->name('bestbuyusa.analytics.export');
    Route::get('/bestbuyusa-analytics/sample', [BestbuyUSAZeroController::class, 'downloadSample'])->name('bestbuyusa.analytics.sample');

    // MM video posted route
    Route::controller(VideoPostedController::class)->group(function () {
        Route::get('/markrting-master/video-posted', 'videoPostedView')->name('mm.video.posted');
        Route::get('/videoPosted/view-data', 'getViewVideoPostedData');
        Route::post('/video-posted/save', 'storeOrUpdate')->name('video_posted_value.store_or_update');

        Route::get('/marketing-master/product-video-upload', 'productVideoUploadView')->name('mm.product.video.upload');
        Route::get('/product-video-upload/view-data', 'getProductVideoUploadData');
        Route::post('/product-video-upload/save', 'productVideoUploadUpdate')->name('mm.product.video.upload.save');

        Route::get('/marketing-master/assembly-video-req', 'assemblyVideoReq')->name('mm.assembly.video.posted');
        Route::get('/assembly-video-req/view-data', 'getAssemblyVideoPostedData');
        Route::post('/assembly-video-req/save', 'asseblyStoreOrUpdate')->name('assembly_video_req.store_or_update');

        Route::get('/marketing-master/assembly-video-upload', 'assemblyVideoUploadView')->name('mm.assembly.video.upload');
        Route::get('/assembly-video-upload/view-data', 'getAssemblyVideoUploadData');
        Route::post('/assembly-video-upload/save', 'assemblyVideoUploadUpdate')->name('assembly_video_upload.store_or_update');

        Route::get('/marketing-master/3d-video-req', 'threeDVideoReq')->name('mm.3d.video.posted');
        Route::get('/3d-video-req/view-data', 'getThreeDVideoPostedData');
        Route::post('/3d-video-req/save', 'threeDStoreOrUpdate')->name('3d_video_req.store_or_update');

        Route::get('/marketing-master/3d-video-upload', 'threeDVideoUploadView')->name('mm.3d.video.upload');
        Route::get('/3d-video-upload/view-data', 'getThreeDVideoUploadData');
        Route::post('/3d-video-upload/save', 'threeDVideoUploadUpdate')->name('3d_video_upload.store_or_update');

        Route::get('/marketing-master/360-video-req', 'three60VideoReq')->name('mm.360.video.posted');
        Route::get('/360-video-req/view-data', 'getThree60VideoPostedData');
        Route::post('/360-video-req/save', 'three60StoreOrUpdate')->name('360_video_req.store_or_update');

        Route::get('/marketing-master/360-video-upload', 'three60VideoUploadView')->name('mm.360.video.upload');
        Route::get('/360-video-upload/view-data', 'getThree60VideoUploadData');
        Route::post('/360-video-upload/save', 'three60VideoUploadUpdate')->name('360_video_upload.store_or_update');

        Route::get('/marketing-master/benefits-video-req', 'benefitsVideoReq')->name('mm.benefits.video.posted');
        Route::get('/benefits-video-req/view-data', 'getBenefitsVideoPostedData');
        Route::post('/benefits-video-req/save', 'benefitsStoreOrUpdate')->name('benefits_video_req.store_or_update');

        Route::get('/marketing-master/benefits-video-upload', 'benefitsVideoUploadView')->name('mm.benefits.video.upload');
        Route::get('/benefits-video-upload/view-data', 'getBenefitsVideoUploadData');
        Route::post('/benefits-video-upload/save', 'benefitsVideoUploadUpdate')->name('benefits_video_upload.store_or_update');

        Route::get('/marketing-master/diy-video-req', 'diyVideoReq')->name('mm.diy.video.posted');
        Route::get('/diy-video-req/view-data', 'getDiyVideoPostedData');
        Route::post('/diy-video-req/save', 'diyStoreOrUpdate')->name('diy_video_req.store_or_update');

        Route::get('/marketing-master/diy-video-upload', 'diyVideoUploadView')->name('mm.diy.video.upload');
        Route::get('/diy-video-upload/view-data', 'getDiyVideoUploadData');
        Route::post('/diy-video-upload/save', 'diyVideoUploadUpdate')->name('diy_video_upload.store_or_update');

        Route::get('/marketing-master/shoppable-video-req', 'shoppableVideoReq')->name('mm.shoppable.video.posted');
        Route::get('/shoppable-video-req/view-data', 'getShoppableVideoPostedData');
        Route::post('/shoppable-video-req/save', 'shoppableStoreOrUpdate')->name('shoppable_video_req.store_or_update');

        Route::post('/video-import', 'import')->name('video.import');
    });

    Route::controller(ClaimReimbursementController::class)->group(function () {
        Route::get('/claim-reimbursement', 'index')->name('claim.reimbursement');
        Route::get('/claim-reimbursement/view-data', 'getViewClaimReimbursementData');
        Route::post('/claim-reimbursement/save', 'saveClaimReimbursement')->name('claim.reimbursement.save');
    });

    Route::controller(VideoAdsMasterController::class)->group(function () {
        Route::get('/tiktok-video-ad', 'tiktokIndex')->name('tiktok.ads.master');
        Route::get('/tikotok-video-ads', 'getTikTokVideoAdsData');
        Route::post('/tiktok-video-ads/save', 'saveTiktokVideoAds')->name('tiktok_video_ads.save');

        Route::get('/facebook-video-ad', 'facebookVideoAdView')->name('facebook.ads.master');
        Route::get('/facebook-video-ads', 'getFacebookVideoAdsData');
        Route::post('/facebook-video-ads/save', 'saveFacebookVideoAds')->name('facebook_video_ads.save');

        // Facebook Video Ads Groups and Categories (using Group Master's groups/categories)
        Route::post('/facebook-video-ads-update-field', 'updateFacebookVideoAdField')->name('facebook.video.ads.update.field');
        Route::post('/facebook-video-ads-upload-excel', 'uploadFacebookVideoAdsExcel')->name('facebook.video.ads.upload.excel');
        Route::get('/facebook-video-ads-download-excel', 'downloadFacebookVideoAdsExcel')->name('facebook.video.ads.download.excel');

        Route::get('/facebook-feed-ad', 'facebookFeedAdView')->name('facebook.feed.ads.master');
        Route::get('/facebook-feed-ads', 'getFacebookFeedAdsData');
        Route::post('/facebook-feed-ads/save', 'saveFacebookFeedAds')->name('facebook_feed_ads.save');

        Route::get('/facebook-reel-ad', 'facebookReelAdView')->name('facebook.reel.ads.master');
        Route::get('/facebook-reel-ads', 'getFacebookReelAdsData');
        Route::post('/facebook-reel-ads/save', 'saveFacebookReelAds')->name('facebook_reel_ads.save');

        Route::get('/instagram-video-ad', 'InstagramVideoAdView')->name('instagram.ads.master');
        Route::get('/instagram-video-ads', 'getInstagramVideoAdsData');
        Route::post('/instagram-video-ads/save', 'saveInstagramVideoAds')->name('instagram_video_ads.save');

        Route::get('/instagram-feed-ad', 'instagramFeedAdView')->name('instagram.feed.ads.master');

        Route::get('/instagram-feed-ads', 'getInstagramFeedAdsData');
        Route::post('/instagram-feed-ads/save', 'saveInstagramFeedAds')->name('instagram_feed_ads.save');

        Route::get('/instagram-reel-ad', 'instagramReelAdView')->name('instagram.reel.ads.master');
        Route::get('/instagram-reel-ads', 'getInstagramReelAdsData');
        Route::post('/instagram-reel-ads/save', 'saveInstagramReelAds')->name('instagram_reel_ads.save');

        Route::get('/youtube-video-ad', 'youtubeVideoAdView')->name('youtube.ads.master');
        Route::get('/youtube-video-ads', 'getYoutubeVideoAdsData');
        Route::post('/youtube-video-ads/save', 'saveYoutubeVideoAds')->name('youtube_video_ads.save');

        Route::get('/youtube-shorts-ad', 'youtubeShortsAdView')->name('youtube.shorts.ads.master');
        Route::get('/youtube-shorts-ads', 'getYoutubeShortsAdsData');
        Route::post('/youtube-shorts-ads/save', 'saveYoutubeShortsAds')->name('youtube_shorts_ads.save');

        Route::get('/traffic/dropship', 'getTrafficDropship')->name('traffic.dropship');
        Route::get('/traffic/caraudio', 'getTrafficCaraudio')->name('traffic.caraudio');
        Route::get('/traffic/musicinst', 'getTrafficMusicInst')->name('traffic.musicinst');
        Route::get('/traffic/repaire', 'getTrafficRepaire')->name('traffic.repaire');
        Route::get('/traffic/musicschool', 'getTrafficMusicSchool')->name('traffic.musicschool');
    });

    Route::controller(ShoppableVideoController::class)->group(function () {
        Route::get('/shoppable-video/one-ration', 'oneRation')->name('one.ration');
        Route::get('/one-ration-video/view-data', 'getOneRatioVideoData');
        Route::post('/one-ration-video/save', 'saveOneRationVideo');

        Route::get('/shoppable-video/four-ration', 'fourRation')->name('four.ration');
        Route::get('/four-ration-video/view-data', 'getFourRatioVideoData');
        Route::post('/four-ration-video/save', 'saveFourRationVideo');

        Route::get('/shoppable-video/nine-ration', 'nineRation')->name('nine.ration');
        Route::get('/nine-ration-video/view-data', 'getNineRatioVideoData');
        Route::post('/nine-ration-video/save', 'saveNineRationVideo');
        Route::post('/nine-ration-video/import', 'importNineRationVideo');
        Route::get('/nine-ration-video/export', 'exportNineRationVideo');

        Route::get('/shoppable-video/sixteen-ration', 'sixteenRation')->name('sixteen.ration');
        Route::get('/sixteen-ration-video/view-data', 'getSixteenRatioVideoData');
        Route::post('/sixteen-ration-video/save', 'savesixteenRationVideo');
        Route::post('/sixteen-ration-video/import', 'importSixteenRationVideo');
        Route::get('/sixteen-ration-video/export', 'exportSixteenRationVideo');
    });

    Route::controller(CampaignImportController::class)->group(function () {
        Route::get('campaign', 'index')->name('campaign');
        Route::get('/campaign/under-utilised/', 'budgetUnderUtilised')->name('campaign.under');
        Route::get('/campaign/over-utilised/', 'budgetOverUtilised')->name('campaign.over');
        Route::post('/upload-csv', 'upload');
        Route::post('campaigns/update-note', 'updateField')->name('campaigns.update-note');
        Route::post('/campaigns/data', 'getCampaigns')->name('campaigns.data');
        Route::get('/campaigns/list', 'getCampaignsData')->name('campaigns.list');
        // Route::post('/campaign/save', 'storeOrUpdateCampaign')->name('campaign.save');
    });

    Route::controller(AmazonSpBudgetController::class)->group(function () {
        Route::get('/amazon-sp/amz-utilized-bgt-kw', 'amzUtilizedBgtKw')->name('amazon-sp.amz-utilized-bgt-kw');
        Route::get('/amazon-sp/get-amz-utilized-bgt-kw', 'getAmzUtilizedBgtKw');
        Route::get('/amazon-sp/get-utilization-chart-data', 'getAmazonUtilizationChartData');
        Route::get('/amazon-sp/get-utilization-counts', 'getAmazonUtilizationCounts');
        Route::post('/update-amazon-sp-bid-price', 'updateAmazonSpBidPrice');
        Route::put('/update-keywords-bid-price', 'updateCampaignKeywordsBid');

        // Consolidated Amazon Utilized pages (KW, PT, HL)
        Route::get('/amazon/utilized/kw', 'amazonUtilizedView')->name('amazon.utilized.kw');
        Route::get('/amazon/utilized/kw/ads/data', 'getAmazonUtilizedKwAdsData');
        Route::get('/amazon/utilized/kw/parent-datsheet-info', 'getAmazonUtilizedParentDatsheetInfo');
        Route::get('/amazon/utilized/pt', 'amazonUtilizedPtView')->name('amazon.utilized.pt');
        Route::get('/amazon/utilized/pt/ads/data', 'getAmazonUtilizedPtAdsData');
        Route::get('/amazon/get-utilization-counts', 'getAmazonUtilizationCounts');
        Route::get('/amazon/get-utilization-chart-data', 'getAmazonUtilizationChartData');
        Route::get('/amazon/utilized/chart/filter', 'filterAmazonUtilizedChart')->name('amazon.utilized.chart.filter');

        // ACOS Action History Routes
        Route::post('/amazon/save-acos-action-history', 'saveAcosActionHistory');
        Route::get('/amazon/get-acos-action-history', 'getAcosActionHistory');

        Route::get('/amazon-sp/amz-utilized-bgt-pt', 'amzUtilizedBgtPt')->name('amazon-sp.amz-utilized-bgt-pt');
        Route::get('/amazon-sp/get-amz-utilized-bgt-pt', 'getAmzUtilizedBgtPt');
        Route::put('/update-amazon-sp-targets-bid-price', 'updateCampaignTargetsBid');
        Route::post('/update-amazon-nr-nrl-fba', 'updateNrNRLFba');

        // SBID M and Approve routes
        Route::post('/save-amazon-sbid-m', 'saveAmazonSbidM');
        Route::post('/approve-amazon-sbid', 'approveAmazonSbid');
    });

    Route::get('/amazon/utilized/kw/sheet', [AmazonUtilizedKwSheetController::class, 'index'])->name('amazon.utilized.kw.sheet');

    Route::controller(AmazonSbBudgetController::class)->group(function () {
        Route::get('/amazon-sb/amz-utilized-bgt-hl', 'amzUtilizedBgtHl')->name('amazon-sb.amz-utilized-bgt-hl');
        Route::get('/amazon-sb/get-amz-utilized-bgt-hl', 'getAmzUtilizedBgtHl');
        Route::post('/update-amazon-sb-bid-price', 'updateAmazonSbBidPrice');
        Route::put('/amazon-sb/update-keywords-bid-price', 'updateCampaignKeywordsBid');

        // Consolidated Amazon HL Utilized page
        Route::get('/amazon/utilized/hl', 'amazonUtilizedHlView')->name('amazon.utilized.hl');
        Route::get('/amazon/utilized/hl/ads/data', 'getAmazonUtilizedHlAdsData');

        Route::get('/amazon-sb/amz-under-utilized-bgt-hl', 'amzUnderUtilizedBgtHl')->name('amazon-sb.amz-under-utilized-bgt-hl');
        Route::get('/amazon-sb/get-amz-under-utilized-bgt-hl', 'getAmzUnderUtilizedBgtHl');
        Route::post('/update-amazon-under-sb-bid-price', 'updateUnderAmazonSbBidPrice');
    });

    Route::controller(AmzUnderUtilizedBgtController::class)->group(function () {
        Route::get('/amazon-sp/amz-under-utilized-bgt-kw', 'amzUnderUtilizedBgtKw')->name('amazon-sp.amz-under-utilized-bgt-kw');
        Route::get('/amazon-sp/get-amz-under-utilized-bgt-kw', 'getAmzUnderUtilizedBgtKw');
        Route::post('/update-amazon-under-utilized-sp-bid-price', 'updateAmazonSpBidPrice');
        Route::put('/update-keywords-bid-price', 'updateCampaignKeywordsBid');
        Route::put('/update-amz-under-targets-bid-price', 'updateCampaignTargetsBid');

        Route::get('/amazon-sp/amz-under-utilized-bgt-pt', 'amzUnderUtilizedBgtPt')->name('amazon-sp.amz-under-utilized-bgt-pt');
        Route::get('/amazon-sp/get-amz-under-utilized-bgt-pt', 'getAmzUnderUtilizedBgtPt');
    });

    Route::controller(AmzCorrectlyUtilizedController::class)->group(function () {
        Route::get('/amazon/correctly-utilized-bgt-kw', 'correctlyUtilizedKw')->name('amazon.amz-correctly-utilized-bgt-kw');
        Route::get('/get-amz-correctly-utilized-bgt-kw', 'getAmzCorrectlyUtilizedBgtKw');

        Route::get('/amazon/correctly-utilized-bgt-hl', 'correctlyUtilizedHl')->name('amazon.amz-correctly-utilized-bgt-hl');
        Route::get('/get-amz-correctly-utilized-bgt-hl', 'getAmzCorrectlyUtilizedBgtHl');

        Route::get('/amazon/correctly-utilized-bgt-pt', 'correctlyUtilizedPt')->name('amazon.amz-correctly-utilized-bgt-pt');
        Route::get('/get-amz-correctly-utilized-bgt-pt', 'getAmzCorrectlyUtilizedBgtPt');
    });

    Route::controller(AmazonAdRunningController::class)->group(function () {
        Route::get('/amazon/ad-running/list', 'index')->name('amazon.ad-running.list');
        Route::get('/amazon/ad-running/data', 'getAmazonAdRunningData');
        Route::get('/adv-amazon/ad-running/save-data', 'getAmazonAdRunningSaveAdvMasterData')->name('adv-amazon.ad-running.save-data');
    });

    Route::controller(AmazonPinkDilAdController::class)->group(function () {
        Route::get('/amazon/pink-dil/kw/ads', 'amazonPinkDilKwAds')->name('amazon.pink.dil.kw.ads');
        Route::get('/amazon/pink-dil/kw/ads/data', 'getAmazonPinkDilKwAdsData');

        Route::get('/amazon/pink-dil/pt/ads', 'amazonPinkDilPtAds')->name('amazon.pink.dil.pt.ads');
        Route::get('/amazon/pink-dil/pt/ads/data', 'getAmazonPinkDilPtAdsData');
    });

    Route::get('/ai-title-manager', [\App\Http\Controllers\ProductMaster\ProductMasterController::class, 'aiTitleManager'])->name('ai.title.manager');
    Route::get('/api/ai-title-data', [\App\Http\Controllers\ProductMaster\ProductMasterController::class, 'getAiTitleData'])->name('ai.title.data');
    Route::post('/api/ai-title-generate', [\App\Http\Controllers\ProductMaster\ProductMasterController::class, 'generateAiTitle'])->name('ai.title.generate');
    Route::post('/api/ai-title-push', [\App\Http\Controllers\ProductMaster\ProductMasterController::class, 'pushTitleToMarketplace'])->name('ai.title.push');

    // FaceBook Adds Manager
    Route::controller(FacebookAddsManagerController::class)->group(function () {
        Route::get('/meta-all-ads-control', 'metaAllAds')->name('meta.all.ads');
        Route::get('/meta-all-ads-control/data', 'metaAllAdsData')->name('meta.all.ads.data');
        Route::post('/meta-all-ads-control/sync-meta-api', 'syncMetaAdsFromApi')->name('meta.ads.sync');

        // Group management routes
        Route::get('/meta-ads/group/list', 'getMetaAdGroups')->name('meta.ads.group.list');
        Route::post('/meta-ads/group/store', 'storeGroup')->name('meta.ads.group.store');
        Route::delete('/meta-ads/group/delete', 'deleteMetaAdGroup')->name('meta.ads.group.delete');

        // Import/Export routes
        Route::post('/meta-ads/import', 'importAds')->name('meta.ads.import');
        Route::post('/meta-ads/export', 'exportAds')->name('meta.ads.export');

        // Facebook AD Type specific routes
        Route::get('/meta-ads/facebook/single-image', 'metaFacebookSingleImage')->name('meta.ads.facebook.single.image');
        Route::get('/meta-ads/facebook/single-image/data', 'metaFacebookSingleImageData')->name('meta.ads.facebook.single.image.data');
        Route::get('/meta-ads/facebook/single-video', 'metaFacebookSingleVideo')->name('meta.ads.facebook.single.video');
        Route::get('/meta-ads/facebook/single-video/data', 'metaFacebookSingleVideoData')->name('meta.ads.facebook.single.video.data');
        Route::get('/meta-ads/facebook/carousal', 'metaFacebookCarousal')->name('meta.ads.facebook.carousal');
        Route::get('/meta-ads/facebook/carousal/data', 'metaFacebookCarousalData')->name('meta.ads.facebook.carousal.data');
        Route::get('/meta-ads/facebook/existing-post', 'metaFacebookExistingPost')->name('meta.ads.facebook.existing.post');
        Route::get('/meta-ads/facebook/existing-post/data', 'metaFacebookExistingPostData')->name('meta.ads.facebook.existing.post.data');
        Route::get('/meta-ads/facebook/catalogue-ad', 'metaFacebookCatalogueAd')->name('meta.ads.facebook.catalogue');
        Route::get('/meta-ads/facebook/catalogue-ad/data', 'metaFacebookCatalogueAdData')->name('meta.ads.facebook.catalogue.data');

        // Instagram AD Type specific routes
        Route::get('/meta-ads/instagram/single-image', 'metaInstagramSingleImage')->name('meta.ads.instagram.single.image');
        Route::get('/meta-ads/instagram/single-image/data', 'metaInstagramSingleImageData')->name('meta.ads.instagram.single.image.data');
        Route::get('/meta-ads/instagram/single-video', 'metaInstagramSingleVideo')->name('meta.ads.instagram.single.video');
        Route::get('/meta-ads/instagram/single-video/data', 'metaInstagramSingleVideoData')->name('meta.ads.instagram.single.video.data');
        Route::get('/meta-ads/instagram/carousal', 'metaInstagramCarousal')->name('meta.ads.instagram.carousal');
        Route::get('/meta-ads/instagram/carousal/data', 'metaInstagramCarousalData')->name('meta.ads.instagram.carousal.data');
        Route::get('/meta-ads/instagram/existing-post', 'metaInstagramExistingPost')->name('meta.ads.instagram.existing.post');
        Route::get('/meta-ads/instagram/existing-post/data', 'metaInstagramExistingPostData')->name('meta.ads.instagram.existing.post.data');
        Route::get('/meta-ads/instagram/catalogue-ad', 'metaInstagramCatalogueAd')->name('meta.ads.instagram.catalogue');
        Route::get('/meta-ads/instagram/catalogue-ad/data', 'metaInstagramCatalogueAdData')->name('meta.ads.instagram.catalogue.data');

        // FB GRP CAROUSAL NEW routes
        Route::get('/meta-ads/facebook/carousal/new', 'metaFacebookCarousalNew')->name('meta.ads.facebook.carousal.new');
        Route::get('/meta-ads/facebook/carousal/new/data', 'metaFacebookCarousalNewData')->name('meta.ads.facebook.carousal.new.data');
        Route::post('/meta-ads/facebook/carousal/new/store', 'storeFacebookCarousalNewCampaign')->name('meta.ads.facebook.carousal.new.store');
        Route::post('/meta-ads/facebook/carousal/new/update-group', 'updateGroupForCampaigns')->name('meta.ads.facebook.carousal.new.update.group');

        // Raw Facebook Ads Data routes
        Route::get('/meta-ads/raw-data', 'showRawAdsData')->name('meta.ads.raw');
        Route::get('/meta-ads/raw-data/fetch', 'fetchRawAdsData')->name('meta.ads.raw.data');
        Route::get('/meta-ads/test-connection', 'testMetaApiConnection')->name('meta.ads.test.connection');

        Route::get('/facebook-ads-control/data', 'index')->name('facebook.ads.index');
        Route::get('/facebook-web-to-video', 'facebookWebToVideo')->name('facebook.web.to.video');
        Route::get('/facebook-web-to-video-data', 'facebookWebToVideoData')->name('facebook.web.to.video.data');
        Route::get('/fb-img-caraousal-to-web', 'FbImgCaraousalToWeb')->name('fb.img.caraousal.to.web');
        Route::get('/fb-img-caraousal-to-web-data', 'FbImgCaraousalToWebData')->name('fb.img.caraousal.to.web.data');
    });

    // Meta Ads Manager - Comprehensive Module
    Route::controller(\App\Http\Controllers\MarketingMaster\MetaAdsManagerController::class)->group(function () {
        // Dashboard
        Route::get('/meta-ads-manager/dashboard', 'dashboard')->name('meta.ads.manager.dashboard');

        // Accounts
        Route::get('/meta-ads-manager/accounts', 'accounts')->name('meta.ads.manager.accounts');
        Route::get('/meta-ads-manager/accounts/data', 'accountsData')->name('meta.ads.manager.accounts.data');

        // Campaigns
        Route::get('/meta-ads-manager/campaigns', 'campaigns')->name('meta.ads.manager.campaigns');
        Route::get('/meta-ads-manager/campaigns/data', 'campaignsData')->name('meta.ads.manager.campaigns.data');
        Route::post('/meta-ads-manager/campaigns/groups', 'storeGroup')->name('meta.ads.manager.campaigns.groups.store');
        Route::post('/meta-ads-manager/campaigns/ad-types', 'storeAdType')->name('meta.ads.manager.campaigns.ad-types.store');
        Route::post('/meta-ads-manager/campaigns/{campaignId}/group', 'updateCampaignGroup')->name('meta.ads.manager.campaigns.group.update');
        Route::post('/meta-ads-manager/campaigns/{campaignId}/parent', 'updateCampaignParent')->name('meta.ads.manager.campaigns.parent.update');
        Route::post('/meta-ads-manager/campaigns/{campaignId}/ad-type', 'updateCampaignAdType')->name('meta.ads.manager.campaigns.ad-type.update');

        // AdSets
        Route::get('/meta-ads-manager/adsets', 'adsets')->name('meta.ads.manager.adsets');
        Route::get('/meta-ads-manager/adsets/data', 'adsetsData')->name('meta.ads.manager.adsets.data');

        // Ads
        Route::get('/meta-ads-manager/ads', 'ads')->name('meta.ads.manager.ads');
        Route::get('/meta-ads-manager/ads/data', 'adsData')->name('meta.ads.manager.ads.data');

        // Actions
        Route::post('/meta-ads-manager/update-status', 'updateStatus')->name('meta.ads.manager.update.status');
        Route::post('/meta-ads-manager/update-budget', 'updateBudget')->name('meta.ads.manager.update.budget');
        Route::post('/meta-ads-manager/bulk-update', 'bulkUpdate')->name('meta.ads.manager.bulk.update');

        // Automation
        Route::get('/meta-ads-manager/automation', 'automation')->name('meta.ads.manager.automation');
        Route::get('/meta-ads-manager/automation/create', 'createRule')->name('meta.ads.manager.automation.create');
        Route::post('/meta-ads-manager/automation', 'storeRule')->name('meta.ads.manager.automation.store');
        Route::get('/meta-ads-manager/automation/{id}/edit', 'editRule')->name('meta.ads.manager.automation.edit');
        Route::put('/meta-ads-manager/automation/{id}', 'updateRule')->name('meta.ads.manager.automation.update');
        Route::delete('/meta-ads-manager/automation/{id}', 'deleteRule')->name('meta.ads.manager.automation.delete');

        // Logs
        Route::get('/meta-ads-manager/logs', 'logs')->name('meta.ads.manager.logs');

        // Export
        Route::get('/meta-ads-manager/export', 'export')->name('meta.ads.manager.export');
    });

    Route::controller(InstagramAdsManagerController::class)->group(function () {
        Route::get('/instagram-ads-control/data', 'index')->name('instagram.ads.index');
        Route::get('/instagram-web-to-video', 'instagramWebToVideo')->name('instagram.web.to.video');
        Route::get('/instagram-web-to-video-data', 'instagramWebToVideoData')->name('instagram.web.to.video.data');
        Route::get('/insta-img-caraousal-to-web', 'InstaImgCaraousalToWeb')->name('insta.img.caraousal.to.web');
        Route::get('/insta-img-caraousal-to-web-data', 'InstaImgCaraousalToWebData')->name('insta.img.caraousal.to.web.data');
    });

    Route::controller(YoutubeAdsManagerController::class)->group(function () {
        Route::get('/youtube-ads-control/data', 'index')->name('youtube.ads.index');
        Route::get('/youtube-web-to-video', 'youtubeWebToVideo')->name('youtube.web.to.video');
        Route::get('/youtube-web-to-video-data', 'youtubeWebToVideoData')->name('youtube.web.to.video.data');
        Route::get('/yt-img-caraousal-to-web', 'YtImgCaraousalToWeb')->name('yt.img.caraousal.to.web');
        Route::get('/yt-img-caraousal-to-web-data', 'YtImgCaraousalToWebData')->name('yt.img.caraousal.to.web.data');
    });

    Route::controller(TiktokAdsManagerController::class)->group(function () {
        Route::get('/tiktok-ads-control/data', 'index')->name('tiktok.ads.index');
        Route::get('/tiktok-web-to-video', 'tiktokWebToVideo')->name('tiktok.web.to.video');
        Route::get('/tiktok-web-to-video-data', 'tiktokWebToVideoData')->name('tiktok.web.to.video.data');
        Route::get('/tk-img-caraousal-to-web', 'TkImgCaraousalToWeb')->name('tk.img.caraousal.to.web');
        Route::get('/tk-img-caraousal-to-web-data', 'TkImgCaraousalToWebData')->name('tk.img.caraousal.to.web.data');
        Route::get('/tiktok-gmv-ads', 'tiktokGMVAds')->name('tiktok.gmv.ads');
        Route::get('/tiktok-gmv-ads-data', 'tiktokGMVAdsData')->name('tiktok.gmv.ads.data');
        Route::get('/tiktok-gmv-max', 'tiktokGmvMax')->name('tiktok.gmv.max');
        Route::get('/tiktok-gmv-max-data', 'tiktokGmvMaxData')->name('tiktok.gmv.max.data');
        Route::get('/tiktok-video-ad-analytics', 'tiktokVideoAd')->name('tiktok.video.ad.analytics');
        Route::get('/tiktok-video-ad-analytics-data', 'tiktokVideoAdData')->name('tiktok.video.ad.analytics.data');
        Route::post('/tiktok/import', 'import')->name('tiktok.import');
        Route::post('/tiktok-gmv-ad/update-status', 'updateGMVAdStatus')->name('tiktok.gmv.ad.update.status');
    });

    Route::controller(AmazonACOSController::class)->group(function () {
        Route::get('/amazon-acos-kw-control', 'amazonAcosKwControl')->name('amazon.acos.kw.control');
        Route::get('/amazon-acos-kw-control-data', 'amazonAcosKwControlData')->name('amazon.acos.kw.control.data');
        Route::get('/amazon-acos-pt-control', 'amazonAcosPtControl')->name('amazon.acos.pt.control');
        Route::get('/amazon-acos-pt-control-data', 'amazonAcosPtControlData')->name('amazon.acos.pt.control.data');

        Route::put('/update-amazon-campaign-bgt-price', 'updateAmazonCampaignBgt');
        Route::post('/toggle-amazon-sp-campaign-status', 'toggleAmazonSpCampaignStatus');
        Route::post('/toggle-amazon-sb-campaign-status', 'toggleAmazonSbCampaignStatus');
        Route::post('/toggle-amazon-sku-ads', 'toggleAmazonSkuAds');
        Route::put('/update-amazon-sb-campaign-bgt-price', 'updateAmazonSbCampaignBgt');
    });

    Route::controller(AmazonCPCZeroController::class)->group(function () {
        Route::get('/amazon-kw-cpc-zero/list', 'getKwCpcZeroView')->name('amazon.kw.cpc.zero.list');
        Route::get('/amazon-kw-cpc-zero-view-data', 'getKwCpcZeroData');
        Route::get('/amazon-pt-cpc-zero/list', 'getPtCpcZeroView')->name('amazon.pt.cpc.zero.list');
        Route::get('/amazon-pt-cpc-zero-view-data', 'getPtCpcZeroData');
    });

    Route::controller(AmazonFbaAcosController::class)->group(function () {
        Route::get('/amazon-fba/acos-kw-control', 'amazonFbaAcosKwView')->name('amazon.fba.acos.kw.control');
        Route::get('/amazon-fba/acos-kw-control-data', 'amazonFbaAcosKwControlData')->name('amazon.fba.acos.kw.control.data');
        Route::get('/amazon-fba/acos-pt-control', 'amazonFbaAcosPtView')->name('amazon.fba.acos.pt.control');
        Route::get('/amazon-fba/acos-pt-control-data', 'amazonFbaAcosPtControlData')->name('amazon.fba.acos.pt.control.data');
    });

    Route::controller(AmazonCampaignReportsController::class)->group(function () {
        Route::get('/amazon/campaign/reports', 'index')->name('amazon.campaign.reports');
        Route::get('/amazon/kw/ads', 'amazonKwAdsView')->name('amazon.kw.ads');
        Route::get('/amazon/kw/ads/data', 'getAmazonKwAdsData');
        Route::get('/amazon-kw-ads/filter', 'filterKwAds')->name('amazonKwAds.filter');
        Route::get('/amazon/campaign/chart-data', 'getCampaignChartData');

        Route::get('/amazon/pt/ads', 'amazonPtAdsView')->name('amazon.pt.ads');

        Route::get('/amazon/pt/ads/data', 'getAmazonPtAdsData');
        Route::get('/amazon-pt-ads/filter', 'filterPtAds')->name('amazonPtAds.filter');
        Route::get('/amazon/hl/ads', 'amazonHlAdsView')->name('amazon.hl.ads');
        Route::get('/amazon/hl/ads/data', 'getAmazonHlAdsData');
        Route::get('/amazon-hl-ads/filter', 'filterHlAds')->name('amazonHlAds.filter');
        Route::get('/amazon/hl/campaign/chart-data', 'getHlCampaignChartData');

        Route::get('/amazon/campaign/reports/data', 'getAmazonCampaignsData');
    });

    Route::controller(AmazonFbaAdsController::class)->group(function () {
        // Consolidated FBA KW Utilized page
        Route::get('/amazon/fba/utilized/kw', 'amazonFbaUtilizedKwView')->name('amazon.fba.utilized.kw');
        Route::get('/amazon/fba/utilized/kw/ads/data', 'getAmazonFbaUtilizedKwAdsData');

        // Consolidated FBA PT Utilized page
        Route::get('/amazon/fba/utilized/pt', 'amazonFbaUtilizedPtView')->name('amazon.fba.utilized.pt');
        Route::get('/amazon/fba/utilized/pt/ads/data', 'getAmazonFbaUtilizedPtAdsData');

        Route::get('/amazon/fba/get-utilization-counts', 'getAmazonFbaUtilizationCounts');
        Route::get('/amazon/fba/get-utilization-chart-data', 'getAmazonFbaUtilizationChartData');

        // Old routes (kept for backward compatibility)
        Route::get('/amazon/fba/over/kw/ads', 'amzFbaUtilizedBgtKw')->name('amazon.fba.over.kw.ads');
        Route::get('/amazon/fba/over/pt/ads', 'amzFbaUtilizedBgtPt')->name('amazon.fba.over.pt.ads');
        Route::get('/amazon/fba/under/kw/ads', 'amzFbaUnderUtilizedBgtKw')->name('amazon.fba.under.kw.ads');
        Route::get('/amazon/fba/under/pt/ads', 'amzFbaUnderUtilizedBgtPt')->name('amazon.fba.under.pt.ads');
        Route::get('/amazon/fba/correct/kw/ads', 'amzFbaCorrectlyUtilizedBgtKw')->name('amazon.fba.correct.kw.ads');
        Route::get('/amazon/fba/correct/pt/ads', 'amzFbaCorrectlyUtilizedBgtPt')->name('amazon.fba.correct.pt.ads');

        Route::get('/amazon/fba/kw/ads/data', 'getAmazonFbaKwAdsData');
        Route::get('/amazon/fba/pt/ads/data', 'getAmazonFbaPtAdsData');
        Route::post('/update-amazon-nr-nrl-fba-data', 'updateNrNRLFbaData');
    });

    // Amazon FBA bid automation health endpoint (last command run info; public for monitoring).
    Route::get('/amazon/fba/bid-auto-update/health', function () {
        $health = cache()->get('amazon_fba_bid_update_health');

        if (empty($health)) {
            return response()->json([
                'status' => 'NO_DATA',
                'health' => null,
            ]);
        }

        return response()->json([
            'status' => 'OK',
            'health' => $health,
        ]);
    })->withoutMiddleware([\App\Http\Middleware\Authenticate::class]);

    Route::controller(AmazonMissingAdsController::class)->group(function () {
        Route::get('/amazon/missing/ads', 'index')->name('amazon.missing.ads');
        Route::get('/amazon/missing/ads/data', 'getAmazonMissingAdsData');
        Route::get('adv-amazon/missing/save-data', 'getAmzonAdvSaveMissingData')->name('adv-amazon.missing.save-data');

        // FBA
        Route::get('/amazon/fba/missing/ads', 'fbaMissingAdsView')->name('amazon.fba.missing.ads');
        Route::get('/amazon/fba/missing/ads/data', 'getAmazonFbaMissingAdsData');
    });
    // ebay ads section
    Route::controller(EbayOverUtilizedBgtController::class)->group(function () {
        Route::get('/ebay-over-uti', 'ebayOverUtilisation')->name('ebay-over-uti');
        Route::get('/ebay/under/utilized', 'ebayUnderUtilized')->name('ebay-under-utilize');
        Route::get('/ebay/correctly/utlized', 'ebayCorrectlyUtilized')->name('ebay-correctly-utilize');
        Route::get('/ebay/utilized', 'ebayUtilizedView')->name('ebay.utilized');
        Route::get('/ebay/make-new/campaign/kw', 'ebayMakeCampaignKw')->name('ebay-make-new-campaign-kw');
        Route::get('/ebay/make-new/campaign/kw/data', 'getEbayMakeNewCampaignKw');

        Route::get('/ebay-over-uti/data', 'getEbayOverUtiData')->name('ebay-over-uti-data');
        Route::get('/ebay-over-uti/filter', 'filterOverUtilizedAds')->name('ebay-over-uti.filter');
        Route::get('/ebay-over-uti/campaign-chart', 'getCampaignChartData')->name('ebay-over-uti.campaign-chart');
        Route::get('/ebay/utilized/ads/data', 'getEbayUtilizedAdsData');
        Route::get('/ebay/get-utilization-counts', 'getEbayUtilizationCounts');
        Route::get('/ebay/get-utilization-chart-data', 'getEbayUtilizationChartData');
        Route::post('/ebay/store-statistics', 'storeEbayStatistics');
        Route::post('/update-ebay-nr-data', 'updateNrData');
        Route::put('/update-ebay-keywords-bid-price', 'updateKeywordsBidDynamic');
        Route::post('/save-ebay-sbid-m', 'saveEbaySbidM');
        Route::post('/save-ebay-sbid-m-bulk', 'saveEbaySbidMBulk');
        Route::post('/clear-ebay-sbid-m-bulk', 'clearEbaySbidMBulk');
        Route::post('/toggle-ebay-campaign-status', 'toggleCampaignStatus');
    });
    Route::controller(EbayACOSController::class)->group(function () {
        Route::get('/ebay-over-uti-acos-pink', 'ebayOverUtiAcosPink')->name('ebay-over-uti-acos-pink');
        Route::get('/ebay-over-uti-acos-green', 'ebayOverUtiAcosGreen')->name('ebay-over-uti-acos-green');
        Route::get('/ebay-over-uti-acos-red', 'ebayOverUtiAcosRed')->name('ebay-over-uti-acos-red');

        Route::get('/ebay-under-uti-acos-pink', 'ebayUnderUtiAcosPink')->name('ebay-under-uti-acos-pink');
        Route::get('/ebay-under-uti-acos-green', 'ebayUnderUtiAcosGreen')->name('ebay-under-uti-acos-green');
        Route::get('/ebay-under-uti-acos-red', 'ebayUnderUtiAcosRed')->name('ebay-under-uti-acos-red');

        Route::get('/ebay-uti-acos/data', 'getEbayUtilisationAcosData');
        Route::get('/ebay-under-uti/campaign-chart', 'getCampaignChartData')->name('ebay-under-uti.campaign-chart');
        Route::get('/ebay-under-uti/filter', 'filterUnderUtilizedAds')->name('ebay-under-uti.filter');
    });

    Route::controller(EbayPinkDilAdController::class)->group(function () {
        Route::get('/ebay/pink-dil/ads', 'index')->name('ebay.pink.dil.ads');
        Route::get('/ebay/pink-dil/ads/data', 'getEbayPinkDilAdsData');
    });

    Route::controller(EbayPMPAdsController::class)->group(function () {
        Route::get('/ebay/pmp/ads', 'index')->name('ebay.pmp.ads');
        Route::get('/ebay/pmp/ads/data', 'getEbayPmpAdsData');
        Route::get('/ebay/pmp/debug/ebay-views/{sku}', 'debugEbaySkuViews')->where('sku', '.*');
        Route::get('/ebay/pmp/ads/filter', 'filterEbayPmpAds')->name('ebay.pmp.ads.filter');
        Route::get('/ebay/pmp/ads/campaign-chart', 'getCampaignChartData')->name('ebay.pmp.ads.campaign-chart');
        Route::post('/update-ebay-pmt-percenatge', 'updateEbayPercentage');
        Route::post('/update-ebay-pmt-sprice', 'saveEbayPMTSpriceToDatabase');
    });

    Route::controller(EbayKwAdsController::class)->group(function () {
        Route::get('/ebay/keywords/ads', 'index')->name('ebay.keywords.ads');
        Route::get('/ebay/keywords/ads/data', 'getEbayKwAdsData');
        Route::get('/ebay/keywords/ads/filter', 'filterEbayKwAds')->name('ebay.keywords.ads.filter');
        Route::get('/ebay/keywords/ads/campaign-chart', 'getCampaignChartData')->name('ebay.keywords.ads.campaign-chart');

        Route::get('/ebay/keywords/ads/less-than-twenty', 'ebayPriceLessThanTwentyAdsView')->name('ebay.keywords.ads.less-than-twenty');
        Route::get('/ebay/keywords/ads/less-than-twenty/data', 'ebayPriceLessThanTwentyAdsData');
        Route::get('/ebay/keywords/ads/less-than-twenty/campaign-chart', 'getCampaignChartData')->name('ebay.keywords.ads.less-than-twenty.campaign-chart');
        Route::get('/ebay/keywords/ads/less-than-twenty/filter', 'filterEbayKwAds')->name('ebay.keywords.ads.less-than-twenty.filter');
    });

    Route::controller(EbayRunningAdsController::class)->group(function () {
        Route::get('/ebay/ad-running/list', 'index')->name('ebay.running.ads');
        Route::get('/ebay/ad-running/data', 'getEbayRunningAdsData');
        Route::get('/ebay/ad-running/filter', 'filterRunningAds')->name('ebay.running.ads.filter');
        Route::get('/ebay/ad-running/campaign-chart', 'getCampaignChartData')->name('ebay.running.ads.campaign-chart');
        Route::get('/adv-ebay/ad-running/save-data', 'getEbayRunningDataSave')->name('adv-ebay.ad-running.save-data');
    });

    Route::controller(EbayMissingAdsController::class)->group(function () {
        Route::get('/ebay/ad-missing/list', 'index')->name('ebay.missing.ads');
        Route::get('/ebay/ad-missing/data', 'getEbayMissingAdsData');
        Route::get('/adv-ebay/missing/save-data', 'getEbayMissingSaveData')->name('adv-ebay.missing.save-data');
        Route::post('/update-ebay-nrl-data', 'updateNrlData');
    });

    Route::controller(EbayViewsController::class)->group(function () {
        Route::get('/ebay-views/list', 'index')->name('ebay.views.data');
        Route::get('/ebay-views/get-data', 'getEbayViewsData');
    });

    // ebay 2 ads section
    Route::controller(Ebay2PMTAdController::class)->group(function () {
        Route::get('/ebay-2/pmt/ads', 'index')->name('ebay2.pmt.ads');
        Route::get('/ebay-2/pmp/ads/data', 'getEbay2PmtAdsData');
        Route::get('/ebay-2/pmp/ads/filter', 'filterEbay2PmtAds')->name('ebay2.pmt.ads.filter');
        Route::get('/ebay-2/pmp/ads/campaign-chart', 'getCampaignChartData')->name('ebay2.pmt.ads.campaign-chart');
        Route::post('/update-ebay-2-pmt-percentage', 'updateEbay2Percentage');
        Route::post('/update-ebay-2-pmt-sprice', 'saveEbay2PMTSpriceToDatabase');
        Route::post('/update-ebay2-nr-data', 'updateEbay2NrData');
    });

    Route::controller(Ebay2RunningAdsController::class)->group(function () {
        Route::get('/ebay-2/ad-running/list', 'index')->name('ebay2.running.ads');
        Route::get('/ebay-2/ad-running/data', 'getEbay2RunningAdsData');
        Route::get('/adv-ebay2/ad-running/save-data', 'getEbay2AdvRunningAdDataSave')->name('adv-ebay2.ad-running.save-data');
    });

    Route::controller(Ebay2MissingAdsController::class)->group(function () {
        Route::get('/ebay2/ad-missing/list', 'index')->name('ebay2.missing.ads');
        Route::get('/ebay2/ad-missing/data', 'getEbay2MissingAdsData');
        Route::get('/adv-ebay2/missing/save-data', 'getAdvEbay2MissingSaveData')->name('adv-ebay2.missing.save-data');
    });

    // ebay 3 ads section
    Route::controller(Ebay3AcosController::class)->group(function () {
        Route::get('/ebay-3/over-acos-pink', 'ebay3OverAcosPinkView')->name('ebay3-over-uti-acos-pink');
        Route::get('/ebay-3/over-acos-green', 'ebay3OverAcosGreenView')->name('ebay3-over-uti-acos-green');
        Route::get('/ebay-3/over-acos-red', 'ebay3OverAcosRedView')->name('ebay3-over-uti-acos-red');
        Route::get('/ebay-3/under-acos-pink', 'ebay3UnderAcosPinkView')->name('ebay3-under-uti-acos-pink');
        Route::get('/ebay-3/under-acos-green', 'ebay3UnderAcosGreenView')->name('ebay3-under-uti-acos-green');
        Route::get('/ebay-3/under-acos-red', 'ebay3UnderAcosRedView')->name('ebay3-under-uti-acos-red');

        Route::get('/ebay-3/acos/control/data', 'getEbay3AcosControlData');
    });

    Route::controller(Ebay3PinkDilAdController::class)->group(function () {
        Route::get('/ebay-3/pink-dil/ads', 'index')->name('ebay3.pink.dil.ads');
        Route::get('/ebay-3/pink-dil/ads/data', 'getEbay3PinkDilAdsData');
    });

    Route::controller(Ebay3PmtAdsController::class)->group(function () {
        Route::get('/ebay-3/pmt/ads', 'index')->name('ebay3.pmt.ads');
        Route::get('/ebay-3/pmp/ads/data', 'getEbay3PmtAdsData');
        Route::post('/update-ebay-3-pmt-percenatge', 'updateEbay3Percentage');
        Route::post('/update-ebay-3-pmt-sprice', 'saveEbay3PMTSpriceToDatabase');
        Route::get('/ebay-3/pmp/ads/filter', 'filterEbay3PmtAds')->name('ebay3.pmp.ads.filter');
        Route::get('/ebay-3/pmp/ads/campaign-chart', 'getCampaignChartData')->name('ebay3.pmp.ads.campaign-chart');
    });

    Route::controller(Ebay3UtilizedAdsController::class)->group(function () {
        Route::get('/ebay-3/over-utilized', 'ebay3OverUtilizedAdsView')->name('ebay3.over.utilized');
        Route::get('/ebay-3/under-utilized', 'ebay3UnderUtilizedAdsView')->name('ebay3.under.utilized');
        Route::get('/ebay-3/correctly-utilized', 'ebay3CorrectlyUtilizedAdsView')->name('ebay3.correctly.utilized');
        Route::get('/ebay-3/utilized', 'ebay3UtilizedView')->name('ebay3.utilized');
        Route::get('/ebay-3/get-utilization-counts', 'getEbay3UtilizationCounts');
        Route::get('/ebay-3/get-utilization-chart-data', 'getEbay3UtilizationChartData');
        Route::get('/ebay-3/utilized/ads/data', 'getEbay3UtilizedAdsData');
        Route::post('/ebay-3/store-statistics', 'storeEbay3Statistics');
        Route::get('/ebay-3/over-utilized/filter', 'filterOverUtilizedAds')->name('ebay3.over.utilized.filter');
        Route::get('/ebay-3/over-utilized/campaign-chart', 'getCampaignChartData')->name('ebay3.over.utilized.campaign-chart');
        Route::put('/update-ebay3-keywords-bid-price', 'updateKeywordsBidDynamic');
        Route::post('/update-ebay3-nr-data', 'updateEbay3NrData');
        Route::post('/save-ebay3-sbid-m', 'saveEbay3SbidM');
        Route::post('/save-ebay3-sbid-m-bulk', 'saveEbay3SbidMBulk');
        Route::post('/clear-ebay3-sbid-m-bulk', 'clearEbay3SbidMBulk');
        Route::post('/toggle-ebay3-campaign-status', 'toggleCampaignStatus');
    });

    Route::controller(Ebay2UtilizedAdsController::class)->group(function () {
        Route::get('/ebay-2/utilized', 'ebay2UtilizedView')->name('ebay2.utilized');
        Route::get('/ebay-2/get-utilization-counts', 'getEbay2UtilizationCounts');
        Route::get('/ebay-2/get-utilization-chart-data', 'getEbay2UtilizationChartData');
        Route::get('/ebay-2/utilized/ads/data', 'getEbay2UtilizedAdsData');
        Route::post('/ebay-2/store-statistics', 'storeEbay2Statistics');
        Route::get('/ebay-2/over-utilized/filter', 'filterOverUtilizedAds')->name('ebay2.over.utilized.filter');
        Route::get('/ebay-2/over-utilized/campaign-chart', 'getCampaignChartData')->name('ebay2.over.utilized.campaign-chart');
        Route::put('/update-ebay2-keywords-bid-price', 'updateKeywordsBidDynamic');
        Route::post('/update-ebay2-nr-data', 'updateEbay2NrData');
        Route::post('/save-ebay2-sbid-m', 'saveEbay2SbidM');
        Route::post('/save-ebay2-sbid-m-bulk', 'saveEbay2SbidMBulk');
        Route::post('/clear-ebay2-sbid-m-bulk', 'clearEbay2SbidMBulk');
        Route::post('/toggle-ebay2-campaign-status', 'toggleCampaignStatus');
    });

    Route::controller(Ebay3KeywordAdsController::class)->group(function () {
        Route::get('/ebay-3/keywords/ads', 'ebay3KeywordAdsView')->name('ebay3.keywords.ads');
        Route::get('/ebay-3/keywords/ads/data', 'getEbay3KeywordAdsData');

        Route::get('/ebay-3/keywords/ads/less-than-thirty', 'ebay3PriceLessThanThirtyAdsView')->name('ebay3.keywords.ads.less-than-thirty');
        Route::get('/ebay-3/keywords/ads/less-than-thirty/data', 'ebay3PriceLessThanThirtyAdsData');

        Route::get('/ebay-3/make-new/kw-ads', 'ebay3MakeNewKwAdsView')->name('ebay3.make.new.kw.ads');
        Route::get('/ebay-3/make-new/kw-ads/data', 'getEbay3MMakeNewKwAdsData');
    });

    Route::controller(Ebay3RunningAdsController::class)->group(function () {
        Route::get('/ebay-3/ad-running/list', 'index')->name('ebay3.running.ads');
        Route::get('/ebay-3/ad-running/data', 'getEbay3RunningAdsData');
        Route::get('/adv-ebay3/ad-running/save-data', 'getAdvEbay3AdRunningDataSave')->name('adv-ebay3.ad-running.save-data');
    });

    Route::controller(Ebay3MissingAdsController::class)->group(function () {
        Route::get('/ebay-3/ad-missing/list', 'index')->name('ebay3.missing.ads');
        Route::get('/ebay-3/ad-missing/data', 'getEbay3MissingAdsData');
        Route::get('/adv-ebay3/missing/save-data', 'getEbay3MissingDataSave')->name('adv-ebay3.missing.save-data');
    });

    Route::controller(WalmartUtilisationController::class)->group(function () {
        Route::get('/walmart/utilized/bgt', 'bgtUtilisedView')->name('walmart.utilized.bgt');
        Route::get('/walmart/utilized/kw', 'index')->name('walmart.utilized.kw');
        Route::get('/walmart/over/utilized', 'overUtilisedView')->name('walmart.over.utilized');
        Route::get('/walmart/under/utilized', 'underUtilisedView')->name('walmart.under.utilized');
        Route::get('/walmart/correctly/utilized', 'correctlyUtilisedView')->name('walmart.correctly.utilized');
        Route::get('/walmart/utilized/kw/data', 'getWalmartAdsData');
        Route::get('/walmart/utilized/bgt/7ub-chart-data', 'get7ubChartData');
        Route::get('/walmart/utilized/bgt/combined-7ub-1ub-chart-data', 'getCombined7ub1ubChartData');
        Route::post('/walmart/utilized/bgt/refresh-sheet', 'refreshWalmartSheet');
        Route::post('/walmart/utilized/bgt/refresh-campaign-data', 'refreshWalmartCampaignData');
    });

    Route::controller(WalmartMissingAdsController::class)->group(function () {
        Route::get('/walmart/missing/ads', 'index')->name('walmart.missing.ads');
        Route::get('/walmart/missing/ads/data', 'getWalmartMissingAdsData');
    });

    Route::controller(WalmartRunningAdsController::class)->group(function () {
        Route::get('/walmart/running/ads', 'index')->name('walmart.running.ads');
        Route::get('/walmart/running/ads/data', 'getWalmartRunningAdsData');
        Route::get('/adv-walmart/ad-running/save-data', 'getAdvWalmartRunningSaveData')->name('adv-walmart.ad-running.save-data');
    });
    Route::controller(GoogleAdsController::class)->group(function () {
        Route::get('/google/shopping', 'index')->name('google.shopping');
        Route::get('/google/shopping/running', 'googleShoppingAdsRunning')->name('google.shopping.running');
        Route::get('/google/shopping/utilized', 'googleShoppingUtilizedView')->name('google.shopping.utilized');
        Route::get('/google/shopping/get-utilization-counts', 'getGoogleShoppingUtilizationCounts')->name('google.shopping.utilization.counts');
        Route::get('/google/shopping/get-utilization-chart-data', 'getGoogleShoppingUtilizationChartData')->name('google.shopping.utilization.chart.data');
        Route::get('/google/shopping/report', 'googleShoppingAdsReport')->name('google.shopping.report');
        Route::get('/adv-shopify/gshopping/save-data', 'getAdvShopifyGShoppingSaveData')->name('adv-shopify.gshopping.save-data');

        // Chart filter routes
        Route::get('/google/shopping/chart/filter', 'filterGoogleShoppingChart')->name('google.shopping.chart.filter');
        Route::get('/google/shopping/running/chart/filter', 'filterGoogleShoppingRunningChart')->name('google.shopping.running.chart.filter');
        Route::get('/google/shopping/campaign/chart-data', 'getGoogleShoppingCampaignChartData');
        Route::get('/google/shopping/campaign/acos-chart-data', 'getGoogleShoppingCampaignAcosChartData');
        Route::get('/google/shopping/campaign/cvr-price-chart-data', 'getGoogleShoppingCampaignCvrPriceChartData');
        Route::get('/google/shopping/overall-acos-chart-data', 'getGoogleShoppingOverallAcosChartData');
        Route::get('/google/shopping/overall-cvr-price-chart-data', 'getGoogleShoppingOverallCvrPriceChartData');
        Route::get('/google/shopping/report/chart/filter', 'filterGoogleShoppingReportChart')->name('google.shopping.report.chart.filter');
        Route::get('/google/serp/chart/filter', 'filterGoogleSerpChart')->name('google.shopping.serp.chart.filter');

        // Toggle campaign status
        Route::post('/google/shopping/toggle-campaign-status', 'toggleGoogleShoppingCampaignStatus')->name('google.shopping.toggle.campaign.status');
        Route::post('/google/shopping/toggle-bulk-campaign-status', 'toggleBulkGoogleShoppingCampaignStatus')->name('google.shopping.toggle.bulk.campaign.status');
        Route::get('/google/serp/report/chart/filter', 'filterGoogleSerpReportChart')->name('google.serp.report.chart.filter');
        Route::get('/google/pmax/chart/filter', 'filterGooglePmaxChart')->name('google.shopping.pmax.chart.filter');

        Route::get('/google/serp/list', 'googleSerpView')->name('google.serp.list');
        Route::get('/google/serp/report', 'googleSerpReportView')->name('google.serp.report');

        Route::get('/google/pmax/list', 'googlePmaxView')->name('google.pmax.list');

        Route::get('/google/shopping/data', 'getGoogleShoppingAdsData');
        Route::get('/google/shopping/ads-report/data', 'getGoogleShoppingAdsReportData');

        Route::get('/google/search/data', 'getGoogleSearchAdsData');
        Route::get('/google/search/report/data', 'getGoogleSearchAdsReportData');

        Route::post('/update-google-ads-bid-price', 'updateGoogleAdsCampaignSbid');
        Route::post('/update-google-nr-data', 'updateGoogleNrData');
        Route::post('/bulk-update-google-nr-data', 'bulkUpdateGoogleNrData');
    });

    Route::controller(TemuAdsController::class)->group(function () {
        Route::get('/temu/ads', 'index')->name('temu.ads');
        Route::get('/temu/ads/data', 'getTemuAdsData');
        Route::post('/temu/ads/update', 'updateTemuAds')->name('temu.ads.update');
        Route::post('/temu/ads/upload-campaign-report', 'uploadCampaignReport')->name('temu.ads.upload.campaign');
    });

    Route::controller(TiktokAdsController::class)->group(function () {
        Route::get('/tiktok/ads', 'index')->name('tiktokshop.ads');
        Route::get('/tiktok/utilized', 'utilized')->name('tiktok.utilized');
        Route::get('/tiktok/utilized/data', 'getUtilizedData')->name('tiktok.utilized.data');
        Route::post('/tiktok/utilized/upload', 'uploadUtilized')->name('tiktok.utilized.upload');
        Route::post('/tiktok/utilized/update', 'updateUtilized')->name('tiktok.utilized.update');
    });
    Route::prefix('repricer/amazon-search')->name('repricer.amazon-search.')->group(function () {
        Route::get('/', [\App\Http\Controllers\RePricer\AmazonSearchController::class, 'index'])->name('index');
        Route::post('/search', [\App\Http\Controllers\RePricer\AmazonSearchController::class, 'search'])->name('search');
        Route::get('/history', [\App\Http\Controllers\RePricer\AmazonSearchController::class, 'getSearchHistory'])->name('history');
        Route::get('/results', [\App\Http\Controllers\RePricer\AmazonSearchController::class, 'getResults'])->name('results');
        Route::get('/filter-options', [\App\Http\Controllers\RePricer\AmazonSearchController::class, 'getFilterOptions'])->name('filter-options');
        Route::get('/raw-response', [\App\Http\Controllers\RePricer\AmazonSearchController::class, 'getRawResponse'])->name('raw-response');
        Route::get('/skus', [\App\Http\Controllers\RePricer\AmazonSearchController::class, 'getSkus'])->name('skus');
        Route::post('/store-competitors', [\App\Http\Controllers\RePricer\AmazonSearchController::class, 'storeCompetitors'])->name('store-competitors');
    });

    Route::prefix('repricer/ebay-search')->name('repricer.ebay-search.')->group(function () {
        Route::get('/', [\App\Http\Controllers\RePricer\EbaySearchController::class, 'index'])->name('index');
        Route::post('/search', [\App\Http\Controllers\RePricer\EbaySearchController::class, 'search'])->name('search');
        Route::get('/history', [\App\Http\Controllers\RePricer\EbaySearchController::class, 'getSearchHistory'])->name('history');
        Route::get('/results', [\App\Http\Controllers\RePricer\EbaySearchController::class, 'getResults'])->name('results');
        Route::get('/filter-options', [\App\Http\Controllers\RePricer\EbaySearchController::class, 'getFilterOptions'])->name('filter-options');
        Route::get('/skus', [\App\Http\Controllers\RePricer\EbaySearchController::class, 'getSkus'])->name('skus');
        Route::post('/store-competitors', [\App\Http\Controllers\RePricer\EbaySearchController::class, 'storeCompetitors'])->name('store-competitors');
    });

    Route::get('/facebook-image-ads', [FacebookAdsController::class, 'facebookImageAds'])->name('facebook.image.ads');
    Route::get('/facebook-image-ads-data', [FacebookAdsController::class, 'facebookImageAdsData'])->name('facebook.image.ads.data');
    // Removed duplicate route - using /facebook-video-ad (singular) from VideoAdsMasterController instead
    // Route::get('/facebook-video-ads', [FacebookAdsController::class, 'facebookVideoAds'])->name('facebook.video.ads');
    Route::get('/facebook-video-ads-data', [FacebookAdsController::class, 'facebookVideoAdsData'])->name('facebook.video.ads.data');

    Route::controller(FbaDataController::class)->group(function () {
        Route::get('fba-view-page', 'fbaPageView');
        Route::get('fba-dispatch-page', 'fbaDispatchPageView');
        Route::get('fba-ads-keywords', 'fbaadskw');
        Route::get('fba-ads-pt', 'fbaAdsPt');
        Route::get('fba-data-json', 'fbaDataJson');
        Route::post('push-fba-price', 'pushFbaPrice');
        Route::post('update-fba-sprice-status', 'updateSpriceStatus');
        Route::get('fba-ads-data-json', 'fbaAdsDataJson');
        Route::get('fba-ads-pt-data-json', 'fbaAdsPtDataJson');
        Route::get('fba-manual-export', 'exportFbaManualData');
        Route::post('fba-manual-import', 'importFbaManualData');
        Route::get('fba-manual-sample', 'downloadSampleTemplate');
        Route::post('fba-ship-calculations-sync', 'syncFbaShipCalculations');
        Route::post('update-fba-sku-manual-data', 'updateFbaSkuManualData');
        Route::get('fba-dispatch-column-visibility', 'getFbaDispatchColumnVisibility');
        Route::post('fba-dispatch-column-visibility', 'setFbaDispatchColumnVisibility');
        Route::get('fba-column-visibility', 'getFbaColumnVisibility');
        Route::post('fba-column-visibility', 'setFbaColumnVisibility');
        Route::get('fba-metrics-history', 'getMetricsHistory');
        Route::get('fba-badge-chart-data', 'fbaBadgeChartData');
        Route::post('update-fba-listing-status', 'updateFbaListingStatus');
    });
    Route::controller(FBAAnalysticsController::class)->group(function () {

        Route::get('fba-analytics-page', 'fbaPageView');
        Route::get('fba-analytics-data-json', 'fbaDataJson');
        Route::get('fba-monthly-sales/{sku}', 'getFbaMonthlySales');
        Route::post('update-fba-manual-data', 'updateFbaManualData');
    });

    Route::post('/channel-promotion/store', [ChannelPromotionMasterController::class, 'storeOrUpdatePromotion']);

    // eBay Refresh Token Generation Routes (must be before catch-all routes)
    // Unified route for all eBay accounts (eBay1, eBay2, eBay3)
    Route::get('/ebay/generate-token', [\App\Http\Controllers\EbayTokenController::class, 'generate'])->name('ebay.token.generate');
    Route::match(['get', 'post'], '/ebay/token-callback', [\App\Http\Controllers\EbayTokenController::class, 'callback'])->name('ebay.token.callback');
    // Task Manager Routes
    Route::get('/tasks', [\App\Http\Controllers\TaskController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/summary', [\App\Http\Controllers\TaskController::class, 'taskSummary'])->name('tasks.summary');
    Route::get('/tasks/summary-stats', [\App\Http\Controllers\TaskController::class, 'taskSummaryStats'])->name('tasks.summaryStats');
    Route::get('/tasks/data', [\App\Http\Controllers\TaskController::class, 'getData'])->name('tasks.data');
    Route::get('/tasks/automated', [\App\Http\Controllers\TaskController::class, 'automatedIndex'])->name('tasks.automated');
    Route::get('/tasks/automated/data', [\App\Http\Controllers\TaskController::class, 'getAutomatedData'])->name('tasks.automatedData');
    Route::get('/tasks/automated/create', [\App\Http\Controllers\TaskController::class, 'automatedCreate'])->name('tasks.automatedCreate');
    Route::post('/tasks/automated/store', [\App\Http\Controllers\TaskController::class, 'automatedStore'])->name('tasks.automatedStore');
    Route::get('/tasks/automated/{id}/edit', [\App\Http\Controllers\TaskController::class, 'automatedEdit'])->name('tasks.automatedEdit');
    Route::put('/tasks/automated/{id}', [\App\Http\Controllers\TaskController::class, 'automatedUpdate'])->name('tasks.automatedUpdate');
    Route::delete('/tasks/automated/{id}', [\App\Http\Controllers\TaskController::class, 'automatedDestroy'])->name('tasks.automatedDestroy');
    Route::get('/tasks/deleted', [\App\Http\Controllers\TaskController::class, 'deletedIndex'])->name('tasks.deleted');
    Route::get('/tasks/deleted/data', [\App\Http\Controllers\TaskController::class, 'deletedData'])->name('tasks.deletedData');
    Route::post('/tasks/deleted/{id}/revive', [\App\Http\Controllers\TaskController::class, 'reviveDeletedTask'])->name('tasks.deleted.revive');
    Route::get('/tasks/deleted-badge-stats', [\App\Http\Controllers\TaskController::class, 'deletedBadgeStats'])->name('tasks.deletedBadgeStats');
    Route::post('/tasks/set-selected-user', [\App\Http\Controllers\TaskController::class, 'setSelectedUser'])->name('tasks.setSelectedUser');
    Route::get('/get-user-rr', [\App\Http\Controllers\TaskController::class, 'getUserRR'])->name('tasks.getUserRR');
    Route::post('/tasks/store-user-rr', [\App\Http\Controllers\TaskController::class, 'storeUserRR'])->name('tasks.storeUserRR');
    Route::get('/tasks/get-user-rr-data', [\App\Http\Controllers\TaskController::class, 'getUserRRData'])->name('tasks.getUserRRData');
    Route::post('/tasks/upload-image', [\App\Http\Controllers\TaskController::class, 'uploadImage'])->name('tasks.uploadImage');
    Route::get('/tasks/users-list', [\App\Http\Controllers\TaskController::class, 'getUsersList'])->name('tasks.usersList');
    Route::get('/tasks/download-template', [\App\Http\Controllers\TaskController::class, 'downloadTemplate'])->name('tasks.downloadTemplate');
    Route::post('/tasks/import-csv', [\App\Http\Controllers\TaskController::class, 'importCsv'])->name('tasks.importCsv');
    Route::get('/tasks/create', [\App\Http\Controllers\TaskController::class, 'create'])->name('tasks.create');
    Route::post('/tasks', [\App\Http\Controllers\TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/bulk-create', [\App\Http\Controllers\TaskController::class, 'bulkCreate'])->name('tasks.bulkCreate');
    Route::post('/tasks/bulk-store', [\App\Http\Controllers\TaskController::class, 'bulkStore'])->name('tasks.bulkStore');
    Route::post('/tasks/bulk-update', [\App\Http\Controllers\TaskController::class, 'bulkUpdate'])->name('tasks.bulkUpdate');
    Route::get('/tasks/{id}', [\App\Http\Controllers\TaskController::class, 'show'])->name('tasks.show');
    Route::get('/tasks/{id}/edit', [\App\Http\Controllers\TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('/tasks/{id}', [\App\Http\Controllers\TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{id}', [\App\Http\Controllers\TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('/tasks/{id}/complete', [\App\Http\Controllers\TaskController::class, 'complete'])->name('tasks.complete');
    Route::post('/tasks/{id}/update-status', [\App\Http\Controllers\TaskController::class, 'updateStatus'])->name('tasks.updateStatus');

    // Resources Master (R&R / training / checklists / media / links)
    Route::prefix('resources-master')->middleware('auth')->name('resources-master.')->group(function () {
        Route::get('/', [ResourcesMasterController::class, 'dashboard'])->name('dashboard');
        Route::get('/data', [ResourcesMasterController::class, 'data'])->name('data');
        Route::get('/section/{section}', [ResourcesMasterController::class, 'section'])->name('section');
        Route::post('/bulk-upload', [ResourcesMasterController::class, 'bulkUpload'])->name('bulk-upload');
        Route::post('/import/csv', [ResourcesMasterController::class, 'importCsv'])->name('import.csv');
        Route::post('/import/zip', [ResourcesMasterController::class, 'importZip'])->name('import.zip');
        Route::post('/store', [ResourcesMasterController::class, 'store'])->name('store');
        Route::put('/item/{resource}', [ResourcesMasterController::class, 'update'])->name('update');
        Route::delete('/item/{resource}', [ResourcesMasterController::class, 'destroy'])->name('destroy');
        Route::post('/restore/{id}', [ResourcesMasterController::class, 'restore'])->whereNumber('id')->name('restore');
        Route::delete('/force/{id}', [ResourcesMasterController::class, 'forceDestroy'])->whereNumber('id')->name('force-destroy');
        Route::get('/item/{resource}/download', [ResourcesMasterController::class, 'download'])->name('download');
        Route::get('/item/{resource}/thumbnail', [ResourcesMasterController::class, 'thumbnail'])->name('thumbnail');
        Route::post('/item/{resource}/view', [ResourcesMasterController::class, 'logView'])->name('view-log');
        Route::post('/item/{resource}/watch', [ResourcesMasterController::class, 'watch'])->name('watch');
    });

    // User management routes
    Route::get('/users/add', [UserController::class, 'index'])
        ->middleware('auth')
        ->name('users.add');

    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('auth')
        ->name('users.update');

    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->middleware('auth')
        ->name('users.destroy');

    Route::post('/users/{id}/restore', [UserController::class, 'restore'])
        ->middleware('auth')
        ->whereNumber('id')
        ->name('users.restore');

    // API endpoint for active users
    Route::get('/api/users/active', [UserController::class, 'getActiveUsers'])
        ->middleware('auth')
        ->name('api.users.active');

    Route::get('/users/{user}/rr-portfolio', [UserRRPortfolioController::class, 'show'])
        ->middleware('auth')
        ->name('users.rr-portfolio.show');

    Route::post('/users/{user}/rr-portfolio', [UserRRPortfolioController::class, 'upload'])
        ->middleware('auth')
        ->name('users.rr-portfolio.upload');

    Route::post('/users/{user}/rr-portfolio/assignment/{assignment}/fits', [UserRRPortfolioController::class, 'updateFits'])
        ->middleware('auth')
        ->name('users.rr-portfolio.assignment.fits');

    // Performance Management Routes
    Route::prefix('performance')->middleware('auth')->name('performance.')->group(function () {
        // Dashboard Routes
        Route::get('/dashboard', [\App\Http\Controllers\PerformanceManagement\PerformanceDashboardController::class, 'employeeDashboard'])->name('dashboard');
        Route::get('/manager-dashboard', [\App\Http\Controllers\PerformanceManagement\PerformanceDashboardController::class, 'managerDashboard'])->name('manager.dashboard');
        Route::get('/admin-dashboard', [\App\Http\Controllers\PerformanceManagement\PerformanceDashboardController::class, 'adminDashboard'])->name('admin.dashboard');
        Route::get('/create-review', [\App\Http\Controllers\PerformanceManagement\PerformanceDashboardController::class, 'createReview'])->name('create.review');
        Route::get('/chart-data/{employeeId}', [\App\Http\Controllers\PerformanceManagement\PerformanceDashboardController::class, 'getChartData'])->name('chart.data');

        // Designation Management (Admin Only)
        Route::prefix('designations')->name('designations.')->group(function () {
            Route::get('/', [\App\Http\Controllers\PerformanceManagement\DesignationController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\PerformanceManagement\DesignationController::class, 'store'])->name('store');
            Route::put('/{designation}', [\App\Http\Controllers\PerformanceManagement\DesignationController::class, 'update'])->name('update');
            Route::delete('/{designation}', [\App\Http\Controllers\PerformanceManagement\DesignationController::class, 'destroy'])->name('destroy');
        });

        // Checklist Management (Admin Only)
        Route::prefix('checklist')->name('checklist.')->group(function () {
            Route::get('/{designationId}', [\App\Http\Controllers\PerformanceManagement\ChecklistController::class, 'getChecklist'])->name('get');
            Route::get('/category/{category}', [\App\Http\Controllers\PerformanceManagement\ChecklistController::class, 'getCategory'])->name('category.get');
            Route::post('/category', [\App\Http\Controllers\PerformanceManagement\ChecklistController::class, 'storeCategory'])->name('category.store');
            Route::put('/category/{category}', [\App\Http\Controllers\PerformanceManagement\ChecklistController::class, 'updateCategory'])->name('category.update');
            Route::delete('/category/{category}', [\App\Http\Controllers\PerformanceManagement\ChecklistController::class, 'destroyCategory'])->name('category.destroy');
            Route::get('/item/{item}', [\App\Http\Controllers\PerformanceManagement\ChecklistController::class, 'getItem'])->name('item.get');
            Route::post('/item', [\App\Http\Controllers\PerformanceManagement\ChecklistController::class, 'storeItem'])->name('item.store');
            Route::put('/item/{item}', [\App\Http\Controllers\PerformanceManagement\ChecklistController::class, 'updateItem'])->name('item.update');
            Route::delete('/item/{item}', [\App\Http\Controllers\PerformanceManagement\ChecklistController::class, 'destroyItem'])->name('item.destroy');
            Route::post('/reorder-categories', [\App\Http\Controllers\PerformanceManagement\ChecklistController::class, 'reorderCategories'])->name('reorder.categories');
            Route::post('/reorder-items', [\App\Http\Controllers\PerformanceManagement\ChecklistController::class, 'reorderItems'])->name('reorder.items');
            Route::get('/export/{designationIdOrName}', [\App\Http\Controllers\PerformanceManagement\ChecklistController::class, 'exportCsv'])->name('export');
            Route::post('/import/{designationIdOrName}', [\App\Http\Controllers\PerformanceManagement\ChecklistController::class, 'importCsv'])->name('import');
        });

        // Performance Review Routes (specific routes before {id} to avoid conflicts)
        Route::prefix('reviews')->name('reviews.')->group(function () {
            Route::get('/', [\App\Http\Controllers\PerformanceManagement\PerformanceReviewController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\PerformanceManagement\PerformanceReviewController::class, 'create'])->name('create');
            Route::get('/employee/{employeeId}/stats', [\App\Http\Controllers\PerformanceManagement\PerformanceReviewController::class, 'getEmployeeStats'])->name('employee.stats');
            Route::post('/', [\App\Http\Controllers\PerformanceManagement\PerformanceReviewController::class, 'store'])->name('store');
            Route::get('/{id}', [\App\Http\Controllers\PerformanceManagement\PerformanceReviewController::class, 'show'])->name('show')->where('id', '[0-9]+');
        });
    });

    // Warehouse Management System (WMS) — register before generic {first}/{second} routes
    Route::prefix('wms')->middleware(['auth', 'wms.portal'])->name('wms.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Wms\WmsPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/structure', [\App\Http\Controllers\Wms\WmsPortalController::class, 'structure'])->name('structure');
        Route::get('/inventory-by-location', [\App\Http\Controllers\Wms\WmsPortalController::class, 'inventoryByLocation'])->name('inventory');
        Route::get('/scan', [\App\Http\Controllers\Wms\WmsPortalController::class, 'scan'])->name('scan');
        Route::get('/movements', [\App\Http\Controllers\Wms\WmsPortalController::class, 'movements'])->name('movements');
        Route::get('/pick', [\App\Http\Controllers\Wms\WmsPortalController::class, 'pick'])->name('pick');
        Route::get('/putaway', [\App\Http\Controllers\Wms\WmsPortalController::class, 'putaway'])->name('putaway');
        Route::get('/locate', [\App\Http\Controllers\Wms\WmsPortalController::class, 'locate'])->name('locate');

        Route::prefix('data')->name('data.')->group(function () {
            $c = \App\Http\Controllers\Wms\WmsWebDataController::class;
            Route::put('/warehouse/{id}', [$c, 'updateWarehouse'])->name('warehouse.update');
            Route::post('/zones', [$c, 'storeZone'])->name('zones.store');
            Route::put('/zones/{id}', [$c, 'updateZone'])->name('zones.update');
            Route::delete('/zones/{id}', [$c, 'destroyZone'])->name('zones.destroy');
            Route::post('/racks', [$c, 'storeRack'])->name('racks.store');
            Route::put('/racks/{id}', [$c, 'updateRack'])->name('racks.update');
            Route::delete('/racks/{id}', [$c, 'destroyRack'])->name('racks.destroy');
            Route::post('/shelves', [$c, 'storeShelf'])->name('shelves.store');
            Route::put('/shelves/{id}', [$c, 'updateShelf'])->name('shelves.update');
            Route::delete('/shelves/{id}', [$c, 'destroyShelf'])->name('shelves.destroy');
            Route::post('/bins', [$c, 'storeBin'])->name('bins.store');
            Route::put('/bins/{id}', [$c, 'updateBin'])->name('bins.update');
            Route::delete('/bins/{id}', [$c, 'destroyBin'])->name('bins.destroy');
            Route::get('/zones-by-warehouse/{warehouseId}', [$c, 'zonesByWarehouse'])->name('zones.by-warehouse');
            Route::get('/racks-by-zone/{zoneId}', [$c, 'racksByZone'])->name('racks.by-zone');
            Route::get('/shelves-by-rack/{rackId}', [$c, 'shelvesByRack'])->name('shelves.by-rack');
            Route::get('/bins-by-shelf/{shelfId}', [$c, 'binsByShelf'])->name('bins.by-shelf');
            Route::get('/inventory-rows', [$c, 'inventoryRows'])->name('inventory.rows');
            Route::get('/movements', [$c, 'movements'])->name('movements');
            Route::get('/locate', [$c, 'locate'])->name('locate');
            Route::post('/stock/move', [$c, 'stockMove'])->name('stock.move');
            Route::post('/stock/lock', [$c, 'pickLock'])->name('stock.lock');
            Route::post('/stock/unlock', [$c, 'pickUnlock'])->name('stock.unlock');
            Route::get('/scan-lookup', [$c, 'scanLookup'])->name('scan.lookup');
        });
    });

    // =========================================================================
    // REVIEW INTELLIGENCE MASTER SYSTEM
    // =========================================================================
    Route::prefix('reviews')->name('reviews.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Reviews\ReviewMasterController::class, 'index'])->name('index');
        Route::get('/data', [\App\Http\Controllers\Reviews\ReviewMasterController::class, 'getData'])->name('data');
        Route::get('/sku/{sku}/detail', [\App\Http\Controllers\Reviews\ReviewMasterController::class, 'skuDetail'])->name('sku.detail');
        Route::get('/supplier-intelligence', [\App\Http\Controllers\Reviews\ReviewMasterController::class, 'supplierIntelligence'])->name('supplier-intelligence');
        Route::get('/ai-insights', [\App\Http\Controllers\Reviews\ReviewMasterController::class, 'aiInsights'])->name('ai-insights');
        Route::get('/alerts', [\App\Http\Controllers\Reviews\ReviewMasterController::class, 'alerts'])->name('alerts');
        Route::post('/alerts/{id}/resolve', [\App\Http\Controllers\Reviews\ReviewMasterController::class, 'resolveAlert'])->name('alerts.resolve');
        Route::post('/upload-csv', [\App\Http\Controllers\Reviews\ReviewMasterController::class, 'uploadCsv'])->name('upload-csv');
        Route::post('/{id}/generate-reply', [\App\Http\Controllers\Reviews\ReviewMasterController::class, 'generateReply'])->name('generate-reply');
        Route::post('/trigger-fetch', [\App\Http\Controllers\Reviews\ReviewMasterController::class, 'triggerFetch'])->name('trigger-fetch');
        Route::post('/refresh-summary', [\App\Http\Controllers\Reviews\ReviewMasterController::class, 'refreshSummary'])->name('refresh-summary');
    });

    Route::get('', [RoutingController::class, 'index'])->name('root');
    Route::get('{first}/{second}', [RoutingController::class, 'secondLevel'])->name('second');
    Route::get('/.well-known/{file}', function ($file) {
        $allowedFiles = ['assetlinks.json', 'apple-app-site-association', 'com.chrome.devtools.json'];
        if (! in_array($file, $allowedFiles)) {
            abort(404);
        }

        $path = public_path(".well-known/{$file}");
        if (! file_exists($path)) {
            abort(404);
        }

        return response()->file($path);
    })->where('file', '.*');
    Route::get('{first}/{second}/{third}', [RoutingController::class, 'thirdLevel'])->name('third');
    Route::post('/ebay-product-price-update', [EbayDataUpdateController::class, 'updatePrice'])->name('ebay_product_price_update');

    // Amazon Competitor Search Routes

    Route::get('{any}', [RoutingController::class, 'root'])->name('any');

    // Route::post('/auto-stock-balance-store', [AutoStockBalanceController::class, 'store'])->name('autostock.balance.store');
    // Route::get('/auto-stock-balance-data-list', [AutoStockBalanceController::class, 'list']);

});

// Shopify Meta Campaigns Routes (Facebook & Instagram)
Route::prefix('shopify/meta-campaigns')->middleware(['auth'])->group(function () {
    Route::get('/summary', [\App\Http\Controllers\ShopifyMetaCampaignController::class, 'summary'])->name('shopify.meta.campaigns.summary');
    Route::get('/compare/{campaignId}', [\App\Http\Controllers\ShopifyMetaCampaignController::class, 'compare'])->name('shopify.meta.campaigns.compare');
    Route::post('/fetch', [\App\Http\Controllers\ShopifyMetaCampaignController::class, 'fetch'])->name('shopify.meta.campaigns.fetch');
});

// =============================================================================
// STEP 6: STATIC ASSET ROUTES (so /js/* and /css/* never hit Shopify wildcard)
// =============================================================================
Route::get('/js/{path}', function (string $path) {
    $fullPath = public_path('js/'.$path);
    $realPath = $fullPath ? realpath($fullPath) : false;
    $publicRoot = realpath(public_path());
    if (! $realPath || ! is_file($realPath) || ! $publicRoot || ! str_starts_with($realPath, $publicRoot)) {
        abort(404);
    }

    return response()->file($realPath);
})->where('path', '[^/]+');

Route::get('/css/{path}', function (string $path) {
    $fullPath = public_path('css/'.$path);
    $realPath = $fullPath ? realpath($fullPath) : false;
    $publicRoot = realpath(public_path());
    if (! $realPath || ! is_file($realPath) || ! $publicRoot || ! str_starts_with($realPath, $publicRoot)) {
        abort(404);
    }

    return response()->file($realPath);
})->where('path', '[^/]+');

// =============================================================================
// STEP 7: SHOPIFY SPECIFIC ROUTES
// =============================================================================
Route::get('/products/shopify-Products', [ShopifyController::class, 'shopifyView'])
    ->defaults('first', 'products')
    ->defaults('second', 'shopify-Products')
    ->name('shopify');

Route::get('/products/inventory', [ShopifyController::class, 'shopifyView'])
    ->defaults('first', 'products')
    ->defaults('second', 'inventory')
    ->name('products.inventory');

// =============================================================================
// STEP 8: SHOPIFY WILDCARD – MUST BE ABSOLUTELY LAST (catches /{first}/{second} only)
// =============================================================================
Route::get('/{first}/{second}', [ShopifyController::class, 'shopifyView']);
