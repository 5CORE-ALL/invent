<?php

namespace Tests\Unit;

use App\Models\AmazonOrder;
use App\Services\GofoExpressService;
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
}
