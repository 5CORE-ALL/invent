<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\FaireSprcDilAutoPushService;
use Illuminate\Console\Command;

/**
 * Headless "Push on reload" for /faire-pricing (same as amazon:push-blue-prices).
 *
 * Blue triangle = INV > 0, listed and Faire Price ≠ S PRC. Every run checks all such SKUs and pushes the S PRC
 * wherever Faire is still different. The live Faire price is pulled first, so a SKU where Faire is already right only has its Price column
 * repaired. A SKU that is still wrong after --cooldown (Faire reverted it) is pushed again.
 * Each push is two Faire API calls plus one pull, so --max-pushes keeps a run short.
 */
class FairePushBluePricesCommand extends Command
{
    protected $signature = 'faire:push-blue-prices
        {--dry-run : Compute only, do NOT push to Faire}
        {--max-pushes=100 : Max Faire pushes per run (rest next run)}
        {--cooldown=60 : Minutes a SKU that was just pushed is left alone before it is re-pushed}
        {--sleep-ms=300 : Delay between Faire pushes (ms)}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Always check + push Faire prices for blue-triangle SKUs (Price ≠ S PRC) without the page open.';

    public function handle(FaireSprcDilAutoPushService $service): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled('faire')) {
            $this->warn('Push on reload is off for Faire — skipped.');

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
            'Faire blue catch-up: candidates=%d pushed=%d failed=%d unchanged=%d repaired=%d skipped=%d',
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
