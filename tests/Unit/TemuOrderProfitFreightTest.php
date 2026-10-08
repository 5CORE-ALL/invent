<?php

namespace Tests\Unit;

use App\Services\TemuShopifySalesService;
use PHPUnit\Framework\TestCase;

/**
 * `base_price_total` holds the goods base until the bg.order.amount.query payload
 * lands, then holds goods + freight. GPFT$ must not move when that flips.
 */
class TemuOrderProfitFreightTest extends TestCase
{
    /** Row before the amount API was fetched: R Price still has to be derived. */
    public function test_profit_basis_is_r_price_when_no_amount_payload(): void
    {
        $basis = TemuShopifySalesService::orderRowProfitBasisUnit([
            'quantity_purchased' => 1,
            'base_price_total' => 6.55,
            'fb_price' => 9.54,
        ]);

        $this->assertSame(9.54, $basis);
    }

    /** Row after the amount API landed: freight is already inside the unit. */
    public function test_profit_basis_does_not_re_add_freight_on_amount_api_rows(): void
    {
        $basis = TemuShopifySalesService::orderRowProfitBasisUnit([
            'quantity_purchased' => 1,
            'base_price_total' => 9.54,
            'fb_price' => 9.54,
        ]);

        $this->assertSame(9.54, $basis);
        $this->assertSame(12.53, TemuShopifySalesService::computeFbPrice(9.54, 1));
    }

    public function test_falls_back_to_r_price_when_fb_price_is_absent(): void
    {
        $basis = TemuShopifySalesService::orderRowProfitBasisUnit([
            'quantity_purchased' => 1,
            'base_price_total' => 6.55,
        ]);

        $this->assertSame(9.54, $basis);
    }

    /**
     * GSS YLW 1PC: goods 6.55, collected 9.54, LP 2.80, Temu ship 4.83.
     * GPFT$ = 9.54 × 0.95 − 2.80 − 4.83 = 1.433 either side of the backfill.
     */
    public function test_gpft_is_stable_across_the_amount_api_backfill(): void
    {
        $margin = 0.95;
        $expected = 9.54 * $margin - 2.80 - 4.83;

        $before = TemuShopifySalesService::temuPriceSalesAndProfit(
            6.55, 1, $margin, 2.80, 4.83, false, true,
            TemuShopifySalesService::orderRowProfitBasisUnit([
                'quantity_purchased' => 1, 'base_price_total' => 6.55, 'fb_price' => 9.54,
            ])
        );

        $after = TemuShopifySalesService::temuPriceSalesAndProfit(
            9.54, 1, $margin, 2.80, 4.83, false, true,
            TemuShopifySalesService::orderRowProfitBasisUnit([
                'quantity_purchased' => 1, 'base_price_total' => 9.54, 'fb_price' => 9.54,
            ])
        );

        $this->assertEqualsWithDelta($expected, $before['profit'], 0.001);
        $this->assertEqualsWithDelta($expected, $after['profit'], 0.001);
    }

    /** Without the override the old call shape still triple-counts — this is the bug. */
    public function test_legacy_call_shape_double_charges_freight(): void
    {
        $buggy = TemuShopifySalesService::temuPriceSalesAndProfit(
            9.54, 1, 0.95, 2.80, 4.83, false, true
        );

        $this->assertEqualsWithDelta(4.27, $buggy['profit'], 0.01);
        $this->assertGreaterThan(2.5 * 1.433, $buggy['profit']);
    }

    /** An explicit basis must not disturb the Temu Price sales figure. */
    public function test_sales_figure_is_untouched_by_the_profit_basis(): void
    {
        $withBasis = TemuShopifySalesService::temuPriceSalesAndProfit(
            9.54, 2, 0.95, 2.80, 4.83, false, true, 9.54
        );
        $withoutBasis = TemuShopifySalesService::temuPriceSalesAndProfit(
            9.54, 2, 0.95, 2.80, 4.83, false, true
        );

        $this->assertSame($withoutBasis['sales'], $withBasis['sales']);
        $this->assertSame($withoutBasis['base'], $withBasis['base']);
    }
}
