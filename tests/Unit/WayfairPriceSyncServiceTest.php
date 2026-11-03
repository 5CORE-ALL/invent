<?php

namespace Tests\Unit;

use App\Services\Wayfair\WayfairPriceSyncService;
use PHPUnit\Framework\TestCase;

class WayfairPriceSyncServiceTest extends TestCase
{
    private function po(string $number, string $date, array $products): array
    {
        return ['poNumber' => $number, 'poDate' => $date, 'products' => $products];
    }

    public function test_newest_po_line_wins_per_sku(): void
    {
        $orders = [
            $this->po('CS1', '2026-10-01T10:00:00Z', [['partNumber' => 'ABC-1', 'price' => 20.5]]),
            $this->po('CS3', '2026-10-20T10:00:00Z', [['partNumber' => 'abc-1 ', 'price' => 24.99]]),
            $this->po('CS2', '2026-10-10T10:00:00Z', [['partNumber' => 'ABC-1', 'price' => 22]]),
        ];

        $latest = (new WayfairPriceSyncService)->latestPriceBySku($orders);

        $this->assertCount(1, $latest);
        $this->assertSame(24.99, $latest['ABC-1']['price']);
        $this->assertSame('CS3', $latest['ABC-1']['po_number']);
    }

    public function test_event_lines_and_bad_prices_are_ignored(): void
    {
        $orders = [
            $this->po('CS1', '2026-10-01T10:00:00Z', [['partNumber' => 'SKU-A', 'price' => 30]]),
            $this->po('CS2', '2026-10-15T10:00:00Z', [
                ['partNumber' => 'SKU-A', 'price' => 18, 'event' => ['id' => 'E1', 'name' => 'Way Day']],
                ['partNumber' => 'SKU-B', 'price' => 0],
                ['partNumber' => 'SKU-C', 'price' => null],
                ['partNumber' => '', 'price' => 9],
            ]),
        ];

        $svc = new WayfairPriceSyncService;
        $latest = $svc->latestPriceBySku($orders);

        $this->assertSame(['SKU-A'], array_keys($latest));
        $this->assertSame(30.0, $latest['SKU-A']['price']);

        $withEvents = $svc->latestPriceBySku($orders, true);
        $this->assertSame(18.0, $withEvents['SKU-A']['price']);
    }

    public function test_orders_without_a_date_are_skipped_and_nbsp_skus_match(): void
    {
        $orders = [
            ['poNumber' => 'CS1', 'products' => [['partNumber' => 'X', 'price' => 5]]],
            $this->po('CS2', '2026-10-15T10:00:00Z', [['partNumber' => "AB\u{00A0}12", 'price' => 7.25]]),
        ];

        $latest = (new WayfairPriceSyncService)->latestPriceBySku($orders);

        $this->assertSame(['AB 12'], array_keys($latest));
        $this->assertSame(7.25, $latest['AB 12']['price']);
    }
}
