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
        \App\Console\Commands\RunShopifyBulletPull::class,
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
     * - Artisan coforcemmands use ->runInBackground() so the scheduler can proceed.
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

        /**
         * Retry-window helper for ad-API data-fetch commands.
         *
         * Registers $command 5 times — once at $finalTime IST and 4 earlier hourly
         * retries (T-4h, T-3h, T-2h, T-1h, T). Each slot uses a unique mutex name so
         * withoutOverlapping() guards against same-slot stacking without preventing
         * later slots from running when an earlier one fails. Idempotent fetches
         * (Google Ads, GA4, Meta, Amazon Advertising, eBay Marketing, Temu Ads, TikTok)
         * tolerate repeated runs because they upsert by (campaign_id, date) or similar
         * natural keys — a successful run after a failed run simply overwrites yesterday's
         * partial state with a complete snapshot, so dependent push / automation jobs
         * downstream see fresh data.
         *
         * Push / automation / mutation commands MUST NOT use this helper because firing
         * them five times would touch live ad accounts five times.
         *
         * Most of the ads-API fetch commands anchor in the morning (final slot 09:00–11:30
         * IST); a handful (Temu / TikTok) anchor in the afternoon (final slot 15:40–15:50)
         * — same five-attempt pattern, just shifted later in the day.
         */
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

        // Proof cron + schedule:run work: check storage/logs/scheduler-activity-*.log (one line/minute).
        // withoutOverlapping(2) guards against the rare case where logging is slow enough that two ticks
        // would otherwise stack — keeps the heartbeat truly one-per-minute.
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

        /*
        |--------------------------------------------------------------------------
        | TASK MANAGEMENT (office timezone — config/tasks.php, NOT IST business window)
        |--------------------------------------------------------------------------
        */
        $taskTz = config('tasks.business_timezone', 'America/Los_Angeles');

        // 24/7: daily instances must exist at start of California day; IST 09–20 window does not apply.
        $schedule->command('tasks:generate-daily-automated')
            ->everyFiveMinutes()
            ->timezone($taskTz)
            ->name('generate-daily-automated-tasks')
            ->withoutOverlapping(self::HF_MUTEX_EVERY_FIVE)
            ->runInBackground()
            ->appendOutputTo($log);

        // Weekly/monthly auto-tasks become missed 144h / 720h after their generated start time.
        // Hourly so the per-type window is honoured promptly. Automated tasks only.
        $schedule->command('tasks:mark-missed-automated')
            ->hourly()
            ->timezone($taskTz)
            ->name('mark-missed-automated-tasks')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log);

        // NOTE: Auto-delete of missed daily automated tasks (tasks:expire-daily-automated)
        // has been removed from the schedule. It can still be run on demand via the
        // "Missed" button (TaskController::expireDailyAutomatedTasks) or artisan.

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

        // Clear Laravel log periodically. withoutOverlapping prevents a slow truncate
        // (50MB+ rewrite on disk) from stacking ticks while the file lock is held.
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

        // Reverb: full sync (orders + Shopify→Reverb inventory) every 5 minutes
        // $schedule->command('reverb:sync-all')
        //     ->everyThirtyMinutes()
        //     ->timezone('UTC')
        //     ->name('reverb-sync-all')
        //     ->withoutOverlapping(15);
        // $schedule->command('app:amazon-campaign-reports')
        //     ->dailyAt('04:00')
        //     ->timezone('America/Los_Angeles');
        // Amazon Advertising API — 5 AFTERNOON runs each (final slots 18:00 / 18:05 / 18:10
        // IST). Aligned with PT (Amazon US accounts run in Pacific Time): a PT day ends at
        // ~12:30 PM IST, so afternoon retries see fully-finalised PT reports. Earlier morning
        // runs were querying mid-PT-day and getting only PARTIAL spend / clicks. Downstream
        // Amazon FBA bid auto-updates also moved to 19:00–19:45 IST so they consume the
        // freshly-fetched complete reports.
        $retryFiveTimesUntil('app:amazon-sp-campaign-reports', 'amazon-sp-campaign-reports', '18:00');
        $retryFiveTimesUntil('app:amazon-sb-campaign-reports', 'amazon-sb-campaign-reports', '18:05');
        $retryFiveTimesUntil('app:amazon-sd-campaign-reports', 'amazon-sd-campaign-reports', '18:10');

        /*
        |--------------------------------------------------------------------------
        | AMAZON BIDS / BUDGET AUTO-UPDATE — EVENING IST (PT-aligned)
        | Bids:    18:30–18:55 IST  (after Amazon SP/SB/SD reports final at 18:10)
        | Budget:  20:00–20:10 IST  (after Amazon FBA bid push cluster at 19:00–19:45)
        |
        | Previously 12:15–12:55 IST — that ran BEFORE the new afternoon report
        | retries (14:00–18:10 IST) so the pushes were using yesterday's stale or
        | partial PT-day report data. Shifting to evening means each push consumes
        | the freshest fully-finalised PT-day report.
        |--------------------------------------------------------------------------
        */
        // KW (keyword) bid pushes
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

        // Pink dilution KW (disabled; left as reference)
        // $schedule->command('amazon:auto-update-pink-dil-kw-ads')
        //     ->dailyAt('03:00')
        //     ->timezone('Asia/Kolkata')
        //     ->name('amazon-pink-dil-kw')
        //     ->withoutOverlapping(60)
        //     ->runInBackground()
        //     ->appendOutputTo($log);

        // PT (product-targeting) bid pushes
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

        // Pink dilution PT (disabled; left as reference)
        // $schedule->command('amazon:auto-update-pink-dil-pt-ads')
        //     ->dailyAt('05:00')
        //     ->timezone('Asia/Kolkata')
        //     ->name('amazon-pink-dil-pt')
        //     ->withoutOverlapping(60)
        //     ->runInBackground()
        //     ->appendOutputTo($log);

        // HL (headline) bid pushes
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

        // Pink dilution HL (disabled; left as reference)
        // $schedule->command('amazon:auto-update-pink-dil-hl-ads')
        //     ->dailyAt('07:00')
        //     ->timezone('Asia/Kolkata')
        //     ->name('amazon-pink-dil-hl')
        //     ->withoutOverlapping(60)
        //     ->runInBackground()
        //     ->appendOutputTo($log);

        // Budget pushes — 20:00–20:10 IST, AFTER the Amazon FBA bid push cluster
        // at 19:00–19:45 IST so budget changes don't race with bid changes.
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

        /*
        |--------------------------------------------------------------------------
        | AMAZON FBA
        |--------------------------------------------------------------------------
        */
        // FBA PT bids: 12:00 & 12:30 PM IST (with standard Amazon PT jobs 12:15–13:00 IST)
        // Amazon FBA bid pushes — moved to 19:00–19:45 IST so they run AFTER the
        // Amazon report fetches (final retry 18:10 IST) using fully-finalised PT-day data.
        // Previously at 13:05/13:10/15:00/15:30 IST with reports still partial.
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
        // Amazon internal aggregators — all shifted to evening IST so they read from
        // the freshly-finalised PT-day Amazon report data (reports complete at 18:10 IST,
        // FBA bid pushes 19:00–19:45 IST). Previously 09:12–13:00 IST when reports were
        // still partial, so utilization counts and metrics were under-counted.
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

        // FBA utilization counts — runs after the FBA bid push cluster (19:00–19:45 IST)
        // and before the Amazon budget pushes at 20:00 IST so any util-driven budget
        // decisions see freshly-pushed FBA bid state.
        $ist($schedule->command('amazon-fba:store-utilization-counts')
            ->dailyAt('19:50')
            ->timezone('Asia/Kolkata')
            ->name('fba-utilization-counts')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // amazon:collect-metrics — aggregates Amazon spend / clicks / conversions from
        // the freshly-completed report rows. Final retry of Amazon SP reports is 18:00,
        // so 18:25 leaves 25 min buffer for any slow tail.
        $ist($schedule->command('amazon:collect-metrics')
            ->dailyAt('18:25')
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

        // eBay 3 daily 60-day backfill — shifted to 19:40 IST so it follows the eBay
        // campaign-reports retries (final 19:20 IST) and lands its data before the
        // ebay3:auto-update / suggested-bid / budget pushes at 21:06+ IST.
        // Previously 09:45 IST when reports were still partial PT-day.
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

        // eBay metrics + table-data fetches — shifted to 19:25–19:35 IST so they run
        // AFTER the eBay campaign-reports retries (final 19:10–19:20) and BEFORE the
        // eBay sync-listings cluster (16:30–20:34) and bid-update push at 21:00+.
        // Previously 09:50 / 10:00 / 10:05 IST when reports were still partial PT-day.
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

        // eBay Marketing API campaign reports — 5 AFTERNOON runs each (final slots 19:10 /
        // 19:15 / 19:20 IST). Aligned with PT (US eBay reporting day ends ~12:30 PM IST);
        // pre-shift, morning runs were querying mid-PT-day with partial click/spend data.
        // Downstream eBay bid auto-updates pushed accordingly to 21:00–21:18 IST.
        $retryFiveTimesUntil('app:ebay-campaign-reports', 'ebay-campaign-reports', '19:10');
        $retryFiveTimesUntil('app:ebay2-campaign-reports', 'ebay2-campaign-reports', '19:15');
        $retryFiveTimesUntil('app:ebay3-campaign-reports', 'ebay3-campaign-reports', '19:20');

        // eBay promoted-listings sync into ebay{,2,3}_campaign_ads — 5 afternoon runs each
        // (final slots 20:30 / 20:32 / 20:34 IST). The downstream suggested-bid + budget jobs
        // consume these tables; runs are timed so the freshest listings land in time for the
        // 21:00–21:18 IST bid push and the 21:20–21:33 IST suggested-bid + budget cluster.
        $retryFiveTimesUntil('ebay:sync-campaign-listings', 'ebay-sync-campaign-listings', '20:30');
        $retryFiveTimesUntil('ebay2:sync-campaign-listings', 'ebay2-sync-campaign-listings', '20:32');
        $retryFiveTimesUntil('ebay3:sync-campaign-listings', 'ebay3-sync-campaign-listings', '20:34');

        // eBay bid pushes — 21:00–21:08 IST, after sync-listings (20:34 IST).
        // (Previously 13:15–13:21 IST when reports were still partial.)
        $ist($schedule->command('ebay:auto-update-over-bids')
            ->dailyAt('21:00')
            ->timezone('Asia/Kolkata')
            ->name('ebay-over-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay:auto-update-under-bids')
            ->dailyAt('21:02')
            ->timezone('Asia/Kolkata')
            ->name('ebay-under-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay2:auto-update-utilized-bids')
            ->dailyAt('21:04')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-utilized-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay3:auto-update-utilized-bids')
            ->dailyAt('21:06')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-utilized-bids')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // eBay suggested-bid + budget update cluster — 21:20–21:33 IST, after the bid
        // push above. (Previously 13:23–13:33 IST when reports were still partial.)
        $ist($schedule->command('ebay:update-suggestedbid')
            ->dailyAt('21:20')
            ->timezone('Asia/Kolkata')
            ->name('ebay-suggestedbid')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay2:update-suggestedbid')
            ->dailyAt('21:23')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-suggestedbid')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay3:update-suggestedbid')
            ->dailyAt('21:26')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-suggestedbid')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay1:update-budget')
            ->dailyAt('21:29')
            ->timezone('Asia/Kolkata')
            ->name('ebay1-budget')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay2:update-budget')
            ->dailyAt('21:31')
            ->timezone('Asia/Kolkata')
            ->name('ebay2-budget')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        $ist($schedule->command('ebay3:update-budget')
            ->dailyAt('21:33')
            ->timezone('Asia/Kolkata')
            ->name('ebay3-budget')
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

        // eBay utilization counts aggregator — shifted to 21:40 IST so it runs after
        // the entire eBay push chain completes at 21:33 IST (last budget push). This
        // aggregator reads from eBay report tables freshly populated 19:10–19:20 IST
        // and the post-push state from 21:00–21:33 IST. Previously 09:24 IST when
        // both the reports AND the push state were stale.
        $ist($schedule->command('ebay:store-utilization-counts')
            ->dailyAt('21:40')
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
        | GOOGLE ADS & SHOPPING — AFTERNOON IST WINDOW (CA-aligned)
        |--------------------------------------------------------------------------
        | The Google Ads account runs in California (PT). A CA day ends at
        | ~12:30 PM IST the next calendar date, after which Google Ads finalizes
        | the prior day's report data. Earlier morning IST runs were querying
        | while CA was still mid-day, so we received only PARTIAL spend / clicks
        | for "yesterday CA". Shifting the entire fetch + push chain to 13:00
        | onwards IST means every run sees a fully-finalised CA day, which is
        | what the SBID / BGT pushes need to compute accurate suggestions.
        |
        | Schedule (all IST):
        |   13:00–17:00  app:fetch-google-ads-campaigns retries (5x)
        |   13:30–17:30  ga4:fetch-campaign-data retries (5x, offset 30m)
        |   17:47        reset SBID status flag
        |   17:48–17:52  sbid + budget pushes + utilization counts (sequential, 1m apart)
        */
        // 5 afternoon runs (13:00, 14:00, 15:00, 16:00, 17:00 IST). At 13:00 IST
        // CA is at 00:30 PDT — yesterday CA is fully finalised; subsequent retries
        // also see a complete day. The 17:00 final-slot leaves 48 minutes of buffer
        // before the SBID push at 17:48 IST.
        $retryFiveTimesUntil('app:fetch-google-ads-campaigns', 'fetch-google-ads-campaigns', '17:00');

        // GA4 actual purchases / revenue back-fill — 5 retries at 13:30, 14:30,
        // 15:30, 16:30, 17:30 IST (offset 30m from app:fetch-google-ads-campaigns
        // so the GA4 API call happens after each Google-Ads run has populated/
        // refreshed the matching campaign rows it joins by name). --days=30 keeps
        // the L30 Sales window backfilled even when a prior afternoon run was
        // missed; ga4:fetch-campaign-data is upsert-safe.
        $retryFiveTimesUntil('ga4:fetch-campaign-data --days=30', 'ga4-fetch-campaign-data', '17:30');

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

        /*
        |--------------------------------------------------------------------------
        | META / FACEBOOK ADS — AFTERNOON IST WINDOW (US-PT-aligned)
        |--------------------------------------------------------------------------
        | Most US Facebook ad accounts use Pacific Time. Shifted from morning IST
        | to afternoon so each retry sees a fully-finalised PT day for "yesterday"
        | instead of partial mid-PT-day data. Meta automation rules pushed
        | accordingly to 20:30 IST so they fire AFTER the freshest sync completes.
        */
        // 5 afternoon runs (14:30–18:30 IST). meta:sync-all-ads is the daily Meta
        // campaigns/ads/insights pull — multiple retries cover Facebook Graph rate-limit
        // hiccups so the pipeline finishes ahead of meta-ads:run-automation at 20:30 IST.
        $retryFiveTimesUntil('meta:sync-all-ads', 'meta-ads-sync-daily', '18:30');

        // 5 afternoon runs (15:00–19:00 IST). meta-ads:sync is the full Ads-Manager-style
        // refresh; it shares an API quota with the --insights-only variant below, so the
        // mutex names diverge by hour to avoid stacking but the two commands intentionally
        // run on the same hourly cadence.
        $retryFiveTimesUntil('meta-ads:sync', 'meta-ads-manager-full-sync', '19:00');
        $retryFiveTimesUntil('meta-ads:sync --insights-only', 'meta-ads-manager-insights-sync', '19:00');

        // Shopify Meta Campaigns — 5 afternoon runs (15:30, 16:30, 17:30, 18:30, 19:30 IST).
        // Pulls 7/30/60-day Facebook campaign metrics for Shopify; idempotent upsert by
        // (channel, campaign_id, window) means a recovered late slot completes prior days.
        $retryFiveTimesUntil('shopify:fetch-meta-campaigns --channel=both', 'fetch-shopify-fb-campaigns-7-30-60-days', '19:30');

        // Meta automation rules push — 20:30 IST, AFTER the 19:30 IST shopify-fb-campaigns
        // final retry and the 19:00 IST meta-ads:sync final retry, so the rules engine sees
        // the freshest insights data.
        $ist($schedule->command('meta-ads:run-automation')
            ->dailyAt('20:30')
            ->timezone('Asia/Kolkata')
            ->name('meta-ads-automation-rules')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

        // Google-Sheets-driven sync of meta:sync-all-ads — different mutex name from the
        // API retries above, runs once daily at 21:45 IST after the last API retry chain.
        $ist($schedule->command('meta:sync-all-ads')
            ->dailyAt('21:45')
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

        // shopify_raw_orders is the LIVE source for /faire-tabulator, /ebay-tabulator and the
        // all-marketplace-master Faire L30/L60 + Y-sales. If shopify:sync-orders stops running
        // the table freezes and those pages' sales stop updating. Thin hourly pass keeps the
        // current day fresh; the daily 60-day pass backfills late edits / refunds for L60.
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
        // Reverb: same daily-morning pattern as eBay (09:35–09:45) so all marketplaces
        // refresh once in the IST morning. Heavy commands — no need to run every 5 min.
        $ist($schedule->command('reverb:fetch')
            ->dailyAt('09:50')
            ->timezone('Asia/Kolkata')
            ->name('reverb-fetch')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo($log));

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
        // Fetch rolling 60-day order-wise raw data (powers /temu-tabulator)
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

        // Populate temu_sku_daily_data for chart history (Price, Views, CVR%, Temu L30)
        $ist($schedule->command('temu:collect-metrics')
            ->dailyAt('14:35')
            ->timezone('Asia/Kolkata')
            ->name('temu-collect-metrics')
            ->withoutOverlapping()
            ->appendOutputTo($log));

        // Temu Ads API — 5 afternoon runs each (final slots 15:40 / 15:50 IST). Different
        // anchor than the morning Google/Meta/Amazon/eBay fetches because Temu's reporting
        // window publishes mid-afternoon; the same hourly retry pattern applies.
        $retryFiveTimesUntil('temu:fetch-ads-data --period=L30', 'temu-ads-data-sync-l30', '15:40');
        $retryFiveTimesUntil('temu:fetch-ads-data --period=L60', 'temu-ads-data-sync-l60', '15:50');

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

        // newegg_orders / newegg_order_items are the LIVE source for /newegg/daily-sales and
        // the all-marketplace-master Newegg L60/L30/L7 + Y sales. Without this pull the table
        // freezes and those pages stop updating. 60-day window keeps L60 fresh and captures
        // late void/refund status changes. Must run from a Newegg-whitelisted server.
        $ist($schedule->command('newegg:orders --days=60 --save')
            ->twiceDaily(9, 18)
            ->name('fetch-newegg-orders')
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

        /*
        |--------------------------------------------------------------------------
        | PRODUCT MASTER — LP / CBM / FRGHT FORMULA SYNC
        |--------------------------------------------------------------------------
        | Source-of-truth formula:
        |   CBM   = (L * 2.54) * (W * 2.54) * (H * 2.54) / 1,000,000
        |   FRGHT = CBM * 200
        |   LP    = CP + FRGHT
        |
        | The same calculation also runs in App\Models\ProductMaster::booted()
        | on every save, so this scheduled command is the safety net for rows
        | that bypass the model (direct DB writes, historical/imported data,
        | SKUs in CustomLpMappingService whose custom LP changes, etc.).
        |
        | Only re-saves a product when LP/CBM/FRGHT actually differ, so
        | `updated_at` is not churned on every tick.
        */
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
        // TikTok Ads API — 5 afternoon runs (final slot 15:45 IST), aligned with the
        // Temu pulls because TikTok's publisher feed updates around the same time.
        $retryFiveTimesUntil('sync:tiktok-api-data', 'sync-tiktok-api-data', '15:45');

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

        /*
        |--------------------------------------------------------------------------
        | SHIPMENT TRACKING (All Orders Status column)
        |--------------------------------------------------------------------------
        | Refresh live shipment status from the tracking provider every 3 hours.
        | Runs 24/7 (no IST window) so status stays current; --stale guards quota.
        */
        $schedule->command('tracking:sync-status --stale=150')
            ->everyThreeHours()
            ->name('shipment-tracking-sync-status')
            ->withoutOverlapping(170)
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
