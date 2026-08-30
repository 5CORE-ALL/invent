<?php

namespace Tests\Unit;

use App\Models\EbaySkuCompetitor;
use PHPUnit\Framework\TestCase;

class EbaySkuCompetitorLmpDedupeTest extends TestCase
{
    public function test_known_shipping_beats_stale_free_sibling_copy(): void
    {
        $staleFree = (object) [
            'id' => 1241,
            'sku' => 'KS Z1 BLK HD',
            'item_id' => '336565389342',
            'price' => 49.99,
            'shipping_cost' => 0,
            'total_price' => 49.99,
            'ignored' => false,
        ];
        $landed = (object) [
            'id' => 1242,
            'sku' => 'KS Z1 BLU HD',
            'item_id' => '336565389342',
            'price' => 49.99,
            'shipping_cost' => 43.44,
            'total_price' => 93.43,
            'ignored' => false,
        ];
        $l1 = (object) [
            'id' => 1272,
            'sku' => 'KS Z1 BLU HD',
            'item_id' => '157922980088',
            'price' => 85.49,
            'shipping_cost' => 0,
            'total_price' => 85.49,
            'ignored' => false,
        ];

        $rows = EbaySkuCompetitor::dedupeByItemId([$staleFree, $landed, $l1], 'KS Z1 BLU HD');
        $lowest = $rows->first(fn ($e) => ! EbaySkuCompetitor::isIgnored($e));

        $this->assertSame(1242, $rows->first(fn ($e) => (string) $e->item_id === '336565389342')->id);
        $this->assertSame(85.49, (float) $lowest->total_price);
    }

    public function test_unique_cheapest_would_have_picked_stale_free(): void
    {
        $staleFree = (object) [
            'id' => 1,
            'sku' => 'KS Z1 BLK HD',
            'item_id' => '336565389342',
            'shipping_cost' => 0,
            'total_price' => 49.99,
            'ignored' => false,
        ];
        $landed = (object) [
            'id' => 2,
            'sku' => 'KS Z1 BLU HD',
            'item_id' => '336565389342',
            'shipping_cost' => 43.44,
            'total_price' => 93.43,
            'ignored' => false,
        ];

        $legacy = collect([$staleFree, $landed])
            ->sortBy('total_price')
            ->unique(fn ($e) => $e->item_id)
            ->first();
        $fixed = EbaySkuCompetitor::dedupeByItemId([$staleFree, $landed])->first();

        $this->assertSame(49.99, (float) $legacy->total_price);
        $this->assertSame(93.43, (float) $fixed->total_price);
    }
}
