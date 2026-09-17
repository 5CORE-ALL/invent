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

    public function test_tiktok_shopify_name_yields_platform_order_id(): void
    {
        $this->assertSame(
            '577572569413617223',
            VeeqoShopifyFulfillmentService::tiktokOrderIdFromShopifyName('#TT-577572569413617223')
        );
        $this->assertSame(
            '577572569413617223',
            VeeqoShopifyFulfillmentService::tiktokOrderIdFromShopifyName('tiktok-577572569413617223')
        );
        $this->assertSame('', VeeqoShopifyFulfillmentService::tiktokOrderIdFromShopifyName('#334262'));
    }

    public function test_tiktok_package_payload_exposes_gofo_tracking(): void
    {
        $hit = VeeqoShopifyFulfillmentService::trackingFromTikTokOrderPayload([
            'packages' => [
                [
                    'tracking_number' => 'GFUSO0107321428770',
                    'shipping_provider_name' => 'GOFO',
                ],
            ],
        ]);

        $this->assertSame('GFUSO0107321428770', $hit['tracking'] ?? null);
        $this->assertSame('GOFO', $hit['carrier'] ?? null);
    }

    public function test_shipped_and_in_transit_statuses_are_ready_unshipped_are_not(): void
    {
        $this->assertTrue(VeeqoShopifyFulfillmentService::marketplaceStatusLooksShipped('SHIPPED'));
        $this->assertTrue(VeeqoShopifyFulfillmentService::marketplaceStatusLooksShipped('IN_TRANSIT'));
        $this->assertTrue(VeeqoShopifyFulfillmentService::marketplaceStatusLooksShipped('AWAITING_COLLECTION'));
        $this->assertTrue(VeeqoShopifyFulfillmentService::marketplaceStatusLooksShipped('Delivered'));
        $this->assertFalse(VeeqoShopifyFulfillmentService::marketplaceStatusLooksShipped('AWAITING_SHIPMENT'));
        $this->assertFalse(VeeqoShopifyFulfillmentService::marketplaceStatusLooksShipped('CANCELLED'));
        $this->assertFalse(VeeqoShopifyFulfillmentService::marketplaceStatusLooksShipped(''));
    }

    public function test_partial_same_sku_reuses_existing_tracking_for_open_qty(): void
    {
        $this->assertTrue(VeeqoShopifyFulfillmentService::shouldFulfillRemainingWithExistingTracking(
            1,
            '923461099037030819380'
        ));
        $this->assertFalse(VeeqoShopifyFulfillmentService::shouldFulfillRemainingWithExistingTracking(
            0,
            '923461099037030819380'
        ));
        $this->assertFalse(VeeqoShopifyFulfillmentService::shouldFulfillRemainingWithExistingTracking(1, ''));
    }
}
