<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\PlsSprcDilAutoPushService;
use App\Services\Support\ChannelPushSpriceDailyEnqueue;
use Illuminate\Console\Command;

/**
 * Headless "Push on reload" for /pls-pricing (same as shopify-b2c:push-blue-prices).
 *
 * Every run:
 *  1. Re-prices every SKU with the Std prc vs dil stack and saves S PRC where it changed,
 *     so a changed rule, Std Prc or stock shows up within minutes.
 *  2. Queues the ProLightSounds push for every blue-triangle SKU (INV > 0, saved S PRC ≠ live Price).
 *     The shared S PRC worker (channel:push-sprice-run pls) sends it, skips negative SNROI and SKUs already at S PRC.
 */
class PlsPushBluePricesCommand extends Command
{
    protected $signature = 'pls:push-blue-prices
        {--dry-run : Compute only. Do NOT save SPRICE and do NOT push to PLS}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Always re-price + push PLS blue-triangle SKUs (Price ≠ S PRC) without the page open.';

    public function handle(PlsSprcDilAutoPushService $service, ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
        @ini_set('memory_limit', '1G');

        if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled('pls')) {
            $this->warn('Push on reload is off for PLS — skipped.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $summary = $service->run(
            dryRun: $dryRun,
            logger: fn (string $msg) => $this->line($msg)
        );
        $s = $summary['stats'] ?? [];
        foreach (array_slice($s['errors'] ?? [], 0, 10) as $err) {
            $this->warn('  · '.$err);
        }

        $queued = 0;
        if (! $dryRun) {
            $res = $enqueue->enqueueChannel('pls');
            $queued = (int) ($res['queued'] ?? 0);
            $this->info('PLS push: '.($res['message'] ?? '').(($res['spawned'] ?? false) ? ' — worker started' : ''));
        }

        $this->info(sprintf(
            'PLS blue catch-up: candidates=%d saved=%d unchanged=%d queued=%d errors=%d',
            (int) ($s['candidates'] ?? 0),
            (int) ($s['applied'] ?? 0),
            (int) ($s['skipped_unchanged'] ?? 0),
            $queued,
            count($s['errors'] ?? [])
        ));

        return self::SUCCESS;
    }
}
