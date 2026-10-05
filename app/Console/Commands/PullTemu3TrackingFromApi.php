<?php

namespace App\Console\Commands;

use App\Models\Temu3ApiOrder;
use App\Services\MarketplaceManager\Temu3OrderTrackingPullService;
use Illuminate\Console\Command;

class PullTemu3TrackingFromApi extends Command
{
    protected $signature = 'temu3:pull-tracking
                            {--limit=40 : Max parent orders to fetch}
                            {--order= : Pull a single Temu 3 parent_order_sn}
                            {--refresh : Re-fetch even when tracking_number already set}';

    protected $description = 'Fetch tracking number + carrier from Temu 3 OpenAPI into temu3_api_orders for Sales Order Fulfillment (no Shopify/CSV).';

    public function handle(Temu3OrderTrackingPullService $pull): int
    {
        $orderId = trim((string) $this->option('order'));
        $refresh = (bool) $this->option('refresh');

        if ($orderId !== '') {
            if (! Temu3ApiOrder::query()->where('parent_order_sn', $orderId)->exists()) {
                $this->error("Temu 3 order {$orderId} not found in temu3_api_orders.");

                return self::FAILURE;
            }

            $result = $pull->pullForParentOrder($orderId, $refresh);
            $this->info($result['message'] ?? json_encode($result));

            return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
        }

        $result = $pull->pullPending(max(1, (int) $this->option('limit')), $refresh);
        $this->info($result['message'] ?? json_encode($result));

        return ! empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
