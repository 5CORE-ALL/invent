<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\EbayDilCouponAutoApplyService;
use App\Support\EbayRuleSprice;
use Illuminate\Console\Command;

/**
 * Headless EL30 Dil % coupon for /ebay-tabulator-view, /ebay2-tabulator-view and /ebay3-tabulator-view.
 * Runs every 10 minutes (see Kernel), right after the blue price push and the Dil→PRMT job, using the coupon
 * switch and slabs saved on each page. See EbayDilCouponAutoApplyService for the safety rules.
 */
class EbayDilCouponAutoApplyCommand extends Command
{
    protected $signature = 'ebay:dil-coupon-auto-apply
        {channel=all : ebay1, ebay2, ebay3 or all}
        {--dry-run : Compute only. Do NOT call eBay}
        {--max= : Max SKUs sent to eBay per channel per run (default 120)}
        {--strip-stacked : Also REMOVE the coupon from SKUs that are on a PRMT sale (default: leave their coupon alone)}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Push the EL30 Dil % coupon for eBay 1/2/3 (saved switch + slabs, no stacking with PRMT) without the page open.';

    public function handle(EbayDilCouponAutoApplyService $service): int
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
        $maxOpt = $this->option('max');
        $max = ($maxOpt !== null && $maxOpt !== '') ? max(1, (int) $maxOpt) : null;
        $failed = false;

        foreach ($channels as $channel) {
            if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled($channel)) {
                $this->warn('Push on reload is off for '.$channel.' — skipped.');

                continue;
            }

            $stats = $service->run($channel, $dryRun, fn (string $msg) => $this->line($msg), $max, (bool) $this->option('strip-stacked'));
            foreach (array_slice($stats['errors'], 0, 10) as $err) {
                $this->warn('  · '.$err);
            }
            if (! $dryRun && $stats['errors'] !== [] && $stats['ok'] === 0 && $stats['sent'] > 0) {
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
