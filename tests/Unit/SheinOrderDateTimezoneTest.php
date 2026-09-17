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
}
