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
        $this->assertEqualsWithDelta(80.0, $out['sprice'], 0.001);
        $this->assertFalse($out['amz_sugg']);
    }

    public function test_keeps_dil_sprice_when_already_above_amazon(): void
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
        $this->assertEqualsWithDelta(31.58, $out['sprice'], 0.01);
    }

    public function test_zero_sold_uses_min_groi_then_amz_floor(): void
    {
        $out = $this->compute([
            'inv' => 8,
            'dil' => 8,
            'b2c_l30' => 0,
            'lp' => 10,
            'ship' => 0,
            'std' => 100,
            'amz' => 50,
            'cvr' => 0,
        ], [
            AmazonDilGroiRule::make(0.1, 5, 40),
            AmazonDilGroiRule::make(5, 10, 70),
        ]);

        $this->assertNotNull($out);
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

    public function test_cvr_below_7_lowers_target_groi_by_10(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 5,
            'b2c_l30' => 2,
            'lp' => 20,
            'ship' => 0,
            'std' => 100,
            'amz' => 1,
            'cvr' => 6.9,
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
            'amz' => 1,
            'cvr' => 10.1,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        // GROI 50 + 10 = 60 → (20 × 1.60) / 0.95
        $this->assertEqualsWithDelta(33.68, $out['sprice'], 0.01);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array{key:string,label:string,min:float,max:float,groi:float}>  $dilRules
     * @return array{sprice:float,prmt:float,cpn:float,amz_sugg:bool}|null
     */
    private function compute(array $row, array $dilRules): ?array
    {
        $service = app(ShopifyB2cRuleSpriceApplyService::class);
        $method = new ReflectionMethod($service, 'computeTarget');
        $method->setAccessible(true);

        return $method->invoke($service, $row, [], [], 0.0, 0.95, $dilRules);
    }
}
