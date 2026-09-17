<?php

namespace Tests\Unit;

use App\Http\Controllers\Channels\SalesOrderFulfillmentController;
use App\Services\ShipmentTrackingService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SofPendingLabelRulesTest extends TestCase
{
    public function test_hyphenated_usps_counts_as_label_tracking(): void
    {
        $ref = new ReflectionClass(SalesOrderFulfillmentController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $looks = $ref->getMethod('looksLikeCarrierTrackingNumber');
        $hasLabel = $ref->getMethod('rowHasLabelTracking');

        $this->assertTrue($looks->invoke($ctrl, '9400-1118-9935-1234-5678-12'));
        $this->assertTrue($hasLabel->invoke($ctrl, [
            'tracking_number' => '9400-1118-9935-1234-5678-12',
        ]));
    }

    public function test_tiktok_awaiting_collection_is_not_warehouse_pending(): void
    {
        $ref = new ReflectionClass(SalesOrderFulfillmentController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $hasLabel = $ref->getMethod('rowHasLabelTracking');

        $this->assertTrue($hasLabel->invoke($ctrl, [
            'status' => 'AWAITING_COLLECTION',
            'tracking_number' => '',
        ]));
        $this->assertTrue($hasLabel->invoke($ctrl, [
            'shipment_status' => ShipmentTrackingService::STATUS_INFO_RECEIVED,
            'tracking_number' => '',
        ]));
    }

    public function test_amazon_order_id_is_not_tracking(): void
    {
        $ref = new ReflectionClass(SalesOrderFulfillmentController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $looks = $ref->getMethod('looksLikeCarrierTrackingNumber');

        $this->assertFalse($looks->invoke($ctrl, '113-1234567-1234567'));
    }
}
