<?php

namespace App\Console\Commands;

use App\Http\Controllers\Channels\OrderFulfillmentController;
use App\Services\FourSellerApiService;
use App\Services\GofoExpressService;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use App\Services\MarketplaceManager\WayfairTrackingSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only by default: for each order id, ask every tracking source whether it has a tracking number.
 * With --save the normal Order Fulfillment lookup runs for those orders and stores what it finds,
 * which is what takes an order off the Pending page.
 */
class FindOrderTrackingCommand extends Command
{
    protected $signature = 'order-fulfillment:find-tracking
        {orders* : Marketplace order ids (Wayfair PO numbers)}
        {--slug=wayfair : Marketplace slug}
        {--schema : Also print how Wayfair describes the label events query (arguments and filter fields)}
        {--save : Store the tracking that is found (same as the page lookup)}';

    protected $description = 'Check, per order id, which source (saved, marketplace row, Veeqo, Wayfair labels, GOFO, 4Seller, Shopify) has a tracking number';

    public function handle(): int
    {
        $slug = strtolower(trim((string) $this->option('slug')));
        $orders = array_values(array_filter(array_map('trim', (array) $this->argument('orders'))));
        $veeqo = app(VeeqoShopifyFulfillmentService::class);
        if ($this->option('schema') && $slug === 'wayfair') {
            $this->line(app(WayfairTrackingSyncService::class)->debugLabelEventsSchema());
        }

        foreach ($orders as $po) {
            $this->newLine();
            $this->info("=== {$slug} {$po} ===");
            $row = $slug === 'wayfair' && Schema::hasTable('wayfair_daily_data')
                ? DB::table('wayfair_daily_data')->where('po_number', $po)->first(['id', 'status', 'po_date', 'sku', 'carrier_code'])
                : null;
            if ($slug === 'wayfair') {
                $this->line($row
                    ? "  order row: id {$row->id}, status {$row->status}, po_date {$row->po_date}, carrier_code {$row->carrier_code}"
                    : '  order row: NOT FOUND in wayfair_daily_data');
            }

            if (Schema::hasTable('order_fulfillment_trackings')) {
                $saved = DB::table('order_fulfillment_trackings')->where('mm_slug', $slug)->where('order_id', $po)->get(['tracking_number', 'carrier', 'source']);
                $this->line('  saved on page: '.($saved->isEmpty() ? 'none' : $saved->map(fn ($s) => trim("{$s->tracking_number} {$s->carrier} ({$s->source})"))->implode(' ; ')));
            }

            $this->probe('marketplace row', fn () => $veeqo->localMarketplaceTracking($slug, [$po]));
            $this->probe('Veeqo', fn () => $veeqo->findVeeqoShipment([$po], true, '', [], 1));
            $this->probe('live marketplace', fn () => $veeqo->lookupLiveChannelTracking($slug, [$po]));
            if ($slug === 'wayfair') {
                $this->probe('Wayfair label events', function () use ($po) {
                    $hit = app(WayfairTrackingSyncService::class)->trackingForPo($po);

                    return $hit ? ['tracking' => $hit['tracking'], 'carrier' => $hit['carrier']] : null;
                });
            }
            if ($slug === 'wayfair') {
                $raw = app(WayfairTrackingSyncService::class)->debugLabelEvents($po);
                $this->line('  Wayfair raw answer:    '.$raw['message']);
            }
            $this->probe('GOFO', fn () => app(GofoExpressService::class)->findShipment([$po], true));
            $this->probe('4Seller', function () use ($po) {
                $fs = app(FourSellerApiService::class);
                $fs->setTimeout(8);

                return $fs->findShipment([$po], 1);
            });
            if ($row) {
                $this->probe('linked Shopify order', fn () => $veeqo->linkedShopifyTracking($slug, (int) $row->id));
                try {
                    $ctx = $veeqo->contextForMarketplaceOrder($slug, (int) $row->id);
                    $this->line('  Shopify copy: '.($ctx && ! empty($ctx['shopify_order_id']) ? $ctx['shopify_order_id'] : 'none linked'));
                } catch (\Throwable $e) {
                    $this->line('  Shopify copy: error '.$e->getMessage());
                }
            }
        }

        if ($this->option('save') && $orders !== []) {
            $this->newLine();
            $found = app(OrderFulfillmentController::class)->resolveTrackingNow(
                array_map(fn ($po) => ['mm_slug' => $slug, 'order_id' => $po], $orders),
                240
            );
            $this->info('Saved tracking for '.count($found).' of '.count($orders).' order(s): '.(implode(', ', array_keys($found)) ?: 'none'));
        }

        return self::SUCCESS;
    }

    /** @param  callable(): (array<string, mixed>|null)  $fn */
    protected function probe(string $label, callable $fn): void
    {
        try {
            $hit = $fn();
        } catch (\Throwable $e) {
            $this->line(sprintf('  %-22s error: %s', $label.':', mb_strimwidth($e->getMessage(), 0, 120, '…')));

            return;
        }
        $tracking = is_array($hit) ? trim((string) ($hit['tracking'] ?? '')) : '';
        $this->line(sprintf(
            '  %-22s %s',
            $label.':',
            $tracking !== '' ? "{$tracking} ".trim((string) ($hit['carrier'] ?? '')) : (is_array($hit) && ! empty($hit['retry']) ? 'busy, try again' : 'none')
        ));
    }
}
