<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\PurchasingPowerSprcDilAutoPushService;
use Illuminate\Console\Command;

/**
 * Headless "Push on reload" for /purchasing-power-pricing (same as amazon:push-blue-prices).
 *
 * Blue triangle = INV > 0, listed and PP Price ≠ S PRC. Every run checks all such SKUs and pushes the S PRC
 * wherever Purchasing Power is still different. PP Price follows the push after the next MCM sync
 * (purchasing-power:sync), so a SKU pushed within --cooldown is left alone.
 * Each push waits for the MCM import result (about 20–40 s), so --max-pushes keeps a run short.
 */
class PurchasingPowerPushBluePricesCommand extends Command
{
    protected $signature = 'purchasing-power:push-blue-prices
        {--dry-run : Compute only, do NOT push to Purchasing Power}
        {--max-pushes=60 : Max Purchasing Power pushes per run (rest next run)}
        {--cooldown=20 : Minutes the MCM import + next sync (every 15 min) get before the same SKU is re-pushed}
        {--sleep-ms=300 : Delay between Purchasing Power pushes (ms)}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Always check + push Purchasing Power prices for blue-triangle SKUs (Price ≠ S PRC) without the page open.';

    public function handle(PurchasingPowerSprcDilAutoPushService $service): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled('purchasing_power')) {
            $this->warn('Push on reload is off for Purchasing Power — skipped.');

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
            'Purchasing Power blue catch-up: candidates=%d pushed=%d failed=%d unchanged=%d skipped=%d',
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
