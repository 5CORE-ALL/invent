<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\BestBuySprcDilAutoPushService;
use Illuminate\Console\Command;

/**
 * Headless "Push on reload" for /bestbuy-pricing (same as amazon:push-blue-prices).
 *
 * Blue triangle = INV > 0, listed and BB Price ≠ S PRC. Every run checks all such SKUs and pushes the S PRC
 * wherever Best Buy is still different. BB Price follows the push after the next MCM OF21 sync, so a SKU
 * pushed within --cooldown is left alone.
 * Each push waits for the MCM import result, so --max-pushes keeps a run short.
 */
class BestBuyPushBluePricesCommand extends Command
{
    protected $signature = 'bestbuy:push-blue-prices
        {--dry-run : Compute only, do NOT push to Best Buy}
        {--max-pushes=40 : Max Best Buy pushes per run (rest next run)}
        {--cooldown=60 : Minutes the MCM import + next sync get before the same SKU is re-pushed}
        {--sleep-ms=300 : Delay between Best Buy pushes (ms)}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Always check + push Best Buy prices for blue-triangle SKUs (Price ≠ S PRC) without the page open.';

    public function handle(BestBuySprcDilAutoPushService $service): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled('bestbuy')) {
            $this->warn('Push on reload is off for Best Buy — skipped.');

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
            'Best Buy blue catch-up: candidates=%d pushed=%d failed=%d unchanged=%d skipped=%d',
            (int) ($s['candidates'] ?? 0),
            (int) ($s['pushed'] ?? 0),
            (int) ($s['push_failed'] ?? 0),
            (int) ($s['skipped_unchanged'] ?? 0),
            (int) ($s['skipped'] ?? 0),
        ));
        foreach (array_slice($s['errors'] ?? [], 0, 10) as $err) {
            $this->warn('  · '.$err);
        }

        return self::SUCCESS;
    }
}
