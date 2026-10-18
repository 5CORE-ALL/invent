<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Http\Controllers\MarketPlace\NewTemuoneController;
use App\Services\Support\ChannelPushSpriceDailyEnqueue;
use Illuminate\Console\Command;
use Throwable;

/**
 * Headless "Push on reload" for /new-temuone (same idea as amazon:push-blue-prices / ebay:push-blue-prices).
 *
 * Every run:
 *  1. Re-prices every SKU with the page rules (Dil→SNROI + CVR + eBay/Amz/LMP cap) and saves NTO_SPRICE where it changed,
 *     the same persist the page data call does, so a changed rule, LMP, stock or Std Prc is picked up within minutes.
 *  2. Queues the Temu push of S Base Prc for every blue-triangle SKU (INV > 0, S Base ≠ live Temu base).
 *     The runner skips negative SNROI and SKUs whose live base already equals S Base.
 */
class NewTemuonePushBluePricesCommand extends Command
{
    protected $signature = 'newtemuone:push-blue-prices
        {--dry-run : Compute only. Do NOT save NTO_SPRICE and do NOT push to Temu}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Always re-price + push New Temu One blue-triangle SKUs (S Base ≠ Temu base) without the page open.';

    public function handle(NewTemuoneController $controller, ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled('newtemuone')) {
            $this->warn('Push on reload is off for New Temu One — skipped.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $summary = $controller->persistSuggestedCatalog(null, $dryRun);
        } catch (Throwable $e) {
            $this->error('New Temu One pricing failed: '.$e->getMessage());

            return self::FAILURE;
        }
        $stats = $summary['stats'] ?? [];

        $queued = 0;
        if (! $dryRun) {
            $res = $enqueue->enqueueChannel('newtemuone');
            $queued = (int) ($res['queued'] ?? 0);
            $this->info('New Temu One push: '.($res['message'] ?? '').(($res['spawned'] ?? false) ? ' — worker started' : ''));
        }

        $this->info(sprintf(
            'New Temu One blue catch-up: candidates=%d saved=%d reused=%d queued=%d',
            (int) ($stats['candidates'] ?? 0),
            (int) ($stats['applied'] ?? 0),
            (int) ($stats['reused'] ?? 0),
            $queued
        ));

        return self::SUCCESS;
    }
}
