<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\SheinSprcDilAutoPushService;
use Illuminate\Console\Command;

/**
 * Headless "Push on reload" for /shein-pricing (same as amazon:push-blue-prices).
 *
 * Blue triangle = INV > 0, listed and Shein Price ≠ S PRC. Every run checks all such SKUs and pushes the S PRC
 * wherever Shein is still different. The live Shein price is pulled first, so a SKU where Shein is already right only has its Price column
 * repaired. A SKU that is still wrong after --cooldown (Shein reverted it) is pushed again.
 * Each SKU costs a live pull and a price save, so --max-pushes keeps a run short.
 */
class SheinPushBluePricesCommand extends Command
{
    protected $signature = 'shein:push-blue-prices
        {--dry-run : Compute only, do NOT push to Shein}
        {--max-pushes=300 : Max Shein pushes per run (rest next run)}
        {--cooldown=10 : Minutes the store gets to apply a push before the same SKU is checked/re-pushed}
        {--sleep-ms=300 : Delay between Shein pushes (ms)}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Always check + push Shein prices for blue-triangle SKUs (Price ≠ S PRC) without the page open.';

    public function handle(SheinSprcDilAutoPushService $service): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled('shein')) {
            $this->warn('Push on reload is off for Shein — skipped.');

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
            'Shein blue catch-up: candidates=%d pushed=%d failed=%d unchanged=%d repaired=%d skipped=%d',
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
