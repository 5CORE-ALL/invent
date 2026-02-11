<?php

namespace App\Console;

use App\Console\Commands\AmazonSbCampaignReports;
use App\Console\Commands\AmazonSdCampaignReports;
use App\Console\Commands\AmazonSpCampaignReports;
use App\Console\Commands\FetchGoogleAdsCampaigns;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\FetchReverbData;
use App\Console\Commands\FetchMacyProducts;
use App\Console\Commands\FetchWayfairData;
use App\Console\Commands\SyncFbMarketplaceSheet;
use App\Console\Commands\SyncFbShopSheet;
use App\Console\Commands\SyncMercariWoShipSheet;
use App\Console\Commands\SyncMercariWShipSheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Console\Commands\FetchMiraklDailyData;
use App\Console\Commands\FetchEbay3DailyData;
use App\Console\Commands\FetchReverbDailyData;
use App\Console\Commands\FetchWalmartDailyData;
use App\Console\Commands\FetchWayfairDailyData;
use App\Console\Commands\FetchShopifyB2BMetrics;
use App\Console\Commands\FetchShopifyB2CMetrics;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        FetchReverbData::class,
        FetchMacyProducts::class,
        FetchWayfairData::class,
        SyncFbMarketplaceSheet::class,
        SyncFbShopSheet::class,
        SyncMercariWShipSheet::class,
        SyncMercariWoShipSheet::class,
        \App\Console\Commands\LogClear::class,
        \App\Console\Commands\SyncTemuSheet::class,
        \App\Console\Commands\AutoUpdateAmazonKwBids::class,
        \App\Console\Commands\AutoUpdateAmazonPtBids::class,
        \App\Console\Commands\AutoUpdateAmazonHlBids::class,
        \App\Console\Commands\AutoUpdateAmzUnderKwBids::class,
        \App\Console\Commands\AutoUpdateAmzUnderPtBids::class,
        \App\Console\Commands\AutoUpdateAmzUnderHlBids::class,
        \App\Console\Commands\AutoUpdateAmazonBgtKw::class,
        \App\Console\Commands\AutoUpdateAmazonBgtPt::class,
        \App\Console\Commands\AutoUpdateAmazonBgtHl::class,
        \App\Console\Commands\AutoUpdateAmazonPinkDilKwAds::class,
        \App\Console\Commands\AutoUpdateAmazonPinkDilPtAds::class,
        \App\Console\Commands\AutoUpdateAmazonPinkDilHlAds::class,
        \App\Console\Commands\EbayOverUtilzBidsAutoUpdate::class,
        \App\Console\Commands\Ebay2UtilizedBidsAutoUpdate::class,
        \App\Console\Commands\Ebay3UtilizedBidsAutoUpdate::class,
        \App\Console\Commands\Ebay1PausePinkDilKwAds::class,
        \App\Console\Commands\Ebay2PausePinkDilKwAds::class,
        \App\Console\Commands\Ebay3PausePinkDilKwAds::class,
        \App\Console\Commands\UpdateEbayOneBudget::class,
        \App\Console\Commands\AutoUpdateAmazonFbaOverKwBids::class,
        \App\Console\Commands\AutoUpdateAmazonFbaUnderKwBids::class,
        \App\Console\Commands\AutoUpdateAmazonFbaOverPtBids::class,
        \App\Console\Commands\AutoUpdateAmazonFbaUnderPtBids::class,
        \App\Console\Commands\GenerateMovementAnalysis::class,
        \App\Console\Commands\UpdateEbaySuggestedBid::class,
        \App\Console\Commands\UpdateStockMappingDaily::class,
        \App\Console\Commands\SyncShopifyAllChannelsData::class,
        AmazonSpCampaignReports::class,
        AmazonSbCampaignReports::class,
        AmazonSdCampaignReports::class,
        FetchGoogleAdsCampaigns::class,
        \App\Console\Commands\SyncMetaAllAds::class,
        \App\Console\Commands\MetaAdsSyncCommand::class,
        \App\Console\Commands\MetaAdsImportRawCommand::class,
        \App\Console\Commands\MetaAdsAutomationCommand::class,
        \App\Console\Commands\MetaAdsSyncAdsCommand::class,
        \App\Console\Commands\MetaAdsProcessQueue::class,
        \App\Console\Commands\MetaAdsProcessQueuePriority::class,
        \App\Console\Commands\SyncFbaShipmentStatus::class,
        \App\Console\Commands\StoreAmazonUtilizationCounts::class,
        \App\Console\Commands\StoreAmazonFbaUtilizationCounts::class,
        \App\Console\Commands\StoreEbayUtilizationCounts::class,
        \App\Console\Commands\StoreGoogleShoppingUtilizationCounts::class,
        FetchMiraklDailyData::class,
        FetchEbay3DailyData::class,
        FetchReverbDailyData::class,
        FetchWalmartDailyData::class,
        FetchWayfairDailyData::class,
        FetchShopifyB2BMetrics::class,
        FetchShopifyB2CMetrics::class,
        \App\Console\Commands\RunAdvMastersCron::class,
        \App\Console\Commands\CollectWalmartMetrics::class,

    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // Test scheduler to verify it's working
        $schedule->call(function () {
            Log::info('Test scheduler is working at ' . now());
        })->everyMinute()->name('test-scheduler-log');

        // Generate Daily Automated Tasks - Run once daily at 12:01 AM IST
        $schedule->command('tasks:generate-daily-automated')
            ->dailyAt('00:01')
            ->timezone('Asia/Kolkata')
            ->name('generate-daily-automated-tasks')
            ->withoutOverlapping();

        // Mark Missed Automated Tasks - Run twice daily at 6 AM and 6 PM IST
        $schedule->command('tasks:mark-missed-automated')
            ->twiceDaily(6, 18)  // Runs at 6:00 AM and 6:00 PM
            ->timezone('Asia/Kolkata')
            ->name('mark-missed-automated-tasks')
            ->withoutOverlapping();

        // Clear Laravel log after test log
        $schedule->call(function () {
            $logPath = storage_path('logs/laravel.log');
            if (file_exists($logPath)) {
                file_put_contents($logPath, '');
            }
        })->everyFiveMinutes()->name('clear-laravel-log');

$schedule->command('amazon:sync-inventory')->everySixHours();
        // All commands running every 5 minutes
        $schedule->command('shopify:save-daily-inventory')
            ->everyFiveMinutes()
            ->timezone('UTC');
        $schedule->command('app:process-jungle-scout-sheet-data')
            ->dailyAt('00:30')
            ->timezone('America/Los_Angeles');
        $schedule->command('app:fetch-amazon-listings')
            ->dailyAt('06:00')
            ->timezone('America/Los_Angeles');
        $schedule->command('reverb:fetch')
            ->everyFiveMinutes()
            ->timezone('UTC');
        $schedule->command('app:fetch-ebay-reports')
            ->hourly()
            ->timezone('UTC');
        $schedule->command('app:fetch-macy-products')
            ->everyFiveMinutes()
            ->timezone('UTC');
        $schedule->command('app:fetch-wayfair-data')
            ->everyFiveMinutes()
            ->timezone('UTC');
        // $schedule->command('app:amazon-campaign-reports')
        //     ->dailyAt('04:00')
        //     ->timezone('America/Los_Angeles');
        $schedule->command('app:amazon-sp-campaign-reports')
            ->dailyAt('06:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('app:amazon-sb-campaign-reports')
            ->dailyAt('07:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('app:amazon-sd-campaign-reports')
            ->dailyAt('08:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('app:fetch-google-ads-campaigns')
            ->dailyAt('09:00')
            ->timezone('Asia/Kolkata');

        // Sync Meta (Facebook & Instagram) Ads data from Meta API
        $schedule->command('meta:sync-all-ads')
            ->dailyAt('10:00')
            ->timezone('Asia/Kolkata')
            ->name('meta-ads-sync-daily')
            ->withoutOverlapping();

        // Meta Ads Manager - Full sync (entities + insights)
        $schedule->command('meta-ads:sync')
            ->dailyAt('11:00')
            ->timezone('Asia/Kolkata')
            ->name('meta-ads-manager-full-sync')
            ->withoutOverlapping();

        // Meta Ads Manager - Daily insights sync (faster, runs more frequently)
        $schedule->command('meta-ads:sync --insights-only')
            ->dailyAt('02:00')
            ->timezone('UTC')
            ->name('meta-ads-manager-insights-sync')
            ->withoutOverlapping();

        // Meta Ads Manager - Automation rules execution (runs after insights sync)
        $schedule->command('meta-ads:run-automation')
            ->dailyAt('03:00')
            ->timezone('UTC')
            ->name('meta-ads-automation-rules')
            ->withoutOverlapping();

        $schedule->command('app:ebay-campaign-reports')
            ->dailyAt('05:00')
            ->timezone('UTC');
        // Doba Daily Sales Data - Fetch last 60 days (runs first)
        $schedule->command('doba:daily --days=60')
            ->dailyAt('01:50')
            ->timezone('Asia/Kolkata')
            ->name('doba-daily')
            ->withoutOverlapping();

        // Doba Metrics - Calculate L30/L60 from doba_daily_data (runs after doba:daily)
        $schedule->command('app:fetch-doba-metrics')
            ->dailyAt('02:00')
            ->timezone('Asia/Kolkata')
            ->name('doba-metrics')
            ->withoutOverlapping();

        // Collect FBA metrics for historical tracking




        // Collect eBay metrics for historical tracking
        $schedule->command('ebay:collect-metrics')
            ->dailyAt('23:35')
            ->timezone('UTC');

        // Collect Walmart metrics for historical tracking
        $schedule->command('walmart:collect-metrics')
            ->dailyAt('23:45')
            ->timezone('UTC');

        // Collect Amazon metrics for historical tracking
        $schedule->command('amazon:collect-metrics')
            ->dailyAt('23:40')
            ->timezone('UTC');

        // Sync Main sheet update command
        $schedule->command('app:sync-sheet')
            ->dailyAt('02:10')
            ->timezone('UTC');


        // Sync mercari-w-ship sheet update command
        $schedule->command('app:sync-mercari-w-ship-sheet')
            ->dailyAt('02:30')
            ->timezone('UTC');

        // Sync mercari-wo-ship sheet update command
        $schedule->command('app:sync-mercari-wo-ship-sheet')
            ->dailyAt('03:00')
            ->timezone('UTC');

        // Sync fbshop sheet update command
        $schedule->command('app:sync-fb-shop-sheet')
            ->dailyAt('03:00')
            ->timezone('UTC');

        // Sync fb marketplace sheet update command
        $schedule->command('app:sync-fb-marketplace-sheet')
            ->dailyAt('03:00')
            ->timezone('UTC');
        // Sync Temu sheet command


        $schedule->command('app:fetch-pls-data')->twiceDaily(1, 13);

        $schedule->command('sync:neweegg-sheet')->twiceDaily(1, 13);
        // Wayfair sheet sync disabled - using API instead
        // $schedule->command('sync:wayfair-sheet')->twiceDaily(2, 14);

        // Wayfair L30/L60 sync from API - runs daily at 1 PM (13:00) America/Los_Angeles timezone
        $schedule->command('sync:wayfair-l30-api')
            ->dailyAt('13:00')
            ->timezone('Asia/Kolkata')
            ->name('wayfair-api-sync-daily')
            ->withoutOverlapping();


        $schedule->command('sync:shein-sheet')->twiceDaily(1, 13);

        // Sync Walmart sheet command
        $schedule->command('sync:walmart-sheet')->twiceDaily(1, 13);
        $schedule->command('sync:temu-sheet-data')->twiceDaily(1, 13);



        // Sync Shopify sheet command
        $schedule->command('sync:shopify-quantity')->twiceDaily(1, 13);


        $schedule->command('app:fetch-ebay-three-metrics')
            ->dailyAt('02:00')
            ->timezone('America/Los_Angeles');

        $schedule->command('app:ebay3-campaign-reports')
            ->dailyAt('04:00')
            ->timezone('America/Los_Angeles');
        $schedule->command('app:fetch-temu-metrics')
            ->dailyAt('03:00')
            ->timezone('America/Los_Angeles');

        // Fetch Temu Ads Data - L30 period
        $schedule->command('temu:fetch-ads-data --period=L30')
            ->dailyAt('04:00')
            ->timezone('America/Los_Angeles')
            ->name('temu-ads-data-sync-l30')
            ->withoutOverlapping();

        // Fetch Temu Ads Data - L60 period
        $schedule->command('temu:fetch-ads-data --period=L60')
            ->dailyAt('05:00')
            ->timezone('America/Los_Angeles')
            ->name('temu-ads-data-sync-l60')
            ->withoutOverlapping();
        $schedule->command('app:fetch-ebay-two-metrics')
            ->dailyAt('01:00')
            ->timezone('America/Los_Angeles');
        $schedule->command('app:ebay2-campaign-reports')
            ->dailyAt('01:15')
            ->timezone('America/Los_Angeles');
        // Amazon over and under utilized bids update commands
        $schedule->command('amazon:auto-update-over-kw-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('amazon:auto-update-over-pt-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('amazon:auto-update-over-hl-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('amazon:auto-update-under-kw-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('amazon:auto-update-under-pt-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('amazon:auto-update-under-hl-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        // amazon acos bgt update commands
        $schedule->command('amazon:auto-update-amz-bgt-kw')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('amazon:auto-update-amz-bgt-pt')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('amazon:auto-update-amz-bgt-hl')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        // Pink Dil ads update command
        $schedule->command('amazon:auto-update-pink-dil-kw-ads')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('amazon:auto-update-pink-dil-pt-ads')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('amazon:auto-update-pink-dil-hl-ads')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        // FBA bids update command

        $schedule->command('amazon-fba:auto-update-over-kw-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('amazon-fba:auto-update-under-kw-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('amazon-fba:auto-update-over-pt-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('amazon-fba:auto-update-under-pt-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');

        // Ebay bids update command
        $schedule->command('ebay:auto-update-over-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('ebay:auto-update-under-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('ebay2:auto-update-utilized-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('ebay3:auto-update-utilized-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('ebay1:auto-pause-pink-dil-kw-ads')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        
        $schedule->command('ebay2:auto-pause-pink-dil-kw-ads')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        
        $schedule->command('ebay3:auto-pause-pink-dil-kw-ads')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('ebay:update-suggestedbid')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('ebay2:update-suggestedbid')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('ebay3:update-suggestedbid')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('ebay1:update-budget')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        // Walmart ad sheet sync command
        $schedule->command('sync:walmart-ad-sheet-data')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        // end of bids update commands
        $schedule->command('sync:amazon-prices')->everyMinute();
        $schedule->command('sync:sync-temu-sip')->everyMinute();
        $schedule->command('sync:walmart-metrics-data')->everyMinute();
        $schedule->command('sync:tiktok-sheet-data')->everyMinute();
        $schedule->command('sync:tiktok-api-data')->daily(); // Sync TikTok API data (price, stock, views) once daily
        $schedule->command('app:aliexpress-sheet-sync')->everyMinute();
        $schedule->command('app:fetch-ebay-table-data')->dailyAt('00:00');
        $schedule->call(function () {
            DB::connection('apicentral')
                ->table('google_ads_campaigns')
                ->where('id', 1)
                ->update(['sbid_status' => 0]);
        })->dailyAt('00:00');
        $schedule->command('sbid:update')
            ->dailyAt('00:01')
            ->timezone('Asia/Kolkata');

        // Advertisement Masters Cron - Run every 5 minutes
        $schedule->command('adv:run-masters-cron')
            ->everyFiveMinutes()
            ->timezone('UTC')
            ->name('advertisement-masters-cron')
            ->withoutOverlapping();

        // SERP (SEARCH) SBID Update - runs after SHOPPING SBID update
        $schedule->command('sbid:update-serp')
            ->dailyAt('00:02')
            ->timezone('Asia/Kolkata');

        // SHOPPING Budget Update - based on ACOS (L30 data)
        $schedule->command('budget:update-shopping')
            ->dailyAt('00:03')
            ->timezone('Asia/Kolkata');

        // SERP (SEARCH) Budget Update - based on ACOS (L30 data)
        $schedule->command('budget:update-serp')
            ->dailyAt('00:04')
            ->timezone('Asia/Kolkata');

        // Store Amazon Utilization Counts - Daily
        $schedule->command('amazon:store-utilization-counts')
            ->dailyAt('00:10')
            ->timezone('Asia/Kolkata');

        $schedule->command('google:store-shopping-utilization-counts')
            ->dailyAt('00:15')
            ->timezone('Asia/Kolkata');

        // Store Amazon FBA Utilization Counts - Daily
        $schedule->command('amazon-fba:store-utilization-counts')
            ->dailyAt('00:10')
            ->timezone('Asia/Kolkata');

        // Store eBay Utilization Counts - Daily
        $schedule->command('ebay:store-utilization-counts')
            ->dailyAt('00:10')
            ->timezone('Asia/Kolkata');

        // Store Amazon Listing Daily Metrics (Missing & INV>0 count) - Daily
        $schedule->command('amazon:store-listing-daily-metrics')
            ->dailyAt('00:20')
            ->timezone('Asia/Kolkata');

        // Amazon FBA Keyword Budget Update - based on ACOS (L30 data)
        $schedule->command('budget:update-amazon-fba-kw')
            ->dailyAt('00:05')
            ->timezone('Asia/Kolkata');

        // Amazon FBA Product Target Budget Update - based on ACOS (L30 data)
        $schedule->command('budget:update-amazon-fba-pt')
            ->dailyAt('00:06')
            ->timezone('Asia/Kolkata');

        $schedule->command('app:sync-cp-master-to-sheet')->hourly();
        // FBA Commands - Daily Updates (IST)

        $schedule->command('app:fetch-fba-reports')
            ->dailyAt('13:30'); // 1:30 PM IST

        $schedule->command('app:fetch-fba-inventory --insert --prices')
            ->dailyAt('14:00'); // 2:00 PM IST

        $schedule->command('app:fetch-fba-monthly-sales')
            ->dailyAt('14:30'); // 2:30 PM IST

        $schedule->command('fba:collect-metrics')
            ->dailyAt('23:30'); // 11:30 PM IST

        // Sync FBA Shipment Status - Daily 4 AM IST
        $schedule->command('fba:sync-shipment-status')
            ->dailyAt('04:00')
            ->name('fba-sync-shipment-status-daily')
            ->withoutOverlapping();


        $schedule->command('app:sync-shopify-all-channels-data')->dailyAt('12:00')->timezone('Asia/Kolkata');
        // Movement Analysis Command for Shopify Order Items (apicentral database)
        $schedule->command('movement:generate')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');

        // Stock Mapping Daily Update with ±1% tolerance (runs automatically for all platforms)
        $schedule->command('stock:update-mapping-daily')
            ->dailyAt('01:00')
            ->timezone('America/Los_Angeles')
            ->name('stock-mapping-daily-update')
            ->withoutOverlapping();

        // Shopify Meta Campaigns (Facebook & Instagram) - Fetch daily at 2 AM PST
        $schedule->command('shopify:fetch-meta-campaigns --channel=both')
            ->dailyAt('02:00')
            ->timezone('America/Los_Angeles')
            ->name('fetch-shopify-fb-campaigns-7-30-60-days')
            ->withoutOverlapping();

        // Meta All Ads - Sync from Google Sheets daily at 3 AM PST
        $schedule->command('meta:sync-all-ads')
            ->dailyAt('03:00')
            ->timezone('America/Los_Angeles')
            ->name('sync-meta-all-ads-from-google-sheets')
            ->withoutOverlapping();




        /*
    |--------------------------------------------------------------------------
    | AMAZON JOBS (IST)
    |--------------------------------------------------------------------------
    */

        // Update Amazon order periods (L30 / L60) – 12:10 AM IST
        $schedule->command('app:fetch-amazon-orders --update-periods')
            ->dailyAt('00:10')
            ->timezone('Asia/Kolkata')
            ->name('update-amazon-order-periods');

        // Fetch new Amazon orders – 12:15 AM IST
        $schedule->command('app:fetch-amazon-orders --new-only --limit=300')
            ->dailyAt('00:15')
            ->timezone('Asia/Kolkata')
            ->name('fetch-new-amazon-orders');

        // Fetch missing Amazon order items – 12:30 AM IST
        $schedule->command('app:fetch-amazon-orders --fetch-missing-items')
            ->dailyAt('00:30')
            ->timezone('Asia/Kolkata')
            ->name('fetch-missing-amazon-order-items');

        // Full Amazon sync – 12:00 AM IST
        $schedule->command('app:fetch-amazon-orders')
            ->dailyAt('00:00')
            ->timezone('Asia/Kolkata')
            ->name('fetch-amazon-orders');

        $schedule->command('app:update-marketplace-daily-metrics')
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('update-marketplace-daily-metrics')
            ->withoutOverlapping();
        /*
    |--------------------------------------------------------------------------
    | EBAY JOBS (IST)
    |--------------------------------------------------------------------------
    */

        // eBay legacy
        $schedule->command('app:fetch-ebay-orders')
            ->dailyAt('00:00')
            ->timezone('Asia/Kolkata')
            ->name('fetch-ebay-orders');

        // eBay v2
        $schedule->command('app:fetch-ebay2-orders')
            ->dailyAt('23:40')
            ->timezone('Asia/Kolkata')
            ->name('fetch-ebay2-orders');

        // eBay v3 (Last 60 Days)
        $schedule->command('ebay3:daily --days=60')
            ->dailyAt('01:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-daily');


        /*
    |--------------------------------------------------------------------------
    | OTHER MARKETPLACES (IST)
    |--------------------------------------------------------------------------
    */

        // Reverb
        $schedule->command('reverb:daily --days=60')
            ->dailyAt('01:10')
            ->timezone('Asia/Kolkata')
            ->name('reverb-daily');

        // Walmart - Optimized schedule to avoid rate limits
        
        // Walmart Orders - Daily (existing)
        $schedule->command('walmart:daily --days=60')
            ->dailyAt('01:20')
            ->timezone('Asia/Kolkata')
            ->name('walmart-daily')
            ->withoutOverlapping();
        
        // Walmart Pricing & Listing Quality - Every 3 hours (conservative)
        $schedule->command('walmart:pricing-sales')
            ->cron('0 */3 * * *')  // 00:00, 03:00, 06:00, 09:00, 12:00, 15:00, 18:00, 21:00
            ->timezone('America/Los_Angeles')
            ->name('walmart-pricing-sales')
            ->withoutOverlapping();
        
        // Walmart Inventory - Every 4 hours (offset from pricing)
        $schedule->command('walmart:fetch-inventory')
            ->cron('30 */4 * * *')  // 00:30, 04:30, 08:30, 12:30, 16:30, 20:30
            ->timezone('America/Los_Angeles')
            ->name('walmart-inventory')
            ->withoutOverlapping();

        // Wayfair
        $schedule->command('wayfair:daily --days=60')
            ->dailyAt('01:30')
            ->timezone('Asia/Kolkata')
            ->name('wayfair-daily');

        // Mirakl
        $schedule->command('mirakl:daily --days=60')
            ->dailyAt('01:40')
            ->timezone('Asia/Kolkata')
            ->name('mirakl-daily');


        /*
    |--------------------------------------------------------------------------
    | SHOPIFY B2B METRICS (IST)
    |--------------------------------------------------------------------------
    */

        $schedule->command('app:fetch-shopify-b2b-metrics --days=60')
            ->twiceDaily(2, 14)
            ->withoutOverlapping()
            ->name('shopify-b2b-metrics');

        $schedule->command('app:fetch-shopify-b2c-metrics --days=60')
            ->twiceDaily(2, 14)
            ->withoutOverlapping()
            ->name('shopify-b2c-metrics');

        /*
    |--------------------------------------------------------------------------
    | AUTO LOGOUT INACTIVE USERS (Every 6 Hours)
    |--------------------------------------------------------------------------
    */
        $schedule->command('users:auto-logout')
            ->everySixHours()
            ->timezone('Asia/Kolkata')
            ->name('auto-logout-users')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
