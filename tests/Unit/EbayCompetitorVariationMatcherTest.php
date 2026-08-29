<?php

namespace Tests\Unit;

use App\Support\Marketplace\EbayCompetitorVariationMatcher;
use PHPUnit\Framework\TestCase;

class EbayCompetitorVariationMatcherTest extends TestCase
{
    public function test_extracts_pack_qty_from_sku_and_dropdown_label(): void
    {
        $this->assertSame(4, EbayCompetitorVariationMatcher::extractPackQty('MS DBL 4PCS'));
        $this->assertSame(4, EbayCompetitorVariationMatcher::extractPackQty('4PCS- ($16.75/ea)'));
        $this->assertSame(10, EbayCompetitorVariationMatcher::extractPackQty('10PCS- ($13.3/ea)'));
        $this->assertSame(2, EbayCompetitorVariationMatcher::extractPackQty('Pack of 2'));
        $this->assertSame(2, EbayCompetitorVariationMatcher::extractPackQty('CS 05 2PAIR'));
        $this->assertNull(EbayCompetitorVariationMatcher::extractPackQty('MS DBL'));
    }

    public function test_picks_4pcs_buy_it_now_not_unit_or_default(): void
    {
        $variations = [
            ['id' => '2', 'label' => '2PCS- ($21/ea)', 'price' => 42.00],
            ['id' => '4', 'label' => '4PCS- ($16.75/ea)', 'price' => 63.99],
            ['id' => '8', 'label' => '8PCS- ($14.5/ea)', 'price' => 116.00],
            ['id' => '10', 'label' => '10PCS- ($13.3/ea)', 'price' => 133.00],
        ];

        $picked = EbayCompetitorVariationMatcher::pick($variations, 'MS DBL 4PCS');

        $this->assertNotNull($picked);
        $this->assertSame(63.99, $picked['price']);
        $this->assertSame('4PCS', EbayCompetitorVariationMatcher::shortLabel($picked));
    }

    public function test_does_not_match_10pcs_when_sku_is_1pcs(): void
    {
        $variations = [
            ['label' => '10PCS', 'price' => 133.00],
            ['label' => '1PCS', 'price' => 21.00],
        ];

        $picked = EbayCompetitorVariationMatcher::pick($variations, 'WIDGET 1PCS');

        $this->assertNotNull($picked);
        $this->assertSame(21.00, $picked['price']);
    }

    public function test_prefers_variation_id_from_listing_url(): void
    {
        $variations = [
            ['id' => '111', 'item_id' => 'v1|999|111', 'label' => '2PCS', 'price' => 42.00],
            ['id' => '222', 'item_id' => 'v1|999|222', 'label' => '4PCS', 'price' => 63.99],
        ];

        $picked = EbayCompetitorVariationMatcher::pick($variations, 'MS DBL 2PCS', '222');

        $this->assertNotNull($picked);
        $this->assertSame(63.99, $picked['price']);
    }

    public function test_returns_null_when_sku_has_no_pack(): void
    {
        $this->assertNull(EbayCompetitorVariationMatcher::pick([
            ['label' => '4PCS', 'price' => 63.99],
        ], 'MS DBL'));
    }
}
