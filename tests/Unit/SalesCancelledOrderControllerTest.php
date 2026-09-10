<?php

namespace Tests\Unit;

use App\Http\Controllers\Channels\SalesCancelledOrderController;
use ReflectionMethod;
use Tests\TestCase;

class SalesCancelledOrderControllerTest extends TestCase
{
    public function test_cancelled_status_keeps_row(): void
    {
        $this->assertTrue($this->keep(['status' => 'Cancelled']));
        $this->assertTrue($this->keep(['status_label' => 'CANCELED']));
        $this->assertTrue($this->keep(['status' => 'Cancel Requested']));
    }

    public function test_cancelled_order_with_delivered_overlay_is_kept(): void
    {
        $this->assertTrue($this->keep([
            'status' => 'Canceled',
            'status_label' => 'Delivered',
        ]));
    }

    public function test_delivered_is_dropped_even_if_cancelled_flag(): void
    {
        $this->assertFalse($this->keep([
            'is_cancelled' => true,
            'status' => 'Delivered',
            'status_label' => 'Delivered',
        ]));
    }

    public function test_refunded_and_voided_are_dropped(): void
    {
        $this->assertFalse($this->keep(['status' => 'Refunded']));
        $this->assertFalse($this->keep(['status_label' => 'Fully Refunded']));
        $this->assertFalse($this->keep(['status' => 'Voided']));
    }

    public function test_shipped_is_dropped(): void
    {
        $this->assertFalse($this->keep(['status' => 'Shipped', 'status_label' => 'In Transit']));
    }

    public function test_present_rows_restore_cancel_label_and_drop_others(): void
    {
        $out = $this->present([
            ['status' => 'Canceled', 'status_label' => 'Delivered', 'amount' => 10],
            ['status' => 'CANCELED', 'status_label' => 'In Transit', 'amount' => 5],
            ['status' => 'Delivered', 'status_label' => 'Delivered', 'amount' => 99],
            ['status' => 'Refunded', 'status_label' => 'Refunded', 'amount' => 8],
        ]);

        $this->assertCount(2, $out);
        $this->assertSame('Canceled', $out[0]['status_label']);
        $this->assertSame('CANCELED', $out[1]['status_label']);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function keep(array $row): bool
    {
        $controller = $this->app->make(SalesCancelledOrderController::class);
        $method = new ReflectionMethod($controller, 'orderRowIsCancelledForPage');

        return (bool) $method->invoke($controller, $row);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function present(array $rows): array
    {
        $controller = $this->app->make(SalesCancelledOrderController::class);
        $method = new ReflectionMethod($controller, 'presentCancelledRows');

        return $method->invoke($controller, $rows);
    }
}
