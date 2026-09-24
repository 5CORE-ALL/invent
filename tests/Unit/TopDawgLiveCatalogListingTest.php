<?php

namespace Tests\Unit;

use App\Models\TopDawgProduct;
use PHPUnit\Framework\TestCase;

class TopDawgLiveCatalogListingTest extends TestCase
{
    public function test_real_tdid_counts_as_listed(): void
    {
        $row = new TopDawgProduct([
            'sku' => 'DS CH BLU',
            'tdid' => 'S003206B003496P000001V001',
            'price' => 14.69,
        ]);

        $this->assertTrue($row->countsAsLiveCatalogListing());
    }

    public function test_sku_placeholder_and_review_do_not_count_as_listed(): void
    {
        $placeholder = new TopDawgProduct([
            'sku' => 'DS CH BLU',
            'topdawg_listing_id' => 'DS CH BLU',
            'price' => 14.69,
        ]);
        $review = new TopDawgProduct([
            'sku' => 'DS CH BLU',
            'tdid' => 'S003206B003496P000001V001',
            'listing_state' => 'under review',
            'price' => 14.69,
        ]);

        $this->assertFalse($placeholder->countsAsLiveCatalogListing());
        $this->assertFalse($review->countsAsLiveCatalogListing());
    }
}
