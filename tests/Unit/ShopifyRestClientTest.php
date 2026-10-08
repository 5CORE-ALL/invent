<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\ShopifyRestClient;
use App\Services\OrderFulfillment\OrderFulfillmentShopifyPushService;
use PHPUnit\Framework\TestCase;

class ShopifyRestClientTest extends TestCase
{
    public function test_no_pause_while_bucket_has_room(): void
    {
        $this->assertSame(0.0, ShopifyRestClient::paceSeconds(10, 40, 0.0));
        $this->assertSame(0.0, ShopifyRestClient::paceSeconds(23, 40, 0.0));
    }

    public function test_pauses_more_as_bucket_fills(): void
    {
        $near = ShopifyRestClient::paceSeconds(30, 40, 0.0);
        $full = ShopifyRestClient::paceSeconds(40, 40, 0.0);

        $this->assertGreaterThan(0.0, $near);
        $this->assertGreaterThan($near, $full);
        $this->assertLessThanOrEqual(5.0, $full);
    }

    public function test_bucket_refills_over_time(): void
    {
        // 40-call bucket refills 2 calls per second: 10 s later a full bucket is half empty.
        $this->assertSame(0.0, ShopifyRestClient::paceSeconds(40, 40, 10.0));
    }

    public function test_rate_limit_results_are_recognised(): void
    {
        $this->assertTrue(OrderFulfillmentShopifyPushService::isRateLimitResult('shopify_rate_limited', ''));
        $this->assertTrue(OrderFulfillmentShopifyPushService::isRateLimitResult('shopify_fulfill_failed', 'Could not load Shopify fulfillment orders (HTTP 429).'));
        $this->assertTrue(OrderFulfillmentShopifyPushService::isRateLimitResult('shopify_fulfill_failed', 'Shopify fulfill failed (HTTP 429): Exceeded 2 calls per second for api client.'));
        $this->assertFalse(OrderFulfillmentShopifyPushService::isRateLimitResult('order_id_mismatch', 'Shopify order does not contain the full marketplace order id.'));
        $this->assertFalse(OrderFulfillmentShopifyPushService::isRateLimitResult('shopify_order_missing', 'Could not load the Shopify order to match marketplace order id + SKU.'));
    }
}
