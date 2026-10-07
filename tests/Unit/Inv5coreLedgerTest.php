<?php

namespace Tests\Unit;

use App\Support\Inv5coreLedger;
use PHPUnit\Framework\TestCase;

class Inv5coreLedgerTest extends TestCase
{
    public function test_parent_sku_matches_cp_master_rule(): void
    {
        $this->assertTrue(Inv5coreLedger::isParentSku('PARENT DRUM KIT'));
        $this->assertTrue(Inv5coreLedger::isParentSku('parent mic'));
        $this->assertFalse(Inv5coreLedger::isParentSku('SP 12120 4OHM GTR'));
    }

    public function test_cancelled_and_refunded_lines_are_not_sales(): void
    {
        $this->assertTrue(Inv5coreLedger::statusSkipsSale('Refunded'));
        $this->assertTrue(Inv5coreLedger::statusSkipsSale('cancelled'));
        $this->assertTrue(Inv5coreLedger::statusSkipsSale('VOID'));
        $this->assertFalse(Inv5coreLedger::statusSkipsSale('paid'));
        $this->assertFalse(Inv5coreLedger::statusSkipsSale(null));
    }

    public function test_only_rows_after_the_opening_watermark_change_on_hand(): void
    {
        $this->assertFalse(Inv5coreLedger::saleIdAffectsBalance(40, 40));
        $this->assertFalse(Inv5coreLedger::saleIdAffectsBalance(12, null));
        $this->assertTrue(Inv5coreLedger::saleIdAffectsBalance(41, 40));
    }

    public function test_a_posted_sale_is_reversed_when_the_order_is_cancelled(): void
    {
        $this->assertTrue(Inv5coreLedger::needsReversal(true, true));
        $this->assertFalse(Inv5coreLedger::needsReversal(false, true));
        $this->assertFalse(Inv5coreLedger::needsReversal(true, false));
    }

    public function test_adjustment_deltas_and_image_priority(): void
    {
        $this->assertSame(-3.0, Inv5coreLedger::deltaForSet(10, 7));
        $this->assertSame(4.0, Inv5coreLedger::deltaForAdd(4));
        $this->assertSame(-2.0, Inv5coreLedger::deltaForSubtract(2));
        $this->assertSame(8.5, Inv5coreLedger::applyDelta(10, -1.5));
        $this->assertSame('/storage/products/a.jpg', Inv5coreLedger::imagePath('storage/products/a.jpg', 'https://cdn.example/a.jpg'));
        $this->assertSame('https://cdn.example/a.jpg', Inv5coreLedger::imagePath(null, 'https://cdn.example/a.jpg'));
    }
}
