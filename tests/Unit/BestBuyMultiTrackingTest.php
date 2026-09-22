<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\BestBuyTrackingSyncService;
use App\Services\MarketplaceManager\ShopifyFulfillmentTrackingMatcher;
use PHPUnit\Framework\TestCase;

class BestBuyMultiTrackingTest extends TestCase
{
    public function test_first_pushed_tracking_does_not_mark_a_qty_2_line_complete(): void
    {
        $raw = [
            'shopify_tracking_pushed' => '9344810990370309233863',
        ];

        $this->assertSame(
            ['9344810990370309233863'],
            BestBuyTrackingSyncService::trackingListFromPayload($raw)
        );
        $this->assertSame(1, BestBuyTrackingSyncService::remainingShipQty(2, $raw));
        $this->assertSame(0, BestBuyTrackingSyncService::remainingShipQty(1, $raw));
    }

    public function test_legacy_tracking_number_is_not_treated_as_a_best_buy_push(): void
    {
        $raw = [
            'tracking_number' => '9344810990370309233863',
        ];

        $this->assertSame([], BestBuyTrackingSyncService::trackingListFromPayload($raw));
        $this->assertSame(2, BestBuyTrackingSyncService::remainingShipQty(2, $raw));
    }

    public function test_second_label_is_counted_after_partial_ship(): void
    {
        $raw = [
            'shopify_trackings_pushed' => ['9344810990370309233863'],
            'shopify_tracking_pushed' => '9344810990370309233863',
            'shopify_shipped_qty' => 1,
        ];

        $this->assertSame(1, BestBuyTrackingSyncService::remainingShipQty(2, $raw));

        $raw['shopify_trackings_pushed'][] = '9400111899223197428000';
        $raw['shopify_shipped_qty'] = 2;

        $this->assertSame(0, BestBuyTrackingSyncService::remainingShipQty(2, $raw));
        $this->assertCount(2, BestBuyTrackingSyncService::trackingListFromPayload($raw));
    }

    public function test_fulfillment_quantity_follows_the_matching_sku_line(): void
    {
        $matcher = new ShopifyFulfillmentTrackingMatcher;

        $this->assertSame(1, $matcher->fulfillmentSkuQuantity([
            'line_items' => [
                ['sku' => '5C-HOME10-MIC', 'quantity' => 1],
                ['sku' => 'OTHER', 'quantity' => 4],
            ],
        ], '5C-HOME10-MIC', []));
    }
}
