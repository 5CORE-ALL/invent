<?php

namespace Tests\Unit;

use App\Http\Controllers\Channels\SalesOrderFulfillmentController;
use App\Services\SheinApiService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SheinOrderDateTimezoneTest extends TestCase
{
    public function test_naive_shein_order_time_stays_shanghai_wall_clock(): void
    {
        $this->assertSame(
            '2026-09-18 02:13:00',
            SheinApiService::apiDateTimeString('2026-09-18 02:13:00')
        );
    }

    public function test_utc_shein_order_time_converts_to_shanghai(): void
    {
        $this->assertSame(
            '2026-09-18 02:13:00',
            SheinApiService::apiDateTimeString('2026-09-17 18:13:00Z')
        );
    }

    public function test_sof_reads_shein_digits_as_shanghai_not_pacific(): void
    {
        $ref = new ReflectionClass(SalesOrderFulfillmentController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('formatOrderDate');

        $this->assertSame(
            '2026-09-17 14:13:00',
            $method->invoke($ctrl, '2026-09-18 02:13:00', SheinApiService::API_TIMEZONE)
        );

        $eloquentPacific = Carbon::parse('2026-09-18 02:13:00', 'America/Los_Angeles');
        $this->assertSame(
            '2026-09-17 14:13:00',
            $method->invoke($ctrl, $eloquentPacific, SheinApiService::API_TIMEZONE)
        );
    }

    public function test_pacific_yesterday_sql_bounds_are_shanghai_not_pacific_wall_clock(): void
    {
        $start = Carbon::parse('2026-09-18 00:00:00', 'America/Los_Angeles');
        $end = Carbon::parse('2026-09-18 23:59:59', 'America/Los_Angeles');

        [$from, $to] = SheinApiService::shanghaiSqlBounds($start, $end);

        $this->assertSame('2026-09-18 15:00:00', $from);
        $this->assertSame('2026-09-19 14:59:59', $to);
    }

    public function test_stored_shanghai_morning_maps_to_previous_pacific_day(): void
    {
        $this->assertSame(
            '2026-09-17',
            SheinApiService::pacificDateFromStored('2026-09-18 02:13:00')
        );
        $this->assertSame(
            '2026-09-18',
            SheinApiService::pacificDateFromStored('2026-09-18 16:00:00')
        );
    }
}
