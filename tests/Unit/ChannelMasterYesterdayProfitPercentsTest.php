<?php

namespace Tests\Unit;

use App\Http\Controllers\Channels\ChannelMasterController;
use ReflectionMethod;
use Tests\TestCase;

class ChannelMasterYesterdayProfitPercentsTest extends TestCase
{
    public function test_percents_come_from_the_measured_one_day_dollars(): void
    {
        // Temu 2, Oct 6: measured $265.72 profit on $429.78 COGS and $1,167.12 sales.
        $out = $this->percents(265.72, 429.78, 11.67, 1167.12);

        $this->assertSame(61.8, $out['groi']);
        $this->assertSame(21.8, $out['npft']);
        $this->assertSame(59.1, $out['nroi']);
    }

    public function test_unmeasured_columns_return_nulls_so_callers_fall_back(): void
    {
        $this->assertSame(
            ['groi' => null, 'npft' => null, 'nroi' => null],
            $this->percents(null, null, null, null)
        );
        $this->assertSame(
            ['groi' => null, 'npft' => null, 'nroi' => null],
            $this->percents(100.0, null, 0.0, 500.0)
        );
    }

    public function test_a_measured_day_with_no_sales_or_cogs_is_not_reported_as_zero(): void
    {
        $this->assertSame(
            ['groi' => null, 'npft' => null, 'nroi' => null],
            $this->percents(0.0, 0.0, 0.0, 0.0)
        );
    }

    public function test_sales_without_cogs_still_yields_npft(): void
    {
        $out = $this->percents(40.0, 0.0, 10.0, 200.0);

        $this->assertNull($out['groi']);
        $this->assertSame(15.0, $out['npft']);
        $this->assertNull($out['nroi']);
    }

    /**
     * @return array{groi: float|null, npft: float|null, nroi: float|null}
     */
    private function percents(mixed $pft, mixed $cogs, mixed $spend, mixed $sales): array
    {
        $controller = app(ChannelMasterController::class);
        $method = new ReflectionMethod($controller, 'yesterdayProfitPercents');
        $method->setAccessible(true);

        return $method->invoke($controller, $pft, $cogs, $spend, $sales);
    }
}
