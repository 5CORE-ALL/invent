<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Services\ShopifyB2bSprcDilAutoPushService;
use Illuminate\Console\Command;

/**
 * Headless "Push on reload" for /shopify-b2b-pricing (same idea as shopify-b2c:push-blue-prices).
 *
 * Every run re-prices every listed SKU with the page's Sprc Dil formula (clearance SKUs included), saves S PRC where it
 * changed and pushes every SKU whose live Price differs from it to business5core.com. Negative SNROI is pushed too
 * (the page's "skip negative SNROI" switch is OFF by default).
 */
class ShopifyB2bPushBluePricesCommand extends Command
{
    protected $signature = 'shopify-b2b:push-blue-prices
        {--dry-run : Compute only. Do NOT save SPRICE and do NOT push to the store}
        {--no-push : Save S PRC only, do not push}
        {--sku=* : Only these SKUs}
        {--limit= : Only the first N candidates}
        {--max-pushes=120 : Most pushes in one run (the rest go in the next run)}
        {--respect-switch : Skip the run when the page Push on reload switch is off}';

    protected $description = 'Re-price + push Shopify B2B blue-triangle SKUs (Price ≠ S PRC, clearance included) without the page open.';

    public function handle(ShopifyB2bSprcDilAutoPushService $service): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
        @ini_set('memory_limit', '1G');

        if ($this->option('respect-switch') && ! ChannelPromoPricingController::isPageReloadPushEnabled('shopify_b2b')) {
            $this->warn('Push on reload is off for Shopify B2B — skipped.');

            return self::SUCCESS;
        }

        $skus = array_values(array_filter(array_map('trim', (array) $this->option('sku'))));
        $limit = $this->option('limit');

        $summary = $service->run(
            dryRun: (bool) $this->option('dry-run'),
            push: ! $this->option('no-push'),
            limit: is_numeric($limit) ? (int) $limit : null,
            onlySkus: $skus === [] ? null : $skus,
            maxPushes: max(1, (int) $this->option('max-pushes')),
            logger: fn (string $msg) => $this->line($msg)
        );
        $s = $summary['stats'] ?? [];
        foreach (array_slice($s['errors'] ?? [], 0, 10) as $err) {
            $this->warn('  · '.$err);
        }
        if ($this->option('dry-run')) {
            foreach (array_slice($summary['to_push'] ?? [], 0, 40) as $j) {
                $this->line(sprintf('  would push %s: %.2f → %.2f%s', $j['sku'], $j['live'], $j['price'], $j['clearance'] ? ' (clearance)' : ''));
            }
        }

        $this->info(sprintf(
            'Shopify B2B blue catch-up: candidates=%d saved=%d unchanged=%d blue=%d clearance=%d pushed=%d failed=%d left=%d errors=%d',
            (int) ($s['candidates'] ?? 0),
            (int) ($s['saved'] ?? 0),
            (int) ($s['unchanged'] ?? 0),
            (int) ($s['blue'] ?? 0),
            (int) ($s['clearance'] ?? 0),
            (int) ($s['pushed'] ?? 0),
            (int) ($s['failed'] ?? 0),
            (int) ($s['left_for_next_run'] ?? 0),
            count($s['errors'] ?? [])
        ));

        return self::SUCCESS;
    }
}
