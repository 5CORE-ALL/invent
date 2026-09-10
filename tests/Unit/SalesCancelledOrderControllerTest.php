<?php

namespace Tests\Unit;

use App\Http\Controllers\Channels\SalesCancelledOrderController;
use ReflectionMethod;
use Tests\TestCase;

class SalesCancelledOrderControllerTest extends TestCase
{
    public function test_cancelled_flag_keeps_row(): void
    {
        $this->assertTrue($this->keep(['is_cancelled' => true, 'status' => 'Shipped']));
    }

    public function test_cancelled_status_keeps_row(): void
    {
        $this->assertTrue($this->keep(['status' => 'Cancelled']));
        $this->assertTrue($this->keep(['status_label' => 'CANCELED']));
    }

    public function test_refunded_and_voided_status_keep_row(): void
    {
        $this->assertTrue($this->keep(['status' => 'Refunded']));
        $this->assertTrue($this->keep(['status_label' => 'Fully Refunded']));
        $this->assertTrue($this->keep(['status' => 'Voided']));
    }

    public function test_active_order_is_dropped(): void
    {
        $this->assertFalse($this->keep(['status' => 'Shipped', 'status_label' => 'In Transit']));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function keep(array $row): bool
    {
        $controller = $this->app->make(SalesCancelledOrderController::class);
        $method = new ReflectionMethod($controller, 'orderRowIsCancelledForPage');
        $method->setAccessible(true);

        return (bool) $method->invoke($controller, $row);
    }
}
