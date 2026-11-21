<?php

namespace Tests\Unit;

use App\Services\AmazonSprcDilAutoPushService;
use PHPUnit\Framework\TestCase;

class AmazonCvrCompoundTest extends TestCase
{
    private function target(bool $compound, float $price, float $sprice): ?array
    {
        $svc = new AmazonSprcDilAutoPushService;
        $cfg = ['enabled' => true, 'down' => 1.0, 'same' => null, 'up' => null, 'pink' => null, 'down_band' => 0.0, 'up_band' => 100.0,
            'lower_cap' => true, 'min_npft' => 10.0, 'min_nroi' => 30.0, 'upper_cap' => false];
        foreach (['cvrPriceRuleCfg' => $cfg, 'cvrCompound' => $compound] as $prop => $val) {
            $r = new \ReflectionProperty($svc, $prop);
            $r->setAccessible(true);
            $r->setValue($svc, $val);
        }
        $m = new \ReflectionMethod($svc, 'computeCvrOnlyTarget');
        $m->setAccessible(true);
        // Sold 0, no sessions to speak of: always CVR Down.
        $row = ['inv' => 87, 'price' => $price, 'sprice' => $sprice, 'standard_price' => 69.99, 'lp' => 16.53, 'ship' => 7.03,
            'a_l30' => 0, 'sess30' => 39, 'a_l60' => 0, 'sess60' => 15];

        return $m->invoke($svc, $row, 12.19);
    }

    public function test_catch_up_holds_once_the_saved_sprice_equals_the_live_price(): void
    {
        $this->assertNull($this->target(false, 69.29, 69.29));
    }

    public function test_scheduled_run_takes_another_percent_off_the_live_price(): void
    {
        $out = $this->target(true, 69.29, 69.29);
        $this->assertNotNull($out);
        $this->assertSame(68.6, $out['sprice']);
        $out = $this->target(true, 68.6, 68.6);
        $this->assertSame(67.91, $out['sprice']);
    }

    public function test_it_stops_at_the_lower_cap_price(): void
    {
        // Floor is about 42.06 here; a price already under it is never cut further.
        $this->assertNull($this->target(true, 40.0, 40.0));
    }
}
