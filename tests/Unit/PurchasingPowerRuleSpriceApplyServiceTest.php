<?php

namespace Tests\Unit;

use App\Services\PurchasingPowerRuleSpriceApplyService;
use App\Support\AmazonDilGroiRule;
use Tests\TestCase;

class PurchasingPowerRuleSpriceApplyServiceTest extends TestCase
{
    public function test_dil_match_excludes_ship(): void
    {
        $out = $this->compute([
            'inv' => 10,
            'dil' => 3,
            'pp_l30' => 2,
            'lp' => 10,
            'ship' => 8,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(23.08, $out['sprice'], 0.01);
    }

    public function test_zero_sold_uses_min_groi_even_when_dil_is_out_of_box(): void
    {
        $out = $this->compute([
            'inv' => 41,
            'dil' => 0,
            'pp_l30' => 0,
            'lp' => 10,
        ], [
            AmazonDilGroiRule::make(0.1, 5, 40),
            AmazonDilGroiRule::make(5, 10, 70),
        ]);

        $this->assertNotNull($out);
        $this->assertEqualsWithDelta(21.54, $out['sprice'], 0.01);
    }

    public function test_out_of_box_with_sales_has_no_dil_sprice(): void
    {
        $out = $this->compute([
            'inv' => 2,
            'dil' => 300,
            'pp_l30' => 4,
            'lp' => 10,
        ], [AmazonDilGroiRule::make(0.1, 25, 50)]);

        $this->assertNull($out);
    }

    public function test_skips_when_inventory_is_zero(): void
    {
        $out = $this->compute([
            'inv' => 0,
            'dil' => 10,
            'pp_l30' => 0,
            'lp' => 10,
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
        return app(PurchasingPowerRuleSpriceApplyService::class)->computeTarget($row, $dilRules, 0.65);
    }
}
