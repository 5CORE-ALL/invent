<?php

namespace Tests\Unit;

use App\Support\EbayYesterdaySpend;
use PHPUnit\Framework\TestCase;

class EbayYesterdaySpendTest extends TestCase
{
    public function test_money_strips_currency_text(): void
    {
        $this->assertSame(12.5, EbayYesterdaySpend::money('USD 12.50'));
        $this->assertSame(1234.5, EbayYesterdaySpend::money('USD 1,234.50'));
        $this->assertSame(0.0, EbayYesterdaySpend::money(null));
        $this->assertSame(0.0, EbayYesterdaySpend::money(''));
    }

    public function test_sku_spend_adds_keyword_and_promoted(): void
    {
        $row = EbayYesterdaySpend::forSku('ABC-123', '999', [
            'l1_sku' => ['ABC 123' => 4.25],
            'prev_sku' => ['ABC 123' => 2.0],
            'l1_listing' => ['999' => 1.5],
            'prev_listing' => ['999' => 1.5],
        ]);

        $this->assertEquals(5.75, $row['y_spend']);
        $this->assertEquals(3.5, $row['y_spend_prev']);
    }

    public function test_cpc_row_uses_campaign_spend_and_promoted_row_uses_listing(): void
    {
        $maps = [
            'l1_campaign' => ['55' => 9.0],
            'prev_campaign' => [],
            'l1_listing' => ['1' => 3.0],
            'prev_listing' => ['1' => 1.0],
        ];

        $cpc = EbayYesterdaySpend::forAdRow('1', '55', 'COST_PER_CLICK', $maps);
        $this->assertEquals(9.0, $cpc['y_spend']);
        $this->assertNull($cpc['y_spend_prev']);
        $this->assertSame('campaign', $cpc['source']);

        $pmt = EbayYesterdaySpend::forAdRow('1', '55', 'COST_PER_SALE', $maps);
        $this->assertEquals(3.0, $pmt['y_spend']);
        $this->assertEquals(1.0, $pmt['y_spend_prev']);
        $this->assertSame('listing', $pmt['source']);
    }

    public function test_sku_dot_uses_the_newer_recorded_day_only(): void
    {
        $row = EbayYesterdaySpend::forSku('ABC', '999', [
            'l1_sku' => ['ABC' => 8],
            'prev_sku' => ['ABC' => 2],
            'prev_sku_date' => ['ABC' => '2026-09-02'],
            'l1_listing' => ['999' => 1],
            'prev_listing' => ['999' => 5],
            'prev_listing_date' => ['999' => '2026-08-01'],
        ]);

        $this->assertEquals(9.0, $row['y_spend']);
        $this->assertEquals(2.0, $row['y_spend_prev']);
        $this->assertSame('2026-09-02', $row['y_spend_prev_date']);
    }

    public function test_missing_prior_day_stays_null(): void
    {
        $row = EbayYesterdaySpend::forSku('ABC', '', [
            'l1_sku' => ['ABC' => 1.25],
            'prev_sku' => [],
            'l1_listing' => [],
            'prev_listing' => [],
        ]);

        $this->assertEquals(1.25, $row['y_spend']);
        $this->assertNull($row['y_spend_prev']);
    }
}
