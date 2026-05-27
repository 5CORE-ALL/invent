<?php

namespace App\Console;

use App\Console\Commands\AmazonSbCampaignReports;
use App\Console\Commands\AmazonSdCampaignReports;
use App\Console\Commands\AmazonSpCampaignReports;
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
        FetchWayfairDailyData::class,
        FetchShopifyB2BMetrics::class,
        FetchShopifyB2CMetrics::class,
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

    /**
     * Define the application's command schedule.
     *
     * HARDENING RULES:
     * - Every command uses ->withoutOverlapping() to prevent duplicate runs.
     * - High-frequency jobs use short mutex TTL (5–55 min) so a crashed process
     *   cannot block the job for 24 hours (Laravel default).
     * - If a tick fires while the same command is still running, that tick is
     *   skipped (not queued). Stagger daily jobs and use evening closing pipeline.
     * - Artisan commands use ->runInBackground() so the scheduler can proceed.
     * - between(09:00–20:00) skips new starts outside the window; running jobs
     *   are never stopped and may finish after 20:00.
     *
     * MARKETPLACE JOBS: India 09:00–20:00 IST via istBusinessWindow().
     * Daily data pipeline targets completion by ~19:30 IST (closing block below).
     * System heartbeat / CRM reminders run 24/7.
     */
    protected function schedule(Schedule $schedule)
    {
        $log = $this->schedulerLog;
        $ist = fn ($event) => $this->istBusinessWindow($event);

        // Proof cron + schedule:run work: check storage/logs/scheduler-activity-*.log (one line/minute).
        $schedule->call(function () {
            Log::info('Scheduler heartbeat at ' . now());
            Log::channel('scheduler_activity')->info('schedule:run_heartbeat', [
                'at' => now()->toIso8601String(),
                'app_tz' => config('app.timezone'),
            ]);
        })->everyMinute()->name('scheduler-heartbeat');

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

        /*
        |--------------------------------------------------------------------------
        | TASK MANAGEMENT
        |--------------------------------------------------------------------------
        */
        // Run every 5 minutes; command itself time-gates to 12:01+ and duplicate checks prevent re-creation.
        $ist($schedule->command('tasks:generate-daily-automated')
            ->everyFiveMinutes()
            ->timezone('Asia/Kolkata')
            ->name('generate-daily-automated-tasks')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_FIVE)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('tasks:mark-missed-automated')
            ->twiceDaily(9, 18)
            ->timezone('Asia/Kolkata')
            ->name('mark-missed-automated-tasks')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // Auto-delete daily automated tasks not completed the same day.
        // Runs at 00:05 IST so yesterday's incomplete daily auto-tasks are archived as Missed
        // before today's new instances are generated (generator runs from 12:01 onward).
        $ist($schedule->command('tasks:expire-daily-automated')
            ->dailyAt('09:05')
            ->timezone('Asia/Kolkata')
            ->name('expire-daily-automated-tasks')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // Lightweight alert: log only when expected automated instances are missing.
        $ist($schedule->command('tasks:automated-health-alert')
            ->everyThirtyMinutes()
            ->timezone('Asia/Kolkata')
            ->name('automated-tasks-health-alert')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // Weekly/monthly automated tasks: run every minute so schedule_time can match (daily handled by generate-daily at 00:01)
        $ist($schedule->command('tasks:execute-automated')
            ->everyMinute()
            ->timezone('Asia/Kolkata')
            ->name('execute-automated-tasks-weekly-monthly')
            ->withoutOverlapping(2)
            ->runInBackground()
            ->appendOutputTo($log));

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

        // Reverb: full sync (orders + Shopify→Reverb inventory) every 5 minutes
        // $schedule->command('reverb:sync-all')
        //     ->everyThirtyMinutes()
        //     ->timezone('UTC')
        //     ->name('reverb-sync-all')
        //     ->withoutOverlapping(15);
        // $schedule->command('app:amazon-campaign-reports')
        //     ->dailyAt('04:00')
        //     ->timezone('America/Los_Angeles');
        $ist($schedule->command('app:amazon-sp-campaign-reports')
            ->dailyAt('09:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-sp-campaign-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:amazon-sb-campaign-reports')
            ->dailyAt('09:05')
            ->timezone('Asia/Kolkata')
            ->name('amazon-sb-campaign-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:amazon-sd-campaign-reports')
            ->dailyAt('09:10')
            ->timezone('Asia/Kolkata')
            ->name('amazon-sd-campaign-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | AMAZON BIDS / BUDGET AUTO-UPDATE (Staggered IST for reliability)
        | Standard PT bids + PT budget: 12:15–13:00 IST (see AMAZON FBA block for FBA PT)
        |--------------------------------------------------------------------------
        */
        // Over-utilized KW bids: 2:00 AM IST
        $ist($schedule->command('amazon:auto-update-over-kw-bids')
            ->dailyAt('12:15')
            ->timezone('Asia/Kolkata')
            ->name('amazon-over-kw-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        // Under-utilized KW bids: 2:30 AM IST
        $ist($schedule->command('amazon:auto-update-under-kw-bids')
            ->dailyAt('12:20')
            ->timezone('Asia/Kolkata')
            ->name('amazon-under-kw-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        // Pink dilution KW: 3:00 AM IST
        // $schedule->command('amazon:auto-update-pink-dil-kw-ads')
        //     ->dailyAt('03:00')
        //     ->timezone('Asia/Kolkata')
        //     ->name('amazon-pink-dil-kw')
        //     ->withoutOverlapping(60)
        //     ->runInBackground()
        //     ->appendOutputTo($log);

        // Over-utilized PT bids: 12:15 PM IST
        $ist($schedule->command('amazon:auto-update-over-pt-bids')
            ->dailyAt('12:25')
            ->timezone('Asia/Kolkata')
            ->name('amazon-over-pt-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        // Under-utilized PT bids: 12:45 PM IST
        $ist($schedule->command('amazon:auto-update-under-pt-bids')
            ->dailyAt('12:30')
            ->timezone('Asia/Kolkata')
            ->name('amazon-under-pt-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        // Pink dilution PT: 5:00 AM IST
        // $schedule->command('amazon:auto-update-pink-dil-pt-ads')
        //     ->dailyAt('05:00')
        //     ->timezone('Asia/Kolkata')
        //     ->name('amazon-pink-dil-pt')
        //     ->withoutOverlapping(60)
        //     ->runInBackground()
        //     ->appendOutputTo($log);

        // Over-utilized HL bids: 6:00 AM IST
        $ist($schedule->command('amazon:auto-update-over-hl-bids')
            ->dailyAt('12:35')
            ->timezone('Asia/Kolkata')
            ->name('amazon-over-hl-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        // Under-utilized HL bids: 6:30 AM IST
        $ist($schedule->command('amazon:auto-update-under-hl-bids')
            ->dailyAt('12:40')
            ->timezone('Asia/Kolkata')
            ->name('amazon-under-hl-bids')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        // Pink dilution HL: 7:00 AM IST
        // $schedule->command('amazon:auto-update-pink-dil-hl-ads')
        //     ->dailyAt('07:00')
        //     ->timezone('Asia/Kolkata')
        //     ->name('amazon-pink-dil-hl')
        //     ->withoutOverlapping(60)
        //     ->runInBackground()
        //     ->appendOutputTo($log);

        // Amazon budget KW: 8:00 AM IST
        $ist($schedule->command('amazon:auto-update-amz-bgt-kw')
            ->dailyAt('12:45')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-kw')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        // Amazon budget PT: 1:00 PM IST (after PT bid updates)
        $ist($schedule->command('amazon:auto-update-amz-bgt-pt')
            ->dailyAt('12:50')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-pt')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        // Amazon budget HL: 9:00 AM IST
        $ist($schedule->command('amazon:auto-update-amz-bgt-hl')
            ->dailyAt('12:55')
            ->timezone('Asia/Kolkata')
            ->name('amazon-bgt-hl')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | AMAZON FBA
        |--------------------------------------------------------------------------
        */
        // FBA PT bids: 12:00 & 12:30 PM IST (with standard Amazon PT jobs 12:15–13:00 IST)
        $ist($schedule->command('amazon-fba:auto-update-under-pt-bids')
            ->dailyAt('13:05')
            ->timezone('Asia/Kolkata')
            ->name('fba-under-pt-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon-fba:auto-update-over-pt-bids')
            ->dailyAt('13:10')
            ->timezone('Asia/Kolkata')
            ->name('fba-over-pt-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // FBA KW bids: 3:00 PM–3:30 PM IST (moved from 12:15/12:35 to avoid overlap with PT afternoon window)
        $ist($schedule->command('amazon-fba:auto-update-over-kw-bids')
            ->dailyAt('15:00')
            ->timezone('Asia/Kolkata')
            ->name('fba-over-kw-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon-fba:auto-update-under-kw-bids')
            ->dailyAt('15:30')
            ->timezone('Asia/Kolkata')
            ->name('fba-under-kw-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-fba-reports')
            ->dailyAt('13:30')
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

        // $schedule->command('amazon:auto-pause-enable-fba-campaigns')
        //     ->dailyAt('12:30')
        //     ->timezone('Asia/Kolkata')
        //     ->name('fba-auto-pause-enable')
        //     ->withoutOverlapping()
        //     ->runInBackground()
        //     ->appendOutputTo($log);

        // $schedule->command('budget:update-amazon-fba-kw')
        //     ->dailyAt('09:05')
        //     ->timezone('Asia/Kolkata')
        //     ->name('budget-fba-kw')
        //     ->withoutOverlapping()
        //     ->runInBackground()
        //     ->appendOutputTo($log);

        // $schedule->command('budget:update-amazon-fba-pt')
        //     ->dailyAt('00:06')
        //     ->timezone('Asia/Kolkata')
        //     ->name('budget-fba-pt')
        //     ->withoutOverlapping()
        //     ->runInBackground()
        //     ->appendOutputTo($log);

        /*
        |--------------------------------------------------------------------------
        | AMAZON UTILIZATION & METRICS
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('amazon:store-utilization-counts')
            ->dailyAt('09:12')
            ->timezone('Asia/Kolkata')
            ->name('amazon-utilization-counts')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon-fba:store-utilization-counts')
            ->dailyAt('09:14')
            ->timezone('Asia/Kolkata')
            ->name('fba-utilization-counts')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:store-listing-daily-metrics')
            ->dailyAt('09:16')
            ->timezone('Asia/Kolkata')
            ->name('amazon-listing-daily-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('amazon:collect-metrics')
            ->dailyAt('13:00')
            ->timezone('Asia/Kolkata')
            ->name('amazon-collect-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | EBAY JOBS
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('app:fetch-ebay-orders')
            ->dailyAt('09:35')
            ->timezone('Asia/Kolkata')
            ->name('fetch-ebay-orders')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-ebay2-orders')
            ->dailyAt('09:40')
            ->timezone('Asia/Kolkata')
            ->name('fetch-ebay2-orders')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay3:daily --days=60')
            ->dailyAt('09:45')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-ebay-reports')
            ->hourly()
            ->name('fetch-ebay-reports')
            ->withoutOverlapping(self::HF_MUTEX_HOURLY)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-ebay-table-data')
            ->dailyAt('09:50')
            ->timezone('Asia/Kolkata')
            ->name('fetch-ebay-table-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-ebay-three-metrics')
            ->dailyAt('10:05')
            ->timezone('Asia/Kolkata')
            ->name('fetch-ebay-three-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:fetch-ebay-two-metrics')
            ->dailyAt('10:00')
            ->timezone('Asia/Kolkata')
            ->name('fetch-ebay-two-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // eBay campaign reports
        $ist($schedule->command('app:ebay-campaign-reports')
            ->dailyAt('10:10')
            ->timezone('Asia/Kolkata')
            ->name('ebay-campaign-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:ebay2-campaign-reports')
            ->dailyAt('10:15')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-campaign-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('app:ebay3-campaign-reports')
            ->dailyAt('10:20')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-campaign-reports')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // eBay bids update
        $ist($schedule->command('ebay:auto-update-over-bids')
            ->dailyAt('13:15')
            ->timezone('Asia/Kolkata')
            ->name('ebay-over-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay:auto-update-under-bids')
            ->dailyAt('13:17')
            ->timezone('Asia/Kolkata')
            ->name('ebay-under-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay2:auto-update-utilized-bids')
            ->dailyAt('13:19')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-utilized-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay3:auto-update-utilized-bids')
            ->dailyAt('13:21')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-utilized-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay:update-suggestedbid')
            ->dailyAt('13:23')
            ->timezone('Asia/Kolkata')
            ->name('ebay-suggestedbid')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay2:update-suggestedbid')
            ->dailyAt('13:25')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-suggestedbid')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay3:update-suggestedbid')
            ->dailyAt('13:27')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-suggestedbid')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay1:update-budget')
            ->dailyAt('13:29')
            ->timezone('Asia/Kolkata')
            ->name('ebay1-budget')
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

        $ist($schedule->command('ebay:store-utilization-counts')
            ->dailyAt('09:24')
            ->timezone('Asia/Kolkata')
            ->name('ebay-utilization-counts')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

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

        /*
        |--------------------------------------------------------------------------
        | GOOGLE ADS & SHOPPING
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('app:fetch-google-ads-campaigns')
            ->dailyAt('09:00')
            ->timezone('Asia/Kolkata')
            ->name('fetch-google-ads-campaigns')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('sbid:update')
            ->dailyAt('09:18')
            ->timezone('Asia/Kolkata')
            ->name('sbid-update')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('sbid:update-serp')
            ->dailyAt('09:19')
            ->timezone('Asia/Kolkata')
            ->name('sbid-update-serp')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('budget:update-shopping')
            ->dailyAt('09:20')
            ->timezone('Asia/Kolkata')
            ->name('budget-shopping')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('budget:update-serp')
            ->dailyAt('09:21')
            ->timezone('Asia/Kolkata')
            ->name('budget-serp')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('google:store-shopping-utilization-counts')
            ->dailyAt('09:22')
            ->timezone('Asia/Kolkata')
            ->name('google-shopping-utilization')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

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
        })->dailyAt('09:17')->timezone('Asia/Kolkata')->name('reset-sbid-status');

        /*
        |--------------------------------------------------------------------------
        | META / FACEBOOK ADS
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('meta:sync-all-ads')
            ->dailyAt('10:00')
            ->timezone('Asia/Kolkata')
            ->name('meta-ads-sync-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('meta-ads:sync')
            ->dailyAt('11:00')
            ->timezone('Asia/Kolkata')
            ->name('meta-ads-manager-full-sync')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('meta-ads:sync --insights-only')
            ->dailyAt('11:00')
            ->timezone('Asia/Kolkata')
            ->name('meta-ads-manager-insights-sync')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('meta-ads:run-automation')
            ->dailyAt('11:15')
            ->timezone('Asia/Kolkata')
            ->name('meta-ads-automation-rules')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // Shopify Meta Campaigns
        $ist($schedule->command('shopify:fetch-meta-campaigns --channel=both')
            ->dailyAt('11:30')
            ->timezone('Asia/Kolkata')
            ->name('fetch-shopify-fb-campaigns-7-30-60-days')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('meta:sync-all-ads')
            ->dailyAt('11:45')
            ->timezone('Asia/Kolkata')
            ->name('sync-meta-all-ads-from-google-sheets')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | SHOPIFY
        |--------------------------------------------------------------------------
        */
        // shopify:save-daily-inventory — crontab only (see scripts/cron-shopify-save-daily-inventory.sh).
        // shopify:sync-live-inventory — crontab only (see scripts/cron-shopify-sync-live-inventory.sh).


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

        /*
        |--------------------------------------------------------------------------
        | REVERB
        |--------------------------------------------------------------------------
        */
        // $schedule->command('reverb:fetch')
        //     ->everyFiveMinutes()
        //     ->timezone('UTC')
        //     ->name('reverb-fetch')
        //     ->withoutOverlapping()
        //     ->runInBackground()
        //     ->appendOutputTo($log);

        // $schedule->command('reverb:daily --days=60')
        //     ->dailyAt('01:10')
        //     ->timezone('Asia/Kolkata')
        //     ->name('reverb-daily')
        //     ->withoutOverlapping()
        //     ->runInBackground()
        //     ->appendOutputTo($log);

        // $schedule->command('reverb:sync-listing-statuses')
        //     ->everySixHours()
        //     ->timezone('UTC')
        //     ->name('reverb-sync-listing-statuses')
        //     ->withoutOverlapping(60)
        //     ->runInBackground()
        //     ->appendOutputTo($log);

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
        | WAYFAIR DATA
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('app:fetch-wayfair-data')
            ->everyFiveMinutes()
            ->name('fetch-wayfair-data')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_FIVE)
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | MIRAKL
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('mirakl:daily --days=60')
            ->dailyAt('14:15')
            ->timezone('Asia/Kolkata')
            ->name('mirakl-daily')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | TEMU
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('app:fetch-temu-metrics')
            ->dailyAt('14:25')
            ->timezone('Asia/Kolkata')
            ->name('fetch-temu-metrics')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // Populate temu_sku_daily_data for chart history (Price, Views, CVR%, Temu L30)
        $ist($schedule->command('temu:collect-metrics')
            ->dailyAt('14:35')
            ->timezone('Asia/Kolkata')
            ->name('temu-collect-metrics')
            ->withoutOverlapping()
            ->appendOutputTo($log));

        $ist($schedule->command('temu:fetch-ads-data --period=L30')
            ->dailyAt('15:40')
            ->timezone('Asia/Kolkata')
            ->name('temu-ads-data-sync-l30')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('temu:fetch-ads-data --period=L60')
            ->dailyAt('15:50')
            ->timezone('Asia/Kolkata')
            ->name('temu-ads-data-sync-l60')
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

        $ist($schedule->command('sync:temu-sheet-data')
            ->twiceDaily(9, 18)
            ->name('sync-temu-sheet')
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

        $ist($schedule->command('sync:sync-temu-sip')
            ->everyMinute()
            ->name('sync-temu-sip')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_MINUTE)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('sync:walmart-metrics-data')
            ->everyMinute()
            ->name('sync-walmart-metrics')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_MINUTE)
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('sync:tiktok-sheet-data')
            ->everyMinute()
            ->name('sync-tiktok-sheet')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_MINUTE)
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

        /*
        |--------------------------------------------------------------------------
        | CHANNEL MASTER PRE-CALCULATION (New - Performance Optimization)
        |--------------------------------------------------------------------------
        */
        // Channel master pre-calculation.
        //
        // /all-marketplace-master serves rows out of channel_master_calculated_data
        // (see ChannelMasterController::getViewChannelDataFast). The underlying
        // marketplace_daily_metrics already refreshes every 5 minutes, so doing this
        // only once a day made the page lag by up to 24 hours behind reality. Run it
        // hourly with --force so that:
        //   - L30/L60 sales (Temu, Temu 2, eBay, Amazon, …) reflect the latest sync
        //   - channel_master_daily_data gets a fresh snapshot for today (drives the
        //     red/green/gray trend dots on every metric column)
        //
        // Each run takes ~50s. Mutex 120 min prevents stack-up; last tick ~19:50 IST.
        $ist($schedule->command('channel:calculate-data --force')
            ->everyTenMinutes()
            ->timezone('Asia/Kolkata')
            ->name('channel-master-calculate-data')
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | TIKTOK
        |--------------------------------------------------------------------------
        */
        $ist($schedule->command('sync:tiktok-api-data')
            ->dailyAt('15:45')
            ->timezone('Asia/Kolkata')
            ->name('sync-tiktok-api-data')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        /*
        |--------------------------------------------------------------------------
        | FINISH-BY-8PM CLOSING PIPELINE (18:00–19:15 IST)
        | Runs after afternoon marketplace syncs. In-flight jobs may finish after
        | 20:00; between() never stops them.
        |--------------------------------------------------------------------------
        */
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
