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

    public function test_returns_null_when_sku_has_no_pack_or_color(): void
    {
        $this->assertNull(EbayCompetitorVariationMatcher::pick([
            ['label' => '4PCS', 'price' => 63.99],
        ], 'MS DBL'));
    }

    public function test_picks_white_for_ps_whls_wh_not_black(): void
    {
        $variations = [
            ['id' => 'b', 'label' => 'Black / Projector Stand', 'price' => 52.99],
            ['id' => 'w', 'label' => 'White / Projector Stand', 'price' => 54.99],
        ];

        $picked = EbayCompetitorVariationMatcher::pick($variations, 'PS WHLS WH');

        $this->assertNotNull($picked);
        $this->assertSame(54.99, $picked['price']);
        $this->assertSame('WHITE', EbayCompetitorVariationMatcher::shortLabel($picked, 'PS WHLS WH'));
    }

    public function test_picks_black_for_ps_whls_blk(): void
    {
        $variations = [
            ['id' => 'b', 'label' => 'Color:Black', 'price' => 52.99],
            ['id' => 'w', 'label' => 'Color:White', 'price' => 54.99],
        ];

        $picked = EbayCompetitorVariationMatcher::pick($variations, 'PS WHLS BLK');

        $this->assertNotNull($picked);
        $this->assertSame(52.99, $picked['price']);
    }

    public function test_wh_does_not_match_whls_in_label(): void
    {
        $variations = [
            ['label' => 'PS WHLS Tripod', 'price' => 39.00],
            ['label' => 'White', 'price' => 54.99],
        ];

        $picked = EbayCompetitorVariationMatcher::pick($variations, 'PS WHLS WH');

        $this->assertNotNull($picked);
        $this->assertSame(54.99, $picked['price']);
    }

    public function test_pack_match_still_wins_when_color_also_present(): void
    {
        $variations = [
            ['label' => '2PCS / White', 'price' => 42.00],
            ['label' => '4PCS / Black', 'price' => 63.99],
            ['label' => '4PCS / White', 'price' => 65.50],
        ];

        $picked = EbayCompetitorVariationMatcher::pick($variations, 'MS DBL WH 4PCS');

        $this->assertNotNull($picked);
        $this->assertSame(65.50, $picked['price']);
    }

    public function test_assign_to_skus_pulls_every_matching_family_variation(): void
    {
        $variations = [
            ['label' => '2PCS- ($21/ea)', 'price' => 39.98],
            ['label' => '4PCS- ($16.75/ea)', 'price' => 63.99],
            ['label' => '8PCS- ($14.5/ea)', 'price' => 113.98],
            ['label' => 'White', 'price' => 54.99],
            ['label' => 'Black', 'price' => 52.99],
        ];

        $assigned = EbayCompetitorVariationMatcher::assignToSkus($variations, [
            'MS DBL 2 PCS',
            'MS DBL 4PCS',
            'PS WHLS WH',
            'PS WHLS BLK',
        ]);

        $this->assertSame(39.98, $assigned['MS DBL 2 PCS']['price']);
        $this->assertSame(63.99, $assigned['MS DBL 4PCS']['price']);
        $this->assertSame(54.99, $assigned['PS WHLS WH']['price']);
        $this->assertSame(52.99, $assigned['PS WHLS BLK']['price']);
        $this->assertArrayNotHasKey('MS DBL 8PCS', $assigned);
    }

    public function test_short_label_keeps_product_dropdown_name(): void
    {
        $label = EbayCompetitorVariationMatcher::shortLabel([
            'label' => 'Projector Stand Floor 22.8 to 57.3 in / Uimoso / Motorized Projector Lift',
        ]);

        $this->assertSame('Projector Stand Floor 22.8 to 57.3 in', $label);
    }

    public function test_variation_item_id_uses_numeric_var_not_parent_listing(): void
    {
        $this->assertSame(
            '416989144665',
            EbayCompetitorVariationMatcher::variationItemId(
                ['id' => '416989144665', 'item_id' => 'v1|116864762870|416989144665'],
                '116864762870'
            )
        );
    }
}
