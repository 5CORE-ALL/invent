<?php

namespace Tests\Unit;

use App\Support\Marketplace\EbayListingEnded;
use PHPUnit\Framework\TestCase;

class EbayListingEndedPreferLiveTest extends TestCase
{
    public function test_prefers_live_relist_over_older_ended_item(): void
    {
        $ended = (object) [
            'id' => 8381,
            'sku' => 'CS 05 2W WoG 2PAIR',
            'item_id' => '366561206160',
            'listing_status' => 'ENDED',
        ];
        $live = (object) [
            'id' => 9212,
            'sku' => 'CS 05 2W WoG 2PAIR',
            'item_id' => '366630699427',
            'listing_status' => null,
        ];

        $picked = EbayListingEnded::preferLiveMetric([$ended, $live]);

        $this->assertSame('366630699427', $picked->item_id);
    }

    public function test_prefers_live_even_when_ended_row_has_higher_id(): void
    {
        $live = (object) [
            'id' => 10,
            'item_id' => '111',
            'listing_status' => 'ACTIVE',
        ];
        $ended = (object) [
            'id' => 99,
            'item_id' => '222',
            'listing_status' => 'ENDED',
        ];

        $picked = EbayListingEnded::preferLiveMetric([$live, $ended]);

        $this->assertSame('111', $picked->item_id);
    }
}
