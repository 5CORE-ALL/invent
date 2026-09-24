<?php

namespace Tests\Unit;

use App\Console\Concerns\CalculatesAmazonFbaBidUpdates;
use PHPUnit\Framework\TestCase;

class FbaInventoryForBidTest extends TestCase
{
    public function test_fba_quantity_is_used_when_shopify_inv_is_zero(): void
    {
        $inv = $this->inventory(
            (object) ['quantity_available' => 101],
            (object) ['inv' => 0]
        );

        $this->assertSame(101, $inv);
    }

    public function test_shopify_inv_is_used_when_fba_quantity_is_zero(): void
    {
        $inv = $this->inventory(
            (object) ['quantity_available' => 0],
            (object) ['inv' => 4]
        );

        $this->assertSame(4, $inv);
    }

    public function test_both_zero_stays_zero(): void
    {
        $this->assertSame(0, $this->inventory((object) ['quantity_available' => 0], null));
    }

    private function inventory(object $fba, ?object $shopify): int
    {
        $job = new class
        {
            use CalculatesAmazonFbaBidUpdates;

            public function inv(object $fba, ?object $shopify): int
            {
                return $this->fbaInventoryForBid($fba, $shopify);
            }
        };

        return $job->inv($fba, $shopify);
    }
}
