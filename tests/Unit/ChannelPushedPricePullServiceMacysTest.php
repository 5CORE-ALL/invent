<?php

namespace Tests\Unit;

use App\Services\ChannelPushedPricePullService;
use App\Services\MacysApiService;
use Tests\TestCase;

class ChannelPushedPricePullServiceMacysTest extends TestCase
{
    public function test_macys_pull_uses_mcm_listed_price(): void
    {
        $this->mock(MacysApiService::class, function ($mock) {
            $mock->shouldReceive('pullLiveListedPrice')
                ->once()
                ->with('GSS BLU 2PCS')
                ->andReturn([
                    'price' => 23.41,
                    'stock' => 4,
                    'shop_sku' => 'GSS BLU 2PCS',
                ]);
        });

        $out = app(ChannelPushedPricePullService::class)->pullSkus('macys', ['GSS BLU 2PCS']);

        $this->assertCount(1, $out);
        $this->assertTrue($out[0]['success']);
        $this->assertEqualsWithDelta(23.41, $out[0]['price'], 0.001);
        $this->assertSame('macys', $out[0]['marketplace']);
    }
}
