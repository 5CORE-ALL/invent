<?php

namespace App\Console;

use App\Console\Commands\AmazonSbCampaignReports;
use App\Console\Commands\AmazonSdCampaignReports;
use App\Console\Commands\AmazonSpCampaignReports;
use App\Console\Commands\AmazonSpKeywordReports;
use App\Console\Commands\AmazonSpNegativeKeywords;
use App\Console\Commands\FetchGoogleAdsCampaigns;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\CheckReverbListings;
use App\Console\Commands\FetchReverbData;
use App\Console\Commands\RelistReverbProducts;
use App\Console\Commands\SyncReverbListingStatuses;
use App\Console\Commands\SyncReverbCommand;
use App\Console\Commands\SyncShopifyCatalogCommand;
use App\Console\Commands\SyncShopifyPlsCatalogCommand;
use App\Console\Commands\DebugEbaySkuMetricsCommand;
use App\Console\Commands\FetchTopDawgData;
use App\Console\Commands\SyncTopDawgAll;
use App\Console\Commands\FetchMacyProducts;
use App\Console\Commands\SyncPurchasingPowerCommand;
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
use App\Console\Commands\FetchWayfairDailyData;
use App\Console\Commands\FetchShopifyB2BMetrics;
use App\Console\Commands\FetchShopifyB2CMetrics;
use App\Console\Commands\FetchShopifyProductViews;
use App\Console\Commands\SyncShopifyLiveInventory;
use App\Jobs\Crm\SendFollowUpReminderJob;
use App\Models\Crm\FollowUp;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        CheckReverbListings::class,
        FetchReverbData::class,
        RelistReverbProducts::class,
        SyncReverbListingStatuses::class,
        SyncReverbCommand::class,
        SyncShopifyCatalogCommand::class,
        SyncShopifyPlsCatalogCommand::class,
        SyncShopifyLiveInventory::class,
        DebugEbaySkuMetricsCommand::class,
        FetchTopDawgData::class,
        SyncTopDawgAll::class,
        \App\Console\Commands\ProcessPendingReverbOrders::class,
        FetchMacyProducts::class,
        SyncPurchasingPowerCommand::class,
        FetchWayfairData::class,
        SyncFbMarketplaceSheet::class,
        SyncFbShopSheet::class,
        SyncMercariWShipSheet::class,
        SyncMercariWoShipSheet::class,
        \App\Console\Commands\LogClear::class,
        \App\Console\Commands\EnsureStorageDirectories::class,
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
        \App\Console\Commands\EbayOverUtilzBidsAutoUpdate::class,
        \App\Console\Commands\Ebay2UtilizedBidsAutoUpdate::class,
        \App\Console\Commands\Ebay3UtilizedBidsAutoUpdate::class,
        \App\Console\Commands\AssignAmzListingVariationVerifyDailyTask::class,
        \App\Console\Commands\AssignMissingMappingDailyTask::class,
        \App\Console\Commands\UpdateEbayOneBudget::class,
        \App\Console\Commands\AutoUpdateAmazonFbaOverKwBids::class,
        \App\Console\Commands\AutoUpdateAmazonFbaUnderKwBids::class,
        \App\Console\Commands\AutoUpdateAmazonFbaOverPtBids::class,
        \App\Console\Commands\AutoUpdateAmazonFbaUnderPtBids::class,
        \App\Console\Commands\UpdateEbaySuggestedBid::class,
        \App\Console\Commands\UpdateStockMappingDaily::class,
        AmazonSpCampaignReports::class,
        AmazonSbCampaignReports::class,
        AmazonSdCampaignReports::class,
        AmazonSpKeywordReports::class,
        AmazonSpNegativeKeywords::class,
        FetchGoogleAdsCampaigns::class,
        \App\Console\Commands\FetchGoogleAdsNegativeKeywords::class,
        \App\Console\Commands\SaveGoogleAdsBadgeL30Snapshots::class,
        \App\Console\Commands\SyncMetaAllAds::class,
        \App\Console\Commands\MetaAdsSyncCommand::class,
        \App\Console\Commands\MetaAdsImportRawCommand::class,
        \App\Console\Commands\MetaAdsAutomationCommand::class,
        \App\Console\Commands\MetaAdsSyncAdsCommand::class,
        \App\Console\Commands\MetaAdsProcessQueue::class,
        \App\Console\Commands\MetaAdsProcessQueuePriority::class,
        \App\Console\Commands\SyncFbaShipmentStatus::class,
        \App\Console\Commands\SyncShipmentTrackingStatus::class,
        \App\Console\Commands\RefreshFulfillmentShipmentStatus::class,
        \App\Console\Commands\SnapshotSalesOrderFulfillmentDaily::class,
        \App\Console\Commands\StoreAmazonUtilizationCounts::class,
        \App\Console\Commands\StoreAmazonFbaUtilizationCounts::class,
        \App\Console\Commands\StoreEbayUtilizationCounts::class,
        \App\Console\Commands\StoreGoogleShoppingUtilizationCounts::class,
        FetchMiraklDailyData::class,
        FetchEbay3DailyData::class,
        FetchReverbDailyData::class,
        FetchWayfairDailyData::class,
        FetchShopifyB2BMetrics::class,
        FetchShopifyB2CMetrics::class,
        FetchShopifyProductViews::class,
        \App\Console\Commands\CollectYesterdayViews::class,
        \App\Console\Commands\UpdateEbayCompetitorPrices::class,
        \App\Console\Commands\UpdateEbaySkuCompetitorPrices::class,
        \App\Console\Commands\UpdateAmazonCompetitorPrices::class,
        \App\Console\Commands\UpdateAmazonSkuCompetitorPrices::class,
        \App\Console\Commands\UpdateGoogleCompetitorPrices::class,
        \App\Console\Commands\UpdateGoogleSkuCompetitorPrices::class,
        \App\Console\Commands\SyncAmazonProducts::class,
        \App\Console\Commands\AmazonDebugSku::class,
        \App\Console\Commands\AliExpressApiTestCommand::class,
        \App\Console\Commands\InventorySnapshot::class,
        \App\Console\Commands\RunShopifyBulletPull::class,
        \App\Console\Commands\RunShopifyImagePull::class,
        \App\Console\Commands\RunImageMasterPush::class,
        \App\Console\Commands\RunShopifyVideoPull::class,
        \App\Console\Commands\RunVideoMasterPush::class,
        \App\Console\Commands\RunPricingErrorsFixPush::class,
    ];

    /**
     * Shared scheduler log path for all commands.
     */
    protected string $schedulerLog;


    /** India business window for artisan jobs (09:00–20:00 IST). */
    protected const IST_TZ = 'Asia/Kolkata';

    protected const IST_WINDOW_START = '09:00';

    protected const IST_WINDOW_END = '20:00';

    /**
     * Restrict a scheduled event to India business hours (09:00–20:00 IST).
     *
     * between() only skips firing outside the window — it never kills a process
     * already running. Jobs started before 20:00 may finish after 20:00.
     */
    protected function istBusinessWindow($event)
    {
        return $event
            ->timezone(self::IST_TZ)
            ->between(self::IST_WINDOW_START, self::IST_WINDOW_END);
    }

    /**
     * Mutex TTL for high-frequency jobs. Shorter than Laravel's default (24h) so a
     * crashed run does not block all future ticks until tomorrow.
     */
    protected const HF_MUTEX_EVERY_MINUTE = 5;

    protected const HF_MUTEX_EVERY_FIVE = 15;

    protected const HF_MUTEX_EVERY_TEN = 30;

    protected const HF_MUTEX_HOURLY = 55;
    /**
     * Boot the Kernel – set up scheduler log path once.
     */
    public function __construct(\Illuminate\Contracts\Foundation\Application $app, \Illuminate\Contracts\Events\Dispatcher $events)
    {
        parent::__construct($app, $events);
        $this->schedulerLog = storage_path('logs/scheduler.log');
    }

    protected function schedule(Schedule $schedule)
    {
        // Payroll: Current INR Rate (USD + CNY) for the month sheet — 1st of every month.
        $schedule->command('payroll:fetch-fx-rates')
            ->monthlyOn(1, '00:15')
            ->withoutOverlapping()
            ->runInBackground()
            ->name('payroll-fetch-fx-rates');

        $log = $this->schedulerLog;
        $ist = fn ($event) => $this->istBusinessWindow($event);

       
        $retryFiveTimesUntil = function (string $command, string $baseName, string $finalTime) use ($schedule, $log) {
            [$h, $m] = array_map('intval', explode(':', $finalTime));
            for ($offset = 4; $offset >= 0; $offset--) {
                $hour = $h - $offset;
                if ($hour < 0) {
                    continue;
                }
                $slot = sprintf('%02d:%02d', $hour, $m);
                $schedule->command($command)
                    ->dailyAt($slot)
                    ->timezone('Asia/Kolkata')
                    ->name($baseName . '-' . sprintf('%02d%02d', $hour, $m))
                    ->withoutOverlapping()
                    ->runInBackground()
                    ->appendOutputTo($log);
            }
        };

        $schedule->call(function () {
            Log::info('Scheduler heartbeat at ' . now());
            Log::channel('scheduler_activity')->info('schedule:run_heartbeat', [
                'at' => now()->toIso8601String(),
                'app_tz' => config('app.timezone'),
            ]);
        })
            ->everyMinute()
            ->name('scheduler-heartbeat')
            ->withoutOverlapping(2);

        /*
        |--------------------------------------------------------------------------
        | CRM — FOLLOW-UP REMINDERS
        |--------------------------------------------------------------------------
        */
        $schedule->call(function () {
            FollowUp::query()
                ->reminderDue()
                ->orderBy('id')
                ->pluck('id')
                ->each(static function (int $followUpId): void {
                    SendFollowUpReminderJob::dispatch($followUpId);
                });
        })
            ->everyMinute()
            ->name('crm-follow-up-reminders')
            ->withoutOverlapping(5)
            ->appendOutputTo($log);

   
        $taskTz = config('tasks.business_timezone', 'America/Los_Angeles');

        $schedule->command('tasks:generate-daily-automated')
            ->everyFiveMinutes()
            ->timezone($taskTz)
            ->name('generate-daily-automated-tasks')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_FIVE)
            ->runInBackground()
            ->appendOutputTo($log);

       
        $schedule->command('tasks:expire-missed-automated')
            ->everyMinute()
            ->timezone($taskTz)
            ->name('expire-missed-automated-tasks')
            ->withoutOverlapping(15)
            ->runInBackground()
            ->appendOutputTo($log);

        
        $schedule->command('tasks:automated-health-alert')
            ->everyThirtyMinutes()
            ->timezone($taskTz)
            ->name('automated-tasks-health-alert')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('tasks:execute-automated')
            ->everyMinute()
            ->timezone($taskTz)
            ->name('execute-automated-tasks-weekly-monthly')
            ->withoutOverlapping(2)
            ->runInBackground()
            ->appendOutputTo($log);

        // Amz Listing Variation Verify — daily MISMATCH badge task → ecomm6@5core.com
        $ist($schedule->command('tasks:assign-amz-lvv-mismatch-daily')
            ->dailyAt('15:00')
            ->timezone('Asia/Kolkata')
            ->name('amz-lvv-mismatch-daily-task')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // Missing Mapping (/map-issues) — daily badge count task → tech-support@5core.com
        $ist($schedule->command('tasks:assign-missing-mapping-daily')
            ->dailyAt('15:00')
            ->timezone('Asia/Kolkata')
            ->name('missing-mapping-daily-task')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

       
        $schedule->call(function () {
            $logPath = storage_path('logs/laravel.log');
            if (file_exists($logPath) && filesize($logPath) > 50 * 1024 * 1024) { // Only if > 50MB
                file_put_contents($logPath, '');
            }
        })
            ->everyFiveMinutes()
            ->name('clear-laravel-log')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_FIVE);

        /*
        |--------------------------------------------------------------------------
        | AMAZON SP-API & INVENTORY
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('amazon:sync-inventory')
            ->cron('0 9,18 * * *')
            ->name('amazon-sync-inventory')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-amazon-listings')
            ->dailyAt('09:25')
            ->name('amazon-fetch-listings')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:sync-products --enrich --enrich-limit=200')
            ->dailyAt('09:30')
            ->timezone('Asia/Kolkata')
            ->name('amazon-sync-products')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-amazon-orders --auto-sync --with-items')
            ->cron('0 9,13,18 * * *')
            ->timezone('Asia/Kolkata')
            ->name('amazon-fetch-orders')
            ->withoutOverlapping(240)
            ->runInBackground()
            ->appendOutputTo($log));

        $retryFiveTimesUntil('app:amazon-sp-campaign-reports', 'amazon-sp-campaign-reports', '18:00');
        $retryFiveTimesUntil('app:amazon-sb-campaign-reports', 'amazon-sb-campaign-reports', '18:05');
        $retryFiveTimesUntil('app:amazon-sd-campaign-reports', 'amazon-sd-campaign-reports', '18:10');

      
        $retryFiveTimesUntil('app:amazon-sp-keyword-reports', 'amazon-sp-keyword-reports', '18:15');
        $retryFiveTimesUntil('app:amazon-sp-negative-keywords --prune', 'amazon-sp-negative-keywords', '18:20');

        $ist($schedule->command('amazon:auto-update-over-kw-bids')
            ->dailyAt('18:30')
            ->timezone('Asia/Kolkata')
            ->name('amazon-over-kw-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:auto-update-under-kw-bids')
            ->dailyAt('18:35')
            ->timezone('Asia/Kolkata')
            ->name('amazon-under-kw-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

      
        $ist($schedule->command('amazon:auto-update-over-pt-bids')
            ->dailyAt('18:40')
            ->timezone('Asia/Kolkata')
            ->name('amazon-over-pt-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:auto-update-under-pt-bids')
            ->dailyAt('18:45')
            ->timezone('Asia/Kolkata')
            ->name('amazon-under-pt-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

      
        $ist($schedule->command('amazon:auto-update-over-hl-bids')
            ->dailyAt('18:50')
            ->timezone('Asia/Kolkata')
            ->name('amazon-over-hl-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:auto-update-under-hl-bids')
            ->dailyAt('18:55')
            ->timezone('Asia/Kolkata')
            ->name('amazon-under-hl-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:auto-update-amz-bgt-kw')
            ->dailyAt('20:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-kw')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:auto-update-amz-bgt-pt')
            ->dailyAt('20:05')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-pt')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:auto-update-amz-bgt-hl')
            ->dailyAt('20:10')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-hl')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        // Catch-up: re-apply only remaining BGT≠SBGT deltas (skips already-applied; retries Amazon errors).
        $ist($schedule->command('amazon:auto-update-amz-bgt-kw')
            ->dailyAt('21:30')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-kw-catchup')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:auto-update-amz-bgt-pt')
            ->dailyAt('21:35')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-pt-catchup')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:auto-update-amz-bgt-hl')
            ->dailyAt('21:40')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-hl-catchup')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon-fba:auto-update-under-pt-bids')
            ->dailyAt('19:00')
            ->timezone('Asia/Kolkata')
            ->name('fba-under-pt-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon-fba:auto-update-over-pt-bids')
            ->dailyAt('19:15')
            ->timezone('Asia/Kolkata')
            ->name('fba-over-pt-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon-fba:auto-update-over-kw-bids')
            ->dailyAt('19:30')
            ->timezone('Asia/Kolkata')
            ->name('fba-over-kw-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon-fba:auto-update-under-kw-bids')
            ->dailyAt('19:45')
            ->timezone('Asia/Kolkata')
            ->name('fba-under-kw-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // FBA reports — shifted from 13:30 IST to 18:15 IST so it pulls data
        // AFTER the Amazon SP/SB/SD report retries finalise at 18:10 IST. Previously
        // it ran while PT day was still mid-afternoon → partial spend / clicks.
        $ist($schedule->command('app:fetch-fba-reports')
            ->dailyAt('18:15')
            ->timezone('Asia/Kolkata')
            ->name('fetch-fba-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-fba-inventory --insert --prices')
            ->dailyAt('14:00')
            ->timezone('Asia/Kolkata')
            ->name('fetch-fba-inventory')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-fba-monthly-sales')
            ->dailyAt('14:30')
            ->timezone('Asia/Kolkata')
            ->name('fetch-fba-monthly-sales')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('fba:collect-metrics')
            ->dailyAt('18:15')
            ->timezone('Asia/Kolkata')
            ->name('fba-collect-metrics')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('fba:save-daily-metrics')
            ->dailyAt('18:30')
            ->timezone('Asia/Kolkata')
            ->name('fba-save-daily-metrics')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('fba:sync-shipment-status')
            ->dailyAt('18:45')
            ->timezone('Asia/Kolkata')
            ->name('fba-sync-shipment-status-daily')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:store-utilization-counts')
            ->dailyAt('18:20')
            ->timezone('Asia/Kolkata')
            ->name('amazon-utilization-counts')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:store-listing-daily-metrics')
            ->dailyAt('18:22')
            ->timezone('Asia/Kolkata')
            ->name('amazon-listing-daily-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon-fba:store-utilization-counts')
            ->dailyAt('19:50')
            ->timezone('Asia/Kolkata')
            ->name('fba-utilization-counts')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:collect-metrics')
            ->dailyAt('18:25')
            ->timezone('Asia/Kolkata')
            ->name('amazon-collect-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('channel:collect-yesterday-views')
            ->dailyAt('18:45')
            ->timezone('Asia/Kolkata')
            ->name('channel-yesterday-views')
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo($log));

        // Amz Buybox — SP-API getListingOffers in lots of 40 (INV ≥ 1 only)
        $ist($schedule->command('amazon:pull-buybox --lot=40')
            ->dailyAt('19:10')
            ->timezone('Asia/Kolkata')
            ->name('amazon-pull-buybox')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // Avg rating + review count for amazon-tabulator Reviews column
        // (Amazon Ads Brand Posts customerReviewSummary; SP-API Catalog fallback).
        $ist($schedule->command('amazon:collect-reviews')
            ->dailyAt('18:40')
            ->timezone('Asia/Kolkata')
            ->name('amazon-collect-reviews')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | EBAY JOBS
        |--------------------------------------------------------------------------
        */
        // Not wrapped in $ist() — a delayed scheduler tick after 20:00 IST must still
        // be allowed to refresh ebay_orders. Short mutex so a hung run cannot block
        // the next slot for 24h (that is why 09:35 IST was missed Aug 14–17).
        foreach (['09:35', '13:35', '18:35'] as $slot) {
            $schedule->command('app:fetch-ebay-orders')
                ->dailyAt($slot)
                ->timezone('Asia/Kolkata')
                ->name('fetch-ebay-orders-'.str_replace(':', '', $slot))
                ->withoutOverlapping(self::HF_MUTEX_HOURLY)
                ->runInBackground()
                ->appendOutputTo($log);
        }

        foreach (['09:40', '13:40', '18:40'] as $slot) {
            $schedule->command('app:fetch-ebay2-orders')
                ->dailyAt($slot)
                ->timezone('Asia/Kolkata')
                ->name('fetch-ebay2-orders-'.str_replace(':', '', $slot))
                ->withoutOverlapping(self::HF_MUTEX_HOURLY)
                ->runInBackground()
                ->appendOutputTo($log);
        }

       
        $ist($schedule->command('ebay3:daily --days=60')
            ->dailyAt('19:40')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-ebay-reports')
            ->dailyAt('14:00')
            ->timezone('Asia/Kolkata')
            ->name('fetch-ebay-reports')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log));

        // Amazon Dil vs PRMT → Listings our_price (4:00 AM America/New_York = EST/EDT).
        // Uses shared pef_dil_vs_prmt rules; pushes only SKUs whose target price changed.
        $schedule->command('amazon:dil-prmt-auto-push')
            ->dailyAt('04:00')
            ->timezone('America/New_York')
            ->name('amazon-dil-prmt-auto-push-4am-et')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Amazon CVR vs CPN → 5%/10% coupons (1/day) → Listings our_price (4:05 AM ET).
        // Uses shared pef_cvr_vs_cpn rules; pushes only SKUs whose target price/tier changed.
        $schedule->command('amazon:cvr-cpn-auto-push')
            ->dailyAt('04:05')
            ->timezone('America/New_York')
            ->name('amazon-cvr-cpn-auto-push-4am-et')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        
        $ist($schedule->command('app:fetch-ebay-table-data')
            ->dailyAt('19:25')
            ->timezone('Asia/Kolkata')
            ->name('fetch-ebay-table-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-ebay-two-metrics')
            ->dailyAt('19:30')
            ->timezone('Asia/Kolkata')
            ->name('fetch-ebay-two-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-ebay-three-metrics')
            ->dailyAt('19:35')
            ->timezone('Asia/Kolkata')
            ->name('fetch-ebay-three-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $retryFiveTimesUntil('app:ebay-campaign-reports', 'ebay-campaign-reports', '19:10');
        $retryFiveTimesUntil('app:ebay2-campaign-reports', 'ebay2-campaign-reports', '19:15');
        $retryFiveTimesUntil('app:ebay3-campaign-reports', 'ebay3-campaign-reports', '19:20');

        
        // Campaign-ads sync: single daily run (not $retryFiveTimesUntil — 5 named
        // mutexes let the same job fire 5×/day and confuse cron-monitor "missed").
        // Intentionally NOT wrapped in $ist() — slots are after IST_WINDOW_END (20:00).
        // withoutOverlapping(90): typical runtime ~10–12 min; TTL clears stale locks.
        $schedule->command('ebay:sync-campaign-listings')
            ->dailyAt('20:30')
            ->timezone('Asia/Kolkata')
            ->name('ebay-sync-campaign-listings')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay2:sync-campaign-listings')
            ->dailyAt('20:32')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-sync-campaign-listings')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay3:sync-campaign-listings')
            ->dailyAt('20:34')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-sync-campaign-listings')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log);

        // Post-20:00 eBay ads jobs — must NOT use $ist() (09:00–20:00), or they never fire.
        $schedule->command('ebay:auto-update-over-bids')
            ->dailyAt('21:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay-over-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay:auto-update-under-bids')
            ->dailyAt('21:02')
            ->timezone('Asia/Kolkata')
            ->name('ebay-under-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay2:auto-update-utilized-bids')
            ->dailyAt('21:04')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-utilized-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay3:auto-update-utilized-bids')
            ->dailyAt('21:06')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-utilized-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay:update-suggestedbid')
            ->dailyAt('21:20')
            ->timezone('Asia/Kolkata')
            ->name('ebay-suggestedbid')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay2:update-suggestedbid')
            ->dailyAt('21:23')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-suggestedbid')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay3:update-suggestedbid')
            ->dailyAt('21:26')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-suggestedbid')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay1:update-budget')
            ->dailyAt('21:29')
            ->timezone('Asia/Kolkata')
            ->name('ebay1-budget')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay2:update-budget')
            ->dailyAt('21:31')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-budget')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay3:update-budget')
            ->dailyAt('21:33')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-budget')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        $ist($schedule->command('ebay:collect-metrics')
            ->dailyAt('19:15')
            ->timezone('Asia/Kolkata')
            ->name('ebay-collect-metrics')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        // eBay 2 per-SKU Price / CVR snapshots for /ebay2-tabulator-view Price trend dots + charts
        $ist($schedule->command('ebay2:collect-metrics')
            ->dailyAt('19:18')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-collect-metrics')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        // TikTok 1 / 2 per-SKU Price snapshots for /tiktok-pricing Price charts
        $ist($schedule->command('tiktok:collect-metrics')
            ->dailyAt('19:20')
            ->timezone('Asia/Kolkata')
            ->name('tiktok-collect-metrics')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        $schedule->command('ebay:store-utilization-counts')
            ->dailyAt('21:40')
            ->timezone('Asia/Kolkata')
            ->name('ebay-utilization-counts')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | EBAY COMPETITOR PRICE UPDATES (Weekly)
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('ebay:update-prices')
            ->weekly()
            ->sundays()
            ->at('14:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay-competitor-prices-weekly')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay:update-sku-prices')
            ->weekly()
            ->sundays()
            ->at('15:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay-sku-competitor-prices-weekly')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | AMAZON COMPETITOR PRICE UPDATES (Weekly)
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('amazon:update-prices')
            ->weekly()
            ->mondays()
            ->at('14:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-competitor-prices-weekly')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:update-sku-prices')
            ->weekly()
            ->mondays()
            ->at('15:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-sku-competitor-prices-weekly')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('google:update-sku-prices --skip-search-refresh')
            ->weekly()
            ->mondays()
            ->at('15:30')
            ->timezone('Asia/Kolkata')
            ->name('google-sku-competitor-prices-weekly')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $retryFiveTimesUntil('app:fetch-google-ads-campaigns', 'fetch-google-ads-campaigns', '17:00');

       
        $retryFiveTimesUntil('ga4:fetch-campaign-data --days=30', 'ga4-fetch-campaign-data', '17:30');

     
        $retryFiveTimesUntil('app:fetch-google-ads-negative-keywords --prune', 'fetch-google-ads-negative-keywords', '17:15');

        // Save rolling L30 badge metrics (ACOS / spend / sales…) for Shopping + SERP + YT
        // charts — one snapshot per campaign per day. Runs after Ads + GA4 pulls.
        $ist($schedule->command('google:save-badge-l30-snapshots')
            ->dailyAt('17:45')
            ->timezone('Asia/Kolkata')
            ->name('google-badge-l30-snapshots')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('sbid:update')
            ->dailyAt('17:48')
            ->timezone('Asia/Kolkata')
            ->name('sbid-update')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('sbid:update-serp')
            ->dailyAt('17:49')
            ->timezone('Asia/Kolkata')
            ->name('sbid-update-serp')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('budget:update-shopping')
            ->dailyAt('17:50')
            ->timezone('Asia/Kolkata')
            ->name('budget-shopping')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('budget:update-serp')
            ->dailyAt('17:51')
            ->timezone('Asia/Kolkata')
            ->name('budget-serp')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('google:store-shopping-utilization-counts')
            ->dailyAt('17:52')
            ->timezone('Asia/Kolkata')
            ->name('google-shopping-utilization')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // Reset SBID status daily — must complete before sbid:update at 17:48 IST.
        // withoutOverlapping(2) keeps the daily reset single-fire even if a tick is delayed.
        $schedule->call(function () {
            try {
                DB::connection('apicentral')
                    ->table('google_ads_campaigns')
                    ->where('id', 1)
                    ->update(['sbid_status' => 0]);
            } catch (\Throwable $e) {
                Log::error('Scheduler: Failed to reset sbid_status - ' . $e->getMessage());
            }
        })
            ->dailyAt('17:47')
            ->timezone('Asia/Kolkata')
            ->name('reset-sbid-status')
            ->withoutOverlapping(2);

        $retryFiveTimesUntil('meta:sync-all-ads', 'meta-ads-sync-daily', '18:30');

   
        $retryFiveTimesUntil('meta-ads:sync', 'meta-ads-manager-full-sync', '19:00');
        $retryFiveTimesUntil('meta-ads:sync --insights-only', 'meta-ads-manager-insights-sync', '19:00');

        $retryFiveTimesUntil('shopify:fetch-meta-campaigns --channel=both', 'fetch-shopify-fb-campaigns-7-30-60-days', '19:30');

        $ist($schedule->command('meta-ads:run-automation')
            ->dailyAt('20:30')
            ->timezone('Asia/Kolkata')
            ->name('meta-ads-automation-rules')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('meta:sync-all-ads')
            ->dailyAt('21:45')
            ->timezone('Asia/Kolkata')
            ->name('sync-meta-all-ads-from-google-sheets')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

     
        $ist($schedule->command('shopify:sync --store=main')
            ->everyThreeHours()
            ->name('shopify-live-catalog-master')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('shopify:sync-orders --days=2')
            ->hourly()
            ->name('shopify-sync-orders-recent')
            ->withoutOverlapping(self::HF_MUTEX_HOURLY)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('shopify:sync-orders --days=60')
            ->dailyAt('09:08')
            ->timezone('Asia/Kolkata')
            ->name('shopify-sync-orders-backfill')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('sync:shopify-quantity')
            ->twiceDaily(9, 18)
            ->name('sync-shopify-quantity')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-shopify-b2b-metrics --days=60')
            ->twiceDaily(10, 18)
            ->name('shopify-b2b-metrics')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-shopify-b2c-metrics --days=60')
            ->twiceDaily(10, 18)
            ->name('shopify-b2c-metrics')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-shopify-product-views --days=30')
            ->twiceDaily(10, 18)
            ->name('shopify-product-views')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | WAYFAIR
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('wayfair:daily --days=60')
            ->dailyAt('14:05')
            ->timezone('Asia/Kolkata')
            ->name('wayfair-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('sync:wayfair-l30-api')
            ->dailyAt('13:02')
            ->timezone('Asia/Kolkata')
            ->name('wayfair-api-sync-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('reverb:fetch')
            ->dailyAt('09:50')
            ->timezone('Asia/Kolkata')
            ->name('reverb-fetch')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $schedule->command('reverb:fetch --skip-bump')
            ->dailyAt('09:00')
            ->timezone('America/Los_Angeles')
            ->name('reverb-fetch-pt')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        $ist($schedule->command('reverb:daily --days=60')
            ->dailyAt('09:55')
            ->timezone('Asia/Kolkata')
            ->name('reverb-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('reverb:sync-listing-statuses')
            ->dailyAt('10:00')
            ->timezone('Asia/Kolkata')
            ->name('reverb-sync-listing-statuses')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('reverb:collect-metrics')
            ->dailyAt('10:05')
            ->timezone('Asia/Kolkata')
            ->name('reverb-collect-metrics')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | TOPDAWG
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('topdawg:fetch')
            ->dailyAt('10:05')
            ->timezone('Asia/Kolkata')
            ->name('topdawg-fetch')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // Shopify live inventory → marketplace.
        // Full crawl: every 4 hours (webhooks handle real-time qty).
        // Mismatch-only: every 15 minutes so drift is corrected without queue bulk.
        $schedule->job(new \App\Jobs\SyncInventoryToAliexpress)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('aliexpress'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('aliexpress', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        // Shopify label/tracking → AliExpress declare/modify shipment (settings-gated).
        $schedule->job(new \App\Jobs\SyncAliexpressTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        // AliExpress receipt address → fill missing Shopify shipping + customer address.
        $schedule->job(new \App\Jobs\SyncAliexpressAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        // Listed product price + AE stock → aliexpress_pricing_prices (/aliexpress-pricing).
        $schedule->command('app:fetch-aliexpress-metrics --listed')
            ->dailyAt('04:45')
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-fetch-listed-prices')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncInventoryToAlibaba)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('alibaba-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('alibaba'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('alibaba-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('alibaba', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('alibaba-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        // Shopify label/tracking → Alibaba declare/modify shipment (settings-gated).
        $schedule->job(new \App\Jobs\SyncAlibabaTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('alibaba-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        // Alibaba receipt address → fill missing Shopify shipping + customer address.
        $schedule->job(new \App\Jobs\SyncAlibabaAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('alibaba-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        // Reverb Marketplace Manager
        $schedule->job(new \App\Jobs\SyncInventoryToReverbManager)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('reverb-manager-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('reverb'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('reverb-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('reverb', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('reverb-manager-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        // Shopify label/tracking → Reverb mark shipped (settings-gated).
        $schedule->job(new \App\Jobs\SyncReverbTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('reverb-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        // Reverb shipping address → fill missing Shopify shipping + customer address.
        $schedule->job(new \App\Jobs\SyncReverbAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('reverb-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        // Link-map refresh (local SKU ↔ product_id only). Hourly to limit marketplace API load.
        $schedule->command('aliexpress:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('alibaba:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('alibaba-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('reverb:manager-sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('reverb-manager-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // Newegg Marketplace Manager: inventory/price from Shopify, orders to Shopify
        $schedule->job(new \App\Jobs\SyncInventoryToNewegg)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('newegg-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('newegg'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('newegg-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('newegg', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('newegg-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        // Shopify label/tracking → Newegg Ship Order (settings-gated).
        $schedule->job(new \App\Jobs\SyncNeweggTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('newegg-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        // Newegg ShipTo / buyer → fill missing Shopify shipping + customer address.
        $schedule->job(new \App\Jobs\SyncNeweggAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('newegg-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('newegg:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('newegg-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // Shein Marketplace Manager: inventory/price from Shopify, orders to Shopify
        $schedule->job(new \App\Jobs\SyncInventoryToShein)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('shein-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('shein'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('shein-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('shein', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('shein-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncSheinTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('shein-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncSheinAcceptJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('shein-accept-orders')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncSheinAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('shein-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('shein:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('shein-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // Amazon Marketplace Manager: order fetch (local amazon_orders)
        $schedule->job(new \App\Jobs\SyncInventoryToAmazon)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('amazon-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('amazon'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('amazon-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('amazon', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('amazon-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncAmazonTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('amazon-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncAmazonAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('amazon-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('amazon:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('amazon-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // TopDawg Marketplace Manager: inventory/price from Shopify, orders to Shopify
        $schedule->job(new \App\Jobs\SyncInventoryToTopDawg)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('topdawg-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('topdawg'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('topdawg-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('topdawg', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('topdawg-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncTopDawgTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('topdawg-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncTopDawgAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('topdawg-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('topdawg:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('topdawg-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

     
        $schedule->job(new \App\Jobs\SyncInventoryToTemu)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('temu-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('temu'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('temu-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        
        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('temu', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('temu-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        // Auto tracking/address apply disabled — use order page / Settings "Sync now" manually.
        // $schedule->job(new \App\Jobs\SyncTemuTrackingJob(true, 40))
        //     ->everyFiveMinutes()
        //     ->timezone('Asia/Kolkata')
        //     ->name('temu-sync-tracking')
        //     ->withoutOverlapping(4)
        //     ->appendOutputTo($log);

        // $schedule->job(new \App\Jobs\SyncTemuAddressJob(true, 40))
        //     ->everyFifteenMinutes()
        //     ->timezone('Asia/Kolkata')
        //     ->name('temu-sync-address')
        //     ->withoutOverlapping(20)
        //     ->appendOutputTo($log);

        $schedule->command('temu:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('temu-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // Temu 2 Marketplace Manager: inventory/price from Shopify, orders to Shopify
        $schedule->job(new \App\Jobs\SyncInventoryToTemu2)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('temu2-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('temu2'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('temu2-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        // Fetch + import NEW open Temu 2 orders only (service skips shipped/delivered backlog; last 3 days).
        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('temu2', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('temu2-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        // Auto tracking/address apply disabled — use order page / Settings "Sync now" manually.
        // $schedule->job(new \App\Jobs\SyncTemu2TrackingJob(true, 40))
        //     ->everyFiveMinutes()
        //     ->timezone('Asia/Kolkata')
        //     ->name('temu2-sync-tracking')
        //     ->withoutOverlapping(4)
        //     ->appendOutputTo($log);

        // $schedule->job(new \App\Jobs\SyncTemu2AddressJob(true, 40))
        //     ->everyFifteenMinutes()
        //     ->timezone('Asia/Kolkata')
        //     ->name('temu2-sync-address')
        //     ->withoutOverlapping(20)
        //     ->appendOutputTo($log);

        $schedule->command('temu2:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('temu2-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // Purchasing Power Marketplace Manager
        $schedule->job(new \App\Jobs\SyncInventoryToPurchasingPower)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('purchasingpower-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('purchasingpower'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('purchasingpower-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('purchasingpower', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('purchasingpower-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncPurchasingPowerTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('purchasingpower-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncPurchasingPowerAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('purchasingpower-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('purchasingpower:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('purchasingpower-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // Wayfair Marketplace Manager — Shopify qty → active listings.
        // 30 min cadence; unique lock + withoutOverlapping skip the next tick if the
        // previous run is still going (do not dispatch a second overlapping job).
        $schedule->job(new \App\Jobs\SyncInventoryToWayfair)
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('wayfair-sync-inventory')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('wayfair'))
            ->cron('15,45 * * * *')
            ->timezone('Asia/Kolkata')
            ->name('wayfair-sync-mismatch-inventory')
            ->withoutOverlapping(14)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('wayfair', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('wayfair-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncWayfairTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('wayfair-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncWayfairAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('wayfair-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('wayfair:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('wayfair-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // Best Buy Marketplace Manager
        $schedule->job(new \App\Jobs\SyncInventoryToBestBuy)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('bestbuy-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('bestbuy'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('bestbuy-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('bestbuy', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('bestbuy-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncBestBuyTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('bestbuy-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncBestBuyAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('bestbuy-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('bestbuy:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('bestbuy-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // Macy's Marketplace Manager
        $schedule->job(new \App\Jobs\SyncInventoryToMacy)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('macy-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('macy'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('macy-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('macy', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('macy-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMacyTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('macy-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMacyAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('macy-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('macy:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('macy-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // Doba Marketplace Manager
        $schedule->job(new \App\Jobs\SyncInventoryToDoba)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('doba-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('doba'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('doba-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('doba', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('doba-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncDobaTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('doba-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncDobaAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('doba-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('doba:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('doba-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // eBay 1 Marketplace Manager: inventory/price from Shopify, orders to Shopify
        $schedule->job(new \App\Jobs\SyncInventoryToEbay1)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('ebay1-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('ebay1'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay1-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('ebay1', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay1-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncEbay1TrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay1-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncEbay1AddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay1-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('ebay1:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('ebay1-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // eBay 2 Marketplace Manager: inventory/price from Shopify, orders to Shopify
        $schedule->job(new \App\Jobs\SyncInventoryToEbay2)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('ebay2-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('ebay2'))
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('ebay2-sync-mismatch-inventory')
            ->withoutOverlapping(50)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('ebay2', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay2-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncEbay2TrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay2-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncEbay2AddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay2-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('ebay2:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('ebay2-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // eBay 3 Marketplace Manager: inventory/price from Shopify, orders to Shopify
        $schedule->job(new \App\Jobs\SyncInventoryToEbay3)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('ebay3-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('ebay3'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay3-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('ebay3', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay3-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncEbay3TrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay3-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncEbay3AddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay3-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('ebay3:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('ebay3-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // Faire Marketplace Manager
        $schedule->job(new \App\Jobs\SyncInventoryToFaire)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('faire-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('faire'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('faire-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('faire', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('faire-sync-orders')
            ->withoutOverlapping(28)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncFaireTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('faire-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncFaireAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('faire-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('faire:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('faire-sync-link-map')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log);

        // TikTok Shop (1) Marketplace Manager
        $schedule->job(new \App\Jobs\RunMarketplaceInventorySyncJob('tiktok'))
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('tiktok-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('tiktok'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('tiktok-sync-mismatch-inventory')
            ->withoutOverlapping(45)
            ->appendOutputTo($log);

        $schedule->command('tiktok:sync-orders --days=2 --import')
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('tiktok-sync-orders')
            ->withoutOverlapping(28)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncTikTokTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('tiktok-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncTikTokAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('tiktok-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        // Auto link-map: quick (changed) most hours; full catalog when stale / empty.
        $schedule->job(new \App\Jobs\SyncTikTokProductsJob('tiktok', true))
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('tiktok-sync-link-map')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        // TikTok 2 Marketplace Manager
        $schedule->job(new \App\Jobs\RunMarketplaceInventorySyncJob('tiktok2'))
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('tiktok2-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('tiktok2'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('tiktok2-sync-mismatch-inventory')
            ->withoutOverlapping(45)
            ->appendOutputTo($log);

        $schedule->command('tiktok2:sync-orders --days=2 --import')
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('tiktok2-sync-orders')
            ->withoutOverlapping(28)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncTikTok2TrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('tiktok2-sync-tracking')
            ->withoutOverlapping(4)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncTikTok2AddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('tiktok2-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncTikTokProductsJob('tiktok2', true))
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('tiktok2-sync-link-map')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        // Backup: queue Shopify imports for unpushed MM orders even if fetch jobs are stuck.
        $schedule->command('mm:dispatch-unpushed-shopify')
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('mm-dispatch-unpushed-shopify')
            ->withoutOverlapping(14)
            ->runInBackground()
            ->appendOutputTo($log);

        // $schedule->command('shopify:retry-pending-orders')
            //     ->hourly()
            //     ->timezone('UTC')
            //     ->name('shopify-retry-pending-orders')
            //     ->withoutOverlapping(30)
            //     ->runInBackground()
            //     ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | MACY
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('app:fetch-macy-products')
            ->everyFiveMinutes()
            ->name('fetch-macy-products')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_FIVE)
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | PURCHASING POWER (MCM OF21 prices/stock + OR11 orders)
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('purchasing-power:sync --days=60')
            ->everyFifteenMinutes()
            ->name('purchasing-power-sync')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log));

     
        $ist($schedule->command('app:fetch-wayfair-data')
            ->everyFiveMinutes()
            ->name('fetch-wayfair-data')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_FIVE)
            ->runInBackground()
            ->appendOutputTo($log));

 
        $ist($schedule->command('mirakl:daily --days=60')
            ->dailyAt('14:15')
            ->timezone('Asia/Kolkata')
            ->name('mirakl-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

       
        $ist($schedule->command('app:fetch-temu-orders')
            ->dailyAt('14:15')
            ->timezone('Asia/Kolkata')
            ->name('fetch-temu-orders')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-temu-metrics')
            ->dailyAt('14:25')
            ->timezone('Asia/Kolkata')
            ->name('fetch-temu-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-temu2-metrics')
            ->dailyAt('14:35')
            ->timezone('Asia/Kolkata')
            ->name('fetch-temu2-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('temu:collect-metrics')
            ->dailyAt('14:35')
            ->timezone('Asia/Kolkata')
            ->name('temu-collect-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

     
        // Stores full raw into temu_ads_api_reports + syncs impressions/clicks to temu_metrics
        $retryFiveTimesUntil('temu:fetch-ads-data --period=L30', 'temu-ads-data-sync-l30', '15:40');
        $retryFiveTimesUntil('temu:fetch-ads-data --period=L60', 'temu-ads-data-sync-l60', '15:50');
        $retryFiveTimesUntil('temu:fetch-ads-api-reports --period=L7', 'temu-ads-api-reports-l7', '15:55');
        // After L7 reports: pause Active ads that match L7 clicks / Stop ROAS.
        // Toggle from Ad rules modal (temu_ads_auto_pause_cron). Command also no-ops when paused.
        $retryFiveTimesUntil('temu:auto-pause-ads', 'temu-ads-auto-pause', '16:10');
        $retryFiveTimesUntil('temu2:fetch-ads-data --period=L30', 'temu2-ads-data-sync-l30', '16:15');
        $retryFiveTimesUntil('temu2:fetch-ads-data --period=L60', 'temu2-ads-data-sync-l60', '16:25');
        $retryFiveTimesUntil('temu2:fetch-ads-api-reports --period=L7', 'temu2-ads-api-reports-l7', '16:30');
        $retryFiveTimesUntil('temu2:auto-pause-ads', 'temu2-ads-auto-pause', '16:40');
        // Recommended supply prices → temu_metrics.recommended_base_price (10=low traffic, 20=restricted)
        $retryFiveTimesUntil('temu:fetch-recommended-prices --both', 'temu-recommended-prices', '16:05');

        /*
        |--------------------------------------------------------------------------
        | DOBA
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('doba:daily --days=60')
            ->dailyAt('14:45')
            ->timezone('Asia/Kolkata')
            ->name('doba-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-doba-metrics')
            ->dailyAt('14:55')
            ->timezone('Asia/Kolkata')
            ->name('doba-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | SHEIN (Open API → shein_metrics / shein_pricing_prices / shein_daily_data)
        |--------------------------------------------------------------------------
        */
        // Products + price/stock → shein_metrics + shein_pricing_prices
        $ist($schedule->command('shein:fetch sync')
            ->dailyAt('15:00')
            ->timezone('Asia/Kolkata')
            ->name('shein-fetch-sync')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log));

        // L30 orders → shein_daily_data (/shein-tabulator)
        $ist($schedule->command('shein:fetch orders --days=30 --target=l30')
            ->dailyAt('15:10')
            ->timezone('Asia/Kolkata')
            ->name('shein-fetch-orders-l30')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log));

        // L60 orders → shein_daily_data_l60
        $ist($schedule->command('shein:fetch orders --days=60 --target=l60')
            ->dailyAt('15:20')
            ->timezone('Asia/Kolkata')
            ->name('shein-fetch-orders-l60')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | SHEET SYNCS (Various marketplaces)
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('app:sync-sheet')
            ->dailyAt('15:05')
            ->timezone('Asia/Kolkata')
            ->name('sync-main-sheet')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:sync-mercari-w-ship-sheet')
            ->dailyAt('15:10')
            ->timezone('Asia/Kolkata')
            ->name('sync-mercari-w-ship')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:sync-mercari-wo-ship-sheet')
            ->dailyAt('15:15')
            ->timezone('Asia/Kolkata')
            ->name('sync-mercari-wo-ship')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:sync-fb-shop-sheet')
            ->dailyAt('15:20')
            ->timezone('Asia/Kolkata')
            ->name('sync-fb-shop')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:sync-fb-marketplace-sheet')
            ->dailyAt('15:25')
            ->timezone('Asia/Kolkata')
            ->name('sync-fb-marketplace')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:top-dawg-shop-sheet')
            ->dailyAt('15:30')
            ->timezone('Asia/Kolkata')
            ->name('sync-topdawg-shop-sheet')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

            $ist($schedule->command('shopify-pls:sync')
            ->cron('55 8,17 * * *')
            ->name('sync-shopify-pls-catalog')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));
        $ist($schedule->command('app:fetch-pls-data')
            ->twiceDaily(9, 18)
            ->name('fetch-pls-data')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('sync:neweegg-sheet')
            ->twiceDaily(9, 18)
            ->name('sync-newegg-sheet')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

     
        $ist($schedule->command('newegg:orders --days=60 --save')
            ->twiceDaily(9, 18)
            ->name('fetch-newegg-orders')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        // Deprecated: Temu sheet tables dropped (temu_pricing, temu_product_sheets, etc.)
        // $ist($schedule->command('sync:temu-sheet-data')
        //     ->twiceDaily(9, 18)
        //     ->name('sync-temu-sheet')
        //     ->withoutOverlapping(90)
        //     ->runInBackground()
        //     ->appendOutputTo($log));

        $ist($schedule->command('app:sync-cp-master-to-sheet')
            ->hourly()
            ->name('sync-cp-master-sheet')
            ->withoutOverlapping(self::HF_MUTEX_HOURLY)
            ->runInBackground()
            ->appendOutputTo($log));

      
        $ist($schedule->command('products:recalc-lp')
            ->hourly()
            ->name('products-recalc-lp')
            ->withoutOverlapping(self::HF_MUTEX_HOURLY)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:process-jungle-scout-sheet-data')
            ->dailyAt('15:30')
            ->timezone('Asia/Kolkata')
            ->name('jungle-scout-sheet')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | HIGH-FREQUENCY SYNCS (every minute / 5 minutes)
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('sync:amazon-prices')
            ->everyMinute()
            ->name('sync-amazon-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_MINUTE)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('sync:walmart-metrics-data')
            ->everyMinute()
            ->name('sync-walmart-metrics')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_MINUTE)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('walmart:fetch-listed-prices')
            ->cron('0 */3 * * *')
            ->name('walmart-fetch-listed-prices')
            ->withoutOverlapping(170)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('walmart:fetch-orders --days=60')
            ->dailyAt('01:20')
            ->name('walmart-fetch-orders')
            ->withoutOverlapping(170)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('tiktok:fetch-orders --days=60 --prune')
            ->dailyAt('02:10')
            ->name('tiktok-fetch-orders')
            ->withoutOverlapping(170)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('tiktok:fetch-orders --channel=tiktok2 --days=60 --prune')
            ->dailyAt('02:25')
            ->name('tiktok2-fetch-orders')
            ->withoutOverlapping(170)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:aliexpress-sheet-sync')
            ->everyMinute()
            ->name('aliexpress-sheet-sync')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_MINUTE)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:update-marketplace-daily-metrics')
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('update-marketplace-daily-metrics')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_FIVE)
            ->runInBackground()
            ->appendOutputTo($log));

     
        $ist($schedule->command('channel:calculate-data --force')
            ->everyTenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('channel-master-calculate-data')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log));

        $retryFiveTimesUntil('sync:tiktok-api-data', 'sync-tiktok-api-data', '15:45');
        $retryFiveTimesUntil('sync:tiktok-api-data --channel=tiktok2', 'sync-tiktok2-api-data', '16:00');

  
        $ist($schedule->command('stock:update-mapping-daily')
            ->dailyAt('18:00')
            ->timezone('Asia/Kolkata')
            ->name('stock-mapping-daily-update')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('inventory:snapshot')
            ->dailyAt('19:00')
            ->timezone('Asia/Kolkata')
            ->name('inventory-snapshot-daily')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('badges:save-all')
            ->dailyAt('19:10')
            ->timezone('Asia/Kolkata')
            ->name('badges-save-all-daily')
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | AUTO LOGOUT INACTIVE USERS
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('users:auto-logout')
            ->cron('0 9,18 * * *')
            ->timezone('Asia/Kolkata')
            ->name('auto-logout-users')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | REVIEW INTELLIGENCE MASTER SYSTEM
        |--------------------------------------------------------------------------
        */
        // Analyze unprocessed reviews every hour (batch of 100)
        $ist($schedule->command('reviews:analyze --batch=100')
            ->hourly()
            ->name('reviews-analyze-batch')
            ->withoutOverlapping(self::HF_MUTEX_HOURLY)
            ->runInBackground()
            ->appendOutputTo($log));

   
        /*
        |--------------------------------------------------------------------------
        | Sales Order Fulfillment — full-day max allowed tracking refresh
        |
        | Use nearly all USPS hourly quota 24/7 (~55/hr → ~1,300/day) on open trackings.
        | 30-day backlog (null status) is prioritized; Delivered/Expired never re-pulled.
        | As packages deliver, open qty drops and the same schedule sustains ~500 new/day.
        |--------------------------------------------------------------------------
        */
        // Carrier sync every 15 min, all day — each tick spends remaining hourly USPS budget.
        // Catch-up sized for large open backlogs (~5k trackings) via 17TRACK batches.
        $schedule->command('tracking:sync-status --only-open --repair-quota --catch-up --limit=800')
            ->cron('*/15 * * * *')
            ->timezone('America/Los_Angeles')
            ->name('shipment-tracking-sync-status-fullday')
            ->withoutOverlapping(14)
            ->runInBackground()
            ->appendOutputTo($log);

        // Marketplace order refresh (Label Created can advance) — daytime only, no extra USPS burn.
        $schedule->command('fulfillment:refresh-shipment-status --skip-tracking --days=30')
            ->hourly()
            ->timezone('America/Los_Angeles')
            ->between('07:00', '21:00')
            ->name('fulfillment-refresh-marketplace-orders-pst')
            ->withoutOverlapping(50)
            ->runInBackground()
            ->appendOutputTo($log);

        // PEF Dil vs PRMT → eBay1 Promotion API (always once/day, even if Dil/INV/rules unchanged).
        // INV=0 forces PRMT%=0 (pause). Uses saved pef_dil_vs_prmt rules or first-time defaults.
        $schedule->command('pef:dil-prmt-auto-apply')
            ->dailyAt('00:00')
            ->timezone('Asia/Kolkata')
            ->name('pef-dil-prmt-auto-apply-midnight-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // PEF CVR vs CPN → eBay1 public coded coupon API (always once/day after Dil/PRMT / price window).
        // Same CPN% reuses campaign (SAVE{nn}PCT); CPN%=0 removes SKU from coupon.
        $schedule->command('pef:cvr-cpn-auto-apply')
            ->dailyAt('00:30')
            ->timezone('Asia/Kolkata')
            ->name('pef-cvr-cpn-auto-apply-after-price-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // SOF summary history — always one row per Pacific day (even if metrics unchanged).
        // Primary write at 00:00 PST (stores the day that just ended).
        $schedule->command('sof:snapshot-daily')
            ->dailyAt('00:00')
            ->timezone('America/Los_Angeles')
            ->name('sof-snapshot-daily-pst')
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo($log);

        // Catch-up: if 00:00 was missed, create any missing recent Pacific-day rows (never skip unchanged).
        $schedule->command('sof:snapshot-daily --catch-up --backfill=3')
            ->dailyAt('00:30')
            ->timezone('America/Los_Angeles')
            ->name('sof-snapshot-daily-catchup-0030')
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('sof:snapshot-daily --catch-up --backfill=3')
            ->dailyAt('06:00')
            ->timezone('America/Los_Angeles')
            ->name('sof-snapshot-daily-catchup-0600')
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('attendance:analyze')
            ->dailyAt('23:30')
            ->timezone('Asia/Kolkata')
            ->name('attendance-daily-analysis')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log);

      
        // Must run inline in schedule:run — runInBackground() would sit on the 1.3M+ default
        // queue and never start mm-* workers, so marketplace orders never reach Shopify.
        $schedule->command('queue:ensure-watchdog-daemon')
            ->everyMinute()
            ->name('queue-ensure-watchdog-daemon')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        // After optimize:clear, file-cache shard dirs can vanish; recreate so sidebar badges don't 500.
        $schedule->command('storage:ensure --fix')
            ->everyMinute()
            ->name('storage-ensure-dirs')
            ->withoutOverlapping(50)
            ->runInBackground()
            ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | CRON MONITOR — watchdog + retention
        |--------------------------------------------------------------------------
        */
        $schedule->command('cron-monitor:watchdog')
            ->everyFiveMinutes()
            ->name('cron-monitor-watchdog')
            ->withoutOverlapping(4)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('cron-monitor:cleanup')
            ->dailyAt('03:40')
            ->timezone('Asia/Kolkata')
            ->name('cron-monitor-cleanup')
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
