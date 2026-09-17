<?php

namespace Tests\Unit;

use App\Services\GofoExpressService;
use PHPUnit\Framework\TestCase;

class GofoExpressLookupTest extends TestCase
{
    public function test_order_no_candidates_strip_tiktok_prefixes(): void
    {
        $candidates = GofoExpressService::orderNoCandidates([
            '#TT-577572569413617223',
            'tiktok-577572569413617223',
        ]);

        $this->assertContains('577572569413617223', $candidates);
        $this->assertContains('#TT-577572569413617223', $candidates);
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
}
