<?php

namespace Tests\Unit;

use App\Services\NeweggApiService;
use Tests\TestCase;

class NeweggPricePushTest extends TestCase
{
    public function test_build_price_row_raises_msrp_when_selling_is_higher(): void
    {
        $row = app(NeweggApiService::class)->buildPriceUpdateRow(
            'USA',
            'USD',
            31.99,
            [],
            ['MSRP' => '20.00', 'MAP' => '15.00', 'CheckoutMAP' => '0']
        );

        $this->assertSame('31.99', $row['SellingPrice']);
        $this->assertSame('31.99', $row['MSRP']);
        $this->assertSame('15.00', $row['MAP']);
        $this->assertSame('0', $row['CheckoutMAP']);
    }

    public function test_build_price_row_keeps_higher_existing_msrp(): void
    {
        $row = app(NeweggApiService::class)->buildPriceUpdateRow(
            'USA',
            'USD',
            19.99,
            [],
            ['MSRP' => '40.00']
        );

        $this->assertSame('19.99', $row['SellingPrice']);
        $this->assertSame('40.00', $row['MSRP']);
    }

    public function test_build_price_row_sets_msrp_when_missing(): void
    {
        $row = app(NeweggApiService::class)->buildPriceUpdateRow('USA', 'USD', 12.50);

        $this->assertSame('12.50', $row['SellingPrice']);
        $this->assertSame('12.50', $row['MSRP']);
    }

    public function test_write_echo_is_used_when_it_matches(): void
    {
        $resolved = app(NeweggApiService::class)->resolvePriceUpdateConfirmation(true, 19.99, 18.00, 19.99);

        $this->assertTrue($resolved['ok']);
        $this->assertSame(19.99, $resolved['confirmed']);
    }

    public function test_lagging_live_get_does_not_fail_accepted_write(): void
    {
        $resolved = app(NeweggApiService::class)->resolvePriceUpdateConfirmation(true, null, 18.00, 31.99);

        $this->assertTrue($resolved['ok']);
        $this->assertSame(31.99, $resolved['confirmed']);
    }

    public function test_rejected_write_stays_failed(): void
    {
        $resolved = app(NeweggApiService::class)->resolvePriceUpdateConfirmation(false, null, 31.99, 31.99);

        $this->assertFalse($resolved['ok']);
        $this->assertNull($resolved['confirmed']);
    }

    public function test_extract_selling_price_unwraps_update_price_result(): void
    {
        $price = app(NeweggApiService::class)->extractSellingPrice([
            'UpdatePriceResult' => [
                'SellerPartNumber' => 'MS RBL HND CLCH',
                'PriceList' => [
                    'Price' => [
                        ['CountryCode' => 'USA', 'SellingPrice' => '31.99'],
                    ],
                ],
            ],
        ], 'USA');

        $this->assertSame(31.99, $price);
    }

    public function test_error_payload_is_not_a_successful_price_write(): void
    {
        $method = new \ReflectionMethod(NeweggApiService::class, 'extractPriceUpdateSuccess');
        $method->setAccessible(true);

        $ok = $method->invoke(app(NeweggApiService::class), [
            'ok' => true,
            'status' => 200,
            'blocked_by_cloudflare' => false,
            'json' => ['Code' => 'CT029', 'Message' => 'The selling price cannot be greater than MSRP'],
            'raw' => '',
            'error' => null,
        ]);

        $this->assertFalse($ok);
    }
}
