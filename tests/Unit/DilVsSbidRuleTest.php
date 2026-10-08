<?php

namespace Tests\Unit;

use App\Support\DilVsSbidRule;
use PHPUnit\Framework\TestCase;

class DilVsSbidRuleTest extends TestCase
{
    public function test_defaults_are_es_bid_then_one_sbid_slab(): void
    {
        $slabs = DilVsSbidRule::defaultSlabs();

        $this->assertCount(2, $slabs);
        $this->assertSame('es_bid', $slabs[0]['mode']);
        $this->assertEquals(0, $slabs[0]['min']);
        $this->assertEquals(0, $slabs[0]['max']);

        $this->assertSame('dynamic', $slabs[1]['mode']);
        $this->assertEquals(0.1, $slabs[1]['min']);
        $this->assertEquals(10, $slabs[1]['max']);
    }

    public function test_resolve_uses_es_bid_or_sbid_and_does_not_pause(): void
    {
        $slabs = DilVsSbidRule::defaultSlabs();

        $zero = DilVsSbidRule::resolve(0, 12.5, $slabs);
        $this->assertSame('es_bid', $zero['mode']);
        $this->assertSame(12.5, $zero['bid']);
        $this->assertFalse($zero['off']);

        $mid = DilVsSbidRule::resolve(10, 12.5, $slabs);
        $this->assertSame('dynamic', $mid['mode']);
        $this->assertSame(8.0, $mid['bid']);
        $this->assertFalse($mid['off']);

        $above = DilVsSbidRule::resolve(10.01, 12.5, $slabs);
        $this->assertSame('dynamic', $above['mode']);
        $this->assertSame(8.0, $above['bid']);
        $this->assertFalse($above['off']);
    }

    public function test_saved_auto_off_slabs_are_dropped(): void
    {
        $slabs = [
            ['min' => 0, 'max' => 0, 'mode' => 'es_bid', 'bid' => null],
            ['min' => 0.1, 'max' => 10, 'mode' => 'dynamic', 'bid' => 8],
            ['min' => 10, 'max' => 20, 'mode' => 'auto_off', 'bid' => null],
            ['min' => 100, 'max' => 9999, 'mode' => 'auto_off', 'bid' => null],
        ];

        $normalized = DilVsSbidRule::normalize($slabs);
        $this->assertCount(2, $normalized);
        $this->assertSame('es_bid', $normalized[0]['mode']);
        $this->assertSame('dynamic', $normalized[1]['mode']);
        $this->assertFalse(DilVsSbidRule::resolve(55, 0, $slabs)['off']);
    }

    public function test_second_slab_keeps_a_custom_dynamic_bid(): void
    {
        $slabs = DilVsSbidRule::defaultSlabs();
        $slabs[1]['bid'] = 6.5;

        $mid = DilVsSbidRule::resolve(4, 9, $slabs);
        $this->assertSame(6.5, $mid['bid']);
        $this->assertSame('dynamic', $mid['mode']);
    }

    public function test_switch_is_off_until_the_account_turns_it_on(): void
    {
        $this->assertFalse(DilVsSbidRule::isEnabled(null));
        $this->assertFalse(DilVsSbidRule::isEnabled(['slabs' => []]));
        $this->assertTrue(DilVsSbidRule::isEnabled(['enabled' => true]));
    }

    public function test_cvr_overlay_adjusts_like_sprc_dil(): void
    {
        $cvr = DilVsSbidRule::defaultCvr();
        $this->assertSame(7.0, $cvr['down_lt']);
        $this->assertSame(-10.0, $cvr['down_adj']);
        $this->assertSame(10.0, $cvr['up_gt']);
        $this->assertSame(10.0, $cvr['up_adj']);

        $down = DilVsSbidRule::applyCvr(8, 100, 4, 12, $cvr);
        $this->assertSame(0.0, $down['bid']);
        $this->assertSame(-10.0, $down['adj']);

        $up = DilVsSbidRule::applyCvr(8, 100, 20, 5, $cvr);
        $this->assertSame(18.0, $up['bid']);
        $this->assertSame(10.0, $up['adj']);

        $flat = DilVsSbidRule::applyCvr(8, 100, 8, 8, $cvr);
        $this->assertSame(8.0, $flat['bid']);
        $this->assertSame(0.0, $flat['adj']);

        $noViews = DilVsSbidRule::applyCvr(8, 0, 4, 12, $cvr);
        $this->assertSame(8.0, $noViews['bid']);
        $this->assertSame(0.0, $noViews['adj']);
    }

    public function test_extra_tables_start_at_zero_so_nothing_changes(): void
    {
        $tables = DilVsSbidRule::defaultTables();
        $this->assertSame(['views', 'cvr', 'sold', 'npft'], array_keys($tables));
        foreach ($tables as $slabs) {
            foreach ($slabs as $slab) {
                $this->assertSame(0.0, $slab['bid']);
            }
        }

        $plain = DilVsSbidRule::resolve(4, 9, DilVsSbidRule::defaultSlabs());
        $total = DilVsSbidRule::resolveTotal(4, 9, DilVsSbidRule::defaultSlabs(), $tables, [
            'views' => 120, 'cvr' => 3.5, 'sold' => 4, 'npft' => 12,
        ]);
        $this->assertSame($plain['bid'], $total['bid']);
        $this->assertSame($plain['mode'], $total['mode']);
    }

    public function test_sbid_is_the_sum_of_dil_views_cvr_sold_and_npft(): void
    {
        $tables = DilVsSbidRule::normalizeTables([
            'views' => [['min' => 0, 'max' => 100, 'bid' => 1], ['min' => 100, 'max' => 99999, 'bid' => 2]],
            'cvr' => [['min' => 0, 'max' => 5, 'bid' => 0.5], ['min' => 5, 'max' => 100, 'bid' => -1]],
            'sold' => [['min' => 0, 'max' => 0, 'bid' => 3], ['min' => 1, 'max' => 9999, 'bid' => 4]],
            'npft' => [['min' => -9999, 'max' => 10, 'bid' => 1.5], ['min' => 10, 'max' => 9999, 'bid' => 0.25]],
        ]);

        $total = DilVsSbidRule::resolveTotal(4, 9, DilVsSbidRule::defaultSlabs(), $tables, [
            'views' => 150, 'cvr' => 3, 'sold' => 0, 'npft' => 12,
        ]);

        $this->assertSame(8.0, $total['parts']['dil']);
        $this->assertSame(2.0, $total['parts']['views']);
        $this->assertSame(0.5, $total['parts']['cvr']);
        $this->assertSame(3.0, $total['parts']['sold']);
        $this->assertSame(0.25, $total['parts']['npft']);
        $this->assertSame(13.75, $total['bid']);
        $this->assertSame('dynamic', $total['mode']);
    }

    public function test_table_ranges_use_first_match_exclusive_shared_edge_and_open_top(): void
    {
        $slabs = DilVsSbidRule::normalizeTables([
            'views' => [
                ['min' => 0, 'max' => 0, 'bid' => 1],
                ['min' => 0, 'max' => 50, 'bid' => 2],
                ['min' => 50, 'max' => 100, 'bid' => 3],
            ],
        ])['views'];

        $this->assertSame(1.0, DilVsSbidRule::tableBid(0, $slabs));
        $this->assertSame(2.0, DilVsSbidRule::tableBid(0.1, $slabs));
        $this->assertSame(2.0, DilVsSbidRule::tableBid(50, $slabs));
        $this->assertSame(3.0, DilVsSbidRule::tableBid(50.1, $slabs));
        $this->assertSame(3.0, DilVsSbidRule::tableBid(5000, $slabs));
        $this->assertSame(0.0, DilVsSbidRule::tableBid(null, $slabs));
        $this->assertSame(0.0, DilVsSbidRule::tableBid(-1, $slabs));
    }

    public function test_extras_can_fill_the_bid_when_the_dil_slab_has_none(): void
    {
        $tables = DilVsSbidRule::defaultTables();
        $tables['views'][1]['bid'] = 5.0;

        $none = DilVsSbidRule::resolveTotal(0, 0, DilVsSbidRule::defaultSlabs(), $tables, ['views' => 10]);
        $this->assertSame(5.0, $none['bid']);

        $tables['views'][1]['bid'] = -20.0;
        $negative = DilVsSbidRule::resolveTotal(4, 9, DilVsSbidRule::defaultSlabs(), $tables, ['views' => 10]);
        $this->assertSame(0.0, $negative['bid']);
        $this->assertSame('none', $negative['mode']);
    }

    public function test_std_npft_matches_lmp_overall(): void
    {
        $this->assertNull(DilVsSbidRule::stdNpft(null, 10, 1));
        $this->assertNull(DilVsSbidRule::stdNpft(0, 10, 1));
        // ((100 × 0.70 − 5 − 40) / 100) × 100 = 25
        $this->assertSame(25.0, DilVsSbidRule::stdNpft(100, 40, 5));
        // No LP counts as 0, like /lmp-overall.
        $this->assertSame(65.0, DilVsSbidRule::stdNpft(100, null, 5));
    }

    public function test_usesnpft_only_when_that_table_adds_something(): void
    {
        $tables = DilVsSbidRule::defaultTables();
        $this->assertFalse(DilVsSbidRule::usesNpft($tables));
        $tables['npft'][2]['bid'] = 1.0;
        $this->assertTrue(DilVsSbidRule::usesNpft($tables));
    }

    public function test_missing_es_bid_does_not_invent_a_percent(): void
    {
        $zero = DilVsSbidRule::resolve(0, 0, DilVsSbidRule::defaultSlabs());
        $this->assertSame('none', $zero['mode']);
        $this->assertSame(0.0, $zero['bid']);
    }
}
