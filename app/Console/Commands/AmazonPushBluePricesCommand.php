<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\AmazonSprcDilAutoPushService;
use Illuminate\Console\Command;

/**
 * Headless "Push on reload" for /amazon-tabulator-view.
 *
 * Blue triangle = INV > 0 and Price ≠ S PRC. Every run checks all such SKUs, pulls the live
 * Price when it may be stale, and pushes the S PRC wherever Amazon is still different.
 * Runs on the schedule whether or not the page is open.
 */
class AmazonPushBluePricesCommand extends Command
{
    protected $signature = 'amazon:push-blue-prices
        {--dry-run : Compute only, do NOT push to Amazon}
        {--max-pushes=300 : Max Amazon pushes per run (rest next run)}
        {--cooldown=10 : Minutes Amazon gets to apply a push before the same SKU is checked/re-pushed}
        {--sleep-ms=300 : Delay between Amazon Listings API calls (ms)}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Always check + push Amazon prices for blue-triangle SKUs (Price ≠ S PRC) without the page open.';

    public function handle(AmazonSprcDilAutoPushService $service): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled('amazon')) {
            $this->warn('Push on reload is off for Amazon — skipped.');

            return self::SUCCESS;
        }

        $summary = $service->run(
            dryRun: (bool) $this->option('dry-run'),
            skipPush: false,
            limit: null,
            sleepMs: max(0, (int) $this->option('sleep-ms')),
            onlySkus: null,
            logger: fn (string $msg) => $this->line($msg),
            pushAll: false,
            blueCatchUp: true,
            cooldownMinutes: max(0, (int) $this->option('cooldown')),
            maxPushes: max(0, (int) $this->option('max-pushes')),
        );

        $s = $summary['stats'] ?? [];
        $this->info(sprintf(
            'Amazon blue catch-up: candidates=%d pushed=%d failed=%d unchanged=%d price_repaired=%d skipped=%d',
            (int) ($s['candidates'] ?? 0),
            (int) ($s['pushed'] ?? 0),
            (int) ($s['push_failed'] ?? 0),
            (int) ($s['skipped_unchanged'] ?? 0),
            (int) ($s['price_repaired'] ?? 0),
            (int) ($s['skipped'] ?? 0),
        ));
        foreach (array_slice($s['errors'] ?? [], 0, 10) as $err) {
            $this->warn('  · '.$err);
        }

        return self::SUCCESS;
    }
}
