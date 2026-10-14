<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\AlibabaSprcDilAutoPushService;
use Illuminate\Console\Command;

/**
 * Headless "Push on reload" for /alibaba-analytics (same as amazon:push-blue-prices).
 *
 * Blue triangle = INV > 0, listed and Alibaba Price ≠ S PRC. Every run checks all such SKUs and pushes the S PRC
 * wherever Alibaba is still different. The live Alibaba price is pulled first, so a SKU where Alibaba is already right only has its Price column
 * repaired. A SKU that is still wrong after --cooldown (Alibaba reverted it) is pushed again.
 * Each SKU costs a live pull plus two Alibaba API calls (product lookup + price update), so --max-pushes keeps a run short.
 */
class AlibabaPushBluePricesCommand extends Command
{
    protected $signature = 'alibaba:push-blue-prices
        {--dry-run : Compute only, do NOT push to Alibaba}
        {--max-pushes=40 : Max Alibaba pushes per run (rest next run)}
        {--cooldown=60 : Minutes a SKU that was just pushed is left alone before it is re-pushed}
        {--sleep-ms=300 : Delay between Alibaba pushes (ms)}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Always check + push Alibaba prices for blue-triangle SKUs (Price ≠ S PRC) without the page open.';

    public function handle(AlibabaSprcDilAutoPushService $service): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled('alibaba')) {
            $this->warn('Push on reload is off for Alibaba — skipped.');

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
            'Alibaba blue catch-up: candidates=%d pushed=%d failed=%d unchanged=%d repaired=%d skipped=%d',
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
