<?php

namespace Tests\Unit;

use App\Support\DobaTrackingNumber;
use PHPUnit\Framework\TestCase;

class DobaTrackingNumberTest extends TestCase
{
    public function test_prepaid_label_tracking_is_kept_without_the_carrier_suffix(): void
    {
        $hit = DobaTrackingNumber::fromOrderPayload([
            'trackingNumber' => '',
            'buyerPrepaidLabelList' => [
                ['trackingNumber' => '1Z16E50BYW58136534(UPS)', 'carrier' => 'UPS'],
            ],
        ]);

        $this->assertSame('1Z16E50BYW58136534', $hit['tracking']);
        $this->assertSame('UPS', $hit['carrier']);
    }

    public function test_nested_doba_logistics_number_is_the_waybill(): void
    {
        $hit = DobaTrackingNumber::fromOrderPayload([
            'ordBusiId' => 'DO-100',
            'ordStatus' => 'In Transit',
            'trackingNumber' => '',
            'orderLogisticsList' => [
                [
                    'logisticsNo' => '9400111899223856927591',
                    'logisticsName' => 'USPS',
                ],
            ],
        ]);

        $this->assertSame('9400111899223856927591', $hit['tracking']);
        $this->assertSame('USPS', $hit['carrier']);
    }
}
