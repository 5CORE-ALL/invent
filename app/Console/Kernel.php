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
        \App\Console\Commands\EbayPinkDilKwBidsAutoUpdate::class,
        \App\Console\Commands\EbayPriceLessBidsAutoUpdate::class,
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

        // Clear Laravel log after test log
        $schedule->call(function () {
            $logPath = storage_path('logs/laravel.log');
            if (file_exists($logPath)) {
                file_put_contents($logPath, '');
            }
        })->everyFiveMinutes()->name('clear-laravel-log');

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
        $schedule->command('app:ebay-campaign-reports')
            ->dailyAt('05:00')
            ->timezone('UTC');
        $schedule->command('app:fetch-doba-metrics')
            ->dailyAt('00:00')
            ->timezone('UTC');
        
        // Collect FBA metrics for historical tracking
        $schedule->command('fba:collect-metrics')
            ->dailyAt('23:30')
            ->timezone('UTC');

        // Collect eBay metrics for historical tracking
        $schedule->command('ebay:collect-metrics')
            ->dailyAt('23:35')
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
        $schedule->command('sync:wayfair-sheet')->twiceDaily(2, 14);


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
        $schedule->command('ebay:auto-update-pink-dil-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata');
        $schedule->command('ebay:auto-update-price-less-bids')
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
        // end of bids update commands
        $schedule->command('sync:amazon-prices')->everyMinute();
        $schedule->command('sync:sync-temu-sip')->everyMinute();
        $schedule->command('sync:walmart-metrics-data')->everyMinute();
        $schedule->command('sync:tiktok-sheet-data')->everyMinute();
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

        $schedule->command('app:sync-cp-master-to-sheet')->hourly();
                
        // FBA Commands - Daily Updates
        $schedule->command('app:fetch-fba-reports')
            ->dailyAt('01:00')
            ->timezone('America/Los_Angeles');
        $schedule->command('app:fetch-fba-inventory --insert --prices')
            ->dailyAt('01:30')
            ->timezone('America/Los_Angeles');
        $schedule->command('app:fetch-fba-monthly-sales')
            ->dailyAt('02:00')
            ->timezone('America/Los_Angeles');

        $schedule->command('fba:sync-ship-calculations')
            ->dailyAt('03:00')
            ->timezone('America/Los_Angeles');

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

        // Shopify Facebook Campaigns - Fetch daily at 2 AM PST
        $schedule->command('shopify:fetch-facebook-campaigns')
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

            // test scheduler for task manager report
//               $schedule->call(function () {
//     \Illuminate\Support\Facades\Log::info('Test Scheduler Executed at ' . now());

//     // Optional: visible for testing
//     file_put_contents(storage_path('logs/test_scheduler_output.txt'), now() . " - Test scheduler executed\n", FILE_APPEND);
// })
// ->everyMinute()
// ->name('test-scheduler')
// ->withoutOverlapping();
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
 