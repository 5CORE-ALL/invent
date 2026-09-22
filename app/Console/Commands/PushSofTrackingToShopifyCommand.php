<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Console\Command;

class PushSofTrackingToShopifyCommand extends Command
{
    protected $signature = 'sof:push-tracking-to-shopify
                            {--limit=80 : Max SOF rows with tracking to fulfill on Shopify}';

    protected $description = 'Fulfill unfulfilled Shopify copies using tracking already shown on /sales-order-fulfillment.';

    public function handle(VeeqoShopifyFulfillmentService $sync): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $result = $sync->pushSofPageTrackingToShopify($limit);
        $this->info($result['message'] ?? json_encode($result));

        return ! empty($result['success'] ?? true) ? self::SUCCESS : self::FAILURE;
    }
}
