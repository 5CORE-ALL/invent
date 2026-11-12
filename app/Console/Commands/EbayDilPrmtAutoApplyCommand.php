<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\EbayDilPrmtAutoApplyService;
use App\Support\EbayRuleSprice;
use Illuminate\Console\Command;

/**
 * Headless Dil vs PRMT% for /ebay-tabulator-view, /ebay2-tabulator-view and /ebay3-tabulator-view.
 * Runs every 10 minutes (see Kernel): a Dil change reaches the saved PRMT% and the eBay markdown sale
 * without the page being open and without pressing "Save & Apply".
 */
class EbayDilPrmtAutoApplyCommand extends Command
{
    protected $signature = 'ebay:dil-prmt-auto-apply
        {channel=all : ebay1, ebay2, ebay3 or all}
        {--dry-run : Compute only. Do NOT save PRMT% and do NOT push to eBay}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Apply Dil vs PRMT% (listing Dil) to eBay 1/2/3 and push changed PRMT% as markdown sales, without the page open.';

    public function handle(EbayDilPrmtAutoApplyService $service): int
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
        $failed = false;
        foreach ($channels as $channel) {
            if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled($channel)) {
                $this->warn('Push on reload is off for '.$channel.' — skipped.');

                continue;
            }

            $stats = $service->run($channel, $dryRun, fn (string $msg) => $this->line($msg));
            foreach (array_slice($stats['errors'], 0, 10) as $err) {
                $this->warn('  · '.$err);
            }
            if ($stats['errors'] !== [] && $stats['saved'] === 0 && $stats['queued'] === 0) {
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
