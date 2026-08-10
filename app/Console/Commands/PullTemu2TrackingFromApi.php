<?php

namespace App\Console\Commands;

use App\Models\Temu2Order;
use App\Services\MarketplaceManager\Temu2OrderTrackingPullService;
use Illuminate\Console\Command;

class PullTemu2TrackingFromApi extends Command
{
    protected $signature = 'temu2:pull-tracking
                            {--limit=40 : Max parent orders to fetch}
                            {--order= : Pull a single Temu 2 parent_order_sn}
                            {--refresh : Re-fetch even when tracking_number already set}';

    protected $description = 'Fetch tracking number + carrier from Temu 2 OpenAPI into temu2_orders for Sales Order Fulfillment (no Shopify/CSV).';

    public function handle(Temu2OrderTrackingPullService $pull): int
    {
        $orderId = trim((string) $this->option('order'));
        $refresh = (bool) $this->option('refresh');

        if ($orderId !== '') {
            if (! Temu2Order::query()->where('parent_order_sn', $orderId)->exists()) {
                $this->error("Temu 2 order {$orderId} not found in temu2_orders.");

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
