<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\PlsSprcDilAutoPushService;
use Illuminate\Console\Command;

/**
 * Headless "Push on reload" for /pls-pricing (same as amazon:push-blue-prices).
 *
 * Blue triangle = INV > 0, listed and PLS Price ≠ S PRC. Every run checks all such SKUs and pushes the S PRC
 * wherever PLS is still different. The live PLS price is pulled first, so a SKU where PLS is already right only has its Price column
 * repaired. A SKU that is still wrong after --cooldown (PLS reverted it) is pushed again.
 * Each push is two ProLightSounds API calls plus one pull, so --max-pushes keeps a run short.
 */
class PlsPushBluePricesCommand extends Command
{
    protected $signature = 'pls:push-blue-prices
        {--dry-run : Compute only, do NOT push to PLS}
        {--max-pushes=300 : Max PLS pushes per run (rest next run)}
        {--cooldown=10 : Minutes the store gets to apply a push before the same SKU is checked/re-pushed}
        {--sleep-ms=300 : Delay between PLS pushes (ms)}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Always check + push PLS prices for blue-triangle SKUs (Price ≠ S PRC) without the page open.';

    public function handle(PlsSprcDilAutoPushService $service): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled('pls')) {
            $this->warn('Push on reload is off for PLS — skipped.');

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
            'PLS blue catch-up: candidates=%d pushed=%d failed=%d unchanged=%d repaired=%d skipped=%d',
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
