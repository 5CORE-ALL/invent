<?php

namespace Tests\Unit;

use App\Services\Support\AmazonPushPrcRunner;
use Tests\TestCase;

class AmazonPushPrcOfferPayloadTest extends TestCase
{
    public function test_sprice_above_std_raises_your_price_to_sprice(): void
    {
        $offer = AmazonPushPrcRunner::amazonOfferPayload([
            'std' => 20.99,
            'sale' => null,
            'effective' => 21.99,
            'max' => 23.09,
        ]);

        $this->assertEqualsWithDelta(21.99, $offer['price'], 0.001);
        $this->assertEqualsWithDelta(21.99, $offer['min_price'], 0.001);
        $this->assertEqualsWithDelta(21.99, $offer['business_price'], 0.001);
        $this->assertGreaterThanOrEqual($offer['price'], $offer['max_price']);
        $this->assertArrayNotHasKey('sale_price', $offer);
    }

    public function test_sprice_below_std_keeps_your_price_and_sends_sale(): void
    {
        $offer = AmazonPushPrcRunner::amazonOfferPayload([
            'std' => 100,
            'sale' => 85,
            'effective' => 85,
            'max' => 110,
        ]);

        $this->assertEqualsWithDelta(100.0, $offer['price'], 0.001);
        $this->assertEqualsWithDelta(85.0, $offer['sale_price'], 0.001);
        $this->assertEqualsWithDelta(85.0, $offer['min_price'], 0.001);
        $this->assertEqualsWithDelta(85.0, $offer['business_price'], 0.001);
        $this->assertEqualsWithDelta(110.0, $offer['max_price'], 0.001);
    }
}
