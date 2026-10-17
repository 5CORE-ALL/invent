<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\ShopifyB2cSprcDilAutoPushService;
use App\Services\Support\ChannelPushSpriceDailyEnqueue;
use Illuminate\Console\Command;

/**
 * Headless "Push on reload" for /shopify-b2c-pricing (same idea as bestbuy:push-blue-prices).
 *
 * Every run:
 *  1. Re-prices every SKU with the Std prc vs dil stack (LMP cap, Amz cap) and saves S PRC where it changed,
 *     so a changed rule, LMP, A Price, Std Prc or stock shows up within minutes, not at the next 04:50 / 20:50 run.
 *  2. Queues the Shopify push for every blue-triangle SKU (INV > 0, saved S PRC ≠ live Price).
 *     The runner skips negative SNROI and SKUs whose live Price already equals S PRC.
 */
class ShopifyB2cPushBluePricesCommand extends Command
{
    protected $signature = 'shopify-b2c:push-blue-prices
        {--dry-run : Compute only. Do NOT save SPRICE and do NOT push to Shopify}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Always re-price + push Shopify B2C blue-triangle SKUs (Price ≠ S PRC) without the page open.';

    public function handle(ShopifyB2cSprcDilAutoPushService $service, ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled('shopify_b2c')) {
            $this->warn('Push on reload is off for Shopify B2C — skipped.');

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
            $res = $enqueue->enqueueChannel('shopify_b2c');
            $queued = (int) ($res['queued'] ?? 0);
            $this->info('Shopify B2C push: '.($res['message'] ?? '').(($res['spawned'] ?? false) ? ' — worker started' : ''));
        }

        $this->info(sprintf(
            'Shopify B2C blue catch-up: candidates=%d saved=%d unchanged=%d queued=%d neg_snroi_stay_blue=%d errors=%d',
            (int) ($s['candidates'] ?? 0),
            (int) ($s['applied'] ?? 0),
            (int) ($s['skipped_unchanged'] ?? 0),
            $queued,
            (int) ($s['neg_snroi'] ?? 0),
            count($s['errors'] ?? [])
        ));

        return self::SUCCESS;
    }
}
