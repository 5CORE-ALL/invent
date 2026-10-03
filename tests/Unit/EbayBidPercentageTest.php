<?php

namespace Tests\Unit;

use App\Support\EbayBidPercentage;
use PHPUnit\Framework\TestCase;

class EbayBidPercentageTest extends TestCase
{
    public function test_es_bid_is_the_maximum_recommendation(): void
    {
        $max = EbayBidPercentage::maximumSuggested([
            ['basis' => 'TRENDING', 'value' => '12.1'],
            ['basis' => 'ITEM', 'value' => '9.0'],
        ]);

        $this->assertSame(12.1, $max);
    }

    public function test_push_uses_one_decimal_inside_ebay_limits(): void
    {
        $this->assertSame('8.1', EbayBidPercentage::forPush(8.14));
        $this->assertSame('10.8', EbayBidPercentage::forPush(10.75));
        $this->assertSame('100.0', EbayBidPercentage::forPush(150));
        $this->assertSame('2.0', EbayBidPercentage::forPush(1.2));
        $this->assertNull(EbayBidPercentage::forPush(0));
    }

    public function test_maximum_is_read_from_the_ebay_error(): void
    {
        $max = EbayBidPercentage::maxFromError([
            'errors' => [[
                'errorId' => 35007,
                'message' => "The 'bidPercentage' 150.0 is not valid. Minimum value: 2.0 , Maximum value:100.0.",
                'parameters' => [
                    ['name' => 'maxBidPercent', 'value' => '100.0'],
                ],
            ]],
        ]);

        $this->assertSame(100.0, $max);
    }
}
