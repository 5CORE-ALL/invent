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

    #[Test]
    public function it_matches_listed_skus_when_spaces_and_hyphens_differ(): void
    {
        $wanted = ListingCountsEngine::wantedSkuKeySet(['LS 100-6 RED']);
        $byKey = [];
        ListingCountsEngine::putListedForSku($byKey, $wanted, 'LS100-6RED', 'TD-YES');
        $map = ListingCountsEngine::listedMapForProductSkus(['LS 100-6 RED'], $byKey);

        $this->assertSame('TD-YES', ListingCountsEngine::listingIdFromMap($map, 'LS 100-6 RED'));
        $this->assertSame('TD-YES', ListingCountsEngine::listingIdFromMap($map, 'LS100 6 RED'));
        $this->assertSame('TD-YES', ListingCountsEngine::listingIdFromMap($map, 'ls100-6red'));
    }

    #[Test]
    public function uploaded_and_unable_to_list_are_not_live(): void
    {
        $this->assertTrue(ListingCountsEngine::isPendingOrReviewListingState('Uploaded'));
        $this->assertTrue(ListingCountsEngine::isPendingOrReviewListingState('Unable to List'));
        $this->assertTrue(ListingCountsEngine::isPendingOrReviewListingState('pending'));
        $this->assertFalse(ListingCountsEngine::isPendingOrReviewListingState('Yes'));
        $this->assertFalse(ListingCountsEngine::isPendingOrReviewListingState('active'));
        $this->assertFalse(ListingCountsEngine::isPendingOrReviewListingState('listed'));
    }
}
