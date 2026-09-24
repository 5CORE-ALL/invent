<?php

namespace Tests\Unit;

use App\Http\Controllers\Channels\SalesOrderFulfillmentController;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class BestBuyOrderDateTimezoneTest extends TestCase
{
    public function test_mirakl_utc_wall_clock_displays_as_eastern_not_pacific(): void
    {
        $ref = new ReflectionClass(SalesOrderFulfillmentController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();
        $utc = $ref->getMethod('utcWallClockDisplayedDate');
        $format = $ref->getMethod('formatOrderDate');

        $order = new class
        {
            public function getRawOriginal(string $column): ?string
            {
                return match ($column) {
                    'order_created_at' => '2026-09-24 18:38:00',
                    'order_updated_at' => '2026-09-24 14:31:00',
                    default => null,
                };
            }
        };

        // Digits 18:38 and 14:31 read as Pacific become 9:38 PM and 5:31 PM Eastern.
        $this->assertSame(
            '2026-09-24 21:38:00',
            Carbon::parse('2026-09-24 18:38:00', 'America/Los_Angeles')->timezone('America/New_York')->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-09-24 17:31:00',
            Carbon::parse('2026-09-24 14:31:00', 'America/Los_Angeles')->timezone('America/New_York')->format('Y-m-d H:i:s')
        );

        $created = $utc->invoke($ctrl, $order, 'order_created_at', null, []);
        $this->assertSame('2026-09-24T18:38:00Z', $created);
        $this->assertSame('2026-09-24 14:38:00', $format->invoke($ctrl, $created, 'UTC'));

        $updated = $utc->invoke($ctrl, $order, 'order_updated_at', null, []);
        $this->assertSame('2026-09-24 10:31:00', $format->invoke($ctrl, $updated, 'UTC'));
    }
}
