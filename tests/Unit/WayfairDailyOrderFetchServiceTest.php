<?php

namespace Tests\Unit;

use App\Services\WayfairDailyOrderFetchService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WayfairDailyOrderFetchServiceTest extends TestCase
{
    public function test_advance_cursor_moves_forward_from_last_po_date(): void
    {
        $svc = app(WayfairDailyOrderFetchService::class);

        $this->assertSame(
            '2026-09-18T15:30:00Z',
            $svc->advanceCursor('2026-09-16T00:00:00Z', '2026-09-18 15:30:00 +00:00', false)
        );
    }

    public function test_advance_cursor_adds_a_second_when_page_is_all_duplicates(): void
    {
        $svc = app(WayfairDailyOrderFetchService::class);

        $this->assertSame(
            '2026-09-16T00:00:01Z',
            $svc->advanceCursor('2026-09-16T00:00:00Z', '2026-09-16T00:00:00Z', true)
        );
    }

    public function test_dropship_fetch_walks_pages_and_keeps_september_orders(): void
    {
        Http::fake(function () {
            static $calls = 0;
            $calls++;
            if ($calls === 1) {
                return Http::response([
                    'data' => [
                        'getDropshipPurchaseOrders' => [
                            $this->po('CS681297428', '2026-09-18T10:00:00Z', 'SS SQ WH', 52.99),
                            $this->po('CS681322233', '2026-09-18T11:00:00Z', 'KS 1X BLU', 14.50),
                        ],
                    ],
                ], 200);
            }

            return Http::response([
                'data' => ['getDropshipPurchaseOrders' => []],
            ], 200);
        });

        $result = app(WayfairDailyOrderFetchService::class)->fetchViaDropship(
            'test-token',
            Carbon::parse('2026-09-16', 'UTC'),
            false
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('dropship', $result['source']);
        $this->assertCount(2, $result['orders']);
        $this->assertSame('CS681297428', $result['orders'][0]['poNumber']);
        $this->assertSame('SS SQ WH', $result['orders'][0]['products'][0]['partNumber']);
    }

    public function test_dropship_graphql_error_is_not_treated_as_empty_success(): void
    {
        Http::fake([
            'https://api.wayfair.com/v1/graphql' => Http::response([
                'errors' => [['message' => 'Access Denied']],
                'data' => ['getDropshipPurchaseOrders' => null],
            ], 200),
        ]);

        $result = app(WayfairDailyOrderFetchService::class)->queryDropshipPage(
            'test-token',
            '2026-09-16T00:00:00Z',
            false
        );

        $this->assertFalse($result['ok']);
        $this->assertSame([], $result['orders']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * @return array<string, mixed>
     */
    private function po(string $number, string $date, string $sku, float $price): array
    {
        return [
            'poNumber' => $number,
            'poDate' => $date,
            'products' => [
                ['partNumber' => $sku, 'quantity' => 1, 'price' => $price],
            ],
        ];
    }
}
