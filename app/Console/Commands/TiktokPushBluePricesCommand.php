<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\TiktokSprcDilAutoPushService;
use Illuminate\Console\Command;

/**
 * Headless "Push on reload" for /tiktok-pricing and /tiktok-2-pricing (same as amazon:push-blue-prices).
 *
 * Blue triangle = INV > 0, listed and TT Price ≠ S PRC. Every run checks all such SKUs and pushes the S PRC
 * wherever TikTok is still different. The live TikTok price is pulled first, so a SKU where TikTok is already right
 * only has its Price column repaired. A SKU that is still wrong after --cooldown (TikTok reverted it) is pushed again.
 * Each push is a price update plus a pull, so --max-pushes keeps a run short.
 */
class TiktokPushBluePricesCommand extends Command
{
    protected $signature = 'tiktok:push-blue-prices
        {--channel=tiktok : tiktok or tiktok2}
        {--dry-run : Compute only, do NOT push to TikTok}
        {--max-pushes=300 : Max TikTok pushes per run (rest next run)}
        {--cooldown=10 : Minutes TikTok gets to apply a push before the same SKU is checked/re-pushed}
        {--sleep-ms=300 : Delay between TikTok pushes (ms)}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Always check + push TikTok / TikTok 2 prices for blue-triangle SKUs (Price ≠ S PRC) without the page open.';

    public function handle(): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $channel = TiktokSprcDilAutoPushService::normalizeChannel((string) $this->option('channel'));
        $label = $channel === 'tiktok2' ? 'TikTok 2' : 'TikTok';

        if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled($channel)) {
            $this->warn('Push on reload is off for '.$label.' — skipped.');

            return self::SUCCESS;
        }

        $summary = TiktokSprcDilAutoPushService::forChannel($channel)->run(
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
            '%s blue catch-up: candidates=%d pushed=%d failed=%d unchanged=%d repaired=%d skipped=%d',
            $label,
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
