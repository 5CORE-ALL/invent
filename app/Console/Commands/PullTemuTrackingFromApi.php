<?php

namespace App\Console\Commands;

use App\Models\TemuOrder;
use App\Services\MarketplaceManager\TemuOrderTrackingPullService;
use Illuminate\Console\Command;

class PullTemuTrackingFromApi extends Command
{
    protected $signature = 'temu:pull-tracking
                            {--limit=40 : Max parent orders to fetch}
                            {--order= : Pull a single Temu parent_order_sn}
                            {--refresh : Re-fetch even when tracking_number already set}';

    protected $description = 'Fetch tracking number + carrier from Temu OpenAPI into temu_orders for Sales Order Fulfillment (no Shopify/CSV).';

    public function handle(TemuOrderTrackingPullService $pull): int
    {
        $orderId = trim((string) $this->option('order'));
        $refresh = (bool) $this->option('refresh');

        if ($orderId !== '') {
            if (! TemuOrder::query()->where('parent_order_sn', $orderId)->exists()) {
                $this->error("Temu order {$orderId} not found in temu_orders.");

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
