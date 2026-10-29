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
use Illuminate\Support\Facades\Schema;
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
        \App\Console\Commands\Ebay2AutoEnrollEligibleAds::class,
        \App\Console\Commands\Ebay3UtilizedBidsAutoUpdate::class,
        \App\Console\Commands\AssignAmzListingVariationVerifyDailyTask::class,
        \App\Console\Commands\AssignMissingMappingDailyTask::class,
        \App\Console\Commands\UpdateEbayOneBudget::class,
        \App\Console\Commands\AutoUpdateAmazonFbaOverKwBids::class,
        \App\Console\Commands\AutoUpdateAmazonFbaUnderKwBids::class,
        \App\Console\Commands\AutoUpdateAmazonFbaOverPtBids::class,
        \App\Console\Commands\AutoUpdateAmazonFbaUnderPtBids::class,
        \App\Console\Commands\UpdateStockMappingDaily::class,
        AmazonSpCampaignReports::class,
        AmazonSbCampaignReports::class,
        \App\Console\Commands\AmazonAdsSyncEnabledCampaigns::class,
        \App\Console\Commands\AmazonAdsLiveBidBgtSync::class,
        \App\Console\Commands\AmazonAdsLbidDailySnapshot::class,
        AmazonSdCampaignReports::class,
        AmazonSpKeywordReports::class,
        AmazonSpNegativeKeywords::class,
        \App\Console\Commands\AmazonAdsSyncTargetCounts::class,
        FetchGoogleAdsCampaigns::class,
        \App\Console\Commands\FetchGoogleAdsNegativeKeywords::class,
        \App\Console\Commands\SaveGoogleAdsBadgeL30Snapshots::class,
        \App\Console\Commands\SyncGoogleShoppingLiveCpcCommand::class,
        \App\Console\Commands\SyncMetaAllAds::class,
        \App\Console\Commands\MetaAdsSyncCommand::class,
        \App\Console\Commands\MetaAdsImportRawCommand::class,
        \App\Console\Commands\MetaAdsAutomationCommand::class,
        \App\Console\Commands\MetaAdsSyncAdsCommand::class,
        \App\Console\Commands\MetaAdsProcessQueue::class,
        \App\Console\Commands\MetaAdsProcessQueuePriority::class,
        \App\Console\Commands\SyncFbaShipmentStatus::class,
        \App\Console\Commands\SyncShipmentTrackingStatus::class,
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
        \App\Console\Commands\SyncStoreWebsitePrices::class,
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
     * The check runs when the event is filtered, against the clock at that
     * moment (or Carbon::setTestNow). Laravel's between() freezes the clock
     * at registration, so a catch-up later in the day could not see the slot.
     * This does not kill a process already running.
     *
     * Do not wrap a job whose clock is outside this window (before 09:00 or
     * after 20:00 IST). The slot will never fire and cron-monitor will mark it missed.
     */
    /**
     * Run-day filter for "every N days" jobs (calendar days in IST since 2026-01-01).
     * Unlike cron day-of-month step syntax, this stays evenly spaced across month ends.
     */
    protected function everyNthDay(int $n): \Closure
    {
        $tz = self::IST_TZ;

        return function () use ($n, $tz): bool {
            $anchor = \Illuminate\Support\Carbon::create(2026, 1, 1, 0, 0, 0, $tz);
            $today = \Illuminate\Support\Carbon::now($tz)->startOfDay();

            return ((int) $anchor->diffInDays($today)) % max(1, $n) === 0;
        };
    }

    protected function istBusinessWindow($event)
    {
        $tz = self::IST_TZ;
        [$startH, $startM] = array_map('intval', explode(':', self::IST_WINDOW_START));
        [$endH, $endM] = array_map('intval', explode(':', self::IST_WINDOW_END));

        return $event
            ->timezone($tz)
            ->when(function () use ($tz, $startH, $startM, $endH, $endM) {
                $now = \Illuminate\Support\Carbon::now($tz);

                return $now->between(
                    $now->copy()->setTime($startH, $startM, 0),
                    $now->copy()->setTime($endH, $endM, 0)
                );
            });
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

        // Veeqo Billing: orders updated since the last run (label charges per shipment).
        $schedule->command('veeqo:sync-billing')
            ->hourlyAt(25)
            ->withoutOverlapping(120)
            ->runInBackground()
            ->name('veeqo-sync-billing')
            ->appendOutputTo($log);
        $schedule->command('veeqo:sync-chargebacks --days=30')
            ->dailyAt('04:40')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->name('veeqo-sync-chargebacks')
            ->appendOutputTo($log);
        $schedule->command('veeqo:fill-order-skus --limit=300')
            ->hourlyAt(50)
            ->withoutOverlapping(60)
            ->runInBackground()
            ->name('veeqo-fill-order-skus')
            ->appendOutputTo($log);
        $schedule->command('tiktok:sync-chargebacks')
            ->dailyAt('04:50')
            ->withoutOverlapping(240)
            ->runInBackground()
            ->name('tiktok-sync-chargebacks')
            ->appendOutputTo($log);
        $schedule->command('veeqo:chargeback-osize')
            ->dailyAt('05:15')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->name('veeqo-chargeback-osize')
            ->appendOutputTo($log);
        $ist = fn ($event) => $this->istBusinessWindow($event);

        // Forecast month table: 1st of the month, 00:20 Pacific (not IST —
        // 00:20 PT is outside the 09:00–20:00 IST window on part of the year).
        $schedule->command('forecast:advance-month')
            ->monthlyOn(1, '00:20')
            ->timezone('America/Los_Angeles')
            ->withoutOverlapping()
            ->runInBackground()
            ->name('forecast-advance-month')
            ->appendOutputTo($log);

       
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
        | RUN ORDER: 1) SALES  2) PRICE  3) EVERYTHING ELSE
        | Same-minute ticks start in this order. Cron times are unchanged.
        |--------------------------------------------------------------------------
        */
        $this->scheduleSalesCommands($schedule, $log, $ist, $retryFiveTimesUntil);
        $this->schedulePriceCommands($schedule, $log, $ist, $retryFiveTimesUntil);
        $this->scheduleOtherCommands($schedule, $log, $ist, $retryFiveTimesUntil);
    }

    /**
     * Marketplace sales / orders / daily metrics. Registered first so they
     * start before price and ads jobs when several events are due together.
     */
    protected function scheduleSalesCommands(Schedule $schedule, string $log, \Closure $ist, \Closure $retryFiveTimesUntil): void
    {

        $ist($schedule->command('app:fetch-amazon-orders --auto-sync --with-items')
            ->cron('0 9,18 * * *')
            ->timezone('Asia/Kolkata')
            ->name('amazon-fetch-orders')
            ->withoutOverlapping(240)
            ->runInBackground()
            ->appendOutputTo($log));

        // 13:10 IST = 00:40 PDT — Pacific yesterday has closed. Own mutex so the
        // 35-day auto-sync cannot skip the full-day CreatedBefore=23:59 pull.
        $ist($schedule->command('app:fetch-amazon-orders --close-yesterday --with-items')
            ->dailyAt('13:10')
            ->timezone('Asia/Kolkata')
            ->name('amazon-close-yesterday')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

       
        $ist($schedule->command('app:fetch-fba-reports')
            ->dailyAt('18:15')
            ->timezone('Asia/Kolkata')
            ->name('fetch-fba-reports')
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

        $ist($schedule->command('amazon:store-listing-daily-metrics')
            ->dailyAt('18:22')
            ->timezone('Asia/Kolkata')
            ->name('amazon-listing-daily-metrics')
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

        // Safety-net re-run (idempotent upsert for today's PT row) so one missed/failed run
        // does not leave a gap in the A L30 / CVR / price history charts.
        $ist($schedule->command('amazon:collect-metrics')
            ->dailyAt('19:35')
            ->timezone('Asia/Kolkata')
            ->name('amazon-collect-metrics-retry')
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

        // eBay 1 Active / Unsold pull: keeps Missing L current when listings are ended or deleted
        // on eBay (the daily inactive-listings sync alone left it up to a day behind).
        $schedule->command('ebay:fetch-listing-status --store=1')
            ->hourlyAt(40)
            ->timezone('Asia/Kolkata')
            ->name('ebay1-fetch-listing-status')
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo($log);

        // eBay 2 / eBay 3: same Active / Unsold pull. Deleted or ended listings are marked
        // INACTIVE / MISSING so they show on Missing L (ebay_2_metrics keeps the old item_id).
        foreach ([2 => 50, 3 => 55] as $ebayStore => $ebayMinute) {
            $schedule->command('ebay:fetch-listing-status --store='.$ebayStore)
                ->cron($ebayMinute.' */3 * * *')
                ->timezone('Asia/Kolkata')
                ->name('ebay'.$ebayStore.'-fetch-listing-status')
                ->withoutOverlapping(60)
                ->runInBackground()
                ->appendOutputTo($log);
        }

        // Once a day: refresh listing data for every marketplace (same job as the
        // "Refresh listings" button on each listing page).
        $schedule->command('listing:refresh all')
            ->dailyAt('04:20')
            ->timezone('Asia/Kolkata')
            ->name('listing-refresh-all-marketplaces')
            ->withoutOverlapping(360)
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

        // Dil vs SBid autopush. Morning / evening still do a full live C Bid
        // verify. Every 30 minutes (and after inventory / metrics jobs) pushes
        // only ads whose stored C Bid no longer matches S Bid, so Dil / views
        // / CVR changes do not sit yellow until the next 09:50 / 19:50 run.
        // eBay 3 stays off the clock — switch On still pushes after its metrics fetch.
        foreach ([
            ['09:50', 'ebay:dil-sbid-auto-push ebay1', 'ebay1-dil-sbid-morning'],
            ['09:55', 'ebay:dil-sbid-auto-push ebay2', 'ebay2-dil-sbid-morning'],
            ['19:50', 'ebay:dil-sbid-auto-push ebay1', 'ebay1-dil-sbid-evening'],
            ['19:55', 'ebay:dil-sbid-auto-push ebay2', 'ebay2-dil-sbid-evening'],
        ] as [$at, $cmd, $name]) {
            $ist($schedule->command($cmd)
                ->dailyAt($at)
                ->timezone('Asia/Kolkata')
                ->name($name)
                ->withoutOverlapping(90)
                ->runInBackground()
                ->appendOutputTo($log));
        }
        foreach (['ebay1', 'ebay2'] as $account) {
            $ist($schedule->command('ebay:dil-sbid-auto-push '.$account.' --mismatch')
                ->everyThirtyMinutes()
                ->timezone('Asia/Kolkata')
                ->name($account.'-dil-sbid-onchange')
                ->withoutOverlapping(90)
                ->runInBackground()
                ->appendOutputTo($log));
        }

        // eBay 3 per-SKU Price / Dil snapshots for /ebay3-tabulator-view Sprc Dil history
        $ist($schedule->command('ebay3:collect-metrics')
            ->dailyAt('19:21')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-collect-metrics')
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

        // /shopify reads shopify_raw_orders. This must run 24/7 — the IST
        // 09:00–20:00 window is Pacific night, and the :00 IST stampede was
        // skipping this job (last success 2026-08-28; no sales after that).
        $schedule->command('shopify:sync-orders --days=2')
            ->hourlyAt(12)
            ->timezone('America/Los_Angeles')
            ->name('shopify-sync-orders-recent')
            ->withoutOverlapping(self::HF_MUTEX_HOURLY)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('shopify:sync-orders --days=60')
            ->dailyAt('09:08')
            ->timezone('Asia/Kolkata')
            ->name('shopify-sync-orders-backfill')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log);

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

        // Shopify B2C S PRC is saved + pushed by shopify-b2c:sprc-dil-auto-push (04:50 / 20:50 IST, see below).

        $ist($schedule->command('app:snapshot-shopify-b2c-badges')
            ->twiceDaily(11, 19)
            ->name('shopify-b2c-badge-snapshot')
            ->withoutOverlapping(120)
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

        // Must run through the Pacific selling day. The IST 09:00–20:00 window
        // is overnight Pacific and stops around 07:30 PT, so orders placed
        // after that never reached reverb_daily_data until the next night.
        $schedule->command('reverb:daily --days=60')
            ->everyTwoHours()
            ->timezone('America/Los_Angeles')
            ->name('reverb-daily')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log);

        $ist($schedule->command('reverb:collect-metrics')
            ->dailyAt('10:05')
            ->timezone('Asia/Kolkata')
            ->name('reverb-collect-metrics')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('aliexpress', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('alibaba', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('alibaba-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('reverb', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('reverb-manager-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('newegg', '', true, 14))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('newegg-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('shein', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('shein-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('amazon', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('amazon-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('topdawg', '', true, 2))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('topdawg-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        
        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('temu', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('temu-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        // Fetch + auto-import Temu 2 to Shopify (duplicate check links existing copies; no second order).
        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('temu2', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('temu2-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('temu3', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('temu3-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('purchasingpower', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('purchasingpower-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('wayfair', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('wayfair-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('bestbuy', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('bestbuy-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('macy', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('macy-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('doba', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('doba-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('ebay1', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay1-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('ebay2', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay2-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('ebay3', '', true, 14))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay3-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('faire', '', true, 14))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('faire-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('tiktok', '', true, 7))
            ->everyTenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('tiktok-sync-orders')
            ->withoutOverlapping(25)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('tiktok2', '', true, 7))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('tiktok2-sync-orders')
            ->withoutOverlapping(55)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceOrdersJob('b5cb2b', '', true, 60))
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('b5cb2b-sync-orders')
            ->withoutOverlapping(55)
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

        $ist($schedule->command('macys:rule-sprice-apply')
            ->hourly()
            ->name('macys-rule-sprice-apply')
            ->withoutOverlapping(180)
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

        $ist($schedule->command('app:fetch-temu2-orders --days=60')
            ->dailyAt('14:20')
            ->timezone('Asia/Kolkata')
            ->name('fetch-temu2-orders')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-temu3-orders --days=60')
            ->dailyAt('14:22')
            ->timezone('Asia/Kolkata')
            ->name('fetch-temu3-orders')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-pls-sales-data --days=90')
            ->twiceDaily(9, 18)
            ->name('fetch-pls-sales-data')
            ->withoutOverlapping(90)
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

        $ist($schedule->command('app:fetch-temu3-metrics')
            ->dailyAt('14:45')
            ->timezone('Asia/Kolkata')
            ->name('fetch-temu3-metrics')
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

        // Shein sales → shein_daily_data / shein_daily_data_l60.
        // Do not wrap in $ist(). between(09:00, 20:00) is checked against the
        // clock when schedule:run starts, so cron:run-missed at 20:15 never
        // sees a skipped 15:10 slot and that day stays stale. Pacific yesterday
        // closes at 12:30 IST; later slots cover a missed minute (schedule:run
        // is overloaded and has skipped single daily slots).
        foreach (['13:20', '15:10', '19:40'] as $slot) {
            $schedule->command('shein:fetch orders --days=30 --target=l30')
                ->dailyAt($slot)
                ->timezone('Asia/Kolkata')
                ->name('shein-fetch-orders-l30-'.str_replace(':', '', $slot))
                ->withoutOverlapping(90)
                ->runInBackground()
                ->appendOutputTo($log);
        }

        foreach (['13:35', '15:20', '19:50'] as $slot) {
            $schedule->command('shein:fetch orders --days=60 --target=l60')
                ->dailyAt($slot)
                ->timezone('Asia/Kolkata')
                ->name('shein-fetch-orders-l60-'.str_replace(':', '', $slot))
                ->withoutOverlapping(90)
                ->runInBackground()
                ->appendOutputTo($log);
        }
        $ist($schedule->command('app:fetch-pls-data')
            ->twiceDaily(9, 18)
            ->name('fetch-pls-data')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        
        $ist($schedule->command('newegg:orders --days=60 --save')
            ->twiceDaily(9, 18)
            ->name('fetch-newegg-orders')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('newegg:item-data --save --source=catalog')
            ->cron('30 */3 * * *')
            ->name('fetch-newegg-item-prices')
            ->withoutOverlapping(15)
            ->runInBackground()
            ->appendOutputTo($log));

        // Item Basic Info report — refreshes newegg_items so Missing L can see new/removed SKUs.
        $ist($schedule->command('newegg:items --save')
            ->twiceDaily(10, 18)
            ->name('fetch-newegg-item-catalog')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('sync:walmart-metrics-data')
            ->everyMinute()
            ->name('sync-walmart-metrics')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_MINUTE)
            ->runInBackground()
            ->appendOutputTo($log));

        // Night slots — do not wrap in $ist() (09:00–20:00), or they never fire.
        $schedule->command('tiktok:fetch-orders --days=60 --prune')
            ->dailyAt('02:10')
            ->timezone('Asia/Kolkata')
            ->name('tiktok-fetch-orders')
            ->withoutOverlapping(170)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('tiktok:fetch-orders --channel=tiktok2 --days=60 --prune')
            ->dailyAt('02:25')
            ->timezone('Asia/Kolkata')
            ->name('tiktok2-fetch-orders')
            ->withoutOverlapping(170)
            ->runInBackground()
            ->appendOutputTo($log);

        $ist($schedule->command('app:update-marketplace-daily-metrics')
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('update-marketplace-daily-metrics')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_FIVE)
            ->runInBackground()
            ->appendOutputTo($log));

     
        // 24/7 every 5 min + --force — do not wrap in $ist() (that window stops after 20:00 IST).
        $schedule->command('channel:calculate-data --force')
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('channel-master-calculate-data')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_FIVE)
            ->runInBackground()
            ->appendOutputTo($log);

        // Today Sales on /all-marketplace-master — hourly 9 AM–10 PM EST/EDT.
        // Lightweight persist so the column does not sit at $0 while calculate-data holds its lock.
        $schedule->command('channel:refresh-today-sales')
            ->cron('0 9-22 * * *')
            ->timezone('America/New_York')
            ->name('channel-refresh-today-sales')
            ->withoutOverlapping(self::HF_MUTEX_HOURLY)
            ->runInBackground()
            ->appendOutputTo($log);

        $retryFiveTimesUntil('sync:tiktok-api-data', 'sync-tiktok-api-data', '15:45');
        $retryFiveTimesUntil('sync:tiktok-api-data --channel=tiktok2', 'sync-tiktok2-api-data', '16:00');
    }

    /**
     * Live / competitor / listed / S-PRC price jobs. Run after sales pulls.
     */
    protected function schedulePriceCommands(Schedule $schedule, string $log, \Closure $ist, \Closure $retryFiveTimesUntil): void
    {

        $ist($schedule->command('app:fetch-fba-inventory --insert --prices')
            ->dailyAt('14:00')
            ->timezone('Asia/Kolkata')
            ->name('fetch-fba-inventory')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // amazon:dil-prmt-auto-push is not scheduled. Amazon Analytics uses Sprc Dil
        // (amazon_dil_vs_groi Target NROI), not Dil vs PRMT. Do not re-enable that cron.

        // Amazon Sprc Dil (Dil→NROI) + CVR/Rev Disc + LMP cap → SPRICE → Listings.
        // 04:00 and 20:00 IST. Do NOT wrap 20:00 in $ist() — IST window ends at 20:00.
        $schedule->command('amazon:sprc-dil-auto-push')
            ->dailyAt('04:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-sprc-dil-auto-push-4am-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:sprc-dil-auto-push')
            ->dailyAt('20:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-sprc-dil-auto-push-8pm-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // AliExpress Std prc vs dil (Std − Age/Dil/B/0 Sold/CVR/Rev Disc, LMP cap) → SPRICE → AliExpress.
        // Same twice-daily idea as Amazon, offset 15 min. /aliexpress-pricing does not need to be open.
        // Do NOT wrap 20:15 in $ist() — IST window ends at 20:00.
        $schedule->command('aliexpress:sprc-dil-auto-push')
            ->dailyAt('04:15')
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-sprc-dil-auto-push-4am-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('aliexpress:sprc-dil-auto-push')
            ->dailyAt('20:15')
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-sprc-dil-auto-push-8pm-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Headless "Push on reload" for /aliexpress-pricing (same as amazon:push-blue-prices): re-prices every SKU (rules,
        // SNROI Cap, LMP cap), saves S PRC where it changed and pushes blue-triangle SKUs (INV > 0, Price ≠ S PRC).
        // A push writes the Price column at once. Every 10 min, 24/7 on purpose (no $ist()), page closed. Starts at :05.
        $schedule->command('aliexpress:push-blue-prices')
            ->cron('5-59/10 * * * *')
            ->name('aliexpress-push-blue-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // DISABLED: Temu 2 auto-push is off. Prices push only from the /new-temu2 push button.
        if (false) {
        // New Temu Two Std prc vs dil (Std − Age/Dil/B/0 Sold/CVR/Rev Disc, LMP cap) → S PRC → Temu 2 base price.
        // Same twice-daily idea as Amazon, offset 20 min. /new-temu2 does not need to be open.
        // Do NOT wrap 20:20 in $ist() — IST window ends at 20:00.
        $schedule->command('newtemutwo:sprc-dil-auto-push')
            ->dailyAt('04:20')
            ->timezone('Asia/Kolkata')
            ->name('newtemutwo-sprc-dil-auto-push-4am-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('newtemutwo:sprc-dil-auto-push')
            ->dailyAt('20:20')
            ->timezone('Asia/Kolkata')
            ->name('newtemutwo-sprc-dil-auto-push-8pm-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);
        }

        // Reverb Std prc vs dil (Std − Age/Dil/B/0 Sold/CVR/Rev Disc, LMP cap) → S PRC → Reverb price API.
        // Same twice-daily idea as Amazon, offset 25 min. /reverb-pricing does not need to be open.
        // Do NOT wrap 20:25 in $ist() — IST window ends at 20:00.
        $schedule->command('reverb:sprc-dil-auto-push')
            ->dailyAt('04:25')
            ->timezone('Asia/Kolkata')
            ->name('reverb-sprc-dil-auto-push-4am-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('reverb:sprc-dil-auto-push')
            ->dailyAt('20:25')
            ->timezone('Asia/Kolkata')
            ->name('reverb-sprc-dil-auto-push-8pm-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Blue-triangle catch-up for /reverb-pricing (INV > 0, RV Price ≠ S PRC): every 10 min, 24/7, page not needed (same as Amazon).
        $schedule->command('reverb:push-blue-prices')
            ->everyTenMinutes()
            ->name('reverb-push-blue-prices')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // TopDawg Std prc vs dil (Std − Age/Dil/B/0 Sold/CVR/Rev Disc, no ship, no LMP) → S PRC → TopDawg price API.
        // Same twice-daily idea as Amazon, offset 30 min. /topdawg-pricing does not need to be open.
        // Do NOT wrap 20:30 in $ist() — IST window ends at 20:00.
        $schedule->command('topdawg:sprc-dil-auto-push')
            ->dailyAt('04:30')
            ->timezone('Asia/Kolkata')
            ->name('topdawg-sprc-dil-auto-push-4am-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('topdawg:sprc-dil-auto-push')
            ->dailyAt('20:30')
            ->timezone('Asia/Kolkata')
            ->name('topdawg-sprc-dil-auto-push-8pm-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Headless "Push on reload" for /topdawg-pricing (same as amazon:push-blue-prices): blue-triangle SKUs
        // (INV > 0, TD Price ≠ S PRC) are checked every 10 min, 24/7 on purpose (no $ist()). TopDawg reviews a
        // push for 1–24h, so the same price is only re-submitted after the 24h cooldown.
        $schedule->command('topdawg:push-blue-prices')
            ->everyTenMinutes()
            ->name('topdawg-push-blue-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // Purchasing Power Std prc vs dil (Std − Age/Dil/B/0 Sold/CVR/Rev Disc, Amz rule) → S PRC → MCM price import.
        // Same twice-daily idea as Amazon, offset 35 min. /purchasing-power-pricing does not need to be open.
        // Do NOT wrap 20:35 in $ist() — IST window ends at 20:00.
        $schedule->command('purchasing-power:sprc-dil-auto-push')
            ->dailyAt('04:35')
            ->timezone('Asia/Kolkata')
            ->name('purchasing-power-sprc-dil-auto-push-4am-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('purchasing-power:sprc-dil-auto-push')
            ->dailyAt('20:35')
            ->timezone('Asia/Kolkata')
            ->name('purchasing-power-sprc-dil-auto-push-8pm-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Headless "Push on reload" for /purchasing-power-pricing (same as amazon:push-blue-prices): blue-triangle
        // SKUs (INV > 0, PP Price ≠ S PRC) are checked every 10 min, 24/7 on purpose (no $ist()). Replaces the old
        // rule apply that ran after every MCM price sync.
        // Starts at :04 so it does not load the tabulator rows together with the :00 jobs (Amazon, Faire, TopDawg, ...).
        $schedule->command('purchasing-power:push-blue-prices')
            ->cron('4-59/10 * * * *')
            ->name('purchasing-power-push-blue-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // Faire Std prc vs dil (Std − Age/Dil/B/0 Sold/CVR/Rev Disc, no ship, no LMP) → S PRC → Faire wholesale price API.
        // Same twice-daily idea as Amazon, offset 45 min. /faire-pricing does not need to be open.
        // Do NOT wrap 20:45 in $ist() — IST window ends at 20:00.
        $schedule->command('faire:sprc-dil-auto-push')
            ->dailyAt('04:45')
            ->timezone('Asia/Kolkata')
            ->name('faire-sprc-dil-auto-push-4am-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('faire:sprc-dil-auto-push')
            ->dailyAt('20:45')
            ->timezone('Asia/Kolkata')
            ->name('faire-sprc-dil-auto-push-8pm-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Shopify B2C Std prc vs dil (Std − Age/Dil/B/0 Sold/CVR/Rev Disc, LMP cap) → S PRC → Shopify live price.
        // Same twice-daily idea as Amazon, offset 50 min. /shopify-b2c-pricing does not need to be open.
        // Saves S PRC, then queues the push for every SKU whose S PRC ≠ live Price (negative SNROI is skipped).
        // Do NOT wrap 20:50 in $ist() — IST window ends at 20:00.
        $schedule->command('shopify-b2c:sprc-dil-auto-push')
            ->dailyAt('04:50')
            ->timezone('Asia/Kolkata')
            ->name('shopify-b2c-sprc-dil-auto-push-4am-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('shopify-b2c:sprc-dil-auto-push')
            ->dailyAt('20:50')
            ->timezone('Asia/Kolkata')
            ->name('shopify-b2c-sprc-dil-auto-push-8pm-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Headless "Push on reload" for /shopify-b2c-pricing (same as bestbuy:push-blue-prices): re-prices every SKU,
        // saves S PRC where it changed, then pushes blue-triangle SKUs (INV > 0, Price ≠ S PRC) to Shopify.
        // Every 10 min, 24/7 on purpose (no $ist()), so a price change is pushed with the page closed.
        $schedule->command('shopify-b2c:push-blue-prices')
            ->everyTenMinutes()
            ->name('shopify-b2c-push-blue-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // Headless "Push on reload" for /shopify-b2b-pricing (same as shopify-b2c:push-blue-prices): prices every listed SKU with the
        // page's Sprc Dil formula (clearance SKUs included), saves S PRC and pushes SKUs whose live Price differs, page closed.
        // Every 10 min, 24/7 (no $ist()), starts at :08. --respect-switch: the page Push on reload switch decides.
        $schedule->command('shopify-b2b:push-blue-prices --respect-switch')
            ->cron('8-59/10 * * * *')
            ->name('shopify-b2b-push-blue-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // Headless "Push on reload" for /ebay-tabulator-view, /ebay2-tabulator-view, /ebay3-tabulator-view (same as
        // amazon:push-blue-prices): re-prices every SKU, saves S PRC where it changed, pushes blue-triangle SKUs
        // (INV > 0, Price ≠ S PRC) to eBay, then pulls the live price back. Every 10 min, 24/7 (no $ist()), page closed.
        // The three accounts start 3 minutes apart so they do not load the tabulator rows together.
        foreach (['ebay1' => '0-59/10', 'ebay2' => '3-59/10', 'ebay3' => '6-59/10'] as $ebayAccount => $ebayMinutes) {
            $schedule->command('ebay:push-blue-prices '.$ebayAccount)
                ->cron($ebayMinutes.' * * * *')
                ->name($ebayAccount.'-push-blue-prices')
                ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
                ->runInBackground()
                ->appendOutputTo($log);
        }

        // eBay 1 volume pricing (Buy 2/3/4 = Weight + Dil + Std NPFT) → eBay promotions. Only listings whose
        // discount changed are re-sent. /ebay-volume-pricing does not need to be open. Runs only when the
        // page has Rules On and Auto push On. Do NOT wrap 21:30 in $ist() — IST window ends at 20:00.
        $schedule->command('ebay1:volume-pricing-auto-push')
            ->dailyAt('05:30')
            ->timezone('Asia/Kolkata')
            ->name('ebay1-volume-pricing-auto-push-530am-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('ebay1:volume-pricing-auto-push')
            ->dailyAt('21:30')
            ->timezone('Asia/Kolkata')
            ->name('ebay1-volume-pricing-auto-push-930pm-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Headless "Push on reload" for /faire-pricing (same as amazon:push-blue-prices): blue-triangle SKUs
        // (INV > 0, Faire Price ≠ S PRC) are checked every 10 min, 24/7 on purpose (no $ist()).
        $schedule->command('faire:push-blue-prices')
            ->everyTenMinutes()
            ->name('faire-push-blue-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // TikTok / TikTok 2 Std prc vs dil (Std − Age/Dil/B/0 Sold/CVR/Rev Disc, LMP cap, Std max)
        // → S PRC → TikTok Shop price API. Same twice-daily idea as Amazon. /tiktok-pricing and /tiktok-2-pricing do not need
        // to be open. Evening runs are NOT wrapped in $ist() — the IST window ends at 20:00.
        foreach ([
            ['tiktok', '06:15', '22:15', 'tiktok'],
            ['tiktok2', '06:25', '22:25', 'tiktok2'],
        ] as [$ttChannel, $ttMorning, $ttEvening, $ttSlug]) {
            $schedule->command('tiktok:sprc-dil-auto-push --channel='.$ttChannel)
                ->dailyAt($ttMorning)
                ->timezone('Asia/Kolkata')
                ->name($ttSlug.'-sprc-dil-auto-push-am-ist')
                ->withoutOverlapping(180)
                ->runInBackground()
                ->appendOutputTo($log);

            $schedule->command('tiktok:sprc-dil-auto-push --channel='.$ttChannel)
                ->dailyAt($ttEvening)
                ->timezone('Asia/Kolkata')
                ->name($ttSlug.'-sprc-dil-auto-push-pm-ist')
                ->withoutOverlapping(180)
                ->runInBackground()
                ->appendOutputTo($log);

            // Headless "Push on reload" (same as amazon:push-blue-prices): blue-triangle SKUs (INV > 0, TT Price ≠ S PRC)
            // are checked every 10 min, 24/7 on purpose (no $ist()).
            $schedule->command('tiktok:push-blue-prices --channel='.$ttChannel)
                ->everyTenMinutes()
                ->name($ttSlug.'-push-blue-prices')
                ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
                ->runInBackground()
                ->appendOutputTo($log);
        }

        // Push and pull together (same as amazon:pull-pushed-prices): a pushed SKU whose live TikTok price still differs is
        // re-read every minute until the Price column shows the pushed S PRC. Covers TikTok and TikTok 2.
        $schedule->command('tiktok:pull-pushed-prices')
            ->everyMinute()
            ->name('tiktok-pull-pushed-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_MINUTE)
            ->runInBackground()
            ->appendOutputTo($log);

        // Alibaba Std prc vs dil (Std − Age/Dil/B/0 Sold/CVR/Rev Disc, Std max, no LMP)
        // → S PRC saved twice daily (--skip-push). Alibaba is NOT pushed from here: /alibaba-analytics pushes
        // S PRC (calc − Ship) itself, on the first page open of the day or with its Push now button.
        // Evening run is NOT wrapped in $ist() — the IST window ends at 20:00.
        $schedule->command('alibaba:sprc-dil-auto-push --skip-push')
            ->dailyAt('08:05')
            ->timezone('Asia/Kolkata')
            ->name('alibaba-sprc-dil-auto-push-am-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('alibaba:sprc-dil-auto-push --skip-push')
            ->dailyAt('23:55')
            ->timezone('Asia/Kolkata')
            ->name('alibaba-sprc-dil-auto-push-pm-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // S PRC kept in step with the rules every 10 min (save only, nothing is pushed), same as /topdawg-pricing:
        // a rule, Std Prc or Ship change shows on the page without waiting for the twice-daily run.
        $schedule->command('alibaba:sprc-dil-auto-push --skip-push')
            ->everyTenMinutes()
            ->name('alibaba-sprc-dil-save-10min')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // Headless "Push on reload" for /alibaba-analytics (same as amazon:push-blue-prices): blue-triangle SKUs
        // (INV > 0, Alibaba Price ≠ S PRC) are pushed with the page closed, so a rule or SNROI Cap change goes live within
        // minutes. The S PRC saved by the job above is already calc − Ship (the same price the page pushes), so the formula
        // is unchanged. Alibaba reviews price changes: a pushed price is held 6 h, then --max-pushes keeps a run short.
        // --respect-switch: the page Push on reload switch decides; when it is OFF this job does nothing.
        $schedule->command('alibaba:push-blue-prices --respect-switch')
            ->cron('7-59/10 * * * *')
            ->name('alibaba-push-blue-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // Push and pull together (same as amazon:pull-pushed-prices): every pushed SKU is read back right after the
        // push; Alibaba reviews price changes, so this re-reads the pushed SKUs until the Price column matches.
        $schedule->command('alibaba:pull-pushed-prices')
            ->everyFiveMinutes()
            ->name('alibaba-pull-pushed-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // Shein Std prc vs dil (Std − Age/Dil/B/0 Sold/CVR/Rev Disc, LMP cap, Std max)
        // → S PRC → Shein product/price/save. Same twice-daily idea as Amazon. /shein-pricing does not need to be open.
        // Evening run is NOT wrapped in $ist() — the IST window ends at 20:00.
        $schedule->command('shein:sprc-dil-auto-push')
            ->dailyAt('06:55')
            ->timezone('Asia/Kolkata')
            ->name('shein-sprc-dil-auto-push-am-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('shein:sprc-dil-auto-push')
            ->dailyAt('22:55')
            ->timezone('Asia/Kolkata')
            ->name('shein-sprc-dil-auto-push-pm-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Headless "Push on reload" for /shein-pricing (same as amazon:push-blue-prices): blue-triangle SKUs
        // (INV > 0, Sp. Price ≠ S PRC) are checked every 10 min, 24/7 on purpose (no $ist()).
        $schedule->command('shein:push-blue-prices')
            ->everyTenMinutes()
            ->name('shein-push-blue-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // PLS (ProLightSounds) Std prc vs dil (Std − Age/Dil/B/0 Sold/CVR/Rev Disc, no ads, no LMP, Std max)
        // → S PRC → ProLightSounds Shopify price. Same twice-daily idea as Amazon. /pls-pricing does not need to be open.
        // Evening run is NOT wrapped in $ist() — the IST window ends at 20:00.
        $schedule->command('pls:sprc-dil-auto-push')
            ->dailyAt('07:45')
            ->timezone('Asia/Kolkata')
            ->name('pls-sprc-dil-auto-push-am-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('pls:sprc-dil-auto-push')
            ->dailyAt('23:45')
            ->timezone('Asia/Kolkata')
            ->name('pls-sprc-dil-auto-push-pm-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Headless "Push on reload" for /pls-pricing (same as amazon:push-blue-prices): blue-triangle SKUs
        // (INV > 0, PLS Price ≠ S PRC) are checked every 10 min, 24/7 on purpose (no $ist()).
        $schedule->command('pls:push-blue-prices')
            ->everyTenMinutes()
            ->name('pls-push-blue-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // Best Buy Std prc vs dil (Std − Age/Dil/B/0 Sold/CVR/Rev Disc, LMP cap, Amz rule) → S PRC → Best Buy MCM.
        // Same twice-daily idea as Amazon, offset 40 min. /bestbuy-pricing does not need to be open.
        // Do NOT wrap 20:40 in $ist() — IST window ends at 20:00.
        $schedule->command('bestbuy:sprc-dil-auto-push')
            ->dailyAt('04:40')
            ->timezone('Asia/Kolkata')
            ->name('bestbuy-sprc-dil-auto-push-4am-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('bestbuy:sprc-dil-auto-push')
            ->dailyAt('20:40')
            ->timezone('Asia/Kolkata')
            ->name('bestbuy-sprc-dil-auto-push-8pm-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Headless "Push on reload" for /bestbuy-pricing (same as amazon:push-blue-prices): blue-triangle SKUs
        // (INV > 0, BB Price ≠ S PRC) are checked every 10 min, 24/7 on purpose (no $ist()).
        $schedule->command('bestbuy:push-blue-prices')
            ->everyTenMinutes()
            ->name('bestbuy-push-blue-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // Headless "Push on reload": blue-triangle SKUs (INV > 0, Price ≠ S PRC) keep getting
        // pushed + pulled all day with the page closed. The two daily runs above skip a SKU whose
        // last pushed offer already equals the target, so a drifted Price stayed stale until the
        // page was opened. Always on, every 10 minutes, 24/7 on purpose (no $ist()).
        $schedule->command('amazon:push-blue-prices')
            ->everyTenMinutes()
            ->name('amazon-push-blue-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // Age Disc on every analytics page reads this cached map; keep it warm so pages never wait ~16s.
        $schedule->command('inv-days:warm-age-map')
            ->everyTenMinutes()
            ->name('inv-days-warm-age-map')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // Headless "Push on reload" for /new-temuone (same as amazon:push-blue-prices): re-prices every SKU, saves
        // NTO_SPRICE where it changed, pushes blue-triangle SKUs (INV > 0, S Base ≠ Temu base). Every 10 min, 24/7
        // on purpose (no $ist()), so a price change is pushed with the page closed.
        // --respect-switch: the page Auto push switch decides; when it is OFF this job does nothing.
        $schedule->command('newtemuone:push-blue-prices --respect-switch')
            ->everyTenMinutes()
            ->name('newtemuone-push-blue-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);

        // The twice-daily command below is replaced by the 10-minute job above.
        // Temu 1 is priced by dil:rule-sprice-apply temu in the loop below.
        if (false) {
        // New Temu One Sprc Dil (Dil→SNROI) + CVR + eBay/Amz/LMP cap → NTO_SPRICE,
        // then push S Base to Temu when it differs from the live base.
        // 04:10 and 20:10 IST — same twice-daily idea as Amazon, offset 10 min.
        $schedule->command('newtemuone:sprc-dil-auto-push')
            ->dailyAt('04:10')
            ->timezone('Asia/Kolkata')
            ->name('newtemuone-sprc-dil-auto-push-4am-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('newtemuone:sprc-dil-auto-push')
            ->dailyAt('20:10')
            ->timezone('Asia/Kolkata')
            ->name('newtemuone-sprc-dil-auto-push-8pm-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);
        }

        // Std prc vs dil → S PRC, then push. The analytics page does not need to be open.
        foreach ([
            ['05:25', '21:25', 'dil:rule-sprice-apply newegg --push', 'newegg-sprc-dil'],
            // temu / temu2 auto-push removed: push only by button on /new-temu1 and /new-temu2.
            ['05:55', '21:55', 'dil:rule-sprice-apply temu3 --push', 'temu3-sprc-dil'],
            ['06:35', '22:35', 'dil:rule-sprice-apply doba', 'doba-sprc-dil'],
            // wayfair: once a day only, see the 12:00 AM PT entry below.
            ['07:25', '23:25', 'dil:rule-sprice-apply fb_marketplace', 'fb-marketplace-sprc-dil'],
            ['07:35', '23:35', 'dil:rule-sprice-apply walmart --push', 'walmart-sprc-dil'],
            ['05:50', '21:50', 'macys:rule-sprice-apply --push', 'macys-sprc-dil'],
        ] as [$morning, $evening, $command, $slug]) {
            $schedule->command($command)
                ->dailyAt($morning)
                ->timezone('Asia/Kolkata')
                ->name($slug.'-4am-ist')
                ->withoutOverlapping(180)
                ->runInBackground()
                ->appendOutputTo($log);
            $schedule->command($command)
                ->dailyAt($evening)
                ->timezone('Asia/Kolkata')
                ->name($slug.'-8pm-ist')
                ->withoutOverlapping(180)
                ->runInBackground()
                ->appendOutputTo($log);
        }

        // Std prc vs dil on every pushable page, 3 times a day. Page does not need to be open.
        // Lock + withoutOverlapping stop a second run. Unchanged live prices are not pushed again.
        foreach (['03:15', '11:15', '19:15'] as $at) {
            $schedule->command('std-prc-vs-dil:apply')
                ->dailyAt($at)
                ->timezone('Asia/Kolkata')
                ->name('std-prc-vs-dil-apply-'.$at)
                ->withoutOverlapping(360)
                ->runInBackground()
                ->appendOutputTo($log);
        }

        // Wayfair S PRC: save + push once a day at 12:00 AM Pacific. Every other run skips Wayfair;
        // the /wayfair-pricing "Push now" button covers anything in between.
        $schedule->command('dil:rule-sprice-apply wayfair --push --daily')
            ->dailyAt('00:00')
            ->timezone('America/Los_Angeles')
            ->name('wayfair-sprc-dil-daily-12am-pt')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Once a day at 12:30 AM PT (after the S PRC push above). Was every 30 minutes.
        // The command skips when nothing changed and never re-uploads a file that is already
        // queued / in progress / successful.
        $schedule->command('wayfair:daily-price-upload')
            ->cron((string) config('wayfair_upload.schedule_cron', '30 0 * * *'))
            ->timezone(config('app.timezone'))
            ->name('wayfair-daily-price-upload')
            ->withoutOverlapping(25)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('wayfair:check-upload-status')
            ->everyFifteenMinutes()
            ->name('wayfair-check-upload-status')
            ->withoutOverlapping(10)
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

        /*
        |--------------------------------------------------------------------------
        | LMP COMPETITOR PRICE PULLS (SerpApi) — Amazon: every 2nd day, eBay: every 5th day,
        | Google: weekly.
        |
        | Only LMP-linked competitor rows are pulled (not every search result), and rows are
        | skipped when Ignored, when our SKU Inv = 0, or (Amazon) when the competitor is
        | out of stock. See App\Support\Marketplace\LmpPullEligibility.
        | The old search-wide `amazon:update-prices` / `ebay:update-prices` jobs re-pulled
        | every price (incl. ignored / OOS), so they are no longer scheduled.
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('ebay:update-sku-prices --skip-search-refresh')
            ->dailyAt('15:00')
            ->timezone('Asia/Kolkata')
            ->when($this->everyNthDay(5))
            ->name('ebay-sku-competitor-prices-every-5-days')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:update-sku-prices --skip-search-refresh')
            ->dailyAt('15:00')
            ->timezone('Asia/Kolkata')
            ->when($this->everyNthDay(2))
            ->name('amazon-sku-competitor-prices-every-2-days')
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

        $ist($schedule->command('store:sync-prices')
            ->twiceDaily(10, 18)
            ->name('store-sync-price-sold-views')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log));

        // Listed product price + AE stock → aliexpress_pricing_prices (/aliexpress-pricing).
        $schedule->command('app:fetch-aliexpress-metrics --listed')
            ->dailyAt('04:45')
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-fetch-listed-prices')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);
        // Recommended supply prices → temu_metrics.recommended_base_price (10=low traffic, 20=restricted)
        $retryFiveTimesUntil('temu:fetch-recommended-prices --both', 'temu-recommended-prices', '16:05');

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

      
        $ist($schedule->command('products:recalc-lp')
            ->hourly()
            ->name('products-recalc-lp')
            ->withoutOverlapping(self::HF_MUTEX_HOURLY)
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

        // Confirm leftover Price pulls after S PRC / Push Prc, and restore Sale
        // if listings sync wrote Your Price over a Dil push. 24/7 on purpose.
        $schedule->command('amazon:pull-pushed-prices')
            ->everyMinute()
            ->name('amazon-pull-pushed-prices')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_MINUTE)
            ->runInBackground()
            ->appendOutputTo($log);

        $ist($schedule->command('walmart:fetch-listed-prices')
            ->cron('0 */3 * * *')
            ->name('walmart-fetch-listed-prices')
            ->withoutOverlapping(170)
            ->runInBackground()
            ->appendOutputTo($log));

        // PEF Dil vs PRMT → eBay1 sale-event create is disabled (do not schedule).

        // Pull business5core.com price / sold / views into store_listing_prices.
        $schedule->command('store:sync-prices')
            ->dailyAt('16:30')
            ->timezone('Asia/Kolkata')
            ->name('store-sync-website-prices-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Remaining Dil pages (not eBay / Amazon / Shopify B2C / Macys / PP).
        // Primary fire is /etc/cron.d/dil-rest-sprice-daily (bypasses schedule:run).
        // That script saves each page, then pushes. This backup does the same.
        $schedule->command('dil:rule-sprice-apply --push')
            ->dailyAt('15:45')
            ->timezone('Asia/Kolkata')
            ->name('dil-rule-sprice-apply-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);
        $schedule->command('dil:rule-sprice-apply --push')
            ->dailyAt('16:25')
            ->timezone('Asia/Kolkata')
            ->name('dil-rule-sprice-apply-ist-catchup')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);
        $schedule->command('channel:push-sprice-daily dil')
            ->dailyAt('16:05')
            ->timezone('Asia/Kolkata')
            ->name('channel-push-sprice-daily-dil-ist')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);
        $schedule->command('channel:push-sprice-daily dil')
            ->dailyAt('16:35')
            ->timezone('Asia/Kolkata')
            ->name('channel-push-sprice-daily-dil-ist-catchup')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Blue-triangle catch-up for the other channels. The daily slots above queue S PRC ≠ live Price
        // once; a Price that drifts or a push that failed afterwards stayed blue until someone opened the
        // page. The enqueue only picks SKUs where saved S PRC ≠ live Price, merges into a running job
        // without duplicating pending SKUs, and the runner still applies the negative-SNROI guard and the
        // "already live" check. Staggered, 24/7 on purpose (no $ist()).
        foreach ([
            ['dil', 5, 'dil'],
            ['shopify_b2c', 10, 'shopify-b2c'],
            ['newtemuone', 20, 'newtemuone'],
        ] as [$blueArg, $blueOffset, $blueName]) {
            $schedule->command('channel:push-sprice-daily '.$blueArg)
                ->cron(sprintf('%d,%d * * * *', $blueOffset, $blueOffset + 30))
                ->timezone('Asia/Kolkata')
                ->name('channel-push-sprice-blue-catchup-'.$blueName)
                ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
                ->runInBackground()
                ->appendOutputTo($log);
        }

        // Macys: every 10 minutes (a price saved by the hourly rule apply, or a failed push, goes live within 10 minutes).
        // Inactive / unlisted offers are not queued, so the run only has real pushes to do.
        $schedule->command('channel:push-sprice-daily macys')
            ->cron('3-59/10 * * * *')
            ->name('channel-push-sprice-blue-catchup-macys')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_TEN)
            ->runInBackground()
            ->appendOutputTo($log);
    }

    /**
     * Ads, inventory, sheets, tasks, CRM, tracking, and other jobs.
     */
    protected function scheduleOtherCommands(Schedule $schedule, string $log, \Closure $ist, \Closure $retryFiveTimesUntil): void
    {
        $schedule->command('social-media:sync')
            ->dailyAt((string) config('social_media.schedule_time', '06:20'))
            ->timezone('Asia/Kolkata')
            ->name('social-media-sync')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log);

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

        // CRM Shopify customers — Last synced on /crm/shopify/customers.
        // Do not wrap in $ist(); Eastern midnight is the required daily run.
        $schedule->command('shopify:sync-customers')
            ->dailyAt('00:00')
            ->timezone('America/New_York')
            ->name('crm-shopify-sync-customers-est')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

   
        $taskTz = config('tasks.business_timezone', 'Asia/Kolkata');

        // Daily instances are created at 12:00 PM IST (India calendar). The
        // command no-ops until that clock time, then the 5-minute tick recovers
        // a delayed scheduler. Assignment still comes from automate_tasks.
        $schedule->command('tasks:generate-daily-automated')
            ->everyFiveMinutes()
            ->timezone($taskTz)
            ->name('generate-daily-automated-tasks')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_FIVE)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('chat:daily-nudges')
            ->dailyAt('10:00')
            ->timezone(\App\Support\TaskBusinessTime::tz())
            ->name('chat-daily-nudges')
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo($log);

       
        $schedule->command('tasks:expire-missed-automated')
            ->everyMinute()
            ->timezone($taskTz)
            ->name('expire-missed-automated-tasks')
            ->withoutOverlapping(15)
            ->runInBackground()
            ->appendOutputTo($log);

        
        $schedule->command('tasks:purge-archived', ['--days' => 90])
            ->dailyAt('03:30')
            ->timezone($taskTz)
            ->name('purge-archived-tasks')
            ->withoutOverlapping(120)
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

        // Missing Mapping (/map-issues): push every listed SKU's Shopify qty, then retry leftovers.
        // In-process (not queued) so it still runs when an mm-* worker is down. One process per
        // channel so a hang on one marketplace cannot stop the others. Hourly around the clock
        // (Shopify sells overnight too), start minutes staggered. A channel with nothing to push exits immediately.
        foreach (\App\Console\Commands\PushMissingMappingInventory::CHANNELS as $i => $mmChannel) {
            $minute = ($i * 2) % 60;
            $schedule->command('inventory:push-missing-mapping', ['--channel' => [$mmChannel]])
                ->cron($minute.' * * * *')
                ->timezone('Asia/Kolkata')
                ->name('push-missing-mapping-inventory-'.$mmChannel)
                ->withoutOverlapping(70)
                ->runInBackground()
                ->appendOutputTo($log);
        }

        // Missing Mapping read-back sweep: every 15 min read the live marketplace qty for the next
        // slice of linked SKUs and store it locally. Pushes write their target as the local qty, so
        // without this a SKU the marketplace never applied (Wayfair feed, TopDawg review, PLS sale)
        // looked "matched" forever. One process per channel; the pass/push above fix what surfaces.
        foreach (\App\Services\MarketplaceManager\MarketplaceQtyReadBack::SUPPORTED as $i => $rbChannel) {
            $minute = $i % 15;
            $schedule->command('mm:readback-marketplace-qty', ['--channel' => [$rbChannel]])
                ->cron($minute.','.($minute + 15).','.($minute + 30).','.($minute + 45).' * * * *')
                ->timezone('Asia/Kolkata')
                ->name('mm-readback-marketplace-qty-'.$rbChannel)
                ->withoutOverlapping(25)
                ->runInBackground()
                ->appendOutputTo($log);
        }

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

        $retryFiveTimesUntil('app:amazon-sp-campaign-reports', 'amazon-sp-campaign-reports', '18:00');
        $retryFiveTimesUntil('app:amazon-sb-campaign-reports', 'amazon-sb-campaign-reports', '18:05');
        $retryFiveTimesUntil('app:amazon-sd-campaign-reports', 'amazon-sd-campaign-reports', '18:10');
        $retryFiveTimesUntil('amazon:ads-pull-product-ads', 'amazon-ads-pull-product-ads', '18:12');

        // ENABLED campaigns with no impressions are missing from Amazon's reports. Pull them from the
        // campaign list on its own schedule so a failed report run (or a campaign enabled mid-day)
        // never leaves /amazon-ads/all short of the Amazon console.
        $schedule->command('amazon:ads-sync-enabled-campaigns')
            ->hourlyAt(20)
            ->timezone('Asia/Kolkata')
            ->name('amazon-ads-sync-enabled-campaigns')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

      
        $retryFiveTimesUntil('app:amazon-sp-keyword-reports', 'amazon-sp-keyword-reports', '18:15');
        $retryFiveTimesUntil('app:amazon-sp-negative-keywords --prune', 'amazon-sp-negative-keywords', '18:20');
        // Both Amazon Ads jobs run every 10 minutes in their own background process, so a slow run never delays the
        // scheduler or the other jobs. The lock expires after 9 minutes so one stuck run cannot block the next slot.
        $schedule->command('amazon-ads:warm-grid')
            ->everyTenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('amazon-ads-warm-grid')
            ->withoutOverlapping(9)
            ->runInBackground()
            ->appendOutputTo($log);
        $schedule->command('amazon-ads:bgt-counts')
            ->everyTenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('amazon-ads-bgt-counts')
            ->withoutOverlapping(9)
            ->runInBackground()
            ->appendOutputTo($log);

        $ist($schedule->command('app:amazon-ads-target-counts')
            ->dailyAt('18:22')
            ->timezone('Asia/Kolkata')
            ->name('amazon-ads-target-counts')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));
        // Dil% ≥ threshold pauses PARENT + child SKU campaigns. Only recently Dil-paused PARENT campaigns auto-enable.
        $retryFiveTimesUntil('amazon:ads-pause-rule', 'amazon-ads-pause-rule', '18:25');

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

        // Post-20:00 Amazon BGT — must NOT use $ist() (09:00–20:00), or they never fire.
        $schedule->command('amazon:auto-update-amz-bgt-kw')
            ->dailyAt('20:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-kw')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-amz-bgt-pt')
            ->dailyAt('20:05')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-pt')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-amz-bgt-hl')
            ->dailyAt('20:10')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-hl')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        // Catch-up: re-apply only remaining BGT≠SBGT deltas (skips already-applied; retries Amazon errors).
        $schedule->command('amazon:auto-update-amz-bgt-kw')
            ->dailyAt('21:30')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-kw-catchup')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-amz-bgt-pt')
            ->dailyAt('21:35')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-pt-catchup')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:auto-update-amz-bgt-hl')
            ->dailyAt('21:40')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-hl-catchup')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        // Recalculates the grid SBGT, then pushes every Enabled bid/budget that
        // still differs. Opening the page is not required.
        $schedule->command('amazon:ads-live-bid-bgt-sync --limit=200 --retry-failed')
            ->dailyAt('21:50')
            ->timezone('Asia/Kolkata')
            ->name('amazon-ads-live-bid-bgt-sync')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log);

        // Every 30 minutes, page open or not: recalculates the grid SBID/SBGT, pulls live
        // Lbid/Lbgt, and pushes every Enabled campaign that still differs. Failures wait
        // 3 hours here and are always retried by the 21:50 run.
        $schedule->command('amazon:ads-live-bid-bgt-sync --limit=100')
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('amazon-ads-live-bid-bgt-sync-followup')
            ->withoutOverlapping(25)
            ->runInBackground()
            ->appendOutputTo($log);

        // Saves each campaign's Lbid once per California day (last value of that day) for the Lbid
        // history dot. Verified live pulls also write today's value as they happen.
        $schedule->command('amazon:ads-lbid-daily-snapshot')
            ->dailyAt('23:55')
            ->timezone('America/Los_Angeles')
            ->name('amazon-ads-lbid-daily-snapshot')
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo($log);

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

        $ist($schedule->command('amazon-fba:store-utilization-counts')
            ->dailyAt('19:50')
            ->timezone('Asia/Kolkata')
            ->name('fba-utilization-counts')
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

        $retryFiveTimesUntil('app:ebay-campaign-reports', 'ebay-campaign-reports', '19:10');
        $retryFiveTimesUntil('app:ebay2-campaign-reports', 'ebay2-campaign-reports', '19:15');
        $retryFiveTimesUntil('app:ebay3-campaign-reports', 'ebay3-campaign-reports', '19:20');

        
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

        // After campaign-ads sync: Eligible (RECOMMENDED) listings → matching parent PMT campaign.
        $schedule->command('ebay2:auto-enroll-eligible')
            ->dailyAt('20:50')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-auto-enroll-eligible')
            ->withoutOverlapping(60)
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

        $schedule->command('ebay:store-utilization-counts')
            ->dailyAt('21:40')
            ->timezone('Asia/Kolkata')
            ->name('ebay-utilization-counts')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

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

        // BGT Rules "Pause ads if price below $X" — hourly, so low-priced ads are paused without waiting for the daily budget push.
        $schedule->command('google:pause-low-price-shopping')
            ->hourlyAt(15)
            ->name('google-pause-low-price-shopping')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // Excluded (grey "−") / paused Item IDs are removed from Shopping campaigns hourly.
        $schedule->command('google:remove-inactive-shopping-items')
            ->hourlyAt(30)
            ->name('google-remove-inactive-shopping-items')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // "Ads Needed": create ads for SKUs with INV > 0, pause (remove) ads for SKUs with INV <= 0 — hourly.
        $schedule->command('google:sync-shopping-ads-by-inventory')
            ->hourlyAt(45)
            ->name('google-sync-shopping-ads-by-inventory')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

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

        // Read Shopping LBid/LBgt from Google Ads after the daily SBID and budget pushes.
        // Runs in this process. The shopping page never starts this.
        $schedule->command('google-shopping:sync-live-bid-bgt')
            ->dailyAt('18:00')
            ->timezone('Asia/Kolkata')
            ->name('google-shopping-live-bid-bgt')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // LT CPC column: today's average CPC straight from the Google Ads API, every 15 minutes (read-only).
        $schedule->command('google-shopping:sync-live-cpc')
            ->cron('*/15 * * * *')
            ->timezone('Asia/Kolkata')
            ->name('google-shopping-live-cpc')
            ->withoutOverlapping(10)
            ->runInBackground()
            ->appendOutputTo($log);

        // Every 10 minutes, page open or not: pulls live Lbid/Lbgt, pushes SBID/SBGT to every Enabled
        // Shopping campaign that still differs, pulls again to confirm. Stays out of the way of the
        // daily 17:48 SBID push, the budget pushes and the 18:00 live pull.
        $schedule->command('google-shopping:match-live-bid-bgt')
            ->cron('*/10 * * * *')
            ->timezone('Asia/Kolkata')
            ->name('google-shopping-match-live-bid-bgt')
            ->skip(static function (): bool {
                $now = now('Asia/Kolkata');
                $m = (int) $now->format('Hi');

                return $m >= 1740 && $m <= 1815;
            })
            ->withoutOverlapping(15)
            ->runInBackground()
            ->appendOutputTo($log);

        // Reset SBID status daily — must complete before sbid:update at 17:48 IST.
        // withoutOverlapping(2) keeps the daily reset single-fire even if a tick is delayed.
        $schedule->call(function () {
            try {
                if (Schema::hasTable('google_ads_campaigns') && Schema::hasColumn('google_ads_campaigns', 'sbid_status')) {
                    DB::table('google_ads_campaigns')
                        ->where('id', 1)
                        ->update(['sbid_status' => 0]);
                }
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

        // Description Master A+: one-time Shopify description snapshot per SKU (only SKUs not yet fetched).
        $ist($schedule->command('description:shopify-aplus-backfill --limit=150')
            ->hourlyAt(37)
            ->name('description-shopify-aplus-backfill')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log));

        // Listing Manager: keep the stored product-modal payload (Amazon / Main Store / drafts) warm
        // so /listing-manager opens from the database. Missing SKUs first, then snapshots older than 24h.
        $ist($schedule->command('listing-manager:snapshot-refresh --limit=120')
            ->hourlyAt(52)
            ->name('listing-manager-snapshot-refresh')
            ->withoutOverlapping(55)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('lqs:sync-shopify-seo')
            ->dailyAt('04:40')
            ->timezone('Asia/Kolkata')
            ->name('lqs-shopify-yoast-seo')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('sync:shopify-quantity')
            ->twiceDaily(9, 18)
            ->name('sync-shopify-quantity')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // Verification Accept: recover Shopify 429s outside the request so rows do not stay failed.
        $schedule->command('verification:retry-shopify-adjustments --limit=25')
            ->everyTwoMinutes()
            ->name('verification-retry-shopify-adjustments')
            ->withoutOverlapping(8)
            ->runInBackground()
            ->appendOutputTo($log);

        $ist($schedule->command('app:fetch-shopify-product-views --days=30')
            ->twiceDaily(10, 18)
            ->name('shopify-product-views')
            ->withoutOverlapping(60)
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

        $ist($schedule->command('reverb:sync-listing-statuses')
            ->dailyAt('10:00')
            ->timezone('Asia/Kolkata')
            ->name('reverb-sync-listing-statuses')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('inactive-listings:sync')
            ->dailyAt('10:20')
            ->timezone('Asia/Kolkata')
            ->name('inactive-listings-daily-sync')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('inactive-listings:warm-page')
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('inactive-listings-warm-page')
            ->withoutOverlapping(20)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('missing-listing:refresh-catalogs --force')
            ->dailyAt('10:35')
            ->timezone('Asia/Kolkata')
            ->name('missing-listing-catalog-refresh')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('missing-listing:warm-page')
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('missing-listing-warm-page')
            ->withoutOverlapping(15)
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | TOPDAWG
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('topdawg:fetch')
            ->twiceDaily(10, 18)
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

        // Shopify label/tracking → AliExpress declare/modify shipment (settings-gated).
        $schedule->job(new \App\Jobs\SyncAliexpressTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-sync-tracking')
            ->withoutOverlapping(18)
            ->appendOutputTo($log);

        // AliExpress receipt address → fill missing Shopify shipping + customer address.
        $schedule->job(new \App\Jobs\SyncAliexpressAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        // L30 page views → aliexpress_metric.views (/aliexpress-pricing Views column).
        $schedule->command('app:fetch-aliexpress-metrics --views')
            ->dailyAt('05:15')
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-fetch-page-views')
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo($log);

        // Review count + rating → aliexpress_metric.reviews (/aliexpress-pricing Reviews column).
        $schedule->command('app:fetch-aliexpress-metrics --reviews')
            ->dailyAt('05:45')
            ->timezone('Asia/Kolkata')
            ->name('aliexpress-fetch-reviews')
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

        // Shopify label/tracking → Alibaba declare/modify shipment (settings-gated).
        $schedule->job(new \App\Jobs\SyncAlibabaTrackingJob(true, 80))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('alibaba-sync-tracking')
            ->withoutOverlapping(20)
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

        // Shopify label/tracking → Reverb mark shipped (settings-gated).
        $schedule->job(new \App\Jobs\SyncReverbTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('reverb-sync-tracking')
            ->withoutOverlapping(18)
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

        // /alibaba-analytics: product detail for SKU, price, and stock. ~769 products.
        $schedule->command('alibaba:sync-analytics')
            ->dailyAt('06:30')
            ->timezone('Asia/Kolkata')
            ->name('alibaba-sync-analytics')
            ->withoutOverlapping(180)
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

        // Shopify label/tracking → Newegg Ship Order (settings-gated).
        $schedule->job(new \App\Jobs\SyncNeweggTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('newegg-sync-tracking')
            ->withoutOverlapping(18)
            ->appendOutputTo($log);

        // Newegg ShipTo / buyer → fill missing Shopify shipping + customer address.
        $schedule->job(new \App\Jobs\SyncNeweggAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('newegg-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('newegg:sync-link-map --force')
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

        $schedule->job(new \App\Jobs\SyncSheinTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('shein-sync-tracking')
            ->withoutOverlapping(18)
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

        // Runs in the scheduler, not on mm-amazon: a backed-up Amazon queue (order fetch,
        // address jobs) kept the mismatch job waiting for hours and mismatches never cleared.
        $schedule->command('marketplace:sync-mismatch-inventory amazon')
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('amazon-sync-mismatch-inventory')
            ->withoutOverlapping(40)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('amazon:sync-zero-inventory')
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('amazon-sync-zero-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncAmazonTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('amazon-sync-tracking')
            ->withoutOverlapping(18)
            ->appendOutputTo($log);

        // Auto-fulfill open Shopify copies (all marketplaces except Doba) from Veeqo/GOFO/SOF tracking.
        // Own process with a time budget so every run finishes, and the shared
        // mm-tracking queue worker stays free for the per-channel tracking jobs.
        $schedule->command('marketplace:fetch-shopify-tracking --limit=1200 --budget=720 --no-progress --push-channels')
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('marketplace-fetch-shopify-tracking')
            ->withoutOverlapping(30)
            ->runInBackground()
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

        $schedule->job(new \App\Jobs\SyncTopDawgTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('topdawg-sync-tracking')
            ->withoutOverlapping(18)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncTopDawgAddressJob(true, 40))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('topdawg-sync-address')
            ->withoutOverlapping(20)
            ->appendOutputTo($log);

        $schedule->command('topdawg:sync-link-map --force')
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

        // Auto tracking from Shopify fulfillments (settings: push_tracking_to_temu).
        $schedule->job(new \App\Jobs\SyncTemuTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('temu-sync-tracking')
            ->withoutOverlapping(18)
            ->appendOutputTo($log);

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

        // Auto tracking from Shopify fulfillments (settings: push_tracking_to_temu2).
        $schedule->job(new \App\Jobs\SyncTemu2TrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('temu2-sync-tracking')
            ->withoutOverlapping(18)
            ->appendOutputTo($log);

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

        // Temu 3 Marketplace Manager: inventory/price from Shopify, orders to Shopify
        $schedule->job(new \App\Jobs\SyncInventoryToTemu3)
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('temu3-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('temu3'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('temu3-sync-mismatch-inventory')
            ->withoutOverlapping(12)
            ->appendOutputTo($log);

        // Auto tracking from Shopify fulfillments (settings: push_tracking_to_temu3).
        $schedule->job(new \App\Jobs\SyncTemu3TrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('temu3-sync-tracking')
            ->withoutOverlapping(18)
            ->appendOutputTo($log);

        $schedule->command('temu3:sync-link-map')
            ->hourly()
            ->timezone('Asia/Kolkata')
            ->name('temu3-sync-link-map')
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

        $schedule->job(new \App\Jobs\SyncPurchasingPowerTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('purchasingpower-sync-tracking')
            ->withoutOverlapping(18)
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

        $schedule->job(new \App\Jobs\SyncWayfairTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('wayfair-sync-tracking')
            ->withoutOverlapping(18)
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

        $schedule->job(new \App\Jobs\SyncBestBuyTrackingJob(true, 120))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('bestbuy-sync-tracking')
            ->withoutOverlapping(18)
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

        $schedule->job(new \App\Jobs\SyncMacyTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('macy-sync-tracking')
            ->withoutOverlapping(18)
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

        $schedule->job(new \App\Jobs\SyncDobaTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('doba-sync-tracking')
            ->withoutOverlapping(18)
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

        $schedule->job(new \App\Jobs\SyncEbay1TrackingJob(true, 80))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay1-sync-tracking')
            ->withoutOverlapping(20)
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

        $schedule->job(new \App\Jobs\SyncEbay2TrackingJob(true, 80))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay2-sync-tracking')
            ->withoutOverlapping(20)
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

        $schedule->job(new \App\Jobs\SyncEbay3TrackingJob(true, 80))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('ebay3-sync-tracking')
            ->withoutOverlapping(20)
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

        $schedule->job(new \App\Jobs\SyncFaireTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('faire-sync-tracking')
            ->withoutOverlapping(18)
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

        $schedule->job(new \App\Jobs\SyncTikTokTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('tiktok-sync-tracking')
            ->withoutOverlapping(18)
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

        $schedule->job(new \App\Jobs\SyncTikTok2TrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('tiktok2-sync-tracking')
            ->withoutOverlapping(18)
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

        $schedule->job(new \App\Jobs\RunMarketplaceInventorySyncJob('b5cb2b'))
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('b5cb2b-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('b5cb2b'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('b5cb2b-sync-mismatch-inventory')
            ->withoutOverlapping(45)
            ->appendOutputTo($log);

        // PLS had no scheduled push, so every 5Core sale left PLS qty higher than Shopify.
        $schedule->job(new \App\Jobs\RunMarketplaceInventorySyncJob('pls'))
            ->everyFourHours()
            ->timezone('Asia/Kolkata')
            ->name('pls-sync-inventory')
            ->withoutOverlapping(200)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncMarketplaceMismatchInventoryJob('pls'))
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('pls-sync-mismatch-inventory')
            ->withoutOverlapping(45)
            ->appendOutputTo($log);

        $schedule->job(new \App\Jobs\SyncB5cB2bTrackingJob(true, 40))
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('b5cb2b-sync-tracking')
            ->withoutOverlapping(18)
            ->appendOutputTo($log);

        // Webhook-independent fallback: push only the Shopify SKUs whose qty changed
        // since the last poll to every enabled channel. Inline so mm-* jobs dispatch.
        $schedule->command('mm:poll-shopify-inventory-changes')
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('mm-poll-shopify-inventory-changes')
            ->withoutOverlapping(10)
            ->appendOutputTo($log);

        // Only writer of the /map-issues counts: the page reads them and never recounts itself.
        $schedule->command('mm:refresh-stale-mapping-counts')
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('mm-refresh-stale-mapping-counts')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        // Order Fulfillment tracking column: resolve orders with no number yet
        // (Veeqo → marketplace API → 4Seller) so the page does not rely on the browser.
        $schedule->command('order-fulfillment:backfill-tracking')
            ->everyTenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('order-fulfillment-backfill-tracking')
            ->withoutOverlapping(15)
            ->runInBackground()
            ->appendOutputTo($log);

        // Copy the numbers the Order Fulfillment page resolved onto the Shopify
        // orders (mark fulfilled) and push them to marketplaces still without tracking.
        $schedule->command('order-fulfillment:push-tracking --limit=150 --budget=540')
            ->everyTenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('order-fulfillment-push-tracking')
            ->withoutOverlapping(15)
            ->runInBackground()
            ->appendOutputTo($log);

        // Every 30 min from Shopify's side (GraphQL): open unfulfilled marketplace orders get the
        // tracking from /order-fulfillment — looked up first when it has none — and are fulfilled.
        $schedule->command('order-fulfillment:reconcile-shopify --days=14 --limit=300 --lookups=80 --budget=1500')
            ->cron('5,35 * * * *')
            ->timezone('Asia/Kolkata')
            ->name('order-fulfillment-reconcile-shopify')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);

        // Backup: queue Shopify imports for unpushed MM orders even if fetch jobs are stuck.
        // Must run inline — runInBackground() sits on the default queue and never dispatches mm-* imports.
        $schedule->command('mm:dispatch-unpushed-shopify')
            ->everyFifteenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('mm-dispatch-unpushed-shopify')
            ->withoutOverlapping(40)
            ->appendOutputTo($log);

        // Hourly: cancel + restock any second Shopify copy of one marketplace order.
        $schedule->command('mm:cancel-duplicate-shopify-orders --cancel --days=3')
            ->hourlyAt(20)
            ->timezone('Asia/Kolkata')
            ->name('mm-cancel-duplicate-shopify-orders')
            ->withoutOverlapping(50)
            ->appendOutputTo($log);

        // Every 30 minutes: fulfill leftover Shopify copies and push tracking to every channel.
        // Per-marketplace try/catch inside the command so one channel cannot stop the rest.
        $schedule->command('mm:push-orders-tracking --days=14 --skip-fetch --skip-inventory --tracking-limit=150')
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('mm-halfhourly-orders-tracking')
            ->withoutOverlapping(40)
            ->runInBackground()
            ->appendOutputTo($log);

        // Daily catch-up also re-fetches marketplace orders, then import + fulfill + tracking.
        $schedule->command('mm:push-orders-tracking --days=7 --skip-inventory')
            ->dailyAt('06:30')
            ->timezone('Asia/Kolkata')
            ->name('mm-daily-orders-tracking')
            ->withoutOverlapping(180)
            ->appendOutputTo($log);

     
        // Stores full raw into temu_ads_api_reports + syncs impressions/clicks to temu_metrics
        $retryFiveTimesUntil('temu:fetch-ads-data --period=L30', 'temu-ads-data-sync-l30', '15:40');
        $retryFiveTimesUntil('temu:fetch-ads-data --period=L60', 'temu-ads-data-sync-l60', '15:50');
        $retryFiveTimesUntil('temu:fetch-ads-api-reports --period=L7', 'temu-ads-api-reports-l7', '15:55');
        // Status column only — not Pause/Run. Picks up ads created on Temu Seller Center.
        $retryFiveTimesUntil('temu:refresh-ad-status', 'temu-ads-refresh-status', '19:30');
        // Ads Rules Auto run (only acts when Auto run is ON in /temu/ads > Ads Rules): L7 sold per parent -> slab Target ROAS -> Temu API.
        // Nothing is scheduled to run (and nothing is pushed) until Auto run is turned ON on that page.
        foreach ([15, 16, 17, 18, 19] as $autoRunHour) {
            $schedule->command('temu:auto-run-ads')
                ->dailyAt(sprintf('%02d:50', $autoRunHour))
                ->timezone('Asia/Kolkata')
                ->name('temu-ads-auto-run-'.$autoRunHour.'50')
                ->when(fn () => app(\App\Services\TemuSoldRoasRuleService::class)->autoRunEnabled())
                ->withoutOverlapping()
                ->runInBackground()
                ->appendOutputTo($log);
        }
        // Create missing ads for Parents per Ads Rules (slab Target ROAS > 0) and push ROAS. 5 min before each Auto run slot,
        // after the status refresh, so only goods Temu really has no ad for are created. Needs Auto run ON in /temu/ads > Ads Rules.
        foreach ([15, 16, 17, 18, 19] as $autoCreateHour) {
            $schedule->command('temu:auto-create-ads')
                ->dailyAt(sprintf('%02d:45', $autoCreateHour))
                ->timezone('Asia/Kolkata')
                ->name('temu-ads-auto-create-'.$autoCreateHour.'45')
                ->when(fn () => app(\App\Services\TemuSoldRoasRuleService::class)->autoRunEnabled())
                ->withoutOverlapping()
                ->runInBackground()
                ->appendOutputTo($log);
        }
        // Morning IST slots finish ~3–6 AM PDT, so /temu/ads stays stale all US day.
        // Do not wrap in $ist() — 22:30–23:45 IST is after IST_WINDOW_END (20:00).
        $schedule->command('temu:fetch-ads-data --period=L30')
            ->dailyAt('22:30')
            ->timezone('Asia/Kolkata')
            ->name('temu-ads-data-sync-l30-pdt-midday')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);
        $schedule->command('temu:fetch-ads-api-reports --period=L7')
            ->dailyAt('23:00')
            ->timezone('Asia/Kolkata')
            ->name('temu-ads-api-reports-l7-pdt-midday')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);
        // Exact Temu SKU names per goods (cache used by /temu/ads to match Seller Center).
        $schedule->command('temu:sync-goods-sku-names')
            ->dailyAt('22:00')
            ->timezone('Asia/Kolkata')
            ->name('temu-sync-goods-sku-names')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);
        // Which goods Temu still lists (/temu/ads hides everything else). ~5 API calls, so hourly.
        $schedule->command('temu:sync-goods-sku-names --live-only')
            ->hourly()
            ->name('temu-sync-live-goods')
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo($log);
        $schedule->command('temu:refresh-ad-status')
            ->dailyAt('23:45')
            ->timezone('Asia/Kolkata')
            ->name('temu-ads-refresh-status-pdt-midday')
            ->withoutOverlapping(15)
            ->runInBackground()
            ->appendOutputTo($log);
        $schedule->command('temu:auto-run-ads')
            ->dailyAt('23:55')
            ->timezone('Asia/Kolkata')
            ->name('temu-ads-auto-run-pdt-midday')
            ->when(fn () => app(\App\Services\TemuSoldRoasRuleService::class)->autoRunEnabled())
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log);
        $retryFiveTimesUntil('temu2:fetch-ads-data --period=L30', 'temu2-ads-data-sync-l30', '16:15');
        $retryFiveTimesUntil('temu2:fetch-ads-data --period=L60', 'temu2-ads-data-sync-l60', '16:25');
        $retryFiveTimesUntil('temu2:fetch-ads-api-reports --period=L7', 'temu2-ads-api-reports-l7', '16:30');
        $retryFiveTimesUntil('temu2:auto-pause-ads', 'temu2-ads-auto-pause', '16:40');
        $retryFiveTimesUntil('temu3:fetch-ads-data --period=L30', 'temu3-ads-data-sync-l30', '16:45');
        $retryFiveTimesUntil('temu3:fetch-ads-data --period=L60', 'temu3-ads-data-sync-l60', '16:55');
        $retryFiveTimesUntil('temu3:fetch-ads-api-reports --period=L7', 'temu3-ads-api-reports-l7', '17:00');
        $retryFiveTimesUntil('temu3:refresh-ad-status', 'temu3-ads-refresh-status', '19:40');

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

        // 09:55 not 08:55 — $ist() starts at 09:00, so the 08:55 slot never fired.
        $ist($schedule->command('shopify-pls:sync')
            ->cron('55 9,17 * * *')
            ->timezone('Asia/Kolkata')
            ->name('sync-shopify-pls-catalog')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('sync:neweegg-sheet')
            ->twiceDaily(9, 18)
            ->name('sync-newegg-sheet')
            ->withoutOverlapping(90)
            ->runInBackground()
            ->appendOutputTo($log));


        $ist($schedule->command('app:sync-cp-master-to-sheet')
            ->hourly()
            ->name('sync-cp-master-sheet')
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

        $ist($schedule->command('app:aliexpress-sheet-sync')
            ->everyMinute()
            ->name('aliexpress-sheet-sync')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_MINUTE)
            ->runInBackground()
            ->appendOutputTo($log));
        $retryFiveTimesUntil('tiktok:sync-gmv-ads --force', 'sync-tiktok-gmv-ads', '16:20');

  
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

   
        $schedule->command('cc:pull-pending-messages')
            ->everyFifteenMinutes()
            ->timezone('America/Los_Angeles')
            ->name('cc-pull-pending-messages')
            ->withoutOverlapping(14)
            ->runInBackground()
            ->appendOutputTo($log);

        $schedule->command('attendance:analyze')
            ->dailyAt('23:30')
            ->timezone('Asia/Kolkata')
            ->name('attendance-daily-analysis')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log);

        // Fraud checkup: re-checks today + yesterday so the red triangle beside an executive's
        // name appears within the hour (no AI / TeamLogger calls, so it is cheap).
        $schedule->command('attendance:checkup')
            ->hourlyAt(10)
            ->name('attendance-fraud-checkup')
            ->withoutOverlapping(50)
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

        // Pre-saved dynamic messages (rules managed by president@5core.com at /dynamic-messages).
        $schedule->command('dynamic-messages:run')
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('dynamic-messages-run')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // Pre-saved dynamic tasks, assignor Amarjit (rules managed by president@5core.com at /dynamic-tasks).
        $schedule->command('dynamic-tasks:run')
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('dynamic-tasks-run')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // Meeting rooms: close stale participants/rooms (fast, every minute) ...
        $schedule->command('meetings:sweep --housekeeping')
            ->everyMinute()
            ->name('meetings-housekeeping')
            ->withoutOverlapping(10)
            ->runInBackground()
            ->appendOutputTo($log);

        // ... and turn finished meetings' recordings into transcript + AI minutes + tasks (slow;
        // has its own lock so a long transcription never delays the housekeeping above).
        $schedule->command('meetings:sweep --minutes')
            ->everyMinute()
            ->name('meetings-minutes')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log);

        // Backup only. The reliable fire is /etc/cron.d/cron-run-missed,
        // because this slot is itself skipped when schedule:run is late.
        // Must NOT use $ist() (06:15 is before the window; 20:15 is after).
        foreach (['06:15', '12:15', '20:15'] as $slot) {
            $schedule->command('cron:run-missed')
                ->dailyAt($slot)
                ->timezone('Asia/Kolkata')
                ->name('cron-run-missed-'.str_replace(':', '', $slot))
                ->withoutOverlapping(90)
                ->runInBackground()
                ->appendOutputTo($log);
        }
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
