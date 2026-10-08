<?php

namespace Tests\Unit;

use App\Http\Controllers\Channels\ChannelMasterController;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Active Channel's Temu GPFT% has to divide by the same base the channel's sales page does:
 * /temu2-tabulator divides by Temu Full Price Sales, /temu-tabulator and /temu3-tabulator
 * by their own sales.
 */
class TemuLiveSummaryGpftBaseTest extends TestCase
{
    /** @param array<string, float|int> $metrics */
    private function summary(array $metrics): array
    {
        $method = new ReflectionMethod(ChannelMasterController::class, 'temuMetricsToLiveSummary');
        $method->setAccessible(true);

        return $method->invoke(app(ChannelMasterController::class), $metrics);
    }

    public function test_temu2_divides_gpft_by_full_price_sales_not_l30_sales(): void
    {
        $out = $this->summary([
            'sales' => 44718.36,
            'full_sales' => 49927.26,
            'pft' => 18070.59,
            'cogs' => 12888.50,
            'orders' => 1760,
            'qty' => 2276,
        ]);

        $this->assertSame(36.19, $out['gpft_percent']);
        $this->assertSame(140.21, $out['groi_percent']);
        // L30 Sales badge stays the reported figure.
        $this->assertSame(44718.36, $out['total_revenue']);
    }

    public function test_channels_without_a_full_sales_figure_divide_by_sales(): void
    {
        $out = $this->summary([
            'sales' => 41359.53,
            'pft' => 10088.80,
            'cogs' => 14513.70,
            'orders' => 1580,
            'qty' => 2437,
        ]);

        $this->assertSame(24.39, $out['gpft_percent']);
    }

    public function test_full_sales_equal_to_sales_changes_nothing(): void
    {
        $out = $this->summary([
            'sales' => 4963.71,
            'full_sales' => 4963.71,
            'pft' => 1145.19,
            'cogs' => 1531.19,
        ]);

        $this->assertSame(23.07, $out['gpft_percent']);
    }
}
