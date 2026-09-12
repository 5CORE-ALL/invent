<?php

namespace Tests\Unit;

use App\Services\MacysRuleSpriceApplyService;
use App\Support\AmazonDilGroiRule;
use Tests\TestCase;

class MacysRuleSpriceApplyServiceTest extends TestCase
{
    public function test_dil_match_uses_slab_then_floors_to_amazon(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 3,
            'mc_l30' => 2,
            'lp' => 10,
            'ship' => 0,
            'amz' => 40,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(40.0, $out['sprice'], 0.001);
    }

    public function test_keeps_dil_when_at_or_above_amazon(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 3,
            'mc_l30' => 2,
            'lp' => 20,
            'ship' => 0,
            'amz' => 10,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(37.5, $out['sprice'], 0.01);
    }

    public function test_out_of_box_zero_sold_uses_min_groi_then_amazon_floor(): void
    {
        $out = $this->compute([
            'inv' => 41,
            'dil' => 0,
            'mc_l30' => 0,
            'lp' => 10,
            'ship' => 0,
            'amz' => 39.60,
        ], [
            AmazonDilGroiRule::make(0.1, 5, 40),
            AmazonDilGroiRule::make(5, 10, 70),
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(39.60, $out['sprice'], 0.001);
    }

    public function test_zero_sold_uses_min_groi_even_when_dil_matches_a_slab(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 3,
            'mc_l30' => 0,
            'lp' => 10,
            'ship' => 0,
            'amz' => 0,
        ], [
            AmazonDilGroiRule::make(0.1, 5, 50),
            AmazonDilGroiRule::make(5, 10, 40),
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(17.50, $out['sprice'], 0.01);
    }

    public function test_zero_sold_keeps_min_groi_when_at_or_above_amazon(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 8,
            'mc_l30' => 0,
            'lp' => 20,
            'ship' => 0,
            'amz' => 10,
        ], [
            AmazonDilGroiRule::make(0.1, 5, 40),
            AmazonDilGroiRule::make(5, 10, 70),
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(35.00, $out['sprice'], 0.01);
    }

    public function test_out_of_box_with_sales_uses_std_then_amazon_floor(): void
    {
        $out = $this->compute([
            'inv' => 2,
            'dil' => 300,
            'mc_l30' => 4,
            'lp' => 10,
            'ship' => 0,
            'std' => 95.99,
            'amz' => 94.07,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(95.99, $out['sprice'], 0.001);
    }

    public function test_out_of_box_std_below_amazon_uses_a_price(): void
    {
        $out = $this->compute([
            'inv' => 2,
            'dil' => 300,
            'mc_l30' => 1,
            'std' => 10.00,
            'amz' => 13.99,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(13.99, $out['sprice'], 0.001);
    }

    public function test_skips_when_inventory_is_zero(): void
    {
        $out = $this->compute([
            'inv' => 0,
            'dil' => 10,
            'mc_l30' => 0,
            'lp' => 10,
            'ship' => 0,
            'amz' => 20,
        ], AmazonDilGroiRule::defaults());

        $this->assertNull($out);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array{key:string,label:string,min:float,max:float,groi:float}>  $dilRules
     * @return array{sprice:float}|null
     */
    private function compute(array $row, array $dilRules): ?array
    {
        return app(MacysRuleSpriceApplyService::class)->computeTarget($row, $dilRules, 0.80);
    }
}
