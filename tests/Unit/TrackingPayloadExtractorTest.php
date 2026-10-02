<?php

namespace Tests\Unit;

use App\Services\OrderFulfillment\ChannelBatchTrackingLookup;
use App\Support\TrackingPayloadExtractor;
use PHPUnit\Framework\TestCase;

class TrackingPayloadExtractorTest extends TestCase
{
    public function test_shein_package_waybill_is_found_and_package_number_ignored(): void
    {
        $order = [
            'orderNo' => 'GSU1SJ53A0016XQ',
            'orderStatus' => 4,
            'packageWaybillList' => [[
                'packageNo' => 'GWU2610010582223873',
                'logisticsProvider' => 'GOFO',
                'expressNo' => 'GFUS01075751245442',
            ]],
        ];

        $hit = TrackingPayloadExtractor::find($order, ChannelBatchTrackingLookup::sheinNonTrackingIds($order));

        $this->assertSame('GFUS01075751245442', $hit['tracking'] ?? null);
    }

    public function test_gofo_waybill_is_found_under_any_key(): void
    {
        $hit = TrackingPayloadExtractor::find(['packages' => [['labelInfo' => ['value' => 'GFUS01075904720516']]]]);

        $this->assertSame('GFUS01075904720516', $hit['tracking'] ?? null);
        $this->assertSame('GOFO', $hit['carrier'] ?? null);
    }

    public function test_tracking_list_and_carrier_name(): void
    {
        $hit = TrackingPayloadExtractor::find([
            'line_items' => [['tracking_number_list' => ['1Z999AA10123456784'], 'shipping_provider_name' => 'UPS']],
        ]);

        $this->assertSame('1Z999AA10123456784', $hit['tracking'] ?? null);
        $this->assertSame('UPS', $hit['carrier'] ?? null);
    }

    public function test_status_words_order_ids_and_empty_payloads_are_not_tracking(): void
    {
        $this->assertNull(TrackingPayloadExtractor::find(['tracking' => 'DELIVERED', 'trackingNo' => '']));
        $this->assertNull(TrackingPayloadExtractor::find(['trackingNumber' => '577596031579558182'], ['577596031579558182']));
        $this->assertNull(TrackingPayloadExtractor::find(['orderNo' => 'GSU1SJ53A0016XQ', 'orderStatus' => 2]));
    }
}
