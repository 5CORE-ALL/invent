<?php

namespace Tests\Unit;

use App\Support\AmazonDilGroiRule;
use PHPUnit\Framework\TestCase;

class AmazonDilGroiRuleTest extends TestCase
{
    public function test_defaults_are_five_slabs_from_0_1_to_25(): void
    {
        $rules = AmazonDilGroiRule::defaults();
        $this->assertCount(5, $rules);
        $this->assertSame('0.1-5', $rules[0]['key']);
        $this->assertSame('0.1–5%', $rules[0]['label']);
        $this->assertSame(0.1, $rules[0]['min']);
        $this->assertSame(5.0, $rules[0]['max']);
        $this->assertSame(50.0, $rules[0]['groi']);
        $this->assertSame('20-25', $rules[4]['key']);
        $this->assertSame('20–25%', $rules[4]['label']);
        $this->assertSame(70.0, $rules[4]['groi']);
    }

    public function test_slab_key_boundaries(): void
    {
        $this->assertNull(AmazonDilGroiRule::slabKey(0));
        $this->assertNull(AmazonDilGroiRule::slabKey(0.09));
        $this->assertSame('0.1-5', AmazonDilGroiRule::slabKey(0.1));
        $this->assertSame('0.1-5', AmazonDilGroiRule::slabKey(4.99));
        $this->assertSame('5-10', AmazonDilGroiRule::slabKey(5));
        $this->assertSame('10-15', AmazonDilGroiRule::slabKey(10));
        $this->assertSame('15-20', AmazonDilGroiRule::slabKey(15));
        $this->assertSame('20-25', AmazonDilGroiRule::slabKey(20));
        $this->assertSame('20-25', AmazonDilGroiRule::slabKey(25));
        $this->assertNull(AmazonDilGroiRule::slabKey(25.01));
        $this->assertNull(AmazonDilGroiRule::slabKey(40));
    }

    public function test_groi_for_dil_uses_matching_slab(): void
    {
        $rules = AmazonDilGroiRule::defaults();
        $this->assertSame(50.0, AmazonDilGroiRule::groiForDil(2.5, $rules));
        $this->assertSame(55.0, AmazonDilGroiRule::groiForDil(7, $rules));
        $this->assertNull(AmazonDilGroiRule::groiForDil(0, $rules));
        $this->assertNull(AmazonDilGroiRule::groiForDil(30, $rules));
    }

    public function test_match_or_nearest_clamps_below_and_above_slabs(): void
    {
        $rules = AmazonDilGroiRule::defaults();
        $this->assertSame('0.1-5', AmazonDilGroiRule::matchOrNearest(0, $rules)['key']);
        $this->assertSame(50.0, AmazonDilGroiRule::matchOrNearest(0, $rules)['groi']);
        $this->assertSame('20-25', AmazonDilGroiRule::matchOrNearest(40, $rules)['key']);
        $this->assertSame(70.0, AmazonDilGroiRule::matchOrNearest(40, $rules)['groi']);
        $this->assertSame('5-10', AmazonDilGroiRule::matchOrNearest(7, $rules)['key']);
    }

    public function test_normalize_list_keeps_custom_slab_count(): void
    {
        $rules = AmazonDilGroiRule::normalizeList([
            ['min' => 0.1, 'max' => 10, 'groi' => 80],
            ['key' => '10-25', 'groi' => 40],
        ]);
        $this->assertCount(2, $rules);
        $this->assertSame('0.1-10', $rules[0]['key']);
        $this->assertSame(80.0, $rules[0]['groi']);
        $this->assertSame('10-25', $rules[1]['key']);
        $this->assertSame(40.0, AmazonDilGroiRule::groiForDil(12, $rules));
        $this->assertSame(40.0, AmazonDilGroiRule::groiForDil(25, $rules));
        $this->assertNull(AmazonDilGroiRule::groiForDil(26, $rules));
    }

    public function test_min_target_is_lowest_groi_in_table(): void
    {
        $rules = AmazonDilGroiRule::normalizeList([
            ['min' => 0.1, 'max' => 5, 'groi' => 50],
            ['min' => 5, 'max' => 10, 'groi' => 55],
            ['min' => 50, 'max' => 60, 'groi' => 100],
        ]);
        $this->assertSame(50.0, AmazonDilGroiRule::minTarget($rules));
        $this->assertNull(AmazonDilGroiRule::minTarget([]));
    }

    public function test_suggested_price_matches_amazon_groi_formula(): void
    {
        $lp = 40.0;
        $ship = 8.0;
        $groi = 70.0;
        $expected = round(($lp * (1 + $groi / 100) + $ship) / 0.80, 2);
        $this->assertSame($expected, AmazonDilGroiRule::suggestedPrice($lp, $ship, $groi));
        $this->assertNull(AmazonDilGroiRule::suggestedPrice(0, $ship, $groi));
    }

    public function test_zero_to_zero_slab_is_valid_and_matches_dil_zero(): void
    {
        $rule = AmazonDilGroiRule::normalize(['min' => 0, 'max' => 0, 'nroi' => 30]);
        $this->assertNotNull($rule);
        $this->assertSame('0-0', $rule['key']);
        $this->assertSame('0–0%', $rule['label']);
        $this->assertSame(0.0, $rule['min']);
        $this->assertSame(0.0, $rule['max']);
        $this->assertSame(30.0, $rule['groi']);

        $rules = AmazonDilGroiRule::normalizeList([
            ['min' => 0, 'max' => 5, 'groi' => 35],
            ['min' => 0, 'max' => 0, 'groi' => 30],
        ]);
        $this->assertCount(2, $rules);
        $this->assertSame('0-0', $rules[0]['key']);
        $this->assertSame('0-5', $rules[1]['key']);
        $this->assertSame(30.0, AmazonDilGroiRule::groiForDil(0, $rules));
        $this->assertSame(35.0, AmazonDilGroiRule::groiForDil(0.1, $rules));
        $this->assertSame(35.0, AmazonDilGroiRule::groiForDil(4.9, $rules));
        $this->assertNull(AmazonDilGroiRule::normalize(['min' => 5, 'max' => 4, 'groi' => 40]));
    }

    public function test_ensure_zero_to_zero_prepends_when_missing(): void
    {
        $ensured = AmazonDilGroiRule::ensureZeroToZero([
            ['min' => 0, 'max' => 5, 'groi' => 35],
            ['min' => 5, 'max' => 10, 'groi' => 40],
        ]);
        $this->assertSame('0-0', $ensured[0]['key']);
        $this->assertSame(35.0, $ensured[0]['groi']);
        $this->assertCount(3, $ensured);

        $already = AmazonDilGroiRule::ensureZeroToZero($ensured);
        $this->assertCount(3, $already);
        $this->assertSame('0-0', $already[0]['key']);

        $amazon = AmazonDilGroiRule::amazonDefaults();
        $this->assertSame('0-0', $amazon[0]['key']);
        $this->assertSame(50.0, $amazon[0]['groi']);
        $this->assertSame('0.1-5', $amazon[1]['key']);
        $this->assertCount(6, $amazon);
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('ebay1'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('ebay2'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('ebay3'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('aliexpress'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('faire'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('tiktok'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('tiktok2'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('mercari_wship'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('mercari_woship'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('pls'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('shein'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('bestbuy'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('newegg'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('reverb'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('wayfair'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('depop'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('macys'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('macy'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('shopify_b2c'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('shopify_b2b'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('purchasing_power'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('topdawg'));
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('ebay1')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('ebay3')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('aliexpress')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('faire')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('tiktok')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('mercari_wship')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('mercari_woship')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('pls')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('shein')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('bestbuy')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('newegg')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('reverb')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('wayfair')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('depop')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('macys')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('macy')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('shopify_b2c')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('shopify_b2b')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('purchasing_power')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('topdawg')[0]['key']);
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('temu'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('newtemuone'));
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('newtemutwo'));
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('temu')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('newtemuone')[0]['key']);
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('newtemutwo')[0]['key']);
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('temu3'));
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('temu3')[0]['key']);
        $this->assertTrue(AmazonDilGroiRule::usesZeroToZero('doba'));
        $this->assertSame('0-0', AmazonDilGroiRule::defaultsForChannel('doba')[0]['key']);
        $this->assertFalse(AmazonDilGroiRule::usesZeroToZero('doba_withoutship'));
        $this->assertFalse(AmazonDilGroiRule::usesZeroToZero('temu2'));
        $this->assertSame('0.1-5', AmazonDilGroiRule::defaultsForChannel('temu2')[0]['key']);
    }

    public function test_normalize_accepts_nroi_alias(): void
    {
        $rule = AmazonDilGroiRule::normalize(['min' => 0.1, 'max' => 5, 'nroi' => 45]);
        $this->assertNotNull($rule);
        $this->assertSame(45.0, $rule['groi']);
        $this->assertSame(45.0, $rule['nroi']);
    }

    public function test_suggested_price_from_nroi_uses_ads_and_inverts_snroi(): void
    {
        $lp = 40.0;
        $ship = 8.0;
        $nroi = 50.0;
        $ads = 10.0;
        $price = AmazonDilGroiRule::suggestedPriceFromNroi($lp, $ship, $nroi, $ads);
        $expected = round(($lp * (1 + $nroi / 100) + $ship) / (0.80 - 0.10), 2);
        $this->assertSame($expected, $price);
        $this->assertEqualsWithDelta(
            $nroi,
            AmazonDilGroiRule::snroiAtPrice($price, $lp, $ship, $ads),
            0.05
        );
        $this->assertNull(AmazonDilGroiRule::suggestedPriceFromNroi($lp, $ship, $nroi, 80));
    }

    public function test_cvr_trend_matches_tabulator_l30_vs_l45(): void
    {
        $this->assertSame('down', AmazonDilGroiRule::cvrTrend(0, 8));
        $this->assertSame('down', AmazonDilGroiRule::cvrTrend(5, 6));
        $this->assertSame('up', AmazonDilGroiRule::cvrTrend(12, 9));
        $this->assertSame('flat', AmazonDilGroiRule::cvrTrend(8, 8));
        $this->assertSame('flat', AmazonDilGroiRule::cvrTrend(8.05, 8));
    }

    public function test_adjust_groi_for_cvr_down_below_7_and_up_above_10(): void
    {
        $this->assertSame(40.0, AmazonDilGroiRule::adjustGroiForCvr(50, 6.9, 'down'));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 7, 'down'));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 5, 'flat'));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 12, 'down'));
        $this->assertSame(60.0, AmazonDilGroiRule::adjustGroiForCvr(50, 10.1, 'up'));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 10, 'up'));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 6, 'up'));
        $this->assertSame(0.0, AmazonDilGroiRule::adjustGroiForCvr(5, 1, 'down'));
    }

    public function test_adjust_groi_uses_saved_cvr_overlay_thresholds(): void
    {
        $cfg = ['down_lt' => 5, 'down_adj' => -8, 'up_gt' => 12, 'up_adj' => 6];
        $this->assertSame(42.0, AmazonDilGroiRule::adjustGroiForCvr(50, 4.9, 'down', $cfg));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 5, 'down', $cfg));
        $this->assertSame(56.0, AmazonDilGroiRule::adjustGroiForCvr(50, 12.1, 'up', $cfg));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 12, 'up', $cfg));
    }

    public function test_cvr_overlay_without_slabs_gets_down_7_to_10_up_slabs_and_prices_as_before(): void
    {
        $cfg = AmazonDilGroiRule::normalizeCvrAdj(['down_lt' => 7, 'down_adj' => -12, 'up_gt' => 10, 'up_adj' => 11]);
        $this->assertSame([
            ['arrow' => 'down', 'from' => null, 'to' => 7.0, 'adj' => -12.0],
            ['arrow' => 'any', 'from' => 7.0, 'to' => 10.0, 'adj' => 0.0],
            ['arrow' => 'up', 'from' => 10.0, 'to' => null, 'adj' => 11.0],
        ], $cfg['slabs']);
        $this->assertSame(38.0, AmazonDilGroiRule::adjustGroiForCvr(50, 6.9, 'down', $cfg));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 7, 'down', $cfg));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 10, 'up', $cfg));
        $this->assertSame(61.0, AmazonDilGroiRule::adjustGroiForCvr(50, 10.1, 'up', $cfg));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 5, 'up', $cfg));
    }

    public function test_cvr_overlay_dynamic_slabs_first_match_wins_and_any_ignores_arrow(): void
    {
        $cfg = AmazonDilGroiRule::normalizeCvrAdj(['slabs' => [
            ['arrow' => 'down', 'to' => 7, 'adj' => -12],
            ['arrow' => 'any', 'from' => 7, 'to' => 10, 'adj' => 3],
            ['arrow' => 'up', 'from' => 10, 'adj' => 11],
            ['arrow' => 'any', 'from' => 15, 'to' => 20, 'adj' => 99],
            ['arrow' => 'up', 'from' => 9, 'to' => 3, 'adj' => 5], // From above To: dropped
        ]]);
        $this->assertCount(4, $cfg['slabs']);
        // Legacy keys follow the first Down / Up slab.
        $this->assertSame(7.0, $cfg['down_lt']);
        $this->assertSame(-12.0, $cfg['down_adj']);
        $this->assertSame(10.0, $cfg['up_gt']);
        $this->assertSame(11.0, $cfg['up_adj']);

        $this->assertSame(3.0, AmazonDilGroiRule::cvrAdjPoints(8.5, 'flat', $cfg));
        $this->assertSame(3.0, AmazonDilGroiRule::cvrAdjPoints(7, 'down', $cfg));
        $this->assertSame(3.0, AmazonDilGroiRule::cvrAdjPoints(10, 'up', $cfg));
        $this->assertSame(-12.0, AmazonDilGroiRule::cvrAdjPoints(6.9, 'down', $cfg));
        $this->assertSame(0.0, AmazonDilGroiRule::cvrAdjPoints(6.9, 'up', $cfg));
        // Up slab is listed before the 15–20 Any slab, but a flat arrow skips it and falls to Any.
        $this->assertSame(11.0, AmazonDilGroiRule::cvrAdjPoints(16, 'up', $cfg));
        $this->assertSame(99.0, AmazonDilGroiRule::cvrAdjPoints(16, 'flat', $cfg));
        // Survives another normalize (what the page re-sends).
        $this->assertSame($cfg, AmazonDilGroiRule::normalizeCvrAdj($cfg));
    }

    public function test_cvr_overlay_saved_empty_slab_list_turns_the_overlay_off(): void
    {
        $cfg = AmazonDilGroiRule::normalizeCvrAdj(['slabs' => []]);
        $this->assertSame([], $cfg['slabs']);
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvr(50, 2, 'down', $cfg));
        $this->assertSame(0.0, AmazonDilGroiRule::cvrAdjPointsLevel(2, $cfg));
    }

    public function test_unpack_stored_keeps_legacy_slab_list_and_cvr_adj_wrapper(): void
    {
        $legacy = AmazonDilGroiRule::unpackStored([
            ['min' => 0.1, 'max' => 5, 'groi' => 50],
        ]);
        $this->assertCount(1, $legacy['rules']);
        $this->assertSame(7.0, $legacy['cvr_adj']['down_lt']);
        $this->assertSame(-10.0, $legacy['cvr_adj']['down_adj']);

        $wrapped = AmazonDilGroiRule::unpackStored([
            'rules' => [['min' => 0.1, 'max' => 5, 'groi' => 50]],
            'cvr_adj' => ['down_lt' => 6, 'down_adj' => -12, 'up_gt' => 11, 'up_adj' => 9],
        ]);
        $this->assertSame(50.0, $wrapped['rules'][0]['groi']);
        $this->assertSame(6.0, $wrapped['cvr_adj']['down_lt']);
        $this->assertSame(-12.0, $wrapped['cvr_adj']['down_adj']);
        $this->assertSame(11.0, $wrapped['cvr_adj']['up_gt']);
        $this->assertSame(9.0, $wrapped['cvr_adj']['up_adj']);
    }

    public function test_lmp_cap_matches_amazon_three_cases(): void
    {
        // Dil $18.81 < LMP $24.95 → keep Dil (never raise to LMP).
        $this->assertEqualsWithDelta(
            18.81,
            AmazonDilGroiRule::capSpriceToLmp(18.81, 24.95, 7.0, 1.748, 0.80),
            0.001
        );

        // Dil $50 > LMP $30 and SGROI at $30 = 140% ≥ 20 → cap to LMP.
        $this->assertEqualsWithDelta(
            30.0,
            AmazonDilGroiRule::capSpriceToLmp(50, 30, 10, 0, 0.80),
            0.001
        );

        // Dil $50 > LMP $12 and SGROI at $12 = −4% < 20 → keep Dil.
        $this->assertEqualsWithDelta(
            50.0,
            AmazonDilGroiRule::capSpriceToLmp(50, 12, 10, 0, 0.80),
            0.001
        );
    }

    public function test_missing_lmp_caps_at_std_price(): void
    {
        // No LMP and Dil $50 > Std $32 → Std is the maximum.
        $this->assertEqualsWithDelta(
            32.0,
            AmazonDilGroiRule::capSpriceToLmp(50, 0, 10, 0, 0.80, 32),
            0.001
        );

        // No LMP and Dil $18 is already under Std $32 → keep Dil.
        $this->assertEqualsWithDelta(
            18.0,
            AmazonDilGroiRule::capSpriceToLmp(18, 0, 10, 0, 0.80, 32),
            0.001
        );

        // No LMP and blank Std → keep Dil.
        $this->assertEqualsWithDelta(
            50.0,
            AmazonDilGroiRule::capSpriceToLmp(50, 0, 10, 0, 0.80, 0),
            0.001
        );

        // LMP is present and SGROI at LMP is under 20, but Dil is above Std → Std cap.
        $this->assertEqualsWithDelta(
            20.0,
            AmazonDilGroiRule::capSpriceToLmp(50, 12, 10, 0, 0.80, 20),
            0.001
        );
    }

    public function test_lmp_above_std_caps_every_price_at_std(): void
    {
        // LMP $40 > Std $32. Dil $50 would become LMP, then Std is still the maximum.
        $this->assertEqualsWithDelta(
            32.0,
            AmazonDilGroiRule::capSpriceToLmp(50, 40, 10, 0, 0.80, 32),
            0.001
        );

        // Price between Std and LMP is pulled down to Std.
        $this->assertEqualsWithDelta(
            20.0,
            AmazonDilGroiRule::capSpriceToLmp(25, 40, 10, 0, 0.80, 20),
            0.001
        );

        // Price already under Std stays there.
        $this->assertEqualsWithDelta(
            15.0,
            AmazonDilGroiRule::capSpriceToLmp(15, 40, 10, 0, 0.80, 20),
            0.001
        );

        // LMP below Std still uses the LMP cap.
        $this->assertEqualsWithDelta(
            30.0,
            AmazonDilGroiRule::capSpriceToLmp(50, 30, 10, 0, 0.80, 40),
            0.001
        );
    }

    public function test_adjust_groi_level_only_ignores_trend(): void
    {
        $this->assertSame(40.0, AmazonDilGroiRule::adjustGroiForCvrLevel(50, 6.9));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvrLevel(50, 7));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvrLevel(50, 8.5));
        $this->assertSame(50.0, AmazonDilGroiRule::adjustGroiForCvrLevel(50, 10));
        $this->assertSame(60.0, AmazonDilGroiRule::adjustGroiForCvrLevel(50, 10.1));
        $this->assertSame(0.0, AmazonDilGroiRule::adjustGroiForCvrLevel(5, 1));
    }

    public function test_diff_detects_add_remove_and_value_change(): void
    {
        $old = AmazonDilGroiRule::normalizeList([
            ['min' => 0, 'max' => 0, 'groi' => 40],
            ['min' => 0, 'max' => 5, 'groi' => 40],
            ['min' => 5, 'max' => 10, 'groi' => 45],
        ]);
        $new = AmazonDilGroiRule::normalizeList([
            ['min' => 0, 'max' => 0, 'groi' => 40],
            ['min' => 0, 'max' => 5, 'groi' => 42],
            ['min' => 10, 'max' => 15, 'groi' => 50],
        ]);
        $diff = AmazonDilGroiRule::diff($old, $new);
        $this->assertCount(1, $diff['added']);
        $this->assertSame('10-15', $diff['added'][0]['key']);
        $this->assertSame(['5-10'], $diff['removed']);
        $this->assertSame(['0-5' => 42.0], $diff['changed']);
        $this->assertFalse(AmazonDilGroiRule::isEmptyDiff($diff));
        $this->assertTrue(AmazonDilGroiRule::isEmptyDiff(AmazonDilGroiRule::diff($old, $old)));
    }

    public function test_apply_patch_updates_value_only_where_slab_exists(): void
    {
        $amazon = AmazonDilGroiRule::defaults();
        $ebay = AmazonDilGroiRule::normalizeList([
            ['min' => 0, 'max' => 0, 'groi' => 40],
            ['min' => 0, 'max' => 5, 'groi' => 40],
            ['min' => 5, 'max' => 10, 'groi' => 45],
            ['min' => 25, 'max' => 30, 'groi' => 65],
        ]);
        $diff = [
            'added' => [AmazonDilGroiRule::make(30, 35, 70)],
            'removed' => ['25-30'],
            'changed' => ['5-10' => 50.0],
        ];

        $patchedAmazon = AmazonDilGroiRule::applyPatch($amazon, $diff);
        $this->assertSame(50.0, AmazonDilGroiRule::rulesByKey($patchedAmazon)['5-10']['groi']);
        $this->assertArrayHasKey('30-35', AmazonDilGroiRule::rulesByKey($patchedAmazon));
        $this->assertArrayNotHasKey('25-30', AmazonDilGroiRule::rulesByKey($patchedAmazon));
        $this->assertArrayNotHasKey('0-5', AmazonDilGroiRule::rulesByKey($patchedAmazon));

        $patchedEbay = AmazonDilGroiRule::applyPatch($ebay, $diff);
        $this->assertSame(50.0, AmazonDilGroiRule::rulesByKey($patchedEbay)['5-10']['groi']);
        $this->assertSame(40.0, AmazonDilGroiRule::rulesByKey($patchedEbay)['0-5']['groi']);
        $this->assertArrayNotHasKey('25-30', AmazonDilGroiRule::rulesByKey($patchedEbay));
        $this->assertArrayHasKey('30-35', AmazonDilGroiRule::rulesByKey($patchedEbay));
    }

    public function test_union_by_key_uses_majority_target(): void
    {
        $a = AmazonDilGroiRule::normalizeList([
            ['min' => 5, 'max' => 10, 'groi' => 45],
            ['min' => 25, 'max' => 30, 'groi' => 65],
        ]);
        $b = AmazonDilGroiRule::normalizeList([
            ['min' => 5, 'max' => 10, 'groi' => 50],
        ]);
        $c = AmazonDilGroiRule::normalizeList([
            ['min' => 5, 'max' => 10, 'groi' => 45],
        ]);
        $union = AmazonDilGroiRule::unionByKey([$a, $b, $c]);
        $byKey = AmazonDilGroiRule::rulesByKey($union);
        $this->assertSame(45.0, $byKey['5-10']['groi']);
        $this->assertArrayHasKey('25-30', $byKey);
        $this->assertFalse(AmazonDilGroiRule::sameRules($a, $b));
        $this->assertTrue(AmazonDilGroiRule::sameRules($a, $a));
    }
    public function test_lmp_reduce_defaults_ten_percent_under_dil_25_else_five(): void
    {
        $rules = AmazonDilGroiRule::normalizeLmpReduceRules(null);

        $this->assertSame(10.0, AmazonDilGroiRule::lmpReducePct(0, $rules));
        $this->assertSame(10.0, AmazonDilGroiRule::lmpReducePct(24.99, $rules));
        $this->assertSame(5.0, AmazonDilGroiRule::lmpReducePct(25, $rules));
        $this->assertSame(5.0, AmazonDilGroiRule::lmpReducePct(300, $rules));
    }

    public function test_lmp_reduce_lowers_the_lmp_cap_by_the_dil_slab_percent(): void
    {
        // Dil $50 > LMP $30, SGROI at LMP ≥ 20 → S PRC = LMP, then LMP less 10% / 5%.
        $this->assertEqualsWithDelta(27.0, AmazonDilGroiRule::capSpriceToLmp(50, 30, 10, 0, 0.80, 0, 10.0), 0.001);
        $this->assertEqualsWithDelta(28.5, AmazonDilGroiRule::capSpriceToLmp(50, 30, 10, 0, 0.80, 0, 5.0), 0.001);
        $this->assertEqualsWithDelta(30.0, AmazonDilGroiRule::capSpriceToLmp(50, 30, 10, 0, 0.80, 0, 0.0), 0.001);

        // Under LMP nothing changes. SGROI at LMP under 20 keeps Dil.
        $this->assertEqualsWithDelta(18.81, AmazonDilGroiRule::capSpriceToLmp(18.81, 24.95, 7.0, 1.748, 0.80, 0, 10.0), 0.001);
        $this->assertEqualsWithDelta(50.0, AmazonDilGroiRule::capSpriceToLmp(50, 12, 10, 0, 0.80, 0, 10.0), 0.001);
    }

    public function test_amazon_sprc_dil_lmp_rule_has_no_snroi_condition(): void
    {
        // LMP 12, LP 10, take-home 0.80: SNROI at LMP is −4% (< 20). The default cap keeps the high price.
        $this->assertEqualsWithDelta(50.0, AmazonDilGroiRule::capSpriceToLmp(50, 12, 10, 0, 0.80, 0, 2.0), 0.001);
        // Sprc Dil: calculated S PRC ≥ LMP → LMP − x%, whatever the SNROI at LMP is.
        $this->assertEqualsWithDelta(11.76, AmazonDilGroiRule::capSpriceToLmp(50, 12, 10, 0, 0.80, 0, 2.0, false), 0.001);
        // Exactly at LMP counts as "at or above".
        $this->assertEqualsWithDelta(11.76, AmazonDilGroiRule::capSpriceToLmp(12, 12, 10, 0, 0.80, 0, 2.0, false), 0.001);
        // Below LMP: nothing changes.
        $this->assertEqualsWithDelta(11.0, AmazonDilGroiRule::capSpriceToLmp(11, 12, 10, 0, 0.80, 0, 2.0, false), 0.001);
        // No LMP: nothing changes.
        $this->assertEqualsWithDelta(50.0, AmazonDilGroiRule::capSpriceToLmp(50, 0, 10, 0, 0.80, 0, 2.0, false), 0.001);
    }

    public function test_lmp_reduce_blank_slab_pct_and_empty_list_turn_it_off(): void
    {
        $rules = AmazonDilGroiRule::normalizeLmpReduceRules([['min' => 0, 'max' => 25, 'pct' => '']]);
        $this->assertNull($rules[0]['pct']);
        $this->assertSame(0.0, AmazonDilGroiRule::lmpReducePct(10, $rules));
        $this->assertSame([], AmazonDilGroiRule::normalizeLmpReduceRules([]));
        $this->assertSame(0.0, AmazonDilGroiRule::lmpReducePct(10, []));
    }
    public function test_ageing_rule_adds_the_age_band_points_to_the_target_nroi(): void
    {
        $rules = AmazonDilGroiRule::normalizeAgeAdj([
            ['min' => 0, 'max' => 90, 'points' => 0],
            ['min' => 90, 'max' => 180, 'points' => -5],
            ['min' => 180, 'max' => 9999, 'points' => -15],
        ]);

        $this->assertSame(0.0, AmazonDilGroiRule::ageAdjPoints(30, $rules));
        // min ≤ age < max: 90 is in the second band, 180 in the third.
        $this->assertSame(-5.0, AmazonDilGroiRule::ageAdjPoints(90, $rules));
        $this->assertSame(-5.0, AmazonDilGroiRule::ageAdjPoints(179, $rules));
        $this->assertSame(-15.0, AmazonDilGroiRule::ageAdjPoints(180, $rules));
        // The last band also includes its To value; past it, no band.
        $this->assertSame(-15.0, AmazonDilGroiRule::ageAdjPoints(9999, $rules));
        $this->assertSame(0.0, AmazonDilGroiRule::ageAdjPoints(10000, $rules));
        // Unknown age adds nothing.
        $this->assertSame(0.0, AmazonDilGroiRule::ageAdjPoints(null, $rules));
        $this->assertSame(-1, AmazonDilGroiRule::ageAdjSlab(null, $rules));

        $this->assertEqualsWithDelta(45.0, AmazonDilGroiRule::adjustGroiForAge(50, 100, $rules), 0.001);
        $this->assertEqualsWithDelta(50.0, AmazonDilGroiRule::adjustGroiForAge(50, 10, $rules), 0.001);
        // Floored at 0 like the CVR overlay.
        $this->assertSame(0.0, AmazonDilGroiRule::adjustGroiForAge(10, 400, $rules));
        // Positive points raise the target.
        $up = AmazonDilGroiRule::normalizeAgeAdj([['min' => 0, 'max' => 9999, 'points' => 7.5]]);
        $this->assertEqualsWithDelta(57.5, AmazonDilGroiRule::adjustGroiForAge(50, 20, $up), 0.001);
    }

    public function test_ageing_rule_defaults_change_nothing_and_an_empty_list_is_off(): void
    {
        $defaults = AmazonDilGroiRule::normalizeAgeAdj(null);
        $this->assertSame(AmazonDilGroiRule::AGE_ADJ_DEFAULTS, $defaults);
        foreach ([0, 45, 90, 200, 365, 5000] as $age) {
            $this->assertSame(0.0, AmazonDilGroiRule::ageAdjPoints($age, $defaults), 'age '.$age);
        }
        $this->assertSame([], AmazonDilGroiRule::normalizeAgeAdj([]));
        $this->assertSame(0.0, AmazonDilGroiRule::ageAdjPoints(100, []));
        // A swapped From / To is put right; a row without a number is dropped.
        $rules = AmazonDilGroiRule::normalizeAgeAdj([['min' => 180, 'max' => 90, 'points' => 3], ['min' => '', 'max' => 10, 'points' => 1]]);
        $this->assertSame([['min' => 90.0, 'max' => 180.0, 'points' => 3.0]], $rules);
    }

    public function test_ageing_rule_is_saved_with_the_sprc_dil_rules(): void
    {
        // Saved without it (older rows) → defaults. Saved empty list → stays off.
        $this->assertSame(AmazonDilGroiRule::AGE_ADJ_DEFAULTS, AmazonDilGroiRule::unpackStored(['rules' => []])['age_adj']);
        $this->assertSame([], AmazonDilGroiRule::unpackStored(['rules' => [], 'age_adj' => []])['age_adj']);
        $saved = AmazonDilGroiRule::unpackStored(['rules' => [], 'age_adj' => [['min' => 0, 'max' => 100, 'points' => -4]]]);
        $this->assertSame([['min' => 0.0, 'max' => 100.0, 'points' => -4.0]], $saved['age_adj']);
    }

    public function test_rule_on_switches_default_to_on_and_only_an_explicit_off_turns_a_rule_off(): void
    {
        $allOn = ['dil' => true, 'rev' => true, 'cvr' => true, 'age' => true, 'diff' => true, 'lmp' => true, 'clearance' => true];
        // Rows saved before the switches existed, or with garbage, stay fully ON.
        $this->assertSame($allOn, AmazonDilGroiRule::unpackStored(['rules' => []])['rule_on']);
        $this->assertSame($allOn, AmazonDilGroiRule::normalizeRuleOn('nope'));
        $this->assertSame($allOn, AmazonDilGroiRule::normalizeRuleOn(['cvr' => true, 'unknown' => false]));

        $saved = AmazonDilGroiRule::unpackStored(['rules' => [], 'rule_on' => ['dil' => false, 'age' => false, 'lmp' => 0, 'diff' => 'false', 'cvr' => '0']]);
        $this->assertSame(['dil' => false, 'rev' => true, 'cvr' => false, 'age' => false, 'diff' => false, 'lmp' => false, 'clearance' => true], $saved['rule_on']);
        $this->assertFalse(AmazonDilGroiRule::normalizeRuleOn(['rev' => 'off'])['rev']);
    }

    public function test_rule_sequence_orders_the_target_nroi_rules_and_the_price_rules(): void
    {
        $this->assertSame(['cvr', 'age', 'diff', 'clearance'], AmazonDilGroiRule::ruleOrder(null));
        $this->assertSame(['lmp', 'cvrud'], AmazonDilGroiRule::priceOrder(null));
        $this->assertSame(['cvr', 'age', 'diff', 'clearance'], AmazonDilGroiRule::ruleOrder('nope'));
        // Lowest number first, inside each group.
        $this->assertSame(['diff', 'cvr', 'age', 'clearance'], AmazonDilGroiRule::ruleOrder(['cvr' => 3, 'age' => 4, 'diff' => 2]));
        $this->assertSame(['cvrud', 'lmp'], AmazonDilGroiRule::priceOrder(['lmp' => 8, 'cvrud' => 2]));
        // The Dil slab number never reorders the rules; Clearance is a Target NROI rule and goes by its own number.
        $this->assertSame(['cvr', 'age', 'diff', 'clearance'], AmazonDilGroiRule::ruleOrder(['dil' => 9]));
        $this->assertSame(['clearance', 'cvr', 'age', 'diff'], AmazonDilGroiRule::ruleOrder(['clearance' => 1]));
        // A tie keeps the default order.
        $this->assertSame(['cvr', 'age', 'diff', 'clearance'], AmazonDilGroiRule::ruleOrder(['cvr' => 5, 'age' => 5, 'diff' => 5]));
        $this->assertSame(['lmp', 'cvrud'], AmazonDilGroiRule::priceOrder(['lmp' => 5, 'cvrud' => 5]));
        // Garbage keeps the default number; numbers are clamped to 1..99.
        $seq = AmazonDilGroiRule::normalizeRuleSeq(['cvr' => -4, 'age' => 'x', 'diff' => 500, 'dil' => 12]);
        $this->assertSame(1.0, $seq['cvr']);
        $this->assertSame(4.0, $seq['age']);
        $this->assertSame(99.0, $seq['diff']);
        $this->assertSame(12.0, $seq['dil']);
        $this->assertSame(8.0, $seq['clearance']);

        $saved = AmazonDilGroiRule::unpackStored(['rules' => [], 'rule_seq' => ['age' => 2, 'cvr' => 3]]);
        $this->assertSame(['dil' => 1.0, 'rev' => 2.0, 'cvr' => 3.0, 'age' => 2.0, 'diff' => 5.0, 'lmp' => 6.0, 'cvrud' => 7.0, 'clearance' => 8.0], $saved['rule_seq']);
        $this->assertSame(AmazonDilGroiRule::RULE_SEQ_DEFAULT, AmazonDilGroiRule::unpackStored(null)['rule_seq']);
        // Review Discount is Seq 2 by default and never reorders the NROI / price rules.
        $this->assertSame(2.0, AmazonDilGroiRule::RULE_SEQ_DEFAULT['rev']);
        $this->assertSame(['cvr', 'age', 'diff', 'clearance'], AmazonDilGroiRule::ruleOrder(['rev' => 1]));
        $this->assertSame(['lmp', 'cvrud'], AmazonDilGroiRule::priceOrder(['rev' => 99]));
    }

    public function test_review_discount_slabs_give_the_reduce_pct_added_to_the_lmp_reduce(): void
    {
        $rules = AmazonDilGroiRule::normalizeRevReduce([
            ['min' => 0, 'max' => 3, 'pct' => 5],
            ['min' => 3, 'max' => 4, 'pct' => 3],
            ['min' => 4, 'max' => 5, 'pct' => 1.5],
        ]);

        $this->assertSame(5.0, AmazonDilGroiRule::revReducePct(2.5, 10, $rules));
        // min ≤ rating < max: 3.0 is in the second slab, 4.0 in the third; the last slab also includes its To value.
        $this->assertSame(3.0, AmazonDilGroiRule::revReducePct(3.0, 10, $rules));
        $this->assertSame(1.5, AmazonDilGroiRule::revReducePct(4.0, 10, $rules));
        $this->assertSame(1.5, AmazonDilGroiRule::revReducePct(5.0, 10, $rules));
        // No rating, no reviews, or a rating outside every slab adds nothing. An unknown review count does not block it.
        $this->assertSame(0.0, AmazonDilGroiRule::revReducePct(0.0, 10, $rules));
        $this->assertSame(0.0, AmazonDilGroiRule::revReducePct(null, 10, $rules));
        $this->assertSame(0.0, AmazonDilGroiRule::revReducePct(2.5, 0, $rules));
        $this->assertSame(5.0, AmazonDilGroiRule::revReducePct(2.5, null, $rules));
        $this->assertSame(0.0, AmazonDilGroiRule::revReducePct(5.5, 10, $rules));

        // It is ADDED to the Dil band %: LMP $30, Dil band 2% + rating slab 5% → LMP − 7% = 27.90.
        $reduce = AmazonDilGroiRule::lmpCapReducePct(10.0, null) + AmazonDilGroiRule::revReducePct(2.5, 10, $rules);
        $this->assertSame(7.0, $reduce);
        $this->assertEqualsWithDelta(27.9, AmazonDilGroiRule::capSpriceToLmp(50, 30, 10, 0, 0.80, 0, $reduce, false), 0.001);
        // The total never goes above 99%.
        $this->assertEqualsWithDelta(0.3, AmazonDilGroiRule::lmpTargetPrice(30, 99 + 50), 0.001);
    }

    public function test_review_discount_defaults_change_nothing_and_are_saved_with_the_sprc_dil_rules(): void
    {
        $defaults = AmazonDilGroiRule::normalizeRevReduce(null);
        $this->assertSame(AmazonDilGroiRule::REV_REDUCE_DEFAULTS, $defaults);
        foreach ([0.5, 2.9, 3.0, 4.2, 5.0] as $rating) {
            $this->assertSame(0.0, AmazonDilGroiRule::revReducePct($rating, 5, $defaults), 'rating '.$rating);
        }
        // Rows saved before the rule existed → defaults. A saved empty list → off.
        $this->assertSame(AmazonDilGroiRule::REV_REDUCE_DEFAULTS, AmazonDilGroiRule::unpackStored(['rules' => []])['rev_reduce']);
        $this->assertSame([], AmazonDilGroiRule::unpackStored(['rules' => [], 'rev_reduce' => []])['rev_reduce']);
        $this->assertSame(0.0, AmazonDilGroiRule::revReducePct(2.0, 5, []));
        $saved = AmazonDilGroiRule::unpackStored(['rules' => [], 'rev_reduce' => [['min' => 1, 'max' => 4, 'pct' => 6]]]);
        $this->assertSame([['min' => 1.0, 'max' => 4.0, 'pct' => 6.0]], $saved['rev_reduce']);

        // A swapped From / To is put right, % is clamped to 0..99, a row without a number is dropped.
        $rules = AmazonDilGroiRule::normalizeRevReduce([['min' => 4, 'max' => 2, 'pct' => 500], ['min' => '', 'max' => 3, 'pct' => 1], ['min' => 1, 'max' => 2, 'pct' => -4]]);
        $this->assertSame([['min' => 2.0, 'max' => 4.0, 'pct' => 99.0], ['min' => 1.0, 'max' => 2.0, 'pct' => 0.0]], $rules);
        $this->assertSame([], AmazonDilGroiRule::normalizeRevReduce('nope'));
    }

    public function test_custom_rule_cards_are_normalized_matched_and_ordered_by_sequence(): void
    {
        $rules = AmazonDilGroiRule::normalizeCustomRules([
            ['id' => 'A-b1!', 'name' => '  High dil  ', 'field' => 'dil', 'op' => '>', 'value' => '50', 'action' => 'nroi', 'points' => '5'],
            ['id' => 'ab1', 'name' => 'duplicate id is dropped'],
            ['id' => 'x2', 'field' => 'nope', 'op' => '??', 'action' => 'boom', 'points' => 9999, 'on' => 'off'],
            ['id' => 'p3', 'field' => 'age', 'op' => 'between', 'value' => 90, 'value2' => 30, 'action' => 'price_pct', 'points' => -3],
            'garbage',
        ]);
        $this->assertCount(3, $rules);
        $this->assertSame('ab1', $rules[0]['id']);
        $this->assertSame('High dil', $rules[0]['name']);
        $this->assertSame(5.0, $rules[0]['points']);
        // Unknown field / op / action fall back, points are clamped, "off" turns the rule off.
        $this->assertSame(['dil', '>', 'nroi', 500.0, false], [$rules[1]['field'], $rules[1]['op'], $rules[1]['action'], $rules[1]['points'], $rules[1]['on']]);
        $this->assertSame([], AmazonDilGroiRule::normalizeCustomRules('nope'));

        // Conditions.
        $this->assertTrue(AmazonDilGroiRule::customRuleHit($rules[0], ['dil' => 60.0]));
        $this->assertFalse(AmazonDilGroiRule::customRuleHit($rules[0], ['dil' => 50.0]));
        $this->assertFalse(AmazonDilGroiRule::customRuleHit($rules[0], ['dil' => null]));
        $this->assertFalse(AmazonDilGroiRule::customRuleHit($rules[1], ['dil' => 99.0]), 'an OFF rule never applies');
        $this->assertTrue(AmazonDilGroiRule::customRuleHit($rules[2], ['age' => 60.0]), 'between works with the bounds in either order');
        $this->assertFalse(AmazonDilGroiRule::customRuleHit($rules[2], ['age' => 120.0]));

        // Each custom rule joins the group of its action and is ordered by its c_<id> sequence number.
        $seq = ['cvr' => 5, 'age' => 6, 'diff' => 7, 'c_ab1' => 1, 'c_x2' => 9, 'c_p3' => 1];
        $this->assertSame(['c_ab1', 'cvr', 'age', 'diff', 'clearance', 'c_x2'], AmazonDilGroiRule::ruleOrder($seq, $rules));
        $this->assertSame(['c_p3', 'lmp', 'cvrud'], AmazonDilGroiRule::priceOrder($seq, $rules));
        // Without custom rules nothing changes.
        $this->assertSame(['cvr', 'age', 'diff', 'clearance'], AmazonDilGroiRule::ruleOrder(null, []));

        // Saved with the other Sprc Dil blocks.
        $saved = AmazonDilGroiRule::unpackStored(['rules' => [], 'custom_rules' => [['id' => 'q9', 'field' => 'inv', 'op' => '<=', 'value' => 3]], 'rule_seq' => ['c_q9' => 2]]);
        $this->assertSame('q9', $saved['custom_rules'][0]['id']);
        $this->assertSame(2.0, $saved['rule_seq']['c_q9']);
        $this->assertSame([], AmazonDilGroiRule::unpackStored(null)['custom_rules']);
    }

    public function test_clearance_snroi_slabs_are_up_to_25_then_up_to_50_then_above(): void
    {
        $rules = AmazonDilGroiRule::normalizeClearanceSnroiRules(null);

        $this->assertSame(0.0, AmazonDilGroiRule::clearanceSnroiTarget(0, $rules));
        $this->assertSame(0.0, AmazonDilGroiRule::clearanceSnroiTarget(25, $rules));
        $this->assertSame(20.0, AmazonDilGroiRule::clearanceSnroiTarget(25.01, $rules));
        $this->assertSame(20.0, AmazonDilGroiRule::clearanceSnroiTarget(50, $rules));
        $this->assertSame(30.0, AmazonDilGroiRule::clearanceSnroiTarget(50.01, $rules));
        $this->assertSame(30.0, AmazonDilGroiRule::clearanceSnroiTarget(400, $rules));
    }

    public function test_dynamic_ads_solves_the_price_against_its_own_slab(): void
    {
        $rule = ['enabled' => true, 'slabs' => [
            ['min' => 0.0, 'max' => 20.0, 'pct' => 0.0],
            ['min' => 20.0, 'max' => null, 'pct' => 13.0],
        ]];
        $solve = fn (float $lp, float $ship, float $target) => fn (float $a): ?float => AmazonDilGroiRule::suggestedPriceFromNroi($lp, $ship, $target, $a);

        // Lands under $20 with 0% Ads.
        $this->assertSame(17.5, AmazonDilGroiRule::priceWithDynamicAds($solve(10, 1, 30), 13.3, $rule));
        // Would be $25.63 with 0% Ads, which is in the 13% slab, so it is solved with 13%.
        $this->assertSame(30.6, AmazonDilGroiRule::priceWithDynamicAds($solve(15, 1, 30), 13.3, $rule));
        // Rule off, or no slabs: the channel Ads % alone.
        $off = ['enabled' => false, 'slabs' => $rule['slabs']];
        $this->assertSame(
            AmazonDilGroiRule::suggestedPriceFromNroi(15, 1, 30, 13.3),
            AmazonDilGroiRule::priceWithDynamicAds($solve(15, 1, 30), 13.3, $off)
        );
        $this->assertSame(13.3, AmazonDilGroiRule::dynamicAdsPctAtPrice(25, $off, 13.3));
    }

    public function test_dynamic_ads_uses_slab_start_when_higher_slab_has_lower_ads(): void
    {
        $rule = ['enabled' => true, 'slabs' => [
            ['min' => 0.0, 'max' => 20.0, 'pct' => 30.0],
            ['min' => 20.0, 'max' => null, 'pct' => 0.0],
        ]];
        $solve = fn (float $a): ?float => AmazonDilGroiRule::suggestedPriceFromNroi(14, 0, 0, $a);
        // 30% Ads needs $28 (not under $20); 0% Ads needs $17.50 (under its slab start), so the slab start $20.
        $this->assertSame(20.0, AmazonDilGroiRule::priceWithDynamicAds($solve, 13.3, $rule));
        $this->assertSame(30.0, AmazonDilGroiRule::dynamicAdsPctAtPrice(19.99, $rule, 13.3));
        $this->assertSame(0.0, AmazonDilGroiRule::dynamicAdsPctAtPrice(20, $rule, 13.3));
    }

    public function test_clearance_snroi_blank_target_and_empty_list_mean_unpriced(): void
    {
        $rules = AmazonDilGroiRule::normalizeClearanceSnroiRules([['min' => 0, 'max' => 25, 'nroi' => '']]);
        $this->assertNull($rules[0]['nroi']);
        $this->assertNull(AmazonDilGroiRule::clearanceSnroiTarget(10, $rules));
        $this->assertSame([], AmazonDilGroiRule::normalizeClearanceSnroiRules([]));
        $this->assertNull(AmazonDilGroiRule::clearanceSnroiTarget(10, []));
        $this->assertNull(AmazonDilGroiRule::clearanceSnroiTarget(10, null));
    }

    public function test_diff_adj_defaults_normalizes_and_decides_points(): void
    {
        $this->assertSame(['gt_pct' => 20.0, 'points' => 10.0], AmazonDilGroiRule::normalizeDiffAdj(null));
        $this->assertSame(['gt_pct' => 15.5, 'points' => -5.0], AmazonDilGroiRule::normalizeDiffAdj(['gt_pct' => '15.5', 'points' => -5, 'x' => 1]));
        $this->assertSame(['gt_pct' => 20.0, 'points' => 10.0], AmazonDilGroiRule::normalizeDiffAdj(['gt_pct' => 'abc', 'points' => '']));
        $this->assertSame(10.0, AmazonDilGroiRule::diffAdjPoints(25.0));
        $this->assertSame(0.0, AmazonDilGroiRule::diffAdjPoints(20.0));
        $this->assertSame(0.0, AmazonDilGroiRule::diffAdjPoints(null));
        $this->assertSame(3.0, AmazonDilGroiRule::diffAdjPoints(11.0, ['gt_pct' => 10, 'points' => 3]));
        $this->assertSame(['gt_pct' => 20.0, 'points' => 10.0], AmazonDilGroiRule::unpackStored(['rules' => [], 'cvr_adj' => []])['diff_adj']);
        $this->assertSame(30.0, AmazonDilGroiRule::unpackStored(['rules' => [], 'diff_adj' => ['gt_pct' => 30]])['diff_adj']['gt_pct']);
    }
}
