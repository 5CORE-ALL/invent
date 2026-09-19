<?php

namespace Tests\Unit;

use App\Services\ChannelPushedPricePullService;
use App\Services\TopDawgApiService;
use Tests\TestCase;

class ChannelPushedPricePullServiceTopDawgTest extends TestCase
{
    public function test_topdawg_pull_uses_live_listing_price(): void
    {
        $this->mock(TopDawgApiService::class, function ($mock) {
            $mock->shouldReceive('pullLiveListedPrice')
                ->once()
                ->with('GSTOOL BLK', null)
                ->andReturn([
                    'price' => 24.99,
                    'stale' => false,
                ]);
        });

        $out = app(ChannelPushedPricePullService::class)->pullSkus('topdawg', ['GSTOOL BLK']);

        $this->assertCount(1, $out);
        $this->assertTrue($out[0]['success']);
        $this->assertEqualsWithDelta(24.99, $out[0]['price'], 0.001);
        $this->assertSame('topdawg', $out[0]['marketplace']);
    }

    public function test_topdawg_pull_retries_when_review_queue_is_stale(): void
    {
        $this->mock(TopDawgApiService::class, function ($mock) {
            $mock->shouldReceive('pullLiveListedPrice')
                ->once()
                ->with('GSTOOL BLK', 29.99)
                ->andReturn([
                    'price' => 19.50,
                    'stale' => true,
                ]);
        });

        $out = app(ChannelPushedPricePullService::class)->pullSkus(
            'topdawg',
            ['GSTOOL BLK'],
            ['GSTOOL BLK' => 29.99]
        );

        $this->assertCount(1, $out);
        $this->assertFalse($out[0]['success']);
        $this->assertEqualsWithDelta(19.50, $out[0]['price'], 0.001);
        $this->assertSame('TopDawg still catching up', $out[0]['message']);
    }
}
