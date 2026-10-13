<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\TopDawgSprcDilAutoPushService;
use Illuminate\Console\Command;

/**
 * Headless "Push on reload" for /topdawg-pricing (same as amazon:push-blue-prices).
 *
 * Blue triangle = INV > 0 and TD Price ≠ S PRC. Every run checks all such SKUs, pulls the live
 * Price when it may be stale, and pushes the S PRC wherever TopDawg is still different.
 * TopDawg reviews a push for 1–24h, so the same price is not re-submitted before --cooldown.
 */
class TopDawgPushBluePricesCommand extends Command
{
    protected $signature = 'topdawg:push-blue-prices
        {--dry-run : Compute only, do NOT push to TopDawg}
        {--max-pushes=300 : Max TopDawg pushes per run (rest next run)}
        {--cooldown=1440 : Minutes TopDawg gets to review a push before the same price is checked/re-pushed}
        {--sleep-ms=300 : Delay between TopDawg API calls (ms)}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Always check + push TopDawg prices for blue-triangle SKUs (Price ≠ S PRC) without the page open.';

    public function handle(TopDawgSprcDilAutoPushService $service): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled('topdawg')) {
            $this->warn('Push on reload is off for TopDawg — skipped.');

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
            'TopDawg blue catch-up: candidates=%d pushed=%d failed=%d unchanged=%d price_repaired=%d skipped=%d',
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
