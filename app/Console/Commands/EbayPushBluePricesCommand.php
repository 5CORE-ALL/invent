<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\EbaySprcDilAutoPushService;
use App\Services\Support\ChannelPushSpriceDailyEnqueue;
use App\Support\EbayRuleSprice;
use Illuminate\Console\Command;

/**
 * Headless "Push on reload" for /ebay-tabulator-view, /ebay2-tabulator-view and /ebay3-tabulator-view
 * (same idea as amazon:push-blue-prices and shopify-b2c:push-blue-prices).
 *
 * Every run, per channel:
 *  1. Re-prices every SKU with the Std prc vs dil stack (LMP cap, Std cap) and saves S PRC where it changed.
 *  2. Queues the eBay push for every blue-triangle SKU (INV > 0, saved S PRC ≠ live Price).
 *     The runner pushes exactly the saved S PRC, skips negative SNROI and SKUs whose live Price already equals
 *     S PRC, then pulls the live price back from eBay (GetItem) so the Price column shows what eBay has.
 */
class EbayPushBluePricesCommand extends Command
{
    protected $signature = 'ebay:push-blue-prices
        {channel=all : ebay1, ebay2, ebay3 or all}
        {--dry-run : Compute only. Do NOT save SPRICE and do NOT push to eBay}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Always re-price + push eBay 1/2/3 blue-triangle SKUs (Price ≠ S PRC) without the page open.';

    public function handle(EbaySprcDilAutoPushService $service, ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $arg = strtolower(trim((string) $this->argument('channel')));
        $channels = $arg === 'all' || $arg === '' ? EbayRuleSprice::CHANNELS : [$arg];
        foreach ($channels as $c) {
            if (! in_array($c, EbayRuleSprice::CHANNELS, true)) {
                $this->error('channel must be one of: '.implode(', ', EbayRuleSprice::CHANNELS).' or all');

                return self::FAILURE;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        foreach ($channels as $channel) {
            if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled($channel)) {
                $this->warn('Push on reload is off for '.$channel.' — skipped.');

                continue;
            }

            $summary = $service->run(
                channel: $channel,
                dryRun: $dryRun,
                logger: fn (string $msg) => $this->line($msg)
            );
            $s = $summary['stats'] ?? [];
            foreach (array_slice($s['errors'] ?? [], 0, 10) as $err) {
                $this->warn('  · '.$err);
            }

            $queued = 0;
            if (! $dryRun) {
                $res = $enqueue->enqueueChannel($channel);
                $queued = (int) ($res['queued'] ?? 0);
                $this->info($channel.' push: '.($res['message'] ?? '').(($res['spawned'] ?? false) ? ' — worker started' : ''));
            }

            $this->info(sprintf(
                '%s blue catch-up: candidates=%d saved=%d unchanged=%d queued=%d neg_snroi_stay_blue=%d errors=%d',
                $channel,
                (int) ($s['candidates'] ?? 0),
                (int) ($s['applied'] ?? 0),
                (int) ($s['skipped_unchanged'] ?? 0),
                $queued,
                (int) ($s['neg_snroi'] ?? 0),
                count($s['errors'] ?? [])
            ));
        }

        return self::SUCCESS;
    }
}
