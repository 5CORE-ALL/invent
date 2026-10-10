<?php

namespace Tests\Unit;

use App\Support\Inv5coreLedger;
use App\Support\Inv5coreMarketplaceOrders;
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

    public function test_marketplace_orders_cover_the_order_management_channels(): void
    {
        $sources = array_column(Inv5coreMarketplaceOrders::definitions(), 'source');

        $this->assertContains('amazon', $sources);
        $this->assertContains('ebay1', $sources);
        $this->assertContains('temu', $sources);
        $this->assertContains('wayfair', $sources);
        $this->assertNotContains('shopify', $sources);
        $this->assertSame('mp:amazon', Inv5coreMarketplaceOrders::sourceKey('amazon'));
        $this->assertSame('mp:amazon:reversal', Inv5coreMarketplaceOrders::reversalKey('amazon'));
        $this->assertSame('mp:amazon:open', Inv5coreMarketplaceOrders::openKey('amazon'));
        $this->assertSame('mp:amazon:fulfilled', Inv5coreMarketplaceOrders::fulfilledKey('amazon'));
    }

    public function test_order_created_commits_stock_and_fulfill_drops_on_hand(): void
    {
        $this->assertFalse(Inv5coreLedger::statusIsFulfilled('UNSHIPPED'));
        $this->assertFalse(Inv5coreLedger::statusIsFulfilled('to be shipped'));
        $this->assertFalse(Inv5coreLedger::statusIsFulfilled('SHIPPING'));
        $this->assertTrue(Inv5coreLedger::statusIsFulfilled('SHIPPED'));
        $this->assertTrue(Inv5coreLedger::statusIsFulfilled('IN_TRANSIT'));
        $this->assertTrue(Inv5coreLedger::statusIsFulfilled('FULFILLED'));

        $created = Inv5coreLedger::nextStates(128, 0, 0, 0, 1);
        $this->assertSame(128.0, $created['on_hand']);
        $this->assertSame(1.0, $created['committed']);
        $this->assertSame(127.0, $created['available']);

        $fulfilled = Inv5coreLedger::nextStates($created['on_hand'], $created['committed'], 0, -1, -1);
        $this->assertSame(127.0, $fulfilled['on_hand']);
        $this->assertSame(0.0, $fulfilled['committed']);
        $this->assertSame(127.0, $fulfilled['available']);
        $this->assertSame('Order created (#3546919)', Inv5coreLedger::historyActivity('order_created', '3546919'));
        $this->assertSame('Order fulfilled (#3546919)', Inv5coreLedger::historyActivity('order_fulfilled', '#3546919'));
        $this->assertSame('Shopify', Inv5coreLedger::historyCreatedBy('opening', 'Shopify', ''));
        $this->assertSame('Amazon', Inv5coreLedger::historyCreatedBy('order_created', 'Amazon', '5Core Inventory'));
        $this->assertSame('Doba', Inv5coreLedger::historyCreatedBy('order_fulfilled', 'Doba', ''));
        $this->assertSame('eBay', Inv5coreLedger::historyCreatedBy('return', 'eBay', ''));
        $this->assertSame('Alex', Inv5coreLedger::historyCreatedBy('adjustment', 'App', 'Alex'));
    }

    public function test_order_history_uses_the_marketplace_and_order_number(): void
    {
        $this->assertNull(Inv5coreLedger::shopifyOrderChannel('doba', ''));
        $this->assertNull(Inv5coreLedger::shopifyOrderChannel('145019994113', 'Doba'));
        $this->assertNull(Inv5coreLedger::shopifyOrderChannel('web', 'amazon'));
        $this->assertSame('Shopify', Inv5coreLedger::shopifyOrderChannel('web', ''));
        $this->assertSame('Shopify', Inv5coreLedger::shopifyOrderChannel('shopify_draft_order', ''));

        $events = Inv5coreLedger::orderMovementEvents(1, 'SHIPPED', '3546919', 'Doba', 1000, 2000);
        $rows = Inv5coreLedger::replayHistory(35, 0, 0, $events);

        $this->assertSame('order_fulfilled', $rows[0]['txn_type']);
        $this->assertSame('Doba', $rows[0]['channel']);
        $this->assertSame(-1.0, $rows[0]['on_hand_delta']);
        $this->assertSame(35.0, $rows[0]['on_hand_after']);
        $this->assertSame(1.0, $rows[0]['available_delta']);
        $this->assertSame(35.0, $rows[0]['available_after']);
        $this->assertSame(0.0, $rows[0]['committed_after']);
        $this->assertSame('Order fulfilled (#3546919)', Inv5coreLedger::historyActivity($rows[0]['txn_type'], $rows[0]['reference']));
        $this->assertSame('Doba', Inv5coreLedger::historyCreatedBy($rows[0]['txn_type'], $rows[0]['channel'], ''));

        $this->assertSame('order_created', $rows[1]['txn_type']);
        $this->assertSame(36.0, $rows[1]['on_hand_after']);
        $this->assertSame(1.0, $rows[1]['committed_after']);
        $this->assertSame(-1.0, $rows[1]['available_delta']);
        $this->assertSame('Order created (#3546919)', Inv5coreLedger::historyActivity($rows[1]['txn_type'], $rows[1]['reference']));
    }
}
