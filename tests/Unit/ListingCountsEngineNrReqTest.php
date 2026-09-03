<?php

namespace Tests\Unit;

use App\Support\Marketplace\ListingCountsEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ListingCountsEngineNrReqTest extends TestCase
{
    #[Test]
    public function it_reads_faire_nr_as_nrl(): void
    {
        $this->assertSame('NR', ListingCountsEngine::nrReqFromDataView(['NR' => 'NR']));
        $this->assertSame('NR', ListingCountsEngine::nrReqFromDataView(['NR' => true]));
        $this->assertSame('NR', ListingCountsEngine::nrReqFromDataView(['NRL' => 'NRL']));
        $this->assertSame('NR', ListingCountsEngine::nrReqFromDataView(['nr_req' => 'NR']));
        $this->assertSame('REQ', ListingCountsEngine::nrReqFromDataView(['NR' => 'REQ']));
        $this->assertSame('REQ', ListingCountsEngine::nrReqFromDataView([]));
    }
}
