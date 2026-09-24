<?php

namespace Tests\Unit;

use App\Http\Controllers\Channels\SalesOrderFulfillmentController;
use App\Services\ShipmentTrackingService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SofInTransitDeliveredRulesTest extends TestCase
{
    public function test_usps_mid_unauthorized_is_not_persistable(): void
    {
        $detail = 'The requested MID is not authorized to access /tracking/9334610990150197992823. USPS implemented Tracking API Access Controls';

        $this->assertTrue(ShipmentTrackingService::isUnusableProviderFailure($detail));
        $this->assertFalse(ShipmentTrackingService::isPersistableResult([
            'status' => ShipmentTrackingService::STATUS_EXCEPTION,
            'detail' => $detail,
        ]));
    }

    public function test_five_day_old_usps_exception_counts_as_delivered(): void
    {
        $ref = new ReflectionClass(SalesOrderFulfillmentController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('rowLooksDelivered');

        $delivered = $method->invoke($ctrl, [
            'shipment_status' => ShipmentTrackingService::STATUS_EXCEPTION,
            'shipment_status_detail' => 'The requested MID is not authorized',
            'tracking_number' => '9334610990150197992823',
            'tracking_company' => 'USPS',
            'order_date' => Carbon::now('America/New_York')->subDays(8)->format('Y-m-d H:i:s'),
            'status' => 'Shipped',
        ]);
        $this->assertTrue($delivered);

        $recent = $method->invoke($ctrl, [
            'shipment_status' => ShipmentTrackingService::STATUS_EXCEPTION,
            'shipment_status_detail' => 'The requested MID is not authorized',
            'tracking_number' => '9334610990150197992823',
            'tracking_company' => 'USPS',
            'order_date' => Carbon::now('America/New_York')->subHours(12)->format('Y-m-d H:i:s'),
            'status' => 'Shipped',
        ]);
        $this->assertFalse($recent);
    }

    public function test_doba_in_transit_status_is_recognized(): void
    {
        $ref = new ReflectionClass(SalesOrderFulfillmentController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('dobaStatusIsInTransit');

        $this->assertTrue($method->invoke($ctrl, 'In Transit'));
        $this->assertTrue($method->invoke($ctrl, 'IN_TRANSIT'));
        $this->assertTrue($method->invoke($ctrl, 'InTransit'));
        $this->assertFalse($method->invoke($ctrl, 'Unshipped'));
        $this->assertFalse($method->invoke($ctrl, 'Completed'));
    }

    public function test_no_scan_is_only_carrier_awaiting_shipment(): void
    {
        $ref = new ReflectionClass(SalesOrderFulfillmentController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('carrierIsAwaitingShipment');
        $now = Carbon::now('America/New_York')->format('Y-m-d H:i:s');
        $old = Carbon::now('America/New_York')->subDays(4)->format('Y-m-d H:i:s');
        $tn = 'GFUS01074521677056';

        $this->assertTrue($method->invoke($ctrl, [
            'tracking_number' => $tn,
            'shipment_status' => ShipmentTrackingService::STATUS_INFO_RECEIVED,
            'shipment_status_detail' => 'Shipping Label Created',
            'order_date' => $old,
        ]));
        $this->assertTrue($method->invoke($ctrl, [
            'tracking_number' => $tn,
            'shipment_status' => ShipmentTrackingService::STATUS_EXCEPTION,
            'shipment_status_detail' => 'Pickup Exception, GOFO has not Received the Package from the Warehouse yet.',
            'order_date' => $old,
        ]));
        $this->assertTrue($method->invoke($ctrl, [
            'tracking_number' => $tn,
            'shipment_status' => '',
            'order_date' => $now,
        ]));
        $this->assertFalse($method->invoke($ctrl, [
            'tracking_number' => $tn,
            'shipment_status' => '',
            'order_date' => $old,
        ]));
        $this->assertFalse($method->invoke($ctrl, [
            'tracking_number' => $tn,
            'shipment_status' => ShipmentTrackingService::STATUS_IN_TRANSIT,
            'order_date' => $now,
        ]));
        $this->assertFalse($method->invoke($ctrl, [
            'tracking_number' => $tn,
            'shipment_status' => ShipmentTrackingService::STATUS_NOT_FOUND,
            'order_date' => $now,
        ]));
        $this->assertFalse($method->invoke($ctrl, [
            'tracking_number' => $tn,
            'shipment_status' => ShipmentTrackingService::STATUS_EXCEPTION,
            'shipment_status_detail' => 'The requested MID is not authorized to access /tracking/9334610990150197992823',
            'order_date' => $now,
        ]));
    }

    public function test_carrier_delivered_and_marketplace_completed_count(): void
    {
        $ref = new ReflectionClass(SalesOrderFulfillmentController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('rowLooksDelivered');

        $this->assertTrue($method->invoke($ctrl, [
            'shipment_status' => ShipmentTrackingService::STATUS_DELIVERED,
            'order_date' => Carbon::now('America/New_York')->format('Y-m-d H:i:s'),
        ]));
        $this->assertTrue($method->invoke($ctrl, [
            'shipment_status' => '',
            'status' => 'COMPLETED',
            'order_date' => Carbon::now('America/New_York')->format('Y-m-d H:i:s'),
        ]));
    }
}
