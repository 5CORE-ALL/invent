<?php

namespace Tests\Unit;

use App\Services\GofoExpressService;
use App\Services\ShipmentTrackingService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class GofoExpressLookupTest extends TestCase
{
    public function test_amazon_amz_prefixed_order_nos_are_tried_before_raw_ids(): void
    {
        $candidates = GofoExpressService::orderNoCandidates([
            '112-8790563-9423418',
            'Amz112-8790563-9423418',
        ], 4);

        $this->assertSame('Amz112-8790563-9423418', $candidates[0] ?? null);
        $this->assertContains('#Amz112-8790563-9423418', $candidates);
        $this->assertContains('112-8790563-9423418', $candidates);
    }

    public function test_order_no_candidates_strip_tiktok_prefixes(): void
    {
        $candidates = GofoExpressService::orderNoCandidates([
            '#TT-577572569413617223',
            'tiktok-577572569413617223',
        ]);

        $this->assertContains('577572569413617223', $candidates);
        $this->assertContains('#TT-577572569413617223', $candidates);
    }

    public function test_marketplace_order_ids_are_not_gofo_order_numbers(): void
    {
        $this->assertFalse(GofoExpressService::isGofoOrderNo('114-3841207-4168263'));
        $this->assertFalse(GofoExpressService::isGofoOrderNo('Amz114-3841207-4168263'));
        $this->assertFalse(GofoExpressService::isGofoOrderNo('amazon-114-3841207-4168263'));
        $this->assertFalse(GofoExpressService::isGofoOrderNo('#342032'));
        $this->assertTrue(GofoExpressService::isGofoOrderNo('GFUS01074141474180'));
        $this->assertTrue(GofoExpressService::isGofoOrderNo('S201234567890'));
    }

    public function test_extracts_gfuso_from_tracking_id_field(): void
    {
        $hit = GofoExpressService::extractTracking([
            'trackingId' => 'GFUSO0107321428770',
            'lastMileCarrier' => 'GOFO',
        ]);

        $this->assertSame('GFUSO0107321428770', $hit['tracking'] ?? null);
        $this->assertSame('GOFO', $hit['carrier'] ?? null);
    }

    public function test_extracts_server_hawb(): void
    {
        $hit = GofoExpressService::extractTracking([
            'serverHawb' => 'GFUSO0107321428770',
        ]);

        $this->assertSame('GFUSO0107321428770', $hit['tracking'] ?? null);
    }

    public function test_label_created_and_not_received_stay_awaiting_shipment(): void
    {
        $ref = new ReflectionClass(GofoExpressService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('normalizeTrackStatus');

        $this->assertSame(
            ShipmentTrackingService::STATUS_INFO_RECEIVED,
            $method->invoke($svc, '100', 'Shipping Label Created')
        );
        $this->assertSame(
            ShipmentTrackingService::STATUS_INFO_RECEIVED,
            $method->invoke($svc, '206', 'Pickup Exception, GOFO has not Received the Package from the Warehouse yet.')
        );
        $this->assertSame(
            ShipmentTrackingService::STATUS_IN_TRANSIT,
            $method->invoke($svc, '202', 'Departed facility')
        );
        $this->assertSame(
            ShipmentTrackingService::STATUS_DELIVERED,
            $method->invoke($svc, '205', 'Delivered')
        );
    }
}
