<?php

namespace Tests\Unit;

use App\Models\AmazonOrder;
use App\Services\GofoExpressService;
use App\Services\MarketplaceManager\AmazonTrackingSyncService;
use PHPUnit\Framework\TestCase;

class AmazonSofTrackingExtractTest extends TestCase
{
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

    public function test_tracking_batch_reserves_slots_for_missing_shipped(): void
    {
        $sizes = AmazonTrackingSyncService::trackingBatchSizes(40);

        $this->assertSame(40, $sizes['unshipped']);
        $this->assertSame(120, $sizes['missing']);
        $this->assertGreaterThanOrEqual($sizes['unshipped'], $sizes['missing']);
    }
}
