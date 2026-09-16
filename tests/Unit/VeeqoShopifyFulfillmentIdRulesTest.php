<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use PHPUnit\Framework\TestCase;

class VeeqoShopifyFulfillmentIdRulesTest extends TestCase
{
    public function test_tiktok_13_digit_order_id_is_kept_when_shopify_rest_id_differs(): void
    {
        $this->assertFalse(VeeqoShopifyFulfillmentService::isShopifyAdminRestId(
            '2560943161723',
            '7159464132845'
        ));
        $this->assertFalse(VeeqoShopifyFulfillmentService::isShopifyAdminRestId(
            'tiktok-2560943161723',
            '7159464132845'
        ));
    }

    public function test_this_orders_shopify_rest_id_is_internal(): void
    {
        $this->assertTrue(VeeqoShopifyFulfillmentService::isShopifyAdminRestId(
            '7159464132845',
            '7159464132845'
        ));
        $this->assertTrue(VeeqoShopifyFulfillmentService::isShopifyAdminRestId(
            '#7159464132845',
            '7159464132845'
        ));
    }

    public function test_13_digit_ids_are_kept_when_shopify_rest_id_is_unknown(): void
    {
        $this->assertFalse(VeeqoShopifyFulfillmentService::isShopifyAdminRestId('2560943161723'));
        $this->assertFalse(VeeqoShopifyFulfillmentService::isShopifyAdminRestId('2560943161723', ''));
    }

    public function test_doba_dated_ids_are_never_internal(): void
    {
        $this->assertFalse(VeeqoShopifyFulfillmentService::isShopifyAdminRestId(
            '2608306873212',
            '7159464132845'
        ));
        $this->assertFalse(VeeqoShopifyFulfillmentService::isShopifyAdminRestId(
            '26083068732127',
            '7159464132845'
        ));
    }

    public function test_short_and_non_numeric_refs_are_not_internal(): void
    {
        $this->assertFalse(VeeqoShopifyFulfillmentService::isShopifyAdminRestId('334262', '7159464132845'));
        $this->assertFalse(VeeqoShopifyFulfillmentService::isShopifyAdminRestId('GSU1RG5550019KF', '7159464132845'));
        $this->assertFalse(VeeqoShopifyFulfillmentService::isShopifyAdminRestId('249001016086', '7159464132845'));
    }
}
