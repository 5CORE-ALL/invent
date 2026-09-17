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

    public function test_shopify_lookup_keys_include_tiktok_prefixes(): void
    {
        $ref = new ReflectionClass(SalesOrderFulfillmentController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $keys = $ref->getMethod('shopifyTrackingLookupKeys');

        $fromPlain = $keys->invoke($ctrl, '577572569413617223');
        $this->assertContains('TT-577572569413617223', $fromPlain);
        $this->assertContains('#TT-577572569413617223', $fromPlain);

        $fromPrefixed = $keys->invoke($ctrl, '#TT-577572569413617223');
        $this->assertContains('577572569413617223', $fromPrefixed);
    }

    public function test_pending_badge_counts_unique_orders_not_sku_lines(): void
    {
        $ref = new ReflectionClass(SalesOrderFulfillmentController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $match = $ref->getMethod('uniqueMarketplaceOrdersMatching');

        $lines = [];
        for ($i = 1; $i <= 8; $i++) {
            $lines[] = [
                'id' => 'faire-'.$i,
                'mm_slug' => 'faire',
                'order_id' => 'bo_n8pa3fg3f8',
                'order_id_api' => 'bo_n8pa3fg3f8',
                'sku' => 'SKU-'.$i,
                'quantity' => 1,
                'status' => 'PROCESSING',
                'tracking_number' => '',
            ];
        }

        $pending = $match->invoke($ctrl, $lines, false);
        $this->assertCount(1, $pending);
        $this->assertSame(8, $pending[0]['quantity']);
        $this->assertStringContainsString('+7', (string) $pending[0]['sku']);

        $lines[0]['tracking_number'] = '9400111899351234567890';
        $pendingAfterLabel = $match->invoke($ctrl, $lines, false);
        $labeled = $match->invoke($ctrl, $lines, true);
        $this->assertCount(0, $pendingAfterLabel);
        $this->assertCount(1, $labeled);
    }
}
