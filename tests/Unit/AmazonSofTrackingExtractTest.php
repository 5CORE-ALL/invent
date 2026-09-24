<?php

namespace Tests\Unit;

use App\Models\AmazonOrder;
use App\Services\GofoExpressService;
use App\Services\MarketplaceManager\AmazonSpOrdersClient;
use App\Services\MarketplaceManager\AmazonTrackingSyncService;
use PHPUnit\Framework\TestCase;

class AmazonSofTrackingExtractTest extends TestCase
{
    public function test_reads_fbm_packages_from_orders_v2026(): void
    {
        $hit = AmazonSpOrdersClient::trackingFromOrderPackages([
            'orderId' => '112-2020581-4711422',
            'packages' => [
                [
                    'packageReferenceId' => 'PKG-1',
                    'carrier' => 'GOFO',
                    'trackingNumber' => 'GFUS01074141474180',
                ],
            ],
        ]);

        $this->assertSame('GFUS01074141474180', $hit['tracking'] ?? null);
        $this->assertSame('GOFO', $hit['carrier'] ?? null);
    }

    public function test_ignores_amazon_order_id_stored_as_package_tracking(): void
    {
        $hit = AmazonSpOrdersClient::trackingFromOrderPackages([
            'orderId' => '112-2020581-4711422',
            'packages' => [
                ['trackingNumber' => '112-2020581-4711422', 'carrier' => 'Other'],
            ],
        ]);

        $this->assertNull($hit);
    }

    public function test_reads_package_tracking_details(): void
    {
        $hit = AmazonOrder::trackingFromDecoded([
            'AmazonOrderId' => '113-1234567-1234567',
            'PackageTrackingDetails' => [
                'TrackingNumber' => '9334610990150197992823',
                'CarrierCode' => 'USPS',
            ],
        ]);

        $this->assertSame('9334610990150197992823', $hit['tracking']);
        $this->assertSame('USPS', $hit['carrier']);
    }

    public function test_does_not_treat_amazon_order_id_as_tracking(): void
    {
        $hit = AmazonOrder::trackingFromDecoded([
            'tracking_number' => '113-1234567-1234567',
        ]);

        $this->assertSame('', $hit['tracking']);
    }

    public function test_gofo_adds_hyphenless_amazon_order_id(): void
    {
        $variants = GofoExpressService::orderNoVariants('113-1234567-1234567');

        $this->assertContains('113-1234567-1234567', $variants);
        $this->assertContains('11312345671234567', $variants);
        $this->assertContains('Amz113-1234567-1234567', $variants);
    }

    public function test_warehouse_refs_include_amz_shopify_name(): void
    {
        $refs = AmazonOrder::warehouseOrderRefs('111-6015777-6213066');

        $this->assertContains('111-6015777-6213066', $refs);
        $this->assertContains('Amz111-6015777-6213066', $refs);
        $this->assertContains('#Amz111-6015777-6213066', $refs);
    }

    public function test_reads_tracking_from_order_item_payload(): void
    {
        $hit = AmazonOrder::trackingFromDecoded([
            'OrderItemId' => '123',
            'tracking_number' => '9400111899351234567890',
            'carrier' => 'USPS',
        ]);

        $this->assertSame('9400111899351234567890', $hit['tracking']);
        $this->assertSame('USPS', $hit['carrier']);
    }

    public function test_reads_tracking_nested_under_order_items(): void
    {
        $hit = AmazonOrder::trackingFromDecoded([
            'AmazonOrderId' => '111-6593956-3036223',
            'OrderItems' => [
                [
                    'SellerSKU' => '6-51080 L D1',
                    'PackageTrackingDetails' => [
                        'TrackingNumber' => 'GFU1234567890123456',
                        'CarrierCode' => 'GOFO',
                    ],
                ],
            ],
        ]);

        $this->assertSame('GFU1234567890123456', $hit['tracking']);
        $this->assertSame('GOFO', $hit['carrier']);
    }

    public function test_tracking_batch_reserves_slots_for_missing_shipped(): void
    {
        $sizes = AmazonTrackingSyncService::trackingBatchSizes(40);

        $this->assertSame(40, $sizes['unshipped']);
        $this->assertSame(120, $sizes['missing']);
        $this->assertGreaterThanOrEqual($sizes['unshipped'], $sizes['missing']);
    }

    public function test_id_fill_returns_empty_when_no_ids(): void
    {
        $sync = (new \ReflectionClass(AmazonTrackingSyncService::class))
            ->newInstanceWithoutConstructor();

        $result = $sync->fillMissingSofTrackingForIds([]);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['checked']);
        $this->assertSame(0, $result['filled']);
    }
}
