<?php

namespace Tests\Unit;

use App\Services\AmazonSpApiService;
use ReflectionMethod;
use Tests\TestCase;

class AmazonCustomerPriceReadTest extends TestCase
{
    public function test_listing_read_uses_customer_your_price_when_business_is_first(): void
    {
        $data = [
            'offers' => [
                ['audience' => ['value' => 'ALL', 'displayName' => 'Sell on Amazon']],
                ['audience' => ['value' => 'B2B', 'displayName' => 'Amazon Business (B2B)']],
            ],
            'attributes' => [
                'purchasable_offer' => [
                    [
                        'audience' => 'B2B',
                        'our_price' => [['schedule' => [['value_with_tax' => 71.75]]]],
                    ],
                    [
                        'audience' => 'ALL',
                        'our_price' => [['schedule' => [['value_with_tax' => 75.53]]]],
                        'discounted_price' => [['schedule' => [['value_with_tax' => 75.43]]]],
                        'minimum_seller_allowed_price' => [['schedule' => [['value_with_tax' => 71.75]]]],
                    ],
                ],
            ],
        ];

        $read = new ReflectionMethod(AmazonSpApiService::class, 'extractListingsItemYourPrice');
        $price = $read->invoke(app(AmazonSpApiService::class), $data);

        $this->assertEqualsWithDelta(75.53, $price, 0.001);
    }
}
