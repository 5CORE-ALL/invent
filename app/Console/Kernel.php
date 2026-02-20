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
        \App\Console\Commands\ProcessPendingReverbOrders::class,
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
        \App\Console\Commands\UpdateEbayCompetitorPrices::class,
        \App\Console\Commands\UpdateEbaySkuCompetitorPrices::class,
        \App\Console\Commands\UpdateAmazonCompetitorPrices::class,
        \App\Console\Commands\UpdateAmazonSkuCompetitorPrices::class,
    ];

    /**
     * Shared scheduler log path for all commands.
     */
    protected string $schedulerLog;

    /**
     * Boot the Kernel – set up scheduler log path once.
     */
    public function __construct(\Illuminate\Contracts\Foundation\Application $app, \Illuminate\Contracts\Events\Dispatcher $events)
    {
        parent::__construct($app, $events);
        $this->schedulerLog = storage_path('logs/scheduler.log');
    }

    /**
     * Define the application's command schedule.
     *
     * HARDENING RULES:
     * - Every command uses ->withoutOverlapping() to prevent duplicate runs.
     * - Every command uses ->runInBackground() so the scheduler can proceed.
     * - Every command uses ->appendOutputTo() for debugging.
     * - No env() calls – only config() used if needed.
     */
    protected function schedule(Schedule $schedule)
    {
        $log = $this->schedulerLog;

        // Test scheduler to verify it's working
        $schedule->call(function () {
            Log::info('Scheduler heartbeat at ' . now());
        })->everyMinute()->name('scheduler-heartbeat');

        /*
        |--------------------------------------------------------------------------
        | TASK MANAGEMENT
        |--------------------------------------------------------------------------
        */
        $schedule->command('tasks:generate-daily-automated')
            ->dailyAt('00:01')
            ->timezone('Asia/Kolkata')
            ->name('generate-daily-automated-tasks')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('tasks:mark-missed-automated')
            ->twiceDaily(6, 18)
            ->timezone('Asia/Kolkata')
            ->name('mark-missed-automated-tasks')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // Clear Laravel log periodically
        $schedule->call(function () {
            $logPath = storage_path('logs/laravel.log');
            if (file_exists($logPath) && filesize($logPath) > 50 * 1024 * 1024) { // Only if > 50MB
                file_put_contents($logPath, '');
            }
        })->everyFiveMinutes()->name('clear-laravel-log');

        /*
        |--------------------------------------------------------------------------
        | AMAZON SP-API & INVENTORY
        |--------------------------------------------------------------------------
        */
        $schedule->command('amazon:sync-inventory')
            ->everySixHours()
            ->name('amazon-sync-inventory')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:fetch-amazon-listings')
            ->dailyAt('06:00')
            ->timezone('America/Los_Angeles');
        // Reverb: full sync (orders + Shopify→Reverb inventory) every 5 minutes
        $schedule->command('reverb:sync-all')
            ->everyThirtyMinutes()
            ->timezone('UTC')
            ->name('reverb-sync-all')
            ->withoutOverlapping(15);
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
            ->timezone('Asia/Kolkata')
            ->name('amazon-sp-campaign-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:amazon-sb-campaign-reports')
            ->dailyAt('07:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-sb-campaign-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:amazon-sd-campaign-reports')
            ->dailyAt('08:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-sd-campaign-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | AMAZON BIDS / BUDGET AUTO-UPDATE (All at 12:00 IST)
        |--------------------------------------------------------------------------
        */
        $schedule->command('amazon:auto-update-over-kw-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-over-kw-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-over-pt-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-over-pt-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-over-hl-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-over-hl-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-under-kw-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-under-kw-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-under-pt-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-under-pt-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-under-hl-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-under-hl-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-amz-bgt-kw')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-kw')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-amz-bgt-pt')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-pt')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-amz-bgt-hl')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-hl')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-pink-dil-kw-ads')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-pink-dil-kw')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-pink-dil-pt-ads')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-pink-dil-pt')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-pink-dil-hl-ads')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-pink-dil-hl')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | AMAZON FBA
        |--------------------------------------------------------------------------
        */
        $schedule->command('amazon-fba:auto-update-over-kw-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('fba-over-kw-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon-fba:auto-update-under-kw-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('fba-under-kw-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon-fba:auto-update-over-pt-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('fba-over-pt-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon-fba:auto-update-under-pt-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('fba-under-pt-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:fetch-fba-reports')
            ->dailyAt('13:30')
            ->timezone('Asia/Kolkata')
            ->name('fetch-fba-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:fetch-fba-inventory --insert --prices')
            ->dailyAt('14:00')
            ->timezone('Asia/Kolkata')
            ->name('fetch-fba-inventory')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:fetch-fba-monthly-sales')
            ->dailyAt('14:30')
            ->timezone('Asia/Kolkata')
            ->name('fetch-fba-monthly-sales')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('fba:collect-metrics')
            ->dailyAt('23:30')
            ->timezone('UTC')
            ->name('fba-collect-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('fba:sync-shipment-status')
            ->dailyAt('04:00')
            ->timezone('Asia/Kolkata')
            ->name('fba-sync-shipment-status-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-pause-enable-fba-campaigns')
            ->dailyAt('12:30')
            ->timezone('Asia/Kolkata')
            ->name('fba-auto-pause-enable')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('budget:update-amazon-fba-kw')
            ->dailyAt('00:05')
            ->timezone('Asia/Kolkata')
            ->name('budget-fba-kw')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('budget:update-amazon-fba-pt')
            ->dailyAt('00:06')
            ->timezone('Asia/Kolkata')
            ->name('budget-fba-pt')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | AMAZON UTILIZATION & METRICS
        |--------------------------------------------------------------------------
        */
        $schedule->command('amazon:store-utilization-counts')
            ->dailyAt('00:10')
            ->timezone('Asia/Kolkata')
            ->name('amazon-utilization-counts')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon-fba:store-utilization-counts')
            ->dailyAt('00:10')
            ->timezone('Asia/Kolkata')
            ->name('fba-utilization-counts')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:store-listing-daily-metrics')
            ->dailyAt('00:20')
            ->timezone('Asia/Kolkata')
            ->name('amazon-listing-daily-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:collect-metrics')
            ->dailyAt('23:40')
            ->timezone('UTC')
            ->name('amazon-collect-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | EBAY JOBS
        |--------------------------------------------------------------------------
        */
        $schedule->command('app:fetch-ebay-orders')
            ->dailyAt('00:00')
            ->timezone('Asia/Kolkata')
            ->name('fetch-ebay-orders')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:fetch-ebay2-orders')
            ->dailyAt('23:40')
            ->timezone('Asia/Kolkata')
            ->name('fetch-ebay2-orders')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay3:daily --days=60')
            ->dailyAt('01:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:fetch-ebay-reports')
            ->hourly()
            ->timezone('UTC')
            ->name('fetch-ebay-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:fetch-ebay-table-data')
            ->dailyAt('00:00')
            ->timezone('UTC')
            ->name('fetch-ebay-table-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:fetch-ebay-three-metrics')
            ->dailyAt('02:00')
            ->timezone('America/Los_Angeles')
            ->name('fetch-ebay-three-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:fetch-ebay-two-metrics')
            ->dailyAt('01:00')
            ->timezone('America/Los_Angeles')
            ->name('fetch-ebay-two-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // eBay campaign reports
        $schedule->command('app:ebay-campaign-reports')
            ->dailyAt('05:00')
            ->timezone('UTC')
            ->name('ebay-campaign-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:ebay2-campaign-reports')
            ->dailyAt('01:15')
            ->timezone('America/Los_Angeles')
            ->name('ebay2-campaign-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:ebay3-campaign-reports')
            ->dailyAt('04:00')
            ->timezone('America/Los_Angeles')
            ->name('ebay3-campaign-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // eBay bids update
        $schedule->command('ebay:auto-update-over-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay-over-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay:auto-update-under-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay-under-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay2:auto-update-utilized-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-utilized-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay3:auto-update-utilized-bids')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-utilized-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay1:auto-pause-pink-dil-kw-ads')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay1-pink-dil')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay2:auto-pause-pink-dil-kw-ads')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-pink-dil')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay3:auto-pause-pink-dil-kw-ads')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-pink-dil')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay:update-suggestedbid')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay-suggestedbid')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay2:update-suggestedbid')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-suggestedbid')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay3:update-suggestedbid')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-suggestedbid')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay1:update-budget')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay1-budget')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay:collect-metrics')
            ->dailyAt('23:35')
            ->timezone('UTC')
            ->name('ebay-collect-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay:store-utilization-counts')
            ->dailyAt('00:10')
            ->timezone('Asia/Kolkata')
            ->name('ebay-utilization-counts')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | EBAY COMPETITOR PRICE UPDATES (Weekly)
        |--------------------------------------------------------------------------
        */
        $schedule->command('ebay:update-prices')
            ->weekly()
            ->sundays()
            ->at('03:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay-competitor-prices-weekly')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay:update-sku-prices')
            ->weekly()
            ->sundays()
            ->at('06:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay-sku-competitor-prices-weekly')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | AMAZON COMPETITOR PRICE UPDATES (Weekly)
        |--------------------------------------------------------------------------
        */
        $schedule->command('amazon:update-prices')
            ->weekly()
            ->mondays()
            ->at('03:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-competitor-prices-weekly')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:update-sku-prices')
            ->weekly()
            ->mondays()
            ->at('06:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-sku-competitor-prices-weekly')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | GOOGLE ADS & SHOPPING
        |--------------------------------------------------------------------------
        */
        $schedule->command('app:fetch-google-ads-campaigns')
            ->dailyAt('09:00')
            ->timezone('Asia/Kolkata')
            ->name('fetch-google-ads-campaigns')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('sbid:update')
            ->dailyAt('00:01')
            ->timezone('Asia/Kolkata')
            ->name('sbid-update')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('sbid:update-serp')
            ->dailyAt('00:02')
            ->timezone('Asia/Kolkata')
            ->name('sbid-update-serp')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('budget:update-shopping')
            ->dailyAt('00:03')
            ->timezone('Asia/Kolkata')
            ->name('budget-shopping')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('budget:update-serp')
            ->dailyAt('00:04')
            ->timezone('Asia/Kolkata')
            ->name('budget-serp')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('google:store-shopping-utilization-counts')
            ->dailyAt('00:15')
            ->timezone('Asia/Kolkata')
            ->name('google-shopping-utilization')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // Reset SBID status daily
        $schedule->call(function () {
            try {
                DB::connection('apicentral')
                    ->table('google_ads_campaigns')
                    ->where('id', 1)
                    ->update(['sbid_status' => 0]);
            } catch (\Throwable $e) {
                Log::error('Scheduler: Failed to reset sbid_status - ' . $e->getMessage());
            }
        })->dailyAt('00:00')->name('reset-sbid-status');

        /*
        |--------------------------------------------------------------------------
        | META / FACEBOOK ADS
        |--------------------------------------------------------------------------
        */
        $schedule->command('meta:sync-all-ads')
            ->dailyAt('10:00')
            ->timezone('Asia/Kolkata')
            ->name('meta-ads-sync-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('meta-ads:sync')
            ->dailyAt('11:00')
            ->timezone('Asia/Kolkata')
            ->name('meta-ads-manager-full-sync')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('meta-ads:sync --insights-only')
            ->dailyAt('02:00')
            ->timezone('UTC')
            ->name('meta-ads-manager-insights-sync')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('meta-ads:run-automation')
            ->dailyAt('03:00')
            ->timezone('UTC')
            ->name('meta-ads-automation-rules')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // Shopify Meta Campaigns
        $schedule->command('shopify:fetch-meta-campaigns --channel=both')
            ->dailyAt('02:00')
            ->timezone('America/Los_Angeles')
            ->name('fetch-shopify-fb-campaigns-7-30-60-days')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('meta:sync-all-ads')
            ->dailyAt('03:00')
            ->timezone('America/Los_Angeles')
            ->name('sync-meta-all-ads-from-google-sheets')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | WALMART
        |--------------------------------------------------------------------------
        */
        $schedule->command('walmart:daily --days=60')
            ->dailyAt('01:20')
            ->timezone('Asia/Kolkata')
            ->name('walmart-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('walmart:pricing-sales')
            ->cron('0 */3 * * *')
            ->timezone('America/Los_Angeles')
            ->name('walmart-pricing-sales')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('walmart:fetch-inventory')
            ->cron('30 */4 * * *')
            ->timezone('America/Los_Angeles')
            ->name('walmart-inventory')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('walmart:collect-metrics')
            ->dailyAt('23:45')
            ->timezone('UTC')
            ->name('walmart-collect-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('sync:walmart-ad-sheet-data')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('walmart-ad-sheet-sync')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | SHOPIFY
        |--------------------------------------------------------------------------
        */
        $schedule->command('shopify:save-daily-inventory')
            ->everyFiveMinutes()
            ->timezone('UTC')
            ->name('shopify-save-daily-inventory')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:sync-shopify-all-channels-data')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('sync-shopify-all-channels')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('sync:shopify-quantity')
            ->twiceDaily(1, 13)
            ->name('sync-shopify-quantity')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:fetch-shopify-b2b-metrics --days=60')
            ->twiceDaily(2, 14)
            ->name('shopify-b2b-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:fetch-shopify-b2c-metrics --days=60')
            ->twiceDaily(2, 14)
            ->name('shopify-b2c-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | WAYFAIR
        |--------------------------------------------------------------------------
        */
        $schedule->command('wayfair:daily --days=60')
            ->dailyAt('01:30')
            ->timezone('Asia/Kolkata')
            ->name('wayfair-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('sync:wayfair-l30-api')
            ->dailyAt('13:00')
            ->timezone('Asia/Kolkata')
            ->name('wayfair-api-sync-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | REVERB
        |--------------------------------------------------------------------------
        */
        $schedule->command('reverb:fetch')
            ->everyFiveMinutes()
            ->timezone('UTC')
            ->name('reverb-fetch')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('reverb:daily --days=60')
            ->dailyAt('01:10')
            ->timezone('Asia/Kolkata')
            ->name('reverb-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | MACY
        |--------------------------------------------------------------------------
        */
        $schedule->command('app:fetch-macy-products')
            ->everyFiveMinutes()
            ->timezone('UTC')
            ->name('fetch-macy-products')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | WAYFAIR DATA
        |--------------------------------------------------------------------------
        */
        $schedule->command('app:fetch-wayfair-data')
            ->everyFiveMinutes()
            ->timezone('UTC')
            ->name('fetch-wayfair-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | MIRAKL
        |--------------------------------------------------------------------------
        */
        $schedule->command('mirakl:daily --days=60')
            ->dailyAt('01:40')
            ->timezone('Asia/Kolkata')
            ->name('mirakl-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | TEMU
        |--------------------------------------------------------------------------
        */
        $schedule->command('app:fetch-temu-metrics')
            ->dailyAt('03:00')
            ->timezone('America/Los_Angeles')
            ->name('fetch-temu-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('temu:fetch-ads-data --period=L30')
            ->dailyAt('04:00')
            ->timezone('America/Los_Angeles')
            ->name('temu-ads-data-sync-l30')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('temu:fetch-ads-data --period=L60')
            ->dailyAt('05:00')
            ->timezone('America/Los_Angeles')
            ->name('temu-ads-data-sync-l60')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | DOBA
        |--------------------------------------------------------------------------
        */
        $schedule->command('doba:daily --days=60')
            ->dailyAt('01:50')
            ->timezone('Asia/Kolkata')
            ->name('doba-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:fetch-doba-metrics')
            ->dailyAt('02:00')
            ->timezone('Asia/Kolkata')
            ->name('doba-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | SHEET SYNCS (Various marketplaces)
        |--------------------------------------------------------------------------
        */
        $schedule->command('app:sync-sheet')
            ->dailyAt('02:10')
            ->timezone('UTC')
            ->name('sync-main-sheet')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:sync-mercari-w-ship-sheet')
            ->dailyAt('02:30')
            ->timezone('UTC')
            ->name('sync-mercari-w-ship')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:sync-mercari-wo-ship-sheet')
            ->dailyAt('03:00')
            ->timezone('UTC')
            ->name('sync-mercari-wo-ship')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:sync-fb-shop-sheet')
            ->dailyAt('03:00')
            ->timezone('UTC')
            ->name('sync-fb-shop')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:sync-fb-marketplace-sheet')
            ->dailyAt('03:00')
            ->timezone('UTC')
            ->name('sync-fb-marketplace')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:fetch-pls-data')
            ->twiceDaily(1, 13)
            ->name('fetch-pls-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('sync:neweegg-sheet')
            ->twiceDaily(1, 13)
            ->name('sync-newegg-sheet')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('sync:shein-sheet')
            ->twiceDaily(1, 13)
            ->name('sync-shein-sheet')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('sync:walmart-sheet')
            ->twiceDaily(1, 13)
            ->name('sync-walmart-sheet')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('sync:temu-sheet-data')
            ->twiceDaily(1, 13)
            ->name('sync-temu-sheet')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:sync-cp-master-to-sheet')
            ->hourly()
            ->name('sync-cp-master-sheet')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:process-jungle-scout-sheet-data')
            ->dailyAt('00:30')
            ->timezone('America/Los_Angeles')
            ->name('jungle-scout-sheet')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | HIGH-FREQUENCY SYNCS (every minute / 5 minutes)
        |--------------------------------------------------------------------------
        */
        $schedule->command('sync:amazon-prices')
            ->everyMinute()
            ->name('sync-amazon-prices')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('sync:sync-temu-sip')
            ->everyMinute()
            ->name('sync-temu-sip')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('sync:walmart-metrics-data')
            ->everyMinute()
            ->name('sync-walmart-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('sync:tiktok-sheet-data')
            ->everyMinute()
            ->name('sync-tiktok-sheet')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:aliexpress-sheet-sync')
            ->everyMinute()
            ->name('aliexpress-sheet-sync')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('app:update-marketplace-daily-metrics')
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('update-marketplace-daily-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // Advertisement Masters Cron
        $schedule->command('adv:run-masters-cron')
            ->everyFiveMinutes()
            ->timezone('UTC')
            ->name('advertisement-masters-cron')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | TIKTOK
        |--------------------------------------------------------------------------
        */
        $schedule->command('sync:tiktok-api-data')
            ->daily()
            ->name('sync-tiktok-api-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | MOVEMENT ANALYSIS & STOCK
        |--------------------------------------------------------------------------
        */
        $schedule->command('movement:generate')
            ->dailyAt('12:00')
            ->timezone('Asia/Kolkata')
            ->name('movement-analysis')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('stock:update-mapping-daily')
            ->dailyAt('01:00')
            ->timezone('America/Los_Angeles')
            ->name('stock-mapping-daily-update')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | AUTO LOGOUT INACTIVE USERS
        |--------------------------------------------------------------------------
        */
        $schedule->command('users:auto-logout')
            ->everySixHours()
            ->timezone('Asia/Kolkata')
            ->name('auto-logout-users')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);
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
