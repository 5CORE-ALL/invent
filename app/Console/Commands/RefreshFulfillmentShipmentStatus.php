<?php

namespace App\Console\Commands;

use App\Jobs\SyncMarketplaceOrdersJob;
use App\Services\ShipmentTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Refresh open shipment / order statuses used by Sales Order Fulfillment.
 *
 * 1) Carrier tracking (shopify_raw_orders.shipment_status) until Delivered/Expired
 * 2) Marketplace order fetches so Label Created / No Scan statuses can advance
 */
class RefreshFulfillmentShipmentStatus extends Command
{
    protected $signature = 'fulfillment:refresh-shipment-status
                            {--stale=25 : Skip tracking numbers checked within the last N minutes}
                            {--limit= : Max tracking numbers this run (defaults to config)}
                            {--skip-tracking : Do not call tracking:sync-status}
                            {--skip-orders : Do not re-fetch marketplace orders}
                            {--days=30 : Marketplace order lookback days}';

    protected $description = 'Refresh open shipment statuses (carrier + marketplace) until delivered — used by Sales Order Fulfillment.';

    /** Marketplaces that commonly sit in Label Created / No Scan until status advances. */
    protected array $orderSyncMarketplaces = [
        'amazon',
        'temu',
        'ebay1',
        'ebay2',
        'ebay3',
    ];

    public function handle(ShipmentTrackingService $tracking): int
    {
        $ok = true;

        if (! $this->option('skip-tracking')) {
            $ok = $this->refreshCarrierTracking($tracking) && $ok;
        }

        if (! $this->option('skip-orders')) {
            $this->queueMarketplaceOrderRefresh();
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    protected function refreshCarrierTracking(ShipmentTrackingService $tracking): bool
    {
        if (! $tracking->isConfigured()) {
            $this->warn('No tracking provider configured (USPS / UPS / TRACKING_API_KEY). Skipping carrier status refresh.');
            Log::warning('fulfillment:refresh-shipment-status skipped tracking — no provider configured');

            return true; // do not fail the whole run; order sync can still proceed
        }

        $params = [
            '--only-open' => true,
            '--stale' => max(0, (int) $this->option('stale')),
        ];
        $limit = $this->option('limit');
        if ($limit !== null && $limit !== '') {
            $params['--limit'] = (int) $limit;
        }

        $this->info('Refreshing open carrier shipment statuses…');
        $exit = Artisan::call('tracking:sync-status', $params);
        $output = trim(Artisan::output());
        if ($output !== '') {
            $this->line($output);
        }

        Log::info('fulfillment:refresh-shipment-status tracking done', [
            'exit' => $exit,
        ]);

        return $exit === self::SUCCESS;
    }

    protected function queueMarketplaceOrderRefresh(): void
    {
        $days = max(1, min(90, (int) $this->option('days')));
        $this->info("Queueing marketplace order refreshes (last {$days} days)…");

        foreach ($this->orderSyncMarketplaces as $slug) {
            SyncMarketplaceOrdersJob::dispatch($slug, '', true, $days);
            $this->line("  queued: {$slug}");
        }

        Log::info('fulfillment:refresh-shipment-status queued marketplace order syncs', [
            'marketplaces' => $this->orderSyncMarketplaces,
            'days' => $days,
        ]);
    }
}
