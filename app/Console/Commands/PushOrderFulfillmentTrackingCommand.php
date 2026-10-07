<?php

namespace App\Console\Commands;

use App\Http\Controllers\Channels\OrderFulfillmentController;
use App\Services\OrderFulfillment\OrderFulfillmentShopifyPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PushOrderFulfillmentTrackingCommand extends Command
{
    protected $signature = 'order-fulfillment:push-tracking
        {--limit=60 : Tracking rows to process per run}
        {--budget=540 : Seconds this run may spend}
        {--slug= : Only this marketplace (ebay1, temu, amazon, …)}
        {--order= : Only this marketplace order id}
        {--dry-run : Show what would be fulfilled without writing to Shopify or any marketplace}
        {--status : Report tracking coverage and why rows are still unfulfilled on Shopify, then exit}';

    protected $description = 'Fulfil Shopify orders with the tracking numbers shown on /order-fulfillment (Veeqo, GOFO/4Seller, marketplace, manual) and push those numbers to marketplaces that have no tracking yet.';

    public function handle(OrderFulfillmentShopifyPushService $service): int
    {
        @set_time_limit(0);

        if ($this->option('status')) {
            return $this->printStatus($service);
        }

        $dryRun = (bool) $this->option('dry-run');
        try {
            $result = $service->run(
                max(1, (int) $this->option('limit')),
                max(20, (int) $this->option('budget')),
                $dryRun,
                $this->option('slug') ?: null,
                $this->option('order') ?: null
            );
        } catch (\Throwable $e) {
            Log::warning('order-fulfillment:push-tracking failed', ['error' => $e->getMessage()]);
            $this->error('FAILED: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($result['rows'] !== [] && ($dryRun || $this->getOutput()->isVerbose() || count($result['rows']) <= 40)) {
            $this->table(
                ['Marketplace', 'Order', 'SKU', 'Tracking', 'Shopify', 'Shopify order', 'Marketplace push', 'Detail'],
                array_map(static fn (array $r) => [
                    $r['marketplace'],
                    $r['order_id'],
                    mb_strimwidth((string) $r['sku'], 0, 22, '…'),
                    $r['tracking'],
                    $r['shopify'],
                    $r['shopify_order_id'],
                    $r['channel'],
                    mb_strimwidth(trim(($r['message'] ?? '').(isset($r['channel_message']) ? ' | '.$r['channel_message'] : '')), 0, 70, '…'),
                ], $result['rows'])
            );
        }

        $this->info(sprintf(
            '%s: %d row(s) checked — Shopify fulfilled %d, already had tracking %d, failed/skipped %d; marketplace pushed %d, failed %d, skipped %d (%.1fs).',
            $dryRun ? 'DRY RUN' : 'OK',
            $result['checked'],
            $result['shopify_fulfilled'],
            $result['shopify_already'],
            $result['shopify_failed'],
            $result['channel_pushed'],
            $result['channel_failed'],
            $result['channel_skipped'],
            $result['seconds']
        ));

        return self::SUCCESS;
    }

    protected function printStatus(OrderFulfillmentShopifyPushService $service): int
    {
        try {
            $coverage = app(OrderFulfillmentController::class)->trackingCoverageBySlug();
        } catch (\Throwable $e) {
            $this->warn('Page coverage unavailable: '.$e->getMessage());
            $coverage = [];
        }
        $report = $service->statusReport();

        $slugs = array_unique(array_merge(array_keys($coverage), array_keys($report['by_slug'])));
        sort($slugs);
        $this->info('Order Fulfillment page (default range) and Shopify push (last '.OrderFulfillmentShopifyPushService::MAX_ROW_AGE_DAYS.' days):');
        $this->table(
            ['Marketplace', 'Page lines', 'No tracking', 'Shopify fulfilled', 'Waiting', 'Due now'],
            array_map(static fn (string $slug) => [
                $slug,
                $coverage[$slug]['lines'] ?? '-',
                $coverage[$slug]['missing'] ?? '-',
                $report['by_slug'][$slug]['fulfilled'] ?? 0,
                $report['by_slug'][$slug]['waiting'] ?? 0,
                $report['by_slug'][$slug]['due_now'] ?? 0,
            ], $slugs)
        );

        if ($report['reasons'] !== []) {
            $this->info('Why rows with tracking are still unfulfilled on Shopify:');
            $this->table(
                ['Marketplace', 'Rows', 'Last result'],
                array_map(static fn (array $r) => [$r['slug'], $r['rows'], $r['reason']], array_slice($report['reasons'], 0, 40))
            );
        }

        return self::SUCCESS;
    }
}
