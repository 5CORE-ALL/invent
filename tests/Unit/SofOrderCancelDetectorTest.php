<?php

namespace Tests\Unit;

use App\Support\Marketplace\SofOrderCancelDetector;
use PHPUnit\Framework\TestCase;

class SofOrderCancelDetectorTest extends TestCase
{
    public function test_aliexpress_cancel_statuses_are_detected(): void
    {
        $this->assertTrue(SofOrderCancelDetector::statusLooksCancelled('IN_CANCEL'));
        $this->assertTrue(SofOrderCancelDetector::statusLooksCancelled('ORDER_CANCEL'));
        $this->assertTrue(SofOrderCancelDetector::statusLooksCancelled('closed'));
        $this->assertTrue(SofOrderCancelDetector::statusLooksCancelled('refund_ok'));
        $this->assertFalse(SofOrderCancelDetector::statusLooksCancelled('WAIT_SELLER_SEND_GOODS'));
        $this->assertFalse(SofOrderCancelDetector::statusLooksCancelled('NO_REFUND'));
    }

    public function test_nested_aliexpress_payload_cancel_is_detected(): void
    {
        $this->assertTrue(SofOrderCancelDetector::payloadLooksCancelled([
            'order' => [
                'order_id' => '8123456789012345',
                'order_status' => 'IN_CANCEL',
            ],
            'line' => ['sku_code' => 'ABC'],
        ]));
        $this->assertTrue(SofOrderCancelDetector::payloadLooksCancelled([
            'order' => [
                'order_status' => 'WAIT_SELLER_SEND_GOODS',
                'refund_status' => 'refund_ok',
            ],
        ]));
        $this->assertFalse(SofOrderCancelDetector::payloadLooksCancelled([
            'order' => [
                'order_status' => 'WAIT_SELLER_SEND_GOODS',
                'refund_status' => 'no_refund',
            ],
        ]));
    }
}
