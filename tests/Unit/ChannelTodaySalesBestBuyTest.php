<?php

namespace Tests\Unit;

use App\Services\Support\ChannelTodaySalesService;
use PHPUnit\Framework\TestCase;

class ChannelTodaySalesBestBuyTest extends TestCase
{
    public function test_bestbuy_display_names_resolve_to_today_sales(): void
    {
        $svc = new ChannelTodaySalesService();
        $sales = [
            'bestbuyusa' => 159.18,
            'bestbuy' => 159.18,
        ];

        $this->assertSame(159.18, $svc->valueForChannel('BestBuy USA', $sales));
        $this->assertSame(159.18, $svc->valueForChannel('Best Buy USA', $sales));
        $this->assertSame(159.18, $svc->valueForChannel('bestbuyusa', $sales));
        $this->assertSame(159.18, $svc->valueForChannel('BestbuyUSA', $sales));
    }
}
