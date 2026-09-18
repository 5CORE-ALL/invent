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

    #[Test]
    public function it_matches_topdawg_pack_and_family_aliases(): void
    {
        $products = [
            'HW 405 BLK',
            'HW 405 WH 4PCS',
            'MS 080 SKY BLU 2 PCS',
            'WF 10120 8OHM GTR',
            'SP 12120 8OHM GTR',
            'GS EL Super',
            'WM SPK COPPER+GLD',
            'KS 1X BLK+KBB 02 BR',
            'CS 04 2W 2PAIR',
        ];
        $wanted = ListingCountsEngine::wantedSkuKeySet($products, true);
        $byKey = [];
        ListingCountsEngine::putListedForSku($byKey, $wanted, 'HW 405 BLK 2PCS', 'TD-405-BLK', true);
        ListingCountsEngine::putListedForSku($byKey, $wanted, 'HW 405 WH 2PCS', 'TD-405-WH', true);
        ListingCountsEngine::putListedForSku($byKey, $wanted, 'MS 080 SKY BLU 2PC', 'TD-080', true);
        ListingCountsEngine::putListedForSku($byKey, $wanted, 'WF 10120 8OHM', 'TD-10120', true);
        ListingCountsEngine::putListedForSku($byKey, $wanted, 'SP 12120 8OHMS', 'TD-12120', true);
        ListingCountsEngine::putListedForSku($byKey, $wanted, 'GS EL', 'TD-GSEL', true);
        ListingCountsEngine::putListedForSku($byKey, $wanted, 'WM SPK COPPER', 'TD-COPPER', true);
        ListingCountsEngine::putListedForSku($byKey, $wanted, 'KS 1X BLK', 'TD-KS', true);
        ListingCountsEngine::putListedForSku($byKey, $wanted, 'CS 04 2W', 'TD-CS04', true);

        $map = ListingCountsEngine::listedMapForProductSkus($products, $byKey, true);

        $this->assertSame('TD-405-BLK', ListingCountsEngine::listingIdFromMap($map, 'HW 405 BLK'));
        $this->assertSame('TD-405-WH', ListingCountsEngine::listingIdFromMap($map, 'HW 405 WH 4PCS'));
        $this->assertSame('TD-080', ListingCountsEngine::listingIdFromMap($map, 'MS 080 SKY BLU 2 PCS'));
        $this->assertSame('TD-10120', ListingCountsEngine::listingIdFromMap($map, 'WF 10120 8OHM GTR'));
        $this->assertSame('TD-12120', ListingCountsEngine::listingIdFromMap($map, 'SP 12120 8OHM GTR'));
        $this->assertSame('TD-GSEL', ListingCountsEngine::listingIdFromMap($map, 'GS EL Super'));
        $this->assertSame('TD-COPPER', ListingCountsEngine::listingIdFromMap($map, 'WM SPK COPPER+GLD'));
        $this->assertSame('TD-KS', ListingCountsEngine::listingIdFromMap($map, 'KS 1X BLK+KBB 02 BR'));
        $this->assertSame('TD-CS04', ListingCountsEngine::listingIdFromMap($map, 'CS 04 2W 2PAIR'));
        $this->assertSame('', ListingCountsEngine::listingIdFromMap($map, 'HW 405 RED'));
    }
}
