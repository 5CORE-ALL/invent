<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\AliexpressSprcDilAutoPushService;
use Illuminate\Console\Command;

/**
 * Headless "Push on reload" for /aliexpress-pricing (same as amazon:push-blue-prices).
 *
 * Blue triangle = INV > 0, listed and AliExpress Price ≠ S PRC. Every run re-prices every SKU with the Std prc vs dil
 * stack (discounts, SNROI Cap, LMP cap, Std max), saves S PRC where it changed and pushes the S PRC wherever the live
 * Price still differs. A successful push writes the pushed price into the Price column at once, so the page shows it
 * without waiting for the daily listed-price sync. A SKU AliExpress rejected is retried after --cooldown.
 *
 * Changing a rule or the SNROI Cap re-saves S PRC straight away (ChannelPromoPricingController); this job then pushes it
 * within minutes instead of waiting for the 04:15 / 20:15 IST run.
 */
class AliexpressPushBluePricesCommand extends Command
{
    protected $signature = 'aliexpress:push-blue-prices
        {--dry-run : Compute only, do NOT push to AliExpress}
        {--cooldown=60 : Minutes a SKU that was just pushed (or rejected) is left alone before it is re-pushed}
        {--sleep-ms=300 : Delay between AliExpress price API batches (ms)}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Always check + push AliExpress prices for blue-triangle SKUs (Price ≠ S PRC) without the page open.';

    public function handle(AliexpressSprcDilAutoPushService $service): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled('aliexpress')) {
            $this->warn('Push on reload is off for AliExpress — skipped.');

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
            cooldownMinutes: max(0, (int) $this->option('cooldown')),
        );

        $s = $summary['stats'] ?? [];
        $this->info(sprintf(
            'AliExpress blue catch-up: candidates=%d pushed=%d failed=%d unchanged=%d skipped=%d',
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
