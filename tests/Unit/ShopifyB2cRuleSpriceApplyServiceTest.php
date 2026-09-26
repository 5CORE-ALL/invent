<?php

namespace Tests\Unit;

use App\Services\ShopifyB2cRuleSpriceApplyService;
use App\Support\AmazonDilGroiRule;
use ReflectionMethod;
use Tests\TestCase;

class ShopifyB2cRuleSpriceApplyServiceTest extends TestCase
{
    public function test_raises_dil_sprice_to_amazon_when_below_a_price(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 5,
            'b2c_l30' => 2,
            'lp' => 10,
            'ship' => 0,
            'std' => 100,
            'amz' => 80,
            'cvr' => 1,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        // GROI 50 → (10 × 1.50) / 0.95 = 15.79, below A Price 80, so S PRC uses Amz.
        $this->assertEqualsWithDelta(80.0, $out['sprice'], 0.001);
        $this->assertFalse($out['amz_sugg']);
    }

    public function test_keeps_sprc_dil_when_above_a_price(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 5,
            'b2c_l30' => 2,
            'lp' => 20,
            'ship' => 0,
            'std' => 100,
            'amz' => 10,
            'cvr' => 8,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        // Dil $ is 31.58, above A Price 10, so S PRC keeps Sprc Dil.
        $this->assertEqualsWithDelta(31.58, $out['sprice'], 0.001);
        $this->assertFalse($out['amz_sugg']);
    }

    public function test_zero_sold_min_groi_rises_to_amazon_when_below_a_price(): void
    {
        $out = $this->compute([
            'inv' => 8,
            'dil' => 8,
            'b2c_l30' => 0,
            'lp' => 10,
            'ship' => 0,
            'std' => 100,
            'amz' => 50,
            'cvr' => 8,
            'cvr_60' => 8,
        ], [
            AmazonDilGroiRule::make(0.1, 5, 40),
            AmazonDilGroiRule::make(5, 10, 70),
        ]);

        $this->assertNotNull($out);
        // Min Target NROI 40 → (10 × 1.40) / 0.95 = 14.74, below A Price 50, so S PRC uses Amz.
        $this->assertEqualsWithDelta(50.0, $out['sprice'], 0.001);
    }

    public function test_sugg_amz_tracks_current_amazon_price(): void
    {
        $out = $this->compute([
            'inv' => 5,
            'dil' => 5,
            'b2c_l30' => 1,
            'lp' => 10,
            'ship' => 0,
            'std' => 100,
            'amz' => 55.25,
            'cvr' => 2,
            'amz_sugg' => true,
            'saved_sprice' => 40,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(55.25, $out['sprice'], 0.001);
        $this->assertTrue($out['amz_sugg']);
    }

    public function test_sugg_amz_does_not_cap_when_sprc_dil_is_above_a_price(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 5,
            'b2c_l30' => 2,
            'lp' => 20,
            'ship' => 0,
            'std' => 100,
            'amz' => 10,
            'cvr' => 8,
            'amz_sugg' => true,
            'saved_sprice' => 10,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        // Dil $ is 31.58, above A Price 10. Stored Amz pin must not cap S PRC down.
        $this->assertEqualsWithDelta(31.58, $out['sprice'], 0.001);
        $this->assertFalse($out['amz_sugg']);
    }

    public function test_zero_sold_coupon_applies_only_when_enabled(): void
    {
        $rules = [AmazonDilGroiRule::make(0.1, 25, 50)];
        $zero = [
            'inv' => 8,
            'dil' => 8,
            'b2c_l30' => 0,
            'lp' => 10,
            'ship' => 0,
            'std' => 100,
            'amz' => 0,
            'cvr' => 0,
        ];
        $on = $this->compute($zero, $rules, 0.0, ['enabled' => true, 'pct' => 5]);
        $off = $this->compute($zero, $rules, 0.0, ['enabled' => false, 'pct' => 5]);
        $sold = $this->compute(array_merge($zero, ['b2c_l30' => 2]), $rules, 0.0, ['enabled' => true, 'pct' => 7.5]);

        $this->assertNotNull($on);
        $this->assertSame(5.0, $on['cpn']);
        $this->assertNotNull($off);
        $this->assertSame(0.0, $off['cpn']);
        $this->assertNotNull($sold);
        $this->assertSame(0.0, $sold['cpn']);
    }

    public function test_cvr_below_7_lowers_target_groi_by_10(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 5,
            'b2c_l30' => 2,
            'lp' => 20,
            'ship' => 0,
            'std' => 100,
            'amz' => 0,
            'cvr' => 6.9,
            'cvr_60' => 8,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        // GROI 50 − 10 = 40 → (20 × 1.40) / 0.95
        $this->assertEqualsWithDelta(29.47, $out['sprice'], 0.01);
    }

    public function test_cvr_above_10_raises_target_groi_by_10(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 5,
            'b2c_l30' => 2,
            'lp' => 20,
            'ship' => 0,
            'std' => 100,
            'amz' => 0,
            'cvr' => 10.1,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        // GROI 50 + 10 = 60 → (20 × 1.60) / 0.95
        $this->assertEqualsWithDelta(33.68, $out['sprice'], 0.01);
    }

    public function test_nroi_uses_ads_and_inverts_snroi(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 5,
            'b2c_l30' => 2,
            'lp' => 20,
            'ship' => 0,
            'std' => 100,
            'amz' => 0,
            'cvr' => 8,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)], 10.0);

        $this->assertNotNull($out);
        $expected = AmazonDilGroiRule::suggestedPriceFromNroi(20, 0, 50, 10, 0.95);
        $this->assertEqualsWithDelta($expected, $out['sprice'], 0.01);
        $this->assertEqualsWithDelta(
            50.0,
            AmazonDilGroiRule::snroiAtPrice($out['sprice'], 20, 0, 10, 0.95),
            0.06
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array{key:string,label:string,min:float,max:float,groi:float}>  $dilRules
     * @param  array{enabled?:bool,pct?:float}  $coupon
     * @return array{sprice:float,prmt:float,cpn:float,amz_sugg:bool}|null
     */
    private function compute(array $row, array $dilRules, float $adsPct = 0.0, array $coupon = []): ?array
    {
        $service = app(ShopifyB2cRuleSpriceApplyService::class);
        $method = new ReflectionMethod($service, 'computeTarget');
        $method->setAccessible(true);

        return $method->invoke($service, $row, [], [], 0.0, 0.95, $dilRules, null, $adsPct, $coupon);
    }
}
