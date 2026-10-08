<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\ShopifyGraphqlFulfiller;
use PHPUnit\Framework\TestCase;

class ShopifyGraphqlFulfillerTest extends TestCase
{
    public function test_throttle_wait_covers_missing_points(): void
    {
        $this->assertSame(4.0, ShopifyGraphqlFulfiller::throttleWait(['currentlyAvailable' => 100, 'restoreRate' => 50], 300));
        $this->assertSame(0.5, ShopifyGraphqlFulfiller::throttleWait(['currentlyAvailable' => 900, 'restoreRate' => 50], 300));
        $this->assertSame(15.0, ShopifyGraphqlFulfiller::throttleWait(['currentlyAvailable' => 0, 'restoreRate' => 1], 300));
        $this->assertSame(2.0, ShopifyGraphqlFulfiller::throttleWait(null, 300));
    }

    public function test_has_tracking_only_counts_successful_fulfillments(): void
    {
        $order = ['fulfillments' => [
            ['status' => 'CANCELLED', 'trackingInfo' => [['number' => 'GFUS01077149048578']]],
            ['status' => 'SUCCESS', 'trackingInfo' => [['number' => '1Z 999 AA1']]],
        ]];

        $this->assertFalse(ShopifyGraphqlFulfiller::hasTracking($order, 'GFUS01077149048578'));
        $this->assertTrue(ShopifyGraphqlFulfiller::hasTracking($order, '1Z999AA1'));
    }

    public function test_open_lines_skips_closed_and_held_fulfillment_orders(): void
    {
        $order = ['fulfillmentOrders' => ['nodes' => [
            ['id' => 'fo1', 'status' => 'OPEN', 'supportedActions' => [['action' => 'CREATE_FULFILLMENT']], 'lineItems' => ['nodes' => [
                ['id' => 'l1', 'remainingQuantity' => 2, 'lineItem' => ['sku' => 'A']],
                ['id' => 'l2', 'remainingQuantity' => 0, 'lineItem' => ['sku' => 'B']],
            ]]],
            ['id' => 'fo2', 'status' => 'ON_HOLD', 'supportedActions' => [['action' => 'RELEASE_HOLD']], 'lineItems' => ['nodes' => [
                ['id' => 'l3', 'remainingQuantity' => 1, 'lineItem' => ['sku' => 'C']],
            ]]],
            ['id' => 'fo3', 'status' => 'CLOSED', 'supportedActions' => [], 'lineItems' => ['nodes' => [
                ['id' => 'l4', 'remainingQuantity' => 1, 'lineItem' => ['sku' => 'D']],
            ]]],
        ]]];

        $this->assertSame(['fo1' => [['id' => 'l1', 'quantity' => 2]]], ShopifyGraphqlFulfiller::openLines($order, null));
    }
}
